# Development Guidelines

This document contains project-specific coding and architectural guidelines for the AI Post Scheduler plugin. It is intended for both human developers and AI coding agents. Follow these guidelines in addition to the general conventions described in `AGENTS.md` and `.github/copilot-instructions.md`.

---

## JavaScript: Always Use the AIPS.Templates Engine for HTML

When building HTML strings in JavaScript files, always use the `AIPS.Templates` engine rather than manually concatenating strings.

- Use `AIPS.Templates.render(templateId, data)` for HTML that will be inserted into the DOM. All `{{token}}` replacements are HTML-escaped automatically, making the output safe for attribute and text contexts.
- Use `AIPS.Templates.renderRaw(templateId, data)` **only** when the replacement values are already-trusted HTML that must not be double-escaped.
- Define reusable HTML snippets in `<script type="text/html" id="tmpl-...">` blocks in the relevant admin template file.
- Do **not** build HTML through string concatenation or template literals in JS. This avoids XSS risks and keeps markup in one place.

Reference: `ai-post-scheduler/assets/js/templates.js`

---

## Database Schema Changes: Use AIPS_DB_Manager and Bump the Plugin Version

Whenever a database schema change is required (new table, new column, modified column, new index):

1. Add or update the `CREATE TABLE` statement in `AIPS_DB_Manager::get_schema()`.
2. Ensure `AIPS_DB_Manager::install_tables()` runs `dbDelta` on the updated schema (this is the existing flow; no extra wiring is needed).
3. **Increment the plugin version** in **both** of these places:
   - The `Version:` field in the plugin header comment in `ai-post-scheduler/ai-post-scheduler.php`.
   - The `AIPS_VERSION` PHP constant defined in the same file.
4. Create a corresponding repository class in `includes/` for any new table.
5. Add PHPUnit coverage for new repository methods and behavior.

Never apply schema changes through ad-hoc SQL outside of the `AIPS_DB_Manager` flow.

---

## Plugin Settings: Registration and Retrieval Pattern

Follow this three-step pattern whenever you add a new plugin setting:

### 1. Declare the default value in `AIPS_Config::get_default_options()`

Add a `key => default_value` entry to the array returned by `AIPS_Config::get_default_options()`. The key is the option name; the value is the canonical default.

```php
'my_new_setting' => 'default_value',
```

### 2. Register the setting in `AIPS_Settings::register_settings()`

Register the setting with WordPress and read its default from `AIPS_Config::get_default_options()` so the two definitions stay in sync:

```php
$defaults = AIPS_Config::get_default_options();

register_setting( 'aips_settings_group', 'my_new_setting', array(
    'default' => $defaults['my_new_setting'],
) );
```

### 3. Read the setting value through `AIPS_Config::get_instance()->get_option()`

Always retrieve setting values via:

```php
$value = AIPS_Config::get_instance()->get_option( 'my_new_setting' );
```

This ensures the default defined in `get_default_options()` is used as the fallback when the option has not yet been saved to the database, keeping behaviour consistent across fresh installs and upgrades.

---

## SQL: Never Write Inline SQL Outside of Repository Classes

All SQL and database query composition must live in **Repository** classes (files named `class-aips-*-repository.php` in `includes/`).

- Do **not** write raw SQL (`$wpdb->query(...)`, `$wpdb->get_results(...)`, `$wpdb->prepare(...)`, etc.) inside Controllers, Services, Scheduler classes, cron callbacks, or templates.
- When a Controller, Service, or Scheduler needs to persist or query data, it must call a method on the appropriate Repository class.
- If no suitable Repository method exists yet, add one to the correct repository rather than adding inline SQL to non-repository code.

This rule enforces a clean separation of concerns and makes the data-access layer easy to test, audit, and refactor.

Reference: `ai-post-scheduler/includes/class-aips-template-repository.php` (example repository)
