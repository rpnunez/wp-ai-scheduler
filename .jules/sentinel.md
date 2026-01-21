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

## 2024-05-24 - [Directory Listing Prevention]
**Vulnerability:** Missing `index.php` or `index.html` files in plugin subdirectories (`includes`, `assets`, etc.).
**Learning:** Without these silent index files, misconfigured web servers may allow users to browse the plugin's file structure (Directory Listing), potentially revealing sensitive information, backups, or the internal architecture.
**Prevention:** Always include an empty `index.php` with `<?php // Silence is golden.` in every directory of the plugin.

## 2025-05-01 - [Stored XSS via AI/Database Reflection]
**Vulnerability:** Unescaped insertion of database content (`generated_title`, `error_message`, `template.name`) into the DOM via string concatenation in `admin.js`.
**Learning:** Admin interfaces are often treated as "trusted zones," but data originating from complex flows (like AI generation or indirect inputs) can be compromised (e.g., via Prompt Injection or Stored XSS). Concatenating HTML strings in JS without explicit escaping is a persistent vulnerability pattern.
**Prevention:** Use a dedicated escaping utility (like `AIPS.escapeHtml()`) for ALL dynamic data inserted into the DOM, regardless of its source (database, API, or user input).
