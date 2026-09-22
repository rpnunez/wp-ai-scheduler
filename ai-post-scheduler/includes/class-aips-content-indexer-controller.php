<?php
/**
 * Content Indexer Controller
 *
 * Handles Admin UI rendering and AJAX actions for Content Indexing,
 * Semantic Graph Visualization, Cannibalization Audits, and Embeddings Configuration.
 *
 * @package AI_Post_Scheduler
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Content_Indexer_Controller
 */
class AIPS_Content_Indexer_Controller {

	/**
	 * @var AIPS_Content_Indexer_Service
	 */
	private $indexer_service;

	/**
	 * @var AIPS_Related_Posts_Service
	 */
	private $related_service;

	/**
	 * @var AIPS_Deduplication_Service
	 */
	private $deduplication_service;

	/**
	 * @var AIPS_Embeddings_Repository
	 */
	private $embeddings_repo;

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

	/**
	 * Initialize controller and register AJAX actions.
	 */
	public function __construct(
		?AIPS_Content_Indexer_Service $indexer_service = null,
		?AIPS_Related_Posts_Service $related_service = null,
		?AIPS_Deduplication_Service $deduplication_service = null,
		?AIPS_Embeddings_Repository $embeddings_repo = null,
		?AIPS_Config $config = null,
		?AIPS_Similarity_Evaluator $similarity_evaluator = null,
		?AIPS_Embeddings_Rate_Limiter $rate_limiter = null,
		?AIPS_Author_Topics_Repository $topics_repo = null,
		?AIPS_Authors_Repository $authors_repo = null
	) {
		$container = AIPS_Container::get_instance();

		$this->indexer_service       = $indexer_service ?: ($container->has(AIPS_Content_Indexer_Service::class) ? $container->make(AIPS_Content_Indexer_Service::class) : new AIPS_Content_Indexer_Service());
		$this->related_service       = $related_service ?: ($container->has(AIPS_Related_Posts_Service::class) ? $container->make(AIPS_Related_Posts_Service::class) : new AIPS_Related_Posts_Service());
		$this->deduplication_service = $deduplication_service ?: ($container->has(AIPS_Deduplication_Service::class) ? $container->make(AIPS_Deduplication_Service::class) : new AIPS_Deduplication_Service());
		$this->embeddings_repo       = $embeddings_repo ?: ($container->has(AIPS_Embeddings_Repository::class) ? $container->make(AIPS_Embeddings_Repository::class) : new AIPS_Embeddings_Repository());
		$this->config                = $config ?: AIPS_Config::get_instance();
		$this->similarity_evaluator  = $similarity_evaluator ?: ($container->has(AIPS_Similarity_Evaluator::class) ? $container->make(AIPS_Similarity_Evaluator::class) : new AIPS_Similarity_Evaluator());
		$this->rate_limiter          = $rate_limiter ?: ($container->has(AIPS_Embeddings_Rate_Limiter::class) ? $container->make(AIPS_Embeddings_Rate_Limiter::class) : new AIPS_Embeddings_Rate_Limiter());
		$this->topics_repo           = $topics_repo ?: ($container->has(AIPS_Author_Topics_Repository::class) ? $container->make(AIPS_Author_Topics_Repository::class) : new AIPS_Author_Topics_Repository());
		$this->authors_repo          = $authors_repo ?: ($container->has(AIPS_Authors_Repository::class) ? $container->make(AIPS_Authors_Repository::class) : new AIPS_Authors_Repository());

		// Register AJAX handlers
		add_action('wp_ajax_aips_indexer_get_status', array($this, 'ajax_get_status'));
		add_action('wp_ajax_aips_indexer_process_batch', array($this, 'ajax_process_batch'));
		add_action('wp_ajax_aips_indexer_clear_index', array($this, 'ajax_clear_index'));
		add_action('wp_ajax_aips_indexer_get_graph', array($this, 'ajax_get_graph'));
		add_action('wp_ajax_aips_indexer_run_cannibalization_audit', array($this, 'ajax_run_cannibalization_audit'));
		add_action('wp_ajax_aips_indexer_save_settings', array($this, 'ajax_save_settings'));
		add_action('wp_ajax_aips_indexer_search_posts', array($this, 'ajax_search_posts'));
		add_action('wp_ajax_aips_indexer_fetch_meow_environments', array($this, 'ajax_fetch_meow_environments'));
		add_action('wp_ajax_aips_indexer_get_post_clusters', array($this, 'ajax_get_post_clusters'));
		add_action('wp_ajax_aips_indexer_save_pillar', array($this, 'ajax_save_pillar'));
		add_action('wp_ajax_aips_indexer_rename_post_cluster', array($this, 'ajax_rename_post_cluster'));
		add_action('wp_ajax_aips_indexer_generate_gap_ideas', array($this, 'ajax_generate_gap_ideas'));
		add_action('wp_ajax_aips_indexer_commit_gap_topics', array($this, 'ajax_commit_gap_topics'));
		add_action('wp_ajax_aips_indexer_resume_cooldown', array($this, 'ajax_resume_cooldown'));
	}

