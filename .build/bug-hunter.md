## 2026-04-03 - [Fix Silent Filesystem Errors and Remove @ Suppressions]
**Learning:** Using `@` to suppress warnings (e.g., `@unlink`, `@chmod`, `@file_put_contents`) masks underlying filesystem failures and violates the "No Silent Failures" rule. Unhandled `unlink` operations can leave zombie files, and missing guards around directory/file creation obscure configuration or permission issues.
**Action:** Removed `@` suppressions across `AIPS_Session_To_JSON`, added explicit `false` checks for `file_put_contents` and `wp_mkdir_p` with `error_log` fallbacks. Added return value checks to `unlink` in `AIPS_Logger` and `AIPS_Image_Service`, returning `false` or logging warnings appropriately. Added DocBlocks to modified methods to clarify error behavior.

## 2024-04-06 - Fix silent filesystem failures
**Learning:** Filesystem functions like `filesize()`, `filemtime()`, `glob()`, `fopen()`, and `ftell()` can return `false` on failure. If not checked explicitly, these boolean values can propagate to strict type-expecting functions (e.g. `size_format(filesize($file))`), causing fatal TypeErrors or unexpected application flow. `file_put_contents` needs directory writability checks, not just a false return check.
**Action:** Always verify the return value of filesystem operations using strict equality `=== false`. Provide safe fallback values. When dealing with directory modifications, verify `is_writable()` before writing files and verify `is_readable()` before opening files.

## 2024-04-06 - [Fix Silent Filesystem Errors in validate-mcp-bridge.php]
**Learning:** `filesize()` can return `false` on failure, which causes issues when passed to functions like `number_format()`.
**Action:** Always verify the return value of filesystem operations using strict equality `=== false`. Provide safe fallback values.
## 2024-05-18 - [Fix wp_unslash and filesize false return issues]
**Learning:** `filesize()` and `filemtime()` can return false, triggering TypeError in PHP 8+. Applying `wp_unslash` recursively to variables triggers a double unslash that causes valid text to be corrupted.
**Action:** Applied strict checks on `filesize()` and `filemtime()` by assigning 0 or "Unknown". Removed repeated `wp_unslash` across nested structures.
