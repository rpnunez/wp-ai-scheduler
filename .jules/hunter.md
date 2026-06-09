## 2026-04-27 - Handle Template Data Object Type Error
**Learning:** Legacy template variables might receive class instances instead of expected associative arrays, causing fatal `Cannot use object of type class as array` errors in PHP 8+.
**Action:** Always check `is_object($data)` before accessing elements via `$data['key']` in templates. If it is an object, use getter methods (e.g. `$handler->get_data()`) to retrieve an array context.
## 2026-05-24 - Missing Array Unslashing on Superglobals
**Learning:** Checking `is_array($_POST['items'])` correctly prevents `TypeError`s, but the resulting array must still be passed through `wp_unslash()` before calling functions like `array_map()` or iterating over it. Without `wp_unslash()`, magic quotes or escaped characters bypass security validation and corrupt intended data payload values.
**Action:** Always wrap superglobal arrays directly in `wp_unslash()` when extracting them or mapping over them (e.g. `array_map('absint', wp_unslash($_POST['ids']))`), even when an `is_array` check proves its type.
