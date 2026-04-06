# Development Guidelines

Project-specific rules for the AI Post Scheduler plugin. Follow these in addition to `AGENTS.md` and `.github/copilot-instructions.md`.

---

## JS: Use AIPS.Templates for HTML

Never build HTML via string concatenation in JS. Always use:
- `AIPS.Templates.render(id, data)` — auto-escapes all `{{token}}` values (safe for text and attributes).
- `AIPS.Templates.renderRaw(id, data)` — no escaping; only for already-trusted HTML.

Define markup in `<script type="text/html" id="tmpl-...">` blocks in the relevant admin template.

---

## DB Schema Changes: AIPS_DB_Manager + Version Bump

For any schema change (new table, column, or index):
1. Update `AIPS_DB_Manager::get_schema()`.
2. `install_tables()` + `dbDelta` handle the rest — no extra wiring needed.
3. Bump **both** the `Version:` plugin header and `AIPS_VERSION` constant in `ai-post-scheduler.php`.
4. Create a repository class in `includes/` for any new table.

---

## Plugin Settings: Three-Step Pattern

1. **Default** — add `'key' => default_value` to `AIPS_Config::get_default_options()`.
2. **Register** — call `register_setting()` in `AIPS_Settings::register_settings()`, reading the default from `AIPS_Config::get_default_options()`.
3. **Read** — always use `AIPS_Config::get_instance()->get_option('key')` so the declared default is the fallback.

---

## SQL: Repository Classes Only

All `$wpdb` queries belong in `class-aips-*-repository.php` files. Never write inline SQL in Controllers, Services, Schedulers, or templates. If a needed query method doesn't exist, add it to the appropriate repository.
