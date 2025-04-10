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

namespace SlidingWindowCounter\Cache;

use WP_Object_Cache;

use function is_int;

/**
 * A WordPress object cache adapter for the sliding window counter.
 */
final class WPCacheAdapter implements CounterCache
{
    /**
     * The WordPress object cache instance.
     */
    private WP_Object_Cache $cache;

    public function __construct(WP_Object_Cache $cache)
    {
        $this->cache = $cache;
    }

    public function increment(string $cache_name, string $cache_key, int $ttl, int $step)
    {
        $this->cache->add($cache_key, 0, $cache_name, $ttl);

        return $this->cache->incr($cache_key, $step, $cache_name);
    }

    public function get(string $cache_name, string $cache_key): ?int
    {
        $value = $this->cache->get($cache_key, $cache_name);

        return is_int($value) ? $value : null;
    }
}
