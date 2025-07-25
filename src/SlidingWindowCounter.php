<?php declare(strict_types=1);
/**
 * Copyright 2023 Automattic, Inc.
 * Copyright 2025 Alexey Kopytko
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace SlidingWindowCounter;

use InvalidArgumentException;
use Pipeline\Helper\RunningVariance;
use Pipeline\Standard;
use DuoClock\DuoClock;
use DuoClock\Interfaces\DuoClockInterface;

use function is_int;
use function Pipeline\take;
use function sprintf;

/**
 * Sliding window counter and time series.
 */
final class SlidingWindowCounter
{
    /** @var string Memcached cache name to use for buckets. */
    private string $cache_name;

    /** @var int The size of the window in seconds. */
    private int $window_size;

    /** @var int Maximum number of seconds for the buckets to last in cache. */
    private int $observation_period;

    /** @var Cache\CounterCache The counter cache instance */
    private Cache\CounterCache $counter_cache;

    /** @var DuoClockInterface The clock instance. */
    private DuoClockInterface $clock;

    /** @var Helper\FrameBuilder The frame builder. */
    private Helper\FrameBuilder $frame_builder;

    /**
     * Construct a new `SlidingWindowCounter`.
     *
     * @param string $cache_name the cache name to use for buckets
     * @param int $window_size the size of the sampling window in seconds
     * @param int $observation_period the maximum number of seconds for counters to persist in the cache
     * @param null|DuoClockInterface $clock the clock instance
     * @param null|Helper\FrameBuilder $frame_builder the timestamp helper
     *
     * @throws InvalidArgumentException if a blank string is provided for the cache name
     * @throws InvalidArgumentException if a non-positive integer is provided for the cache lifetime
     */
    public function __construct(
        string $cache_name,
        int $window_size,
        int $observation_period,
        Cache\CounterCache $counter_cache,
        ?DuoClockInterface $clock = null,
        ?Helper\FrameBuilder $frame_builder = null
    ) {
        if ('' === $cache_name) {
            throw new InvalidArgumentException('Cache name expected to be a non-blank string');
        }
        if ($window_size < 1) {
            throw new InvalidArgumentException(
                sprintf('Window size expected to be a strictly positive integer, received: %d', $window_size)
            );
        }
        if ($observation_period < 1) {
            throw new InvalidArgumentException(
                sprintf('Observation period expected to be a strictly positive integer, received: %d', $observation_period)
            );
        }

        $this->cache_name = $cache_name;
        $this->window_size = $window_size;
        $this->observation_period = $observation_period;
        $this->counter_cache = $counter_cache;

        // Optional dependencies
        $this->clock = $clock ?? new DuoClock();

        $this->frame_builder = $frame_builder ?? new Helper\FrameBuilder($window_size, $observation_period, $this->clock);
    }

    /**
     * Increment a counter.
     *
     * @param string $bucket_key The bucket key, such as IP subnet, ASN, or anything that works for a bucket key
     * @param int $step The step
     * @param null|int $at_time The optional time to increment the counter at
     *
     * @return bool|int
     *
     * @throws InvalidArgumentException If the time provided is too far in the past
     */
    public function increment(string $bucket_key, int $step = 1, ?int $at_time = null)
    {
        if (null !== $at_time && $at_time < $this->clock->time() - $this->observation_period) {
            throw new InvalidArgumentException(
                sprintf(
                    'The time provided (%d) is too far in the past (current time: %d, observation period: %d)',
                    $at_time,
                    $this->clock->time(),
                    $this->observation_period
                )
            );
        }

        $at_time ??= $this->clock->time();

        $cache_key = $this->frame_builder
            ->newFrame($at_time)
            ->getCacheKey(
                $bucket_key,
                $this->observation_period
            );

        return $this->counter_cache->increment(
            $this->cache_name,
            $cache_key,
            $this->observation_period,
            $step
        );
    }

    /**
     * Returns all raw values between two timestamps, keyed to start time of the window.
     *
     * @internal Use getTimeSeries() instead
     *
     * @param string $bucket_key The bucket key
     * @param null|int $start_time The optional start time; leaving out the start time will omit the leading null values
     * @param null|int $end_time The optional end time; defaults to the current time
     *
     * @return iterable<int, Helper\Frame>|Standard
     */
    private function generateMaterialFrames(string $bucket_key, ?int $start_time = null, ?int $end_time = null)
    {
        $pipeline = take($this->frame_builder->generateFrames($start_time ?? 0, $end_time))
            ->cast(
                fn (Helper\Frame $frame) => $frame->setValue($this->cacheGet(
                    $this->cache_name,
                    $frame->getCacheKey($bucket_key, $this->observation_period)
                ))
            );

        // If the start time is not set, we skip all null values until we get to the first non-null value
        // This is useful for when we want to get all sensible values, but we don't want to include the
        // leading null values thus skewing the statistics
        if (null === $start_time) {
            $pipeline->skipWhile(fn (Helper\Frame $frame) => $frame->hasNullValue());
        }

        return $pipeline;
    }

