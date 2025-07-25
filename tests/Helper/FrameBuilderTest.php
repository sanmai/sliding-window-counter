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

namespace Tests\SlidingWindowCounter\Helper;

use SlidingWindowCounter\Helper\Frame;
use SlidingWindowCounter\Helper\FrameBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use DuoClock\TimeSpy;

use function max;
use function min;
use function Pipeline\take;

/**
 * @covers \SlidingWindowCounter\Helper\FrameBuilder
 *
 * @internal
 */
final class FrameBuilderTest extends TestCase
{
    /**
     * Data provider for testTimestampRange.
     */
    public static function provideTimestampRange(): iterable
    {
        yield 'happy path' => [
            'start_time' => 0,
            'end_time' => null,
            'expected' => [
                660,
                720,
                780,
                840,
                900,
                960,
            ],
        ];

        yield 'start time below boundary' => [
            'start_time' => 839,
            'end_time' => null,
            'expected' => [
                780,
                840,
                900,
                960,
            ],
        ];

        yield 'start time at boundary' => [
            'start_time' => 840,
            'end_time' => null,
            'expected' => [
                840,
                900,
                960,
            ],
        ];

        yield 'start time above boundary' => [
            'start_time' => 661,
            'end_time' => 910,
            'expected' => [
                660,
                720,
                780,
                840,
                900,
            ],
        ];

        yield 'start time below boundary with end time' => [
            'start_time' => 719,
            'end_time' => 910,
            'expected' => [
                660,
                720,
                780,
                840,
                900,
            ],
        ];

        yield 'skips expired frames' => [
            'start_time' => 421,
            'end_time' => null,
            'expected' => [
                900, // 997-120 = 877 but frame 840 is already expired
                960,
            ],
            'window_size' => 60,
            'observation_period' => 120,
        ];

        yield 'start time one second in the past' => [
            'start_time' => 996,
            'end_time' => null,
            'expected' => [
                960,
            ],
            'window_size' => 60,
            'observation_period' => 120,
        ];

        yield 'last value' => [
            'start_time' => 997 - 60,
            'end_time' => null,
            'expected' => [
                900,
                960,
            ],
        ];

        yield 'start time is now' => [
            'start_time' => 960,
            'end_time' => null,
            'expected' => [
                960,
            ],
            'window_size' => 60,
            'observation_period' => 120,
            'now' => 960,
        ];

        yield 'start time is below the boundary' => [
            'start_time' => 959,
            'end_time' => null,
            'expected' => [
                900,
                960,
            ],
            'window_size' => 60,
            'observation_period' => 120,
            'now' => 960,
        ];
    }

    /**
     * Test `generateTimestampRange`.
     *
     * @dataProvider provideTimestampRange
     *
     * @param int $start_time The start time
     * @param null|int $end_time The end time
     * @param array $expected The array of expected timestamps
     * @param int $window_size The window size
     * @param int $observation_period The observation period
     * @param int $now The current time
     */
    public function testTimestampRange(
        int $start_time,
        ?int $end_time,
        array $expected,
        int $window_size = 60,
        int $observation_period = 360,
        int $now = 997 // prime number
    ): void {
        $builder = new FrameBuilder($window_size, $observation_period, new TimeSpy($now));

        $frame_starts = take($builder->generateFrames($start_time, $end_time))->cast(fn (Frame $frame) => $frame->getStart())->toArray();

        $this->assertLessThanOrEqual($now, max($frame_starts));

        if ($start_time > 0 && null !== $end_time) {
            $this->assertLessThanOrEqual($start_time, min($frame_starts), 'First frame must include the start time');
            $this->assertGreaterThanOrEqual($end_time, max($frame_starts) + $window_size, 'Last frame must include the end time');
        }

        if (null === $end_time) {
            $this->assertLessThanOrEqual($now, max($frame_starts), 'Last frame must not be in the future');
        }

        $this->assertSame(
            $expected,
            $frame_starts
        );
    }

    /** Test it throws an exception if the start time is in the future */
    public function testStartTimeInFuture(): void
    {
        $builder = new FrameBuilder(60, 360, new TimeSpy(997));

        $this->expectException(InvalidArgumentException::class);
        foreach ($builder->generateFrames(1000) as $frame) {
            $this->fail("Should not have generated frame {$frame->getStart()}");
        }
    }

    /** Test it throws an exception if the end time is before the start time */
    public function testEndTimeBeforeStartTime(): void
    {
        $builder = new FrameBuilder(60, 360, new TimeSpy(997));
        $this->expectException(InvalidArgumentException::class);
        foreach ($builder->generateFrames(100, 99) as $frame) {
            $this->fail("Should not have generated frame {$frame->getStart()}");
        }
    }
}
