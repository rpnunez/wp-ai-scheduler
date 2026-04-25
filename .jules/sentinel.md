## 2024-05-24 - Fix SQL Injection and Information Leakage in History Endpoints
**Vulnerability:** Found a potential SQL injection by interpolating an array directly via implode in AIPS_History_Repository::delete_bulk, and found error leakage via wp_die in AJAX handlers in AIPS_History::ajax_export_history.
**Learning:** The ID array in delete_bulk was cast to absint, providing some defense, but direct interpolation violates the "Defense in Depth" policy. The wp_die function outputs raw HTML that breaks JSON API expectations and exposes internal implementation details.
**Prevention:** Always use wpdb->prepare with dynamic placeholders (%d, %s) for IN clauses, and always return standard generic JSON responses via AIPS_Ajax_Response::error in AJAX endpoints instead of wp_die.
