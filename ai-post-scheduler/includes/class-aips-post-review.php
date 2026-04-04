<?php
/**
 * Post Review Handler
 *
 * Manages the Post Review admin page for reviewing draft posts.
 * Handles AJAX operations for publishing, regenerating, and deleting posts.
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Post_Review
 *
 * Handles post review functionality including bulk operations.
 */
class AIPS_Post_Review {
	
	/**
	 * @var AIPS_Post_Review_Repository Repository for database operations
	 */
	private $repository;
	
	/**
	 * @var AIPS_History_Service Service for history logging
	 */
	private $history_service;
	
	/**
	 * Initialize the post review handler.
	 */
	public function __construct() {
		$this->repository = new AIPS_Post_Review_Repository();
		$this->history_service = new AIPS_History_Service();
		
		// Register AJAX handlers
		add_action('wp_ajax_aips_get_draft_posts', array($this, 'ajax_get_draft_posts'));
		add_action('wp_ajax_aips_publish_post', array($this, 'ajax_publish_post'));
		add_action('wp_ajax_aips_bulk_publish_posts', array($this, 'ajax_bulk_publish_posts'));
		add_action('wp_ajax_aips_regenerate_post', array($this, 'ajax_regenerate_post'));
		add_action('wp_ajax_aips_delete_draft_post', array($this, 'ajax_delete_draft_post'));
		add_action('wp_ajax_aips_bulk_delete_draft_posts', array($this, 'ajax_bulk_delete_draft_posts'));
		add_action('wp_ajax_aips_bulk_regenerate_posts', array($this, 'ajax_bulk_regenerate_posts'));
		add_action('wp_ajax_aips_get_draft_post_preview', array($this, 'ajax_get_draft_post_preview'));
	}
	
	/**
	 * Get draft posts with pagination.
	 *
	 * @param array $args Query arguments.
	 * @return array Draft posts data.
	 */
	public function get_draft_posts($args = array()) {
		return $this->repository->get_draft_posts($args);
	}
	
	/**
	 * Get count of draft posts.
	 *
	 * @return int Number of draft posts.
	 */
	public function get_draft_count() {
		return $this->repository->get_draft_count();
	}
	
	/**
	 * AJAX handler to get draft post preview data.
	 */
	public function ajax_get_draft_post_preview() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

		if (!$post_id) {
			wp_send_json_error(array('message' => __('Invalid post ID.', 'ai-post-scheduler')));
		}

		$post = get_post($post_id);

		if (!$post || $post->post_status !== 'draft') {
			wp_send_json_error(array('message' => __('Post not found or not a draft.', 'ai-post-scheduler')));
		}

		// Prepare preview data
		$data = array(
			'title' => get_the_title($post),
			'content' => apply_filters('the_content', $post->post_content),
			'excerpt' => get_the_excerpt($post),
			'featured_image' => esc_url_raw(get_the_post_thumbnail_url($post_id, 'full')),
			'edit_url' => esc_url_raw(get_edit_post_link($post_id)),
		);

