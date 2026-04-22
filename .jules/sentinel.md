## 2024-05-24 - Fix raw wp_die() in JSON API endpoints
**Vulnerability:** Found `check_ajax_referer` calls without the `false` third argument, which causes WordPress to use `wp_die()` with raw HTML on failure, breaking JSON API responses and potentially leaking internal stack traces or confusing the client.
**Learning:** `check_ajax_referer` defaults to a hard die. In JSON-based AJAX endpoints, this results in improper error handling.
**Prevention:** Always pass `false` as the third parameter to `check_ajax_referer` in AJAX controllers and explicitly handle the failure by returning `AIPS_Ajax_Response::error()`.
