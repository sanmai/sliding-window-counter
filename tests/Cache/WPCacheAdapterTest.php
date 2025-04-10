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

namespace Tests\SlidingWindowCounter\Cache;

use SlidingWindowCounter\Cache\WPCacheAdapter;
use PHPUnit\Framework\TestCase;
use WP_Object_Cache;

/**
 * @internal
 *
 * @covers \SlidingWindowCounter\Cache\WPCacheAdapter
 */
final class WPCacheAdapterTest extends TestCase
{
    public function testGetFromCache(): void
    {
        $wp_cache = $this->createMock(WP_Object_Cache::class);
        $wp_cache->expects($this->once())
            ->method('get')
            ->with('bar', 'default')
            ->willReturn(100);

        $cache = new WPCacheAdapter($wp_cache);
        $this->assertSame(100, $cache->get('default', 'bar'));
    }

    public function testGetFromCacheNull(): void
    {
        $wp_cache = $this->createMock(WP_Object_Cache::class);
        $wp_cache->expects($this->once())
            ->method('get')
            ->with('cache-key', 'default')
            ->willReturn(false);

        $cache = new WPCacheAdapter($wp_cache);
        $this->assertNull($cache->get('default', 'cache-key'));
    }

    public function testIncrement(): void
    {
        $wp_cache = $this->createMock(WP_Object_Cache::class);

        $wp_cache->expects($this->once())
            ->method('add')
            ->with('cache-key', 0, 'default', 3600);

        $wp_cache->expects($this->once())
            ->method('incr')
            ->with('cache-key', 123, 'default')
            ->willReturn(100);

        $cache = new WPCacheAdapter($wp_cache);
        $this->assertSame(100, $cache->increment('default', 'cache-key', 3600, 123));
    }
}
