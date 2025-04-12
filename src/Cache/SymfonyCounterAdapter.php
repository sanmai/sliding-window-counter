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

use Symfony\Component\Cache\Adapter\CounterAdapterInterface;

use function implode;
use function is_int;

/**
 * A Symfony Counter adapter for the sliding window counter.
 * This adapter utilizes Symfony's CounterAdapterInterface which provides atomic increment operations.
 */
final class SymfonyCounterAdapter implements CounterCache
{
    /**
     * The Symfony Counter adapter instance.
     */
    private CounterAdapterInterface $cache;

    /**
     * @param CounterAdapterInterface $cache Symfony Counter adapter instance
     */
    public function __construct(CounterAdapterInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Increment a counter using Symfony's Counter adapter.
     *
     * @param string $cache_name memcached cache (or domain) name to use
     * @param string $cache_key key to use in the cache
     * @param int $ttl maximum number of seconds for the bucket to last in cache
     * @param int $step Increment by this amount
     *
     * @return bool|int The current value or false
     */
    public function increment(string $cache_name, string $cache_key, int $ttl, int $step)
    {
        // Create a composite key that includes the cache name
        $full_key = $this->createCacheKey($cache_name, $cache_key);

        // Use Symfony's atomic increment
        // Note: initialize the counter to 0 if it doesn't exist
        if (!$this->cache->hasItem($full_key)) {
            $this->cache->getItem($full_key)->set(0)->expiresAfter($ttl);
            $this->cache->save($this->cache->getItem($full_key));
        }

        // Perform the atomic increment
        return $this->cache->increment($full_key, $step);
    }

    /**
     * Get a value from the Symfony cache.
     *
     * @param string $cache_name memcached cache name to use
     * @param string $cache_key key to use in the cache
     *
     * @return null|int The current value
     */
    public function get(string $cache_name, string $cache_key): ?int
    {
        // Create a composite key that includes the cache name
        $full_key = $this->createCacheKey($cache_name, $cache_key);

        // Check if item exists
        if (!$this->cache->hasItem($full_key)) {
            return null;
        }

        // Get the value
        $item = $this->cache->getItem($full_key);
        $value = $item->get();

        // Ensure the return value is either an integer or null
        return is_int($value) ? $value : null;
    }

    /**
     * Create a composite cache key from cache name and key.
     *
     * @param string $cache_name The cache name/namespace
     * @param string $cache_key The cache key
     * @return string The composite key
     */
    private function createCacheKey(string $cache_name, string $cache_key): string
    {
        return implode(':', [$cache_name, $cache_key]);
    }
}
