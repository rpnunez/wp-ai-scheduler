
## 2026-04-21 - Standardize AJAX Error Responses
**Vulnerability:** Raw `wp_die()` used in `ajax_export_history` instead of standard JSON responses via `AIPS_Ajax_Response`, leaking internal HTML.
**Learning:** Inconsistent usage of error handlers in new endpoints can lead to improper JSON formatting and potentially expose raw HTML to clients expecting JSON.
**Prevention:** Always use `AIPS_Ajax_Response::permission_denied()` and `AIPS_Ajax_Response::error()` inside AJAX handlers for consistency and secure generic responses.

## 2026-05-24 - Fix SQL Injection and Information Leakage in History Endpoints
**Vulnerability:** Found a potential SQL injection by interpolating an array directly via implode in AIPS_History_Repository::delete_bulk, and found error leakage via wp_die in AJAX handlers in AIPS_History::ajax_export_history.
**Learning:** The ID array in delete_bulk was cast to absint, providing some defense, but direct interpolation violates the "Defense in Depth" policy. The wp_die function outputs raw HTML that breaks JSON API expectations and exposes internal implementation details.
**Prevention:** Always use wpdb->prepare with dynamic placeholders (%d, %s) for IN clauses, and always return standard generic JSON responses via AIPS_Ajax_Response::error in AJAX endpoints instead of wp_die.
