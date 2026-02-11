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
