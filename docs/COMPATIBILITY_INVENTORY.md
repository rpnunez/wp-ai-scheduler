# Deprecated Adapters & Compatibility Inventory

This inventory documents deprecated adapters, shims, and legacy classes across the plugin codebase, tracking their runtime callers, supported contracts, owners, deprecation timeline, and target removal versions (per Issue #2263).

## Inventory Matrix

| Adapter / Class / Method | File Path | Runtime Callers | Supported Contract | Subsystem Owner | Deprecated In | Target Removal | Sunset / Migration Recommendation |
|---|---|---|---|---|---|---|---|
| `AIPS_Admin_Flow_Controller` | `includes/class-aips-admin-flow-controller.php` | None (covered by `Test_AIPS_Admin_Flow_Controller.php`) | Subclasses `AIPS_Campaigns_Controller`, exposes `render_page()` delegating to `render_wizard_page()`. | Campaigns / Admin IA | 3.8.0 | 4.0.0 | Deprecated in 3.8.0 with `_deprecated_constructor()` notice. All campaign routing now uses `AIPS_Campaigns_Controller` directly. Safely remove in 4.0.0 release. |
| `AIPS_Cache_Invalidation_Bus` | `includes/class-aips-cache-invalidation-bus.php` | None in core codebase. | Legacy pub/sub invalidation bus for repository cache keys. | Cache Infrastructure | 2.6.0 | 4.0.0 | Replaced by `AIPS_Cacheable_Repository::invalidate_cache_domain()`. Remove in 4.0.0. |
| `AIPS_Cache_Policy` (legacy constants) | `includes/class-aips-cache-policy.php` | None in core codebase; retained for external backward compatibility. | Constants for cache TTLs and tag names (`DEFAULT_TTL`, `TAG_*`). | Cache Infrastructure | 2.6.0 | 4.0.0 | Repository caching now defines TTL and domain keys directly on `AIPS_Cacheable_Repository`. Remove in 4.0.0. |
| `AIPS_History_Query_Service` | `includes/class-aips-history-query-service.php` | None in core codebase (covered by unit test). | Thin delegation facade wrapping query methods on `AIPS_History_Repository`. | History Subsystem | 2.4.0 | 4.0.0 | All history queries now resolve through `AIPS_History_Repository_Interface` via `AIPS_Container`. Remove in 4.0.0. |
| `AIPS_Post_Creator` | `includes/class-aips-post-creator.php` | None in core codebase. | Legacy post creation helper with `_deprecated_class()` warning. | Content Generation | 1.7.0 | 4.0.0 | Fully replaced by `AIPS_Post_Manager`. Remove in 4.0.0. |
| `AIPS_Upgrades` | `includes/class-aips-upgrades.php` | None in core codebase. | Legacy database upgrade entry point. | Database / Migrations | 2.5.1 | 4.0.0 | Fully replaced by `AIPS_DB_Migrations`. Remove in 4.0.0. |
| `AIPS_Prompt_Builder::build_prompt()` (excerpt prompt) | `includes/class-aips-prompt-builder.php` | None in core codebase. | Legacy excerpt prompt construction method. | Prompt Builders | 2.0.0 | 4.0.0 | Fully replaced by `AIPS_Prompt_Builder::build_excerpt_prompt()`. Remove in 4.0.0. |
| `AIPS_DB_Migrations::migrate_history_table()` | `includes/class-aips-db-migrations.php` | None in runtime flow. | Historical migration step for pre-2.5.0 database schema. | Database / Migrations | 2.5.0 | 4.0.0 | All sites are on >= 2.5.0 schema. Clean up in 4.0.0 migration cleanup pass. |

---

## Removal & Deprecation Guidelines

1. **Deprecation Notice Requirement**:
   - Any class or adapter marked for deprecation must emit `_deprecated_class()` or `_deprecated_constructor()` when accessed or instantiated during execution.
   - Deprecated methods must emit `_deprecated_function()`.
2. **Container Construction Guardrail**:
   - No deprecated classes should be bound into `AIPS_Container` or instantiated in core application bootstrapping.
3. **Target Removal Window**:
   - Items scheduled for `4.0.0` will be cleanly purged during the 4.0 major release refactoring branch.
