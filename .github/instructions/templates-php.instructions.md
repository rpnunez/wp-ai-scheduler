---
applyTo: "templates/**/*.php,ai-post-scheduler/templates/**/*.php"
---

Use this file for PHP template changes in `templates/**/*.php`.

- Treat templates as presentation-layer files.
- Keep business logic, data access, and heavy processing out of templates.
- Do not place SQL in templates; use repositories/services/controllers to prepare data before rendering.
- Preserve the existing admin page shell structure unless there is an explicit redesign request:
- `div.wrap.aips-wrap` -> `div.aips-page-container` -> `div.aips-page-header`/`div.aips-content-panel` sections.
- Reuse existing layout blocks such as `aips-page-header-top`, `aips-page-actions`, `aips-filter-bar`, `aips-panel-toolbar`, and `aips-panel-body`.
- Keep class naming consistent with the current design system: prefer `aips-*` classes for new UI hooks and visual components.
- Keep IDs and JS hooks consistent with existing conventions (`aips-*` IDs/classes and `data-*` attributes used by scripts).

- Follow the existing table patterns:
- Use `table.aips-table` (plus page-specific variants like `aips-history-table`, `aips-schedule-table`) for data grids.
- Keep semantic table markup (`thead`, `tbody`, optional `tfoot`) and existing column class patterns (`column-*`, `check-column`, `cell-primary`, `cell-meta`, `cell-actions`).
- Preserve row-level `data-*` attributes that JavaScript depends on.

- Follow the existing button/action patterns:
- Use the established button class system (`aips-btn`, size modifiers like `aips-btn-sm`, and variants like `aips-btn-primary`, `aips-btn-secondary`, `aips-btn-ghost`, `aips-btn-danger`).
- Keep icon usage consistent with Dashicons (`span.dashicons ...`).
- For icon-only controls, include accessible text via `screen-reader-text` or `aria-label`.

- Follow the existing modal structure for consistency:
- Modal wrapper: `.aips-modal` (typically hidden initially).
- Inner structure: `.aips-modal-content` with optional size modifiers (for example `.aips-modal-large`), then `.aips-modal-header`, `.aips-modal-body`, and optional `.aips-modal-footer`.
- Use `.aips-modal-close` for close controls and keep close button accessibility labels.
- Keep overlay and tab/content structures used by shared partials (for example `view-session-modal.php`, `ai-edit-modal.php`) intact.

- Follow the existing tab/layout conventions where tabs are used:
- Use `.aips-tab-link`/`.nav-tab` controls with matching `data-tab`/hash targets.
- Keep corresponding content containers and active-state classes aligned with existing JS behavior.

- Escape all output with context-appropriate WordPress helpers (`esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`).
- Sanitize request-derived values before use, and avoid trusting raw `$_GET`, `$_POST`, or `$_REQUEST`.
- Use WordPress internationalization functions for user-facing strings (`__()`, `_e()`, `esc_html__()`, etc.).
- Follow WordPress markup and accessibility expectations (labels, semantics, and ARIA where needed).
- Preserve existing CSS/JS hooks and DOM structure unless the change explicitly requires updates.
- Prefer existing utility/components classes over adding new one-off inline styles; if inline styles already exist in legacy markup, avoid expanding them unless required.
- For interactive/admin actions, use nonce-protected endpoints and capability checks in controllers.
- Prefer small, focused template changes and avoid broad UI refactors unless requested.
- When behavior changes, update related docs/tests and verify affected admin pages manually.
