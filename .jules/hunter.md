## 2026-04-27 - Handle Template Data Object Type Error
**Learning:** Legacy template variables might receive class instances instead of expected associative arrays, causing fatal `Cannot use object of type class as array` errors in PHP 8+.
**Action:** Always check `is_object($data)` before accessing elements via `$data['key']` in templates. If it is an object, use getter methods (e.g. `$handler->get_data()`) to retrieve an array context.
## 2026-05-10 - Fallback to strtotime() for interval calculator
**Learning:** Functions expecting timestamp arguments may receive formatted string dates from legacy flows.
**Action:** Always implement type guards like `strtotime($var) !== false` when parsing parameters that are expected to be numeric timestamps. Update corresponding tests to assert strict numeric values.
