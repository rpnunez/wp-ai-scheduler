
## 2025-02-14 - Removed `@` suppressions in AIPS_Session_To_JSON
**Learning:** Suppressing errors with `@` for filesystem operations (like `chmod`, `file_put_contents`, and `unlink`) creates silent failures. If file permissions block an action, the application flow continues without warning, making debugging nearly impossible and risking data loss/inconsistency.
**Action:** Removed all `@` suppressions from `includes/class-aips-session-to-json.php`. Added explicit error checking, pre-flight checks (like `is_writable()` before deleting), and integrated `AIPS_Logger` to record these failures correctly.
