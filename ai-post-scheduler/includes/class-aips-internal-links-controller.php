<?php
/**
 * Internal Links Controller
 *
 * Handles AJAX endpoints for the Internal Links admin page and registers
 * the cron callback that runs the background post-indexing job.
 *
 * Intentionally instantiated outside the admin-only bootstrap so that the
 * 'aips_index_posts_batch' cron callback is registered during wp-cron
 * and frontend contexts as well as admin contexts.
 *
 * @package AI_Post_Scheduler
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Internal_Links_Controller
 *
 * Admin page render + AJAX endpoints for the Internal Links feature.
 */
class AIPS_Internal_Links_Controller {

	/**
	 * @var AIPS_Internal_Links_Service
	 */
	private $service;

	/**
	 * @var AIPS_Internal_Links_Repository
	 */
	private $links_repo;

	/**
	 * @var AIPS_Embeddings_Repository
	 */
	private $embeddings_repo;

	/**
	 * @var AIPS_Internal_Link_Inserter_Service
	 */
	private $inserter_service;

	/**
	 * @var AIPS_Logger
	 */
	private $logger;

	/**
	 * @var AIPS_Job_Scheduler
	 */
	private $job_scheduler;

	/**
	 * @var AIPS_Content_Links_Repository
	 */
	private $content_links_repo;

	/**
	 * @var AIPS_Link_Graph_Service
	 */
	private $link_graph_service;

	/**
	 * Initialize the controller and register AJAX hooks.
	 *
	 * @param AIPS_Internal_Links_Service|null          $service          Internal links service.
	 * @param AIPS_Internal_Links_Repository|null       $links_repo       Links repository.
	 * @param AIPS_Embeddings_Repository|null           $embeddings_repo  Embeddings repository.
	 * @param AIPS_Logger|null                          $logger           Logger instance.
	 * @param AIPS_Internal_Link_Inserter_Service|null  $inserter_service Link inserter service.
	 * @param AIPS_Job_Scheduler|null                   $job_scheduler    Job scheduler service.
	 * @param AIPS_Content_Links_Repository|null        $content_links_repo Content links repository.
	 * @param AIPS_Link_Graph_Service|null              $link_graph_service Link graph service.
	 */
	public function __construct(
		$service = null,
		$links_repo = null,
		$embeddings_repo = null,
		$logger = null,
		$inserter_service = null,
		$job_scheduler = null,
		$content_links_repo = null,
		$link_graph_service = null
	) {
		$container              = AIPS_Container::get_instance();
		$this->service          = $service          ?: new AIPS_Internal_Links_Service();
		$this->links_repo       = $links_repo       ?: new AIPS_Internal_Links_Repository();
		$this->embeddings_repo  = $embeddings_repo  ?: ($container->has(AIPS_Embeddings_Repository::class) ? $container->make(AIPS_Embeddings_Repository::class) : new AIPS_Embeddings_Repository());
		$this->logger           = $logger           ?: new AIPS_Logger();
		$this->inserter_service = $inserter_service ?: new AIPS_Internal_Link_Inserter_Service();
		$this->job_scheduler    = $job_scheduler    ?: new AIPS_Job_Scheduler();
		$this->content_links_repo = $content_links_repo ?: ($container->has(AIPS_Content_Links_Repository::class) ? $container->make(AIPS_Content_Links_Repository::class) : new AIPS_Content_Links_Repository());
		$this->link_graph_service = $link_graph_service ?: ($container->has(AIPS_Link_Graph_Service::class) ? $container->make(AIPS_Link_Graph_Service::class) : new AIPS_Link_Graph_Service());

		// AJAX endpoints — suggestion management
		add_action('wp_ajax_aips_internal_links_get_suggestions', array($this, 'ajax_get_suggestions'));
		add_action('wp_ajax_aips_internal_links_generate_suggestions', array($this, 'ajax_generate_suggestions'));
		add_action('wp_ajax_aips_internal_links_update_status', array($this, 'ajax_update_status'));
		add_action('wp_ajax_aips_internal_links_update_anchor', array($this, 'ajax_update_anchor'));
		add_action('wp_ajax_aips_internal_links_delete', array($this, 'ajax_delete'));
		add_action('wp_ajax_aips_internal_links_start_indexing', array($this, 'ajax_start_indexing'));
		add_action('wp_ajax_aips_internal_links_get_status', array($this, 'ajax_get_status'));
		add_action('wp_ajax_aips_internal_links_reindex_post', array($this, 'ajax_reindex_post'));
		add_action('wp_ajax_aips_internal_links_clear_index', array($this, 'ajax_clear_index'));

		// AJAX endpoints — link insertion workflow
		add_action('wp_ajax_aips_internal_links_get_post_for_insertion', array($this, 'ajax_get_post_for_insertion'));
		add_action('wp_ajax_aips_internal_links_find_insert_locations', array($this, 'ajax_find_insert_locations'));
		add_action('wp_ajax_aips_internal_links_apply_insertion', array($this, 'ajax_apply_insertion'));
		add_action('wp_ajax_aips_internal_links_apply_bulk_insertions', array($this, 'ajax_apply_bulk_insertions'));

		// AJAX endpoints — SEO link graph & orphan explorer
		add_action('wp_ajax_aips_get_seo_link_graph_data', array($this, 'ajax_get_seo_link_graph_data'));
		add_action('wp_ajax_aips_find_linking_opportunities', array($this, 'ajax_find_linking_opportunities'));
	}

