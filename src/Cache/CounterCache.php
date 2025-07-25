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

interface CounterCache
{
    /**
     * Mockable method to increment values to the cache.
     *
     * @param string $cache_name memcached cache (or domain) name to use
     * @param string $cache_key key to use in the cache
     * @param int $ttl maximum number of seconds for the bucket to last in cache
     * @param int $step Increment by this amount
     *
     * @return bool|int The current value or false
     */
    public function increment(string $cache_name, string $cache_key, int $ttl, int $step);

    /**
     * Mockable method to get values to the cache.
     *
     * @param string $cache_name memcached cache name to use
     * @param string $cache_key key to use in the cache
     *
     * @return null|int The current value
     */
    public function get(string $cache_name, string $cache_key): ?int;
}
