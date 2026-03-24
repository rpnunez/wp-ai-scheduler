## 2024-05-18 - Extract AIPS_Admin_Menu from AIPS_Settings
**Context:** The AIPS_Settings class was acting as a God Object, handling both WordPress Settings API registration and admin menu/page rendering.
**Decision:** Extracted all admin menu registration and page rendering logic into a new, dedicated AIPS_Admin_Menu class to enforce the Single Responsibility Principle.
**Consequence:** AIPS_Settings is now strictly focused on plugin configuration. Two classes must be instantiated during admin initialization instead of one.
**Tests:** Added test file test-aips-admin-menu.php to ensure instantiation and hooks registration.
