## 2024-06-08 - Hoist get_option in Generated Posts Controller
**Area:** ai-post-scheduler/includes/class-aips-generated-posts-controller.php
**Status:** opened PR
**PR:** ⚡ Bolt: Hoist date and time format options outside of loops in generated posts controller
**Learning:** Hoisting get_option('date_format') and get_option('time_format') out of loops reduces redundant DB queries and function calls.
**Action:** Always check loops for repeated WP option calls and extract them into variables.

## 2024-06-13 - Fix N+1 queries in bulk operations
**Area:** ai-post-scheduler/includes/class-aips-post-review.php
**Status:** opened PR
**PR:** ⚡ Bolt: Fix N+1 queries during bulk operations in generated posts admin
**Learning:** Bulk operations looping over post IDs such as `get_post()` without pre-fetching cause N+1 queries. We can mitigate this by injecting `_prime_post_caches()` prior to the loop.
**Action:** Always pre-fetch posts using `_prime_post_caches()` when dealing with arrays of IDs that will sequentially be retrieved via `get_post()`.
