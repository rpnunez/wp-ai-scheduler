## 2024-05-24 - Fix Silent Failures in Session JSON Export
**Learning:** Relying on the `@` error suppression operator (e.g., `@chmod`, `@unlink`, `@file_put_contents`) masks system-level failures during file operations like JSON export creation and cleanup. This makes debugging difficult and allows operations to fail silently without application awareness.
**Action:** Use pre-flight checks (like `is_writable()`), explicitly capture boolean return values (e.g., from `file_put_contents`), and log failures descriptively using `error_log()` instead of masking them with `@`.
