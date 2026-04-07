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
	 * @var AIPS_Post_Embeddings_Repository
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
	 * Initialize the controller and register AJAX hooks.
	 *
	 * @param AIPS_Internal_Links_Service|null          $service          Internal links service.
	 * @param AIPS_Internal_Links_Repository|null       $links_repo       Links repository.
	 * @param AIPS_Post_Embeddings_Repository|null      $embeddings_repo  Embeddings repository.
	 * @param AIPS_Logger|null                          $logger           Logger instance.
	 * @param AIPS_Internal_Link_Inserter_Service|null  $inserter_service Link inserter service.
	 */
	public function __construct(
		$service = null,
		$links_repo = null,
		$embeddings_repo = null,
		$logger = null,
		$inserter_service = null
	) {
		$this->service          = $service          ?: new AIPS_Internal_Links_Service();
		$this->links_repo       = $links_repo       ?: new AIPS_Internal_Links_Repository();
		$this->embeddings_repo  = $embeddings_repo  ?: new AIPS_Post_Embeddings_Repository();
		$this->logger           = $logger           ?: new AIPS_Logger();
		$this->inserter_service = $inserter_service ?: new AIPS_Internal_Link_Inserter_Service();

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
	}

	// -------------------------------------------------------------------------
	// Admin Page
	// -------------------------------------------------------------------------

	/**
	 * Render the Internal Links admin page.
	 *
	 * @return void
	 */
	public function render_page() {
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
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')), 403);
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

		wp_send_json_success(array(
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
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')), 403);
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
			wp_send_json_error(array('message' => __('Invalid post ID.', 'ai-post-scheduler')));
		}

		if (!$this->embeddings_service_available()) {
			wp_send_json_error(array('message' => __('Embeddings are not available. Please configure AI Engine.', 'ai-post-scheduler')));
		}

		$ids = $this->service->generate_suggestions_for_post($post_id, $max_suggestions, $threshold);

		if (is_wp_error($ids)) {
			wp_send_json_error(array('message' => $ids->get_error_message()));
		}

		wp_send_json_success(array(
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
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')), 403);
		}

		$id     = absint(isset($_POST['id']) ? $_POST['id'] : 0);
		$status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';

		if (!$id || !in_array($status, AIPS_Internal_Links_Repository::VALID_STATUSES, true)) {
			wp_send_json_error(array('message' => __('Invalid parameters.', 'ai-post-scheduler')));
		}

		$result = $this->links_repo->update_status($id, $status);

		if ($result === false) {
			wp_send_json_error(array('message' => __('Failed to update status.', 'ai-post-scheduler')));
		}

		wp_send_json_success(array('message' => __('Status updated.', 'ai-post-scheduler')));
	}

	/**
	 * AJAX: Update the anchor text of a suggestion.
	 *
	 * @return void
	 */
	public function ajax_update_anchor() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')), 403);
		}

		$id          = absint(isset($_POST['id']) ? $_POST['id'] : 0);
		$anchor_text = isset($_POST['anchor_text']) ? sanitize_text_field(wp_unslash($_POST['anchor_text'])) : '';

		if (!$id) {
			wp_send_json_error(array('message' => __('Invalid ID.', 'ai-post-scheduler')));
		}

		$result = $this->links_repo->update_anchor_text($id, $anchor_text);

		if ($result === false) {
			wp_send_json_error(array('message' => __('Failed to update anchor text.', 'ai-post-scheduler')));
		}

		wp_send_json_success(array('message' => __('Anchor text updated.', 'ai-post-scheduler')));
	}

	/**
	 * AJAX: Delete a suggestion.
	 *
	 * @return void
	 */
	public function ajax_delete() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')), 403);
		}

		$id = absint(isset($_POST['id']) ? $_POST['id'] : 0);

		if (!$id) {
			wp_send_json_error(array('message' => __('Invalid ID.', 'ai-post-scheduler')));
		}

		$result = $this->links_repo->delete($id);

		if ($result === false) {
			wp_send_json_error(array('message' => __('Failed to delete suggestion.', 'ai-post-scheduler')));
		}

		wp_send_json_success(array('message' => __('Suggestion deleted.', 'ai-post-scheduler')));
	}

	/**
	 * AJAX: Start background indexing of all unindexed posts.
	 *
	 * Schedules the first cron batch and returns immediately.
	 *
	 * @return void
	 */
	public function ajax_start_indexing() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')), 403);
		}

		if (!$this->embeddings_service_available()) {
			wp_send_json_error(array('message' => __('Embeddings are not available. Please configure AI Engine.', 'ai-post-scheduler')));
		}

		$this->schedule_indexing_batch(0);

		wp_send_json_success(array(
			'message' => __('Indexing started. Posts will be indexed in the background.', 'ai-post-scheduler'),
		));
	}

	/**
	 * AJAX: Get current indexing status.
	 *
	 * @return void
	 */
	public function ajax_get_status() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')), 403);
		}

		$summary = $this->service->get_dashboard_summary();
		wp_send_json_success($summary);
	}

	/**
	 * AJAX: Re-index a single post and regenerate its suggestions.
	 *
	 * @return void
	 */
	public function ajax_reindex_post() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')), 403);
		}

		$post_id = absint(isset($_POST['post_id']) ? $_POST['post_id'] : 0);

		if (!$post_id) {
			wp_send_json_error(array('message' => __('Invalid post ID.', 'ai-post-scheduler')));
		}

		if (!$this->embeddings_service_available()) {
			wp_send_json_error(array('message' => __('Embeddings are not available. Please configure AI Engine.', 'ai-post-scheduler')));
		}

		$result = $this->service->index_post($post_id);

		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
		}

		$suggestion_ids = $this->service->generate_suggestions_for_post($post_id);

		if (is_wp_error($suggestion_ids)) {
			// Indexing succeeded even if suggestion generation failed
			wp_send_json_success(array(
				'message' => sprintf(
					/* translators: %s error message */
					__('Post re-indexed but suggestion generation failed: %s', 'ai-post-scheduler'),
					$suggestion_ids->get_error_message()
				),
			));
			return;
		}

		wp_send_json_success(array(
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
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')), 403);
		}

		$this->embeddings_repo->delete_all();
		$this->links_repo->delete_all();

		wp_send_json_success(array(
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
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')), 403);
		}

		$suggestion_id = absint(isset($_POST['suggestion_id']) ? $_POST['suggestion_id'] : 0);

		if (!$suggestion_id) {
			wp_send_json_error(array('message' => __('Invalid suggestion ID.', 'ai-post-scheduler')));
		}

		$suggestion = $this->links_repo->get_by_id($suggestion_id);

		if (!$suggestion) {
			wp_send_json_error(array('message' => __('Suggestion not found.', 'ai-post-scheduler')));
		}

		$source_post = get_post($suggestion->source_post_id);

		if (!$source_post) {
			wp_send_json_error(array('message' => __('Source post not found.', 'ai-post-scheduler')));
		}

		// Fetch all accepted suggestions for this source post.
		$accepted = $this->links_repo->get_by_source_post($suggestion->source_post_id, 'accepted');

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

		wp_send_json_success(array(
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
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')), 403);
		}

		$suggestion_id = absint(isset($_POST['suggestion_id']) ? $_POST['suggestion_id'] : 0);

		if (!$suggestion_id) {
			wp_send_json_error(array('message' => __('Invalid suggestion ID.', 'ai-post-scheduler')));
		}

		$result = $this->inserter_service->find_insertion_locations($suggestion_id);

		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
		}

		wp_send_json_success(array(
			'locations'       => $result,
			'requested_count' => AIPS_Internal_Link_Inserter_Service::NUM_LOCATIONS_TO_REQUEST,
			'returned_count'  => count($result),
		));
	}

	/**
	 * AJAX: Apply a specific insertion to the source post content.
	 *
	 * @return void
	 */
	public function ajax_apply_insertion() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')), 403);
		}

		$suggestion_id       = absint(isset($_POST['suggestion_id']) ? $_POST['suggestion_id'] : 0);
		$match_snippet       = isset($_POST['match_snippet']) ? wp_unslash($_POST['match_snippet']) : '';
		$replacement_snippet = isset($_POST['replacement_snippet']) ? wp_unslash($_POST['replacement_snippet']) : '';

		if (!$suggestion_id || empty($match_snippet) || empty($replacement_snippet)) {
			wp_send_json_error(array('message' => __('Invalid parameters.', 'ai-post-scheduler')));
		}

		// Validate that snippets contain no HTML (they must be plain text).
		if (strpos($match_snippet, '<') !== false || strpos($match_snippet, '>') !== false) {
			wp_send_json_error(array('message' => __('Invalid match snippet.', 'ai-post-scheduler')));
		}

		if (strpos($replacement_snippet, '<') !== false || strpos($replacement_snippet, '>') !== false) {
			wp_send_json_error(array('message' => __('Invalid replacement snippet.', 'ai-post-scheduler')));
		}

		// Require exactly one [[...]] link marker in the replacement snippet.
		if (!preg_match('/\[\[.*?\]\]/s', $replacement_snippet) || preg_match_all('/\[\[.*?\]\]/s', $replacement_snippet) !== 1) {
			wp_send_json_error(array('message' => __('Replacement snippet must contain exactly one [[link marker]].', 'ai-post-scheduler')));
		}

		$result = $this->inserter_service->apply_insertion($suggestion_id, $match_snippet, $replacement_snippet);

		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
		}

		wp_send_json_success(array(
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
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')), 403);
		}

		$insertions_raw = isset($_POST['insertions']) ? wp_unslash($_POST['insertions']) : '';

		if (empty($insertions_raw)) {
			wp_send_json_error(array('message' => __('No insertions provided.', 'ai-post-scheduler')));
			return;
		}

		$insertions = json_decode($insertions_raw, true);

		if (!is_array($insertions) || empty($insertions)) {
			wp_send_json_error(array('message' => __('Invalid insertions data.', 'ai-post-scheduler')));
			return;
		}

		$applied = 0;
		$errors  = array();

		foreach ($insertions as $ins) {
			$suggestion_id       = absint(isset($ins['suggestion_id']) ? $ins['suggestion_id'] : 0);
			$match_snippet       = isset($ins['match_snippet']) ? sanitize_text_field(wp_unslash($ins['match_snippet'])) : '';
			$replacement_snippet = isset($ins['replacement_snippet']) ? sanitize_text_field(wp_unslash($ins['replacement_snippet'])) : '';

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
			wp_send_json_error(array(
				'message' => implode(' ', $errors),
				'errors'  => $errors,
			));
			return;
		}

		wp_send_json_success(array(
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
			wp_schedule_single_event($timestamp, 'aips_index_posts_batch', array($args));
		}
	}
}
