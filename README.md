# Sliding Window Counter

[![Latest Stable Version](https://img.shields.io/packagist/v/sanmai/sliding-window-counter.svg)](https://packagist.org/packages/sanmai/sliding-window-counter)
[![License](https://img.shields.io/badge/license-GPL--2.0%20OR%20Apache--2.0-blue.svg)](https://github.com/sanmai/sliding-window-counter#license)

## Short-lived cache-backed time series with anomaly detection

A lightweight, efficient PHP library for tracking time-based events and detecting anomalies without the overhead of databases or logs.

## Table of Contents

- [Overview](#whats-this-all-about)
- [Features](#features)
- [How It Works](#how-it-works-the-simple-version)
- [Installation](#installation)
- [Quick Start](#quick-start)
  - [Setting up a counter](#setting-up-a-counter)
  - [Tracking events](#tracking-events)
  - [Detecting unusual activity](#detecting-unusual-activity)
  - [Getting more stats](#getting-more-stats)
- [Advanced Usage](#adjusting-sensitivity)
- [Cache Adapters](#available-cache-adapters)
- [Technical Details](#technical-details-for-the-curious)
- [Contributing](#contributing)
- [License](#license)

## Installation

```bash
composer require sanmai/sliding-window-counter
```

## What's this all about?

Ever needed to track how many times something happens over time and spot when those numbers get weird? That's what this library does, and it does it efficiently.

Real-world example: Imagine you want to detect when suspicious messages from specific IP ranges suddenly spike. Instead of digging through logs or querying databases, this library uses in-memory caching to track events and spot unusual patterns before it is too late.

## Features

- Lightweight - Uses your existing cache infrastructure
- Fast - No database queries or log parsing
- Robust anomaly detection - Based on standard deviations
- Flexible time windows - Configure to your needs
- Production-ready - Originally developed for Tumblr

## How it works (the simple version)

1. Divide time into buckets - We slice time into equal chunks (like 5-minute windows or hourly buckets)
2. Count events in cache - Each event increments a counter in the appropriate time bucket
3. Create time series on demand - When needed, we assemble these buckets into a continuous series
4. Apply statistical analysis - We calculate mean, standard deviation, and detect outliers

The library handles all the tricky parts like:
- What happens when current time doesn't perfectly align with your time buckets
- Calculating meaningful statistics on the fly
- Determining what counts as "unusual" activity (with adjustable sensitivity)

## Quick Start

### Setting up a counter

```php
// Create a counter that tracks hourly data for the past 24 hours
$counter = new \SlidingWindowCounter\SlidingWindowCounter(
    'visitor-counter',     // Name for your counter
    3600,                  // Window size: 3600 seconds (1 hour)
    3600 * 24,             // Keep data for 24 hours
    new \SlidingWindowCounter\Cache\MemcachedAdapter($memcached)
);
```

### Tracking events

```php
// Count a visit from this IP address
$counter->increment($_SERVER['REMOTE_ADDR']);

// You can also count by other keys
$counter->increment('user_' . $user_id);
$counter->increment('product_' . $product_id);
```

### Detecting unusual activity

```php
// Check if current activity is abnormal
$result = $counter->detectAnomaly($_SERVER['REMOTE_ADDR']);

if ($result->isAnomaly()) {
    // Something unusual is happening!
    $direction = $result->getDirection(); // Returns DIRECTION_UP, DIRECTION_DOWN, or DIRECTION_NONE
    
    if ($direction === \SlidingWindowCounter\AnomalyDetectionResult::DIRECTION_UP) {
        // Unusually high activity
        echo "Spike detected! Current: " . $result->getLatest();
        echo "Normal range: " . $result->getLow() . " to " . $result->getHigh();
    }
}
```

### Getting more stats

```php
// Get all stats as an array (values rounded to 2 decimal places by default)
$stats = $result->toArray();

// Or access individual values
$mean = $result->getMean();
$stdDev = $result->getStandardDeviation();
$currentValue = $result->getLatest();

// You can also get historical variance directly
$variance = $counter->getHistoricVariance($_SERVER['REMOTE_ADDR']);
$sampleCount = $variance->getCount();
```

## Adjusting Sensitivity

You can control how sensitive the anomaly detection is by specifying the number of standard deviations that define "normal":

```php
// Higher sensitivity (1 standard deviation) - detects more anomalies
$result = $counter->detectAnomaly($_SERVER['REMOTE_ADDR'], 1);

// Default sensitivity (2 standard deviations)
$result = $counter->detectAnomaly($_SERVER['REMOTE_ADDR']);

// Lower sensitivity (3 standard deviations) - only extreme outliers
$result = $counter->detectAnomaly($_SERVER['REMOTE_ADDR'], 3);

// Extremely low sensitivity (5 standard deviations) - only detects extreme outliers
$result = $counter->detectAnomaly($_SERVER['REMOTE_ADDR'], 5);
```

A quick stats refresher:
- 1 standard deviation: ~68% of normal values in this range (fairly sensitive)
- 2 standard deviations: ~95% of normal values in this range (recommended default)
- 3 standard deviations: ~99.7% of normal values in this range (high confidence)
- 5 standard deviations: ~99.99994% of normal values in this range (1 in ~1.7 million chance)

## Available Cache Adapters

The library supports multiple caching backends through a simple adapter interface. An example using regular Memcached:

```php
$adapter = new \SlidingWindowCounter\Cache\MemcachedAdapter($memcached);
```

### Creating Your Own Adapter

Need to use a different cache system? Implementing a custom adapter is straightforward:

```php
use SlidingWindowCounter\Cache\CounterCache;

class RedisAdapter implements CounterCache 
{
    private $redis;
    
    public function __construct(Redis $redis) 
    {
        $this->redis = $redis;
    }
    
    public function increment(string $cache_name, string $cache_key, int $ttl, int $step)
    {
        $key = "{$cache_name}:{$cache_key}";
        $this->redis->setnx($key, 0); // Create if not exists
        $this->redis->expire($key, $ttl);
        return $this->redis->incrby($key, $step);
    }
    
    public function get(string $cache_name, string $cache_key): ?int
    {
        $value = $this->redis->get("{$cache_name}:{$cache_key}");
        return is_numeric($value) ? (int) $value : null;
    }
}
```

## Technical Details (for the curious)

The library uses an elegant sliding window approach to time series data. Here's how it works under the hood:

- Material frames: The actual cached data buckets aligned to window boundaries
- Logical frames: Windows aligned to the current time (which may overlap multiple material frames)

When calculating values for logical frames that don't perfectly align with material frames, we perform weighted extrapolation to ensure smooth transitions in the time series.

Consider these two scenarios:

1. Perfectly aligned frames: When the query time aligns with cache bucket boundaries, we can use the raw values directly.

![Aligned Frames](docs/images/logical-frames-aligned.svg)

2. Misaligned frames: When the query time doesn't align with cache boundaries, we extrapolate values based on overlapping portions.

![Misaligned Frames](docs/images/logical-frames-disaligned.svg)

For a more detailed explanation of the internal workings, check out [this Cloudflare blog post](https://blog.cloudflare.com/counting-things-a-lot-of-different-things/) which explains a similar approach.

## License

This library is dual-licensed under the GNU General Public License v2.0 or later and the Apache License 2.0. You may choose either license to govern your use of this software.

- For GPL-2.0-or-later license terms, see the [LICENSE-GPL](LICENSE-GPL) file
- For Apache-2.0 license terms, see the [LICENSE](LICENSE) file

When using this library, you must comply with the terms of at least one of these licenses.

All contributions to this project have been reviewed and confirmed by the respective authors as dual-licensed. If you believe your code was included without proper attribution or license representation, please contact us and we'll address it immediately.
