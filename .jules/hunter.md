## 2024-05-25 - Schedule Initialization Logic Flaw
**Learning:** Using `calculate_next_run(interval, start_time)` for *initial* schedule creation is incorrect because it calculates `start_time + interval`, effectively skipping the first run.
**Action:** When creating schedules, always treat `start_time` as the explicit *first* execution time (`next_run`), rather than a base for calculation. Only use `calculate_next_run` for subsequent recurring executions.

## 2024-05-25 - Time-Dependent Test Failures
**Learning:** Unit tests for time-dependent logic (like scheduling calculators) that use hardcoded dates become "time bombs" when those dates pass. Catch-up logic for past dates can further obscure the interval calculation logic being tested.
**Action:** Use relative future dates (e.g., `strtotime('+1 year')`) in tests to ensure they remain valid regardless of when they are run and to isolate the interval logic from catch-up mechanisms.

## 2026-02-03 - Incomplete Test Environment Mocks
**Learning:** The "limited mode" test environment in `bootstrap.php` mocks `$wpdb` as an anonymous class but misses standard methods (`get_charset_collate`, `esc_like`, `get_col`) and helper functions (`wp_parse_args`, `dbDelta`). This causes fatal errors in tests that rely on these basics, masking actual logic failures.
**Action:** When adding new DB interactions or WP function calls, always verify if they are supported by the `bootstrap.php` mock. If adding a new test file, ensure the class under test is manually loaded in `bootstrap.php`'s fallback loader.
