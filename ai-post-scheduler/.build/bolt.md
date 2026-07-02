## 2024-06-08 - Hoist get_option in Generated Posts Controller
**Area:** ai-post-scheduler/includes/class-aips-generated-posts-controller.php
**Status:** opened PR
**PR:** ⚡ Bolt: Hoist date and time format options outside of loops in generated posts controller
**Learning:** Hoisting get_option('date_format') and get_option('time_format') out of loops reduces redundant DB queries and function calls.
**Action:** Always check loops for repeated WP option calls and extract them into variables.
## 2024-06-08 - Fix N+1 queries in Authors Controller
**Area:** ai-post-scheduler/includes/class-aips-authors-controller.php
**Status:** opened PR
**PR:** ⚡ Bolt: Fix N+1 queries in Authors Controller
**Learning:** Using `_prime_post_caches` prevents N+1 queries in loops calling `get_post()`.
**Action:** Always pre-fetch WP post caches before loops referencing multiple post IDs.
