<?php
if (!defined('ABSPATH')) { exit; }

// Backward compatibility mappings for refactored classes

class_alias('AIPS\\Repository\\HistoryRepository', 'AIPS_History_Repository');
class_alias('AIPS\\Repository\\AuthorTopicsRepository', 'AIPS_Author_Topics_Repository');
class_alias('AIPS\\Repository\\PromptSectionRepository', 'AIPS_Prompt_Section_Repository');
class_alias('AIPS\\Repository\\TemplateRepository', 'AIPS_Template_Repository');
class_alias('AIPS\\Repository\\ScheduleRepository', 'AIPS_Schedule_Repository');
class_alias('AIPS\\Repository\\PostReviewRepository', 'AIPS_Post_Review_Repository');
class_alias('AIPS\\Repository\\AuthorTopicLogsRepository', 'AIPS_Author_Topic_Logs_Repository');
class_alias('AIPS\\Repository\\VoicesRepository', 'AIPS_Voices_Repository');
class_alias('AIPS\\Repository\\NotificationsRepository', 'AIPS_Notifications_Repository');
class_alias('AIPS\\Repository\\ArticleStructureRepository', 'AIPS_Article_Structure_Repository');
class_alias('AIPS\\Repository\\TrendingTopicsRepository', 'AIPS_Trending_Topics_Repository');
class_alias('AIPS\\Repository\\AuthorsRepository', 'AIPS_Authors_Repository');
class_alias('AIPS\\Repository\\FeedbackRepository', 'AIPS_Feedback_Repository');
