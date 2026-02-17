<?php
/**
 * Backward Compatibility Layer
 *
 * Provides class aliases for old class names to maintain backward compatibility
 * with third-party code that may reference the old AIPS_* class names.
 *
 * DEPRECATION TIMELINE:
 * - v2.0.0: All 77 classes migrated. Old names work via aliases.
 * - v2.1.0: Deprecation notices may be added (optional).
 * - v3.0.0: This file will be removed. Use namespaced classes only.
 *
 * MIGRATION: Replace AIPS_Old_Class with AIPS\Namespace\NewClass and add use statement.
 * See docs/psr-4-refactor/PSR4_CLASS_MAPPING.md for full mapping.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

// Repositories
class_alias('AIPS\\Repositories\\DBManager', 'AIPS_DB_Manager');
class_alias('AIPS\\Repositories\\TemplateRepository', 'AIPS_Template_Repository');
class_alias('AIPS\\Repositories\\ScheduleRepository', 'AIPS_Schedule_Repository');
class_alias('AIPS\\Repositories\\HistoryRepository', 'AIPS_History_Repository');
class_alias('AIPS\\Repositories\\ArticleStructureRepository', 'AIPS_Article_Structure_Repository');
class_alias('AIPS\\Repositories\\AuthorsRepository', 'AIPS_Authors_Repository');
class_alias('AIPS\\Repositories\\AuthorTopicsRepository', 'AIPS_Author_Topics_Repository');
class_alias('AIPS\\Repositories\\AuthorTopicLogsRepository', 'AIPS_Author_Topic_Logs_Repository');
class_alias('AIPS\\Repositories\\VoicesRepository', 'AIPS_Voices_Repository');
class_alias('AIPS\\Repositories\\PromptSectionRepository', 'AIPS_Prompt_Section_Repository');
class_alias('AIPS\\Repositories\\PostReviewRepository', 'AIPS_Post_Review_Repository');
class_alias('AIPS\\Repositories\\FeedbackRepository', 'AIPS_Feedback_Repository');
class_alias('AIPS\\Repositories\\TrendingTopicsRepository', 'AIPS_Trending_Topics_Repository');

// Interfaces
class_alias('AIPS\\Interfaces\\GenerationContext', 'AIPS_Generation_Context');

// Models
class_alias('AIPS\\Models\\Config', 'AIPS_Config');
class_alias('AIPS\\Models\\HistoryType', 'AIPS_History_Type');
class_alias('AIPS\\Models\\HistoryContainer', 'AIPS_History_Container');
class_alias('AIPS\\Models\\TemplateContext', 'AIPS_Template_Context');
class_alias('AIPS\\Models\\TopicContext', 'AIPS_Topic_Context');
class_alias('AIPS\\Models\\TemplateTypeSelector', 'AIPS_Template_Type_Selector');
class_alias('AIPS\\Models\\ArticleStructureManager', 'AIPS_Article_Structure_Manager');

// Services - Core
class_alias('AIPS\\Services\\Logger', 'AIPS_Logger');
class_alias('AIPS\\Services\\ImageService', 'AIPS_Image_Service');
class_alias('AIPS\\Services\\HistoryService', 'AIPS_History_Service');
class_alias('AIPS\\Services\\SessionToJson', 'AIPS_Session_To_Json');
class_alias('AIPS\\Services\\SeederService', 'AIPS_Seeder_Service');

// Services - AI
class_alias('AIPS\\Services\\AI\\AIService', 'AIPS_AI_Service');
class_alias('AIPS\\Services\\AI\\EmbeddingsService', 'AIPS_Embeddings_Service');
class_alias('AIPS\\Services\\AI\\PromptBuilder', 'AIPS_Prompt_Builder');
class_alias('AIPS\\Services\\AI\\ResilienceService', 'AIPS_Resilience_Service');

// Services - Content
class_alias('AIPS\\Services\\Content\\ComponentRegenerationService', 'AIPS_Component_Regeneration_Service');
class_alias('AIPS\\Services\\Content\\PostCreator', 'AIPS_Post_Creator');
class_alias('AIPS\\Services\\Content\\TemplateProcessor', 'AIPS_Template_Processor');
class_alias('AIPS\\Services\\Content\\TemplateHelper', 'AIPS_Template_Helper');

// Services - Research
class_alias('AIPS\\Services\\Research\\ResearchService', 'AIPS_Research_Service');
class_alias('AIPS\\Services\\Research\\TopicExpansionService', 'AIPS_Topic_Expansion_Service');
class_alias('AIPS\\Services\\Research\\TopicPenaltyService', 'AIPS_Topic_Penalty_Service');

// Services - Generation
class_alias('AIPS\\Services\\Generation\\GenerationLogger', 'AIPS_Generation_Logger');
class_alias('AIPS\\Services\\Generation\\GenerationSession', 'AIPS_Generation_Session');

// Generators
class_alias('AIPS\\Generators\\Generator', 'AIPS_Generator');
class_alias('AIPS\\Generators\\AuthorPostGenerator', 'AIPS_Author_Post_Generator');
class_alias('AIPS\\Generators\\AuthorTopicsGenerator', 'AIPS_Author_Topics_Generator');
class_alias('AIPS\\Generators\\ScheduleProcessor', 'AIPS_Schedule_Processor');

// Controllers - Core
class_alias('AIPS\\Controllers\\AIEditController', 'AIPS_AI_Edit_Controller');
class_alias('AIPS\\Controllers\\DataManagementController', 'AIPS_Data_Management');

// Controllers - Admin
class_alias('AIPS\\Controllers\\Admin\\AuthorsController', 'AIPS_Authors_Controller');
class_alias('AIPS\\Controllers\\Admin\\AuthorTopicsController', 'AIPS_Author_Topics_Controller');
class_alias('AIPS\\Controllers\\Admin\\CalendarController', 'AIPS_Calendar_Controller');
class_alias('AIPS\\Controllers\\Admin\\DashboardController', 'AIPS_Dashboard_Controller');
class_alias('AIPS\\Controllers\\Admin\\GeneratedPostsController', 'AIPS_Generated_Posts_Controller');
class_alias('AIPS\\Controllers\\Admin\\PromptSectionsController', 'AIPS_Prompt_Sections_Controller');
class_alias('AIPS\\Controllers\\Admin\\ResearchController', 'AIPS_Research_Controller');
class_alias('AIPS\\Controllers\\Admin\\ScheduleController', 'AIPS_Schedule_Controller');
class_alias('AIPS\\Controllers\\Admin\\StructuresController', 'AIPS_Structures_Controller');
class_alias('AIPS\\Controllers\\Admin\\TemplatesController', 'AIPS_Templates_Controller');

// Admin Classes
class_alias('AIPS\\Admin\\Settings', 'AIPS_Settings');
class_alias('AIPS\\Admin\\AdminAssets', 'AIPS_Admin_Assets');
class_alias('AIPS\\Admin\\DevTools', 'AIPS_Dev_Tools');
class_alias('AIPS\\Admin\\History', 'AIPS_History');
class_alias('AIPS\\Admin\\Planner', 'AIPS_Planner');
class_alias('AIPS\\Admin\\Scheduler', 'AIPS_Scheduler');
class_alias('AIPS\\Admin\\Templates', 'AIPS_Templates');
class_alias('AIPS\\Admin\\Voices', 'AIPS_Voices');
class_alias('AIPS\\Admin\\PostReview', 'AIPS_Post_Review');
class_alias('AIPS\\Admin\\SeederAdmin', 'AIPS_Seeder_Admin');
class_alias('AIPS\\Admin\\SystemStatus', 'AIPS_System_Status');
class_alias('AIPS\\Admin\\Upgrades', 'AIPS_Upgrades');

// Utilities
class_alias('AIPS\\Utilities\\IntervalCalculator', 'AIPS_Interval_Calculator');
class_alias('AIPS\\Utilities\\AuthorTopicsScheduler', 'AIPS_Author_Topics_Scheduler');

// Data Management - Export
class_alias('AIPS\\DataManagement\\Export\\ExportHandler', 'AIPS_Data_Management_Export');
class_alias('AIPS\\DataManagement\\Export\\JsonExporter', 'AIPS_Data_Management_Export_JSON');
class_alias('AIPS\\DataManagement\\Export\\MySQLExporter', 'AIPS_Data_Management_Export_MySQL');

// Data Management - Import
class_alias('AIPS\\DataManagement\\Import\\ImportHandler', 'AIPS_Data_Management_Import');
class_alias('AIPS\\DataManagement\\Import\\JsonImporter', 'AIPS_Data_Management_Import_JSON');
class_alias('AIPS\\DataManagement\\Import\\MySQLImporter', 'AIPS_Data_Management_Import_MySQL');

// Notifications
class_alias('AIPS\\Notifications\\PostReviewNotifications', 'AIPS_Post_Review_Notifications');
