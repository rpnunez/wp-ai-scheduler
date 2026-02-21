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

/**
 * Register a class alias only if the alias name is not already in use.
 * Prevents "Cannot declare class" fatal when old includes/ files are loaded
 * before this compatibility layer (e.g. via a stale classmap).
 */
$register_alias = function ($original, $alias) {
	if (!class_exists($alias, false)) {
		class_alias($original, $alias);
	}
};

// Repositories
$register_alias('AIPS\\Repositories\\DBManager', 'AIPS_DB_Manager');
$register_alias('AIPS\\Repositories\\TemplateRepository', 'AIPS_Template_Repository');
$register_alias('AIPS\\Repositories\\ScheduleRepository', 'AIPS_Schedule_Repository');
$register_alias('AIPS\\Repositories\\HistoryRepository', 'AIPS_History_Repository');
$register_alias('AIPS\\Repositories\\ArticleStructureRepository', 'AIPS_Article_Structure_Repository');
$register_alias('AIPS\\Repositories\\AuthorsRepository', 'AIPS_Authors_Repository');
$register_alias('AIPS\\Repositories\\AuthorTopicsRepository', 'AIPS_Author_Topics_Repository');
$register_alias('AIPS\\Repositories\\AuthorTopicLogsRepository', 'AIPS_Author_Topic_Logs_Repository');
$register_alias('AIPS\\Repositories\\VoicesRepository', 'AIPS_Voices_Repository');
$register_alias('AIPS\\Repositories\\PromptSectionRepository', 'AIPS_Prompt_Section_Repository');
$register_alias('AIPS\\Repositories\\PostReviewRepository', 'AIPS_Post_Review_Repository');
$register_alias('AIPS\\Repositories\\FeedbackRepository', 'AIPS_Feedback_Repository');
$register_alias('AIPS\\Repositories\\TrendingTopicsRepository', 'AIPS_Trending_Topics_Repository');

// Interfaces
$register_alias('AIPS\\Interfaces\\GenerationContext', 'AIPS_Generation_Context');

// Models
$register_alias('AIPS\\Models\\Config', 'AIPS_Config');
$register_alias('AIPS\\Models\\HistoryType', 'AIPS_History_Type');
$register_alias('AIPS\\Models\\HistoryContainer', 'AIPS_History_Container');
$register_alias('AIPS\\Models\\TemplateContext', 'AIPS_Template_Context');
$register_alias('AIPS\\Models\\TopicContext', 'AIPS_Topic_Context');
$register_alias('AIPS\\Models\\TemplateTypeSelector', 'AIPS_Template_Type_Selector');
$register_alias('AIPS\\Models\\ArticleStructureManager', 'AIPS_Article_Structure_Manager');

// Services - Core
$register_alias('AIPS\\Services\\Logger', 'AIPS_Logger');
$register_alias('AIPS\\Services\\ImageService', 'AIPS_Image_Service');
$register_alias('AIPS\\Services\\HistoryService', 'AIPS_History_Service');
$register_alias('AIPS\\Services\\SessionToJson', 'AIPS_Session_To_Json');
$register_alias('AIPS\\Services\\SeederService', 'AIPS_Seeder_Service');

// Services - AI
$register_alias('AIPS\\Services\\AI\\AIService', 'AIPS_AI_Service');
$register_alias('AIPS\\Services\\AI\\EmbeddingsService', 'AIPS_Embeddings_Service');
$register_alias('AIPS\\Services\\AI\\PromptBuilder', 'AIPS_Prompt_Builder');
$register_alias('AIPS\\Services\\AI\\ResilienceService', 'AIPS_Resilience_Service');

// Services - Content
$register_alias('AIPS\\Services\\Content\\ComponentRegenerationService', 'AIPS_Component_Regeneration_Service');
$register_alias('AIPS\\Services\\Content\\PostCreator', 'AIPS_Post_Creator');
$register_alias('AIPS\\Services\\Content\\TemplateProcessor', 'AIPS_Template_Processor');
$register_alias('AIPS\\Services\\Content\\TemplateHelper', 'AIPS_Template_Helper');

// Services - Research
$register_alias('AIPS\\Services\\Research\\ResearchService', 'AIPS_Research_Service');
$register_alias('AIPS\\Services\\Research\\TopicExpansionService', 'AIPS_Topic_Expansion_Service');
$register_alias('AIPS\\Services\\Research\\TopicPenaltyService', 'AIPS_Topic_Penalty_Service');

