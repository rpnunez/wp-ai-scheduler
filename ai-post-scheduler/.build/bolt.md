## 2026-06-07 - Optimize Generated Posts formatting overhead
**Area:** includes/class-aips-generated-posts-controller.php
**Status:** opened PR
**PR:** [pending]
**Learning:** Repeated get_option calls in loops to fetch date and time formats are unnecessary and should be cached in a variable beforehand, especially in list/listing endpoints.
**Action:** Always extract get_option calls for format strings (and other constants) outside of iterating loops to minimize function overhead and database/object cache interactions.
