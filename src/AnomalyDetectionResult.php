<?php declare(strict_types=1);
/**
 * The Sliding Window Counter, a short-lived time series library.
 * Copyright 2023 Automattic, Inc.
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

use function abs;
use function array_map;
use function ceil;
use function floor;
use function get_object_vars;
use function is_float;
use function round;

/**
 * The result of the anomaly detection.
 */
final class AnomalyDetectionResult
{
    /**
     * Anomaly detection not found. Not an anomaly.
     */
    public const DIRECTION_NONE = 'none';

    /**
     * Anomaly towards upwards direction.
     */
    public const DIRECTION_UP = 'up';

    /**
     * Anomaly towards downwards direction.
     */
    public const DIRECTION_DOWN = 'down';

    /** @var int The number of observations */
    private int $count;

    /** @var float The standard deviation */
    private float $std_dev;

    /** @var float The mean value */
    private float $mean;

    /** @var float|int The sensitivity */
    private float|int $sensitivity;

    /** @var float The low bound */
    private float $low;

    /** @var float The high bound */
    private float $high;

    /** @var float The latest value */
    private float $latest;

    /** @var string The direction of the anomaly */
    private string $direction = self::DIRECTION_NONE;

    /** @var float The distance from the norm (AKA anomaly score) */
    private float $hops = 0.0;

    /**
     * Create a new anomaly detection result instance.
     *
     * @param int $count The number of observations
     * @param float $std_dev The standard deviation
     * @param float $mean The mean value
     * @param float $latest The latest value
     * @param float|int $sensitivity The sensitivity (see `SlidingWindowCounter::detectAnomaly()`)
     */
    public function __construct(int $count, float $std_dev, float $mean, float $latest, float|int $sensitivity)
    {
        $this->count = $count;
        $this->std_dev = $std_dev;
        $this->mean = $mean;
        $this->latest = $latest;
        $this->sensitivity = $sensitivity;

        $this->high = ceil($this->mean + ($sensitivity * $this->std_dev));
        $this->low = floor($this->mean - ($sensitivity * $this->std_dev));

        if ($this->latest >= $this->low && $this->latest <= $this->high) {
            return;
        }

        if ($this->std_dev > 0.0) {
            $this->hops = abs($this->mean - $this->latest) / $this->std_dev;
        }

        if ($this->latest < $this->low) {
            $this->direction = self::DIRECTION_DOWN;
        } elseif ($this->latest > $this->high) {
            $this->direction = self::DIRECTION_UP;
        }
    }

    /**
     * Whether the current value is an anomaly or not.
     */
    public function isAnomaly(): bool
    {
        return self::DIRECTION_NONE !== $this->direction;
    }

    /**
     * The result as an array.
     *
     * @param int $precision the number of decimal digits to round to
     */
    public function toArray(int $precision = 2): array
    {
        return array_map(
            fn ($val) => is_float($val) ? round($val, $precision) : $val,
            get_object_vars($this)
        );
    }

    /**
     * The number of observations
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * The standard deviation.
     */
    public function getStandardDeviation(): float
    {
        return $this->std_dev;
    }

    /**
     * The mean value.
     */
    public function getMean(): float
    {
        return $this->mean;
    }

    /**
     * The sensitivity.
     */
    public function getSensitivity(): float|int
    {
        return $this->sensitivity;
    }

    /**
     * The low bound.
     */
    public function getLow(): float
    {
        return $this->low;
    }

    /**
     * The high bound.
     */
    public function getHigh(): float
    {
        return $this->high;
    }

    /**
     * The latest value.
     */
    public function getLatest(): float
    {
        return $this->latest;
    }

    /**
     * The direction of the anomaly.
     */
    public function getDirection(): string
    {
        return $this->direction;
    }

    /**
     * The distance from the norm (AKA anomaly score).
     */
    public function getHops(): float
    {
        return $this->hops;
    }
}
