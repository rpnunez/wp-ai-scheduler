## 2024-05-24 - SSRF Prevention with `wp_safe_remote_get`
**Learning:** `wp_remote_get` allows requests to local/private IPs, making it a vector for SSRF attacks when handling user or AI-generated URLs.
**Action:** Always use `wp_safe_remote_get` for fetching external resources, which includes built-in DNS rebinding protection and private IP blocking.
