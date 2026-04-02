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

## 2025-05-25 - [Output Escaping on Generated Links in Settings]
**Vulnerability:** Unescaped usage of `get_permalink()`, `get_edit_post_link()`, and `get_the_post_thumbnail_url()` within JSON responses generated by `ajax_get_activity()` and `ajax_get_activity_detail()` in `ai-post-scheduler/includes/class-aips-settings.php`.
**Learning:** Returning unescaped URLs within JSON payloads, even if they originate from core WordPress functions, can be a potential risk if they contain malicious characters or javascript: URIs that an unsuspecting frontend might evaluate.
**Prevention:** Use `esc_url_raw()` to escape URLs being returned as data within API or AJAX responses. Use `esc_url()` for direct HTML output.

## 2025-10-28 - [HIGH] Fix XSS Vulnerability in Author Topics Controller
**Vulnerability:** Missing URL escaping for `get_permalink()` and `get_edit_post_link()` in AJAX JSON responses within `AIPS_Authors_Controller`.
**Learning:** URLs generated by WordPress functions like `get_permalink()` should be escaped with `esc_url_raw()` when returned in JSON APIs to prevent JavaScript injection.
**Prevention:** Always wrap URL outputs intended for API or JSON/AJAX responses with `esc_url_raw()` and direct HTML outputs with `esc_url()`.

## 2025-10-27 - [Output Escaping on Generated Links]
**Vulnerability:** Unescaped usage of `get_permalink()`, `get_edit_post_link()`, and `get_the_post_thumbnail_url()` in various backend classes and API responses.
**Learning:** While WordPress core functions generally return safe URLs, generating URLs dynamically for JSON/API responses or within variables that will later be rendered without escaping violates Defense in Depth. Unescaped outputs could be an attack vector if internal filters are compromised or data states are poisoned (e.g., via malicious inputs impacting `post_link` filters).
**Prevention:** Always wrap dynamically generated URLs with `esc_url_raw()` (for data structures/APIs) or `esc_url()` (for direct HTML output) to ensure they are properly sanitized immediately at the point of generation.

## 2026-03-21 - [SQL Injection Prevention in AIPS_DB_Manager]
**Vulnerability:** Potential SQL Injection in `AIPS_DB_Manager::drop_tables()`, `truncate_tables()`, and `backup_data()` due to unescaped string interpolation of table names.
**Learning:** While the table names were retrieved internally from a static array, directly interpolating them into SQL queries (e.g. `$wpdb->query("DROP TABLE IF EXISTS $table")`) bypasses WordPress's recommended database preparation patterns and creates a latent risk if the table source ever becomes dynamic or user-influenced.
**Prevention:** Use `$wpdb->prepare()` for parameters and use `esc_sql()` wrapped in backticks for table names/identifiers when constructing dynamic queries.

## 2026-03-23 - [Fix error message leakage in export data]
**Vulnerability:** Raw `Exception` messages were being returned directly to the client via `wp_send_json_error()` in `class-aips-data-management.php` during export operations. This could potentially expose sensitive internal paths, logic, or stack traces.
**Learning:** Developers sometimes pass raw exception objects or their messages directly to API error responses for easier debugging, but this violates secure error handling principles by leaking internal details.
**Prevention:** Always log detailed error messages internally (e.g., using `error_log()`) and return generic error messages to the client indicating that an error occurred.

## 2026-03-23 - [Unsanitized POST array in AI Edit Controller Hook]
**Vulnerability:** The `$components` array from `$_POST['components']` was passed directly to the `aips_post_components_updated` action hook without being sanitized first, exposing any listeners to potentially malicious unsanitized POST data (XSS, Injection).
**Learning:** `$_POST` arrays should be recursively sanitized or validated field-by-field before passing them to do_action.
**Prevention:** Always construct a new array with properly sanitized fields using functions like `sanitize_text_field` and `wp_kses_post` before exposing user input through action hooks.

## 2026-03-24 - [Missing Input Unslashing before Sanitization]
**Vulnerability:** Superglobals like `$_POST`, `$_GET`, and `$_REQUEST` were passed directly to sanitization functions (e.g., `sanitize_text_field`, `wp_kses_post`) without first being unslashed.
**Learning:** WordPress automatically adds slashes to `$_POST`, `$_GET`, and `$_REQUEST` arrays. If these slashes are not removed using `wp_unslash()` before sanitization, it can lead to data corruption (e.g., literal backslashes being saved to the database) or potentially bypass certain sanitization filters, leading to XSS vulnerabilities.
**Prevention:** Always apply `wp_unslash()` to values retrieved from `$_POST`, `$_GET`, or `$_REQUEST` immediately before passing them to any sanitization function.

## 2026-03-24 - [Output Escaping on Generated Links in Onboarding and Notifications]
**Vulnerability:** Unescaped usage of `get_permalink()` and `get_edit_post_link()` in `ai-post-scheduler/includes/class-aips-onboarding-wizard.php` (JSON API response) and `ai-post-scheduler/includes/class-aips-notifications.php`.
**Learning:** Returning unescaped URLs within JSON payloads or notification data structures, even if they originate from core WordPress functions, can be a potential risk if they contain malicious characters or javascript: URIs that an unsuspecting frontend might evaluate. Also, using the 'display' context (default) for `get_edit_post_link` can cause double-encoded ampersands.
**Prevention:** Use `esc_url_raw()` to escape URLs being returned as data within API, AJAX responses, or internal data structures. Use the 'raw' context for `get_edit_post_link()` when the output is not immediately rendered as HTML.
