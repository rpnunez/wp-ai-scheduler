## 2026-04-27 - Handle Template Data Object Type Error
**Learning:** Legacy template variables might receive class instances instead of expected associative arrays, causing fatal `Cannot use object of type class as array` errors in PHP 8+.
**Action:** Always check `is_object($data)` before accessing elements via `$data['key']` in templates. If it is an object, use getter methods (e.g. `$handler->get_data()`) to retrieve an array context.
## 2026-05-19 - [Fixing WP Core Mocks for PHPUnit]
**Learning:** PHPUnit tests run in limited mode and throw `Call to undefined function` errors (like `esc_sql`, `wp_date`, `wp_timezone`) when WP core functions aren't mocked in `tests/bootstrap.php`.
**Action:** Always add WP core function mocks to `tests/bootstrap.php` if they are used by the components being tested and the test environment runs in limited mode.
