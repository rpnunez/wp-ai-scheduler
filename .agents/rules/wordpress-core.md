# WordPress rules (all PHP, JS, templates)

Applies to any change in `ai-post-scheduler/`. Repo-wide context is in `AGENTS.md`; this file only adds WordPress hygiene specific to this plugin.

## Targets
- PHP 8.2+, WordPress 5.8+ (`ai-post-scheduler/ai-post-scheduler.php` header, `composer.json`).
- Text domain: `ai-post-scheduler`. Wrap every user-facing string in `__()`, `esc_html__()`, etc. with that domain.
- Never modify WordPress core; extend through actions and filters. Plugin hooks are documented in `docs/HOOKS.md`.
- No Composer/npm build step for assets. Do not add one for a small script change.

## Security
- Escape on output (`esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`); sanitize on input (`sanitize_text_field()`, `sanitize_key()`, `absint()`, ...).
- Every state-changing request needs a nonce check and `current_user_can()` (admin actions use `manage_options`).
- Every SQL statement with variable input goes through `$wpdb->prepare()`, and only inside repositories.
- Validate uploads by MIME type and use `wp_handle_upload()` / `media_handle_upload()`.

## Assets
- Enqueue scripts and styles through `AIPS_Admin_Assets` (`includes/class-aips-admin-assets.php`), scoped to the screens that need them.
- Pass PHP data to JS with `wp_localize_script()` (existing `aips*L10n` / `aipsAjax` objects). No raw `<script>` / `<style>` blocks in new PHP, except the `<script type="text/html" id="tmpl-...">` markup templates used by `AIPS.Templates`.

## Performance
- Do no heavy work on `init`; use the request-context boot in `AI_Post_Scheduler::init()`.
- Cache expensive queries (transients/object cache) and plan invalidation. Page unbounded lists.

## Settings API
- New options follow the three-step pattern in `AGENTS.md` / `docs/DEVELOPMENT_GUIDELINES.md` (default in `AIPS_Config`, `register_setting()` with a `sanitize_callback`, read via `AIPS_Config`).

## Tests and docs
- Add or update PHPUnit tests in `ai-post-scheduler/tests/` when behavior changes. Run them as described in `TESTING.md`.
- No `phpcs.xml` or ESLint config exists in this repo; follow the style of the surrounding file rather than inventing a linter setup.
- Update `ai-post-scheduler/CHANGELOG.md` and the docs named in `AGENTS.md` when behavior or public hooks change.
