## 2024-04-25 - Fix `TypeError` in history template
 **Learning:** Passing objects like `AIPS_History` to templates where arrays are expected can cause fatal `TypeError`s (`Cannot use object of type ... as array`) when extracting array keys. The fallback pattern using `isset($history['items'])` without type checking triggers this error on objects in PHP 8.
 **Action:** Always verify variables are arrays with `is_array()` before attempting to access their keys, especially in templates that may receive data from multiple code paths or legacy patterns.
