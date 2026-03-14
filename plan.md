# Strategy
- **Hunter:** In `AIPS_System_Status::get_system_info()`, there is an error reported by phpstan: "Call to an undefined static method AIPS_Data_Management::get_instance()". I will fix this method by resolving how data management instance is created/retrieved. Also, another error: "Constant WP_CONTENT_DIR not found" which is simple to fix using `defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ...`
- **Atlas:** Refactor hardcoded `$wpdb` calls in `AIPS_Authors_Controller::ajax_delete_author()`, which does raw deletes rather than using repositories, and in `AIPS_Templates::get_all_pending_stats()` which does `$wpdb->get_results()`.

I will do both.
