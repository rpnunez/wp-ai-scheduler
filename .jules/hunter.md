## 2026-04-27 - Handle Template Data Object Type Error
**Learning:** Legacy template variables might receive class instances instead of expected associative arrays, causing fatal `Cannot use object of type class as array` errors in PHP 8+.
**Action:** Always check `is_object($data)` before accessing elements via `$data['key']` in templates. If it is an object, use getter methods (e.g. `$handler->get_data()`) to retrieve an array context.
## 2024-05-22 - Missing WordPress Mock Function in Tests
**Learning:** In the limited unit testing environment (when WordPress is not loaded), WordPress escaping functions are not naturally available. Missing functions, such as `esc_sql`, lead to fatal PHP errors (`Call to undefined function`) during test execution when those functions are invoked by the classes under test.
**Action:** When tests fail with missing WordPress functions in isolated environments, proactively mock the necessary WordPress functions (like `esc_sql`) in `tests/bootstrap.php` to replicate the required behavior safely.
