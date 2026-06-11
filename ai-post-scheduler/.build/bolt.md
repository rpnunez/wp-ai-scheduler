## 2024-06-08 - Hoist get_option in Generated Posts Controller
**Area:** ai-post-scheduler/includes/class-aips-generated-posts-controller.php
**Status:** opened PR
**PR:** ⚡ Bolt: Hoist date and time format options outside of loops in generated posts controller
**Learning:** Hoisting get_option('date_format') and get_option('time_format') out of loops reduces redundant DB queries and function calls.
**Action:** Always check loops for repeated WP option calls and extract them into variables.

## 2024-06-11 - Prevent N+1 query in authors and generated posts
**Area:** ai-post-scheduler/includes/class-aips-authors-controller.php, ai-post-scheduler/includes/class-aips-generated-posts-controller.php
**Status:** opened PR
**PR:** ⚡ Bolt: Prevent N+1 queries in authors and generated posts controllers
**Learning:** Hoisting get_post lookups and priming post caches reduces redundant queries and improves AJAX response time.
**Action:** Use _prime_post_caches for large loops containing get_post calls.
