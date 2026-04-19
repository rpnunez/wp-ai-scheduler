
## 2024-05-24 - Fix Unsafe Nonce Verification
**Vulnerability:** Found multiple instances where `check_ajax_referer` was called without passing `false` as the third parameter. This caused WordPress to trigger `wp_die` which can expose HTML in JSON contexts or lead to default behavior instead of graceful generic error handling.
**Learning:** By default, `check_ajax_referer` stops execution if a nonce is invalid. When used in AJAX endpoints expecting JSON, it causes an unexpected HTML response instead of a clean, generic JSON error.
**Prevention:** Always pass `false` as the third parameter to `check_ajax_referer` when writing AJAX endpoints to safely handle nonce failures by returning an explicit `AIPS_Ajax_Response::error()`.
