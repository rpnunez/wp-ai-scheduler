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

## 2025-10-26 - [Arbitrary SQL Execution in DB Import]
**Vulnerability:** Weak validation in `AIPS_Data_Management_Import_MySQL` relied on `stripos` to check for table names, allowing arbitrary SQL (like `DELETE FROM wp_users`) if it merely *contained* a valid table name string or if the command didn't match `TABLE`/`INSERT` keywords.
**Learning:** Checking for the presence of a safe string is NOT enough to validate a complex command like SQL. Attackers can embed safe strings in comments or irrelevant clauses to bypass such checks.
**Prevention:** Use a strict allowlist of command types (e.g., only `INSERT`, `CREATE`, `DROP`) and ensure that the command *structure* targets the allowed resources, rather than just grepping for keywords.

## 2025-10-27 - [Exposed Sensitive Data in Logs]
**Vulnerability:** In `ai-post-scheduler/includes/class-aips-session-to-json.php`, the `create_htaccess_protection` method generated a `.htaccess` file with `deny from all` to protect exported JSON session files, but lacked an `index.php` fallback.
**Learning:** For web servers that do not process or respect `.htaccess` files (such as NGINX or misconfigured Apache servers), relying solely on `.htaccess` leaves directories vulnerable to directory listing, exposing sensitive logs or exported data.
**Prevention:** Always pair `.htaccess` protection with an `index.php` file containing `<?php // Silence is golden` to provide defense-in-depth against directory listing across all web server environments.

## 2025-05-18 - [Fix TRUNCATE TABLE Vulnerability]
**Finding**: `AIPS_History_Repository::delete_by_status()` used a `TRUNCATE TABLE` query to clear out the entire table when the status filter is empty.
**Risk**: `TRUNCATE TABLE` bypasses normal table deletion constraints (such as `ON DELETE` triggers) and always reports returning a boolean rather than the count of rows removed. If this method is called inadvertently with an empty string due to an invalid request or logical bug upstream, the entire history table would be completely cleared without safety mechanisms.
**Resolution**: Changed the query to `DELETE FROM {$this->table_name}`, which behaves predictably within MySQL's transactional bounds, safely processes trigger conditions, and returns the expected integer count of deleted rows.

## 2026-03-20 - Fix missing URL escaping in JSON response
**Vulnerability:** Missing URL escaping in `AIPS_Settings::ajax_get_activity_detail()` and `AIPS_Settings::ajax_get_activity()`. WordPress-generated URLs (such as `get_permalink()`, `get_edit_post_link()`, and `get_the_post_thumbnail_url()`) were being passed directly into the JSON response without proper sanitization.
**Learning:** Returning unescaped dynamic URLs, even if they're generated internally, can present XSS and injection vulnerabilities if they reflect user-controllable input (such as post titles within permalinks in certain setups or manipulated IDs).
**Prevention:** Ensure that URLs are properly escaped with `esc_url_raw()` when returning them as data within API or JSON/AJAX responses to enforce secure output formatting.
