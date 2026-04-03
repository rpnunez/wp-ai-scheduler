
## 2025-02-13 - Fix Silent Filesystem Errors and Remove @ Suppressions
**Learning:** Using `@` to suppress warnings (e.g., `@unlink`, `@chmod`, `@file_put_contents`) masks underlying filesystem failures and violates the "No Silent Failures" rule. Unhandled `unlink` operations can leave zombie files, and missing guards around directory/file creation obscure configuration or permission issues.
**Action:** Removed `@` suppressions across `AIPS_Session_To_JSON`, added explicit `false` checks for `file_put_contents` and `wp_mkdir_p` with `error_log` fallbacks. Added return value checks to `unlink` in `AIPS_Logger` and `AIPS_Image_Service`, returning `false` or logging warnings appropriately. Added DocBlocks to modified methods to clarify error behavior.
