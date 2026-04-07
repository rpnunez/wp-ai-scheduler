## 2024-05-25 - [Schedule Initialization Logic Flaw]
**Learning:** Using `calculate_next_run(interval, start_time)` for *initial* schedule creation is incorrect because it calculates `start_time + interval`, effectively skipping the first run.
**Action:** When creating schedules, always treat `start_time` as the explicit *first* execution time (`next_run`), rather than a base for calculation. Only use `calculate_next_run` for subsequent recurring executions.

## 2024-05-25 - [Time-Dependent Test Failures]
**Learning:** Unit tests for time-dependent logic (like scheduling calculators) that use hardcoded dates become "time bombs" when those dates pass. Catch-up logic for past dates can further obscure the interval calculation logic being tested.
**Action:** Use relative future dates (e.g., `strtotime('+1 year')`) in tests to ensure they remain valid regardless of when they are run and to isolate the interval logic from catch-up mechanisms.

## 2024-05-25 - [PHPUnit Mock DB Limitations]
**Learning:** In a headless PHPUnit run (limited mode without actual WP environment), `WPDB` mocks using `stdClass` cannot support complex querying (`get_results`, state retention via `insert`) inherently assumed by Repository pattern classes.
**Action:** When writing tests that hit `WPDB` via repositories or global `$wpdb`, explicitly provide `$wpdb->get_results_return_val` if static data suffices, or invoke `$this->markTestSkipped()` properly early in the test via checking `property_exists($wpdb, 'get_col_return_val')` or `!function_exists('wp_insert_post')` to prevent meaningless object assertions.

## 2024-05-18 - [Missing property check on returned objects]
**Learning:** Functions like `get_category()` or `get_userdata()` can sometimes return objects with missing properties in limited test mock environments or edge cases. Failing to verify `isset($obj->prop)` leads to undefined property notices or fatal errors.
**Action:** Defensively verify that properties exist on returned objects using `isset()` before accessing them, even if the primary existence check (`if ($obj)`) passes.

## 2024-05-18 - [WordPress Settings API Unchecked Checkboxes]
**Learning:** When using the WordPress Settings API with checkboxes, an unchecked checkbox does not send a value in $_POST. This causes `options.php` to ignore the update entirely, effectively making it impossible to "uncheck" settings.
**Action:** Always precede the checkbox with a hidden input using the same name and a '0' value (e.g., `<input type="hidden" name="option_name" value="0">`) to ensure unchecked states are saved.

## 2026-03-25 - [Missing wp_unslash on Arrays]
**Learning:** `$_POST` array inputs containing user-supplied text (e.g. topic titles, names, free-form text from text inputs) must be unslashed before sanitizing or mapping, otherwise WordPress magic quotes will persist and leave backslashes in stored data. However, arrays whose values are IDs or structured keys from checkbox/bulk-selection submissions (e.g. `"type:id"` pairs like `"template:5"`) contain no characters affected by magic quotes and do not need `wp_unslash()`.
**Action:** Apply `wp_unslash()` to `$_POST['array_key']` only when the values are text or user input (titles, labels, free-form strings). Skip it when the values are integer IDs, slugs, or structured keys from bulk-selection checkboxes, as those values are sanitized by `absint()` or `sanitize_key()` and contain no quotable characters.

## 2024-06-18 - [Limited Testing Mode mock logic]
**Learning:** When writing tests that may run in limited mode, missing WordPress core functionality and mocked `$wpdb` properties like `get_var_return_val` need to be explicitly declared on the anonymous mocked class in `tests/bootstrap.php` to prevent PHP 8.2+ dynamic property deprecation errors. These can be set inside specific test methods using `$GLOBALS['wpdb']->get_var_return_val = <expected value>;` to bypass DB interactions and verify test logic accurately.
**Action:** Whenever `$wpdb->get_var()` or similar queries are used in tests meant to run in limited mode, explicitly set the `get_var_return_val` on the mocked `$wpdb` so the mock can predictably return expected database states for the tests to process. Avoid using `@` suppression; instead use `property_exists()` checks to verify the property can be written securely.

## 2024-03-31 - [Fix PHP Warning in AIPS_Schedule_Processor and missing mock support in Tests]
**Learning:** The tests rely on `$wpdb->get_results` properly simulating queries with complex joins, but in limited test mode, a mocked `get_results_return_val` is needed, but missing in `tests/bootstrap.php`. Additionally, `AIPS_Schedule_Processor::execute_schedule_logic()` assumes `$actual_template_model` has a `post_quantity` property, which emits a PHP Warning if the object exists but lacks the property (e.g., from mock environments or incomplete data).
**Action:** Added `get_results_return_val` to the mock `wpdb` class in `tests/bootstrap.php` and modified `get_results()` to return it if set. Also fixed the PHP Warning in `AIPS_Schedule_Processor` by checking `isset($actual_template_model->post_quantity)`.
## 2026-04-04 - [Missing isset on db queries]
**Learning:** Directly accessing properties of objects returned by database queries like `$wpdb->get_row()` triggers PHP Warnings if the query fails or returns nothing (null) and the code assumes an object structure.
**Action:** Always wrap direct property access from potentially null query results with an `isset()` check (e.g. `isset($results->count) ? $results->count : 0`) before casting or returning.
