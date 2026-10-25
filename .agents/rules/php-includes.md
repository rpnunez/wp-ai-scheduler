# PHP rules: `ai-post-scheduler/includes/**`

Read `wordpress-core.md` too. Long-form rules live in `docs/DEVELOPMENT_GUIDELINES.md`; subsystem details in `docs/AI_AGENT_REFERENCE.md`.

## Naming and style
- Classes are `AIPS_Foo_Bar`, in `class-aips-foo-bar.php` (interfaces: `interface-aips-*.php`). No namespaces. Subfolders: `diagnostics/`, `job/`, `providers/`, `tables/`.
- Start every file with `if (!defined('ABSPATH')) { exit; }`.
- Use `array()` syntax. Indent with tabs; if you edit one of the older space-indented files, match that file.
- Use `AIPS_DateTime` for timestamps (stored as BIGINT unix ints); never `date()`, `strtotime()`, `current_time()` in new code.

## Layers (enforced)
- **Controllers** (`class-aips-*-controller.php`): register `wp_ajax_*` hooks in the constructor, verify nonce + capability, sanitize, respond with `AIPS_Ajax_Response`. Parse booleans with `filter_var($v, FILTER_VALIDATE_BOOLEAN)`.
- **Repositories** (`class-aips-*-repository.php`): all `$wpdb` SQL. Collection/repeater repositories need atomic group sync; clone actions must copy child mappings.
- **Services** (`class-aips-*-service.php`): business logic and orchestration, no SQL.
- `composer lint:repository-boundary` (run from `ai-post-scheduler/`) fails on `$wpdb` in controllers/services outside `config/repository-boundary-whitelist.txt`. Do not add whitelist entries without a reason in the PR.
- Some older classes (e.g. `class-aips-history.php`, `class-aips-planner.php`) still register AJAX hooks or touch `$wpdb` directly. They are legacy; do not copy the pattern.

## Wiring
- Register every AJAX action in `AIPS_Ajax_Registry::$map` (`includes/class-aips-ajax-registry.php`); never add ad hoc `wp_ajax_*` registration elsewhere.
- Resolve shared services with `AIPS_Container::get_instance()->make(...)`; bindings live in `AI_Post_Scheduler::register_container_bindings()`.
- Boot code is context-split (`boot_common/cron/ajax/admin/frontend`); load only what that request needs.
- Schema change: edit `AIPS_DB_Manager::get_schema()`, bump `Version:` and `AIPS_VERSION`, add a repository for a new table. Data migrations go through `AIPS_DB_Migrations`.
- Long-running work registers with `AIPS_Background_Process_Manager` (extend `AIPS_Managed_Background_Process`) instead of a private cron chain. Bulk jobs go through `AIPS_Bulk_Batch_Processor` strategies.

## Generation, logging, localization
- Generation flows use `AIPS_Generation_Context`, `AIPS_Template_Context`, `AIPS_Topic_Context` (via `AIPS_Generation_Context_Factory`) and the shared prompt builders, never ad hoc string assembly.
- Record significant actions, AI calls and failures with `AIPS_History_Service` / `AIPS_History_Container`, plus `AIPS_Generation_Logger`, `AIPS_Logger` and `AIPS_Correlation_Id`. Log lifecycle (start, warning/error, completion).
- Localized data comes from `AIPS_Language_Store` and `AIPS_Admin_L10n`.
- Handle a missing Meow Apps AI Engine dependency gracefully (see `includes/providers/`).

## Verify
- Matching reviewers: `ajax-controller-reviewer`, `db-migration-reviewer`, `batch-job-reviewer`, `generation-pipeline-reviewer`, `l10n-reviewer` (see `.agents/agents/`).
