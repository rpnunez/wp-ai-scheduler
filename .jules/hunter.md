## 2026-04-27 - Handle Template Data Object Type Error
**Learning:** Legacy template variables might receive class instances instead of expected associative arrays, causing fatal `Cannot use object of type class as array` errors in PHP 8+.
**Action:** Always check `is_object($data)` before accessing elements via `$data['key']` in templates. If it is an object, use getter methods (e.g. `$handler->get_data()`) to retrieve an array context.

## 2026-05-04 - Test Suite Reliability Fixes
**Learning:** In isolated testing environments, WP AJAX functions like `check_ajax_referer` rely on `$_REQUEST` instead of just `$_POST`, and responses from `wp_send_json_error()` throw `WPAjaxDieContinueException` or `WPAjaxDieStopException` which cause "Risky" unclosed output buffer warnings or failures if not caught. Furthermore, isolated mock database implementations may require manually injecting `get_results_return_val` to satisfy log retrieval, and parameter strictness in scheduling methods (expecting integer Unix timestamps instead of strings) causes tests to fail unexpectedly under PHP 8+.
**Action:** Always wrap `wp_ajax` calls in tests with `try/catch` expecting Die exceptions and `ob_start/ob_get_clean`, ensure `$_REQUEST` is populated for nonces, and verify that test mocks and parameter data types match the production environment constraints.