	/**
	 * Prepare shared view data for Content Intelligence templates.
	 *
	 * @return array
	 */
	private function get_view_data(): array {
		$post_types      = (array) $this->config->get_option('aips_indexer_post_types', array('post'));
		$status          = $this->indexer_service->get_indexing_status($post_types);
		$stats           = $this->embeddings_repo->get_stats();
		$stored_dims     = $this->embeddings_repo->get_stored_dimensions();
		$active_dims     = (int) $this->config->get_option('aips_embeddings_dimensions', 1536);
		$cooldown_status = $this->rate_limiter->get_cooldown_status();
		$queue_status    = $this->indexer_service->get_queue_status();
		$authors         = $this->authors_repo->get_all(true);

		$dimension_mismatch = (!empty($stored_dims) && (count($stored_dims) > 1 || !in_array($active_dims, $stored_dims, true)));

		// Available public post types
		$all_post_types = get_post_types(array('public' => true), 'objects');
		unset($all_post_types['attachment']);

		$settings = array(
			'embeddings_enabled'             => (bool) $this->config->get_option('aips_embeddings_enabled', true),
			'embeddings_provider'            => (string) $this->config->get_option('aips_embeddings_provider', ''),
			'embeddings_model'               => (string) $this->config->get_option('aips_embeddings_model', 'text-embedding-3-small'),
			'embeddings_env_id'              => (string) $this->config->get_option('aips_embeddings_env_id', ''),
			'embeddings_dimensions'          => $active_dims,
			'post_types'                     => $post_types,
			'similarity_threshold'           => (float) $this->config->get_option('aips_indexer_similarity_threshold', 0.65),
			'auto_index_on_publish'          => (bool) $this->config->get_option('aips_auto_index_on_publish', true),
			'verbose_history'                => (bool) $this->config->get_option('aips_indexer_verbose_history', false),
			'embeddings_scope'               => (string) $this->config->get_option('aips_embeddings_scope', 'aips_only'),
			'embeddings_rate_limits_enabled' => (bool) $this->config->get_option('aips_embeddings_rate_limits_enabled', true),
			'embeddings_daily_limit'         => (int) $this->config->get_option('aips_embeddings_daily_limit', 50),
			'embeddings_weekly_limit'        => (int) $this->config->get_option('aips_embeddings_weekly_limit', 200),
			'embeddings_monthly_limit'       => (int) $this->config->get_option('aips_embeddings_monthly_limit', 500),
			'rate_limits'                    => isset($status['rate_limits']) ? $status['rate_limits'] : array(),
			'related_posts_enabled'          => (bool) $this->config->get_option('aips_related_posts_enabled', true),
			'related_posts_auto_append'      => (bool) $this->config->get_option('aips_related_posts_auto_append', false),
			'related_posts_count'            => (int) $this->config->get_option('aips_related_posts_count', 4),
			'related_posts_heading'          => (string) $this->config->get_option('aips_related_posts_heading', 'Related Articles'),
			'related_posts_layout'           => (string) $this->config->get_option('aips_related_posts_layout', 'grid'),
			'deduplication_mode'             => (string) $this->config->get_option('aips_deduplication_mode', 'warn'),
			'deduplication_threshold'        => (float) $this->config->get_option('aips_deduplication_threshold', 0.85),
			'publish_execution_timing'       => (string) $this->config->get_option('aips_indexer_publish_execution_timing', 'queued'),
			'batch_size'                     => (int) $this->config->get_option('aips_indexer_batch_size', 10),
			'queue_debounce_seconds'         => (int) $this->config->get_option('aips_indexer_queue_debounce_seconds', 15),
			'quota_pause_enabled'            => (bool) $this->config->get_option('aips_indexer_quota_pause_enabled', true),
			'queue_notifications_enabled'    => (bool) $this->config->get_option('aips_indexer_queue_notifications_enabled', true),
			'error_pause_duration'           => (int) $this->config->get_option('aips_indexer_error_pause_duration', 30),
			'error_pause_unit'               => (string) $this->config->get_option('aips_indexer_error_pause_unit', 'minutes'),
			'consecutive_error_threshold'    => (int) $this->config->get_option('aips_indexer_consecutive_error_threshold', 2),
			'post_cluster_threshold'         => (float) $this->config->get_option('aips_indexer_post_cluster_threshold', 0.65),
			'scan_entity_scope'              => (string) $this->config->get_option('aips_indexer_scan_entity_scope', 'all'),
			'topics_continuous_sync'         => (bool) $this->config->get_option('aips_indexer_topics_continuous_sync', true),
			'topics_execution_timing'        => (string) $this->config->get_option('aips_indexer_topics_execution_timing', 'immediate'),
			'cooldown'                       => $cooldown_status,
			'queue_status'                   => $queue_status,
		);

		return compact(
			'status',
			'stats',
			'stored_dims',
			'active_dims',
			'cooldown_status',
			'queue_status',
			'authors',
			'dimension_mismatch',
			'all_post_types',
			'settings'
		);
	}

