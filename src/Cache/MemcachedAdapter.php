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
