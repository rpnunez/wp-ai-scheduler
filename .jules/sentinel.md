## 2026-04-24 - Fix HTML response leakage on AJAX nonce failure
**Vulnerability:** AJAX endpoints `ajax_reset_circuit_breaker` and `ajax_fetch_source_now` were calling `check_ajax_referer` without the `die` parameter set to `false`. This causes WordPress to fire a default `wp_die()` with an HTML response on failure, breaking the expected JSON response format for AJAX endpoints.
**Learning:** Always pass `false` as the third parameter to `check_ajax_referer` in endpoints that return JSON to prevent unexpected HTML error pages that could confuse clients or mask real issues.
**Prevention:** Ensure all JSON-returning AJAX endpoints handle nonce failures explicitly by returning a generic JSON error using `AIPS_Ajax_Response::error()`.

## 2026-04-24 - Fix SQL Injection and Information Leakage in History Endpoints
**Vulnerability:** Found a potential SQL injection by interpolating an array directly via implode in AIPS_History_Repository::delete_bulk, and found error leakage via wp_die in AJAX handlers in AIPS_History::ajax_export_history.
**Learning:** The ID array in delete_bulk was cast to absint, providing some defense, but direct interpolation violates the "Defense in Depth" policy. The wp_die function outputs raw HTML that breaks JSON API expectations and exposes internal implementation details.
**Prevention:** Always use wpdb->prepare with dynamic placeholders (%d, %s) for IN clauses, and always return standard generic JSON responses via AIPS_Ajax_Response::error in AJAX endpoints instead of wp_die.
