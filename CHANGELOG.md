
### Changed
- Admin History: Replaced full page reload with AJAX table reload when retrying failed generations to improve user flow.

### Fixed
- 🛡️ **Security**: Fixed information leakage where internal `WP_Error` details were exposed directly to the client in AJAX endpoints within `AIPS_Internal_Links_Controller`. Internal errors are now properly logged and a generic message is returned to the user.
