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
