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

namespace Tests\SlidingWindowCounter;

use PHPUnit\Framework\TestCase;
use SlidingWindowCounter\SlidingWindowCounter;
use SlidingWindowCounter\Cache\MemcachedAdapter;
use Memcached;

use function bin2hex;
use function ceil;
use function rand;
use function random_bytes;
use function range;
use function time;

/**
 * @internal
 *
 * @coversNothing
 */
final class IntegrationTest extends TestCase
{
    /**
     * @group integration
     */
    public function testMemcachedConnection(): string
    {
        $memcached = new Memcached();
        $memcached->addServer('127.0.0.1', 11211);

        do {
            $example_key = bin2hex(random_bytes(16));
        } while (false !== $memcached->get($example_key));

        $this->assertFalse($memcached->get($example_key));
        $this->assertTrue($memcached->add($example_key, 0, 60));
        $this->assertSame(1000, $memcached->increment($example_key, 1000));
        $this->assertSame(1000, $memcached->get($example_key));

        return $example_key;
    }

    /**
     * @group integration
     *
     * @depends testMemcachedConnection
     */
    public function testFuzzing(string $bucket_key): void
    {
        $memcached = new Memcached();
        $memcached->addServer('127.0.0.1', 11211);

        $counter = new SlidingWindowCounter(
            'my-counters',
            60,
            3600,
            new MemcachedAdapter($memcached)
        );

        $now = time();

        foreach (range($now - 1800, $now - 60, 60) as $timestamp) {
            $result = $counter->increment($bucket_key, rand(55, 65), $timestamp);
            $this->assertNotFalse($result);
        }

        // Tweak up the last value to be around the historic mean
        $counter->increment($bucket_key, (int) ($counter->getHistoricVariance($bucket_key)->getMean() - $counter->getLatestValue($bucket_key)));
        $this->assertEqualsWithDelta(60.0, $counter->getLatestValue($bucket_key), 10.0);

        $this->assertFalse($counter->detectAnomaly($bucket_key)->isAnomaly(), "Anomaly detected at {$now}");

        // Now make it an anomaly
        $counter->increment($bucket_key, (int) ceil($counter->getHistoricVariance($bucket_key)->getStandardDeviation() * 3));

        $anomaly = $counter->detectAnomaly($bucket_key);

        $this->assertTrue($anomaly->isAnomaly(), "Anomaly not detected at {$now}");
    }
}
