# Hunter's Journal — Bug Patterns & Domain Learnings

This file merges two complementary journals:
- **Part 1 — Domain & Testing Patterns** (originally `.jules/hunter.md`): WordPress domain quirks, PHPUnit gotchas, and testing best practices discovered while hunting bugs.
- **Part 2 — Security & Error-Handling Patterns** (originally `.build/bug-hunter.md`): Vulnerability fixes, silent failure elimination, and defensive programming patterns.

---

## Part 1 — Domain & Testing Patterns

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

---

## Part 2 — Security & Error-Handling Patterns

## 2026-04-03 - [Fix Silent Filesystem Errors and Remove @ Suppressions]
**Learning:** Using `@` to suppress warnings (e.g., `@unlink`, `@chmod`, `@file_put_contents`) masks underlying filesystem failures and violates the "No Silent Failures" rule. Unhandled `unlink` operations can leave zombie files, and missing guards around directory/file creation obscure configuration or permission issues.
**Action:** Removed `@` suppressions across `AIPS_Session_To_JSON`, added explicit `false` checks for `file_put_contents` and `wp_mkdir_p` with `error_log` fallbacks. Added return value checks to `unlink` in `AIPS_Logger` and `AIPS_Image_Service`, returning `false` or logging warnings appropriately. Added DocBlocks to modified methods to clarify error behavior.

## 2024-04-06 - Fix silent filesystem failures
**Learning:** Filesystem functions like `filesize()`, `filemtime()`, `glob()`, `fopen()`, and `ftell()` can return `false` on failure. If not checked explicitly, these boolean values can propagate to strict type-expecting functions (e.g. `size_format(filesize($file))`), causing fatal TypeErrors or unexpected application flow. `file_put_contents` needs directory writability checks, not just a false return check.
**Action:** Always verify the return value of filesystem operations using strict equality `=== false`. Provide safe fallback values. When dealing with directory modifications, verify `is_writable()` before writing files and verify `is_readable()` before opening files.

## 2024-04-06 - [Fix Silent Filesystem Errors in validate-mcp-bridge.php]
**Learning:** `filesize()` can return `false` on failure, which causes issues when passed to functions like `number_format()`.
**Action:** Always verify the return value of filesystem operations using strict equality `=== false`. Provide safe fallback values.

## 2026-04-08 - [Fix Undefined Variable in create_htaccess_protection]
**Learning:** Using an undefined variable in a conditional check like `is_writable($base_dir)` throws a PHP warning and fails the condition, leading to silent failures when attempting to create protective files.
**Action:** Replaced the undefined variable with the correct parameter `$dir`. Added regression test to ensure the method executes successfully without warnings.

## 2026-04-15 - [Fix Silent json_decode Failures on Scalar Decodes]
**Learning:** `json_decode()` can return scalar values (like strings or integers) for valid JSON inputs (e.g. `'"string"'`). Relying solely on `json_last_error() === JSON_ERROR_NONE` or assuming the result is an array can lead to silent TypeErrors or invalid offset accesses when code tries to access keys on a boolean/string/integer.
**Action:** Always verify that the decoded JSON result is an array (or the expected type) using `is_array($decoded)` before proceeding, and ensure safe fallbacks or explicit error handling if it is not.

## 2026-04-15 - [PHP 8 Strict Typing with Anonymous Mock Classes]
**Learning:** Returning anonymous classes (`new class() {}`) that do not explicitly implement required interfaces (like `AIPS_AI_Service_Interface`) will cause fatal `TypeError`s in PHP 8+ when injected into type-hinted constructors. Additionally, if an anonymous class explicitly implements an interface, it must define *all* methods declared in that interface to avoid a fatal "contains abstract methods" error, even if those methods aren't used in the test.
**Action:** When mocking dependencies for PHPUnit tests, always define explicit standard stub classes (e.g., `class AIPS_Test_Stub_AI_Service implements AIPS_AI_Service_Interface`) rather than relying on anonymous classes, and ensure all interface methods have dummy implementations. Use unique class names per test file (e.g. `_For_Suggestions`) if `class_exists` checks cannot be used safely to prevent redeclaration errors.
