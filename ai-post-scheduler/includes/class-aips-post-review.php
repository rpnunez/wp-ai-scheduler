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
	 * Initialize the post review handler.
	 */
	public function __construct() {
		$this->repository = new AIPS_Post_Review_Repository();
		
		// Register AJAX handlers
		add_action('wp_ajax_aips_get_draft_posts', array($this, 'ajax_get_draft_posts'));
		add_action('wp_ajax_aips_publish_post', array($this, 'ajax_publish_post'));
		add_action('wp_ajax_aips_bulk_publish_posts', array($this, 'ajax_bulk_publish_posts'));
		add_action('wp_ajax_aips_regenerate_post', array($this, 'ajax_regenerate_post'));
		add_action('wp_ajax_aips_delete_draft_post', array($this, 'ajax_delete_draft_post'));
		add_action('wp_ajax_aips_bulk_delete_draft_posts', array($this, 'ajax_bulk_delete_draft_posts'));
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
	 * AJAX handler to get draft posts.
	 */
	public function ajax_get_draft_posts() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$page = isset($_POST['page']) ? absint($_POST['page']) : 1;
		$search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
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
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		
		if (!$post_id) {
			wp_send_json_error(array('message' => __('Invalid post ID.', 'ai-post-scheduler')));
		}
		
		// Verify the post exists and is a draft managed by this plugin
		$post = get_post($post_id);
		if (!$post || $post->post_status !== 'draft') {
			wp_send_json_error(array('message' => __('Post not found or not a draft.', 'ai-post-scheduler')));
		}
		
		// Verify the post is in the review queue (has a history record)
		$history_repository = new AIPS_History_Repository();
		global $wpdb;
		$history_table = $wpdb->prefix . 'aips_history';
		$history_exists = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$history_table} WHERE post_id = %d AND status = 'completed'",
			$post_id
		));
		
		if (!$history_exists) {
			wp_send_json_error(array('message' => __('Post not found in review queue.', 'ai-post-scheduler')));
		}
		
		// Check per-post capability
		if (!current_user_can('publish_post', $post_id)) {
			wp_send_json_error(array('message' => __('You do not have permission to publish this post.', 'ai-post-scheduler')));
		}
		
		$result = wp_update_post(array(
			'ID' => $post_id,
			'post_status' => 'publish',
		));
		
		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
		}
		
		// Log the publish activity
		$activity_repository = new AIPS_Activity_Repository();
		$activity_repository->create(array(
			'event_type' => 'post_published',
			'event_status' => 'success',
			'post_id' => $post_id,
			'message' => __('Post published from review queue', 'ai-post-scheduler'),
		));
		
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
			wp_send_json_error(array('message' => __('No posts selected.', 'ai-post-scheduler')));
		}
		
		$success_count = 0;
		$activity_repository = new AIPS_Activity_Repository();
		global $wpdb;
		$history_table = $wpdb->prefix . 'aips_history';
		
		foreach ($post_ids as $post_id) {
			// Verify the post exists and is a draft
			$post = get_post($post_id);
			if (!$post || $post->post_status !== 'draft') {
				continue;
			}
			
			// Verify the post is in the review queue
			$history_exists = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM {$history_table} WHERE post_id = %d AND status = 'completed'",
				$post_id
			));
			
			if (!$history_exists) {
				continue;
			}
			
			// Check per-post capability
			if (!current_user_can('publish_post', $post_id)) {
				continue;
			}
			
			$result = wp_update_post(array(
				'ID' => $post_id,
				'post_status' => 'publish',
			));
			
			if (!is_wp_error($result)) {
				$success_count++;
				
				// Log the publish activity
				$activity_repository->create(array(
					'event_type' => 'post_published',
					'event_status' => 'success',
					'post_id' => $post_id,
					'message' => __('Post published from review queue (bulk)', 'ai-post-scheduler'),
				));
				
				/**
				 * Fires after a post is published from the review queue.
				 *
				 * @param int $post_id Post ID that was published.
				 */
				do_action('aips_post_review_published', $post_id);
			}
		}
		
		wp_send_json_success(array(
			'message' => sprintf(__('%d posts published successfully.', 'ai-post-scheduler'), $success_count),
			'count' => $success_count,
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
		$history_repository = new AIPS_History_Repository();
		$history_item = $history_repository->get_by_id($history_id);
		
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
		$history_repository->update($history_id, array(
			'status' => 'pending',
			'post_id' => null,
			'error_message' => null,
		));
		
		// Trigger regeneration using the generator (same API as history retry)
		$generator = new AIPS_Generator();
		$result = $generator->generate_post($template);
		
		if (is_wp_error($result)) {
			// Log the regeneration failure
			$activity_repository = new AIPS_Activity_Repository();
			$activity_repository->create(array(
				'event_type' => 'post_regenerated',
				'event_status' => 'failed',
				'message' => sprintf(__('Post regeneration failed: %s', 'ai-post-scheduler'), $result->get_error_message()),
			));
			
			wp_send_json_error(array('message' => $result->get_error_message()));
		}
		
		// Log the regeneration success
		$activity_repository = new AIPS_Activity_Repository();
		$activity_repository->create(array(
			'event_type' => 'post_regenerated',
			'event_status' => 'success',
			'post_id' => $result,
			'message' => __('Post regenerated from review queue', 'ai-post-scheduler'),
		));
		
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
	 * AJAX handler to delete a draft post.
	 */
	public function ajax_delete_draft_post() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
		
		if (!$post_id) {
			wp_send_json_error(array('message' => __('Invalid post ID.', 'ai-post-scheduler')));
		}
		
		// Verify the post exists and is a draft
		$post = get_post($post_id);
		if (!$post || $post->post_status !== 'draft') {
			wp_send_json_error(array('message' => __('Post not found or not a draft.', 'ai-post-scheduler')));
		}
		
		// Verify the post is in the review queue
		global $wpdb;
		$history_table = $wpdb->prefix . 'aips_history';
		$history_exists = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$history_table} WHERE post_id = %d AND status = 'completed'",
			$post_id
		));
		
		if (!$history_exists) {
			wp_send_json_error(array('message' => __('Post not found in review queue.', 'ai-post-scheduler')));
		}
		
		// Check per-post capability
		if (!current_user_can('delete_post', $post_id)) {
			wp_send_json_error(array('message' => __('You do not have permission to delete this post.', 'ai-post-scheduler')));
		}
		
		// Delete the post
		$result = wp_delete_post($post_id, true);
		
		if (!$result) {
			wp_send_json_error(array('message' => __('Failed to delete post.', 'ai-post-scheduler')));
		}
		
		// Update history if history_id is provided
		if ($history_id) {
			$history_repository = new AIPS_History_Repository();
			$history_repository->update($history_id, array(
				'post_id' => null,
			));
		}
		
		// Log the delete activity
		$activity_repository = new AIPS_Activity_Repository();
		$activity_repository->create(array(
			'event_type' => 'post_deleted',
			'event_status' => 'success',
			'post_id' => $post_id,
			'message' => __('Draft post deleted from review queue', 'ai-post-scheduler'),
		));
		
		/**
		 * Fires after a post is deleted from the review queue.
		 *
		 * @param int $post_id Post ID that was deleted.
		 */
		do_action('aips_post_review_deleted', $post_id);
		
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
		
		$items = ( isset( $_POST['items'] ) && is_array( $_POST['items'] ) ) ? $_POST['items'] : array();
		
		if ( empty( $items ) ) {
			wp_send_json_error( array( 'message' => __( 'No posts selected.', 'ai-post-scheduler' ) ) );
		}
		
		$success_count = 0;
		$history_repository = new AIPS_History_Repository();
		$activity_repository = new AIPS_Activity_Repository();
		global $wpdb;
		$history_table = $wpdb->prefix . 'aips_history';
		
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$post_id    = isset( $item['post_id'] ) ? absint( $item['post_id'] ) : 0;
			$history_id = isset( $item['history_id'] ) ? absint( $item['history_id'] ) : 0;
			
			if ( ! $post_id ) {
				continue;
			}
			
			// Verify the post exists and is a draft
			$post = get_post($post_id);
			if (!$post || $post->post_status !== 'draft') {
				continue;
			}
			
			// Verify the post is in the review queue
			$history_exists = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM {$history_table} WHERE post_id = %d AND status = 'completed'",
				$post_id
			));
			
			if (!$history_exists) {
				continue;
			}
			
			// Check per-post capability
			if (!current_user_can('delete_post', $post_id)) {
				continue;
			}
			
			$result = wp_delete_post($post_id, true);
			
			if ($result) {
				$success_count++;
				
				// Update history if history_id is provided
				if ($history_id) {
					$history_repository->update($history_id, array(
						'post_id' => null,
					));
				}
				
				// Log the delete activity
				$activity_repository->create(array(
					'event_type' => 'post_deleted',
					'event_status' => 'success',
					'post_id' => $post_id,
					'message' => __('Draft post deleted from review queue (bulk)', 'ai-post-scheduler'),
				));
				
				/**
				 * Fires after a post is deleted from the review queue.
				 *
				 * @param int $post_id Post ID that was deleted.
				 */
				do_action('aips_post_review_deleted', $post_id);
			}
		}
		
		wp_send_json_success(array(
			'message' => sprintf(__('%d posts deleted successfully.', 'ai-post-scheduler'), $success_count),
			'count' => $success_count,
		));
	}
}
