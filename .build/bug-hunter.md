## 2026-04-03 - [Fix Silent Filesystem Errors and Remove @ Suppressions]
**Learning:** Using `@` to suppress warnings (e.g., `@unlink`, `@chmod`, `@file_put_contents`) masks underlying filesystem failures and violates the "No Silent Failures" rule. Unhandled `unlink` operations can leave zombie files, and missing guards around directory/file creation obscure configuration or permission issues.
**Action:** Removed `@` suppressions across `AIPS_Session_To_JSON`, added explicit `false` checks for `file_put_contents` and `wp_mkdir_p` with `error_log` fallbacks. Added return value checks to `unlink` in `AIPS_Logger` and `AIPS_Image_Service`, returning `false` or logging warnings appropriately. Added DocBlocks to modified methods to clarify error behavior.

## 2026-04-05 - [Fix unsafe property access on $wpdb->get_row()]
**Learning:** Using `$wpdb->get_row()` or `$wpdb->get_results()` may return `null` or an object without specific properties if the query fails or fields are missing. Casting missing properties like `(int) $results->total` directly leads to `Undefined property` warnings.
**Action:** Always wrap `$wpdb` return property accesses with `isset()` before casting or using them to provide safe defaults and prevent runtime warnings or errors.

## 2026-04-06 - [Fix silent filesystem failures]
**Learning:** Filesystem functions like `filesize()`, `filemtime()`, `glob()`, `fopen()`, and `ftell()` can return `false` on failure. If not checked explicitly, these boolean values can propagate to strict type-expecting functions (e.g. `size_format(filesize($file))`), causing fatal TypeErrors or unexpected application flow. `file_put_contents` needs directory writability checks, not just a false return check.
**Action:** Always verify the return value of filesystem operations using strict equality `=== false`. Provide safe fallback values. When dealing with directory modifications, verify `is_writable()` before writing files and verify `is_readable()` before opening files.
