## 2024-05-23 - [SSRF Protection in Image Downloads]
**Vulnerability:** Usage of `wp_remote_get()` on AI-generated image URLs without validation.
**Learning:** Even "trusted" sources like an AI engine response should be treated as untrusted input when dealing with URLs, as prompt injection could theoretically cause the AI to return a malicious local URL (e.g., `http://localhost/metadata`).
**Prevention:** Always use `wp_safe_remote_get()` for HTTP requests to external or dynamic URLs. This function checks that the host is not a local IP/host.
