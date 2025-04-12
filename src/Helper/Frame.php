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

use function implode;
use function intdiv;

/**
 * The current frame of the sliding window counter.
 */
final class Frame
{
    /** @var int Frame's reference timestamp */
    private int $time;

    /** @var int The window size */
    private int $window_size;

    /** @var float The current value */
    private ?float $value = null;

    /**
     * The frame's constructor.
     *
     * @param int $time the frame's reference time
     * @param int<1, max> $window_size the window size
     */
    public function __construct(int $time, int $window_size)
    {
        $this->time = $time;
        $this->window_size = $window_size;
    }

    /**
     * The logical frame reference time.
     */
    public function getTime(): int
    {
        return $this->time;
    }

    /**
     * The material frame start time (reference time aligned to the window size).
     */
    public function getStart(): int
    {
        return $this->time - $this->time % $this->window_size;
    }

    /**
     * Set a new material value.
     *
     * @param null|float $value The new value
     *
     * @return $this
     */
    public function setValue(?float $value): self
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Whether the frame has a null material value.
     */
    public function hasNullValue(): bool
    {
        return null === $this->value;
    }

    /**
     * The material frame's value.
     */
    public function getValue(): float
    {
        return $this->value ?? 0.0;
    }

    /**
     * Computes the cache window ID.
     */
    private function getWindowId(): int
    {
        return intdiv($this->time, $this->window_size);
    }

    /**
     * Computes the list of source material frames and the number of seconds they overlap with the current frame.
     *
     * @return array<int, int>
     */
    public function getFrameOverlap(): array
    {
        $current_frame_seconds = $this->getTime() - $this->getStart();

        return [
            $this->getStart() - $this->window_size => $this->window_size - $current_frame_seconds,
            $this->getStart() => $current_frame_seconds,
        ];
    }

    /**
     * Returns a cache key for given arguments.
     *
     * @param string $bucket_key The bucket key
     * @param int $observation_period The length of observation period
     *
     * @return string
     */
    public function getCacheKey(string $bucket_key, int $observation_period)
    {
        return implode(':', [
            $bucket_key,
            $observation_period,
            $this->window_size,
            $this->getWindowId(),
        ]);
    }
}