// Services - Generation
$register_alias('AIPS\\Services\\Generation\\GenerationLogger', 'AIPS_Generation_Logger');
$register_alias('AIPS\\Services\\Generation\\GenerationSession', 'AIPS_Generation_Session');

// Generators
$register_alias('AIPS\\Generators\\Generator', 'AIPS_Generator');
$register_alias('AIPS\\Generators\\AuthorPostGenerator', 'AIPS_Author_Post_Generator');
$register_alias('AIPS\\Generators\\AuthorTopicsGenerator', 'AIPS_Author_Topics_Generator');
$register_alias('AIPS\\Generators\\ScheduleProcessor', 'AIPS_Schedule_Processor');

// Controllers - Core
$register_alias('AIPS\\Controllers\\AIEditController', 'AIPS_AI_Edit_Controller');
$register_alias('AIPS\\Controllers\\DataManagementController', 'AIPS_Data_Management');

// Controllers - Admin
$register_alias('AIPS\\Controllers\\Admin\\AuthorsController', 'AIPS_Authors_Controller');
$register_alias('AIPS\\Controllers\\Admin\\AuthorTopicsController', 'AIPS_Author_Topics_Controller');
$register_alias('AIPS\\Controllers\\Admin\\CalendarController', 'AIPS_Calendar_Controller');
$register_alias('AIPS\\Controllers\\Admin\\DashboardController', 'AIPS_Dashboard_Controller');
$register_alias('AIPS\\Controllers\\Admin\\GeneratedPostsController', 'AIPS_Generated_Posts_Controller');
$register_alias('AIPS\\Controllers\\Admin\\PromptSectionsController', 'AIPS_Prompt_Sections_Controller');
$register_alias('AIPS\\Controllers\\Admin\\ResearchController', 'AIPS_Research_Controller');
$register_alias('AIPS\\Controllers\\Admin\\ScheduleController', 'AIPS_Schedule_Controller');
$register_alias('AIPS\\Controllers\\Admin\\StructuresController', 'AIPS_Structures_Controller');
$register_alias('AIPS\\Controllers\\Admin\\TemplatesController', 'AIPS_Templates_Controller');

// Admin Classes
$register_alias('AIPS\\Admin\\Settings', 'AIPS_Settings');
$register_alias('AIPS\\Admin\\AdminAssets', 'AIPS_Admin_Assets');
$register_alias('AIPS\\Admin\\DevTools', 'AIPS_Dev_Tools');
$register_alias('AIPS\\Admin\\History', 'AIPS_History');
$register_alias('AIPS\\Admin\\Planner', 'AIPS_Planner');
$register_alias('AIPS\\Admin\\Scheduler', 'AIPS_Scheduler');
$register_alias('AIPS\\Admin\\Templates', 'AIPS_Templates');
$register_alias('AIPS\\Admin\\Voices', 'AIPS_Voices');
$register_alias('AIPS\\Admin\\PostReview', 'AIPS_Post_Review');
$register_alias('AIPS\\Admin\\SeederAdmin', 'AIPS_Seeder_Admin');
$register_alias('AIPS\\Admin\\SystemStatus', 'AIPS_System_Status');
$register_alias('AIPS\\Admin\\Upgrades', 'AIPS_Upgrades');

// Utilities
$register_alias('AIPS\\Utilities\\IntervalCalculator', 'AIPS_Interval_Calculator');
$register_alias('AIPS\\Utilities\\AuthorTopicsScheduler', 'AIPS_Author_Topics_Scheduler');

// Data Management - Export
$register_alias('AIPS\\DataManagement\\Export\\ExportHandler', 'AIPS_Data_Management_Export');
$register_alias('AIPS\\DataManagement\\Export\\JsonExporter', 'AIPS_Data_Management_Export_JSON');
$register_alias('AIPS\\DataManagement\\Export\\MySQLExporter', 'AIPS_Data_Management_Export_MySQL');

// Data Management - Import
$register_alias('AIPS\\DataManagement\\Import\\ImportHandler', 'AIPS_Data_Management_Import');
$register_alias('AIPS\\DataManagement\\Import\\JsonImporter', 'AIPS_Data_Management_Import_JSON');
$register_alias('AIPS\\DataManagement\\Import\\MySQLImporter', 'AIPS_Data_Management_Import_MySQL');

// Notifications
$register_alias('AIPS\\Notifications\\PostReviewNotifications', 'AIPS_Post_Review_Notifications');
