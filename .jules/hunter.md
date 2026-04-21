
## 2026-04-19 - JSON Endpoint Nonce Validation
**Learning:** When using `check_ajax_referer` in JSON API endpoints (e.g. AJAX controllers), failing to pass `false` as the third parameter causes WordPress to trigger a default `wp_die()` with an HTML response. This breaks JSON parsing on the client.
**Action:** Always wrap `check_ajax_referer` in an `if` statement passing `false` as the third parameter, and return an explicit generic JSON error using the class's `AIPS_Ajax_Response::error` wrapper.
