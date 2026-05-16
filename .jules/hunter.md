## 2026-04-27 - Handle Template Data Object Type Error
**Learning:** Legacy template variables might receive class instances instead of expected associative arrays, causing fatal `Cannot use object of type class as array` errors in PHP 8+.
**Action:** Always check `is_object($data)` before accessing elements via `$data['key']` in templates. If it is an object, use getter methods (e.g. `$handler->get_data()`) to retrieve an array context.

## 2026-05-16 - Mock Missing WP Functions in Test Environment
**Learning:** Certain WordPress functions like `esc_sql` and `wp_date` are not available when running tests in limited mode (without the full WP environment).
**Action:** When a test fails with "Call to undefined function [wp_function]" in limited mode tests, ensure to mock that function in `tests/bootstrap.php`.
