# Phase 5 Removal Checklist (Non-Repository Legacy Consumers)

Last updated: 2026-06-06

## Scope

- Remove remaining non-repository legacy cache usage in:
  - `admin_bar`
  - system status cache rebuild tooling
- Preserve user-visible behavior and labels on the System Status page and admin toolbar.

## Prerequisites

- [x] Phase 1-4 repository migration completed.
- [x] Repository boundary lint is passing.
- [x] Phase 3 legacy callsite tracker exists.

## Implementation Tasks

### Admin Bar (`ai-post-scheduler/includes/class-aips-admin-bar.php`)

- [x] Remove `AIPS_Cache_Policy` usage.
- [x] Keep unread count cache key format stable (`aips_unread_count_{user_id}`).
- [x] Keep cache group `aips_admin_bar` and 1-minute TTL behavior.

### System Status (`ai-post-scheduler/includes/class-aips-system-status-controller.php`)

- [x] Remove `AIPS_Cache_Policy::get_subsystems()` usage.
- [x] Remove `AIPS_Cache_Invalidation_Bus::rebuild()` usage.
- [x] Add local cache rebuild subsystem map (`admin_bar`).
- [x] Rebuild cache via direct named-cache flush.

### System Status View (`ai-post-scheduler/includes/class-aips-system-status.php`, `ai-post-scheduler/templates/admin/system-status.php`)

- [x] Pass cache subsystem data from PHP controller layer to template.
- [x] Remove direct template dependency on `AIPS_Cache_Policy`.

## Validation

- [x] `php -l` on touched PHP files.
- [x] Repository boundary lint (`php tools/check-repository-boundary.php`).
- [ ] Manual System Status page check in wp-admin.
- [ ] Manual admin toolbar unread count check.

## Rollback Plan

1. Revert Phase 5 commit.
2. Re-run `php tools/check-repository-boundary.php`.
3. Confirm System Status cache rebuild and admin toolbar notification count behavior.

## Completion Criteria

- [x] No `AIPS_Cache_Policy` usage remains in admin bar and system status rendering/controller paths.
- [x] No `AIPS_Cache_Invalidation_Bus` usage remains in system status rebuild action.
- [ ] Manual UI verification completed.