# Phase 3 Legacy Cache Callsite Tracker

Last updated: 2026-06-06

## Objective

Track remaining legacy cache bus/policy call sites during the compatibility window while keeping repository usage at zero.

## Repository usage status

- Repository boundary lint (`php tools/check-repository-boundary.php`): **pass**
- Repository classes using `AIPS_Cache_Invalidation_Bus` or `AIPS_Cache_Policy`: **0**
- `config/repository-cache-legacy-baseline.txt` active entries: **0**

## Legacy callsites (non-repository compatibility)

| File | Symbol | Purpose | Status |
| --- | --- | --- | --- |
| `ai-post-scheduler/includes/class-aips-system-status-controller.php` | `AIPS_Cache_Invalidation_Bus::rebuild()` | Manual cache rebuild from System Status UI | Keep (compat) |
| `ai-post-scheduler/includes/class-aips-system-status-controller.php` | `AIPS_Cache_Policy::get_subsystems()` | List cache subsystems in System Status UI | Keep (compat) |
| `ai-post-scheduler/includes/class-aips-admin-bar.php` | `AIPS_Cache_Policy::key()` | Admin bar unread-count cache key | Keep (compat) |
| `ai-post-scheduler/includes/class-aips-admin-bar.php` | `AIPS_Cache_Policy::default_ttl()` | Admin bar unread-count cache TTL | Keep (compat) |

## Phase 3 actions completed

- Added deprecation annotations for repository-facing legacy entry points in:
  - `ai-post-scheduler/includes/class-aips-cache-invalidation-bus.php`
  - `ai-post-scheduler/includes/class-aips-cache-policy.php`
- Kept legacy classes available for non-repository consumers during compatibility window.
- Confirmed repository boundary guardrails still pass.

## Phase 4 progress

- Removed repository-specific legacy pathways from `AIPS_Cache_Policy`.
- `AIPS_Cache_Policy::get_subsystems()` now exposes only non-repository compatibility (`admin_bar`).
- Remaining legacy callsites are non-repository and tracked below until final class removal.

## Exit signal for Phase 4

Proceed to Phase 4 once:

1. System Status and Admin Bar are migrated off legacy policy/bus, or intentionally retained behind a final compatibility decision.
2. Deprecation timeline is documented in release notes/changelog.
3. Repository boundary lint remains passing in CI and local runs.