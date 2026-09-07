---
name: l10n-reviewer
description: Reviews translation readiness, text domain usage, string escaping, and localization data stores in wp-ai-scheduler. Use whenever user-facing admin UI strings, PHP i18n functions, JS L10n payloads, or language stores are updated.
tools: [read]
---

> **Canonical reference:** Read [`AGENTS.md`](../../AGENTS.md) first. This file adds localization-specific review criteria only.

## Review Checklist

For every localization or user-facing string change, verify the following before approving.

### 1. Text domain consistency
- All gettext function calls (`__`, `_e`, `esc_html__`, `esc_html_e`, `esc_attr__`, `esc_attr_e`, `_n`, `_x`) specify the `'ai-post-scheduler'` text domain.
- No hardcoded English strings in templates or JS files intended for translation.

### 2. Escaping gettext outputs
- Translatable strings in HTML output are properly escaped using `esc_html__`, `esc_html_e`, `esc_attr__`, or `esc_attr_e`.
- Complex translatable strings containing dynamic HTML placeholders use `wp_kses()` or `sprintf()` with appropriate escaping.

### 3. Localization data store usage
- Shared translation dictionaries and dynamic language context live in `AIPS_Language_Store` and `AIPS_Admin_L10n`.
- JS translations are passed via localized script objects (`wp_localize_script`) under `AIPS_L10n` namespaces.

### 4. Template presentation hygiene
- Admin views under `templates/admin/` contain presentation markup and localized strings only—no SQL or direct `$wpdb` queries.
- Variable interpolations in templates follow security escaping standards.

### 5. Tests
- Verify localized options and data store outputs pass unit tests without PHP warnings or missing domain errors.

## Key files to read
- `ai-post-scheduler/includes/class-aips-language-store.php`
- `ai-post-scheduler/includes/class-aips-admin-l10n.php`
- `ai-post-scheduler/templates/admin/`
