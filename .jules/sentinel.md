## 2024-05-23 - [SSRF Protection in Image Downloads]
**Vulnerability:** Usage of `wp_remote_get()` on AI-generated image URLs without validation.
**Learning:** Even "trusted" sources like an AI engine response should be treated as untrusted input when dealing with URLs, as prompt injection could theoretically cause the AI to return a malicious local URL (e.g., `http://localhost/metadata`).
**Prevention:** Always use `wp_safe_remote_get()` for HTTP requests to external or dynamic URLs. This function checks that the host is not a local IP/host.

## 2024-05-23 - [Output Escaping on Generated Links]
**Vulnerability:** Unescaped usage of `get_permalink()` and `get_edit_post_link()` in HTML attributes.
**Learning:** While these functions typically return valid URLs, relying on their internal filtering is insufficient defense-in-depth. A compromised database or a malicious filter could inject javascript: URIs.
**Prevention:** Always wrap URL outputs in `esc_url()`, even when they come from core WordPress functions.

## 2024-05-24 - [Reflected XSS via API Response]
**Vulnerability:** Unescaped concatenation of AI service response into a JSON success message which was rendered via `.html()` in JavaScript.
**Learning:** AI text generation outputs must be treated as untrusted user input. If a model is manipulated or hallucinating, it can return malicious HTML/JS.
**Prevention:** Always escape text content from external services using `esc_html()` before sending it to the client, especially if the client renders it as HTML.

## 2024-05-24 - [Log Injection via Unsanitized Input]
**Vulnerability:** The `AIPS_Logger::log` method directly wrote the `$message` parameter to the log file without sanitization.
**Learning:** If a log message contains newline characters (e.g., from user input or external API errors), an attacker can inject fake log entries, confusing administrators or corrupting log analysis tools.
**Prevention:** Always sanitize log messages by replacing newline characters (`\r`, `\n`) with spaces or a placeholder to ensure each log event occupies exactly one line.
