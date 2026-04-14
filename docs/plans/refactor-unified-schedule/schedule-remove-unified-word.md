# Plan: Remove "unified" Terminology from Codebase

## Goal
Remove the word "unified" from primary schedule-related class names, function/method names, JS selectors, CSS hooks, comments, and tests, while using a temporary backward-compatible AJAX alias window.

## Status
Completed as of 2026-04-13.

- Phase 1 complete: backend schedule naming migrated to non-unified primary names.
- Phase 2 complete: frontend JS, template, and CSS schedule hooks migrated.
- Phase 3 complete: schedule tests and targeted comment/docblock cleanup updated.
- Phase 4 complete: legacy `aips_unified_*` AJAX aliases, deprecation logging, and alias-only tests removed.
- Strict grep gate passed: `rg -n "\bunified\b|Unified" ai-post-scheduler --glob '!**/vendor/**'` returned no matches.

Final state:
- The schedule runtime uses only `aips_schedule_*` action names.
- No `unified` schedule references remain in the plugin codebase under `ai-post-scheduler/`.

## Scope
Primary scope:
- [ai-post-scheduler/includes/class-aips-unified-schedule-service.php](ai-post-scheduler/includes/class-aips-unified-schedule-service.php)
- [ai-post-scheduler/includes/class-aips-schedule-controller.php](ai-post-scheduler/includes/class-aips-schedule-controller.php)
- [ai-post-scheduler/includes/class-aips-ajax-registry.php](ai-post-scheduler/includes/class-aips-ajax-registry.php)
- [ai-post-scheduler/assets/js/admin.js](ai-post-scheduler/assets/js/admin.js)
- [ai-post-scheduler/templates/admin/schedule.php](ai-post-scheduler/templates/admin/schedule.php)
- [ai-post-scheduler/assets/css/admin.css](ai-post-scheduler/assets/css/admin.css)
- schedule-related tests under [ai-post-scheduler/tests](ai-post-scheduler/tests)

Secondary sweep (word cleanup in comments/docs in code files):
- [ai-post-scheduler/includes/class-aips-history-type.php](ai-post-scheduler/includes/class-aips-history-type.php)
- [ai-post-scheduler/includes/class-aips-history-service.php](ai-post-scheduler/includes/class-aips-history-service.php)
- [ai-post-scheduler/includes/class-aips-generator.php](ai-post-scheduler/includes/class-aips-generator.php)
- [ai-post-scheduler/includes/class-aips-admin-assets.php](ai-post-scheduler/includes/class-aips-admin-assets.php)
- [ai-post-scheduler/includes/class-aips-schedule-processor.php](ai-post-scheduler/includes/class-aips-schedule-processor.php)

## Naming Map (Target State)

### PHP Class/File
- `AIPS_Unified_Schedule_Service` -> `AIPS_Schedule_Service`
- `class-aips-unified-schedule-service.php` -> `class-aips-schedule-service.php`

### AJAX Hooks
- `aips_unified_run_now` -> `aips_schedule_run_now`
- `aips_unified_toggle` -> `aips_schedule_toggle`
- `aips_unified_bulk_toggle` -> `aips_schedule_bulk_toggle`
- `aips_unified_bulk_run_now` -> `aips_schedule_bulk_run_now`
- `aips_unified_bulk_delete` -> `aips_schedule_bulk_delete`
- `aips_get_unified_schedule_history` -> `aips_get_schedule_history`

### JS Function Names
- `filterUnifiedByType` -> `filterScheduleByType`
- `filterUnifiedSchedules` -> `filterSchedules`
- `clearUnifiedSearch` -> `clearScheduleSearch`
- `toggleAllUnified` -> `toggleAllScheduleRows`
- `toggleUnifiedSelection` -> `toggleScheduleSelection`
- `selectAllUnified` -> `selectAllSchedules`
- `unselectAllUnified` -> `unselectAllSchedules`
- `updateUnifiedBulkActions` -> `updateScheduleBulkActions`
- `applyUnifiedBulkAction` -> `applyScheduleBulkAction`
- `confirmUnifiedBulkDelete` -> `confirmScheduleBulkDelete`
- `unifiedBulkRunNow` -> `scheduleBulkRunNow`
- `unifiedBulkToggle` -> `scheduleBulkToggle`
- `unifiedBulkDelete` -> `scheduleBulkDelete`
- `toggleUnifiedSchedule` -> `toggleSchedule`
- `updateUnifiedRowStatus` -> `updateScheduleRowStatus`
- `runNowUnified` -> `runScheduleNow`
- `viewUnifiedScheduleHistory` -> `viewScheduleHistory`

