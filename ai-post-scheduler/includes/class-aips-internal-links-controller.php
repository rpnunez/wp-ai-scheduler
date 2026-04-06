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
	 * @var AIPS_Logger
	 */
	private $logger;

	/**
	 * Initialize the controller and register AJAX hooks.
	 *
	 * @param AIPS_Internal_Links_Service|null     $service         Internal links service.
	 * @param AIPS_Internal_Links_Repository|null  $links_repo      Links repository.
	 * @param AIPS_Post_Embeddings_Repository|null $embeddings_repo Embeddings repository.
	 * @param AIPS_Logger|null                     $logger          Logger instance.
	 */
	public function __construct(
		$service = null,
		$links_repo = null,
		$embeddings_repo = null,
		$logger = null
	) {
		$this->service         = $service         ?: new AIPS_Internal_Links_Service();
		$this->links_repo      = $links_repo      ?: new AIPS_Internal_Links_Repository();
		$this->embeddings_repo = $embeddings_repo ?: new AIPS_Post_Embeddings_Repository();
		$this->logger          = $logger          ?: new AIPS_Logger();

		// AJAX endpoints
		add_action('wp_ajax_aips_internal_links_get_suggestions', array($this, 'ajax_get_suggestions'));
		add_action('wp_ajax_aips_internal_links_generate_suggestions', array($this, 'ajax_generate_suggestions'));
		add_action('wp_ajax_aips_internal_links_update_status', array($this, 'ajax_update_status'));
		add_action('wp_ajax_aips_internal_links_update_anchor', array($this, 'ajax_update_anchor'));
		add_action('wp_ajax_aips_internal_links_delete', array($this, 'ajax_delete'));
		add_action('wp_ajax_aips_internal_links_start_indexing', array($this, 'ajax_start_indexing'));
		add_action('wp_ajax_aips_internal_links_get_status', array($this, 'ajax_get_status'));
		add_action('wp_ajax_aips_internal_links_reindex_post', array($this, 'ajax_reindex_post'));
		add_action('wp_ajax_aips_internal_links_clear_index', array($this, 'ajax_clear_index'));
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

		$page    = max(1, absint(isset($_POST['page']) ? $_POST['page'] : 1));
		$per_page = max(1, min(100, absint(isset($_POST['per_page']) ? $_POST['per_page'] : 20)));
		$status  = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
		$search  = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

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
			as_schedule_single_action($timestamp, 'aips_index_posts_batch', $args, 'aips-internal-links');
		} else {
			wp_schedule_single_event($timestamp, 'aips_index_posts_batch', array($args));
		}
	}
}
