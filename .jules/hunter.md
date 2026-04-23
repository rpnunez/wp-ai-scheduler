
## 2026-04-23 - History Template TypeError with Object
**Learning:** The `ai-post-scheduler/templates/admin/history.php` template expected the `$history` variable to be an array and directly checked `isset($history['items'])`. However, when `$history` was passed as an object (e.g., an instance of `AIPS_History`), this caused a fatal `TypeError: Cannot use object of type AIPS_History as array`.
**Action:** When a template can receive variables as either arrays or objects (often due to legacy code paths or flexible APIs), explicitly check the variable type using `is_object()` before treating it like an array. If it's an object, retrieve the data via its designated methods (e.g., `method_exists($obj, 'get_history')`).