### JS/CSS/Template Selectors
- `#aips-unified-type-filter` -> `#aips-schedule-type-filter`
- `#aips-unified-search` -> `#aips-schedule-search`
- `#aips-unified-search-clear` -> `#aips-schedule-search-clear`
- `.aips-clear-unified-search-btn` -> `.aips-clear-schedule-search-btn`
- `.aips-unified-row` -> `.aips-schedule-row`
- `.aips-unified-checkbox` -> `.aips-schedule-checkbox`
- `#cb-select-all-unified` -> `#cb-select-all-schedules`
- `#aips-unified-select-all` -> `#aips-schedule-select-all`
- `#aips-unified-unselect-all` -> `#aips-schedule-unselect-all`
- `#aips-unified-bulk-action` -> `#aips-schedule-bulk-action`
- `#aips-unified-bulk-apply` -> `#aips-schedule-bulk-apply`
- `#aips-unified-selected-count` -> `#aips-schedule-selected-count`
- `.aips-unified-toggle-schedule` -> `.aips-schedule-toggle`
- `.aips-unified-run-now` -> `.aips-schedule-run-now`
- `.aips-view-unified-history` -> `.aips-view-schedule-history`
- `.aips-delete-unified-schedule` -> `.aips-delete-schedule`
- `.aips-unified-schedule-table` -> `.aips-schedule-table`
- `#aips-unified-search-no-results` -> `#aips-schedule-search-no-results`

## Migration Strategy

### Phase 0: Compatibility Mode Decision (Strict vs Transitional)

| Option | What it does | Pros | Cons | When to choose |
|---|---|---|---|---|
| Strict immediate cutover | Remove all `aips_unified_*` hooks immediately and keep only `aips_schedule_*` hooks | Fully removes legacy naming immediately | Breaking change for external callers still using old actions | Choose when strict naming purge is non-negotiable and integrations can be updated at once |
| Transitional deprecation window (selected) | Keep old `aips_unified_*` aliases temporarily, route to renamed handlers, and emit warning logs when alias hooks are called | Smooth migration path with minimal integration breakage | `unified` remains in code during transition, so full purge is deferred | Choose when compatibility and low-risk rollout are preferred |

Selected path:
- Use transitional deprecation window.
- Keep old unified AJAX aliases temporarily.
- Emit deprecation warnings to WordPress error log each time legacy aliases are called.

Planned warning behavior for alias calls:
- Log with `error_log()` when any `aips_unified_*` hook is invoked.
- Include old hook name, recommended new hook, and removal target version/date in the message.
- Keep logging lightweight and deterministic (no user data, no payload dump).

### Phase 1: Backend Rename (PHP + AJAX)
1. Rename class/file and update all instantiation/type references.
2. Rename AJAX hook registrations in schedule controller.
3. Add temporary legacy alias hooks (`aips_unified_*`) that forward to the renamed handlers.
4. Emit deprecation warning logs when alias hooks are called.
5. Rename action keys in AJAX registry map and keep alias map entries during transition.
6. Rename controller method names only where they include "unified" (keep behavior unchanged).
7. Update all service constant references in schedule template/tests.

Acceptance:
- Primary runtime path uses only new non-unified names.
- Legacy alias hooks still function during transition and emit deprecation warnings.
- Existing schedule behavior unchanged.

### Phase 2: Frontend Rename (JS + Template + CSS)
1. Rename JS methods and all call sites/events in [ai-post-scheduler/assets/js/admin.js](ai-post-scheduler/assets/js/admin.js).
2. Rename selectors/classes/IDs in schedule template.
3. Rename matching CSS selectors in [ai-post-scheduler/assets/css/admin.css](ai-post-scheduler/assets/css/admin.css).
4. Update AJAX action names used in JS requests to the new non-unified hooks.

