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
