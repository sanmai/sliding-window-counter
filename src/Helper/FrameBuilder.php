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

namespace SlidingWindowCounter\Helper;

use InvalidArgumentException;
use Tumblr\Chorus;

use function max;

/**
 * Handles timestamp generation for the sliding window counter.
 */
final class FrameBuilder
{
    /** @var int<1, max> The size of the window in seconds. */
    private int $window_size;

    /** @var int Maximum number of seconds for the buckets to last in cache. */
    private int $observation_period;

    /** @var Chorus\TimeKeeper The timekeeper instance. */
    private Chorus\TimeKeeper $time_keeper;

    /**
     * FrameBuilder constructor.
     *
     * @param int<1, max> $window_size the size of the window in seconds
     * @param int $observation_period maximum number of seconds for the buckets to last in cache
     * @param Chorus\TimeKeeper $time_keeper the timekeeper instance
     */
    public function __construct(int $window_size, int $observation_period, Chorus\TimeKeeper $time_keeper)
    {
        $this->window_size = $window_size;
        $this->observation_period = $observation_period;
        $this->time_keeper = $time_keeper;
    }

    /**
     * Builds a new frame instance.
     *
     * @param int $time the frame's reference time
     */
    public function newFrame(int $time): Frame
    {
        return new Frame($time, $this->window_size);
    }

    /**
     * Generates a range of valid frames for the given start time.
     *
     * @param int $start_time The start time
     * @param null|int $end_time The optional end time; defaults to the current time
     *
     * @return iterable<Frame>
     *
     * @throws InvalidArgumentException If the start time is in the future
     */
    public function generateFrames(int $start_time = 0, ?int $end_time = null): iterable
    {
        if (null !== $end_time && $end_time < $start_time) {
            throw new InvalidArgumentException("End time cannot be before start time (start: {$start_time}, end: {$end_time})");
        }

        $end_time ??= $this->time_keeper->getCurrentUnixTime();

        // Start time cannot be in the future
        if ($start_time > $end_time) {
            throw new InvalidArgumentException("Start time cannot be in the future (start: {$start_time}, end: {$end_time})");
        }

        // We cannot be looking at records beyond the max lifetime
        $started_tracking = $this->time_keeper->getCurrentUnixTime() - $this->observation_period;
        $start_time = max($start_time, $started_tracking);

        $window_boundary = $start_time % $this->window_size;

        // Clamp at the window boundary to simplify the logic
        $start_time -= $window_boundary;

        // Extend the start time to the size of the window to skip the null fetch
        // (since Memcached's Increment doesn't extend the expiration this record will be deleted)
        if ($start_time < $started_tracking) {
            $start_time += $this->window_size;
        }

        do {
            // Keying to start time for legacy reasons
            yield $start_time => $this->newFrame($start_time + $window_boundary);
            $start_time += $this->window_size;
        } while ($start_time <= $end_time);
    }
}
