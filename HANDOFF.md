# HANDOFF — Remove Telemetry & Cache Monitor subsystems

**Branch:** `feature/remove-telemetry-and-cache-monitor`
**Plan:** `remove-cache-monitor-and-telemetry-plan.md` (in repo root)
**Target version:** `3.7.25` → `3.7.26` (already bumped)
**Status as of this handoff:** core removal + migration complete; **tests partially updated**, docs not yet updated, full verification not yet run.

---

## Scope (unchanged from the plan)

- **Remove:** Telemetry collection/UI/pruning; Cache Monitor UI/AJAX/cron; the Cache Index / `aips_cache_events` tables that existed only to feed Cache Monitor.
- **Keep:** core `AIPS_Cache` / `aips_cache` table / driver get/set/delete/flush; history-log retention and orphan-cleanup pruning.
- **Group flush:** `delete_group()` promoted onto the cache-driver interface; `AIPS_Cache::flush_group()` added; embeddings repo calls `flush_group('aips_embeddings')`.

---

## DONE ✅

### Critical DB + migration (this session)
- **`includes/class-aips-db-manager.php`** — removed `aips_telemetry`, `aips_cache_index`, `aips_cache_events` from the `$tables` array, the local `$table_*` vars, all three `CREATE TABLE` blocks, and the `aips_telemetry` entry in the datetime-column status map. New installs no longer create these tables. Lint-clean.
- **`includes/class-aips-db-migrations.php`** — added `migrate_to_3_7_26()` and its `version_compare( $from, '3.7.26', '<' )` gate in `run_upgrade()` (runs after `install_tables()`, before the version stamp). It:
  1. `DROP TABLE IF EXISTS` the three tables.
  2. Copies `aips_telemetry_prune_interval` → `aips_db_prune_interval` (only if the legacy option was stored and the new one is unset), then deletes the legacy option.
  3. `delete_option` for all 12 removed Telemetry / Cache Monitor options.
  4. `wp_clear_scheduled_hook('aips_cache_monitor_maintenance')`.
  5. Resyncs `aips_database_prune_cleanup` via `AIPS_Settings::sync_prune_cron_schedule()` when the interval was migrated.
  - NOTE: the remaining `telemetry` / `cache_monitor` grep hits in this file are **intentional** — they are the literal table/option/hook names the migration must drop.

### File deletions (this session)
14 files `git rm`'d: telemetry class/controller/repository + `templates/admin/telemetry.php` + `assets/js/telemetry.js` + `assets/css/telemetry.css` + `tests/Test_AIPS_Telemetry.php`; cache-monitor service/controller/repository + `class-aips-cache-index.php` + `interface-aips-cache-monitorable-driver.php` + `templates/admin/cache-monitor.php` + `assets/js/cache-monitor.js`.
- **`composer dump-autoload` was run** — classmap regenerated, 0 references to the deleted classes remain in `vendor/composer/autoload_classmap.php` / `autoload_static.php`.

### Runtime detangle + wiring strip (pre-existing branch work, verified consistent)
- AJAX registry, admin-menu submenus, admin-assets, container DI, bootstrap `boot_common()` telemetry boot, settings/config option defaults + fields, cache + 3 drivers + observer, ops-insights, db-prune-service, system-diagnostics-service, system-status-controller, templates, `admin-system-status.js`, cron-registry, system-info-provider — all stripped. No kept file references the deleted classes.
- **Prune-interval rename (3a) complete:** `aips_telemetry_prune_interval` → `aips_db_prune_interval` in config default, `cron-registry::resolve_prune_recurrence()`, `register_setting`, settings-ui field, and the `update_option_/add_option_` cron-sync hooks.

### Missed wiring sites the plan didn't list (this session)
- `includes/class-aips-admin-menu-helper.php` — removed `'telemetry' => 'telemetry'` from `$diagnostics_tabs`.
- `includes/class-aips-schedule-controller.php` — removed the `'telemetry'` quick-link.
- `assets/js/admin.js` — removed the telemetry anchor from the retry-pending warning (its l10n key was already gone).
- `includes/tables/class-aips-schedules-list-table.php` — "Health & Telemetry" label → "Health & Status" (generic word, not the subsystem).
- Comment/string-only cleanups so the grep comes back clean: `class-aips-cache.php`, `class-aips-content-indexer-service.php`, `class-aips-generator.php`, `class-aips-repository-cache-config.php`, `trait-aips-cacheable-repository.php`, `templates/admin/diagnostics.php`, `templates/admin/operational-status.php`, `scripts/setup-devstacktips-content.php`.

### Tests updated so far (this session)
- `tests/Test_AIPS_Admin_Page_Context.php` — `aips-telemetry` child-page assertion → `aips-status`.
- `tests/Test_AIPS_Repository_Insert_Id.php` — dropped the `aips_cache_monitor_index_enabled` setUp option.
- `tests/Test_AIPS_Cron_Registry.php` — removed `aips_cache_monitor_maintenance` from expected hooks; renamed prune-interval option in tearDown + 3 tests.
- `tests/Test_AIPS_Cron_Manager.php` — renamed prune-interval option (tearDown + drift test); swapped the generic reschedule test's hook from `aips_cache_monitor_maintenance` to `aips_database_prune_cleanup`.

