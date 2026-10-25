# Template rules: `ai-post-scheduler/templates/**`

Read `wordpress-core.md` too. The design system (tokens, shared classes) is `ai-post-scheduler/docs/Design_Guidelines.md`; tokens are in `assets/css/design-tokens.css`.

## Layout
- Templates are presentation only: no SQL, no business logic. Controllers/services prepare the data.
- Directories: `templates/admin/` (pages), `templates/admin/partials/` (shared page building blocks such as `admin-page-header`, `admin-card`, `admin-hub-shell`, `admin-rail`, `admin-status-badge`, `admin-empty-state`), `templates/partials/` (shared modals and history rows, see its `README.md`), `templates/email/`.
- Page shell: `div.wrap.aips-wrap` > `div.aips-page-container` > `div.aips-page-header` / `div.aips-content-panel`. Reuse `aips-page-header-top`, `aips-page-actions`, `aips-filter-bar`, `aips-panel-toolbar`, `aips-panel-body`.
- Prefer existing partials and utility classes over new markup. Do not add inline styles; new UI classes and IDs use the `aips-` prefix.

## Components
- Tables: `table.aips-table` (+ page variants), semantic `thead`/`tbody`, existing `column-*` / `cell-*` classes; keep row `data-*` attributes JS relies on.
- Buttons: `aips-btn` + `aips-btn-sm` and `-primary` / `-secondary` / `-ghost` / `-danger`. Icons are Dashicons. Icon-only controls need `screen-reader-text` or `aria-label`.
- Modals: `.aips-modal` > `.aips-modal-content` (+ `.aips-modal-large`) > `.aips-modal-header` / `.aips-modal-body` / optional `.aips-modal-footer`; `.aips-modal-close` with an accessible label.
- Tabs: `.aips-tab-link` / `.nav-tab` with matching `data-tab` / hash targets.
- JS markup templates: `<script type="text/html" id="tmpl-...">` blocks consumed by `AIPS.Templates` (the one allowed inline script). No other inline `<script>` or `<style>`; a few legacy templates still have them, so do not add more.

## Output safety and i18n
- Escape all output (`esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`); never read raw `$_GET`/`$_POST`/`$_REQUEST` without sanitizing.
- All visible strings use `__()` / `esc_html__()` with text domain `ai-post-scheduler`.
- Labels, semantics and ARIA per WordPress admin accessibility expectations.
- Interactive actions rely on nonce + capability checks in the controller.

## Verify
- `l10n-reviewer` for strings; check the affected admin page manually. Update `Design_Guidelines.md` if you add a shared component.
