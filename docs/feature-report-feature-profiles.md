## Feature Profiles

### Ai Edit Controller
* **Summary**: AI Edit Controller
* **File**: `ai-post-scheduler/includes/class-aips-ai-edit-controller.php`
* **Class**: `AIPS_AI_Edit_Controller`
* **Missing Functionality**: No input validation methods visible
* **Recommended Improvements**: 
    1. [WARNING] Registers 6 AJAX hook(s) in constructor instead of via AIPS_Ajax_Registry: aips_get_post_components, aips_regenerate_component, aips_regenerate_all_components, aips_save_post_components, aips_get_component_revisions
    2. [INFO] Uses raw error_log() 2 time(s) — prefer AIPS_Logger for structured logging
    3. Consider refactoring — class has 654 lines (may violate SRP)
    4. Document custom hooks in HOOKS.md for third-party developers

---

### Ai Service
* **Summary**: AI Service Layer
* **File**: `ai-post-scheduler/includes/class-aips-ai-service.php`
* **Class**: `AIPS_AI_Service`
* **Implements**: `AIPS_AI_Service_Interface`
* **Missing Functionality**: No input validation methods visible
* **Recommended Improvements**: 
    1. Consider refactoring — class has 1042 lines (may violate SRP)
    2. Consider using AIPS_Cache for caching expensive operations
    3. Document custom hooks in HOOKS.md for third-party developers

---

### Admin Assets
* **Summary**: Class AIPS_Admin_Assets
* **File**: `ai-post-scheduler/includes/class-aips-admin-assets.php`
* **Class**: `AIPS_Admin_Assets`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Consider refactoring — class has 1121 lines (may violate SRP)
    2. Document custom hooks in HOOKS.md for third-party developers

---

### Admin Bar
* **Summary**: Class AIPS_Admin_Bar
* **File**: `ai-post-scheduler/includes/class-aips-admin-bar.php`
* **Class**: `AIPS_Admin_Bar`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [WARNING] Registers 2 AJAX hook(s) in constructor instead of via AIPS_Ajax_Registry: aips_mark_notification_read, aips_mark_all_notifications_read

---

### Admin Menu
* **Summary**: Class AIPS_Admin_Menu
* **File**: `ai-post-scheduler/includes/class-aips-admin-menu.php`
* **Class**: `AIPS_Admin_Menu`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Consider refactoring — class has 543 lines (may violate SRP)
    2. High method count (24+ methods) — consider splitting responsibilities
    3. High coupling — depends on 13 classes

---

### Admin Menu Helper
* **Summary**: Admin Menu Helper
* **File**: `ai-post-scheduler/includes/class-aips-admin-menu-helper.php`
* **Class**: `AIPS_Admin_Menu_Helper`
* **Missing Functionality**: None identified

---

### Ajax Registry
* **Summary**: AJAX Registry
* **File**: `ai-post-scheduler/includes/class-aips-ajax-registry.php`
* **Class**: `AIPS_Ajax_Registry`
* **Missing Functionality**: None identified

---

### Ajax Response
* **Summary**: AJAX Response
* **File**: `ai-post-scheduler/includes/class-aips-ajax-response.php`
* **Class**: `AIPS_Ajax_Response`
* **Missing Functionality**: None identified

---

### Article Structure Manager
* **Summary**: Article Structure Manager
* **File**: `ai-post-scheduler/includes/class-aips-article-structure-manager.php`
* **Class**: `AIPS_Article_Structure_Manager`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Document custom hooks in HOOKS.md for third-party developers

---

### Article Structure Repository
* **Summary**: Article Structure Repository
* **File**: `ai-post-scheduler/includes/class-aips-article-structure-repository.php`
* **Class**: `AIPS_Article_Structure_Repository`
* **Missing Functionality**: Does not implement an interface — consider adding a contract

---

### Author Post Generator
* **Summary**: Author Post Generator
* **File**: `ai-post-scheduler/includes/class-aips-author-post-generator.php`
* **Class**: `AIPS_Author_Post_Generator`
* **Implements**: `AIPS_Cron_Generation_Handler`
* **Missing Functionality**: 
    * No filter hooks for customizing generation output
    * No dedicated error handling methods visible
* **Recommended Improvements**: 
    1. [INFO] Directly instantiates AIPS_History_Service, AIPS_Logger without using AIPS_Container — consider resolving from the container
    2. High coupling — depends on 13 classes
    3. Document custom hooks in HOOKS.md for third-party developers

---

### Author Suggestions Service
* **Summary**: Author Suggestions Service
* **File**: `ai-post-scheduler/includes/class-aips-author-suggestions-service.php`
* **Class**: `AIPS_Author_Suggestions_Service`
* **Missing Functionality**: 
    * Does not implement an interface — consider adding a contract
    * No input validation methods visible
* **Recommended Improvements**: 
    1. High coupling — depends on 9 classes
    2. Consider using AIPS_Cache for caching expensive operations

---

### Author Topic Logs Repository
* **Summary**: Author Topic Logs Repository
* **File**: `ai-post-scheduler/includes/class-aips-author-topic-logs-repository.php`
* **Class**: `AIPS_Author_Topic_Logs_Repository`
* **Missing Functionality**:
    * Missing save/update methods for data persistence
    * Does not implement an interface — consider adding a contract

---

### Author Topics Controller
* **Summary**: Author Topics Controller
* **File**: `ai-post-scheduler/includes/class-aips-author-topics-controller.php`
* **Class**: `AIPS_Author_Topics_Controller`
* **Missing Functionality**: No input validation methods visible
* **Recommended Improvements**: 
    1. [WARNING] Registers 20 AJAX hook(s) in constructor instead of via AIPS_Ajax_Registry: aips_approve_topic, aips_reject_topic, aips_edit_topic, aips_delete_topic, aips_generate_post_from_topic
    2. Consider refactoring — class has 1085 lines (may violate SRP)
    3. High method count (22+ methods) — consider splitting responsibilities
    4. High coupling — depends on 14 classes

---

### Author Topics Generator
* **Summary**: Author Topics Generator
* **File**: `ai-post-scheduler/includes/class-aips-author-topics-generator.php`
* **Class**: `AIPS_Author_Topics_Generator`
* **Missing Functionality**: 
    * No filter hooks for customizing generation output
    * No dedicated error handling methods visible
* **Recommended Improvements**: 
    1. High coupling — depends on 12 classes

---

### Author Topics Repository
* **Summary**: Author Topics Repository
* **File**: `ai-post-scheduler/includes/class-aips-author-topics-repository.php`
* **Class**: `AIPS_Author_Topics_Repository`
* **Missing Functionality**: Does not implement an interface — consider adding a contract

---

### Author Topics Scheduler
* **Summary**: Author Topics Scheduler
* **File**: `ai-post-scheduler/includes/class-aips-author-topics-scheduler.php`
* **Class**: `AIPS_Author_Topics_Scheduler`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [INFO] Directly instantiates AIPS_History_Service, AIPS_Logger without using AIPS_Container — consider resolving from the container

---