Acceptance:
- Schedule page add/edit/run/toggle/bulk/history/search still works.
- DOM hooks and JS bindings are internally consistent.

### Phase 3: Tests + Strings + Comment Sweep
1. Update schedule tests for class names and AJAX hook names.
2. Add tests for alias forwarding + warning logging behavior.
3. Update test descriptions/titles containing "unified" where they refer to primary paths.
4. Remove "unified" in schedule-related comments/docblocks except temporary deprecation references.
5. Optional strict sweep: remove remaining non-schedule "unified" wording in code comments (history/generator/admin-assets) if the requirement is truly global.

Acceptance:
- Schedule test suite passes with new names.
- `rg -n "aips_unified_" ai-post-scheduler --glob '!**/vendor/**'` returns only temporary alias and deprecation references.

### Phase 4: Alias Removal (Final Cutover)
1. Remove legacy `aips_unified_*` alias hooks after the deprecation window.
2. Remove alias warning logging and related compatibility tests.
3. Run strict grep gate for complete removal.

Acceptance:
- `rg -n "\bunified\b|Unified" ai-post-scheduler --glob '!**/vendor/**'` returns zero schedule runtime hits.
- Any remaining `unified` hits are intentional historical references outside runtime code or removed as part of strict global cleanup.

## Compatibility Decision (Required)
Compatibility policy now uses a transitional deprecation window:
- Keep old hook aliases temporarily.
- Emit deprecation warning logs for every alias call.
- Publish migration guidance to move callers from `aips_unified_*` to `aips_schedule_*`.
- Remove aliases in a planned final cutover phase.

Impact:
- Existing callers continue to work during transition but generate warning logs.
- External callers must still migrate before alias removal phase.

## Risk Areas
- Schedule page selectors are heavily referenced; partial renames can silently break actions.
- AJAX hook rename is externally visible and can break custom scripts or integrations.
- Bulk action logic has many function chains in JS and should be renamed mechanically in one coherent patch.

## Validation Checklist
1. Static checks:
- `rg -n "aips_unified_" ai-post-scheduler --glob '!**/vendor/**'`
 - Confirm hits are only alias compatibility code during transition.
 - Final cutover gate: `rg -n "\bunified\b|Unified" ai-post-scheduler --glob '!**/vendor/**'`
2. Schedule UI manual checks:
- Add schedule
- Edit schedule
- Save schedule
- Toggle schedule active state
- Run now (single)
- Bulk run/toggle/delete
- View schedule history
- Search/filter/select-all behavior
3. PHPUnit targets:
- [ai-post-scheduler/tests/test-schedule-controller-save.php](ai-post-scheduler/tests/test-schedule-controller-save.php)
- [ai-post-scheduler/tests/test-schedule-controller-run-now.php](ai-post-scheduler/tests/test-schedule-controller-run-now.php)
- [ai-post-scheduler/tests/test-schedule-controller-bulk.php](ai-post-scheduler/tests/test-schedule-controller-bulk.php)
- [ai-post-scheduler/tests/test-schedule-history.php](ai-post-scheduler/tests/test-schedule-history.php)
- [ai-post-scheduler/tests/test-admin-js-schedule-legacy-only.php](ai-post-scheduler/tests/test-admin-js-schedule-legacy-only.php)
- Add or update tests that verify alias hook forwarding and warning logging.

## Recommended Execution Order (Single PR)
1. Rename PHP class/file and primary AJAX hook constants/usages.
2. Add temporary alias hooks + warning logging.
3. Rename template + JS selectors/methods and AJAX action payloads.
4. Rename CSS hooks.
5. Update tests, including alias/warning coverage.
6. Run transition grep gate and schedule-focused tests.
7. Schedule final alias-removal follow-up and strict grep gate.

## Done Definition
- During transition: primary schedule code path uses non-unified naming; only temporary alias/deprecation references to `aips_unified_*` remain; schedule functionality and tests remain green.
- Final cutover: all `unified` schedule runtime references are removed and strict grep gate passes.
