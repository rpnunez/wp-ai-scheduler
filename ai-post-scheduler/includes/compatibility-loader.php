<?php
/**
 * Backward Compatibility Layer
 * 
 * Provides class aliases for old class names to maintain backward compatibility
 * with third-party code that may reference the old AIPS_* class names.
 * 
 * This file will be maintained for 2-3 versions and then deprecated.
 * 
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

// This file will be populated as classes are migrated
// Format: class_alias('AIPS\\Namespace\\NewClassName', 'AIPS_Old_Class_Name');

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
