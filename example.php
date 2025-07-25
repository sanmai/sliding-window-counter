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

require 'vendor/autoload.php';

$_SERVER['REMOTE_ADDR'] = '192.168.1.1';

$memcached = new Memcached();
$memcached->addServer('127.0.0.1', 11211);

// Configure a counter to work in hourly buckets, for the last 24 hours
// and using the Memcached adapter.
$counter = new \SlidingWindowCounter\SlidingWindowCounter(
    'my-counters',
    3600,
    3600 * 24,
    new \SlidingWindowCounter\Cache\MemcachedAdapter($memcached)
);

// Increment the counter when a certain event happens.
$counter->increment($_SERVER['REMOTE_ADDR']);

// Try to detect an anomaly.
$anomaly_result = $counter->detectAnomaly($_SERVER['REMOTE_ADDR']);
if ($anomaly_result->isAnomaly()) {
    // Inspect the result using the toArray() method.
    $anomaly_result->toArray();

    // Or individual accessors.
    $anomaly_result->getMean();
    $anomaly_result->getStandardDeviation();
    // ...
}

// And explore the historic variance...
$variance = $counter->getHistoricVariance($_SERVER['REMOTE_ADDR']);
// ...using various accessors.
$variance->getCount();
$variance->getMean();
$variance->getStandardDeviation();
