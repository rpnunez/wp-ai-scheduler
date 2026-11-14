# Admin UI Changes Skill

Use this skill when changing WordPress admin pages, menus, templates, and admin JS/CSS.

## Scope
- Admin menus/routes and admin-facing rendering.
- `ai-post-scheduler/templates/admin/`, `ai-post-scheduler/assets/js/`, `ai-post-scheduler/assets/css/`, and admin controllers.

## Required workflow
1. **Map page ownership**
   - Verify route/menu source in `AIPS_Admin_Menu::add_menu_pages()`.
   - Identify the controller that provides data to the template.
2. **Separate concerns**
   - Put business logic in `ai-post-scheduler/includes/` service/controller classes.
   - Keep templates in `ai-post-scheduler/templates/admin/` primarily for rendering.
3. **Security + hygiene**
   - Escape all output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
   - Verify nonce/capability for state-changing actions.
4. **Asset updates & JS Module Pattern**
   - Follow the standard JS module IIFE pattern: `window.AIPS.ModuleName = { init(), bindEvents(), ... }`.
   - Dynamic HTML generation MUST use `AIPS.Templates.render(id, data)` (auto-escaped) or `AIPS.Templates.renderRaw(id, data)` for trusted HTML. Never string concatenation.
   - Use `AIPS.Utilities.showToast(message, type)` instead of `alert()`, and `AIPS.Utilities.confirm(message, heading, buttons)` instead of `confirm()`.
   - Refresh DOM via AJAX and re-render with `AIPS.Templates`; never call `location.reload()`.
5. **CSS Layout Structure**
   - Wrap pages in `div.wrap.aips-wrap` → `div.aips-page-container` → `div.aips-page-header` / `div.aips-content-panel`.
   - Re-use standard button classes (`aips-btn`, `aips-btn-primary`, `aips-btn-danger`) and table classes (`table.aips-table`).
6. **Responsive design**
   - Never write a new page-specific `@media` block for a dense table, modal, or tab strip. Reuse the opt-in classes in `assets/css/admin-responsive.css` (loaded globally on every admin page):
     - `.aips-table-responsive` on `table.aips-table` — reflows to stacked cards below 600px. Requires `data-label="..."` on every `<td>`, matching the `<th>` text, so no column is ever hidden on mobile.
     - `.aips-modal-fullscreen-mobile` on `.aips-modal-content` — goes edge-to-edge below 600px instead of a centered/drawer panel.
     - `.aips-tabs-scrollable` on a tab/pill nav — scrolls horizontally below 600px instead of wrapping.
   - Breakpoints are documented as `--aips-bp-tablet` (782px) and `--aips-bp-mobile` (600px) in `design-tokens.css`; copy those literal px values into new `@media` queries (custom properties don't work inside `@media` feature queries).
   - Never lay out a component with inline `style="display:grid;..."` etc. in a template — it silences any later `@media` override. Put layout in a class in `admin.css` (or the component's own CSS file) instead.
   - Before touching a table, filter bar, modal, or pagination, check `docs/responsive-design-system-migration.md` — it tracks which shared system each page is on, which pages use `AIPS_List_Table` (don't apply `.aips-table-responsive` there — core WP_List_Table already handles their mobile stacking), and the migration checklist per system.
7. **Validation**
   - Exercise affected admin pages.
   - Run relevant PHPUnit tests for touched controllers/services.

## Guardrails
- Do not register menu pages in `AIPS_Settings`; use `AIPS_Admin_Menu`.
- Avoid render-time controller reinstantiation patterns.
- Never use direct string concatenation for HTML generation in JS; use `AIPS.Templates`.
- Follow plugin style conventions (tabs, `array()`).

## Useful files
- `ai-post-scheduler/includes/class-aips-admin-menu.php`
- `ai-post-scheduler/includes/class-aips-admin-assets.php`
- `ai-post-scheduler/templates/admin/`
- `ai-post-scheduler/assets/js/`
- `ai-post-scheduler/assets/css/`

## Useful references
- `.github/instructions/templates-php.instructions.md`
- `.github/instructions/assets-js.instructions.md`