	// -------------------------------------------------------------------------
	// Admin Page
	// -------------------------------------------------------------------------

	/**
	 * Render the Internal Links admin page.
	 *
	 * @param bool $embedded Whether the page is being rendered inside another admin page.
	 * @return void
	 */
	public function render_page($embedded = false) {
		$summary       = $this->service->get_dashboard_summary();
		$links_repo    = $this->links_repo;
		$service       = $this->service;

		include AIPS_PLUGIN_DIR . 'templates/admin/internal-links.php';
	}

	// -------------------------------------------------------------------------
	// AJAX Handlers
	// -------------------------------------------------------------------------

	/**
	 * AJAX: Get paginated list of internal link suggestions.
	 *
	 * @return void
	 */
	public function ajax_get_suggestions() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$page     = max(1, absint(isset($_POST['page']) ? wp_unslash($_POST['page']) : 1));
		$per_page = max(1, min(100, absint(isset($_POST['per_page']) ? wp_unslash($_POST['per_page']) : 20)));
		$status   = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
		$search   = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

		$items = $this->links_repo->get_paginated($per_page, $page, $status, $search);
		$total = $this->links_repo->get_paginated_count($status, $search);

		// Enrich items with edit URLs
		foreach ($items as $item) {
			$item->source_edit_url = get_edit_post_link($item->source_post_id, 'url');
			$item->target_edit_url = get_edit_post_link($item->target_post_id, 'url');
			$item->target_url      = get_permalink($item->target_post_id);
		}

