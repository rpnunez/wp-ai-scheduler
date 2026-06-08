
## 2025-02-18 - Optimized Generated Posts admin page load
**Learning:** Found N+1 query patterns in `AIPS_Generated_Posts_Controller::render_page()` when iterating over `$history['items']` and `$partial_generations['items']` calling `get_post()`. Also found multiple `get_option('date_format')` and `get_option('time_format')` calls per item within the loops.
**Action:** Used `_prime_post_caches(array_unique($post_ids), false, true)` to pre-fetch post caches for both history loops (wrapped in `function_exists` check), and cached the formatted datetime string outside the loops, significantly reducing database queries per request for this admin view.
