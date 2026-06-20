## 2024-06-08 - Hoist get_option in Generated Posts Controller
**Area:** ai-post-scheduler/includes/class-aips-generated-posts-controller.php
**Status:** opened PR
**PR:** ⚡ Bolt: Hoist date and time format options outside of loops in generated posts controller
**Learning:** Hoisting get_option('date_format') and get_option('time_format') out of loops reduces redundant DB queries and function calls.
**Action:** Always check loops for repeated WP option calls and extract them into variables.
## 2026-06-20 - Fix N+1 post queries by priming post cache
**Area:** ai-post-scheduler/includes (Multiple Controllers)
**Status:** opened PR
**PR:** ⚡ Bolt: Fix N+1 post queries by priming post cache
**Learning:** In loops where `get_post($id)` is repeatedly called for various arrays, doing a single `_prime_post_caches()` with all the IDs ensures that they are fetched from the database in a single batched query instead of one query per post.
**Action:** Identify `get_post()` calls inside loops processing multiple objects or IDs, pre-fetch IDs into an array, and use `_prime_post_caches()` outside the loop.
