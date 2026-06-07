# Dead Code Inventory and Removal Plan

## Scope
- `ai-post-scheduler/assets/js/*`
- `ai-post-scheduler/assets/css/*`
- `ai-post-scheduler/includes/*`
- `ai-post-scheduler/templates/*`

This plan includes only high-confidence dead runtime code. Test-only references are out of scope for deletion in this phase.

## Evidence Summary

### Confirmed dead/stale runtime code
1. Legacy schedule handlers in `assets/js/admin.js` are bound to selectors not rendered by the current schedule UI (`templates/admin/schedule.php`).
2. Legacy refresh target in `assets/js/admin.js` references `.aips-schedule-table`, but the current template uses `.aips-unified-schedule-table`.
3. Stale schedule table styles in `assets/css/admin.css` target `.aips-schedule-table` selectors that are not rendered by current templates.
4. Runtime-unused class candidate in `includes/class-aips-error-handler.php` has no references in `includes/*.php`.

### Explicitly excluded
- `includes/class-aips-admin-flow-controller.php` (runtime-unused but test-referenced).

## Inventory (High Confidence)

| Candidate | File | Evidence | Confidence | Phase |
|---|---|---|---|---|
| Legacy schedule event bindings/selectors | `assets/js/admin.js` | Selectors absent from current templates | High | 1 |
| Legacy schedule methods tied to removed selectors | `assets/js/admin.js` | No reachable DOM trigger paths | High | 1 |
| Stale schedule refresh selector | `assets/js/admin.js` | Uses `.aips-schedule-table` (not rendered) | High | 1 |
| Stale schedule table CSS | `assets/css/admin.css` | `.aips-schedule-table` not rendered | High | 1 |
| Runtime-unused error helper class | `includes/class-aips-error-handler.php` | No include-runtime references | High | 3 |

## Phased Approach

### Phase 1: Frontend dead path removal
- Remove legacy schedule bindings and methods from `assets/js/admin.js` where selectors no longer exist.
- Update stale table refresh selector from `.aips-schedule-table` to `.aips-unified-schedule-table`.
- Remove stale `.aips-schedule-table` CSS in `assets/css/admin.css`.

Validation:
- Re-scan for removed selectors across templates.
- Re-scan for removed method names and stale CSS selectors.

### Phase 2: Server endpoint cleanup
- Remove legacy AJAX actions from `includes/class-aips-ajax-registry.php` only if no JS callers remain.
- Remove corresponding legacy methods from `includes/class-aips-schedule-controller.php`.

Validation:
- Verify all retained AJAX actions still have call sites.
- Run focused schedule/AJAX registry tests.

### Phase 3: Runtime-unused class cleanup
- Remove `includes/class-aips-error-handler.php` if still unreferenced in runtime includes.

Validation:
- Full-text check over `includes/*.php` and plugin bootstrap paths.
- Run targeted tests.

## Safety Gates
1. Keep changes small and reversible per phase.
2. Validate references after each phase before proceeding.
3. Do not remove test-only symbols in this initiative unless explicitly approved.

## Rollback Plan
- Revert phase commit(s) if any schedule page behavior regresses.
- Restore removed JS/CSS blocks first, then endpoint mappings if needed.