### Authors Controller
* **Summary**: Authors Controller
* **File**: `ai-post-scheduler/includes/class-aips-authors-controller.php`
* **Class**: `AIPS_Authors_Controller`
* **Missing Functionality**: No input validation methods visible
* **Recommended Improvements**: 
    1. [WARNING] Registers 9 AJAX hook(s) in constructor instead of via AIPS_Ajax_Registry: aips_save_author, aips_delete_author, aips_get_author, aips_get_author_topics, aips_get_author_posts
    2. Consider refactoring — class has 558 lines (may violate SRP)
    3. Consider resolving dependencies from AIPS_Container instead of direct instantiation
    4. Document custom hooks in HOOKS.md for third-party developers

---

### Authors Repository
* **Summary**: Authors Repository
* **File**: `ai-post-scheduler/includes/class-aips-authors-repository.php`
* **Class**: `AIPS_Authors_Repository`
* **Missing Functionality**: Does not implement an interface — consider adding a contract

---

### Autoloader
* **Summary**: No description available
* **File**: `ai-post-scheduler/includes/class-aips-autoloader.php`
* **Class**: `AIPS_Autoloader`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Add comprehensive class-level PHPDoc documentation

---

### Bulk Generation Result
* **Summary**: Bulk Generator Service
* **File**: `ai-post-scheduler/includes/class-aips-bulk-generator-service.php`
* **Class**: `AIPS_Bulk_Generation_Result`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. High coupling — depends on 9 classes

---

### Cache
* **Summary**: Class AIPS_Cache
* **File**: `ai-post-scheduler/includes/class-aips-cache.php`
* **Class**: `AIPS_Cache`
* **Missing Functionality**: None identified

---

### Cache Array Driver
* **Summary**: Class AIPS_Cache_Array_Driver
* **File**: `ai-post-scheduler/includes/class-aips-cache-array-driver.php`
* **Class**: `AIPS_Cache_Array_Driver`
* **Implements**: `AIPS_Cache_Driver`
* **Missing Functionality**: None identified

---

### Cache Db Driver
* **Summary**: Class AIPS_Cache_Db_Driver
* **File**: `ai-post-scheduler/includes/class-aips-cache-db-driver.php`
* **Class**: `AIPS_Cache_Db_Driver`
* **Implements**: `AIPS_Cache_Driver`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [WARNING] Uses $wpdb directly — SQL should be in a Repository class
    2. Consider using Repository pattern for database access instead of direct $wpdb

---

### Cache Factory
* **Summary**: Class AIPS_Cache_Factory
* **File**: `ai-post-scheduler/includes/class-aips-cache-factory.php`
* **Class**: `AIPS_Cache_Factory`
* **Missing Functionality**: None identified

---

### Cache Redis Driver
* **Summary**: Class AIPS_Cache_Redis_Driver
* **File**: `ai-post-scheduler/includes/class-aips-cache-redis-driver.php`
* **Class**: `AIPS_Cache_Redis_Driver`
* **Implements**: `AIPS_Cache_Driver`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [INFO] Uses raw error_log() 1 time(s) — prefer AIPS_Logger for structured logging

---

### Cache Session Driver
* **Summary**: Class AIPS_Cache_Session_Driver
* **File**: `ai-post-scheduler/includes/class-aips-cache-session-driver.php`
* **Class**: `AIPS_Cache_Session_Driver`
* **Implements**: `AIPS_Cache_Driver`
* **Missing Functionality**: None identified

---

### Cache Wp Object Cache Driver
* **Summary**: Class AIPS_Cache_Wp_Object_Cache_Driver
* **File**: `ai-post-scheduler/includes/class-aips-cache-wp-object-cache-driver.php`
* **Class**: `AIPS_Cache_Wp_Object_Cache_Driver`
* **Implements**: `AIPS_Cache_Driver`
* **Missing Functionality**: None identified

---

### Calendar Controller
* **Summary**: Class AIPS_Calendar_Controller
* **File**: `ai-post-scheduler/includes/class-aips-calendar-controller.php`
* **Class**: `AIPS_Calendar_Controller`
* **Missing Functionality**: No input validation methods visible
* **Recommended Improvements**: 
    1. [WARNING] Registers 1 AJAX hook(s) in constructor instead of via AIPS_Ajax_Registry: aips_get_calendar_events
    2. Consider resolving dependencies from AIPS_Container instead of direct instantiation

---

### Component Regeneration Service
* **Summary**: Component Regeneration Service
* **File**: `ai-post-scheduler/includes/class-aips-component-regeneration-service.php`
* **Class**: `AIPS_Component_Regeneration_Service`
* **Missing Functionality**: 
    * No AIPS_Logger or AIPS_History_Service usage for observability
    * Does not implement an interface — consider adding a contract
    * No input validation methods visible
* **Recommended Improvements**: 
    1. [INFO] Directly instantiates AIPS_AI_Service without using AIPS_Container — consider resolving from the container
    2. Consider refactoring — class has 548 lines (may violate SRP)
    3. High coupling — depends on 12 classes
    4. Consider using AIPS_Cache for caching expensive operations

---

### Config
* **Summary**: Configuration Manager
* **File**: `ai-post-scheduler/includes/class-aips-config.php`
* **Class**: `AIPS_Config`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Consider refactoring — class has 703 lines (may violate SRP)
    2. High method count (32+ methods) — consider splitting responsibilities

---

### Container
* **Summary**: Dependency Injection Container
* **File**: `ai-post-scheduler/includes/class-aips-container.php`
* **Class**: `AIPS_Container`
* **Missing Functionality**: None identified

---

### Content Auditor
* **Summary**: Content Auditor Service
* **File**: `ai-post-scheduler/includes/class-aips-content-auditor.php`
* **Class**: `AIPS_Content_Auditor`
* **Missing Functionality**: None identified

---

### Correlation Id
* **Summary**: Correlation ID Manager
* **File**: `ai-post-scheduler/includes/class-aips-correlation-id.php`
* **Class**: `AIPS_Correlation_ID`
* **Missing Functionality**: None identified

---

### Db Manager
* **Summary**: No description available
* **File**: `ai-post-scheduler/includes/class-aips-db-manager.php`
* **Class**: `AIPS_DB_Manager`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [WARNING] Registers 4 AJAX hook(s) in constructor instead of via AIPS_Ajax_Registry: aips_repair_db, aips_reinstall_db, aips_wipe_db, aips_flush_cron_events
    2. Consider refactoring — class has 904 lines (may violate SRP)
    3. Add comprehensive class-level PHPDoc documentation

---

### Dashboard Controller
* **Summary**: AIPS_Dashboard_Controller
* **File**: `ai-post-scheduler/includes/class-aips-dashboard-controller.php`
* **Class**: `AIPS_Dashboard_Controller`
* **Missing Functionality**: 
    * No AJAX handlers or action hooks registered
    * No input validation methods visible
* **Recommended Improvements**: 
    1. Consider resolving dependencies from AIPS_Container instead of direct instantiation

---

### Data Management
* **Summary**: Data Management Controller
* **File**: `ai-post-scheduler/includes/class-aips-data-management.php`
* **Class**: `AIPS_Data_Management`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [WARNING] Registers 2 AJAX hook(s) in constructor instead of via AIPS_Ajax_Registry: aips_export_data, aips_import_data
    2. [INFO] Uses raw error_log() 1 time(s) — prefer AIPS_Logger for structured logging

