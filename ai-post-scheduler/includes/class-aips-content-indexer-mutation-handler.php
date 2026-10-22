<?php
/**
 * Content Indexer Mutation Handler
 *
 * Handles AJAX mutation / update endpoints for Content Indexer.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Content_Indexer_Mutation_Handler
 */
class AIPS_Content_Indexer_Mutation_Handler {

	/**
	 * @var AIPS_Content_Indexer_Service
	 */
	private $indexer_service;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * @var AIPS_Similarity_Evaluator
	 */
	private $similarity_evaluator;

	/**
	 * @var AIPS_Embeddings_Rate_Limiter
	 */
	private $rate_limiter;

	/**
	 * @var AIPS_Author_Topics_Repository
	 */
	private $topics_repo;

	/**
	 * @var AIPS_Authors_Repository
	 */
	private $authors_repo;

	public function __construct(
		?AIPS_Content_Indexer_Service $indexer_service = null,
		?AIPS_Config $config = null,
		?AIPS_Similarity_Evaluator $similarity_evaluator = null,
		?AIPS_Embeddings_Rate_Limiter $rate_limiter = null,
		?AIPS_Author_Topics_Repository $topics_repo = null,
		?AIPS_Authors_Repository $authors_repo = null
	) {
		$container = AIPS_Container::get_instance();
		$this->indexer_service      = $indexer_service ?: ($container->has(AIPS_Content_Indexer_Service::class) ? $container->make(AIPS_Content_Indexer_Service::class) : new AIPS_Content_Indexer_Service());
		$this->config               = $config ?: AIPS_Config::get_instance();
		$this->similarity_evaluator = $similarity_evaluator ?: ($container->has(AIPS_Similarity_Evaluator::class) ? $container->make(AIPS_Similarity_Evaluator::class) : new AIPS_Similarity_Evaluator());
		$this->rate_limiter         = $rate_limiter ?: ($container->has(AIPS_Embeddings_Rate_Limiter::class) ? $container->make(AIPS_Embeddings_Rate_Limiter::class) : new AIPS_Embeddings_Rate_Limiter());
		$this->topics_repo           = $topics_repo ?: ($container->has(AIPS_Author_Topics_Repository::class) ? $container->make(AIPS_Author_Topics_Repository::class) : new AIPS_Author_Topics_Repository());
		$this->authors_repo          = $authors_repo ?: ($container->has(AIPS_Authors_Repository::class) ? $container->make(AIPS_Authors_Repository::class) : new AIPS_Authors_Repository());
	}

	public function clear_index() {
		AIPS_Ajax_Guard::check_admin();

		$this->indexer_service->clear_index();

		AIPS_History_Event_Recorder::instance()->record_simple(
			AIPS_History_Event_Type::CONTENT_INDEX_CLEARED,
			true,
			__('Content index cleared', 'ai-post-scheduler'),
			'content_index_operation',
			array('event' => __('Clear content index', 'ai-post-scheduler')),
			'manual'
		);

		AIPS_Ajax_Response::success(array(
			'message' => __('Index cleared successfully.', 'ai-post-scheduler'),
		));
	}

