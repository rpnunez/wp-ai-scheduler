Analysis complete. Here's the state of the branch against the plan and what's left to finish it.

## Where the branch actually stands

The **runtime detangle is essentially done, but the deletions and the schema migration are not started.** Someone stripped all the call sites cleanly (28 files, ~1,600 deletions, and no dangling references to the removed classes in any kept file), but never deleted the subsystem files themselves, never touched the DB layer's table definitions, and never wrote the cleanup migration — even though the version was already bumped. That last combination is the dangerous gap.

### Done ✅
- **Step 2 — wiring strip:** AJAX registry, admin-menu submenus, admin-assets, container DI, and `boot_common()` telemetry boot are all cleaned. No kept file references `AIPS_Telemetry`/`AIPS_Cache_Monitor`/`AIPS_Cache_Index`/`AIPS_Cache_Monitorable`.
- **Step 3 — settings/config:** `aips_enable_telemetry`, `aips_telemetry_retention_*`, `aips_cache_monitor_*` defaults/fields removed from config, settings, settings-ui; `setup-devstacktips-content.php` toggle cleared.
- **Step 3a — prune rename:** `aips_telemetry_prune_interval` → `aips_db_prune_interval` is fully renamed in config default, `cron-registry::resolve_prune_recurrence()`, `register_setting`, the UI field, and the `update_option_/add_option_` cron-sync hooks.
- **Step 4 — runtime detangle:** cache, all three drivers, repository-cache-observer, operations-insights, db-prune-service, system-diagnostics-service, system-status-controller, templates, `admin-system-status.js`, cron-registry, system-info-provider all stripped. `flush_group` was promoted onto `interface-aips-cache-driver.php` (+8 lines).
- **Version:** header + `AIPS_VERSION` already at `3.7.26`.

### Not done ❌
- **Step 1 — delete files:** all 14 dedicated files still exist (telemetry ×3 + template/js/css/test, cache-monitor ×3 + index + monitorable interface + template/js).
- **Step 5 — schema + migration (critical):** `class-aips-db-manager.php` and `class-aips-db-migrations.php` are **untouched**. `aips_telemetry`, `aips_cache_index`, `aips_cache_events` are still in the `$tables` list, local vars (lines 33/36/37, 105/108/109), `CREATE TABLE` blocks (~691/778 + cache_events), and the status map (~1091). There is **no `migrate_to_3_7_26()`**. Because the version is already bumped to 3.7.26, an upgrade right now would stamp `aips_db_version` to 3.7.26 **without ever dropping the tables or copying the renamed prune option** — so existing installs silently keep the dead tables and lose their prune interval.
- **Step 6 — tests/docs:** `Test_AIPS_Telemetry.php` still present; ~10 other test files still reference the removed subsystems (Container_Bindings, Admin_Menu, Admin_Page_Context, Cache, Cron_Registry, Cron_Manager, System_Diagnostics_Service, Performance_Hardening, Repository_Insert_Id, Diagnostics_Embedded_Templates, History_Stalled_Status). Docs not updated.

### Gaps the plan itself missed
These still route to the removed telemetry page and will break links / leave dead labels:
- `class-aips-admin-menu-helper.php:84` — `'telemetry' => 'telemetry'` in `$diagnostics_tabs`.
- `class-aips-schedule-controller.php:240` — `'telemetry' => get_page_url('telemetry')` quick link.
- `assets/js/admin.js:210` + the `aipsScheduleL10n.telemetry` localized string that feeds it.
- `tables/class-aips-schedules-list-table.php:820,824` — "Health & Telemetry" drawer label.
- Comment-only mentions (cosmetic, affects the "grep-clean" verification goal): `class-aips-cache.php:32`, `content-indexer-service.php:7`, `generator.php:2330`, `repository-cache-config.php:51`, `trait-aips-cacheable-repository.php:310`, `diagnostics.php:38`, `operational-status.php:21`.
- **Composer autoload:** `vendor/composer/autoload_classmap.php` + `autoload_static.php` still map the deleted classes — needs `composer dump-autoload` (via `make composer-install`) after deletion.

## Plan to finalize

1. **Delete the 14 files** (Step 1) and run `make composer-install` to regenerate the autoload classmaps.
2. **DB manager (Step 5a):** remove all three tables from the `$tables` array, the three local `$table_*` vars, their `CREATE TABLE` blocks, and the status-map entry — so new installs never create them.
3. **Write `migrate_to_3_7_26()` (Step 5b)** in `class-aips-db-migrations.php`, gated by `version_compare($from, '3.7.26', '<')`: `DROP TABLE IF EXISTS` the three tables; copy `aips_telemetry_prune_interval` → `aips_db_prune_interval` then `delete_option` the old; `delete_option` every removed telemetry/cache-monitor setting; `wp_clear_scheduled_hook('aips_cache_monitor_maintenance')`; resync the prune schedule. Register it in `check_and_run()`. **This is the top priority** since the version is already bumped.
4. **Close the missed wiring sites:** remove the telemetry entry from `admin-menu-helper`, the `schedule-controller` quick link, the `admin.js` link + its l10n string, and the list-table label; sweep the comment-only mentions so Step 7's grep comes back clean.
5. **Tests/docs (Step 6):** delete `Test_AIPS_Telemetry.php`, update the ~10 referencing suites, update `docs/AI_AGENT_REFERENCE.md` and `docs/performance-pruning-guide.md`.
6. **Verify (Step 7):** grep for the terms, then run the affected suites via `make test-session ARGS="tests/Test_AIPS_DB_Migrations.php tests/Test_AIPS_Container_Bindings.php tests/Test_AIPS_Admin_Menu.php tests/Test_AIPS_Cache.php tests/Test_AIPS_Cron_Registry.php tests/Test_AIPS_System_Diagnostics_Service.php ..."`.

Suggested order: **3 → 2 → 1 → 4 → 5 → 6**, i.e. land the migration and DB-manager changes *first* (the branch is currently in an unshippable state because of the version/migration mismatch), then do the deletions and autoload regen, then clean up wiring, tests, and docs.

Want me to start executing — and if so, should I begin with the critical migration + DB-manager work, or the file deletions?