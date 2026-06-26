## 2024-06-08 - Hoist get_option in Generated Posts Controller
**Area:** ai-post-scheduler/includes/class-aips-generated-posts-controller.php
**Status:** opened PR
**PR:** ⚡ Bolt: Hoist date and time format options outside of loops in generated posts controller
**Learning:** Hoisting get_option('date_format') and get_option('time_format') out of loops reduces redundant DB queries and function calls.
**Action:** Always check loops for repeated WP option calls and extract them into variables.

## 2026-06-26 - Pre-fetch posts in schedule modal data
**Area:** ai-post-scheduler/includes/class-aips-schedule-controller.php
**Status:** opened PR
**PR:** ⚡ Bolt: Fix N+1 queries in schedule controller modal generation
**Learning:** Pre-fetching posts using `_prime_post_caches()` before a loop prevents N+1 queries when loading preview payloads.
**Action:** Always pre-fetch posts into cache before iterating over a list of post IDs.
