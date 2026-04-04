## 2026-04-03 - [Fix Silent Filesystem Errors and Remove @ Suppressions]
**Learning:** Using `@` to suppress warnings (e.g., `@unlink`, `@chmod`, `@file_put_contents`) masks underlying filesystem failures and violates the "No Silent Failures" rule. Unhandled `unlink` operations can leave zombie files, and missing guards around directory/file creation obscure configuration or permission issues.
**Action:** Removed `@` suppressions across `AIPS_Session_To_JSON`, added explicit `false` checks for `file_put_contents` and `wp_mkdir_p` with `error_log` fallbacks. Added return value checks to `unlink` in `AIPS_Logger` and `AIPS_Image_Service`, returning `false` or logging warnings appropriately. Added DocBlocks to modified methods to clarify error behavior.

## 2024-04-04 - Handle False Returns in Filesystem Operations
**Learning:** Functions like `filesize()`, `filemtime()`, and `ftell()` can return `false` on failure (e.g., if the file doesn't exist, is unreadable, or a stream error occurs). Passing this boolean directly into arithmetic operations, array mappings, or numeric comparisons without explicit type checking can cause warnings or logical errors.
**Action:** Always strictly check if the return value of filesystem functions `=== false` before using it, and handle the failure gracefully (e.g., returning an empty array, substituting a fallback value, or exiting early).