		AIPS_Ajax_Response::success(array(
			'items'       => $items,
			'total'       => $total,
			'total_pages' => (int) ceil($total / $per_page),
			'page'        => $page,
		));
	}

	/**
	 * AJAX: Generate suggestions for a specific post.
	 *
	 * @return void
	 */
	public function ajax_generate_suggestions() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$post_id_raw = isset($_POST['post_id']) ? wp_unslash($_POST['post_id']) : 0;
		$post_id     = absint($post_id_raw);

		$max_suggestions_raw = isset($_POST['max_suggestions']) ? wp_unslash($_POST['max_suggestions']) : AIPS_Internal_Links_Service::DEFAULT_MAX_SUGGESTIONS;
		$max_suggestions     = is_numeric($max_suggestions_raw) ? (int) $max_suggestions_raw : (int) AIPS_Internal_Links_Service::DEFAULT_MAX_SUGGESTIONS;
		$max_suggestions     = max(1, min(20, $max_suggestions));

		$threshold_raw = isset($_POST['threshold']) ? wp_unslash($_POST['threshold']) : AIPS_Internal_Links_Service::DEFAULT_SIMILARITY_THRESHOLD;
		$threshold     = is_numeric($threshold_raw) ? (float) $threshold_raw : (float) AIPS_Internal_Links_Service::DEFAULT_SIMILARITY_THRESHOLD;
		$threshold     = max(0, min(1, $threshold));
		if (!$post_id) {
			AIPS_Ajax_Response::error(array('message' => __('Invalid post ID.', 'ai-post-scheduler')));
		}

		if (!$this->embeddings_service_available()) {
			AIPS_Ajax_Response::error(array('message' => __('Embeddings are not available. Please configure AI Engine.', 'ai-post-scheduler')));
		}

		$ids = $this->service->generate_suggestions_for_post($post_id, $max_suggestions, $threshold);

		if (is_wp_error($ids)) {
			AIPS_Ajax_Response::error(array('message' => $ids->get_error_message()));
		}

		AIPS_Ajax_Response::success(array(
			'created' => count($ids),
			'message' => sprintf(
				/* translators: %d number of suggestions */
				_n(
					'%d suggestion created.',
					'%d suggestions created.',
					count($ids),
					'ai-post-scheduler'
				),
				count($ids)
			),
		));
	}

	/**
	 * AJAX: Update the status of a suggestion (accept / reject).
	 *
	 * @return void
	 */
	public function ajax_update_status() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$id     = absint(isset($_POST['id']) ? $_POST['id'] : 0);
		$status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';

		if (!$id || !in_array($status, AIPS_Internal_Links_Repository::VALID_STATUSES, true)) {
			AIPS_Ajax_Response::error(array('message' => __('Invalid parameters.', 'ai-post-scheduler')));
		}

		$result = $this->links_repo->update_status($id, $status);

		if ($result === false) {
			AIPS_Ajax_Response::error(array('message' => __('Failed to update status.', 'ai-post-scheduler')));
		}

		AIPS_Ajax_Response::success(array('message' => __('Status updated.', 'ai-post-scheduler')));
	}

	/**
	 * AJAX: Update the anchor text of a suggestion.
	 *
	 * @return void
	 */
	public function ajax_update_anchor() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$id          = absint(isset($_POST['id']) ? $_POST['id'] : 0);
		$anchor_text = isset($_POST['anchor_text']) ? sanitize_text_field(wp_unslash($_POST['anchor_text'])) : '';

		if (!$id) {
			AIPS_Ajax_Response::error(array('message' => __('Invalid ID.', 'ai-post-scheduler')));
		}

		$result = $this->links_repo->update_anchor_text($id, $anchor_text);

		if ($result === false) {
			AIPS_Ajax_Response::error(array('message' => __('Failed to update anchor text.', 'ai-post-scheduler')));
		}

		AIPS_Ajax_Response::success(array('message' => __('Anchor text updated.', 'ai-post-scheduler')));
	}

	/**
	 * AJAX: Delete a suggestion.
	 *
	 * @return void
	 */
	public function ajax_delete() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$id = absint(isset($_POST['id']) ? $_POST['id'] : 0);

		if (!$id) {
			AIPS_Ajax_Response::error(array('message' => __('Invalid ID.', 'ai-post-scheduler')));
		}

		$result = $this->links_repo->delete($id);

		if ($result === false) {
			AIPS_Ajax_Response::error(array('message' => __('Failed to delete suggestion.', 'ai-post-scheduler')));
		}

		AIPS_Ajax_Response::success(array('message' => __('Suggestion deleted.', 'ai-post-scheduler')));
	}

	/**
	 * AJAX: Start background indexing of all unindexed posts.
	 *
	 * Schedules the first cron batch and returns immediately.
	 *
	 * @return void
	 */
	public function ajax_start_indexing() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		if (!$this->embeddings_service_available()) {
			AIPS_Ajax_Response::error(array('message' => __('Embeddings are not available. Please configure AI Engine.', 'ai-post-scheduler')));
		}

		$this->schedule_indexing_batch(0);

		AIPS_Ajax_Response::success(array(
			'message' => __('Indexing started. Posts will be indexed in the background.', 'ai-post-scheduler'),
		));
	}

	/**
	 * AJAX: Get current indexing status.
	 *
	 * @return void
	 */
	public function ajax_get_status() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$summary = $this->service->get_dashboard_summary();
		AIPS_Ajax_Response::success($summary);
	}

	/**
	 * AJAX: Re-index a single post and regenerate its suggestions.
	 *
	 * @return void
	 */
	public function ajax_reindex_post() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$post_id = absint(isset($_POST['post_id']) ? $_POST['post_id'] : 0);

		if (!$post_id) {
			AIPS_Ajax_Response::error(array('message' => __('Invalid post ID.', 'ai-post-scheduler')));
		}

		if (!$this->embeddings_service_available()) {
			AIPS_Ajax_Response::error(array('message' => __('Embeddings are not available. Please configure AI Engine.', 'ai-post-scheduler')));
		}

		$result = $this->service->index_post($post_id);

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
		}

		$suggestion_ids = $this->service->generate_suggestions_for_post($post_id);

		if (is_wp_error($suggestion_ids)) {
			// Indexing succeeded even if suggestion generation failed
			AIPS_Ajax_Response::success(array(
				'message' => sprintf(
					/* translators: %s error message */
					__('Post re-indexed but suggestion generation failed: %s', 'ai-post-scheduler'),
					$suggestion_ids->get_error_message()
				),
			));
			return;
		}

		AIPS_Ajax_Response::success(array(
			'message' => sprintf(
				/* translators: %d number of suggestions */
				_n(
					'Post re-indexed. %d suggestion created.',
					'Post re-indexed. %d suggestions created.',
					count($suggestion_ids),
					'ai-post-scheduler'
				),
				count($suggestion_ids)
			),
		));
	}

	/**
	 * AJAX: Clear the entire embeddings index and all link suggestions.
	 *
	 * @return void
	 */
	public function ajax_clear_index() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$this->embeddings_repo->delete_all();
		$this->links_repo->delete_all();

		AIPS_Ajax_Response::success(array(
			'message' => __('Index cleared. All embeddings and suggestions have been removed.', 'ai-post-scheduler'),
		));
	}

	// -------------------------------------------------------------------------
	// Link insertion AJAX handlers
	// -------------------------------------------------------------------------

	/**
	 * AJAX: Get source post content and its accepted suggestions for the insertion modal.
	 *
	 * @return void
	 */
	public function ajax_get_post_for_insertion() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$suggestion_id = absint(isset($_POST['suggestion_id']) ? $_POST['suggestion_id'] : 0);

		if (!$suggestion_id) {
			AIPS_Ajax_Response::error(array('message' => __('Invalid suggestion ID.', 'ai-post-scheduler')));
		}

		$suggestion = $this->links_repo->get_by_id($suggestion_id);

		if (!$suggestion) {
			AIPS_Ajax_Response::error(array('message' => __('Suggestion not found.', 'ai-post-scheduler')));
		}

		$source_post = get_post($suggestion->source_post_id);

		if (!$source_post) {
			AIPS_Ajax_Response::error(array('message' => __('Source post not found.', 'ai-post-scheduler')));
		}

		// Fetch all accepted suggestions for this source post.
		$accepted = $this->links_repo->get_by_source_post($suggestion->source_post_id, 'accepted');

		$post_ids = array();
		foreach ($accepted as $s) {
			$post_ids[] = (int) $s->target_post_id;
		}
		if (!empty($post_ids) && function_exists('_prime_post_caches')) {
			_prime_post_caches(array_unique($post_ids), false, true);
		}

		$suggestions_data = array();
		foreach ($accepted as $s) {
			$target_post = get_post($s->target_post_id);
			$suggestions_data[] = array(
				'id'                => (int) $s->id,
				'target_post_id'    => (int) $s->target_post_id,
				'target_post_title' => $target_post ? $target_post->post_title : '#' . $s->target_post_id,
				'target_url'        => get_permalink($s->target_post_id),
				'anchor_text'       => $s->anchor_text,
				'similarity_score'  => (float) $s->similarity_score,
			);
		}

		AIPS_Ajax_Response::success(array(
			'post_id'      => (int) $source_post->ID,
			'post_title'   => $source_post->post_title,
			'post_content' => $source_post->post_content,
			'suggestions'  => $suggestions_data,
		));
	}

	/**
	 * AJAX: Ask AI for up to the configured number of insertion locations for a
	 * given suggestion.
	 *
	 * @return void
	 */
	public function ajax_find_insert_locations() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$suggestion_id = absint(isset($_POST['suggestion_id']) ? $_POST['suggestion_id'] : 0);

		if (!$suggestion_id) {
			AIPS_Ajax_Response::error(array('message' => __('Invalid suggestion ID.', 'ai-post-scheduler')));
		}

		$result = $this->inserter_service->find_insertion_locations($suggestion_id);

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
		}

		AIPS_Ajax_Response::success(array(
			'locations'       => isset($result['locations']) ? $result['locations'] : array(),
			'requested_count' => AIPS_Internal_Link_Inserter_Service::NUM_LOCATIONS_TO_REQUEST,
			'ai_returned_count' => isset($result['raw_count']) ? (int) $result['raw_count'] : 0,
			'valid_count'       => isset($result['valid_count']) ? (int) $result['valid_count'] : 0,
		));
	}

	/**
	 * AJAX: Apply a specific insertion to the source post content.
	 *
	 * @return void
	 */
	public function ajax_apply_insertion() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$suggestion_id       = absint(isset($_POST['suggestion_id']) ? $_POST['suggestion_id'] : 0);
		$match_snippet       = isset($_POST['match_snippet']) ? wp_unslash($_POST['match_snippet']) : '';
		$replacement_snippet = isset($_POST['replacement_snippet']) ? wp_unslash($_POST['replacement_snippet']) : '';

		if (!$suggestion_id || empty($match_snippet) || empty($replacement_snippet)) {
			AIPS_Ajax_Response::error(array('message' => __('Invalid parameters.', 'ai-post-scheduler')));
		}

		// Validate that snippets contain no HTML (they must be plain text).
		if (strpos($match_snippet, '<') !== false || strpos($match_snippet, '>') !== false) {
			AIPS_Ajax_Response::error(array('message' => __('Invalid match snippet.', 'ai-post-scheduler')));
		}

		if (strpos($replacement_snippet, '<') !== false || strpos($replacement_snippet, '>') !== false) {
			AIPS_Ajax_Response::error(array('message' => __('Invalid replacement snippet.', 'ai-post-scheduler')));
		}

		// Require exactly one [[...]] link marker in the replacement snippet.
		if (!preg_match('/\[\[.*?\]\]/s', $replacement_snippet) || preg_match_all('/\[\[.*?\]\]/s', $replacement_snippet) !== 1) {
			AIPS_Ajax_Response::error(array('message' => __('Replacement snippet must contain exactly one [[link marker]].', 'ai-post-scheduler')));
		}

		$result = $this->inserter_service->apply_insertion($suggestion_id, $match_snippet, $replacement_snippet);

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
		}

		AIPS_Ajax_Response::success(array(
			'message' => __('Link inserted successfully.', 'ai-post-scheduler'),
		));
	}

	/**
	 * AJAX: Apply multiple insertions to the source post(s) in sequence.
	 *
	 * Accepts a JSON-encoded array of insertion objects, each with keys
	 * suggestion_id, match_snippet, and replacement_snippet. Insertions are
	 * applied one by one so that later insertions search the already-modified
	 * post content. Partial success is returned when some insertions succeed
	 * and others fail.
	 *
	 * @return void
	 */
	public function ajax_apply_bulk_insertions() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$insertions_raw = isset($_POST['insertions']) ? wp_unslash($_POST['insertions']) : '';

		if (empty($insertions_raw)) {
			AIPS_Ajax_Response::error(array('message' => __('No insertions provided.', 'ai-post-scheduler')));
			return;
		}

		$insertions = json_decode($insertions_raw, true);

		if (!is_array($insertions) || empty($insertions)) {
			AIPS_Ajax_Response::error(array('message' => __('Invalid insertions data.', 'ai-post-scheduler')));
			return;
		}

		$applied = 0;
		$errors  = array();

		foreach ($insertions as $ins) {
			$suggestion_id       = absint(isset($ins['suggestion_id']) ? $ins['suggestion_id'] : 0);
			$match_snippet       = isset($ins['match_snippet']) ? wp_unslash($ins['match_snippet']) : '';
			$replacement_snippet = isset($ins['replacement_snippet']) ? wp_unslash($ins['replacement_snippet']) : '';

			if (!$suggestion_id || empty($match_snippet) || empty($replacement_snippet)) {
				$errors[] = __('Invalid insertion parameters.', 'ai-post-scheduler');
				continue;
			}

			// Validate no HTML in snippets.
			if (strpos($match_snippet, '<') !== false || strpos($match_snippet, '>') !== false) {
				$errors[] = __('Invalid match snippet.', 'ai-post-scheduler');
				continue;
			}

			if (strpos($replacement_snippet, '<') !== false || strpos($replacement_snippet, '>') !== false) {
				$errors[] = __('Invalid replacement snippet.', 'ai-post-scheduler');
				continue;
			}

			// Require exactly one [[...]] link marker.
			if (!preg_match('/\[\[.*?\]\]/s', $replacement_snippet) || preg_match_all('/\[\[.*?\]\]/s', $replacement_snippet) !== 1) {
				$errors[] = __('Replacement snippet must contain exactly one [[link marker]].', 'ai-post-scheduler');
				continue;
			}

			$result = $this->inserter_service->apply_insertion($suggestion_id, $match_snippet, $replacement_snippet);

			if (is_wp_error($result)) {
				$errors[] = $result->get_error_message();
			} else {
				$applied++;
			}
		}

		if ($applied === 0 && !empty($errors)) {
			AIPS_Ajax_Response::error(array(
				'message' => implode(' ', $errors),
				'errors'  => $errors,
			));
			return;
		}

		AIPS_Ajax_Response::success(array(
			'applied' => $applied,
			'errors'  => $errors,
			'message' => sprintf(
				/* translators: %d: number of links inserted */
				_n(
					'%d link inserted successfully.',
					'%d links inserted successfully.',
					$applied,
					'ai-post-scheduler'
				),
				$applied
			),
		));
	}

	// -------------------------------------------------------------------------
	// Cron callback
	// -------------------------------------------------------------------------

	/**
	 * Cron callback: process one batch of unindexed posts.
	 *
	 * Called via the 'aips_index_posts_batch' action hook.
	 * Re-schedules itself if more work remains.
	 *
	 * @param array $args Arguments with keys: last_post_id, batch_size.
	 * @return void
	 */
	public function process_indexing_batch_cron($args) {
		$last_post_id = isset($args['last_post_id']) ? absint($args['last_post_id']) : 0;
		$batch_size   = isset($args['batch_size']) ? absint($args['batch_size']) : 10;

		if (!$this->embeddings_service_available()) {
			$this->logger->log('Internal links indexing skipped: embeddings not available.', 'warning');
			return;
		}

		$result = $this->service->process_indexing_batch($batch_size, $last_post_id);

		$this->logger->log(
			sprintf(
				'Internal links indexing batch: success=%d failed=%d last_post_id=%d done=%s',
				$result['success'],
				$result['failed'],
				$result['last_post_id'],
				$result['done'] ? 'yes' : 'no'
			),
			'info'
		);

		if (!$result['done']) {
			$this->schedule_indexing_batch($result['last_post_id'], $batch_size);
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Check whether the embeddings service is available.
	 *
	 * @return bool
	 */
	private function embeddings_service_available() {
		$svc = new AIPS_Embeddings_Service();
		return $svc->is_embeddings_supported();
	}

	/**
	 * Schedule the next indexing batch cron event.
	 *
	 * @param int $last_post_id Last indexed post ID cursor.
	 * @param int $batch_size   Batch size for the next run.
	 * @return void
	 */
	private function schedule_indexing_batch($last_post_id, $batch_size = 10) {
		$args = array(
			'last_post_id' => $last_post_id,
			'batch_size'   => $batch_size,
		);

		$timestamp = time() + 5;

		if (function_exists('as_schedule_single_action')) {
			as_schedule_single_action($timestamp, 'aips_index_posts_batch', array($args), 'aips-internal-links');
		} else {
			// Use centralized job scheduler
			$this->job_scheduler->schedule_simple(
				'aips_index_posts_batch',
				$timestamp,
				array($args),
				array(
					'job_type'      => 'internal_links_indexing',
					'retry_options' => array(
						'max_attempts' => 3,
					),
				)
			);
		}
	}

	/**
	 * AJAX endpoint to retrieve paginated SEO Link Graph and Orphan data.
	 *
	 * @return void
	 */
	public function ajax_get_seo_link_graph_data() {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security token.', 'ai-post-scheduler')), 403);
			return;
		}

		if (!current_user_can('edit_posts')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')), 403);
			return;
		}

		global $wpdb;

		$page          = isset($_POST['page']) ? max(1, absint($_POST['page'])) : 1;
		$per_page      = isset($_POST['per_page']) ? min(100, max(5, absint($_POST['per_page']))) : 20;
		$status_filter = isset($_POST['status_filter']) ? sanitize_key($_POST['status_filter']) : 'all';
		$search        = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
		$offset        = ($page - 1) * $per_page;

		$post_types      = apply_filters('aips_editor_indexable_post_types', array('post', 'page'));
		$pt_placeholders = implode(',', array_fill(0, count($post_types), '%s'));

		// Compute network aggregate counts using indexed queries
		$total_network_posts = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($pt_placeholders)",
				$post_types
			)
		);
		$total_edges = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}aips_content_links"
		);

		// Compute total orphans directly via SQL (published indexable posts with 0 inbound links)
		$total_orphans = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(p.ID) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->prefix}aips_content_links l ON p.ID = l.target_id WHERE p.post_status = 'publish' AND p.post_type IN ($pt_placeholders) AND l.id IS NULL",
				$post_types
			)
		);

		// Single-pass BFS for graph depth calculation
		$all_depths = $this->link_graph_service->get_all_graph_depths();
		$total_deep = 0;
		foreach ($all_depths as $d) {
			if ($d >= 3) {
				$total_deep++;
			}
		}

		$paged_ids   = array();
		$total_items = 0;

		// Database-level filtering and pagination
		if ('orphans' === $status_filter) {
			if (!empty($search)) {
				$like        = '%' . $wpdb->esc_like($search) . '%';
				$total_items = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(p.ID) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->prefix}aips_content_links l ON p.ID = l.target_id WHERE p.post_status = 'publish' AND p.post_type IN ($pt_placeholders) AND l.id IS NULL AND p.post_title LIKE %s",
						array_merge($post_types, array($like))
					)
				);
				$paged_ids   = array_map('intval', $wpdb->get_col(
					$wpdb->prepare(
						"SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->prefix}aips_content_links l ON p.ID = l.target_id WHERE p.post_status = 'publish' AND p.post_type IN ($pt_placeholders) AND l.id IS NULL AND p.post_title LIKE %s ORDER BY p.ID DESC LIMIT %d OFFSET %d",
						array_merge($post_types, array($like, $per_page, $offset))
					)
				));
			} else {
				$total_items = $total_orphans;
				$paged_ids   = array_map('intval', $wpdb->get_col(
					$wpdb->prepare(
						"SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->prefix}aips_content_links l ON p.ID = l.target_id WHERE p.post_status = 'publish' AND p.post_type IN ($pt_placeholders) AND l.id IS NULL ORDER BY p.ID DESC LIMIT %d OFFSET %d",
						array_merge($post_types, array($per_page, $offset))
					)
				));
			}
		} elseif ('hubs' === $status_filter) {
			$hub_ids = array_map('intval', $wpdb->get_col(
				"SELECT target_id FROM {$wpdb->prefix}aips_content_links GROUP BY target_id HAVING COUNT(*) >= 5"
			));
			if (!empty($hub_ids)) {
				$hub_query = new WP_Query(array(
					'post_type'      => $post_types,
					'post_status'    => 'publish',
					'post__in'       => $hub_ids,
					'posts_per_page' => $per_page,
					'paged'          => $page,
					'fields'         => 'ids',
					's'              => $search,
					'orderby'        => 'ID',
					'order'          => 'DESC',
				));
				$total_items = (int) $hub_query->found_posts;
				$paged_ids   = array_map('intval', $hub_query->posts);
			}
		} elseif ('deep' === $status_filter) {
			$deep_candidate_ids = array();
			foreach ($all_depths as $cand_id => $d) {
				if ($d >= 3) {
					$deep_candidate_ids[] = (int) $cand_id;
				}
			}
			if (!empty($deep_candidate_ids)) {
				$deep_query = new WP_Query(array(
					'post_type'      => $post_types,
					'post_status'    => 'publish',
					'post__in'       => $deep_candidate_ids,
					'posts_per_page' => $per_page,
					'paged'          => $page,
					'fields'         => 'ids',
					's'              => $search,
					'orderby'        => 'ID',
					'order'          => 'DESC',
				));
				$total_items = (int) $deep_query->found_posts;
				$paged_ids   = array_map('intval', $deep_query->posts);
			}
		} else {
			// 'all' status
			$query_args = array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'DESC',
			);
			if (!empty($search)) {
				$query_args['s'] = $search;
			}
			$main_query  = new WP_Query($query_args);
			$total_items = (int) $main_query->found_posts;
			$paged_ids   = array_map('intval', $main_query->posts);
		}

		$total_pages = $total_items > 0 ? (int) ceil($total_items / $per_page) : 1;

		if (!empty($paged_ids) && function_exists('_prime_post_caches')) {
			_prime_post_caches($paged_ids, false, false);
		}

		// Batch load inbound & outbound counts for the paginated slice
		$inbound_map  = !empty($paged_ids) ? $this->content_links_repo->get_inbound_counts($paged_ids) : array();
		$outbound_map = !empty($paged_ids) ? $this->content_links_repo->get_outbound_counts($paged_ids) : array();

		$paged_items = array();
		foreach ($paged_ids as $p_id) {
			$inbound       = isset($inbound_map[$p_id]) ? (int) $inbound_map[$p_id] : 0;
			$outbound      = isset($outbound_map[$p_id]) ? (int) $outbound_map[$p_id] : 0;
			$depth         = isset($all_depths[$p_id]) ? (int) $all_depths[$p_id] : 99;
			$depth_display = (99 === $depth) ? '∞' : ('L' . $depth);
			$is_orphan     = (0 === $inbound);

			if ($is_orphan) {
				$equity_tier = 'orphan';
			} elseif ($inbound >= 5) {
				$equity_tier = 'hub';
			} elseif ($inbound <= 2) {
				$equity_tier = 'low';
			} else {
				$equity_tier = 'moderate';
			}

			$paged_items[] = array(
				'id'            => $p_id,
				'title'         => html_entity_decode(get_the_title($p_id), ENT_QUOTES, 'UTF-8'),
				'url'           => get_permalink($p_id),
				'edit_url'      => get_edit_post_link($p_id, 'raw'),
				'post_type'     => get_post_type($p_id),
				'inbound_count' => $inbound,
				'outbound_count'=> $outbound,
				'depth_level'   => $depth_display,
				'is_orphan'     => $is_orphan,
				'equity_tier'   => $equity_tier,
			);
		}

		wp_send_json_success(array(
			'items'      => $paged_items,
			'summary'    => array(
				'total_network_posts' => $total_network_posts,
				'total_edges'         => $total_edges,
				'total_orphans'       => $total_orphans,
				'total_deep'          => $total_deep,
			),
			'pagination' => array(
				'page'        => $page,
				'per_page'    => $per_page,
				'total_items' => $total_items,
				'total_pages' => $total_pages,
			),
		));
	}

	/**
	 * AJAX endpoint to find candidate source posts that can link to a given target post.
	 *
	 * @return void
	 */
	public function ajax_find_linking_opportunities() {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security token.', 'ai-post-scheduler')), 403);
			return;
		}

		if (!current_user_can('edit_posts')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')), 403);
			return;
		}

		$target_id = isset($_POST['target_id']) ? absint($_POST['target_id']) : 0;
		if ($target_id <= 0) {
			wp_send_json_error(array('message' => __('Invalid target post ID.', 'ai-post-scheduler')));
			return;
		}

		$target_post = get_post($target_id);
		if (!$target_post) {
			wp_send_json_error(array('message' => __('Target post not found.', 'ai-post-scheduler')));
			return;
		}

		$target_title = html_entity_decode(get_the_title($target_id), ENT_QUOTES, 'UTF-8');
		$target_url   = get_permalink($target_id);

		// Get all source posts that already link to target_id so we exclude them
		$existing_inbound = $this->content_links_repo->get_inbound_links($target_id);
		$already_linked_source_ids = array_map(function($l) {
			return (int) $l->source_id;
		}, $existing_inbound);
		$exclude_ids = array_merge(array($target_id), $already_linked_source_ids);

		$candidates = array();

		// Priority 1: Vector similarity relationships if embeddings exist
		$container = AIPS_Container::get_instance();
		$relationships_repo = $container->has(AIPS_Relationships_Repository::class) ? $container->make(AIPS_Relationships_Repository::class) : new AIPS_Relationships_Repository();
		$top_rels = $relationships_repo->get_top_relationships($target_id, 15, 0.40);

		if (!empty($top_rels)) {
			foreach ($top_rels as $rel) {
				$candidate_id = (int) $rel->target_post_id;
				if (in_array($candidate_id, $exclude_ids, true)) {
					continue;
				}
				$cand_post = get_post($candidate_id);
				if (!$cand_post || 'publish' !== $cand_post->post_status) {
					continue;
				}

				$outbound_count = $this->content_links_repo->get_outbound_count($candidate_id);
				$excerpt = !empty($cand_post->post_excerpt) ? $cand_post->post_excerpt : wp_trim_words($cand_post->post_content, 20);

				$candidates[] = array(
					'id'               => $candidate_id,
					'title'            => html_entity_decode(get_the_title($candidate_id), ENT_QUOTES, 'UTF-8'),
					'url'              => get_permalink($candidate_id),
					'edit_url'         => get_edit_post_link($candidate_id, 'raw'),
					'post_type'        => $cand_post->post_type,
					'similarity_pct'   => round((float) $rel->similarity * 100),
					'excerpt'          => wp_strip_all_tags($excerpt),
					'outbound_count'   => $outbound_count,
					'suggested_anchor' => $target_title,
				);

				$exclude_ids[] = $candidate_id;
				if (count($candidates) >= 8) {
					break;
				}
			}
		}

		// Priority 2: Text/Keyword search fallback if candidates are fewer than 5
		if (count($candidates) < 5) {
			$words = array_filter(explode(' ', preg_replace('/[^\p{L}\p{N}\s]/u', '', $target_title)), function ($w) {
				return mb_strlen($w) >= 3;
			});
			$search_query = implode(' ', array_slice(array_unique($words), 0, 3));

			$post_types = apply_filters('aips_editor_indexable_post_types', array('post', 'page'));
			$search_args = array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'post__not_in'   => $exclude_ids,
				'orderby'        => 'date',
				'order'          => 'DESC',
			);
			if (!empty($search_query)) {
				$search_args['s'] = $search_query;
			}

			$query = new WP_Query($search_args);
			if ($query->have_posts()) {
				foreach ($query->posts as $f_post) {
					$f_id = (int) $f_post->ID;
					$outbound_count = $this->content_links_repo->get_outbound_count($f_id);
					$excerpt = !empty($f_post->post_excerpt) ? $f_post->post_excerpt : wp_trim_words($f_post->post_content, 20);

					$candidates[] = array(
						'id'               => $f_id,
						'title'            => html_entity_decode(get_the_title($f_id), ENT_QUOTES, 'UTF-8'),
						'url'              => get_permalink($f_id),
						'edit_url'         => get_edit_post_link($f_id, 'raw'),
						'post_type'        => $f_post->post_type,
						'similarity_pct'   => 55,
						'excerpt'          => wp_strip_all_tags($excerpt),
						'outbound_count'   => $outbound_count,
						'suggested_anchor' => $target_title,
					);

					$exclude_ids[] = $f_id;
					if (count($candidates) >= 8) {
						break;
					}
				}
			}
		}

		wp_send_json_success(array(
			'target_id'    => $target_id,
			'target_title' => $target_title,
			'target_url'   => $target_url,
			'candidates'   => $candidates,
		));
	}
}