	/**
	 * Render the primary Content Intelligence Hub Admin Page (Graph Visualizer & Health).
	 */
	public function render_intelligence_hub() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ai-post-scheduler'));
		}

		extract($this->get_view_data());
		include AIPS_PLUGIN_DIR . 'templates/admin/content-intelligence.php';
	}

	/**
	 * Render the dedicated Topic Clusters & Content Gaps Admin Page.
	 */
	public function render_clusters_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ai-post-scheduler'));
		}

		extract($this->get_view_data());
		include AIPS_PLUGIN_DIR . 'templates/admin/content-intelligence-clusters.php';
	}

	/**
	 * Render the dedicated Cannibalization & Semantic Duplicate Audit Admin Page.
	 */
	public function render_cannibalization_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ai-post-scheduler'));
		}

		extract($this->get_view_data());
		include AIPS_PLUGIN_DIR . 'templates/admin/content-intelligence-cannibalization.php';
	}

	/**
	 * Render the Content Indexer Admin Page (legacy backward compatibility).
	 */
	public function render_page() {
		$this->render_intelligence_hub();
	}

	/**
	 * AJAX: Get current indexing status and metrics.
	 */
	public function ajax_get_status() {
		$this->verify_request();

		$post_types  = (array) $this->config->get_option('aips_indexer_post_types', array('post'));
		$status      = $this->indexer_service->get_indexing_status($post_types);
		$stats       = $this->embeddings_repo->get_stats();
		$stored_dims = $this->embeddings_repo->get_stored_dimensions();
		$active_dims = (int) $this->config->get_option('aips_embeddings_dimensions', 1536);

		$dimension_mismatch = (!empty($stored_dims) && (count($stored_dims) > 1 || !in_array($active_dims, $stored_dims, true)));

		AIPS_Ajax_Response::success(array(
			'embeddings_enabled' => (bool) $this->config->get_option('aips_embeddings_enabled', true),
			'status'             => $status,
			'stats'              => $stats,
			'stored_dimensions'  => $stored_dims,
			'active_dimensions'  => $active_dims,
			'dimension_mismatch' => $dimension_mismatch,
			'cooldown'           => $this->rate_limiter->get_cooldown_status(),
			'queue_status'       => $this->indexer_service->get_queue_status(),
		));
	}

	/**
	 * AJAX: Process a progressive indexing batch.
	 */
	public function ajax_process_batch() {
		$this->verify_request();

		if (!$this->config->get_option('aips_embeddings_enabled', true)) {
			AIPS_Ajax_Response::error(__('The vector embeddings system is disabled in settings.', 'ai-post-scheduler'));
		}

		$batch_size   = isset($_POST['batch_size']) ? absint($_POST['batch_size']) : 10;
		$last_post_id = isset($_POST['last_post_id']) ? absint($_POST['last_post_id']) : 0;
		$entity_scope = isset($_POST['entity_scope']) ? sanitize_key($_POST['entity_scope']) : (string) $this->config->get_option('aips_indexer_scan_entity_scope', 'all');
		$post_types   = (array) $this->config->get_option('aips_indexer_post_types', array('post'));

		$result = $this->indexer_service->process_indexing_batch($batch_size, $last_post_id, $post_types, 'publish', $entity_scope);

		AIPS_Ajax_Response::success($result);
	}

	/**
	 * AJAX: Clear all embeddings and relationships index.
	 */
	public function ajax_clear_index() {
		$this->verify_request();

		$this->indexer_service->clear_index();

		AIPS_Ajax_Response::success(array(
			'message' => __('Index cleared successfully.', 'ai-post-scheduler'),
		));
	}

	/**
	 * AJAX: Fetch graph data for a specific post.
	 */
	public function ajax_get_graph() {
		$this->verify_request();

		$post_id        = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$source_type    = isset($_POST['source_type']) ? sanitize_key($_POST['source_type']) : 'post';
		$limit          = isset($_POST['limit']) ? absint($_POST['limit']) : 15;
		$min_similarity = isset($_POST['min_similarity']) ? (float) $_POST['min_similarity'] : 0.50;

		if ($post_id <= 0) {
			if ('topic' === $source_type) {
				$indexed_topics = $this->embeddings_repo->get_all_topics_for_similarity();
				if (!empty($indexed_topics)) {
					$post_id = (int) $indexed_topics[0]->object_id;
				}
			} else {
				$indexed_ids = $this->embeddings_repo->get_all_for_similarity('post', array('post'), 'publish');
				if (!empty($indexed_ids)) {
					$post_id = (int) $indexed_ids[0]->object_id;
				}
			}
		}

		if ($post_id <= 0) {
			AIPS_Ajax_Response::error(__('No indexed items found to visualize.', 'ai-post-scheduler'));
		}

		$graph = $this->related_service->get_graph_data_for_post($post_id, $limit, $min_similarity, $source_type);

		AIPS_Ajax_Response::success(array(
			'post_id'     => $post_id,
			'source_type' => $source_type,
			'graph'       => $graph,
		));
	}

	/**
	 * AJAX: Run site-wide Cannibalization / Duplicate Post audit.
	 */
	public function ajax_run_cannibalization_audit() {
		$this->verify_request();

		$threshold   = isset($_POST['threshold']) ? (float) $_POST['threshold'] : 0.80;
		$limit       = isset($_POST['limit']) ? absint($_POST['limit']) : 50;
		$entity_type = isset($_POST['entity_type']) ? sanitize_key($_POST['entity_type']) : 'all';

		$results = $this->deduplication_service->get_cannibalization_audit_results($threshold, $limit, $entity_type);

		AIPS_Ajax_Response::success(array(
			'clusters'    => $results,
			'count'       => count($results),
			'entity_type' => $entity_type,
		));
	}

	/**
	 * AJAX: Search indexed posts for graph visualizer selector.
	 */
	public function ajax_search_posts() {
		$this->verify_request();

		$query = isset($_POST['q']) ? sanitize_text_field($_POST['q']) : '';
		if (empty($query)) {
			AIPS_Ajax_Response::success(array('results' => array()));
			return;
		}

		$posts = get_posts(array(
			'post_type'      => $this->config->get_option('aips_indexer_post_types', array('post')),
			'post_status'    => 'publish',
			's'              => $query,
			'posts_per_page' => 15,
		));

		$results = array();
		if (!empty($posts)) {
			$post_ids    = wp_list_pluck($posts, 'ID');
			$indexed_map = $this->embeddings_repo->get_by_post_ids($post_ids);

			foreach ($posts as $p) {
				$is_indexed = isset($indexed_map[$p->ID]);
				$results[]  = array(
					'id'         => $p->ID,
					'title'      => $p->post_title,
					'label'      => $p->post_title . " ({$p->post_type} #{$p->ID})",
					'post_type'  => $p->post_type,
					'is_indexed' => $is_indexed,
				);
			}
		}

		AIPS_Ajax_Response::success(array('results' => $results));
	}

	/**
	 * AJAX: Fetch configured embedding environments / connections from Meow Apps AI Engine.
	 */
	public function ajax_fetch_meow_environments() {
		$this->verify_request();

		$meow = new AIPS_Meow_AI_Provider();
		if (!$meow->is_available()) {
			AIPS_Ajax_Response::error(__('Meow Apps AI Engine is not available.', 'ai-post-scheduler'));
		}

		$environments = $meow->get_embeddings_environments();

		AIPS_Ajax_Response::success(array(
			'environments' => $environments,
			'count'        => count($environments),
		));
	}

	/**
	 * AJAX: Save Content Indexer and Related Posts configuration.
	 */
	public function ajax_save_settings() {
		$this->verify_request();

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

	/**
	 * AJAX: Get post clusters and orphan posts.
	 */
	public function ajax_get_post_clusters() {
		$this->verify_request();

		$threshold = isset($_POST['threshold']) ? (float) $_POST['threshold'] : (float) $this->config->get_option('aips_indexer_post_cluster_threshold', 0.65);
		$clusters = $this->similarity_evaluator->detect_post_clusters($threshold);
		$orphans  = $this->similarity_evaluator->get_orphan_posts($threshold);

		AIPS_Ajax_Response::success(array(
			'clusters' => array_values($clusters),
			'orphans'  => $orphans,
			'count'    => count($clusters),
		));
	}

	/**
	 * AJAX: Designate a pillar post for a post cluster.
	 */
	public function ajax_save_pillar() {
		$this->verify_request();

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

	/**
	 * AJAX: Rename a post cluster.
	 */
	public function ajax_rename_post_cluster() {
		$this->verify_request();

		$cluster_id  = isset($_POST['cluster_id']) ? sanitize_text_field($_POST['cluster_id']) : '';
		$custom_name = isset($_POST['custom_name']) ? sanitize_text_field($_POST['custom_name']) : '';

		if (empty($cluster_id) || empty($custom_name)) {
			AIPS_Ajax_Response::error(__('Cluster ID and custom name are required.', 'ai-post-scheduler'));
		}

		$this->similarity_evaluator->rename_post_cluster($cluster_id, $custom_name);

		AIPS_Ajax_Response::success(array('message' => __('Cluster renamed successfully.', 'ai-post-scheduler')));
	}

	/**
	 * AJAX: Generate AI content gap suggestions for a post cluster.
	 */
	public function ajax_generate_gap_ideas() {
		$this->verify_request();

		$cluster_id = isset($_POST['cluster_id']) ? sanitize_text_field($_POST['cluster_id']) : '';
		$post_id    = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

		if ($post_id <= 0 && !empty($cluster_id)) {
			$clusters = (array) $this->config->get_option('aips_post_clusters', array());
			if (isset($clusters[$cluster_id]['pillar_id'])) {
				$post_id = (int) $clusters[$cluster_id]['pillar_id'];
			}
		}

		if ($post_id <= 0) {
			AIPS_Ajax_Response::error(__('Missing target post ID or valid cluster pillar post ID.', 'ai-post-scheduler'));
		}

		$suggestions = $this->similarity_evaluator->generate_gap_suggestions($post_id, $cluster_id);

		if (is_wp_error($suggestions)) {
			AIPS_Ajax_Response::error($suggestions->get_error_message());
		}

		AIPS_Ajax_Response::success(array(
			'cluster_id'  => $cluster_id,
			'suggestions' => $suggestions,
			'count'       => count($suggestions),
		));
	}

	/**
	 * AJAX: Commit content gap ideas directly into Author Topics pending approval.
	 */
	public function ajax_commit_gap_topics() {
		$this->verify_request();

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
				/* translators: 1: number of topics, 2: author name */
				__('Successfully committed %1$d topic(s) to author "%2$s".', 'ai-post-scheduler'),
				$inserted,
				$author->name
			),
			'inserted' => $inserted,
		));
	}

	/**
	 * AJAX: Clear active cooldown and resume indexing.
	 */
	public function ajax_resume_cooldown() {
		$this->verify_request();

		$this->rate_limiter->clear_cooldown();

		AIPS_Ajax_Response::success(array(
			'message'  => __('Cooldown cleared. Indexing operations have been resumed.', 'ai-post-scheduler'),
			'cooldown' => $this->rate_limiter->get_cooldown_status(),
		));
	}

	/**
	 * Verify nonce and capability for AJAX calls.
	 */
	private function verify_request() {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Security check failed. Please refresh the page.', 'ai-post-scheduler'), 'invalid_nonce');
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::error(__('Permission denied.', 'ai-post-scheduler'), 'forbidden');
		}
	}
}

if (!class_exists('AIPS_Content_Intelligence_Controller')) {
	class_alias(AIPS_Content_Indexer_Controller::class, 'AIPS_Content_Intelligence_Controller');
}

