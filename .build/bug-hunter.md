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

## 2026-04-15 - [PHP 8 Strict Typing with Anonymous Mock Classes]
**Learning:** Returning anonymous classes (`new class() {}`) that do not explicitly implement required interfaces (like `AIPS_AI_Service_Interface`) will cause fatal `TypeError`s in PHP 8+ when injected into type-hinted constructors. Additionally, if an anonymous class explicitly implements an interface, it must define *all* methods declared in that interface to avoid a fatal "contains abstract methods" error, even if those methods aren't used in the test.
**Action:** When mocking dependencies for PHPUnit tests, always define explicit standard stub classes (e.g., `class AIPS_Test_Stub_AI_Service implements AIPS_AI_Service_Interface`) rather than relying on anonymous classes, and ensure all interface methods have dummy implementations. Use unique class names per test file (e.g. `_For_Suggestions`) if `class_exists` checks cannot be used safely to prevent redeclaration errors.
