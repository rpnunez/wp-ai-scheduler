<?php
/**
 * Content Indexer Job Handler
 *
 * Handles AJAX long-running / batch job endpoints for Content Indexer.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Content_Indexer_Job_Handler
 */
class AIPS_Content_Indexer_Job_Handler {

	/**
	 * @var AIPS_Content_Indexer_Service
	 */
	private $indexer_service;

	/**
	 * @var AIPS_Deduplication_Service
	 */
	private $deduplication_service;

	/**
	 * @var AIPS_Similarity_Evaluator
	 */
	private $similarity_evaluator;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	public function __construct(
		?AIPS_Content_Indexer_Service $indexer_service = null,
		?AIPS_Deduplication_Service $deduplication_service = null,
		?AIPS_Similarity_Evaluator $similarity_evaluator = null,
		?AIPS_Config $config = null
	) {
		$container = AIPS_Container::get_instance();
		$this->indexer_service       = $indexer_service ?: ($container->has(AIPS_Content_Indexer_Service::class) ? $container->make(AIPS_Content_Indexer_Service::class) : new AIPS_Content_Indexer_Service());
		$this->deduplication_service = $deduplication_service ?: ($container->has(AIPS_Deduplication_Service::class) ? $container->make(AIPS_Deduplication_Service::class) : new AIPS_Deduplication_Service());
		$this->similarity_evaluator  = $similarity_evaluator ?: ($container->has(AIPS_Similarity_Evaluator::class) ? $container->make(AIPS_Similarity_Evaluator::class) : new AIPS_Similarity_Evaluator());
		$this->config                = $config ?: AIPS_Config::get_instance();
	}

	public function process_batch() {
		AIPS_Ajax_Guard::check_admin();

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

	public function run_cannibalization_audit() {
		AIPS_Ajax_Guard::check_admin();

		$threshold   = isset($_POST['threshold']) ? (float) $_POST['threshold'] : (float) $this->config->get_option('aips_deduplication_threshold', 0.85);
		$limit       = isset($_POST['limit']) ? absint($_POST['limit']) : (int) $this->config->get_option('aips_audit_result_limit', 50);
		$entity_type = isset($_POST['entity_type']) ? sanitize_key($_POST['entity_type']) : 'all';

		$results = $this->deduplication_service->get_cannibalization_audit_results($threshold, $limit, $entity_type);

		AIPS_History_Event_Recorder::instance()->record_simple(
			AIPS_History_Event_Type::CANNIBALIZATION_AUDIT_RUN,
			true,
			sprintf(__('Cannibalization audit found %d clusters', 'ai-post-scheduler'), count($results)),
			'content_index_operation',
			array('event' => __('Cannibalization audit', 'ai-post-scheduler'), 'detail' => sprintf(__('Threshold %1$s, entity type %2$s', 'ai-post-scheduler'), $threshold, $entity_type)),
			'manual',
			array('threshold' => $threshold, 'limit' => $limit, 'entity_type' => $entity_type, 'clusters' => count($results))
		);

		AIPS_Ajax_Response::success(array(
			'clusters'    => $results,
			'count'       => count($results),
			'entity_type' => $entity_type,
		));
	}

	public function generate_gap_ideas() {
		AIPS_Ajax_Guard::check_admin();

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
}