---

### Data Management Export
* **Summary**: No description available
* **File**: `ai-post-scheduler/includes/class-aips-data-management-export.php`
* **Class**: `AIPS_Data_Management_Export`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Add comprehensive class-level PHPDoc documentation

---

### Data Management Export Json
* **Summary**: JSON export implementation (placeholder for future)
* **File**: `ai-post-scheduler/includes/class-aips-data-management-export-json.php`
* **Class**: `AIPS_Data_Management_Export_JSON`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [WARNING] Uses $wpdb directly — SQL should be in a Repository class
    2. Consider using Repository pattern for database access instead of direct $wpdb

---

### Data Management Export Mysql
* **Summary**: MySQL dump export implementation
* **File**: `ai-post-scheduler/includes/class-aips-data-management-export-mysql.php`
* **Class**: `AIPS_Data_Management_Export_MySQL`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [WARNING] Uses $wpdb directly — SQL should be in a Repository class
    2. Consider using Repository pattern for database access instead of direct $wpdb

---

### Data Management Import
* **Summary**: No description available
* **File**: `ai-post-scheduler/includes/class-aips-data-management-import.php`
* **Class**: `AIPS_Data_Management_Import`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Add comprehensive class-level PHPDoc documentation

---

### Data Management Import Json
* **Summary**: JSON import implementation (placeholder for future)
* **File**: `ai-post-scheduler/includes/class-aips-data-management-import-json.php`
* **Class**: `AIPS_Data_Management_Import_JSON`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [WARNING] Uses $wpdb directly — SQL should be in a Repository class
    2. Consider using Repository pattern for database access instead of direct $wpdb

---

### Data Management Import Mysql
* **Summary**: MySQL dump import implementation
* **File**: `ai-post-scheduler/includes/class-aips-data-management-import-mysql.php`
* **Class**: `AIPS_Data_Management_Import_MySQL`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [WARNING] Uses $wpdb directly — SQL should be in a Repository class
    2. Consider using Repository pattern for database access instead of direct $wpdb

---

### Dev Tools
* **Summary**: Class AIPS_Dev_Tools
* **File**: `ai-post-scheduler/includes/class-aips-dev-tools.php`
* **Class**: `AIPS_Dev_Tools`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [WARNING] Registers 1 AJAX hook(s) in constructor instead of via AIPS_Ajax_Registry: aips_generate_scaffold
    2. [INFO] Directly instantiates AIPS_AI_Service without using AIPS_Container — consider resolving from the container

---

### Embeddings Cron
* **Summary**: Embeddings Cron Handler
* **File**: `ai-post-scheduler/includes/class-aips-embeddings-cron.php`
* **Class**: `AIPS_Embeddings_Cron`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Document custom hooks in HOOKS.md for third-party developers

---

### Embeddings Service
* **Summary**: Embeddings Service
* **File**: `ai-post-scheduler/includes/class-aips-embeddings-service.php`
* **Class**: `AIPS_Embeddings_Service`
* **Missing Functionality**: 
    * Does not implement an interface — consider adding a contract
    * No input validation methods visible
* **Recommended Improvements**: 
    1. Consider using AIPS_Cache for caching expensive operations

---

### Error Handler
* **Summary**: AIPS_Error_Handler
* **File**: `ai-post-scheduler/includes/class-aips-error-handler.php`
* **Class**: `AIPS_Error_Handler`
* **Missing Functionality**: None identified

---

### Feedback Repository
* **Summary**: Feedback Repository
* **File**: `ai-post-scheduler/includes/class-aips-feedback-repository.php`
* **Class**: `AIPS_Feedback_Repository`
* **Missing Functionality**:
    * Missing save/update methods for data persistence
    * Does not implement an interface — consider adding a contract

---

### Generated Posts Controller
* **Summary**: Generated Posts Controller
* **File**: `ai-post-scheduler/includes/class-aips-generated-posts-controller.php`
* **Class**: `AIPS_Generated_Posts_Controller`
* **Missing Functionality**: No input validation methods visible
* **Recommended Improvements**: 
    1. [WARNING] Registers 3 AJAX hook(s) in constructor instead of via AIPS_Ajax_Registry: aips_get_post_session, aips_get_session_json, aips_download_session_json
    2. Consider refactoring — class has 536 lines (may violate SRP)
    3. High coupling — depends on 10 classes
    4. Consider resolving dependencies from AIPS_Container instead of direct instantiation

---

### Generation Context Factory
* **Summary**: Generation Context Factory
* **File**: `ai-post-scheduler/includes/class-aips-generation-context-factory.php`
* **Class**: `AIPS_Generation_Context_Factory`
* **Missing Functionality**: None identified

---

### Generation Execution Runner
* **Summary**: No description available
* **File**: `ai-post-scheduler/includes/class-aips-generation-execution-runner.php`
* **Class**: `AIPS_Generation_Execution_Runner`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Add comprehensive class-level PHPDoc documentation

---

### Generation Logger
* **Summary**: AIPS_Generation_Logger
* **File**: `ai-post-scheduler/includes/class-aips-generation-logger.php`
* **Class**: `AIPS_Generation_Logger`
* **Missing Functionality**: None identified

---

### Generation Result
* **Summary**: Generation Result DTO
* **File**: `ai-post-scheduler/includes/class-aips-generation-result.php`
* **Class**: `AIPS_Generation_Result`
* **Missing Functionality**: None identified

---

### Generation Session
* **Summary**: Generation Session Tracker
* **File**: `ai-post-scheduler/includes/class-aips-generation-session.php`
* **Class**: `AIPS_Generation_Session`
* **Missing Functionality**: None identified

---

### Generator
* **Summary**: AIPS_Generator
* **File**: `ai-post-scheduler/includes/class-aips-generator.php`
* **Class**: `AIPS_Generator`
* **Missing Functionality**: 
    * No filter hooks for customizing generation output
    * No dedicated error handling methods visible
* **Recommended Improvements**: 
    1. Consider refactoring — class has 1154 lines (may violate SRP)
    2. High coupling — depends on 19 classes
    3. Document custom hooks in HOOKS.md for third-party developers

---

### History
* **Summary**: Handles history management for AI post generation runs.
* **File**: `ai-post-scheduler/includes/class-aips-history.php`
* **Class**: `AIPS_History`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [WARNING] Registers 7 AJAX hook(s) in constructor instead of via AIPS_Ajax_Registry: aips_bulk_delete_history, aips_clear_history, aips_export_history, aips_get_history_details, aips_get_history_logs

---

### History Container
* **Summary**: History Container
* **File**: `ai-post-scheduler/includes/class-aips-history-container.php`
* **Class**: `AIPS_History_Container`
* **Missing Functionality**: None identified

---

### History Repository
* **Summary**: History Repository
* **File**: `ai-post-scheduler/includes/class-aips-history-repository.php`
* **Class**: `AIPS_History_Repository`
* **Implements**: `AIPS_History_Repository_Interface`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Consider refactoring — class has 1172 lines (may violate SRP)
    2. High method count (26+ methods) — consider splitting responsibilities

