
## 2024-04-21 - Standardize AJAX Error Responses
**Vulnerability:** Raw `wp_die()` used in `ajax_export_history` instead of standard JSON responses via `AIPS_Ajax_Response`, leaking internal HTML.
**Learning:** Inconsistent usage of error handlers in new endpoints can lead to improper JSON formatting and potentially expose raw HTML to clients expecting JSON.
**Prevention:** Always use `AIPS_Ajax_Response::permission_denied()` and `AIPS_Ajax_Response::error()` inside AJAX handlers for consistency and secure generic responses.
