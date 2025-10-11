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

use SlidingWindowCounter\AnomalyDetectionResult;
use PHPUnit\Framework\TestCase;

/**
 * @covers \SlidingWindowCounter\AnomalyDetectionResult
 *
 * @internal
 */
final class AnomalyDetectionResultTest extends TestCase
{
    /**
     * Test happy path, no anomaly.
     */
    public function testHappyPath(): void
    {
        $result = new AnomalyDetectionResult(10, 0.999, 10.0, 11.0, 1);

        $this->assertFalse($result->isAnomaly());
        $this->assertSame(AnomalyDetectionResult::DIRECTION_NONE, $result->getDirection());

        $this->assertSame([
            'count' => 10,
            'std_dev' => 1.0,
            'mean' => 10.0,
            'sensitivity' => 1,
            'low' => 9.0,
            'high' => 11.0,
            'latest' => 11.0,
            'direction' => AnomalyDetectionResult::DIRECTION_NONE,
            'hops' => 0.0,
        ], $result->toArray());
    }

    /**
     * Test all getters.
     */
    public function testAllGetters(): void
    {
        $result = new AnomalyDetectionResult(11, 1.0, 10.0, 11.0, 0.9);

        $this->assertSame(11, $result->getCount());
        $this->assertSame(1.0, $result->getStandardDeviation());
        $this->assertSame(10.0, $result->getMean());
        $this->assertSame(0.9, $result->getSensitivity());
        $this->assertSame(9.0, $result->getLow());
        $this->assertSame(11.0, $result->getHigh());
        $this->assertSame(11.0, $result->getLatest());
        $this->assertSame(AnomalyDetectionResult::DIRECTION_NONE, $result->getDirection());
        $this->assertSame(0.0, $result->getHops());
    }

    /**
     * Test anomaly detection up.
     */
    public function testAnomalyDirectionUp(): void
    {
        $result = new AnomalyDetectionResult(22, 1.0, 10.0, 12.011, 1);

        $this->assertTrue($result->isAnomaly());

        $this->assertSame([
            'count' => 22,
            'std_dev' => 1.0,
            'mean' => 10.0,
            'sensitivity' => 1,
            'low' => 9.0,
            'high' => 11.0,
            'latest' => 12.0,
            'direction' => AnomalyDetectionResult::DIRECTION_UP,
            'hops' => 2.0,
        ], $result->toArray(1));
    }

    /**
     * Test anomaly detection down.
     */
    public function testAnomalyDetectionDown(): void
    {
        $result = new AnomalyDetectionResult(33, 1.0, 10.0, 1.123456, 3);

        $this->assertTrue($result->isAnomaly());

        $this->assertSame([
            'count' => 33,
            'std_dev' => 1.0,
            'mean' => 10.0,
            'sensitivity' => 3,
            'low' => 7.0,
            'high' => 13.0,
            'latest' => 1.123,
            'direction' => AnomalyDetectionResult::DIRECTION_DOWN,
            'hops' => 8.877,
        ], $result->toArray(3));
    }

    /**
     * @return iterable data provider for `testDirections()`
     */
    public static function providerDirections(): iterable
    {
        yield 'way too low' => [1.0, true, AnomalyDetectionResult::DIRECTION_DOWN];
        yield 'kind of low' => [7.0, true, AnomalyDetectionResult::DIRECTION_DOWN];
        yield 'barely too low' => [8.999, true, AnomalyDetectionResult::DIRECTION_DOWN];

        yield 'on low edge' => [9.0, false, AnomalyDetectionResult::DIRECTION_NONE];
        yield 'nearing low edge' => [9.001, false, AnomalyDetectionResult::DIRECTION_NONE];
        yield 'perfect' => [10.0, false, AnomalyDetectionResult::DIRECTION_NONE];
        yield 'nearing high edge' => [10.999, false, AnomalyDetectionResult::DIRECTION_NONE];
        yield 'on high edge' => [11.0, false, AnomalyDetectionResult::DIRECTION_NONE];

        yield 'barely too high' => [11.001, true, AnomalyDetectionResult::DIRECTION_UP];
        yield 'kind of high' => [13.0, true, AnomalyDetectionResult::DIRECTION_UP];
        yield 'way too high' => [150.0, true, AnomalyDetectionResult::DIRECTION_UP];
    }

    /**
     * Test various directions.
     *
     * @dataProvider providerDirections
     *
     * @param float $latest Latest value
     * @param bool $expected_is_anomaly Expected anomaly result
     * @param string $expected_direction Expected direction
     */
    public function testDirections(float $latest, bool $expected_is_anomaly, string $expected_direction): void
    {
        $result = new AnomalyDetectionResult(100, 1.0, 10.0, $latest, 1);

        $this->assertSame($expected_is_anomaly, $result->isAnomaly(), 'Unexpected anomaly result');
        $this->assertSame($expected_direction, $result->getDirection(), 'Unexpected direction');
    }

    /** Test that ceil is used for high boundary, not round */
    public function testCeilUsedForHighBoundary(): void
    {
        // mean=10, std_dev=1.4, sensitivity=1 -> high = ceil(10 + 1.4) = ceil(11.4) = 12
        // If round() were used: round(11.4) = 11 (different!)
        $result = new AnomalyDetectionResult(100, 1.4, 10.0, 11.9, 1);

        $this->assertSame(12.0, $result->getHigh(), 'High should use ceil(11.4)=12, not round(11.4)=11');
        $this->assertFalse($result->isAnomaly(), '11.9 should be within [floor(8.6)=8, ceil(11.4)=12]');
    }

    /** Test that floor is used for low boundary, not round */
    public function testFloorUsedForLowBoundary(): void
    {
        // mean=10, std_dev=1.4, sensitivity=1 -> low = floor(10 - 1.4) = floor(8.6) = 8
        // If round() were used: round(8.6) = 9 (different!)
        $result = new AnomalyDetectionResult(100, 1.4, 10.0, 8.1, 1);

        $this->assertSame(8.0, $result->getLow(), 'Low should use floor(8.6)=8, not round(8.6)=9');
        $this->assertFalse($result->isAnomaly(), '8.1 should be within [floor(8.6)=8, ceil(11.4)=12]');
    }

    /** Test that hops uses division, not multiplication */
    public function testHopsUsesDivision(): void
    {
        // std_dev=2, mean=10, latest=16 -> hops = abs(10-16)/2 = 6/2 = 3
        $result = new AnomalyDetectionResult(100, 2.0, 10.0, 16.0, 1);

        $this->assertSame(3.0, $result->getHops(), 'Hops should use division by std_dev');
    }
}
