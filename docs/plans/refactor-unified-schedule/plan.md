# Refactor Unified Schedule: Full Plan

## Goal
Remove legacy schedule list/bulk/history code and keep unified schedule runtime/UI as the single supported path for Schedule page operations, while handling template schedule create/edit explicitly.

## Scope
Included:
- Legacy schedule AJAX actions and handlers that duplicate unified behavior.
- Legacy schedule JS listeners/methods tied to non-unified DOM or legacy actions.
- Legacy schedule template/modal fragments not needed after unification.
- Legacy schedule localization and CSS hooks tied to removed behavior.
- Legacy-only tests and docs references once unified tests are in place.

Excluded unless explicitly approved:
- Changes to core cron hooks and generation internals beyond schedule endpoint/UI unification.
- Broad architecture changes outside schedule domain.

## Phase 0: Decision gate
1. Decide template schedule create/edit strategy:
- Option A: Migrate add/edit to unified endpoint contracts now.
- Option B: Keep minimal template-only add/edit endpoint temporarily, remove all other legacy list/bulk/history code.
2. Recommended first step: Option B for lower risk, then Option A in a follow-up.

## Phase 1: Test safety net before removals
1. Add dedicated unified endpoint tests for:
- `ajax_unified_run_now`
- `ajax_unified_toggle`
- `ajax_unified_bulk_toggle`
- `ajax_unified_bulk_run_now`
- `ajax_unified_bulk_delete`
- `ajax_get_unified_schedule_history`
2. Mirror assertion depth currently present in legacy tests.
3. Keep legacy tests temporarily until unified test parity and stability are confirmed.

## Phase 2: JavaScript cleanup
1. Remove legacy schedule listener bindings in `bindEvents()` that target legacy selectors.
2. Remove legacy schedule method family:
- `toggleAllSchedules`
- `toggleScheduleSelection`
- `selectAllSchedules`
- `unselectAllSchedules`
- `updateScheduleBulkActions`
- `applyScheduleBulkAction`
- `bulkDeleteSchedules`
- `bulkToggleSchedules`
- `bulkRunNowSchedules`
- `runNowSchedule`
- `toggleSchedule`
- `viewScheduleHistory`
- Legacy schedule search/filter methods tied to removed selectors.
3. Keep unified methods as the only schedule-page behavior.

## Phase 3: Controller cleanup
1. Remove legacy action registrations replaced by unified equivalents:
- `aips_delete_schedule`
- `aips_toggle_schedule`
- `aips_run_now`
- `aips_bulk_delete_schedules`
- `aips_bulk_toggle_schedules`
- `aips_bulk_run_now_schedules`
- `aips_get_schedules_post_count`
- `aips_get_schedule_history`
2. Remove corresponding legacy controller methods after UI/JS migration confirms no usage.
3. Keep or migrate `ajax_save_schedule` per Phase 0 decision.

## Phase 4: AJAX registry cleanup
1. Remove legacy schedule action map entries from `AIPS_Ajax_Registry`.
2. Keep only unified schedule actions plus any explicitly retained transitional add/edit endpoint.

## Phase 5: Template cleanup
1. In schedule template, remove legacy-only modal/actions once no longer used.
2. Ensure row actions and controls use unified classes and unified endpoint flows only.
3. If template add/edit remains temporarily, isolate and label it as transitional.

## Phase 6: Localization and CSS cleanup
1. Remove unused legacy schedule strings from schedule-page localization payload.
2. Remove obsolete legacy schedule CSS selectors.
3. Keep shared styles still used by unified UI.

## Phase 7: Tests and docs migration
1. Remove or rewrite legacy endpoint tests after unified tests are complete and green.
2. Update docs to unified-only truth.
3. Add changelog entry for endpoint deprecation/removal and migration notes.

## Verification checklist
1. Static checks:
- No references to removed legacy schedule action names.
- No references to removed legacy schedule selectors.
2. PHPUnit:
- Unified endpoint tests pass.
- Existing schedule and integration suites pass.
3. Manual wp-admin checks on Schedules page:
- Type filter
- Search and clear
- Select all and unselect
- Bulk run/pause/resume/delete
- Per-row run now
- Per-row toggle
- History modal
- Template add/edit if retained
4. Regression sanity:
- Cron-driven processing still works for template, author topic, and author post schedules.

## Risks and mitigations
1. Risk: External consumers might call legacy AJAX actions directly.
- Mitigation: Include short compatibility/deprecation window and clear release notes.
2. Risk: Coverage gap if legacy tests are removed too early.
- Mitigation: Build unified tests first and enforce parity.
3. Risk: Breaking template schedule edit flow.
- Mitigation: Keep Decision Gate explicit and test chosen strategy thoroughly.

## Suggested execution order
1. Add unified test suite.
2. Remove legacy JS listeners/methods and template hooks.
3. Remove legacy controller methods and constructor registrations.
4. Remove legacy registry mappings.
5. Clean localization and CSS.
6. Remove legacy tests.
7. Update docs and changelog.

## Primary files impacted
- `ai-post-scheduler/assets/js/admin.js`
- `ai-post-scheduler/includes/class-aips-schedule-controller.php`
- `ai-post-scheduler/includes/class-aips-ajax-registry.php`
- `ai-post-scheduler/templates/admin/schedule.php`
- `ai-post-scheduler/includes/class-aips-admin-assets.php`
- `ai-post-scheduler/assets/css/admin.css`
- `ai-post-scheduler/tests/test-schedule-controller-save.php`
- `ai-post-scheduler/tests/test-schedule-controller-run-now.php`
- `ai-post-scheduler/tests/test-schedule-controller-bulk.php`
- `ai-post-scheduler/tests/test-schedule-history.php`
- `docs/feature-report.md`
