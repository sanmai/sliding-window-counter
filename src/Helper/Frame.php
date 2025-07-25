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

namespace SlidingWindowCounter\Helper;

use function implode;
use function intdiv;

/**
 * The current frame of the sliding window counter.
 * @final
 */
class Frame
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