    /**
     * Returns a list of extrapolated discrete-time values between two timestamps.
     *
     * @param string $bucket_key The bucket key
     * @param null|int $start_time The optional start time; leaving out the start time will omit the leading null values
     * @param null|int $end_time The optional end time; defaults to the current time
     *
     * @return iterable<int, float>
     */
    public function getTimeSeries(string $bucket_key, ?int $start_time = null, ?int $end_time = null): iterable
    {
        // Capture the current time to decide when to stop extrapolating
        // We can't use the provided end time for this purpose because it could be in the past necessitating extrapolation
        $now = $this->clock->time();

        /** @var null|Helper\Frame $previous_frame */
        $previous_frame = null;

        foreach ($this->generateMaterialFrames($bucket_key, $start_time, $end_time) as $frame) {
            /** @var Helper\Frame $frame */
            if (null === $previous_frame) {
                $previous_frame = $frame;

                // To avoid aliasing artifacts we don't approximate the value for the oldest frame
                // There could be a way to solve this issue, but for now we skip all initial frames
                continue;
            }

            $full_value = 0.0;

            foreach ($frame->getFrameOverlap() as $frame_start => $seconds) {
                // Handle the previous frame
                if ($frame_start === $previous_frame->getStart()) {
                    $full_value += $previous_frame->getValue() * $seconds / $this->window_size;

                    continue;
                }

                // If the frame is the most recent frame available, add the whole value without extrapolation
                if ($frame->getStart() + $seconds >= $now) {
                    $full_value += $frame->getValue();

                    continue;
                }

                // Else add the value with extrapolation
                $full_value += $frame->getValue() * $seconds / $this->window_size;
            }

            yield $frame->getTime() => $full_value;

            $previous_frame = $frame;
        }
    }

    /**
     * Returns the latest extrapolated value in the window of observation for a given bucket key.
     *
     * @param string $bucket_key The bucket key, such as IP subnet, ASN, or anything that works for a bucket key
     */
    public function getLatestValue(string $bucket_key): float
    {
        return take($this->getTimeSeries(
            $bucket_key,
            $this->clock->time() - $this->window_size
        ))->fold(0.0);
    }

    /**
     * Tests the standard deviation against the acceptable range and returns the alert message.
     *
     * @param string $bucket_key The bucket key
     * @param float|int $sensitivity The sensitivity: 3 = low (99.7%), 2 = standard (95%) 1 = high (68% deviation triggers alert)
     * @param null|int $start_time The optional start time; leaving out the start time will omit the leading null values
     */
    public function detectAnomaly(string $bucket_key, float|int $sensitivity = 2, ?int $start_time = null): AnomalyDetectionResult
    {
        $variance = $this->getHistoricVariance($bucket_key, $start_time);

        return new AnomalyDetectionResult(
            $variance->getCount(),
            $variance->getStandardDeviation(),
            $variance->getMean(),
            $this->getLatestValue($bucket_key),
            $sensitivity
        );
    }

    /**
     * Computes various statistics for a given bucket key and time window using a numerically stable online algorithm.
     *
     * @see https://en.wikipedia.org/wiki/Algorithms_for_calculating_variance#Welford's_online_algorithm
     *
     * @param string $bucket_key The bucket key
     * @param null|int $start_time The optional start time; defaults to the current time minus the observation period
     * @param null|int $end_time The optional end time; defaults to the current time
     */
    public function getHistoricVariance(string $bucket_key, ?int $start_time = null, ?int $end_time = null): RunningVariance
    {
        return take($this->getTimeSeries($bucket_key, $start_time, $end_time))
            // Skip the most recent value
            ->slice(0, -1)
            ->finalVariance();
    }

    /**
     * Typesafe method to get values from the cache.
     *
     * @param string $cache_name memcached cache name to use
     * @param string $cache_key key to use in the cache
     *
     * @return null|int The current value
     */
    private function cacheGet(string $cache_name, string $cache_key): ?int
    {
        $value = $this->counter_cache->get($cache_name, $cache_key);

        return is_int($value) ? $value : null;
    }
}
