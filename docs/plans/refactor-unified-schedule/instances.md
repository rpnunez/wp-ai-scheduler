# Refactor Unified Schedule: Dual-Code Inventory

## Scope
This inventory lists every verified location where legacy schedule code and unified schedule code coexist.

## 1. Controller-level dual AJAX registration
- Both legacy and unified AJAX action handlers are registered in the same constructor.
- Legacy actions:
- `aips_save_schedule`
- `aips_delete_schedule`
- `aips_toggle_schedule`
- `aips_run_now`
- `aips_bulk_delete_schedules`
- `aips_bulk_toggle_schedules`
- `aips_bulk_run_now_schedules`
- `aips_get_schedules_post_count`
- `aips_get_schedule_history`
- Unified actions:
- `aips_unified_run_now`
- `aips_unified_toggle`
- `aips_unified_bulk_toggle`
- `aips_unified_bulk_run_now`
- `aips_unified_bulk_delete`
- `aips_get_unified_schedule_history`
- File: `ai-post-scheduler/includes/class-aips-schedule-controller.php`

## 2. Central AJAX registry maps both families
- The AJAX registry includes both legacy and unified schedule action names mapped to `AIPS_Schedule_Controller`.
- File: `ai-post-scheduler/includes/class-aips-ajax-registry.php`

## 3. Controller contains two complete handler families
- Legacy methods in the same class:
- `ajax_save_schedule`
- `ajax_delete_schedule`
- `ajax_toggle_schedule`
- `ajax_run_now`
- `ajax_bulk_delete_schedules`
- `ajax_bulk_toggle_schedules`
- `ajax_bulk_run_now_schedules`
- `ajax_get_schedules_post_count`
- `ajax_get_schedule_history`
- Unified methods in the same class:
- `ajax_unified_run_now`
- `ajax_unified_toggle`
- `ajax_unified_bulk_toggle`
- `ajax_unified_bulk_run_now`
- `ajax_unified_bulk_delete`
- `ajax_get_unified_schedule_history`
- File: `ai-post-scheduler/includes/class-aips-schedule-controller.php`

## 4. JavaScript event binding duplicates legacy + unified wiring
- Legacy schedule listeners and unified schedule listeners are both registered in `bindEvents()`.
- Legacy listener examples:
- `.aips-toggle-schedule`
- `.aips-view-schedule-history`
- `#cb-select-all-schedules`
- `.aips-schedule-checkbox`
- `#aips-schedule-bulk-apply`
- Unified listener examples:
- `.aips-unified-toggle-schedule`
- `.aips-view-unified-history`
- `#cb-select-all-unified`
- `.aips-unified-checkbox`
- `#aips-unified-bulk-apply`
- File: `ai-post-scheduler/assets/js/admin.js`

## 5. JavaScript contains legacy and unified method families in parallel
- Legacy schedule methods still present:
- `openScheduleModal`
- `editSchedule`
- `cloneSchedule`
- `saveSchedule`
- `saveScheduleWizard`
- `deleteSchedule`
- `runNowSchedule`
- `toggleSchedule`
- `viewScheduleHistory`
- Legacy bulk methods still present:
- `toggleAllSchedules`
- `toggleScheduleSelection`
- `selectAllSchedules`
- `unselectAllSchedules`
- `updateScheduleBulkActions`
- `applyScheduleBulkAction`
- `bulkDeleteSchedules`
- `bulkToggleSchedules`
- `bulkRunNowSchedules`
- Unified methods also present:
- `filterUnifiedByType`
- `filterUnifiedSchedules`
- `clearUnifiedSearch`
- `toggleAllUnified`
- `toggleUnifiedSelection`
- `selectAllUnified`
- `unselectAllUnified`
- `updateUnifiedBulkActions`
- `applyUnifiedBulkAction`
- `confirmUnifiedBulkDelete`
- `unifiedBulkRunNow`
- `unifiedBulkToggle`
- `unifiedBulkDelete`
- `toggleUnifiedSchedule`
- `updateUnifiedRowStatus`
- `runNowUnified`
- `viewUnifiedScheduleHistory`
- File: `ai-post-scheduler/assets/js/admin.js`

## 6. Schedule template is unified table but keeps legacy modal/action hooks
- Unified table and controls are the primary UI.
- Legacy Add/Edit template schedule modal still exists.
- Template-only row actions still use legacy classes for edit/delete.
- Unified controls still exist for run now/toggle/history.
- File: `ai-post-scheduler/templates/admin/schedule.php`

## 7. Localized schedule strings include both legacy and unified UX text
- Schedule localization payload includes labels/messages used by legacy and unified paths together.
- File: `ai-post-scheduler/includes/class-aips-admin-assets.php`

## 8. CSS contains legacy and unified schedule selectors
- Legacy selectors present (for old schedule table patterns).
- Unified selectors present (for unified row/action patterns).
- File: `ai-post-scheduler/assets/css/admin.css`

## 9. Test coverage is legacy-heavy; unified endpoint suite missing
- Legacy endpoint tests exist:
- `ai-post-scheduler/tests/test-schedule-controller-save.php`
- `ai-post-scheduler/tests/test-schedule-controller-run-now.php`
- `ai-post-scheduler/tests/test-schedule-controller-bulk.php`
- `ai-post-scheduler/tests/test-schedule-history.php`
- No dedicated test file for `ajax_unified_*` endpoints found.

## 10. Documentation records both legacy and unified handlers
- Feature report documents both endpoint families together.
- File: `docs/feature-report.md`

## 11. Service-layer coexistence
- Unified aggregation service exists and powers unified schedule listing.
- Legacy scheduler/repository flows still power legacy endpoint behavior and template schedule internals.
- Files:
- `ai-post-scheduler/includes/class-aips-unified-schedule-service.php`
- `ai-post-scheduler/includes/class-aips-scheduler.php`
- `ai-post-scheduler/includes/class-aips-schedule-repository.php`

## Inventory Summary
- Dual runtime endpoint families: present.
- Dual JS interaction families: present.
- Unified table with legacy modal/actions still embedded: present.
- Tests mostly target legacy endpoints: present.
- Docs still describe both paths: present.