---

### History Service
* **Summary**: History Service
* **File**: `ai-post-scheduler/includes/class-aips-history-service.php`
* **Class**: `AIPS_History_Service`
* **Implements**: `AIPS_History_Service_Interface`
* **Missing Functionality**: No input validation methods visible
* **Recommended Improvements**: 
    1. Consider using AIPS_Cache for caching expensive operations

---

### History Type
* **Summary**: History Type Constants
* **File**: `ai-post-scheduler/includes/class-aips-history-type.php`
* **Class**: `AIPS_History_Type`
* **Missing Functionality**: None identified

---

### Image Service
* **Summary**: Image Service
* **File**: `ai-post-scheduler/includes/class-aips-image-service.php`
* **Class**: `AIPS_Image_Service`
* **Missing Functionality**: Does not implement an interface — consider adding a contract
* **Recommended Improvements**:
    1. Consider using AIPS_Cache for caching expensive operations

---

### Internal Link Inserter Service
* **Summary**: Internal Link Inserter Service
* **File**: `ai-post-scheduler/includes/class-aips-internal-link-inserter-service.php`
* **Class**: `AIPS_Internal_Link_Inserter_Service`
* **Missing Functionality**:
    * Does not implement an interface — consider adding a contract
    * No input validation methods visible
* **Recommended Improvements**:
    1. [INFO] Directly instantiates AIPS_AI_Service, AIPS_Logger without using AIPS_Container — consider resolving from the container
    2. Consider refactoring — class has 602 lines (may violate SRP)
    3. Consider using AIPS_Cache for caching expensive operations

---

### Internal Links Controller
* **Summary**: Internal Links Controller
* **File**: `ai-post-scheduler/includes/class-aips-internal-links-controller.php`
* **Class**: `AIPS_Internal_Links_Controller`
* **Missing Functionality**: No input validation methods visible
* **Recommended Improvements**:
    1. [WARNING] Registers 13 AJAX hook(s) in constructor instead of via AIPS_Ajax_Registry: aips_internal_links_get_suggestions, aips_internal_links_generate_suggestions, aips_internal_links_update_status, aips_internal_links_update_anchor, aips_internal_links_delete
    2. [INFO] Uses raw wp_send_json*() 53 time(s) — prefer AIPS_Ajax_Response::success()/error()
    3. [INFO] Directly instantiates AIPS_Logger without using AIPS_Container — consider resolving from the container
    4. Consider refactoring — class has 706 lines (may violate SRP)
    5. Consider resolving dependencies from AIPS_Container instead of direct instantiation

---

### Internal Links Repository
* **Summary**: Internal Links Repository
* **File**: `ai-post-scheduler/includes/class-aips-internal-links-repository.php`
* **Class**: `AIPS_Internal_Links_Repository`
* **Missing Functionality**: Does not implement an interface — consider adding a contract

---

### Internal Links Service
* **Summary**: Internal Links Service
* **File**: `ai-post-scheduler/includes/class-aips-internal-links-service.php`
* **Class**: `AIPS_Internal_Links_Service`
* **Missing Functionality**:
    * Does not implement an interface — consider adding a contract
    * No input validation methods visible
* **Recommended Improvements**: 
    1. [INFO] Directly instantiates AIPS_Logger without using AIPS_Container — consider resolving from the container
    2. Consider using AIPS_Cache for caching expensive operations

---

### Interval Calculator
* **Summary**: Interval Calculator Service
* **File**: `ai-post-scheduler/includes/class-aips-interval-calculator.php`
* **Class**: `AIPS_Interval_Calculator`
* **Missing Functionality**: None identified

---

### Logger
* **Summary**: No description available
* **File**: `ai-post-scheduler/includes/class-aips-logger.php`
* **Class**: `AIPS_Logger`
* **Implements**: `AIPS_Logger_Interface`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Add comprehensive class-level PHPDoc documentation

---

### Markdown Parser
* **Summary**: Markdown Parser Utility.
* **File**: `ai-post-scheduler/includes/class-aips-markdown-parser.php`
* **Class**: `AIPS_Markdown_Parser`
* **Missing Functionality**: None identified

---

### Metrics Repository
* **Summary**: Metrics Repository
* **File**: `ai-post-scheduler/includes/class-aips-metrics-repository.php`
* **Class**: `AIPS_Metrics_Repository`
* **Missing Functionality**:
    * Missing save/update methods for data persistence
    * Does not implement an interface — consider adding a contract
* **Recommended Improvements**: 
    1. [INFO] Directly instantiates AIPS_Resilience_Service without using AIPS_Container — consider resolving from the container
    2. Consider refactoring — class has 698 lines (may violate SRP)

---

### Notification Registry
* **Summary**: Notification Type Registry
* **File**: `ai-post-scheduler/includes/class-aips-notification-registry.php`
* **Class**: `AIPS_Notification_Registry`
* **Missing Functionality**: None identified

---

### Notification Senders
* **Summary**: Notification Senders
* **File**: `ai-post-scheduler/includes/class-aips-notification-senders.php`
* **Class**: `AIPS_Notification_Senders`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Consider refactoring — class has 737 lines (may violate SRP)
    2. High method count (22+ methods) — consider splitting responsibilities

---

### Notification Template
* **Summary**: Notification Template Value Object
* **File**: `ai-post-scheduler/includes/class-aips-notification-template.php`
* **Class**: `AIPS_Notification_Template`
* **Missing Functionality**: None identified

---

### Notification Templates
* **Summary**: Notification Templates Registry
* **File**: `ai-post-scheduler/includes/class-aips-notification-templates.php`
* **Class**: `AIPS_Notification_Templates`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Document custom hooks in HOOKS.md for third-party developers

---

### Notifications
* **Summary**: Central Notifications Service
* **File**: `ai-post-scheduler/includes/class-aips-notifications.php`
* **Class**: `AIPS_Notifications`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Consider refactoring — class has 750 lines (may violate SRP)
    2. High method count (30+ methods) — consider splitting responsibilities
    3. High coupling — depends on 11 classes

---

### Notifications Event Handler
* **Summary**: Notifications Event Handler
* **File**: `ai-post-scheduler/includes/class-aips-notifications-event-handler.php`
* **Class**: `AIPS_Notifications_Event_Handler`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Consider refactoring — class has 697 lines (may violate SRP)
    2. Document custom hooks in HOOKS.md for third-party developers

---

### Notifications Repository
* **Summary**: Class AIPS_Notifications_Repository
* **File**: `ai-post-scheduler/includes/class-aips-notifications-repository.php`
* **Class**: `AIPS_Notifications_Repository`
* **Implements**: `AIPS_Notifications_Repository_Interface`
* **Missing Functionality**: Missing save/update methods for data persistence

---