---

## REMAINING ❌

### 1. Finish test updates
These still reference removed classes/options and **will fatal or fail** until fixed:

- **`tests/Test_AIPS_Admin_Menu.php`**
  - Line ~121: `$_GET['page'] = 'aips-telemetry';` — repoint to a surviving diagnostics page or remove that case.
  - Lines ~227–265: `test_cache_monitor_page_is_not_registered_by_default()` and `test_cache_monitor_page_is_registered_when_enabled()` — **delete both** (feature is gone).
- **`tests/Test_AIPS_Cache.php`** — heaviest. Remove:
  - `AIPS_Test_Cache_Index_Recorder extends AIPS_Cache_Index` class (top of file).
  - `AIPS_Telemetry` setUp/tearDown reflection resets + `delete_option('aips_enable_telemetry')`.
  - `test_get_cache_index_is_resolved_per_instance()`, `test_cache_operations_record_telemetry_when_enabled()`, the `inject_cache_index()` helper, and any `aips_cache_monitor_index_enabled` toggles.
  - Keep/verify the `flush_group()` coverage is intact.
- **`tests/Test_AIPS_Container_Bindings.php`** — remove the `AIPS_Telemetry_Repository` binding assertions (lines ~67, 112–116, 147).
- **`tests/Test_AIPS_Diagnostics_Embedded_Templates.php`** — remove `test_telemetry_template_renders_as_partial()` and the `'telemetry_enabled' => true` context entry (lines ~53, 67–84).
- **`tests/Test_AIPS_Performance_Hardening.php`** — remove the `AIPS_Cache_Index` tests (lines ~174–222): index insert/flush/threshold cases.
- **`tests/Test_AIPS_System_Diagnostics_Service.php`** — remove the `AIPS_Cache_Monitor_Service` mock + the `cache_monitor_service` constructor dependency and the two tests asserting `run_maintenance()` behavior (lines ~23–25, 42, 81–82, 178–242). Confirm the real `AIPS_System_Diagnostics_Service` constructor signature no longer takes that dependency and update `make_service()` accordingly.
- `tests/Test_AIPS_History_Stalled_Status.php:252` — `'Telemetry post'` is just a fixture post title; **leave as-is** (unrelated to the subsystem).

### 2. Docs (living only)
- `docs/AI_AGENT_REFERENCE.md` — remove telemetry / cache-monitor surface area.
- `docs/performance-pruning-guide.md` — update the option reference: `aips_telemetry_prune_interval` → `aips_db_prune_interval`; drop telemetry retention mentions.
- Leave historical notes under `docs/superpowers/` and `docs/css-variables-audit.md` untouched.

### 3. Verification (plan step 7)
- Grep the repo for `telemetry`, `cache_monitor`, `Cache_Monitor`, `Cache_Index`, `aips_telemetry`, `aips_cache_index`, `aips_cache_events` and confirm only the intentional migration literals (and the `docs/superpowers/` history) remain.
- Run affected suites in one invocation (per `TESTING.md` Agent test protocol):
  ```
  make test-session ARGS="tests/Test_AIPS_DB_Migrations.php tests/Test_AIPS_Container_Bindings.php tests/Test_AIPS_Admin_Menu.php tests/Test_AIPS_Admin_Page_Context.php tests/Test_AIPS_Cache.php tests/Test_AIPS_Cron_Registry.php tests/Test_AIPS_Cron_Manager.php tests/Test_AIPS_System_Diagnostics_Service.php tests/Test_AIPS_Diagnostics_Embedded_Templates.php tests/Test_AIPS_Performance_Hardening.php tests/Test_AIPS_Repository_Insert_Id.php"
  ```
- Spot-check Diagnostics hub: no Telemetry/Cache Monitor tabs; Settings has no enable toggles; System Status has no prune/purge telemetry actions.

### 4. Housekeeping before PR
- Decide whether to keep `remove-cache-monitor-and-telemetry-plan.md` and this `HANDOFF.md` in the repo or remove them from the final commit.
- Nothing is committed yet — all work is in the working tree.

---

## Gotchas / rationale
- **Version was bumped to 3.7.26 before the migration existed.** That was the dangerous state the migration now resolves — do not re-bump; keep `3.7.26` in both the plugin header and `AIPS_VERSION`.
- The migration is placed **after** `install_tables()` so the drop runs after dbDelta; since the tables are also removed from `get_schema()`, dbDelta no longer recreates them, so ordering is safe either way.
- All migration steps are idempotent (`DROP TABLE IF EXISTS` / `delete_option` / `wp_clear_scheduled_hook`), so re-running is harmless.
- `AIPS_Settings::sync_prune_cron_schedule()` is static and reads `aips_db_prune_interval`; the migration calls it guarded by `class_exists`.
