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

namespace SlidingWindowCounter\Cache;

use Memcached;

use function implode;
use function is_int;

/**
 * A Memcached adapter for the sliding window counter.
 */
final class MemcachedAdapter implements CounterCache
{
    private Memcached $cache;

    public function __construct(Memcached $memcached)
    {
        $this->cache = $memcached;
    }

    public function increment(string $cache_name, string $cache_key, int $ttl, int $step)
    {
        $cache_key = implode(':', [$cache_name, $cache_key]);

        $this->cache->add($cache_key, 0, $ttl);

        return $this->cache->increment($cache_key, $step);
    }

    public function get(string $cache_name, string $cache_key): ?int
    {
        $cache_key = implode(':', [$cache_name, $cache_key]);

        $value = $this->cache->get($cache_key);

        return is_int($value) ? $value : null;
    }
}
