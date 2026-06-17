## 2024-06-08 - Hoist get_option in Generated Posts Controller
**Area:** ai-post-scheduler/includes/class-aips-generated-posts-controller.php
**Status:** opened PR
**PR:** ⚡ Bolt: Hoist date and time format options outside of loops in generated posts controller
**Learning:** Hoisting get_option('date_format') and get_option('time_format') out of loops reduces redundant DB queries and function calls.
**Action:** Always check loops for repeated WP option calls and extract them into variables.

## 2026-06-17 - Fix N+1 post queries in generated posts controller
**Area:** ai-post-scheduler/includes/class-aips-generated-posts-controller.php
**Status:** opened PR
**PR:** ⚡ Bolt: Fix N+1 post queries in generated posts controller
**Learning:** Calling `get_post()` inside loops causes N+1 query patterns. Bulk loading the post IDs beforehand using `_prime_post_caches` ensures better database lookup performance.
**Action:** Use `_prime_post_caches()` with `array_unique` mapping of post IDs before entering loops that use `get_post()`.
