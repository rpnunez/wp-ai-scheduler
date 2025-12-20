## 2024-05-23 - [SSRF Protection]
**Vulnerability:** `AIPS_Generator` used `wp_remote_get()` to fetch images from URLs provided by the AI engine. While the source is semi-trusted (the AI), it's best practice to treat all external URLs as untrusted to prevent Server-Side Request Forgery (SSRF).
**Learning:** Even "trusted" external sources can be manipulated or return unexpected values. `wp_safe_remote_get()` is a simple drop-in replacement that adds significant protection against internal network scanning.
**Prevention:** Always use `wp_safe_remote_get()` for HTTP requests to user-supplied or external URLs.