		wp_send_json_success($data);
	}

	/**
	 * AJAX handler to get draft posts.
	 */
	public function ajax_get_draft_posts() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$page = isset($_POST['page']) ? absint($_POST['page']) : 1;
		$search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
		$template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
		
		$draft_posts = $this->get_draft_posts(array(
			'page' => $page,
			'search' => $search,
			'template_id' => $template_id,
		));
		
		wp_send_json_success($draft_posts);
	}
	
	/**
	 * AJAX handler to publish a single post.
	 */
	public function ajax_publish_post() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			$history = $this->history_service->create('post_review_action', array());
			$history->record(
				'activity',
				__('Post publish failed: Permission denied', 'ai-post-scheduler'),
				array('event_type' => 'post_published', 'event_status' => 'failed'),
				null,
				array()
			);
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		
		if (!$post_id) {
			$history = $this->history_service->create('post_review_action', array());
			$history->record(
				'activity',
				__('Post publish failed: Invalid post ID', 'ai-post-scheduler'),
				array('event_type' => 'post_published', 'event_status' => 'failed'),
				null,
				array()
			);
			wp_send_json_error(array('message' => __('Invalid post ID.', 'ai-post-scheduler')));
		}
		
		// Verify the post exists and is a draft managed by this plugin
		$post = get_post($post_id);
		if (!$post || $post->post_status !== 'draft') {
			$history = $this->history_service->create('post_review_action', array('post_id' => $post_id));
			$history->record(
				'activity',
				__('Post publish failed: Post not found or not a draft', 'ai-post-scheduler'),
				array('event_type' => 'post_published', 'event_status' => 'failed'),
				null,
				array('post_id' => $post_id)
			);
			wp_send_json_error(array('message' => __('Post not found or not a draft.', 'ai-post-scheduler')));
		}
		
		// Verify the post is in the review queue (has a history record)
		if (!$this->history_service->post_has_history_and_completed($post_id)) {
			$history = $this->history_service->create('post_review_action', array('post_id' => $post_id));
			$history->record(
				'activity',
				__('Post publish failed: Post not found in review queue', 'ai-post-scheduler'),
				array('event_type' => 'post_published', 'event_status' => 'failed'),
				null,
				array('post_id' => $post_id)
			);
			wp_send_json_error(array('message' => __('Post not found in review queue.', 'ai-post-scheduler')));
		}
		
		// Check per-post capability
		if (!current_user_can('publish_post', $post_id)) {
			$history = $this->history_service->create('post_review_action', array('post_id' => $post_id));
			$history->record(
				'activity',
				__('Post publish failed: Insufficient permissions', 'ai-post-scheduler'),
				array('event_type' => 'post_published', 'event_status' => 'failed'),
				null,
				array('post_id' => $post_id)
			);
			wp_send_json_error(array('message' => __('You do not have permission to publish this post.', 'ai-post-scheduler')));
		}
		
		$result = wp_update_post(array(
			'ID' => $post_id,
			'post_status' => 'publish',
		));
		
		if (is_wp_error($result)) {
			$history = $this->history_service->create('post_review_action', array('post_id' => $post_id));
			$history->record(
				'activity',
				sprintf(__('Post publish failed: %s', 'ai-post-scheduler'), $result->get_error_message()),
				array('event_type' => 'post_published', 'event_status' => 'failed'),
				null,
				array('post_id' => $post_id, 'error' => $result->get_error_message())
			);
			wp_send_json_error(array('message' => $result->get_error_message()));
		}
		
		// Log the publish activity
		$history = $this->history_service->create('post_review_action', array('post_id' => $post_id));
		$history->record(
			'activity',
			__('Post published from review queue', 'ai-post-scheduler'),
			array('event_type' => 'post_published', 'event_status' => 'success'),
			null,
			array('post_id' => $post_id)
		);
		
		/**
		 * Fires after a post is published from the review queue.
		 *
		 * @param int $post_id Post ID that was published.
		 */
		do_action('aips_post_review_published', $post_id);
		
		wp_send_json_success(array(
			'message' => __('Post published successfully.', 'ai-post-scheduler'),
			'post_id' => $post_id,
		));
	}
	
	/**
	 * AJAX handler to publish multiple posts.
	 */
	public function ajax_bulk_publish_posts() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			$history = $this->history_service->create('post_review_action', array());
			$history->record(
				'activity',
				__('Bulk publish failed: Permission denied', 'ai-post-scheduler'),
				array('event_type' => 'post_published', 'event_status' => 'failed'),
				null,
				array()
			);
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$post_ids = array();
		
		if (isset($_POST['post_ids']) && is_array($_POST['post_ids'])) {
			$post_ids = array_filter(
				array_map('absint', $_POST['post_ids']),
				function( $post_id ) {
					return $post_id > 0;
				}
			);
		}
		
		if (empty($post_ids)) {
			$history = $this->history_service->create('post_review_action', array());
			$history->record(
				'activity',
				__('Bulk publish failed: No posts selected', 'ai-post-scheduler'),
				array('event_type' => 'post_published', 'event_status' => 'failed'),
				null,
				array()
			);
			wp_send_json_error(array('message' => __('No posts selected.', 'ai-post-scheduler')));
		}
		
		$success_count = 0;
		$failed_count = 0;
		
		foreach ($post_ids as $post_id) {
			// Verify the post exists and is a draft
			$post = get_post($post_id);
			if (!$post || $post->post_status !== 'draft') {
				$failed_count++;
				$history = $this->history_service->create('post_review_action', array('post_id' => $post_id));
				$history->record(
					'activity',
					__('Bulk publish failed: Post not found or not a draft', 'ai-post-scheduler'),
					array('event_type' => 'post_published', 'event_status' => 'failed'),
					null,
					array('post_id' => $post_id)
				);
				continue;
			}
			
			// Verify the post is in the review queue
			if (!$this->history_service->post_has_history_and_completed($post_id)) {
				$failed_count++;
				$history = $this->history_service->create('post_review_action', array('post_id' => $post_id));
				$history->record(
					'activity',
					__('Bulk publish failed: Post not in review queue', 'ai-post-scheduler'),
					array('event_type' => 'post_published', 'event_status' => 'failed'),
					null,
					array('post_id' => $post_id)
				);
				continue;
			}
			
			// Check per-post capability
			if (!current_user_can('publish_post', $post_id)) {
				$failed_count++;
				$history = $this->history_service->create('post_review_action', array('post_id' => $post_id));
				$history->record(
					'activity',
					__('Bulk publish failed: Insufficient permissions', 'ai-post-scheduler'),
					array('event_type' => 'post_published', 'event_status' => 'failed'),
					null,
					array('post_id' => $post_id)
				);
				continue;
			}
			
			$result = wp_update_post(array(
				'ID' => $post_id,
				'post_status' => 'publish',
			));
			
			if (!is_wp_error($result)) {
				$success_count++;
				
				// Log the publish activity
				$history = $this->history_service->create('post_review_action', array('post_id' => $post_id));
				$history->record(
					'activity',
					__('Post published from review queue (bulk)', 'ai-post-scheduler'),
					array('event_type' => 'post_published', 'event_status' => 'success'),
					null,
					array('post_id' => $post_id)
				);
				
				/**
				 * Fires after a post is published from the review queue.
				 *
				 * @param int $post_id Post ID that was published.
				 */
				do_action('aips_post_review_published', $post_id);
			} else {
				$failed_count++;
				$history = $this->history_service->create('post_review_action', array('post_id' => $post_id));
				$history->record(
					'activity',
					sprintf(__('Bulk publish failed: %s', 'ai-post-scheduler'), $result->get_error_message()),
					array('event_type' => 'post_published', 'event_status' => 'failed'),
					null,
					array('post_id' => $post_id, 'error' => $result->get_error_message())
				);
			}
		}
		
		wp_send_json_success(array(
			'message' => sprintf(__('%d posts published successfully.', 'ai-post-scheduler'), $success_count),
			'count' => $success_count,
			'failed' => $failed_count,
		));
	}
	
	/**
	 * AJAX handler to regenerate a post.
	 */
	public function ajax_regenerate_post() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
		
		if (!$history_id) {
			wp_send_json_error(array('message' => __('Invalid history ID.', 'ai-post-scheduler')));
		}
		
		// Get the history item
		$history_item = $this->history_service->get_by_id($history_id);
		
		if (!$history_item || !$history_item->template_id) {
			wp_send_json_error(array('message' => __('History item not found or no template associated.', 'ai-post-scheduler')));
		}
		
		// Get the template
		$template_repository = new AIPS_Template_Repository();
		$template = $template_repository->get_by_id($history_item->template_id);
		
		if (!$template) {
			wp_send_json_error(array('message' => __('Template not found.', 'ai-post-scheduler')));
		}
		
		// Delete the existing post if it exists
		if ($history_item->post_id) {
			// Verify per-post capability before deleting
			if (!current_user_can('delete_post', $history_item->post_id)) {
				wp_send_json_error(array('message' => __('You do not have permission to regenerate this post.', 'ai-post-scheduler')));
			}
			wp_delete_post($history_item->post_id, true);
		}
		
		// Update history status to pending for regeneration
		$this->history_service->update_history_record($history_id, array(
			'status' => 'pending',
			'post_id' => null,
			'error_message' => null,
		));
		
		// Trigger regeneration using the generator (same API as history retry)
		$generator = new AIPS_Generator();
		$result = $generator->generate_post($template);
		
		if (is_wp_error($result)) {
			// Log the regeneration failure
			$history = $this->history_service->create('post_review_action', array());
			$history->record(
				'activity',
				sprintf(__('Post regeneration failed: %s', 'ai-post-scheduler'), $result->get_error_message()),
				array('event_type' => 'post_regenerated', 'event_status' => 'failed'),
				null,
				array('error' => $result->get_error_message())
			);
			
			wp_send_json_error(array('message' => $result->get_error_message()));
			return;
		}
		
		// Log the regeneration success
		$history = $this->history_service->create('post_review_action', array('post_id' => $result));
		$history->record(
			'activity',
			__('Post regenerated from review queue', 'ai-post-scheduler'),
			array('event_type' => 'post_regenerated', 'event_status' => 'success'),
			null,
			array('post_id' => $result)
		);
		
		/**
		 * Fires after a post is regenerated from the review queue.
		 *
		 * @param int $history_id History ID of the post being regenerated.
		 */
		do_action('aips_post_review_regenerated', $history_id);
		
		wp_send_json_success(array(
			'message' => __('Post regeneration started successfully.', 'ai-post-scheduler'),
			'history_id' => $history_id,
		));
	}
	
	/**
	 * AJAX handler to regenerate multiple posts.
	 */
	public function ajax_bulk_regenerate_posts() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$items = (isset($_POST['items']) && is_array($_POST['items'])) ? wp_unslash($_POST['items']) : array();

		if (empty($items)) {
			wp_send_json_error(array('message' => __('No posts selected.', 'ai-post-scheduler')));
		}

		// Determine batch size and limit the number of items processed in a single request.
		$total_requested   = count($items);
		$max_batch_size    = apply_filters('aips_post_review_bulk_regenerate_max_batch', 5);
		$items_to_process  = $items;
		$batch_was_limited = false;

		if ($max_batch_size > 0 && $total_requested > $max_batch_size) {
			$items_to_process  = array_slice($items, 0, $max_batch_size);
			$batch_was_limited = true;
		}

		// Create history container for bulk regenerate operation
		$history = $this->history_service->create('bulk_regenerate', array(
			'user_id'        => get_current_user_id(),
			'source'         => 'manual_ui',
			'trigger'        => 'ajax_bulk_regenerate_posts',
			'entity_type'    => 'draft_posts',
			// Track how many posts were requested for regeneration, even if we process a subset.
			'entity_count'   => $total_requested,
			'processed_count' => count($items_to_process),
		));

		$history->record_user_action(
			'bulk_regenerate_posts',
			sprintf(
				/* translators: 1: number of requested draft posts, 2: number of processed draft posts */
				__('User initiated bulk regenerate for %1$d draft posts (processing %2$d in this batch)', 'ai-post-scheduler'),
				$total_requested,
				count($items_to_process)
			),
			array(
				'item_count'      => $total_requested,
				'processed_count' => count($items_to_process),
			)
		);

		if ($batch_was_limited) {
			$history->record_activity(
				'bulk_regenerate_batch_limited',
				sprintf(
					/* translators: 1: max batch size, 2: total requested items */
					__('Bulk regenerate batch limited to %1$d items out of %2$d requested to avoid timeouts.', 'ai-post-scheduler'),
					$max_batch_size,
					$total_requested
				),
				array(
					'max_batch_size'   => $max_batch_size,
					'total_requested'  => $total_requested,
					'processed_in_batch' => count($items_to_process),
				)
			);
		}

		$success_count = 0;
		$failed_count = 0;
		$generator = new AIPS_Generator();
		$template_repository = new AIPS_Template_Repository();

		foreach ($items_to_process as $item) {
			if (!is_array($item)) {
				$failed_count++;
				continue;
			}

			$post_id = isset($item['post_id']) ? absint($item['post_id']) : 0;
			$history_id = isset($item['history_id']) ? absint($item['history_id']) : 0;

			if (!$history_id) {
				$failed_count++;
				continue;
			}

			// Get the history item and derive a trusted post ID from it.
			$history_item = $this->history_service->get_by_id($history_id);

			if (!$history_item || !$history_item->template_id) {
				$failed_count++;
				$history->record(
					'warning',
					sprintf(__('Cannot regenerate post ID %d: History item not found or no template associated', 'ai-post-scheduler'), $post_id),
					null,
					null,
					array('post_id' => $post_id, 'history_id' => $history_id)
				);
				continue;
			}

			$history_post_id = isset($history_item->post_id) ? absint($history_item->post_id) : 0;
			if (!$history_post_id) {
				$failed_count++;
				$history->record(
					'warning',
					sprintf(__('Cannot regenerate: History record %d has no associated post ID', 'ai-post-scheduler'), $history_id),
					null,
					null,
					array('history_id' => $history_id)
				);
				continue;
			}

			// If a client-supplied post_id was provided, ensure it matches the history record.
			if ($post_id && $post_id !== $history_post_id) {
				$failed_count++;
				$history->record(
					'warning',
					sprintf(
						__('Skipping regeneration: Client post ID %1$d does not match history post ID %2$d', 'ai-post-scheduler'),
						$post_id,
						$history_post_id
					),
					null,
					null,
					array(
						'post_id'      => $post_id,
						'history_id'   => $history_id,
						'history_post' => $history_post_id,
					)
				);
				continue;
			}

			// From this point on, only use the trusted post ID from the history record.
			$post_id = $history_post_id;

			// Verify the post exists and is a draft
			$post = get_post($post_id);
			if (!$post || $post->post_status !== 'draft') {
				$failed_count++;
				$history->record(
					'warning',
					sprintf(__('Cannot regenerate post ID %d: Not found or not a draft', 'ai-post-scheduler'), $post_id),
					null,
					null,
					array('post_id' => $post_id)
				);
				continue;
			}
			// Get the template
			$template = $template_repository->get_by_id($history_item->template_id);

			if (!$template) {
				$failed_count++;
				$history->record('warning', sprintf(__('Cannot regenerate post ID %d: Template not found', 'ai-post-scheduler'), $post_id), null, null, array('post_id' => $post_id));
				continue;
			}

			// Check per-post capability
			if (!current_user_can('delete_post', $post_id)) {
				$failed_count++;
				$history->record('warning', sprintf(__('Cannot regenerate post ID %d: Insufficient permissions', 'ai-post-scheduler'), $post_id), null, null, array('post_id' => $post_id));
				continue;
			}

			$delete_result = wp_delete_post($post_id, true);

			if (!$delete_result) {
				$failed_count++;
				$history->record('warning', sprintf(__('Failed to delete old post ID %d for regeneration', 'ai-post-scheduler'), $post_id), null, null, array('post_id' => $post_id));
				continue;
			}

			// Update history status to pending for regeneration
			$this->history_service->update_history_record($history_id, array(
				'status' => 'pending',
				'post_id' => null,
				'error_message' => null,
			));

			// Trigger regeneration
			$result = $generator->generate_post($template);

			if (is_wp_error($result)) {
				$failed_count++;
				$history->record(
					'warning',
					sprintf(__('Post regeneration failed for history ID %d: %s', 'ai-post-scheduler'), $history_id, $result->get_error_message()),
					array('event_type' => 'post_regenerated', 'event_status' => 'failed'),
					null,
					array('history_id' => $history_id, 'error' => $result->get_error_message())
				);
			} else {
				$success_count++;
				$history->record(
					'activity',
					sprintf(__('Post regenerated from review queue for history ID %d', 'ai-post-scheduler'), $history_id),
					array('event_type' => 'post_regenerated', 'event_status' => 'success'),
					null,
					array('history_id' => $history_id, 'new_post_id' => $result)
				);

				/**
				 * Fires after a post is regenerated from the review queue.
				 *
				 * @param int $history_id History ID of the post being regenerated.
				 */
				do_action('aips_post_review_regenerated', $history_id);
			}
		}

		$history->record('activity', sprintf(__('Bulk regenerate completed: %d started, %d failed', 'ai-post-scheduler'), $success_count, $failed_count), null, null, array(
			'regenerated_count' => $success_count,
			'failed_count' => $failed_count,
			'requested_count' => count($items)
		));

		if ($failed_count > 0) {
			$history->complete_failure(
				sprintf(__('Bulk regenerate completed with %d failures', 'ai-post-scheduler'), $failed_count),
				array('success_count' => $success_count, 'failed_count' => $failed_count)
			);
		} else {
			$history->complete_success(array('regenerated_count' => $success_count));
		}

		wp_send_json_success(array(
			'message' => sprintf(__('%d posts regeneration started successfully.', 'ai-post-scheduler'), $success_count),
			'success_count' => $success_count,
			'failed_count' => $failed_count,
		));
	}

	/**
	 * AJAX handler to delete a draft post.
	 */
	public function ajax_delete_draft_post() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			$history = $this->history_service->create('post_review_action', array());
			$history->record(
				'activity',
				__('Post delete failed: Permission denied', 'ai-post-scheduler'),
				array('event_type' => 'post_deleted', 'event_status' => 'failed'),
				null,
				array()
			);
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
		
		if (!$post_id) {
			$history = $this->history_service->create('post_review_action', array());
			$history->record(
				'activity',
				__('Post delete failed: Invalid post ID', 'ai-post-scheduler'),
				array('event_type' => 'post_deleted', 'event_status' => 'failed'),
				null,
				array()
			);
			wp_send_json_error(array('message' => __('Invalid post ID.', 'ai-post-scheduler')));
		}
		
		// Verify the post exists and is a draft
		$post = get_post($post_id);
		if (!$post || $post->post_status !== 'draft') {
			$history = $this->history_service->create('post_review_action', array('post_id' => $post_id));
			$history->record(
				'activity',
				__('Post delete failed: Post not found or not a draft', 'ai-post-scheduler'),
				array('event_type' => 'post_deleted', 'event_status' => 'failed'),
				null,
				array('post_id' => $post_id)
			);
			wp_send_json_error(array('message' => __('Post not found or not a draft.', 'ai-post-scheduler')));
		}
		
		// Verify the post is in the review queue
		if (!$this->history_service->post_has_history_and_completed($post_id)) {
			$history = $this->history_service->create('post_review_action', array('post_id' => $post_id));
			$history->record(
				'activity',
				__('Post delete failed: Post not in review queue', 'ai-post-scheduler'),
				array('event_type' => 'post_deleted', 'event_status' => 'failed'),
				null,
				array('post_id' => $post_id)
			);
			wp_send_json_error(array('message' => __('Post not found in review queue.', 'ai-post-scheduler')));
		}
		
		// Check per-post capability
		if (!current_user_can('delete_post', $post_id)) {
			$history = $this->history_service->create('post_review_action', array('post_id' => $post_id));
			$history->record(
				'activity',
				__('Post delete failed: Insufficient permissions', 'ai-post-scheduler'),
				array('event_type' => 'post_deleted', 'event_status' => 'failed'),
				null,
				array('post_id' => $post_id)
			);
			wp_send_json_error(array('message' => __('You do not have permission to delete this post.', 'ai-post-scheduler')));
		}
		
		// Delete the post
		$result = wp_delete_post($post_id, true);
		
		if (!$result) {
			$history = $this->history_service->create('post_review_action', array('post_id' => $post_id));
			$history->record(
				'activity',
				__('Post delete failed: Unable to delete post', 'ai-post-scheduler'),
				array('event_type' => 'post_deleted', 'event_status' => 'failed'),
				null,
				array('post_id' => $post_id)
			);
			wp_send_json_error(array('message' => __('Failed to delete post.', 'ai-post-scheduler')));
		}
		
		// Update history if history_id is provided
		if ($history_id) {
			$this->history_service->update_history_record($history_id, array(
				'post_id' => null,
			));
		}
		
		// Log the delete activity
		$history = $this->history_service->create('post_review_action', array('post_id' => $post_id));
		$history->record(
			'activity',
			__('Draft post deleted from review queue', 'ai-post-scheduler'),
			array('event_type' => 'post_deleted', 'event_status' => 'success'),
			null,
			array('post_id' => $post_id)
		);
		
		/**
		 * Fires after a post is deleted from the review queue.
		 *
		 * @param int   $post_id Post ID that was deleted.
		 * @param array $meta    Optional metadata for listeners.
		 */
		do_action('aips_post_review_deleted', $post_id, array(
			'post_title' => !empty($post->post_title) ? $post->post_title : '',
		));
		
		wp_send_json_success(array(
			'message' => __('Post deleted successfully.', 'ai-post-scheduler'),
			'post_id' => $post_id,
		));
	}
	
	/**
	 * AJAX handler to delete multiple draft posts.
	 */
	public function ajax_bulk_delete_draft_posts() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$items = (isset($_POST['items']) && is_array($_POST['items'])) ? $_POST['items'] : array();
		
		if (empty($items)) {
			wp_send_json_error(array('message' => __('No posts selected.', 'ai-post-scheduler')));
		}
		
		// Create history container for bulk delete operation
		$history = $this->history_service->create('bulk_delete', array(
			'user_id' => get_current_user_id(),
			'source' => 'manual_ui',
			'trigger' => 'ajax_bulk_delete_draft_posts',
			'entity_type' => 'draft_posts',
			'entity_count' => count($items)
		));
		
		$history->record_user_action(
			'bulk_delete_drafts',
			sprintf(__('User initiated bulk delete for %d draft posts', 'ai-post-scheduler'), count($items)),
			array('item_count' => count($items))
		);
		
		$success_count = 0;
		$failed_count = 0;
		
		foreach ($items as $item) {
			if (!is_array($item)) {
				$failed_count++;
				continue;
			}

			$post_id = isset($item['post_id']) ? absint($item['post_id']) : 0;
			$history_id = isset($item['history_id']) ? absint($item['history_id']) : 0;
			
			if (!$post_id) {
				$failed_count++;
				continue;
			}
			
			// Verify the post exists and is a draft
			$post = get_post($post_id);
			if (!$post || $post->post_status !== 'draft') {
				$failed_count++;
				$history->record('warning', sprintf(__('Cannot delete post ID %d: Not found or not a draft', 'ai-post-scheduler'), $post_id), null, null, array('post_id' => $post_id));
				continue;
			}
			
			// Verify the post is in the review queue
			if (!$this->history_service->post_has_history_and_completed($post_id)) {
				$failed_count++;
				$history->record('warning', sprintf(__('Cannot delete post ID %d: Not in review queue', 'ai-post-scheduler'), $post_id), null, null, array('post_id' => $post_id));
				continue;
			}
			
			// Check per-post capability
			if (!current_user_can('delete_post', $post_id)) {
				$failed_count++;
				$history->record('warning', sprintf(__('Cannot delete post ID %d: Insufficient permissions', 'ai-post-scheduler'), $post_id), null, null, array('post_id' => $post_id));
				continue;
			}
			
			$result = wp_delete_post($post_id, true);
			
			if ($result) {
				$success_count++;
				
				// Update history if history_id is provided
				if ($history_id) {
					$this->history_service->update_history_record($history_id, array(
						'post_id' => null,
					));
				}
				
				/**
				 * Fires after a post is deleted from the review queue.
				 *
				 * @param int   $post_id Post ID that was deleted.
				 * @param array $meta    Optional metadata for listeners.
				 */
				do_action('aips_post_review_deleted', $post_id, array(
					'post_title' => !empty($post->post_title) ? $post->post_title : '',
				));
			} else {
				$failed_count++;
				$history->record('warning', sprintf(__('Failed to delete post ID %d', 'ai-post-scheduler'), $post_id), null, null, array('post_id' => $post_id));
			}
		}
		
		$history->record('activity', sprintf(__('Bulk delete completed: %d deleted, %d failed', 'ai-post-scheduler'), $success_count, $failed_count), null, null, array(
			'deleted_count' => $success_count,
			'failed_count' => $failed_count,
			'requested_count' => count($items)
		));
		
		if ($failed_count > 0) {
			$history->complete_failure(
				sprintf(__('Bulk delete completed with %d failures', 'ai-post-scheduler'), $failed_count),
				array('success_count' => $success_count, 'failed_count' => $failed_count)
			);
		} else {
			$history->complete_success(array('deleted_count' => $success_count));
		}
		
		wp_send_json_success(array(
			'message' => sprintf(__('%d posts deleted successfully.', 'ai-post-scheduler'), $success_count),
			'count' => $success_count,
			'failed' => $failed_count,
		));
	}
}
