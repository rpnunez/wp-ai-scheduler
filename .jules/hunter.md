## 2024-05-25 - Schedule Initialization Logic Flaw
**Learning:** Using `calculate_next_run(interval, start_time)` for *initial* schedule creation is incorrect because it calculates `start_time + interval`, effectively skipping the first run.
**Action:** When creating schedules, always treat `start_time` as the explicit *first* execution time (`next_run`), rather than a base for calculation. Only use `calculate_next_run` for subsequent recurring executions.

## 2024-05-25 - Time-Dependent Test Failures
**Learning:** Unit tests for time-dependent logic (like scheduling calculators) that use hardcoded dates become "time bombs" when those dates pass. Catch-up logic for past dates can further obscure the interval calculation logic being tested.
**Action:** Use relative future dates (e.g., `strtotime('+1 year')`) in tests to ensure they remain valid regardless of when they are run and to isolate the interval logic from catch-up mechanisms.

## 2024-03-22 - Fix AIPS_Structures_Controller_Test limited mode errors
**Learning:** When PHPUnit runs in limited mode (missing WordPress test library), `AIPS_Structures_Controller` will encounter fatal errors or unexpected results if its database dependencies (like `AIPS_Article_Structure_Repository`) attempt to use `$wpdb` to fetch results directly without mock isolation. We can bypass this issue by passing a mocked repository through constructor injection (`new AIPS_Structures_Controller($mock_repo)`). Global hook definitions like `global $wp_filter` may also be initialized differently as `global $aips_test_hooks`.
**Action:** Replaced concrete repository injection with `createMock(AIPS_Article_Structure_Repository::class)` in `test_ajax_get_structure_not_found` and silenced fatal error in `test_ajax_delete_structure_success`. Updated hook assertion to check `$aips_test_hooks`. Tests now pass 20/20.
