 [x] Audit all date/time usage across the codebase (~60 files, ~38 DATETIME columns, ~200+ scattered function calls)
 [x] Create AIPS_DateTime class extending DateTimeImmutable (UTC-first, factory methods, display formatting, human-readable diffs)
 [x] Update AIPS_DB_Manager::get_schema() to change all DATETIME columns to BIGINT UNSIGNED (Unix timestamps)
 [x] Add migrate_to_2_5_0() in AIPS_Upgrades to convert existing DATETIME values to Unix timestamps
 [x] Bump plugin version to 2.5.0
 [x] Update AIPS_Interval_Calculator to use AIPS_DateTime
 [x] Update AIPS_Schedule_Processor to use AIPS_DateTime
 [ ] Update AIPS_Scheduler to use AIPS_DateTime
 [ ] Update all repository classes to read/write timestamps via AIPS_DateTime
 [ ] Update all controller/service classes to use AIPS_DateTime for formatting
 [ ] Update admin templates to use AIPS_DateTime for display
 [ ] Add PHPUnit tests for AIPS_DateTime
 [ ] Update existing tests to work with new timestamp format
 [ ] Run validation

 We are standardizing all date/time operations to use the new `AIPS_DateTime` class. The database now stores all datetime columns as `BIGINT UNSIGNED` (Unix timestamps) instead of DATETIME. Replace ALL uses of `current_time()`, `strtotime()`, `date()`, `date_i18n()`, `gmdate()` with `AIPS_DateTime` equivalents.

Key replacements:

*   `current_time('mysql')` → `AIPS_DateTime::now()->timestamp()` (for DB writes) or `AIPS_DateTime::now()->toMysql()` (for display/logging only)
*   `current_time('mysql', true)` → `AIPS_DateTime::now()->timestamp()` (was UTC, now always UTC)
*   `current_time('timestamp')` → `AIPS_DateTime::now()->timestamp()`
*   `strtotime($datetime_str)` → parse via `AIPS_DateTime::fromMysql($str)->timestamp()` or just use the int directly since DB now stores ints
*   `date('Y-m-d H:i:s', $ts)` → `AIPS_DateTime::fromTimestamp($ts)->toMysql()` (for display only) or just store the int
*   `date_i18n($format, $ts)` → `AIPS_DateTime::fromTimestamp($ts)->toDisplay($format)`
*   `gmdate('c')` → `AIPS_DateTime::now()->toIso8601()`

For DB writes: just store the int timestamp directly. For display: use `AIPS_DateTime::fromTimestamp($ts)->toDisplay()` or `->toHumanDiff()`.

Update these repository files:

1.  **ai-post-scheduler/includes/class-aips-notifications-repository.php** - Uses `current_time('mysql', true)` for created\_at, read\_at
2.  **ai-post-scheduler/includes/class-aips-history-repository.php** - Uses `current_time('mysql')` for timestamps
3.  **ai-post-scheduler/includes/class-aips-authors-repository.php** - Uses `current_time()` for author scheduling timestamps
4.  **ai-post-scheduler/includes/class-aips-author-topics-repository.php** - Uses `current_time()` for topic timestamps
5.  **ai-post-scheduler/includes/class-aips-feedback-repository.php** - Uses `current_time()` for created\_at
6.  **ai-post-scheduler/includes/class-aips-sources-repository.php** - Uses `current_time()` for fetch timestamps. Also uses `calculate_next_run()` which now returns int timestamps.
7.  **ai-post-scheduler/includes/class-aips-sources-data-repository.php** - Uses `current_time()` for fetch timestamps
8.  **ai-post-scheduler/includes/class-aips-template-repository.php** - Uses `current_time()` for created\_at/updated\_at
9.  **ai-post-scheduler/includes/class-aips-voices-repository.php** - Uses `current_time()` for created\_at
10.  **ai-post-scheduler/includes/class-aips-article-structure-repository.php** - Uses `current_time()` for timestamps
11.  **ai-post-scheduler/includes/class-aips-prompt-section-repository.php** - Uses `current_time()` for timestamps
12.  **ai-post-scheduler/includes/class-aips-trending-topics-repository.php** - Uses `current_time()` for researched\_at
13.  **ai-post-scheduler/includes/class-aips-taxonomy-repository.php** - Uses `current_time()` for timestamps
14.  **ai-post-scheduler/includes/class-aips-post-embeddings-repository.php** - Uses `current_time()` for indexed\_at
15.  **ai-post-scheduler/includes/class-aips-internal-links-repository.php** - Uses `current_time()` for timestamps
16.  **ai-post-scheduler/includes/class-aips-metrics-repository.php** - Uses date functions for telemetry
17.  **ai-post-scheduler/includes/class-aips-cache-db-driver.php** - Uses date functions for expires\_at

Keep coding standards: use tabs for indentation, `array()` syntax, WordPress style. Do NOT change any test files. Only change the listed repository files. When a repository previously stored `current_time('mysql')` (a datetime string), now store `AIPS_DateTime::now()->timestamp()` (an int). For `updated_at` columns that previously relied on `ON UPDATE CURRENT_TIMESTAMP` (which no longer works with BIGINT), make sure repository UPDATE methods explicitly set `updated_at`.