	public function save_settings() {
		AIPS_Ajax_Guard::check_admin();

		if (isset($_POST['embeddings_enabled'])) {
			$embeddings_enabled = filter_var($_POST['embeddings_enabled'], FILTER_VALIDATE_BOOLEAN);
			$this->config->set_option('aips_embeddings_enabled', $embeddings_enabled);
		}

		if (isset($_POST['embeddings_provider'])) {
			$this->config->set_option('aips_embeddings_provider', sanitize_key($_POST['embeddings_provider']));
		}

		if (isset($_POST['embeddings_model'])) {
			$this->config->set_option('aips_embeddings_model', sanitize_text_field($_POST['embeddings_model']));
		}

		if (isset($_POST['embeddings_env_id'])) {
			$this->config->set_option('aips_embeddings_env_id', sanitize_text_field($_POST['embeddings_env_id']));
		}

		if (isset($_POST['embeddings_dimensions'])) {
			$this->config->set_option('aips_embeddings_dimensions', max(1, absint($_POST['embeddings_dimensions'])));
		}

		if (isset($_POST['post_types']) && is_array($_POST['post_types'])) {
			$post_types = array_map('sanitize_key', $_POST['post_types']);
			$this->config->set_option('aips_indexer_post_types', $post_types);
		}

		if (isset($_POST['similarity_threshold'])) {
			$this->config->set_option('aips_indexer_similarity_threshold', (float) $_POST['similarity_threshold']);
		}

		if (isset($_POST['auto_index_on_publish'])) {
			$auto_index = filter_var($_POST['auto_index_on_publish'], FILTER_VALIDATE_BOOLEAN);
			$this->config->set_option('aips_auto_index_on_publish', $auto_index);
		}

		if (isset($_POST['verbose_history'])) {
			$verbose_history = filter_var($_POST['verbose_history'], FILTER_VALIDATE_BOOLEAN);
			$this->config->set_option('aips_indexer_verbose_history', $verbose_history);
		}

		if (isset($_POST['related_posts_enabled'])) {
			$rel_enabled = filter_var($_POST['related_posts_enabled'], FILTER_VALIDATE_BOOLEAN);
			$this->config->set_option('aips_related_posts_enabled', $rel_enabled);
		}

		if (isset($_POST['related_posts_auto_append'])) {
			$auto_append = filter_var($_POST['related_posts_auto_append'], FILTER_VALIDATE_BOOLEAN);
			$this->config->set_option('aips_related_posts_auto_append', $auto_append);
		}

		if (isset($_POST['related_posts_count'])) {
			$this->config->set_option('aips_related_posts_count', max(1, min(12, absint($_POST['related_posts_count']))));
		}

		if (isset($_POST['related_posts_heading'])) {
			$this->config->set_option('aips_related_posts_heading', sanitize_text_field($_POST['related_posts_heading']));
		}

		if (isset($_POST['related_posts_layout'])) {
			$layout = sanitize_key($_POST['related_posts_layout']);
			$this->config->set_option('aips_related_posts_layout', in_array($layout, array('grid', 'list'), true) ? $layout : 'grid');
		}

		if (isset($_POST['deduplication_mode'])) {
			$mode = sanitize_key($_POST['deduplication_mode']);
			$this->config->set_option('aips_deduplication_mode', in_array($mode, array('warn', 'block'), true) ? $mode : 'warn');
		}

		if (isset($_POST['deduplication_threshold'])) {
			$this->config->set_option('aips_deduplication_threshold', (float) $_POST['deduplication_threshold']);
		}

		if (isset($_POST['publish_execution_timing'])) {
			$timing = sanitize_key($_POST['publish_execution_timing']);
			$this->config->set_option('aips_indexer_publish_execution_timing', in_array($timing, array('immediate', 'queued', 'disabled'), true) ? $timing : 'queued');
		}

		if (isset($_POST['batch_size'])) {
			$this->config->set_option('aips_indexer_batch_size', max(1, min(50, absint($_POST['batch_size']))));
		}

		if (isset($_POST['queue_debounce_seconds'])) {
			$this->config->set_option('aips_indexer_queue_debounce_seconds', max(5, min(300, absint($_POST['queue_debounce_seconds']))));
		}

		if (isset($_POST['quota_pause_enabled'])) {
			$this->config->set_option('aips_indexer_quota_pause_enabled', filter_var($_POST['quota_pause_enabled'], FILTER_VALIDATE_BOOLEAN));
		}

		if (isset($_POST['queue_notifications_enabled'])) {
			$this->config->set_option('aips_indexer_queue_notifications_enabled', filter_var($_POST['queue_notifications_enabled'], FILTER_VALIDATE_BOOLEAN));
		}

		if (isset($_POST['error_pause_duration'])) {
			$this->config->set_option('aips_indexer_error_pause_duration', max(1, absint($_POST['error_pause_duration'])));
		}

		if (isset($_POST['error_pause_unit'])) {
			$unit = sanitize_key($_POST['error_pause_unit']);
			$this->config->set_option('aips_indexer_error_pause_unit', in_array($unit, array('minutes', 'hours', 'days'), true) ? $unit : 'minutes');
		}

		if (isset($_POST['consecutive_error_threshold'])) {
			$this->config->set_option('aips_indexer_consecutive_error_threshold', max(1, min(10, absint($_POST['consecutive_error_threshold']))));
		}

		if (isset($_POST['post_cluster_threshold'])) {
			$this->config->set_option('aips_indexer_post_cluster_threshold', max(0.1, min(0.99, (float) $_POST['post_cluster_threshold'])));
		}

		if (isset($_POST['embeddings_scope'])) {
			$scope = sanitize_key($_POST['embeddings_scope']);
			$this->config->set_option('aips_embeddings_scope', in_array($scope, array('aips_only', 'all_posts'), true) ? $scope : 'aips_only');
		}

		if (isset($_POST['embeddings_rate_limits_enabled'])) {
			$this->config->set_option('aips_embeddings_rate_limits_enabled', filter_var($_POST['embeddings_rate_limits_enabled'], FILTER_VALIDATE_BOOLEAN));
		}

		if (isset($_POST['embeddings_daily_limit'])) {
			$this->config->set_option('aips_embeddings_daily_limit', absint($_POST['embeddings_daily_limit']));
		}

		if (isset($_POST['embeddings_weekly_limit'])) {
			$this->config->set_option('aips_embeddings_weekly_limit', absint($_POST['embeddings_weekly_limit']));
		}

		if (isset($_POST['embeddings_monthly_limit'])) {
			$this->config->set_option('aips_embeddings_monthly_limit', absint($_POST['embeddings_monthly_limit']));
		}

		if (isset($_POST['scan_entity_scope'])) {
			$scope = sanitize_key($_POST['scan_entity_scope']);
			$this->config->set_option('aips_indexer_scan_entity_scope', in_array($scope, array('all', 'posts', 'topics'), true) ? $scope : 'all');
		}

		if (isset($_POST['topics_continuous_sync'])) {
			$this->config->set_option('aips_indexer_topics_continuous_sync', filter_var($_POST['topics_continuous_sync'], FILTER_VALIDATE_BOOLEAN));
		}

		if (isset($_POST['topics_execution_timing'])) {
			$timing = sanitize_key($_POST['topics_execution_timing']);
			$this->config->set_option('aips_indexer_topics_execution_timing', in_array($timing, array('immediate', 'queued'), true) ? $timing : 'immediate');
		}

		AIPS_Ajax_Response::success(array(
			'message' => __('Settings saved successfully.', 'ai-post-scheduler'),
		));
	}

