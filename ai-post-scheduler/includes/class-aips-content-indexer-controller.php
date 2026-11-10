<?php
/**
 * Content Indexer Controller
 *
 * Handles Admin UI rendering and coordinates query, mutation, and job handlers
 * for Content Indexing, Semantic Graph Visualization, Cannibalization Audits,
 * and Embeddings Configuration.
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
	 * @var AIPS_Content_Indexer_Query_Handler
	 */
	private $query_handler;

	/**
	 * @var AIPS_Content_Indexer_Mutation_Handler
	 */
	private $mutation_handler;

	/**
	 * @var AIPS_Content_Indexer_Job_Handler
	 */
	private $job_handler;

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

		$this->query_handler = new AIPS_Content_Indexer_Query_Handler(
			$this->indexer_service,
			$this->related_service,
			$this->embeddings_repo,
			$this->config,
			$this->similarity_evaluator,
			$this->rate_limiter
		);

		$this->mutation_handler = new AIPS_Content_Indexer_Mutation_Handler(
			$this->indexer_service,
			$this->config,
			$this->similarity_evaluator,
			$this->rate_limiter,
			$this->topics_repo,
			$this->authors_repo
		);

		$this->job_handler = new AIPS_Content_Indexer_Job_Handler(
			$this->indexer_service,
			$this->deduplication_service,
			$this->similarity_evaluator,
			$this->config
		);

		// Canonical hook registration
		AIPS_Ajax_Registry::register_controller_actions($this);
	}

	/**
	 * Prepare shared base view data and banner alerts for Content Intelligence pages.
	 *
	 * @return array
	 */
	public function get_shared_view_data(): array {
		$post_types = (array) $this->config->get_option('aips_indexer_post_types', array('post'));

		try {
			$status = $this->indexer_service->get_indexing_status($post_types);
		} catch (\Throwable $e) {
			$status = array(
				'total_posts'        => 0,
				'indexed'            => 0,
				'unindexed'          => 0,
				'percent'            => 0,
				'total_topics'       => 0,
				'indexed_topics'     => 0,
				'unindexed_topics'   => 0,
				'topics_percent'     => 0,
				'posts'              => array('total' => 0, 'indexed' => 0, 'unindexed' => 0, 'percent' => 0),
				'topics'             => array('total' => 0, 'indexed' => 0, 'unindexed' => 0, 'percent' => 0),
				'combined'           => array('total' => 0, 'indexed' => 0, 'unindexed' => 0, 'percent' => 0),
				'post_types'         => $post_types,
				'scope'              => 'aips_only',
				'embeddings_enabled' => true,
				'rate_limits'        => array(),
			);
		}

		try {
			$stats = $this->embeddings_repo->get_stats();
		} catch (\Throwable $e) {
			$stats = array(
				'total'        => 0,
				'posts'        => 0,
				'topics'       => 0,
				'by_post_type' => array(),
				'models'       => array(),
			);
		}

		try {
			$stored_dims = $this->embeddings_repo->get_stored_dimensions();
		} catch (\Throwable $e) {
			$stored_dims = array();
		}

		$active_dims = (int) $this->config->get_option('aips_embeddings_dimensions', 1536);

		try {
			$cooldown_status = $this->rate_limiter->get_cooldown_status();
		} catch (\Throwable $e) {
			$cooldown_status = array(
				'active'              => false,
				'remaining_seconds'   => 0,
				'remaining_formatted' => '00:00',
				'until'               => 0,
				'reason'              => '',
			);
		}

		try {
			$queue_status = $this->indexer_service->get_queue_status();
		} catch (\Throwable $e) {
			$queue_status = array('is_running' => false, 'pending_count' => 0);
		}

		$dimension_mismatch = (!empty($stored_dims) && (count($stored_dims) > 1 || !in_array($active_dims, $stored_dims, true)));

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

		$dim_mismatch_message = '';
		if ($dimension_mismatch) {
			$dim_mismatch_message = sprintf(
				esc_html__('Stored vector embeddings use %1$s dimensions, but your active environment is configured for %2$s dimensions. Cosine similarity comparisons cannot cross mismatched dimensions.', 'ai-post-scheduler'),
				'<strong>' . esc_html(implode(', ', (array) $stored_dims)) . '</strong>',
				'<strong>' . esc_html((string) $active_dims) . '</strong>'
			);
		}

		$rate_limits_info   = isset($status['rate_limits']) ? $status['rate_limits'] : array();
		$is_rate_limited    = !empty($rate_limits_info['is_rate_limited']);
		$exceeded_limit     = !empty($rate_limits_info['exceeded_limit']) ? (string) $rate_limits_info['exceeded_limit'] : '';
		$rate_limit_message = '';
		if ($is_rate_limited) {
			if (!empty($exceeded_limit)) {
				$rate_limit_message = sprintf(
					esc_html__('The %1$s vector embedding rate limit quota has been reached to protect your API budget. Scanning is paused.', 'ai-post-scheduler'),
					esc_html($exceeded_limit)
				);
			} else {
				$rate_limit_message = esc_html__('Vector embedding rate limit quota reached. Scanning paused.', 'ai-post-scheduler');
			}
		}

		$is_cooldown_active = !empty($cooldown_status['active']);
		$cooldown_remaining = isset($cooldown_status['remaining_seconds']) ? (int) $cooldown_status['remaining_seconds'] : 0;
		$cooldown_until     = isset($cooldown_status['until']) ? (int) $cooldown_status['until'] : 0;
		$cooldown_reason    = isset($cooldown_status['reason']) ? (string) $cooldown_status['reason'] : '';

		$cooldown_message   = '';
		if (!empty($cooldown_reason)) {
			$cooldown_message = sprintf(
				esc_html__('Remote provider reported: "%s". Operations are temporarily halted to respect remote rate limits.', 'ai-post-scheduler'),
				esc_html($cooldown_reason)
			);
		} else {
			$cooldown_message = esc_html__('Remote rate limits encountered. Operations are temporarily paused.', 'ai-post-scheduler');
		}

		$banners = array(
			'embeddings_disabled' => empty($settings['embeddings_enabled']),
			'dimension_mismatch'  => array(
				'active'      => $dimension_mismatch,
				'stored_dims' => $stored_dims,
				'active_dims' => $active_dims,
				'message'     => $dim_mismatch_message,
			),
			'rate_limit'          => array(
				'active'         => $is_rate_limited,
				'exceeded_limit' => $exceeded_limit,
				'message'        => $rate_limit_message,
			),
			'cooldown'            => array(
				'active'              => $is_cooldown_active,
				'remaining_seconds'   => $cooldown_remaining,
				'remaining_formatted' => gmdate('i:s', $cooldown_remaining),
				'until'               => $cooldown_until,
				'reason'              => $cooldown_reason,
				'message'             => $cooldown_message,
			),
		);

		return compact(
			'status',
			'stats',
			'stored_dims',
			'active_dims',
			'cooldown_status',
			'queue_status',
			'dimension_mismatch',
			'settings',
			'banners'
		);
	}

	public function get_intelligence_hub_view_data(): array {
		$shared = $this->get_shared_view_data();
		extract($shared);

		$total_posts    = isset($status['total_posts']) ? (int) $status['total_posts'] : 0;
		$indexed        = isset($status['indexed']) ? (int) $status['indexed'] : 0;
		$unindexed      = isset($status['unindexed']) ? (int) $status['unindexed'] : 0;
		$percent        = isset($status['percent']) ? (int) $status['percent'] : 0;

		$total_topics       = isset($status['total_topics']) ? (int) $status['total_topics'] : 0;
		$indexed_topics     = isset($status['indexed_topics']) ? (int) $status['indexed_topics'] : 0;
		$unindexed_topics   = isset($status['unindexed_topics']) ? (int) $status['unindexed_topics'] : 0;
		$topics_percent     = isset($status['topics_percent']) ? (int) $status['topics_percent'] : 0;
		$combined_unindexed = $unindexed + $unindexed_topics;

		$active_model = !empty($stats['models']) ? $stats['models'][0]->model : 'Default (AI Engine)';
		$active_dims  = !empty($stats['models']) ? (int) $stats['models'][0]->dimensions : $active_dims;

		$metrics = array(
			'total_posts'               => $total_posts,
			'indexed'                   => $indexed,
			'unindexed'                 => $unindexed,
			'percent'                   => $percent,
			'total_topics'              => $total_topics,
			'indexed_topics'            => $indexed_topics,
			'unindexed_topics'          => $unindexed_topics,
			'topics_percent'            => $topics_percent,
			'combined_unindexed'        => $combined_unindexed,
			'unindexed_breakdown_label' => sprintf(
				esc_html__('%1$d posts, %2$d topics pending', 'ai-post-scheduler'),
				$unindexed,
				$unindexed_topics
			),
			'active_model'              => $active_model,
			'active_dims'               => $active_dims,
		);

		$all_post_types = get_post_types(array('public' => true), 'objects');
		if (isset($all_post_types['attachment'])) {
			unset($all_post_types['attachment']);
		}

		$post_type_breakdown = array();
		if (is_array($all_post_types)) {
			foreach ($all_post_types as $pt_slug => $pt_obj) {
				$in_scope     = in_array($pt_slug, $settings['post_types'], true);
				$pt_counts    = wp_count_posts($pt_slug);
				$pt_published = (is_object($pt_counts) && isset($pt_counts->publish)) ? (int) $pt_counts->publish : 0;
				$pt_indexed   = isset($stats['by_post_type'][$pt_slug]) ? (int) $stats['by_post_type'][$pt_slug] : 0;
				$pt_pct       = $pt_published > 0 ? (int) min(100, round(($pt_indexed / $pt_published) * 100)) : 0;
				$label        = (is_object($pt_obj) && isset($pt_obj->labels->singular_name)) ? $pt_obj->labels->singular_name : (string) $pt_slug;

				$post_type_breakdown[] = array(
					'slug'             => $pt_slug,
					'label'            => $label,
					'in_scope'         => $in_scope,
					'published_count'  => $pt_published,
					'indexed_count'    => $pt_indexed,
					'coverage_percent' => $pt_pct,
				);
			}
		}

		return array_merge($shared, compact('metrics', 'post_type_breakdown', 'all_post_types'));
	}

	public function get_clusters_view_data(): array {
		$shared = $this->get_shared_view_data();
		extract($shared);

		$cluster_threshold = isset($settings['post_cluster_threshold']) ? (float) $settings['post_cluster_threshold'] : 0.65;
		$cluster_config    = array(
			'threshold'         => $cluster_threshold,
			'threshold_percent' => (int) round($cluster_threshold * 100),
			'min_size'          => max(2, (int) $this->config->get_option('aips_cluster_default_min_size', 3)),
		);

		try {
			$authors = $this->authors_repo->get_all(true);
		} catch (\Throwable $e) {
			$authors = array();
		}

		return array_merge($shared, compact('cluster_config', 'authors'));
	}

	public function get_cannibalization_view_data(): array {
		return $this->get_shared_view_data();
	}

	public function render_intelligence_hub() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ai-post-scheduler'));
		}

		extract($this->get_intelligence_hub_view_data());
		include AIPS_PLUGIN_DIR . 'templates/admin/content-intelligence.php';
	}

	public function render_clusters_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ai-post-scheduler'));
		}

		extract($this->get_clusters_view_data());
		include AIPS_PLUGIN_DIR . 'templates/admin/content-intelligence-clusters.php';
	}

	public function render_cannibalization_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ai-post-scheduler'));
		}

		extract($this->get_cannibalization_view_data());
		include AIPS_PLUGIN_DIR . 'templates/admin/content-intelligence-cannibalization.php';
	}

	public function render_page() {
		$this->render_intelligence_hub();
	}

	// --- Query endpoints ---

	public function ajax_get_status() {
		return $this->query_handler->get_status();
	}

	public function ajax_get_graph() {
		return $this->query_handler->get_graph();
	}

	public function ajax_search_posts() {
		return $this->query_handler->search_posts();
	}

	public function ajax_fetch_meow_environments() {
		return $this->query_handler->fetch_meow_environments();
	}

	public function ajax_get_post_clusters() {
		return $this->query_handler->get_post_clusters();
	}

	// --- Mutation endpoints ---

	public function ajax_clear_index() {
		return $this->mutation_handler->clear_index();
	}

	public function ajax_save_settings() {
		return $this->mutation_handler->save_settings();
	}

	public function ajax_save_pillar() {
		return $this->mutation_handler->save_pillar();
	}

	public function ajax_rename_post_cluster() {
		return $this->mutation_handler->rename_post_cluster();
	}

	public function ajax_commit_gap_topics() {
		return $this->mutation_handler->commit_gap_topics();
	}

	public function ajax_resume_cooldown() {
		return $this->mutation_handler->resume_cooldown();
	}

	// --- Job endpoints ---

	public function ajax_process_batch() {
		return $this->job_handler->process_batch();
	}

	public function ajax_run_cannibalization_audit() {
		return $this->job_handler->run_cannibalization_audit();
	}

	public function ajax_generate_gap_ideas() {
		return $this->job_handler->generate_gap_ideas();
	}
}

if (!class_exists('AIPS_Content_Intelligence_Controller')) {
	class_alias(AIPS_Content_Indexer_Controller::class, 'AIPS_Content_Intelligence_Controller');
}
