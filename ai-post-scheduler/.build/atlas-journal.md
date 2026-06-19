
## 2024-05-24 - Extract Asset Enqueue Routing
**Context:** The `enqueue_admin_assets` method in `AIPS_Admin_Assets` was a God Method with over 120 lines of `if` statements mapping admin page slugs and hooks to specific asset enqueue functions. This created high coupling and poor developer experience as adding any new page required modifying this central method.
**Decision:** Refactored to a routing pattern. Introduced `get_asset_routes()` which returns a declarative map (associative array) tying page constants to their respective methods. The `enqueue_admin_assets()` method was simplified to traverse this map.
**Consequence:** The method is now OCP-compliant (Open-Closed Principle). We traded explicit conditional structures for dynamic method invocation via routing maps. Dynamic tabs (e.g. diagnostics) required smaller fallback arrays.
**Tests:** Added `Test_AIPS_Admin_Assets.php` asserting that the `get_asset_routes()` method correctly returns a well-formed mapping dictionary, and tests ensuring fallback functionality operates gracefully without fatal errors.
