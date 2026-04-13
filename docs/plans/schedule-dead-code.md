# Final Pass: Schedule Dead-Code Audit

## Scope
- JS file reviewed: [ai-post-scheduler/assets/js/admin.js](ai-post-scheduler/assets/js/admin.js)
- Includes classes reviewed:
  - [ai-post-scheduler/includes/class-aips-schedule-controller.php](ai-post-scheduler/includes/class-aips-schedule-controller.php)
  - [ai-post-scheduler/includes/class-aips-scheduler.php](ai-post-scheduler/includes/class-aips-scheduler.php)
  - [ai-post-scheduler/includes/class-aips-schedule-processor.php](ai-post-scheduler/includes/class-aips-schedule-processor.php)
  - [ai-post-scheduler/includes/class-aips-schedule-repository.php](ai-post-scheduler/includes/class-aips-schedule-repository.php)
  - [ai-post-scheduler/includes/class-aips-unified-schedule-service.php](ai-post-scheduler/includes/class-aips-unified-schedule-service.php)
  - [ai-post-scheduler/includes/class-aips-schedule-entry.php](ai-post-scheduler/includes/class-aips-schedule-entry.php)

## Confirmed In-Use (Not Dead)
- Legacy schedule modal JS flow is fully wired and used by template markup:
  - Event bindings in [ai-post-scheduler/assets/js/admin.js#L106](ai-post-scheduler/assets/js/admin.js#L106)
  - Handler methods in [ai-post-scheduler/assets/js/admin.js#L1223](ai-post-scheduler/assets/js/admin.js#L1223), [ai-post-scheduler/assets/js/admin.js#L1236](ai-post-scheduler/assets/js/admin.js#L1236), [ai-post-scheduler/assets/js/admin.js#L1281](ai-post-scheduler/assets/js/admin.js#L1281), [ai-post-scheduler/assets/js/admin.js#L2861](ai-post-scheduler/assets/js/admin.js#L2861)
  - Matching markup in [ai-post-scheduler/templates/admin/schedule.php#L406](ai-post-scheduler/templates/admin/schedule.php#L406), [ai-post-scheduler/templates/admin/schedule.php#L415](ai-post-scheduler/templates/admin/schedule.php#L415), [ai-post-scheduler/templates/admin/schedule.php#L486](ai-post-scheduler/templates/admin/schedule.php#L486)
- Unified schedule AJAX endpoints are registered and mapped:
  - Controller registrations in [ai-post-scheduler/includes/class-aips-schedule-controller.php#L13](ai-post-scheduler/includes/class-aips-schedule-controller.php#L13)
  - Registry mappings in [ai-post-scheduler/includes/class-aips-ajax-registry.php#L41](ai-post-scheduler/includes/class-aips-ajax-registry.php#L41)
- Unified service methods are actively used by schedule template/controller:
  - Service class in [ai-post-scheduler/includes/class-aips-unified-schedule-service.php#L22](ai-post-scheduler/includes/class-aips-unified-schedule-service.php#L22)
  - Template usage in [ai-post-scheduler/templates/admin/schedule.php#L7](ai-post-scheduler/templates/admin/schedule.php#L7)
  - Controller usage in [ai-post-scheduler/includes/class-aips-schedule-controller.php#L147](ai-post-scheduler/includes/class-aips-schedule-controller.php#L147)

## Likely Dead or Stale Candidates

### 1) AIPS_Schedule_Entry appears test-only (no production call sites)
- DTO defined at [ai-post-scheduler/includes/class-aips-schedule-entry.php#L32](ai-post-scheduler/includes/class-aips-schedule-entry.php#L32)
- Factory usage appears in tests only (for example [ai-post-scheduler/tests/test-typed-dtos.php#L203](ai-post-scheduler/tests/test-typed-dtos.php#L203))
- No production usage found under includes/templates/bootstrap paths.

Risk: Low runtime risk, but removing would require deciding whether this DTO is intentionally future-facing.

### 2) Scheduler wrapper methods with no external call sites found
- Methods:
  - [ai-post-scheduler/includes/class-aips-scheduler.php#L158](ai-post-scheduler/includes/class-aips-scheduler.php#L158) get_all_schedules
  - [ai-post-scheduler/includes/class-aips-scheduler.php#L162](ai-post-scheduler/includes/class-aips-scheduler.php#L162) get_schedule
  - [ai-post-scheduler/includes/class-aips-scheduler.php#L386](ai-post-scheduler/includes/class-aips-scheduler.php#L386) calculate_next_run
- These look like pass-through wrappers to repository/interval calculator and had no direct callers found.

Risk: Medium. Could be used by third-party integrations/extensions, so deprecate before removal.

### 3) Injection helpers currently unused by internal code/tests
- Methods:
  - [ai-post-scheduler/includes/class-aips-scheduler.php#L135](ai-post-scheduler/includes/class-aips-scheduler.php#L135) set_processor
  - [ai-post-scheduler/includes/class-aips-schedule-processor.php#L110](ai-post-scheduler/includes/class-aips-schedule-processor.php#L110) set_runner
- No internal usages were found.

Risk: Medium-low. They may exist for future testing/integration flexibility.

### 4) Residual schedule-wizard wording/hash markers in admin.js (stale, not functional code)
- Hash marker still set/cleaned:
  - [ai-post-scheduler/assets/js/admin.js#L2810](ai-post-scheduler/assets/js/admin.js#L2810)
  - [ai-post-scheduler/assets/js/admin.js#L2931](ai-post-scheduler/assets/js/admin.js#L2931)
- Comment still references schedule wizard:
  - [ai-post-scheduler/assets/js/admin.js#L2943](ai-post-scheduler/assets/js/admin.js#L2943)

Risk: Very low. Primarily clarity/maintenance noise.

## No Action Taken
- Per request, this pass is read-only. No production or test files were modified in this audit.

## Suggested Next Cleanup PR (Optional)
1. Decide if AIPS_Schedule_Entry is an intentionally staged DTO; if not, remove with test updates.
2. Deprecate and then remove unused scheduler wrapper/injection methods after one release cycle.
3. Clean stale schedule-wizard comments/hash references in admin.js for consistency.
