<?php declare(strict_types=1);
/**
 * Copyright 2023 Brandon Frohs
 * Copyright 2025 Alexey Kopytko
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * Alternatively, you may use this file under the terms of the
 * GNU General Public License as published by the Free Software Foundation,
 * either version 2 of the License, or (at your option) any later version.
 */

namespace Tests\SlidingWindowCounter;

use SlidingWindowCounter\Cache\CounterCache;
use SlidingWindowCounter\Helper\Frame;
use SlidingWindowCounter\Helper\FrameBuilder;
use SlidingWindowCounter\SlidingWindowCounter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Tests\SlidingWindowCounter\Cache\FakeCache;
use DuoClock\TimeSpy;

use function array_keys;
use function iterator_to_array;
use function max;
use function Pipeline\take;
use function range;
use function sprintf;

/**
 * @covers \SlidingWindowCounter\SlidingWindowCounter
 *
 * @internal
 */
final class SlidingWindowCounterTest extends TestCase
{
    /**
     * Test it throws on invalid cache name.
     */
    public function testItThrowsOnInvalidCacheName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cache name expected to be a non-blank string');

        new SlidingWindowCounter('', 0, 0, new FakeCache(), new TimeSpy());
    }

    /**
     * Test it throws on invalid window size.
     */
    public function testItThrowsOnInvalidWindowSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Window size expected to be a strictly positive integer, received: 0');

        new SlidingWindowCounter('test', 0, 0, new FakeCache(), new TimeSpy());
    }

    /**
     * Test it throws on invalid observation period.
     */
    public function testItThrowsOnInvalidObservationPeriod(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Observation period expected to be a strictly positive integer, received: 0');

        new SlidingWindowCounter('test', 1, 0, new FakeCache(), new TimeSpy());
    }

    /**
     * Test `increment`.
     */
    public function testIncrement(): void
    {
        // Prime number so we can test the modulo
        $time_keeper = new TimeSpy(27644437);

        $window_size = 60;
        $observation_period = 1000;
        $cache_name = 'testing';

        // 27644437 / 60 = 460740.61667, so we get 460740
        $expected_bucket_key = sprintf('test:%d:%d:460740', $observation_period, $window_size);
        $cache_increment_expected_arguments = [$cache_name, $expected_bucket_key, $observation_period, 123];

        $cache = $this->createMock(CounterCache::class);
        $cache->expects($this->once())
            ->method('increment')
            ->with(...$cache_increment_expected_arguments)
            ->willReturn(42);

        $counter = new SlidingWindowCounter($cache_name, $window_size, $observation_period, $cache, $time_keeper);

        $this->assertSame(42, $counter->increment('test', 123));
    }

    /**
     * Test `increment` throws on invalid time.
     */
    public function testIncrementThrowsTimeInPast(): void
    {
        $time_keeper = new TimeSpy(27644437);

        $window_size = 60;
        $observation_period = 1000;

        $counter = new SlidingWindowCounter('default', $window_size, $observation_period, new FakeCache(), $time_keeper);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/The time provided \(\d+\) is too far in the past \(current time: \d+, observation period: 1000\)/');

        $counter->increment('test', 123, $time_keeper->time() - $observation_period - 1);
    }

    /**
     * Test `cacheGet`.
     */
    public function testCacheGet(): void
    {
        $time_keeper = new TimeSpy(27644437);

        $window_size = 60;
        $observation_period = 60;
        $cache_name = 'testing';

        // 27644437 / 60 = 460740.61667, so we get 460740
        $expected_bucket_key = sprintf('test:%d:%d:460740', $observation_period, $window_size);
        $cache_get_expected_arguments = [$cache_name, $expected_bucket_key];

        $cache = $this->createMock(CounterCache::class);
        $cache->expects($this->once())
            ->method('get')
            ->with(...$cache_get_expected_arguments)
            ->willReturn(42);

        $counter = new SlidingWindowCounter($cache_name, $window_size, $observation_period, $cache, $time_keeper);

        foreach (self::getMaterialValues($counter, 'test', $time_keeper->time()) as $value) {
            $this->assertSame(42.0, $value);
        }
    }

    /**
     * Test `getApproximateTotalSince` with two buckets.
     */
    public function testIncrementWithTwoBuckets(): void
    {
        $now = 193;
        $time_keeper = new TimeSpy($now);

        $window_size = 60;

        $counter = new SlidingWindowCounter('default', $window_size, $window_size * 2, new FakeCache(), $time_keeper);

        $counter->increment('test', 2);

        $time_keeper->setTime($now++);
        $counter->increment('test', 3);

        $time_keeper->setTime($now++);
        $counter->increment('test', 3);

        $time_keeper->setTime($now++);
        $counter->increment('test', 16);

        $time_keeper->setTime($now++);
        $counter->increment('test', 18);

        $time_keeper->setTime($now += $window_size - 2);

        $counter->increment('test', 8);
        $counter->increment('test', 10);

        $this->assertSame(255, $time_keeper->time());

        $this->assertSame([
            180 => 42.0,
            240 => 18.0,
        ], self::getMaterialValues($counter, 'test'));

        // (60-(255-60)+180) * 42/60 + 18
        $this->assertSame(49.5, $counter->getLatestValue('test'));
    }

    /**
     * Test `getVariance`.
     */
    public function testVariance(): void
    {
        $now = 939193;
        $window_size = 60;

        $counter = new SlidingWindowCounter('default', $window_size, 172800, new FakeCache(), new TimeSpy($now));

        /** @var SlidingWindowCounter $counter */
        $counter->increment('test', 40);
        $counter->increment('test', 20, $now - 60);
        $counter->increment('test', 9, $now - 110);
        $counter->increment('test', 9, $now - 120);
        $counter->increment('test', 6, $now - 180);
        $counter->increment('test', 5, $now - 240);
        $counter->increment('test', 1, $now - 360);

        $this->assertLessThanOrEqual(
            $now,
            max(array_keys(self::getMaterialValues($counter, 'test'))),
            'The timestamp for the last window should not exceed the current time'
        );

        $this->assertSame([
            938820 => 1.0,
            938880 => 0.0,
            938940 => 5.0,
            939000 => 6.0,
            939060 => 18.0,
            939120 => 20.0,
            939180 => 40.0, // 13 seconds of sliding window
        ], self::getMaterialValues($counter, 'test'));

        $extrapolated_values = iterator_to_array($counter->getTimeSeries('test'));

        $this->assertSame(
            $now,
            max(array_keys($extrapolated_values)),
            'The timestamp for the last extrapolated value should point at the current time'
        );

        foreach ([
            938893 => 0.78,
            938953 => 1.08,
            939013 => 5.21,
            939073 => 8.6,
            939133 => 18.43,
            939193 => 55.66,
        ] as $timestamp => $value) {
            $this->assertEqualsWithDelta($value, $extrapolated_values[$timestamp], 0.01);
        }

        $this->assertSame(5, $counter->getHistoricVariance('test')->getCount());

        $this->assertSame([
            'count' => 5,
            'std_dev' => 7.24,
            'mean' => 6.82,
            'sensitivity' => 3,
            'low' => -15.0,
            'high' => 29.0,
            'latest' => 55.67,
            'direction' => 'up',
            'hops' => 6.74,
        ], $counter->detectAnomaly('test', 3)->toArray());

        // Tackle edge cases
        $this->assertSame(2, $counter->getHistoricVariance('test', $now - $window_size * 3)->getCount());
        $this->assertSame(29, $counter->getHistoricVariance('test', $now - $window_size * 30)->getCount());

        // Setting the start time before we have any data leads to skewed results
        // (We skip leading blank frames if we don't set the start time)
        $this->assertEqualsWithDelta(
            3.78,
            $counter->getHistoricVariance('test', $now - $window_size * 30)->getStandardDeviation(),
            0.01
        );
    }

    /**
     * Test `getLatestValue` when the last logical frame overlaps with material.
     */
    public function testNullFrame(): void
    {
        $now = 1686908940;
        $bucket_key = 'foo';

        $counter = new SlidingWindowCounter('default', 60, 3600, new FakeCache(), new TimeSpy($now));

        foreach (range($now - 600, $now - 60, 60) as $timestamp) {
            $counter->increment($bucket_key, 60, $timestamp);
        }

        $this->assertSame(60.0, $counter->getLatestValue($bucket_key));

        $this->assertFalse($counter->detectAnomaly($bucket_key)->isAnomaly(), 'Anomaly detected when there is none');
    }

    /**
     * Test `detectAnomaly` when the last logical frame overlaps with material.
     */
    public function testNullFrameAnomaly(): void
    {
        $now = 1686908940;
        $bucket_key = 'bar';

        $counter = new SlidingWindowCounter('default', 60, 3600, new FakeCache(), new TimeSpy($now));

        foreach (range($now - 60 * 5, $now - 60, 60) as $timestamp) {
            $counter->increment($bucket_key, 60, $timestamp);
        }
        $counter->increment($bucket_key, 2000);

        $this->assertGreaterThan(60.0, $counter->getLatestValue($bucket_key));

        $this->assertTrue($counter->detectAnomaly($bucket_key, 3)->isAnomaly(), 'Anomaly not detected');
    }

    /** Test that constructor uses provided frame_builder instead of creating new one */
    public function testFrameBuilderDependencyInjection(): void
    {
        $window_size = 60;
        $time_keeper = new TimeSpy(1000);

        $custom_frame_builder = $this->createMock(FrameBuilder::class);
        $custom_frame_builder
            ->expects($this->once())
            ->method('newFrame')
            ->with($time_keeper->time())
            ->willReturn(new Frame($time_keeper->time(), $window_size));

        $counter = new SlidingWindowCounter(
            'test',
            $window_size,
            120,
            new FakeCache(),
            $time_keeper,
            $custom_frame_builder
        );

        // This increment should use the mocked frame_builder
        $counter->increment('test', 10);
    }

    /**
     * Test `getLatestValue` when window_size equals observation_period.
     *
     * This is the edge case where only one material frame can exist at a time,
     * because the observation period doesn't extend far enough back to include
     * a previous frame. The fix ensures we return the single frame's value
     * instead of returning 0.
     */
    public function testWindowSizeEqualsObservationPeriod(): void
    {
        $window_size = 60;
        $observation_period = 60;
        $now = $window_size * 100 + 30; // = 6030, 30 seconds into the current frame
        $bucket_key = 'equal-window';

        $time_keeper = new TimeSpy($now);
        $counter = new SlidingWindowCounter('default', $window_size, $observation_period, new FakeCache(), $time_keeper);

        // Both increments fall into the same material frame (6000-6060)
        $counter->increment($bucket_key, 20, $now - 10);
        $counter->increment($bucket_key, 30);

        $latestValue = $counter->getLatestValue($bucket_key);

        // The result should be greater than the larger single increment
        $this->assertGreaterThan(30.0, $latestValue, "getLatestValue should account for both increments");

        // The result should not exceed the sum of both increments
        $this->assertLessThanOrEqual(50.0, $latestValue, "getLatestValue should not exceed the sum");

        // Most importantly, verify the value is non-zero (the bug being fixed)
        $this->assertGreaterThan(0.0, $latestValue, "getLatestValue should be > 0 when window_size equals observation_period");
    }

    /**
     * Test `getLatestValue` with two material frames available.
     *
     * This tests the normal extrapolation case where we have both the previous
     * and current frame available for weighted calculation.
     */
    public function testWindowSizeWithTwoFrames(): void
    {
        $window_size = 60;
        $observation_period = 120; // 2x window_size ensures two frames are available
        $now = $window_size * 100 + 30; // = 6030, 30 seconds into the current frame
        $bucket_key = 'two-frames';

        $time_keeper = new TimeSpy($now);
        $counter = new SlidingWindowCounter('default', $window_size, $observation_period, new FakeCache(), $time_keeper);

        // Increment in previous material frame (5940-6000)
        $counter->increment($bucket_key, 20, $now - 50);

        // Increment in current material frame (6000-6060)
        $counter->increment($bucket_key, 30);

        $latestValue = $counter->getLatestValue($bucket_key);

        // With two frames, we should get extrapolated value
        $this->assertGreaterThan(30.0, $latestValue, "getLatestValue should account for both increments across frames");
        $this->assertLessThan(50.0, $latestValue, "getLatestValue should be less than sum due to extrapolation");
    }

    /**
     * Data provider for `testFuzzing`.
     */
    public static function provideFuzzySeconds(): iterable
    {
        $now = 1686900000;
        foreach (range($now, $now + 700) as $second) {
            yield $second - $now => [$second];
        }
    }

    /**
     * Fuzz testing `detectAnomaly` and `getLatestValue`.
     *
     * @param int $now The current time
     *
     * @dataProvider provideFuzzySeconds
     */
    public function testFuzzingAnomaly(int $now): void
    {
        $bucket_key = "fuzz{$now}";

        $counter = new SlidingWindowCounter('default', 5, 600, new FakeCache(), new TimeSpy($now));

        foreach (range($now - 300, $now, 5) as $timestamp) {
            $counter->increment($bucket_key, 5, $timestamp);
        }

        $counter->increment($bucket_key, 20);
        $this->assertGreaterThan(5.0, $counter->getLatestValue($bucket_key));
        $this->assertTrue($counter->detectAnomaly($bucket_key, 3)->isAnomaly(), "Anomaly not detected at {$now}");
    }

    /**
     * @param SlidingWindowCounter $counter The counter instance
     * @param string $bucket_key The bucket key
     * @param null|int $start_time The start time
     * @param null|int $end_time The end time
     */
    private static function getMaterialValues(
        SlidingWindowCounter $counter,
        string $bucket_key,
        ?int $start_time = null,
        ?int $end_time = null
    ): array {
        $method = new ReflectionMethod($counter, 'generateMaterialFrames');

        $pipeline = $method->invoke(
            $counter,
            $bucket_key,
            $start_time,
            $end_time
        );

        return take($pipeline)
            ->cast(fn (Frame $frame) => $frame->getValue())
            ->toAssoc();
    }
}
