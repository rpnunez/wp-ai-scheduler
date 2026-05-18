## 2026-04-27 - Handle Template Data Object Type Error
**Learning:** Legacy template variables might receive class instances instead of expected associative arrays, causing fatal `Cannot use object of type class as array` errors in PHP 8+.
**Action:** Always check `is_object($data)` before accessing elements via `$data['key']` in templates. If it is an object, use getter methods (e.g. `$handler->get_data()`) to retrieve an array context.

## 2026-05-09 - Interval Calculator Date String Parsing
**Learning:** `AIPS_Interval_Calculator::calculate_next_run` strictly enforced `is_numeric` on `$start_time`, but it was often passed string dates (e.g. '2030-06-15 10:00:00') from other components or tests, causing silent fallback to the current timestamp and incorrect scheduling offsets.
**Action:** When a method signature allows flexible date formats (`$start_time`), implement type guards that fallback gracefully (e.g., using `strtotime($start_time) !== false`). Tests evaluating timestamps should be strict about type casting instead of relying on loose equality with string dates.
