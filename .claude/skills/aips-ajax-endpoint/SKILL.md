---
name: aips-ajax-endpoint
description: Use when adding, changing, or reviewing an AJAX action in the AI Post Scheduler plugin (ai-post-scheduler/) — anything involving wp_ajax_* handlers, AIPS_Ajax_Registry, or a *-controller.php file.
---

# AJAX endpoint workflow

This plugin routes every AJAX action through a single registry rather than ad hoc
`add_action('wp_ajax_...')` calls scattered across the codebase.

## Required workflow

1. **Register the action.** Add `'aips_action_name' => 'AIPS_ClassName'` to
   `AIPS_Ajax_Registry::$map` in `includes/class-aips-ajax-registry.php`. `boot_ajax()`
   reads `$_REQUEST['action']`, resolves the controller from this map, and constructs
   it — the constructor registers the real WordPress hook. Never register a
   `wp_ajax_*` action outside this map.
2. **Controller owns the request boundary.** The controller class
   (`class-aips-*-controller.php`) is responsible for nonce verification, a
   `current_user_can()` capability check, input sanitization, and returning JSON via
   `AIPS_Ajax_Response`. See `includes/class-aips-schedule-controller.php` for the
   reference shape (constructor takes optional service/repository-interface
   dependencies with nullable defaults; methods like `ajax_save_schedule()`,
   `ajax_delete_schedule()` do the request-boundary work only).
3. **Business logic goes in a service, not the controller.** Orchestration belongs in
   a `class-aips-*-service.php` (e.g. `AIPS_Unified_Schedule_Service`). Controllers
   call the service; they don't contain multi-step logic themselves.
4. **SQL goes in a repository, not the controller or service.** All `$wpdb` access
   lives in a `class-aips-*-repository.php` implementing an `*_Repository_Interface`
   (e.g. `AIPS_Schedule_Repository implements AIPS_Schedule_Repository_Interface`).
   See the `aips-repository-boundary` skill for the enforcement mechanism.
5. **Write or extend a controller test.** Tests follow the `Test_AIPS_*_Controller_*.php`
   naming convention (e.g. `Test_AIPS_Schedule_Controller_Save.php`) and extend
   `WP_UnitTestCase` directly (no shared base TestCase in this repo). The standard
   pattern: mock the injected service/repository with `getMockBuilder()`, construct
   the controller with the mock, then use a `call_ajax()` helper that `ob_start()`s,
   invokes the AJAX callback, catches `WPAjaxDieContinueException` /
   `WPAjaxDieStopException`, and `json_decode`s the captured output.

## Guardrails

- Never bypass `AIPS_Ajax_Registry` — a controller instantiated outside `boot_ajax()`
  won't be wired to the right context boot (see `AI_Post_Scheduler::init()`).
- Don't skip the capability/nonce check even for read-only actions.
- Don't run the full test suite proactively — per the repo's testing policy, only run
  `composer test` when explicitly asked or required by the task; targeted single-file
  runs (`vendor/bin/phpunit tests/<file>.php`) are fine when verifying a specific change.

## Reference files

- `ai-post-scheduler/includes/class-aips-ajax-registry.php`
- `ai-post-scheduler/includes/class-aips-schedule-controller.php`
- `ai-post-scheduler/includes/class-aips-unified-schedule-service.php`
- `ai-post-scheduler/includes/class-aips-schedule-repository.php`
- `ai-post-scheduler/tests/Test_AIPS_Schedule_Controller_Save.php`