### Onboarding Wizard
* **Summary**: No description available
* **File**: `ai-post-scheduler/includes/class-aips-onboarding-wizard.php`
* **Class**: `AIPS_Onboarding_Wizard`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [WARNING] Registers 8 AJAX hook(s) in constructor instead of via AIPS_Ajax_Registry: aips_onboarding_save_strategy, aips_onboarding_create_author, aips_onboarding_create_template, aips_onboarding_generate_topics, aips_onboarding_generate_post
    2. High coupling — depends on 9 classes
    3. Document custom hooks in HOOKS.md for third-party developers
    4. Add comprehensive class-level PHPDoc documentation

---

### Partial Generation State Reconciler
* **Summary**: Partial Generation State Reconciler
* **File**: `ai-post-scheduler/includes/class-aips-partial-generation-state-reconciler.php`
* **Class**: `AIPS_Partial_Generation_State_Reconciler`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Document custom hooks in HOOKS.md for third-party developers

---

### Planner
* **Summary**: No description available
* **File**: `ai-post-scheduler/includes/class-aips-planner.php`
* **Class**: `AIPS_Planner`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [WARNING] Registers 3 AJAX hook(s) in constructor instead of via AIPS_Ajax_Registry: aips_generate_topics, aips_bulk_schedule, aips_bulk_generate_now
    2. Document custom hooks in HOOKS.md for third-party developers
    3. Add comprehensive class-level PHPDoc documentation

---

### Post Creator
* **Summary**: Legacy Post Creator alias.
* **File**: `ai-post-scheduler/includes/class-aips-post-creator.php`
* **Class**: `AIPS_Post_Creator`
* **Missing Functionality**: None identified

---

### Post Embeddings Repository
* **Summary**: Post Embeddings Repository
* **File**: `ai-post-scheduler/includes/class-aips-post-embeddings-repository.php`
* **Class**: `AIPS_Post_Embeddings_Repository`
* **Missing Functionality**:
    * Missing save/update methods for data persistence
    * Does not implement an interface — consider adding a contract

---

### Post Manager
* **Summary**: Post Manager Service
* **File**: `ai-post-scheduler/includes/class-aips-post-manager.php`
* **Class**: `AIPS_Post_Manager`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Document custom hooks in HOOKS.md for third-party developers

---

### Post Review
* **Summary**: Post Review Handler
* **File**: `ai-post-scheduler/includes/class-aips-post-review.php`
* **Class**: `AIPS_Post_Review`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [WARNING] Registers 8 AJAX hook(s) in constructor instead of via AIPS_Ajax_Registry: aips_get_draft_posts, aips_publish_post, aips_bulk_publish_posts, aips_regenerate_post, aips_delete_draft_post
    2. [INFO] Directly instantiates AIPS_History_Service without using AIPS_Container — consider resolving from the container
    3. Consider refactoring — class has 949 lines (may violate SRP)
    4. Document custom hooks in HOOKS.md for third-party developers

---

### Post Review Repository
* **Summary**: Post Review Repository
* **File**: `ai-post-scheduler/includes/class-aips-post-review-repository.php`
* **Class**: `AIPS_Post_Review_Repository`
* **Missing Functionality**:
    * Missing save/update methods for data persistence
    * Does not implement an interface — consider adding a contract

---

### Prompt Builder
* **Summary**: AIPS_Prompt_Builder
* **File**: `ai-post-scheduler/includes/class-aips-prompt-builder.php`
* **Class**: `AIPS_Prompt_Builder`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [INFO] Uses raw get_option() for plugin keys 1 time(s) — prefer AIPS_Config::get_instance()->get_option()
    2. Consider refactoring — class has 584 lines (may violate SRP)
    3. High coupling — depends on 12 classes
    4. Uses get_option()/update_option() — migrate to AIPS_Config for caching and defaults
    5. Document custom hooks in HOOKS.md for third-party developers

---

### Prompt Builder Article Structure Section
* **Summary**: Article Structure Section Prompt Builder
* **File**: `ai-post-scheduler/includes/class-aips-prompt-builder-article-structure-section.php`
* **Class**: `AIPS_Prompt_Builder_Article_Structure_Section`
* **Missing Functionality**: None identified

---

### Prompt Builder Authors
* **Summary**: Author Suggestions Prompt Builder
* **File**: `ai-post-scheduler/includes/class-aips-prompt-builder-authors.php`
* **Class**: `AIPS_Prompt_Builder_Authors`
* **Missing Functionality**: None identified

---

### Prompt Builder Post Content
* **Summary**: Post Content Prompt Builder
* **File**: `ai-post-scheduler/includes/class-aips-prompt-builder-post-content.php`
* **Class**: `AIPS_Prompt_Builder_Post_Content`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Document custom hooks in HOOKS.md for third-party developers

---

### Prompt Builder Post Excerpt
* **Summary**: Post Excerpt Prompt Builder
* **File**: `ai-post-scheduler/includes/class-aips-prompt-builder-post-excerpt.php`
* **Class**: `AIPS_Prompt_Builder_Post_Excerpt`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Document custom hooks in HOOKS.md for third-party developers

---

### Prompt Builder Post Featured Image
* **Summary**: Post Featured Image Prompt Builder
* **File**: `ai-post-scheduler/includes/class-aips-prompt-builder-post-featured-image.php`
* **Class**: `AIPS_Prompt_Builder_Post_Featured_Image`
* **Missing Functionality**: None identified

---

### Prompt Builder Post Title
* **Summary**: Post Title Prompt Builder
* **File**: `ai-post-scheduler/includes/class-aips-prompt-builder-post-title.php`
* **Class**: `AIPS_Prompt_Builder_Post_Title`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Document custom hooks in HOOKS.md for third-party developers

---

### Prompt Builder Taxonomy
* **Summary**: Taxonomy Prompt Builder
* **File**: `ai-post-scheduler/includes/class-aips-prompt-builder-taxonomy.php`
* **Class**: `AIPS_Prompt_Builder_Taxonomy`
* **Missing Functionality**: None identified

---

### Prompt Builder Topic
* **Summary**: Topic Prompt Builder
* **File**: `ai-post-scheduler/includes/class-aips-prompt-builder-topic.php`
* **Class**: `AIPS_Prompt_Builder_Topic`
* **Missing Functionality**: None identified

---

### Prompt Section Repository
* **Summary**: Prompt Section Repository
* **File**: `ai-post-scheduler/includes/class-aips-prompt-section-repository.php`
* **Class**: `AIPS_Prompt_Section_Repository`
* **Missing Functionality**: Does not implement an interface — consider adding a contract

---

### Prompt Sections Controller
* **Summary**: Controller for managing prompt sections via AJAX in the WordPress admin.
* **File**: `ai-post-scheduler/includes/class-aips-prompt-sections-controller.php`
* **Class**: `AIPS_Prompt_Sections_Controller`
* **Missing Functionality**: No input validation methods visible
* **Recommended Improvements**: 
    1. [WARNING] Registers 5 AJAX hook(s) in constructor instead of via AIPS_Ajax_Registry: aips_get_prompt_sections, aips_get_prompt_section, aips_save_prompt_section, aips_delete_prompt_section, aips_toggle_prompt_section_active

---

