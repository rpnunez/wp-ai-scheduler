## 2026-04-27 - Handle Template Data Object Type Error
**Learning:** Legacy template variables might receive class instances instead of expected associative arrays, causing fatal `Cannot use object of type class as array` errors in PHP 8+.
**Action:** Always check `is_object($data)` before accessing elements via `$data['key']` in templates. If it is an object, use getter methods (e.g. `$handler->get_data()`) to retrieve an array context.

## 2026-05-23 - Mocking WP Core Functions in Limited Testing Mode
**Learning:** When PHPUnit tests fail in limited mode with "Call to undefined function" for WordPress core functions (e.g., `esc_sql`, `wp_date`), it's because the full WP environment isn't loaded.
**Action:** Ensure these functions are safely mocked with conditional checks (e.g. `if (!function_exists('wp_date'))`) within `tests/bootstrap.php` to prevent fatal test execution errors.
