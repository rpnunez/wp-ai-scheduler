## 2026-04-27 - Handle Template Data Object Type Error
**Learning:** Legacy template variables might receive class instances instead of expected associative arrays, causing fatal `Cannot use object of type class as array` errors in PHP 8+.
**Action:** Always check `is_object($data)` before accessing elements via `$data['key']` in templates. If it is an object, use getter methods (e.g. `$handler->get_data()`) to retrieve an array context.

## 2024-05-21 - Interval Calculator Start Time Fallback
**Learning:** `calculate_next_run` methods expecting Unix timestamps must safely fallback using `strtotime()` when string date representations are provided instead of strictly rejecting non-numeric inputs.
**Action:** When method signatures expect numeric timestamps but might receive string dates (e.g. `$start_time`), implement type guards that gracefully fallback using `strtotime($var) !== false`. Corresponding tests must assert against strict numeric values rather than comparing timestamps to formatted date strings.
