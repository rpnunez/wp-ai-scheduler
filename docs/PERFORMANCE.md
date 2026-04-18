# Performance Benchmarking

## Overview

The AI Post Scheduler plugin includes a performance benchmarking system to detect regressions and measure the impact of changes continuously. This system is integrated into the CI/CD pipeline via GitHub Actions.

## Components

### 1. Benchmark Script (`ai-post-scheduler/bin/benchmark.php`)

A PHP CLI script that:
- Boots WordPress in a test environment
- Loads representative admin pages, frontend pages, and AJAX endpoints
- Records performance metrics:
  - `$wpdb->num_queries` - Database query count
  - `memory_get_peak_usage()` - Peak memory usage
  - Wall time - Execution time in milliseconds

### 2. GitHub Actions Workflow (`.github/workflows/performance-tests.yml`)

Runs on:
- Push to `main` or `develop` branches
- Pull requests to `main` or `develop` branches
- Manual workflow dispatch

Features:
- Sets up PHP 8.3, MySQL 8.0, and WordPress test environment
- Runs benchmark script and records results
- Compares against baseline from main branch
- **Fails PRs** when thresholds are exceeded
- Updates baseline automatically on main/develop branches
- Posts comparison results as PR comment

### 3. Baseline File (`.github/performance-baseline.json`)

JSON file containing baseline performance metrics. Updated automatically when changes are merged to main/develop branches.

## Benchmarks Run

The script runs five benchmarks:

1. **Admin Dashboard Load** - Simulates loading the WordPress admin dashboard
2. **Frontend Page Load** - Simulates a typical frontend page with 10 posts
3. **Plugin Admin Page Load** - Simulates loading the AI Post Scheduler settings page
4. **AJAX Endpoint** - Tests the template list AJAX endpoint
5. **Schedule Check** - Tests database-heavy schedule lookup operations

## Performance Thresholds

PRs fail if metrics exceed these thresholds compared to baseline:

| Metric | Threshold | Description |
|--------|-----------|-------------|
| Queries | +20% | Database queries allowed to increase by 20% |
| Peak Memory | +25% | Memory usage allowed to increase by 25% |
| Wall Time | +30% | Execution time allowed to increase by 30% |

## Running Locally

### Prerequisites

1. Install WordPress test library:
   ```bash
   cd ai-post-scheduler
   bash bin/install-wp-tests.sh wordpress_test root '' localhost latest true
   ```

2. Set up WordPress environment at `/tmp/wordpress` with wp-config.php

### Run Benchmark

Basic usage:
```bash
cd ai-post-scheduler
php bin/benchmark.php --wp-core-dir=/tmp/wordpress
```

With baseline comparison:
```bash
php bin/benchmark.php \
  --wp-core-dir=/tmp/wordpress \
  --baseline-file=../.github/performance-baseline.json \
  --output-file=/tmp/performance-results.json \
  --fail-on-regression
```

### Command-line Options

- `--wp-core-dir=<path>` - Path to WordPress core directory (default: `/tmp/wordpress`)
- `--baseline-file=<path>` - Path to baseline JSON file for comparison
- `--output-file=<path>` - Path to save results JSON file
- `--fail-on-regression` - Exit with code 1 if regression detected

## Interpreting Results

### Output Format

```
========================================
Performance Benchmark
========================================
WordPress Core: /tmp/wordpress
========================================

Running benchmarks...

------------------------------------------------------------------------------------------------------------------------
Admin Dashboard Load                     | Queries:   25 | Memory:   2.00 MB | Peak:  40.00 MB | Time:   150.00ms
Frontend Page Load                       | Queries:   15 | Memory:   1.00 MB | Peak:  40.00 MB | Time:   100.00ms
Plugin Admin Page Load                   | Queries:   10 | Memory: 512.00 KB | Peak:  40.00 MB | Time:    80.00ms
AJAX Endpoint (Template List)            | Queries:    5 | Memory: 256.00 KB | Peak:  40.00 MB | Time:    50.00ms
Schedule Check (Heavy Query)             | Queries:    3 | Memory: 128.00 KB | Peak:  40.00 MB | Time:    30.00ms
------------------------------------------------------------------------------------------------------------------------

TOTALS:
Total Queries: 58 | Peak Memory: 40.00 MB | Total Time: 410.00ms

========================================
Baseline Comparison
========================================

Queries         | Baseline:           58 | Current:           58 | Change:   +0.00% | Threshold: +20% | PASS
Memory peak     | Baseline:     40.00 MB | Current:     40.00 MB | Change:   +0.00% | Threshold: +25% | PASS
Wall time       | Baseline:       410.00 | Current:       410.00 | Change:   +0.00% | Threshold: +30% | PASS

SUCCESS: All performance metrics within acceptable thresholds.
```

### Status Indicators

- **PASS** - Metric is within acceptable threshold
- **FAIL** - Metric exceeded threshold, indicating regression

## CI Integration

### Pull Requests

When a PR is opened:
1. Workflow downloads baseline from main branch
2. Runs benchmark on PR code
3. Compares results against baseline
4. **Fails the PR** if any metric exceeds threshold
5. Posts comparison table as PR comment

### Main/Develop Branch

When code is merged:
1. Workflow runs benchmark on updated code
2. Saves results as new baseline
3. Commits baseline file back to repository (with `[skip ci]`)

## Troubleshooting

### "WordPress not found" Error

Ensure WordPress is installed at the correct path:
```bash
WP_CORE_DIR=/tmp/wordpress
ls -la $WP_CORE_DIR/wp-load.php
```

### "Database not initialized" Error

Check MySQL connection and database creation:
```bash
mysql -h127.0.0.1 -uroot -e "SHOW DATABASES LIKE 'wordpress_test';"
```

### Baseline File Not Found

For local testing, create an initial baseline:
```bash
php bin/benchmark.php \
  --wp-core-dir=/tmp/wordpress \
  --output-file=../.github/performance-baseline.json
```

## Extending Benchmarks

To add a new benchmark:

1. Add benchmark call in `benchmark.php`:
   ```php
   $benchmarks['my_benchmark'] = run_benchmark('My Benchmark Name', function() {
       // Your benchmark code here
       // This code will be timed and measured
   });
   print_metrics($benchmarks['my_benchmark']);
   ```

2. Update baseline values accordingly

3. Consider whether new thresholds are needed for specific metrics

## Performance Best Practices

When developing:

1. **Run benchmarks locally** before submitting PR
2. **Investigate** any metric increases > 10%
3. **Optimize queries** - Use indexes, avoid N+1 problems
4. **Cache aggressively** - Use transients and object caching
5. **Lazy load** - Don't load data until needed
6. **Profile** - Use Xdebug or Query Monitor to find bottlenecks

## Related Documentation

- [Testing Guide](../docs/SETUP.md) - Local development setup
- [Database Schema](../docs/MIGRATIONS.md) - Database optimization tips
- [Feature List](../docs/FEATURE_LIST.md) - Plugin features and architecture
