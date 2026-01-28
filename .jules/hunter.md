## 2024-05-25 - Schedule Initialization Logic Flaw
**Learning:** Using `calculate_next_run(interval, start_time)` for *initial* schedule creation is incorrect because it calculates `start_time + interval`, effectively skipping the first run.
**Action:** When creating schedules, always treat `start_time` as the explicit *first* execution time (`next_run`), rather than a base for calculation. Only use `calculate_next_run` for subsequent recurring executions.

## 2025-02-18 - Clipboard Fallback Layout Shifts
**Learning:** Using `document.body.appendChild(textarea)` for `document.execCommand('copy')` fallback causes visible layout shifts because the appended element is part of the flow.
**Action:** Always position the temporary textarea off-screen using `position: fixed; top: 0; left: -9999px;` to ensure it is invisible and doesn't affect layout.

## 2025-02-18 - Test Suite Conflicts
**Learning:** The `ai-post-scheduler` test suite has a fatal error due to `esc_html__` being redeclared in `tests/integration-test-post-review.php`, conflicting with `tests/bootstrap.php`.
**Action:** When fixing the test suite in the future, `tests/integration-test-post-review.php` should likely be excluded or its mock definitions refactored to check `function_exists`.
