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
