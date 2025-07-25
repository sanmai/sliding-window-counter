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
use PHPUnit\Framework\TestCase;

use function array_keys;
use function array_sum;
use function max;
use function min;
use function range;

/**
 * Test for Frame.
 *
 * @covers \SlidingWindowCounter\Helper\Frame
 *
 * @internal
 */
final class FrameTest extends TestCase
{
    /**
     * Test the "textbook" example.
     *
     * @see https://blog.cloudflare.com/counting-things-a-lot-of-different-things/
     */
    public function testTextbookExample(): void
    {
        $window_size = 60;
        $frame = new Frame(135, $window_size);

        $this->assertSame(135, $frame->getTime());
        $this->assertSame(120, $frame->getStart());

        $this->assertSame($window_size, array_sum($frame->getFrameOverlap()));

        $this->assertSame([
            60 => 45,
            120 => 15,
        ], $frame->getFrameOverlap());
    }

    /**
     * Test happy path.
     */
    public function testHappyPath(): void
    {
        // 104729 is a prime number, so it is not divisible by 7200 or any other number
        $frame = new Frame(104729, 7200);

        $this->assertSame(104729, $frame->getTime());
        $this->assertSame(100800, $frame->getStart());
        $this->assertTrue($frame->getStart() <= $frame->getTime());
        $this->assertSame('test:1234567:7200:14', $frame->getCacheKey('test', 1234567));

        $this->assertSame([
            93600 => 3271, // 7200 - 3929
            100800 => 3929, // 104729 - 100800
        ], $frame->getFrameOverlap());

        $this->assertSame(7200, array_sum($frame->getFrameOverlap()));
    }

    /**
     * Logical frame starting at the material frame boundary should include 100% of the previous material frame.
     */
    public function testFrameAtTheBoundary(): void
    {
        $frame = new Frame(100800, 7200);
        $this->assertSame(100800, $frame->getTime());
        $this->assertSame(100800, $frame->getStart());
        $this->assertTrue($frame->getStart() <= $frame->getTime());

        $this->assertSame([
            100800 - 7200 => 7200,
            100800 => 0,
        ], $frame->getFrameOverlap());

        $this->assertSame(7200, array_sum($frame->getFrameOverlap()));
    }

    public function testFrameAfterTheBoundary(): void
    {
        $frame = new Frame(100801, 7200);
        $this->assertSame(100801, $frame->getTime());
        $this->assertSame(100800, $frame->getStart());
        $this->assertTrue($frame->getStart() <= $frame->getTime());

        $this->assertSame([
            93600 => 7199,
            100800 => 1,
        ], $frame->getFrameOverlap());

        $this->assertSame(7200, array_sum($frame->getFrameOverlap()));
    }

    /**
     * Logical frame boundary should be computed with second precision.
     */
    public function testFrameBelowTheBoundary(): void
    {
        $frame = new Frame(100799, 7200);
        $this->assertSame(100799, $frame->getTime());
        $this->assertSame(93600, $frame->getStart());
        $this->assertTrue($frame->getStart() <= $frame->getTime());

        $this->assertSame(7200, array_sum($frame->getFrameOverlap()));

        $this->assertSame([
            86400 => 1,
            93600 => 7199,
        ], $frame->getFrameOverlap());
    }

    /**
     * Logical frame should include 100% of the window, but no more than that.
     */
    public function testSanity(): void
    {
        $window_size = 11;

        $frame_seconds_used = [];
        $frames_seen = [];

        foreach (range(104729, 104729 + 300, $window_size) as $time) {
            $frame = new Frame($time, $window_size);
            $overlap = $frame->getFrameOverlap();
            $this->assertSame($window_size, array_sum($overlap));

            foreach ($overlap as $frame_start => $frame_seconds) {
                $frame_seconds_used[$frame_start] ??= 0;
                $frame_seconds_used[$frame_start] += $frame_seconds;

                $frames_seen[$frame_start] ??= 0;
                ++$frames_seen[$frame_start];

                $this->assertLessThanOrEqual(
                    2,
                    $frames_seen[$frame_start],
                    'Each material frame must be used at most by two logical frames'
                );

                $this->assertLessThanOrEqual(
                    $window_size,
                    $frame_seconds_used[$frame_start],
                    'Each logical frame must use only up to the window size of seconds from each material frame'
                );
            }

            $material_frames = array_keys($overlap);

            $this->assertNotSame(min($material_frames), max($material_frames));
        }
    }

    /**
     * Test frame value getter and setters.
     */
    public function testFrameValue(): void
    {
        $frame = new Frame(100800, 7200);

        $this->assertTrue($frame->hasNullValue());
        $this->assertSame(0.0, $frame->getValue());

        $frame->setValue(123.1);
        $this->assertSame(123.1, $frame->getValue());
    }
}
