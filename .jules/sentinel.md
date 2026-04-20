## 2024-05-24 - Fix HTML response leakage on AJAX nonce failure
**Vulnerability:** AJAX endpoints `ajax_reset_circuit_breaker` and `ajax_fetch_source_now` were calling `check_ajax_referer` without the `die` parameter set to `false`. This causes WordPress to fire a default `wp_die()` with an HTML response on failure, breaking the expected JSON response format for AJAX endpoints.
**Learning:** Always pass `false` as the third parameter to `check_ajax_referer` in endpoints that return JSON to prevent unexpected HTML error pages that could confuse clients or mask real issues.
**Prevention:** Ensure all JSON-returning AJAX endpoints handle nonce failures explicitly by returning a generic JSON error using `AIPS_Ajax_Response::error()`.
