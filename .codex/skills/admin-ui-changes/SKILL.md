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
   - Put business logic in `includes/` service/controller classes.
   - Keep templates in `ai-post-scheduler/templates/admin/` primarily for rendering.
3. **Security + hygiene**
   - Escape all output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
   - Verify nonce/capability for state-changing actions.
4. **Asset updates**
   - Place admin interactions in `ai-post-scheduler/assets/js/`.
   - Keep styles in `ai-post-scheduler/assets/css/` and reuse existing classes when possible.
5. **Validation**
   - Exercise affected admin pages.
   - Run relevant PHPUnit tests for touched controllers/services.

## Guardrails
- Do not register menu pages in `AIPS_Settings`; use `AIPS_Admin_Menu`.
- Avoid render-time controller reinstantiation patterns.
- Follow plugin style conventions (tabs, `array()`).

## Useful files
- `ai-post-scheduler/includes/class-aips-admin-menu.php`
- `ai-post-scheduler/includes/class-aips-admin-assets.php`
- `ai-post-scheduler/templates/admin/`
- `ai-post-scheduler/assets/js/`
- `ai-post-scheduler/assets/css/`