	public function save_pillar() {
		AIPS_Ajax_Guard::check_admin();

		$cluster_id     = isset($_POST['cluster_id']) ? sanitize_text_field($_POST['cluster_id']) : '';
		$pillar_post_id = isset($_POST['pillar_post_id']) ? absint($_POST['pillar_post_id']) : 0;

		if (empty($cluster_id) || $pillar_post_id <= 0) {
			AIPS_Ajax_Response::error(__('Missing cluster ID or valid pillar post ID.', 'ai-post-scheduler'));
		}

		$success = $this->similarity_evaluator->set_pillar_post($cluster_id, $pillar_post_id);

		if ($success) {
			AIPS_Ajax_Response::success(array('message' => __('Pillar post designated successfully.', 'ai-post-scheduler')));
		} else {
			AIPS_Ajax_Response::error(__('Failed to save pillar post designation.', 'ai-post-scheduler'));
		}
	}

	public function rename_post_cluster() {
		AIPS_Ajax_Guard::check_admin();

		$cluster_id  = isset($_POST['cluster_id']) ? sanitize_text_field($_POST['cluster_id']) : '';
		$custom_name = isset($_POST['custom_name']) ? sanitize_text_field($_POST['custom_name']) : '';

		if (empty($cluster_id) || empty($custom_name)) {
			AIPS_Ajax_Response::error(__('Cluster ID and custom name are required.', 'ai-post-scheduler'));
		}

		$this->similarity_evaluator->rename_post_cluster($cluster_id, $custom_name);

		AIPS_Ajax_Response::success(array('message' => __('Cluster renamed successfully.', 'ai-post-scheduler')));
	}

	public function commit_gap_topics() {
		AIPS_Ajax_Guard::check_admin();

		$author_id = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;
		$topics    = isset($_POST['topics']) && is_array($_POST['topics']) ? array_map('sanitize_text_field', $_POST['topics']) : array();

		if ($author_id <= 0) {
			AIPS_Ajax_Response::error(__('Please select a valid author.', 'ai-post-scheduler'));
		}

		if (empty($topics)) {
			AIPS_Ajax_Response::error(__('No topics provided to commit.', 'ai-post-scheduler'));
		}

		$author = $this->authors_repo->get_by_id($author_id);
		if (!$author) {
			AIPS_Ajax_Response::error(__('Selected author does not exist.', 'ai-post-scheduler'));
		}

		$inserted = 0;
		foreach ($topics as $title) {
			$title = trim($title);
			if (empty($title)) {
				continue;
			}

			$id = $this->topics_repo->create(array(
				'author_id'    => $author_id,
				'topic_title'  => $title,
				'status'       => 'pending',
				'generated_at' => AIPS_DateTime::now()->timestamp(),
			));

			if ($id) {
				$inserted++;
			}
		}

		AIPS_Ajax_Response::success(array(
			'message'  => sprintf(
				__('Successfully committed %1$d topic(s) to author "%2$s".', 'ai-post-scheduler'),
				$inserted,
				$author->name
			),
			'inserted' => $inserted,
		));
	}

	public function resume_cooldown() {
		AIPS_Ajax_Guard::check_admin();

		$this->rate_limiter->clear_cooldown();

		AIPS_Ajax_Response::success(array(
			'message'  => __('Cooldown cleared. Indexing operations have been resumed.', 'ai-post-scheduler'),
			'cooldown' => $this->rate_limiter->get_cooldown_status(),
		));
	}
}