### Research Controller
* **Summary**: Research Controller
* **File**: `ai-post-scheduler/includes/class-aips-research-controller.php`
* **Class**: `AIPS_Research_Controller`
* **Missing Functionality**: No input validation methods visible
* **Recommended Improvements**: 
    1. [INFO] Directly instantiates AIPS_History_Service, AIPS_Logger without using AIPS_Container — consider resolving from the container
    2. Consider refactoring — class has 884 lines (may violate SRP)
    3. High coupling — depends on 14 classes
    4. Consider resolving dependencies from AIPS_Container instead of direct instantiation
    5. Document custom hooks in HOOKS.md for third-party developers

---

### Research Service
* **Summary**: Research Service
* **File**: `ai-post-scheduler/includes/class-aips-research-service.php`
* **Class**: `AIPS_Research_Service`
* **Missing Functionality**: 
    * Does not implement an interface — consider adding a contract
    * No input validation methods visible
* **Recommended Improvements**: 
    1. Consider refactoring — class has 601 lines (may violate SRP)
    2. Consider using AIPS_Cache for caching expensive operations

---

### Resilience Service
* **Summary**: Resilience Service Layer
* **File**: `ai-post-scheduler/includes/class-aips-resilience-service.php`
* **Class**: `AIPS_Resilience_Service`
* **Missing Functionality**: 
    * Does not implement an interface — consider adding a contract
    * No input validation methods visible
* **Recommended Improvements**: 
    1. Consider refactoring — class has 625 lines (may violate SRP)
    2. Document custom hooks in HOOKS.md for third-party developers

---

### Schedule Controller
* **Summary**: No description available
* **File**: `ai-post-scheduler/includes/class-aips-schedule-controller.php`
* **Class**: `AIPS_Schedule_Controller`
* **Missing Functionality**: No input validation methods visible
* **Recommended Improvements**: 
    1. [WARNING] Registers 15 AJAX hook(s) in constructor instead of via AIPS_Ajax_Registry: aips_save_schedule, aips_delete_schedule, aips_toggle_schedule, aips_run_now, aips_bulk_delete_schedules
    2. Consider refactoring — class has 902 lines (may violate SRP)
    3. High coupling — depends on 13 classes
    4. Document custom hooks in HOOKS.md for third-party developers
    5. Add comprehensive class-level PHPDoc documentation

---

### Schedule Entry
* **Summary**: Schedule Entry DTO
* **File**: `ai-post-scheduler/includes/class-aips-schedule-entry.php`
* **Class**: `AIPS_Schedule_Entry`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [WARNING] Uses $wpdb directly — SQL should be in a Repository class
    2. Consider using Repository pattern for database access instead of direct $wpdb

---

### Schedule Processor
* **Summary**: AIPS_Schedule_Processor
* **File**: `ai-post-scheduler/includes/class-aips-schedule-processor.php`
* **Class**: `AIPS_Schedule_Processor`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Consider refactoring — class has 759 lines (may violate SRP)
    2. High coupling — depends on 18 classes
    3. Document custom hooks in HOOKS.md for third-party developers

---

### Schedule Repository
* **Summary**: Schedule Repository
* **File**: `ai-post-scheduler/includes/class-aips-schedule-repository.php`
* **Class**: `AIPS_Schedule_Repository`
* **Implements**: `AIPS_Schedule_Repository_Interface`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Consider refactoring — class has 718 lines (may violate SRP)
    2. High method count (24+ methods) — consider splitting responsibilities

---

### Scheduler
* **Summary**: Class AIPS_Scheduler
* **File**: `ai-post-scheduler/includes/class-aips-scheduler.php`
* **Class**: `AIPS_Scheduler`
* **Implements**: `AIPS_Cron_Generation_Handler`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [INFO] Directly instantiates AIPS_History_Service without using AIPS_Container — consider resolving from the container
    2. [WARNING] Uses $wpdb directly — SQL should be in a Repository class
    3. High coupling — depends on 9 classes

---

### Seeder Admin
* **Summary**: No description available
* **File**: `ai-post-scheduler/includes/class-aips-seeder-admin.php`
* **Class**: `AIPS_Seeder_Admin`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [WARNING] Registers 1 AJAX hook(s) in constructor instead of via AIPS_Ajax_Registry: aips_process_seeder
    2. Document custom hooks in HOOKS.md for third-party developers
    3. Add comprehensive class-level PHPDoc documentation

---

### Seeder Service
* **Summary**: No description available
* **File**: `ai-post-scheduler/includes/class-aips-seeder-service.php`
* **Class**: `AIPS_Seeder_Service`
* **Missing Functionality**: 
    * No AIPS_Logger or AIPS_History_Service usage for observability
    * Does not implement an interface — consider adding a contract
    * No input validation methods visible
* **Recommended Improvements**: 
    1. Consider using AIPS_Cache for caching expensive operations
    2. Add comprehensive class-level PHPDoc documentation

---

### Session To Json
* **Summary**: Session To JSON Converter
* **File**: `ai-post-scheduler/includes/class-aips-session-to-json.php`
* **Class**: `AIPS_Session_To_JSON`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [INFO] Directly instantiates AIPS_Logger without using AIPS_Container — consider resolving from the container

---

### Settings
* **Summary**: Class AIPS_Settings
* **File**: `ai-post-scheduler/includes/class-aips-settings.php`
* **Class**: `AIPS_Settings`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Consider refactoring — class has 683 lines (may violate SRP)

---

### Settings Ajax
* **Summary**: Class AIPS_Settings_AJAX
* **File**: `ai-post-scheduler/includes/class-aips-settings-ajax.php`
* **Class**: `AIPS_Settings_AJAX`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [WARNING] Registers 2 AJAX hook(s) in constructor instead of via AIPS_Ajax_Registry: aips_test_connection, aips_notifications_data_hygiene
    2. [INFO] Directly instantiates AIPS_AI_Service without using AIPS_Container — consider resolving from the container

---

### Settings Ui
* **Summary**: Class AIPS_Settings_UI
* **File**: `ai-post-scheduler/includes/class-aips-settings-ui.php`
* **Class**: `AIPS_Settings_UI`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Consider refactoring — class has 861 lines (may violate SRP)
    2. High method count (55+ methods) — consider splitting responsibilities

---

### Site Context
* **Summary**: Site Context Service
* **File**: `ai-post-scheduler/includes/class-aips-site-context.php`
* **Class**: `AIPS_Site_Context`
* **Missing Functionality**: None identified

---

### Sources Controller
* **Summary**: Sources Controller
* **File**: `ai-post-scheduler/includes/class-aips-sources-controller.php`
* **Class**: `AIPS_Sources_Controller`
* **Missing Functionality**: No input validation methods visible
* **Recommended Improvements**: 
    1. [WARNING] Registers 8 AJAX hook(s) in constructor instead of via AIPS_Ajax_Registry: aips_get_sources, aips_save_source, aips_delete_source, aips_toggle_source_active, aips_fetch_source_now
    2. Consider resolving dependencies from AIPS_Container instead of direct instantiation

---

### Sources Cron
* **Summary**: Sources Cron Handler
* **File**: `ai-post-scheduler/includes/class-aips-sources-cron.php`
* **Class**: `AIPS_Sources_Cron`
* **Missing Functionality**: None identified
* **Recommended Improvements**:
    1. [INFO] Directly instantiates AIPS_Logger without using AIPS_Container — consider resolving from the container

