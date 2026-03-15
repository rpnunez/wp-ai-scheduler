## 2025-03-15 - Extract Admin Menu from Settings God Object
**Context:** The `AIPS_Settings` class was behaving as a God Object, handling not just settings registration, but also admin menu page registrations and their corresponding render methods.
**Decision:** Create a new `AIPS_Admin_Menu` class to strictly handle the admin menu registration (`add_menu_page`, `add_submenu_page`) and routing (`render_*_page` methods). Remove this logic from `AIPS_Settings`.
**Consequence:** Better separation of concerns. `AIPS_Settings` now only focuses on registering options and settings sections/fields, while `AIPS_Admin_Menu` focuses on the admin navigation.
**Tests:** Verified tests running correctly after test case updates related to `current_user_can` and nonce overrides.