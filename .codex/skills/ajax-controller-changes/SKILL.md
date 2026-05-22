# AJAX Controller Changes Skill

Use this skill when adding or modifying plugin AJAX endpoints and handlers.

## Scope
- `wp_ajax_*` handlers, controller constructors, and action routing.
- Registry mapping in `AIPS_Ajax_Registry`.

## Required workflow
1. **Route first**
   - Add/confirm the action in `AIPS_Ajax_Registry::$map`.
   - Ensure the action resolves to exactly one responsible controller class.
2. **Controller responsibilities**
   - Register hooks in the controller constructor.
   - Handle nonce checks, capability checks, sanitization, and response formatting.
3. **Repository boundary**
   - Keep SQL/persistence in repository classes.
   - Call repositories/services from controller methods.
4. **Response consistency**
   - Use `AIPS_Ajax_Response` for JSON replies.
   - Return predictable error payloads for validation/capability failures.
5. **Validation**
   - Add/extend PHPUnit tests for both success and failure paths.

## Guardrails
- Never bypass `current_user_can('manage_options')` for admin-only actions.
- Do not introduce anonymous ad hoc AJAX actions outside registry routing.

## Useful files
- `ai-post-scheduler/includes/class-aips-ajax-registry.php`
- `ai-post-scheduler/includes/class-aips-ajax-response.php`
- `ai-post-scheduler/includes/class-aips-settings-ajax.php`
- `ai-post-scheduler/tests/Test_AIPS_Ajax_Registry_Response.php`
