
### Fixed
- Tests: Mocked WP core functions in bootstrap.php (`esc_sql`, `wp_date`, `wp_timezone`) to fix fatal errors in PHPUnit tests running in limited mode.

### Changed
- Admin History: Replaced full page reload with AJAX table reload when retrying failed generations to improve user flow.
- Refactored multiple admin UI actions to update DOM tables dynamically without a full page reload for a smoother user experience.
