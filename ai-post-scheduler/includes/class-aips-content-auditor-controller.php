<?php
/**
 * Content Auditor Controller
 *
 * Handles chunked step-by-step AJAX pipeline execution, audit retrieval, and history management.
 *
 * @package AI_Post_Scheduler
 * @since 3.4.2
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Content_Auditor_Controller {

	/**
	 * @var AIPS_Content_Auditor_Scanner
	 */
	private $scanner;

	/**
	 * @var AIPS_Content_Auditor_Engine
	 */
	private $engine;

	/**
	 * @var AIPS_Content_Auditor_Repository
	 */
	private $repository;

	/**
	 * @var AIPS_Logger_Interface
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Content_Auditor_Scanner|null    $scanner Scanner instance.
	 * @param AIPS_Content_Auditor_Engine|null     $engine Engine instance.
	 * @param AIPS_Content_Auditor_Repository|null $repository Repository instance.
	 * @param AIPS_Logger_Interface|null           $logger Logger instance.
	 */
	public function __construct(
		?AIPS_Content_Auditor_Scanner $scanner = null,
		?AIPS_Content_Auditor_Engine $engine = null,
		?AIPS_Content_Auditor_Repository $repository = null,
		?AIPS_Logger_Interface $logger = null
	) {
		$container = AIPS_Container::get_instance();
		$this->scanner    = $scanner ?: new AIPS_Content_Auditor_Scanner();
		$this->engine     = $engine ?: new AIPS_Content_Auditor_Engine();
		$this->repository = $repository ?: new AIPS_Content_Auditor_Repository();
		$this->logger     = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());

		$this->init_hooks();
	}

	/**
	 * Register WordPress AJAX hooks.
	 */
	private function init_hooks() {
		add_action('wp_ajax_aips_auditor_scan_step', array($this, 'ajax_scan_step'));
		add_action('wp_ajax_aips_auditor_graph_step', array($this, 'ajax_graph_step'));
		add_action('wp_ajax_aips_auditor_analyze_step', array($this, 'ajax_analyze_step'));
		add_action('wp_ajax_aips_auditor_synthesize_step', array($this, 'ajax_synthesize_step'));
		add_action('wp_ajax_aips_auditor_get_latest', array($this, 'ajax_get_latest'));
		add_action('wp_ajax_aips_auditor_get_history', array($this, 'ajax_get_history'));
		add_action('wp_ajax_aips_auditor_get_audit', array($this, 'ajax_get_audit'));
		add_action('wp_ajax_aips_auditor_delete_audit', array($this, 'ajax_delete_audit'));
	}

	/**
	 * AJAX Step 1: Scan and fingerprint site content library.
	 */
	public function ajax_scan_step() {
		$this->verify_request();

		$limit  = isset($_POST['limit']) ? min(500, max(1, (int) $_POST['limit'])) : 200;
		$offset = isset($_POST['offset']) ? max(0, (int) $_POST['offset']) : 0;

		$fingerprints = $this->scanner->scan_library($limit, $offset);

		AIPS_Ajax_Response::success(array(
			'fingerprints' => $fingerprints,
			'count'        => count($fingerprints),
			'progress'     => 25,
			'step'         => 'scan_complete',
		));
	}

	/**
	 * AJAX Step 2: Build internal link graph and entity clusters.
	 */
	public function ajax_graph_step() {
		$this->verify_request();

		$fingerprints = isset($_POST['fingerprints']) ? $this->sanitize_fingerprints((array) $_POST['fingerprints']) : array();

		if (empty($fingerprints)) {
			// Scan fresh if not provided from step 1
			$limit = isset($_POST['limit']) ? min(500, max(1, (int) $_POST['limit'])) : 200;
			$fingerprints = $this->scanner->scan_library($limit);
		}

		$link_graph      = $this->scanner->build_link_graph($fingerprints);
		$entity_clusters = $this->scanner->build_entity_clusters($fingerprints);

		AIPS_Ajax_Response::success(array(
			'link_graph'      => $link_graph,
			'entity_clusters' => $entity_clusters,
			'progress'        => 50,
			'step'            => 'graph_complete',
		));
	}

	/**
	 * AJAX Step 3: Execute a single AI intelligence module.
	 */
	public function ajax_analyze_step() {
		$this->verify_request();

		$niche            = isset($_POST['niche']) ? sanitize_text_field(wp_unslash($_POST['niche'])) : 'General';
		$module           = isset($_POST['module']) ? sanitize_key($_POST['module']) : 'gaps';
		$fingerprints     = isset($_POST['fingerprints']) ? $this->sanitize_fingerprints((array) $_POST['fingerprints']) : array();
		$link_graph       = isset($_POST['link_graph']) ? (array) $_POST['link_graph'] : array();
		$entity_clusters  = isset($_POST['entity_clusters']) ? (array) $_POST['entity_clusters'] : array();
		$source_group_ids = isset($_POST['source_group_ids']) ? array_map('absint', (array) $_POST['source_group_ids']) : array();

		$result = array();

		switch ($module) {
			case 'gaps':
				$result = $this->engine->analyze_topic_gaps($niche, $fingerprints, $entity_clusters);
				break;

			case 'cannibalization':
				$candidates = isset($entity_clusters['cannibalization_candidates']) ? (array) $entity_clusters['cannibalization_candidates'] : array();
				$result     = $this->engine->analyze_cannibalization($candidates);
				break;

			case 'decay':
				$decayed = isset($entity_clusters['decayed_posts']) ? (array) $entity_clusters['decayed_posts'] : array();
				$thin    = isset($entity_clusters['thin_posts']) ? (array) $entity_clusters['thin_posts'] : array();
				$result  = $this->engine->analyze_content_decay($decayed, $thin);
				break;

			case 'links':
				$result = $this->engine->analyze_internal_linking($link_graph, $fingerprints);
				break;

			case 'trends':
				$result = $this->engine->analyze_source_trends($niche, $fingerprints, $source_group_ids);
				break;

			default:
				AIPS_Ajax_Response::error(sprintf(__('Unknown audit module: %s', 'ai-post-scheduler'), esc_html($module)));
				return;
		}

		AIPS_Ajax_Response::success(array(
			'module'   => $module,
			'result'   => $result,
			'progress' => 75,
			'step'     => 'module_complete',
		));
	}

	/**
	 * AJAX Step 4: Synthesize health scorecard, assemble report, and persist to database.
	 */
	public function ajax_synthesize_step() {
		$this->verify_request();

		$niche           = isset($_POST['niche']) ? sanitize_text_field(wp_unslash($_POST['niche'])) : 'General';
		$fingerprints    = isset($_POST['fingerprints']) ? $this->sanitize_fingerprints((array) $_POST['fingerprints']) : array();
		$link_graph      = isset($_POST['link_graph']) ? (array) $_POST['link_graph'] : array();
		$entity_clusters = isset($_POST['entity_clusters']) ? (array) $_POST['entity_clusters'] : array();
		$modules         = isset($_POST['modules']) && is_array($_POST['modules']) ? $_POST['modules'] : array();

		$scorecard = $this->engine->synthesize_overall_health($modules, $entity_clusters, $link_graph);

		$report = array(
			'niche'            => $niche,
			'audited_at'       => current_time('mysql'),
			'total_posts'      => count($fingerprints),
			'modules'          => $modules,
			'health_scorecard' => $scorecard,
		);

		// Save snapshot to database
		$audit_id = $this->repository->save($report);

		AIPS_Ajax_Response::success(array(
			'audit_id' => $audit_id,
			'report'   => $report,
			'progress' => 100,
			'step'     => 'audit_complete',
		));
	}

	/**
	 * AJAX: Get latest saved audit.
	 */
	public function ajax_get_latest() {
		$this->verify_request();

		$niche  = isset($_POST['niche']) && !empty($_POST['niche']) ? sanitize_text_field(wp_unslash($_POST['niche'])) : null;
		$latest = $this->repository->get_latest($niche);

		AIPS_Ajax_Response::success(array(
			'audit' => $latest,
		));
	}

	/**
	 * AJAX: Get audit history list.
	 */
	public function ajax_get_history() {
		$this->verify_request();

		$limit  = isset($_POST['limit']) ? min(100, max(1, (int) $_POST['limit'])) : 20;
		$offset = isset($_POST['offset']) ? max(0, (int) $_POST['offset']) : 0;
		$niche  = isset($_POST['niche']) && !empty($_POST['niche']) ? sanitize_text_field(wp_unslash($_POST['niche'])) : null;

		$history = $this->repository->get_history($limit, $offset, $niche);
		$total   = $this->repository->count($niche);

		AIPS_Ajax_Response::success(array(
			'history' => $history,
			'total'   => $total,
		));
	}

	/**
	 * AJAX: Get specific audit by ID.
	 */
	public function ajax_get_audit() {
		$this->verify_request();

		$id    = isset($_POST['id']) ? absint($_POST['id']) : 0;
		$audit = $this->repository->get_by_id($id);

		if (!$audit) {
			AIPS_Ajax_Response::error(__('Audit record not found.', 'ai-post-scheduler'));
			return;
		}

		AIPS_Ajax_Response::success(array(
			'audit' => $audit,
		));
	}

	/**
	 * AJAX: Delete audit by ID.
	 */
	public function ajax_delete_audit() {
		$this->verify_request();

		$id      = isset($_POST['id']) ? absint($_POST['id']) : 0;
		$deleted = $this->repository->delete($id);

		if (!$deleted) {
			AIPS_Ajax_Response::error(__('Failed to delete audit record.', 'ai-post-scheduler'));
			return;
		}

		AIPS_Ajax_Response::success(array(
			'message' => __('Audit deleted successfully.', 'ai-post-scheduler'),
		));
	}

	/**
	 * Verify nonce and capability permissions.
	 */
	private function verify_request() {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
	}

	/**
	 * Sanitize post fingerprint arrays from client payload.
	 *
	 * @param array $fingerprints Raw fingerprints from POST.
	 * @return array
	 */
	private function sanitize_fingerprints(array $fingerprints) {
		$clean = array();
		foreach ($fingerprints as $fp) {
			if (!is_array($fp) || empty($fp['id'])) {
				continue;
			}
			$clean[] = array(
				'id'                      => absint($fp['id']),
				'title'                   => isset($fp['title']) ? sanitize_text_field($fp['title']) : '',
				'slug'                    => isset($fp['slug']) ? sanitize_title($fp['slug']) : '',
				'url'                     => isset($fp['url']) ? esc_url_raw($fp['url']) : '',
				'categories'              => isset($fp['categories']) ? array_map('sanitize_text_field', (array) $fp['categories']) : array(),
				'tags'                    => isset($fp['tags']) ? array_map('sanitize_text_field', (array) $fp['tags']) : array(),
				'word_count'              => isset($fp['word_count']) ? absint($fp['word_count']) : 0,
				'char_count'              => isset($fp['char_count']) ? absint($fp['char_count']) : 0,
				'published_date'          => isset($fp['published_date']) ? sanitize_text_field($fp['published_date']) : '',
				'modified_date'           => isset($fp['modified_date']) ? sanitize_text_field($fp['modified_date']) : '',
				'age_days'                => isset($fp['age_days']) ? absint($fp['age_days']) : 0,
				'is_thin'                 => !empty($fp['is_thin']),
				'is_decayed'              => !empty($fp['is_decayed']),
				'headings'                => isset($fp['headings']) ? array_map('sanitize_text_field', (array) $fp['headings']) : array(),
				'outbound_internal_links' => isset($fp['outbound_internal_links']) ? array_map('sanitize_text_field', (array) $fp['outbound_internal_links']) : array(),
				'outbound_link_count'     => isset($fp['outbound_link_count']) ? absint($fp['outbound_link_count']) : 0,
				'keyphrases'              => isset($fp['keyphrases']) ? array_map('sanitize_text_field', (array) $fp['keyphrases']) : array(),
				'inbound_internal_links'  => isset($fp['inbound_internal_links']) ? array_map('absint', (array) $fp['inbound_internal_links']) : array(),
				'inbound_link_count'      => isset($fp['inbound_link_count']) ? absint($fp['inbound_link_count']) : 0,
				'is_orphan'               => !empty($fp['is_orphan']),
			);
		}
		return $clean;
	}
}
