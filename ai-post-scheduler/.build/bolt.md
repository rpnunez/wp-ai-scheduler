## 2024-06-08 - Hoist get_option in Generated Posts Controller
**Area:** ai-post-scheduler/includes/class-aips-generated-posts-controller.php
**Status:** opened PR
**PR:** ⚡ Bolt: Hoist date and time format options outside of loops in generated posts controller
**Learning:** Hoisting get_option('date_format') and get_option('time_format') out of loops reduces redundant DB queries and function calls.
**Action:** Always check loops for repeated WP option calls and extract them into variables.

## 2026-07-05 - [N+1 Query Fix]
**Area:** Multiple AJAX Controllers (Authors, Internal Links, Post Review, Taxonomy)
**Status:** opened PR
**PR:** ⚡ Bolt: Fix N+1 post queries by pre-fetching bulk caches
**Learning:** Loops calling `get_post()` sequentially trigger excessive database lookups.
**Action:** Pre-fetch post IDs into arrays and use `_prime_post_caches()` before loops.
