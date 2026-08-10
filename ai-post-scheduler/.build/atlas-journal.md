## 2025-01-21 - [Refactored AIPS_Admin_Assets asset routing]
**Context:** `AIPS_Admin_Assets::enqueue_admin_assets` was a massive 110+ line function, which acted as a God method containing many unstructured `if` conditions to decide which assets to enqueue.
**Decision:** Applied declarative routing using `get_asset_routes()` and `dispatch_asset_routes()`.
**Consequence:** Better readability, easier maintainability. Slightly more overhead with the use of Reflection.
**Tests:** Created `Test_AIPS_Admin_Assets.php` to verify no regressions in admin functionality.
