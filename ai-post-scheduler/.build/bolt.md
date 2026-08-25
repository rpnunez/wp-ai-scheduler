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
## 2026-07-05 - [N+1 Query Fix]
**Area:** Multiple AJAX Controllers (Authors, Internal Links, Post Review, Taxonomy)
**Status:** opened PR
**PR:** ⚡ Bolt: Fix N+1 post queries by pre-fetching bulk caches
**Learning:** Loops calling `get_post()` sequentially trigger excessive database lookups.
**Action:** Pre-fetch post IDs into arrays and use `_prime_post_caches()` before loops.
## 2026-08-10 - Content Auditor N+1 Optimization
**Area:** ai-post-scheduler/includes/class-aips-content-auditor.php
**Status:** opened PR
**PR:** ⚡ Bolt: Fix N+1 post queries in Content Auditor
**Learning:** Always use `_prime_post_caches` when querying multiple post titles/categories in a loop.
**Action:** Verify if other auditor/scraper functions run into the same N+1 queries.
## 2024-06-08 - Fix N+1 queries in Schedule Controller modal data
**Area:** ai-post-scheduler/includes/class-aips-schedule-controller.php
**Status:** opened PR
**PR:** ⚡ Bolt: Fix N+1 post queries in schedule controller modal data
**Learning:** Modals processing lists of generated posts need post cache prefetching just like AJAX handlers.
**Action:** Always pre-fetch WP post caches before loops referencing multiple post IDs.
