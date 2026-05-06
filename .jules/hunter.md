## 2026-04-27 - Handle Template Data Object Type Error
**Learning:** Legacy template variables might receive class instances instead of expected associative arrays, causing fatal `Cannot use object of type class as array` errors in PHP 8+.
**Action:** Always check `is_object($data)` before accessing elements via `$data['key']` in templates. If it is an object, use getter methods (e.g. `$handler->get_data()`) to retrieve an array context.

## 2026-05-06 - [Fix TypeErrors on get_row database results]
**Learning:** When using `$wpdb->get_row()`, it may return `null` if no records are found or if the query fails. Directly accessing properties on the result via `isset($result->prop)` causes warnings/fatal errors in PHP 8+ if `$result` is not an object.
**Action:** Always wrap `get_row()` property checks with `is_object($result) && isset($result->prop)` to prevent TypeErrors, e.g. `(is_object($result) && isset($result->total)) ? (int) $result->total : 0`.
