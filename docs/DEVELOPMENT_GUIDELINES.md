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

---

## Container Constructor Policy

Do not directly instantiate cross-cutting core services in `ai-post-scheduler/includes/`:
- `AIPS_Logger`
- `AIPS_History_Service`
- `AIPS_AI_Service`
- `AIPS_Resilience_Service`

Resolve them from `AIPS_Container` instead, using constructor injection first and container fallback second.

Preferred constructor pattern:

```php
public function __construct(
	?AIPS_Logger_Interface $logger = null,
	?AIPS_History_Service_Interface $history_service = null
) {
	$container = AIPS_Container::get_instance();
	$this->logger = $logger ?: $container->makeIfExists(AIPS_Logger_Interface::class, AIPS_Logger::class);
	$this->history_service = $history_service ?: $container->makeIfExists(AIPS_History_Service_Interface::class, AIPS_History_Service::class);
}
```

Migration guidance:
1. Keep constructor parameters optional for backward compatibility.
2. Prefer interface aliases as the primary container key.
3. Use `makeIfExists()` with an explicit fallback concrete class.
4. If a temporary exception is unavoidable, add the file to `ai-post-scheduler/config/container-instantiation-whitelist.txt` with a rationale and create a follow-up cleanup task.

Enforcement:
- Script: `ai-post-scheduler/tools/check-container-instantiation-policy.php`
- Composer command: `composer lint:container-policy` (run from `ai-post-scheduler/`)
- Architectural test: `tests/Test_Container_Instantiation_Policy.php`

---

## JS Feedback: Use AIPS.Utilities.showToast, Never alert()

Never call the native `alert()` function. Always use:
- `AIPS.Utilities.showToast(message, type, opts)` — `type` is `'success'`, `'error'`, `'warning'`, or `'info'`.
- Plain-text messages are auto-escaped. Pass `opts.isHtml = true` only for pre-trusted HTML.
- Set `opts.duration = 0` to suppress auto-dismiss.

The shorthand `AIPS.showToast(message, type, opts)` delegates to the same method.

---

## JS Confirmation: Use AIPS.Utilities.confirm, Never confirm()

Never call the native `confirm()` function. Always use:
- `AIPS.Utilities.confirm(message, heading, buttons)` — renders a styled, accessible modal dialog.
- `buttons` is an array of `{ label, className, action }` objects. Omit `action` for a close-only button.
- The modal closes on the action callback, Escape key, or backdrop click.

---

## JS DOM Refresh: Never Use location.reload()

Never call `location.reload()` after an AJAX action. Instead:
- Re-fetch the updated data via a follow-up AJAX call and re-render the affected UI region using `AIPS.Templates.render()` / `renderRaw()`.
- Only replace or patch the specific DOM nodes that changed; leave the rest of the page untouched.

---

## Admin UI Design System

For all admin interface work, use `ai-post-scheduler/docs/Design_Guidelines.md` as the single source of truth for tokens, shared component classes, approved usage, and migration patterns.
