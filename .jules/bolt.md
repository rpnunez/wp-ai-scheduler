
## 2024-05-19 - Generated Posts Controller N+1 Optimization
**Learning:** Found N+1 query loops where `get_post` was called for each history item inside `AIPS_Generated_Posts_Controller::render_page`.
**Action:** Bulk primed post caches using `_prime_post_caches()` before iterating over the history items, resolving the N+1 issue for all tabs (Generated Posts, Pending Review, Partial Generations) on the history admin page. This ensures all post data is loaded in a single bulk query rather than executing one query per record.