---

### Sources Data Repository
* **Summary**: Sources Data Repository
* **File**: `ai-post-scheduler/includes/class-aips-sources-data-repository.php`
* **Class**: `AIPS_Sources_Data_Repository`
* **Missing Functionality**: Does not implement an interface — consider adding a contract

---

### Sources Fetcher
* **Summary**: Sources Fetcher Service
* **File**: `ai-post-scheduler/includes/class-aips-sources-fetcher.php`
* **Class**: `AIPS_Sources_Fetcher`
* **Missing Functionality**: None identified
* **Recommended Improvements**:
    1. [INFO] Uses raw get_option() for plugin keys 2 time(s) — prefer AIPS_Config::get_instance()->get_option()
    2. [INFO] Directly instantiates AIPS_Logger without using AIPS_Container — consider resolving from the container
    3. Consider refactoring — class has 633 lines (may violate SRP)
    4. Uses get_option()/update_option() — migrate to AIPS_Config for caching and defaults

---

### Sources Repository
* **Summary**: Sources Repository
* **File**: `ai-post-scheduler/includes/class-aips-sources-repository.php`
* **Class**: `AIPS_Sources_Repository`
* **Missing Functionality**: Does not implement an interface — consider adding a contract
* **Recommended Improvements**: 
    1. Consider refactoring — class has 508 lines (may violate SRP)

---

### Structures Controller
* **Summary**: No description available
* **File**: `ai-post-scheduler/includes/class-aips-structures-controller.php`
* **Class**: `AIPS_Structures_Controller`
* **Missing Functionality**: No input validation methods visible
* **Recommended Improvements**: 
    1. [WARNING] Registers 5 AJAX hook(s) in constructor instead of via AIPS_Ajax_Registry: aips_get_structures, aips_get_structure, aips_save_structure, aips_delete_structure, aips_toggle_structure_active
    2. Consider resolving dependencies from AIPS_Container instead of direct instantiation
    3. Add comprehensive class-level PHPDoc documentation

---

### System Diagnostics Environment Provider
* **Summary**: AIPS_System_Diagnostics_Environment_Provider
* **File**: `ai-post-scheduler/includes/diagnostics/class-aips-system-diagnostics-environment-provider.php`
* **Class**: `AIPS_System_Diagnostics_Environment_Provider`
* **Implements**: `AIPS_System_Diagnostic_Provider_Interface`
* **Missing Functionality**: None identified
* **Recommended Improvements**:
    1. [WARNING] Uses $wpdb directly — SQL should be in a Repository class
    2. Consider using Repository pattern for database access instead of direct $wpdb

---

### System Diagnostics Logs Provider
* **Summary**: AIPS_System_Diagnostics_Logs_Provider
* **File**: `ai-post-scheduler/includes/diagnostics/class-aips-system-diagnostics-logs-provider.php`
* **Class**: `AIPS_System_Diagnostics_Logs_Provider`
* **Implements**: `AIPS_System_Diagnostic_Provider_Interface`
* **Missing Functionality**: None identified
* **Recommended Improvements**:
    1. [INFO] Directly instantiates AIPS_Logger without using AIPS_Container — consider resolving from the container

---

### System Diagnostics Queue Provider
* **Summary**: AIPS_System_Diagnostics_Queue_Provider
* **File**: `ai-post-scheduler/includes/diagnostics/class-aips-system-diagnostics-queue-provider.php`
* **Class**: `AIPS_System_Diagnostics_Queue_Provider`
* **Implements**: `AIPS_System_Diagnostic_Provider_Interface`
* **Missing Functionality**: None identified
* **Recommended Improvements**:
    1. [INFO] Directly instantiates AIPS_Resilience_Service without using AIPS_Container — consider resolving from the container

---

### System Diagnostics Scheduler Provider
* **Summary**: AIPS_System_Diagnostics_Scheduler_Provider
* **File**: `ai-post-scheduler/includes/diagnostics/class-aips-system-diagnostics-scheduler-provider.php`
* **Class**: `AIPS_System_Diagnostics_Scheduler_Provider`
* **Implements**: `AIPS_System_Diagnostic_Provider_Interface`
* **Missing Functionality**: No AIPS_Logger or AIPS_History_Service usage for observability

---

### System Diagnostics Service
* **Summary**: AIPS_System_Diagnostics_Service
* **File**: `ai-post-scheduler/includes/class-aips-system-diagnostics-service.php`
* **Class**: `AIPS_System_Diagnostics_Service`
* **Missing Functionality**:
    * No AIPS_Logger or AIPS_History_Service usage for observability
    * Does not implement an interface — consider adding a contract
    * No input validation methods visible
* **Recommended Improvements**:
    1. Consider using AIPS_Cache for caching expensive operations

---

### System Status
* **Summary**: No description available
* **File**: `ai-post-scheduler/includes/class-aips-system-status.php`
* **Class**: `AIPS_System_Status`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Add comprehensive class-level PHPDoc documentation

---

### System Status Controller
* **Summary**: System Status Controller
* **File**: `ai-post-scheduler/includes/class-aips-system-status-controller.php`
* **Class**: `AIPS_System_Status_Controller`
* **Missing Functionality**: No input validation methods visible
* **Recommended Improvements**:
    1. [WARNING] Registers 1 AJAX hook(s) in constructor instead of via AIPS_Ajax_Registry: aips_reset_circuit_breaker
    2. [INFO] Directly instantiates AIPS_Resilience_Service without using AIPS_Container — consider resolving from the container

---

### Taxonomy Controller
* **Summary**: Taxonomy Controller
* **File**: `ai-post-scheduler/includes/class-aips-taxonomy-controller.php`
* **Class**: `AIPS_Taxonomy_Controller`
* **Missing Functionality**: No input validation methods visible
* **Recommended Improvements**: 
    1. [WARNING] Registers 11 AJAX hook(s) in constructor instead of via AIPS_Ajax_Registry: aips_get_taxonomy_items, aips_generate_taxonomy, aips_approve_taxonomy, aips_reject_taxonomy, aips_delete_taxonomy
    2. Consider refactoring — class has 680 lines (may violate SRP)

---

### Taxonomy Repository
* **Summary**: Taxonomy Repository
* **File**: `ai-post-scheduler/includes/class-aips-taxonomy-repository.php`
* **Class**: `AIPS_Taxonomy_Repository`
* **Missing Functionality**: Does not implement an interface — consider adding a contract

---

### Telemetry
* **Summary**: Telemetry Collector
* **File**: `ai-post-scheduler/includes/class-aips-telemetry.php`
* **Class**: `AIPS_Telemetry`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [WARNING] Uses $wpdb directly — SQL should be in a Repository class
    2. Consider refactoring — class has 569 lines (may violate SRP)
    3. Uses get_option()/update_option() — migrate to AIPS_Config for caching and defaults
    4. Document custom hooks in HOOKS.md for third-party developers

---

