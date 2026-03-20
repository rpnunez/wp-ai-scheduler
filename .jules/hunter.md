## 2024-05-25 - Schedule Initialization Logic Flaw
**Learning:** Using `calculate_next_run(interval, start_time)` for *initial* schedule creation is incorrect because it calculates `start_time + interval`, effectively skipping the first run.
**Action:** When creating schedules, always treat `start_time` as the explicit *first* execution time (`next_run`), rather than a base for calculation. Only use `calculate_next_run` for subsequent recurring executions.

## 2024-05-25 - Time-Dependent Test Failures
**Learning:** Unit tests for time-dependent logic (like scheduling calculators) that use hardcoded dates become "time bombs" when those dates pass. Catch-up logic for past dates can further obscure the interval calculation logic being tested.
**Action:** Use relative future dates (e.g., `strtotime('+1 year')`) in tests to ensure they remain valid regardless of when they are run and to isolate the interval logic from catch-up mechanisms.

## 2024-05-25 - PHPUnit Mock DB Limitations
**Learning:** In a headless PHPUnit run (limited mode without actual WP environment), `WPDB` mocks using `stdClass` cannot support complex querying (`get_results`, state retention via `insert`) inherently assumed by Repository pattern classes.
**Action:** When writing tests that hit `WPDB` via repositories or global `$wpdb`, explicitly provide `$wpdb->get_results_return_val` if static data suffices, or invoke `$this->markTestSkipped()` properly early in the test via checking `property_exists($wpdb, 'get_col_return_val')` or `!function_exists('wp_insert_post')` to prevent meaningless object assertions.
