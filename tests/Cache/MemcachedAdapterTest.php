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

namespace Tests\SlidingWindowCounter\Cache;

use SlidingWindowCounter\Cache\MemcachedAdapter;
use Memcached;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \SlidingWindowCounter\Cache\MemcachedAdapter
 */
final class MemcachedAdapterTest extends TestCase
{
    public function testGetNull(): void
    {
        $cache_name = 'foo';
        $cache_key = 'example';

        $memcached = $this->createMock(Memcached::class);
        $memcached->expects($this->once())
            ->method('get')
            ->with("{$cache_name}:{$cache_key}")
            ->willReturn('bar');

        $adapter = new MemcachedAdapter($memcached);
        $this->assertNull($adapter->get($cache_name, $cache_key));
    }

    public function testGetInt(): void
    {
        $cache_name = 'foo';
        $cache_key = 'example';

        $memcached = $this->createMock(Memcached::class);
        $memcached->expects($this->once())
            ->method('get')
            ->with("{$cache_name}:{$cache_key}")
            ->willReturn(42);

        $adapter = new MemcachedAdapter($memcached);
        $this->assertSame(42, $adapter->get($cache_name, $cache_key));
    }

    public function testIncrement(): void
    {
        $memcached = $this->createMock(Memcached::class);

        $cache_name = 'foo';
        $cache_key = 'example';

        $memcached->expects($this->once())
            ->method('add')
            ->with("{$cache_name}:{$cache_key}", 0, 60)
            ->willReturn(true);

        $memcached->expects($this->once())
            ->method('increment')
            ->with("{$cache_name}:{$cache_key}", 1)
            ->willReturn(1);

        $adapter = new MemcachedAdapter($memcached);
        $this->assertSame(1, $adapter->increment($cache_name, $cache_key, 60, 1));
    }
}