### Telemetry Controller
* **Summary**: Telemetry Controller
* **File**: `ai-post-scheduler/includes/class-aips-telemetry-controller.php`
* **Class**: `AIPS_Telemetry_Controller`
* **Missing Functionality**: No input validation methods visible
* **Recommended Improvements**:
    1. [WARNING] Registers 2 AJAX hook(s) in constructor instead of via AIPS_Ajax_Registry: aips_get_telemetry, aips_get_telemetry_details

---

### Telemetry Repository
* **Summary**: Telemetry Repository
* **File**: `ai-post-scheduler/includes/class-aips-telemetry-repository.php`
* **Class**: `AIPS_Telemetry_Repository`
* **Missing Functionality**: Does not implement an interface — consider adding a contract

---

### Template Context
* **Summary**: Class AIPS_Template_Context
* **File**: `ai-post-scheduler/includes/class-aips-template-context.php`
* **Class**: `AIPS_Template_Context`
* **Implements**: `AIPS_Generation_Context`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. High method count (24+ methods) — consider splitting responsibilities

---

### Template Data
* **Summary**: Template Data DTO
* **File**: `ai-post-scheduler/includes/class-aips-template-data.php`
* **Class**: `AIPS_Template_Data`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [WARNING] Uses $wpdb directly — SQL should be in a Repository class
    2. Consider using Repository pattern for database access instead of direct $wpdb

---

### Template Helper
* **Summary**: No description available
* **File**: `ai-post-scheduler/includes/class-aips-template-helper.php`
* **Class**: `AIPS_Template_Helper`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. Add comprehensive class-level PHPDoc documentation

---

### Template Processor
* **Summary**: Template Variable Processor
* **File**: `ai-post-scheduler/includes/class-aips-template-processor.php`
* **Class**: `AIPS_Template_Processor`
* **Missing Functionality**: No AIPS_Logger or AIPS_History_Service usage for observability
* **Recommended Improvements**: 
    1. Document custom hooks in HOOKS.md for third-party developers

---

### Template Repository
* **Summary**: Template Repository
* **File**: `ai-post-scheduler/includes/class-aips-template-repository.php`
* **Class**: `AIPS_Template_Repository`
* **Missing Functionality**: Does not implement an interface — consider adding a contract

---

### Template Type Selector
* **Summary**: Template Type Selector
* **File**: `ai-post-scheduler/includes/class-aips-template-type-selector.php`
* **Class**: `AIPS_Template_Type_Selector`
* **Missing Functionality**: None identified

---

### Templates
* **Summary**: No description available
* **File**: `ai-post-scheduler/includes/class-aips-templates.php`
* **Class**: `AIPS_Templates`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [WARNING] Uses $wpdb directly — SQL should be in a Repository class
    2. Add comprehensive class-level PHPDoc documentation

---

### Templates Controller
* **Summary**: No description available
* **File**: `ai-post-scheduler/includes/class-aips-templates-controller.php`
* **Class**: `AIPS_Templates_Controller`
* **Missing Functionality**: No input validation methods visible
* **Recommended Improvements**: 
    1. [WARNING] Registers 6 AJAX hook(s) in constructor instead of via AIPS_Ajax_Registry: aips_save_template, aips_delete_template, aips_get_template, aips_test_template, aips_clone_template
    2. Consider resolving dependencies from AIPS_Container instead of direct instantiation
    3. Document custom hooks in HOOKS.md for third-party developers
    4. Add comprehensive class-level PHPDoc documentation

---

### Token Budget
* **Summary**: Token budget utility.
* **File**: `ai-post-scheduler/includes/class-aips-token-budget.php`
* **Class**: `AIPS_Token_Budget`
* **Missing Functionality**: None identified

---

### Topic Context
* **Summary**: Class AIPS_Topic_Context
* **File**: `ai-post-scheduler/includes/class-aips-topic-context.php`
* **Class**: `AIPS_Topic_Context`
* **Implements**: `AIPS_Generation_Context`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. High method count (25+ methods) — consider splitting responsibilities

---

### Topic Expansion Service
* **Summary**: Topic Expansion Service
* **File**: `ai-post-scheduler/includes/class-aips-topic-expansion-service.php`
* **Class**: `AIPS_Topic_Expansion_Service`
* **Missing Functionality**: 
    * Does not implement an interface — consider adding a contract
    * No input validation methods visible
* **Recommended Improvements**: 
    1. Consider refactoring — class has 526 lines (may violate SRP)
    2. High coupling — depends on 9 classes
    3. Consider using AIPS_Cache for caching expensive operations

---

### Topic Penalty Service
* **Summary**: Topic Penalty Service
* **File**: `ai-post-scheduler/includes/class-aips-topic-penalty-service.php`
* **Class**: `AIPS_Topic_Penalty_Service`
* **Missing Functionality**: 
    * Does not implement an interface — consider adding a contract
    * No input validation methods visible
* **Recommended Improvements**: 
    1. Consider using AIPS_Cache for caching expensive operations

---

### Trending Topics Repository
* **Summary**: Trending Topics Repository
* **File**: `ai-post-scheduler/includes/class-aips-trending-topics-repository.php`
* **Class**: `AIPS_Trending_Topics_Repository`
* **Missing Functionality**: Does not implement an interface — consider adding a contract
* **Recommended Improvements**: 
    1. Consider refactoring — class has 758 lines (may violate SRP)

---

### Unified Schedule Service
* **Summary**: Unified Schedule Service
* **File**: `ai-post-scheduler/includes/class-aips-unified-schedule-service.php`
* **Class**: `AIPS_Unified_Schedule_Service`
* **Missing Functionality**: 
    * No AIPS_Logger or AIPS_History_Service usage for observability
    * Does not implement an interface — consider adding a contract
    * No input validation methods visible
* **Recommended Improvements**: 
    1. [WARNING] Uses $wpdb directly — SQL should be in a Repository class
    2. Consider using AIPS_Cache for caching expensive operations

---

### Upgrades
* **Summary**: No description available
* **File**: `ai-post-scheduler/includes/class-aips-upgrades.php`
* **Class**: `AIPS_Upgrades`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [INFO] Directly instantiates AIPS_Logger without using AIPS_Container — consider resolving from the container
    2. Add comprehensive class-level PHPDoc documentation

---

### Utilities
* **Summary**: General Utilities
* **File**: `ai-post-scheduler/includes/class-aips-utilities.php`
* **Class**: `AIPS_Utilities`
* **Missing Functionality**: None identified

---

### Voices
* **Summary**: No description available
* **File**: `ai-post-scheduler/includes/class-aips-voices.php`
* **Class**: `AIPS_Voices`
* **Missing Functionality**: None identified
* **Recommended Improvements**: 
    1. [WARNING] Registers 4 AJAX hook(s) in constructor instead of via AIPS_Ajax_Registry: aips_save_voice, aips_delete_voice, aips_get_voice, aips_search_voices
    2. Add comprehensive class-level PHPDoc documentation

---

### Voices Repository
* **Summary**: Voices Repository
* **File**: `ai-post-scheduler/includes/class-aips-voices-repository.php`
* **Class**: `AIPS_Voices_Repository`
* **Missing Functionality**: Does not implement an interface — consider adding a contract

---

