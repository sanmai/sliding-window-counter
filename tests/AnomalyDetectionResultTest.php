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
}
