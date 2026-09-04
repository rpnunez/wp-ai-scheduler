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
	 * Initialize controller and register AJAX actions.
	 */
	public function __construct(
		?AIPS_Content_Indexer_Service $indexer_service = null,
		?AIPS_Related_Posts_Service $related_service = null,
		?AIPS_Deduplication_Service $deduplication_service = null,
		?AIPS_Embeddings_Repository $embeddings_repo = null,
		?AIPS_Config $config = null
	) {
		$container = AIPS_Container::get_instance();

		$this->indexer_service       = $indexer_service ?: ($container->has(AIPS_Content_Indexer_Service::class) ? $container->make(AIPS_Content_Indexer_Service::class) : new AIPS_Content_Indexer_Service());
		$this->related_service       = $related_service ?: ($container->has(AIPS_Related_Posts_Service::class) ? $container->make(AIPS_Related_Posts_Service::class) : new AIPS_Related_Posts_Service());
		$this->deduplication_service = $deduplication_service ?: ($container->has(AIPS_Deduplication_Service::class) ? $container->make(AIPS_Deduplication_Service::class) : new AIPS_Deduplication_Service());
		$this->embeddings_repo       = $embeddings_repo ?: ($container->has(AIPS_Embeddings_Repository::class) ? $container->make(AIPS_Embeddings_Repository::class) : new AIPS_Embeddings_Repository());
		$this->config                = $config ?: AIPS_Config::get_instance();

		// Register AJAX handlers
		add_action('wp_ajax_aips_indexer_get_status', array($this, 'ajax_get_status'));
		add_action('wp_ajax_aips_indexer_process_batch', array($this, 'ajax_process_batch'));
		add_action('wp_ajax_aips_indexer_clear_index', array($this, 'ajax_clear_index'));
		add_action('wp_ajax_aips_indexer_get_graph', array($this, 'ajax_get_graph'));
		add_action('wp_ajax_aips_indexer_run_cannibalization_audit', array($this, 'ajax_run_cannibalization_audit'));
		add_action('wp_ajax_aips_indexer_save_settings', array($this, 'ajax_save_settings'));
		add_action('wp_ajax_aips_indexer_search_posts', array($this, 'ajax_search_posts'));
		add_action('wp_ajax_aips_indexer_fetch_meow_environments', array($this, 'ajax_fetch_meow_environments'));
	}

	/**
	 * Render the Content Indexer Admin Page.
	 */
	public function render_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ai-post-scheduler'));
		}

		$post_types  = (array) $this->config->get_option('aips_indexer_post_types', array('post'));
		$status      = $this->indexer_service->get_indexing_status($post_types);
		$stats       = $this->embeddings_repo->get_stats();
		$stored_dims = $this->embeddings_repo->get_stored_dimensions();
		$active_dims = (int) $this->config->get_option('aips_embeddings_dimensions', 1536);

		$dimension_mismatch = (!empty($stored_dims) && (count($stored_dims) > 1 || !in_array($active_dims, $stored_dims, true)));

		// Available public post types
		$all_post_types = get_post_types(array('public' => true), 'objects');
		unset($all_post_types['attachment']);

		$settings = array(
			'embeddings_provider'      => (string) $this->config->get_option('aips_embeddings_provider', ''),
			'embeddings_model'         => (string) $this->config->get_option('aips_embeddings_model', 'text-embedding-3-small'),
			'embeddings_env_id'        => (string) $this->config->get_option('aips_embeddings_env_id', ''),
			'embeddings_dimensions'    => $active_dims,
			'post_types'               => $post_types,
			'similarity_threshold'     => (float) $this->config->get_option('aips_indexer_similarity_threshold', 0.65),
			'auto_index_on_publish'    => (bool) $this->config->get_option('aips_auto_index_on_publish', true),
			'related_posts_enabled'    => (bool) $this->config->get_option('aips_related_posts_enabled', true),
			'related_posts_auto_append'=> (bool) $this->config->get_option('aips_related_posts_auto_append', false),
			'related_posts_count'      => (int) $this->config->get_option('aips_related_posts_count', 4),
			'related_posts_heading'    => (string) $this->config->get_option('aips_related_posts_heading', 'Related Articles'),
			'related_posts_layout'     => (string) $this->config->get_option('aips_related_posts_layout', 'grid'),
			'deduplication_mode'       => (string) $this->config->get_option('aips_deduplication_mode', 'warn'),
			'deduplication_threshold'  => (float) $this->config->get_option('aips_deduplication_threshold', 0.85),
		);

		include AIPS_PLUGIN_DIR . 'templates/admin/content-indexer.php';
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
			'status'             => $status,
			'stats'              => $stats,
			'stored_dimensions'  => $stored_dims,
			'active_dimensions'  => $active_dims,
			'dimension_mismatch' => $dimension_mismatch,
		));
	}

	/**
	 * AJAX: Process a progressive indexing batch.
	 */
	public function ajax_process_batch() {
		$this->verify_request();

		$batch_size   = isset($_POST['batch_size']) ? absint($_POST['batch_size']) : 10;
		$last_post_id = isset($_POST['last_post_id']) ? absint($_POST['last_post_id']) : 0;
		$post_types   = (array) $this->config->get_option('aips_indexer_post_types', array('post'));

		$result = $this->indexer_service->process_indexing_batch($batch_size, $last_post_id, $post_types, 'publish');

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
		$limit          = isset($_POST['limit']) ? absint($_POST['limit']) : 15;
		$min_similarity = isset($_POST['min_similarity']) ? (float) $_POST['min_similarity'] : 0.50;

		if ($post_id <= 0) {
			// If no post_id provided, pick the first indexed post
			$indexed_ids = $this->embeddings_repo->get_all_for_similarity('post', array('post'), 'publish');
			if (!empty($indexed_ids)) {
				$post_id = (int) $indexed_ids[0]->object_id;
			}
		}

		if ($post_id <= 0) {
			AIPS_Ajax_Response::error(__('No indexed posts found to visualize.', 'ai-post-scheduler'));
		}

		$graph = $this->related_service->get_graph_data_for_post($post_id, $limit, $min_similarity);

		AIPS_Ajax_Response::success(array(
			'post_id' => $post_id,
			'graph'   => $graph,
		));
	}

	/**
	 * AJAX: Run site-wide Cannibalization / Duplicate Post audit.
	 */
	public function ajax_run_cannibalization_audit() {
		$this->verify_request();

		$threshold = isset($_POST['threshold']) ? (float) $_POST['threshold'] : 0.80;
		$limit     = isset($_POST['limit']) ? absint($_POST['limit']) : 50;

		$results = $this->deduplication_service->get_cannibalization_audit_results($threshold, $limit);

		AIPS_Ajax_Response::success(array(
			'clusters' => $results,
			'count'    => count($results),
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
		foreach ($posts as $p) {
			$results[] = array(
				'id'    => $p->ID,
				'title' => $p->post_title . " ({$p->post_type} #{$p->ID})",
			);
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

		if (isset($_POST['embeddings_provider'])) {
			update_option('aips_embeddings_provider', sanitize_key($_POST['embeddings_provider']));
		}

		if (isset($_POST['embeddings_model'])) {
			update_option('aips_embeddings_model', sanitize_text_field($_POST['embeddings_model']));
		}

		if (isset($_POST['embeddings_env_id'])) {
			update_option('aips_embeddings_env_id', sanitize_text_field($_POST['embeddings_env_id']));
		}

		if (isset($_POST['embeddings_dimensions'])) {
			update_option('aips_embeddings_dimensions', max(1, absint($_POST['embeddings_dimensions'])));
		}

		if (isset($_POST['post_types']) && is_array($_POST['post_types'])) {
			$post_types = array_map('sanitize_key', $_POST['post_types']);
			update_option('aips_indexer_post_types', $post_types);
		}

		if (isset($_POST['similarity_threshold'])) {
			update_option('aips_indexer_similarity_threshold', (float) $_POST['similarity_threshold']);
		}

		if (isset($_POST['auto_index_on_publish'])) {
			$auto_index = filter_var($_POST['auto_index_on_publish'], FILTER_VALIDATE_BOOLEAN);
			update_option('aips_auto_index_on_publish', $auto_index);
		}

		if (isset($_POST['related_posts_enabled'])) {
			$rel_enabled = filter_var($_POST['related_posts_enabled'], FILTER_VALIDATE_BOOLEAN);
			update_option('aips_related_posts_enabled', $rel_enabled);
		}

		if (isset($_POST['related_posts_auto_append'])) {
			$auto_append = filter_var($_POST['related_posts_auto_append'], FILTER_VALIDATE_BOOLEAN);
			update_option('aips_related_posts_auto_append', $auto_append);
		}

		if (isset($_POST['related_posts_count'])) {
			update_option('aips_related_posts_count', max(1, min(12, absint($_POST['related_posts_count']))));
		}

		if (isset($_POST['related_posts_heading'])) {
			update_option('aips_related_posts_heading', sanitize_text_field($_POST['related_posts_heading']));
		}

		if (isset($_POST['related_posts_layout'])) {
			$layout = sanitize_key($_POST['related_posts_layout']);
			update_option('aips_related_posts_layout', in_array($layout, array('grid', 'list'), true) ? $layout : 'grid');
		}

		if (isset($_POST['deduplication_mode'])) {
			$mode = sanitize_key($_POST['deduplication_mode']);
			update_option('aips_deduplication_mode', in_array($mode, array('warn', 'block'), true) ? $mode : 'warn');
		}

		if (isset($_POST['deduplication_threshold'])) {
			update_option('aips_deduplication_threshold', (float) $_POST['deduplication_threshold']);
		}

		AIPS_Ajax_Response::success(array(
			'message' => __('Settings saved successfully.', 'ai-post-scheduler'),
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
