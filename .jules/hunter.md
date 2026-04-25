## 2024-04-25 - History Template TypeError Fix
**Learning:** In templates expecting an array but sometimes receiving a class instance via legacy inclusion methods, explicitly accessing array keys without an `is_array` or object handler causes fatal TypeErrors in PHP 8+.
**Action:** When a template can receive either an array or an object instance for the same variable, handle the object case by checking `is_object()` and `method_exists()` to extract the expected array data. Always verify the resulting variable is an array before accessing its keys.
