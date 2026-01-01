## 2024-05-23 - [SSRF Protection in Image Downloads]
**Vulnerability:** Usage of `wp_remote_get()` on AI-generated image URLs without validation.
**Learning:** Even "trusted" sources like an AI engine response should be treated as untrusted input when dealing with URLs, as prompt injection could theoretically cause the AI to return a malicious local URL (e.g., `http://localhost/metadata`).
**Prevention:** Always use `wp_safe_remote_get()` for HTTP requests to external or dynamic URLs. This function checks that the host is not a local IP/host.

## 2024-05-24 - [Strict MIME Type Validation for Images]
**Vulnerability:** The check `strpos($content_type, 'image/') !== 0` is too broad and allows dangerous types like `image/svg+xml` which can contain XSS payloads. Additionally, hardcoding `.jpg` extension for all downloads is incorrect.
**Learning:** Checking for a prefix is insufficient for MIME type validation when some subtypes within that main type are dangerous. SVGs are technically images but behave like documents/scripts in browsers.
**Prevention:** Use a strict allowlist (whitelist) of safe MIME types (e.g., `image/jpeg`, `image/png`, `image/gif`, `image/webp`) and derive the file extension from the verified MIME type.
