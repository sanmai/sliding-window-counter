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
