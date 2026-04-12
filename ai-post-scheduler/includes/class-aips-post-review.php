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
	 * @var self|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * Get the shared singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @var AIPS_Post_Review_Repository Repository for database operations
	 */
	private $repository;
	
	/**
	 * @var AIPS_History_Service Service for history logging
	 */
	private $history_service;

	/**
	 * @var AIPS_Bulk_Generator_Service Shared bulk generation harness
	 */
	private $bulk_generator_service;
	
	/**
	 * Initialize the post review handler.
	 */
	public function __construct() {
		$this->repository             = new AIPS_Post_Review_Repository();
		$this->history_service        = new AIPS_History_Service();
		$this->bulk_generator_service = new AIPS_Bulk_Generator_Service( $this->history_service );
		
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
			AIPS_Ajax_Response::permission_denied();
		}

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

		if (!$post_id) {
			AIPS_Ajax_Response::error(__('Invalid post ID.', 'ai-post-scheduler'));
		}

		$post = get_post($post_id);

		if (!$post || $post->post_status !== 'draft') {
			AIPS_Ajax_Response::error(__('Post not found or not a draft.', 'ai-post-scheduler'));
		}

		// Prepare preview data
		$data = array(
			'title' => get_the_title($post),
			'content' => apply_filters('the_content', $post->post_content),
			'excerpt' => get_the_excerpt($post),
			'featured_image' => esc_url_raw(get_the_post_thumbnail_url($post_id, 'full')),
			'edit_url' => esc_url_raw(get_edit_post_link($post_id)),
		);

		AIPS_Ajax_Response::success($data);
	}

	/**
	 * AJAX handler to get draft posts.
	 */
	public function ajax_get_draft_posts() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
		
		$page = isset($_POST['page']) ? absint($_POST['page']) : 1;
		$search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
		$template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
		
		$draft_posts = $this->get_draft_posts(array(
			'page' => $page,
			'search' => $search,
			'template_id' => $template_id,
		));
		
		AIPS_Ajax_Response::success($draft_posts);
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
			AIPS_Ajax_Response::permission_denied();
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
			AIPS_Ajax_Response::error(__('Invalid post ID.', 'ai-post-scheduler'));
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
			AIPS_Ajax_Response::error(__('Post not found or not a draft.', 'ai-post-scheduler'));
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
			AIPS_Ajax_Response::error(__('Post not found in review queue.', 'ai-post-scheduler'));
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
			AIPS_Ajax_Response::error(__('You do not have permission to publish this post.', 'ai-post-scheduler'));
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
			AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
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
		
		AIPS_Ajax_Response::success(array(
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
			AIPS_Ajax_Response::permission_denied();
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
			AIPS_Ajax_Response::error(__('No posts selected.', 'ai-post-scheduler'));
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
		
		AIPS_Ajax_Response::success(array(
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
			AIPS_Ajax_Response::permission_denied();
		}
		
		$history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
		
		if (!$history_id) {
			AIPS_Ajax_Response::error(__('Invalid history ID.', 'ai-post-scheduler'));
		}
		
		// Get the history item
		$history_item = $this->history_service->get_by_id($history_id);
		
		if (!$history_item || !$history_item->template_id) {
			AIPS_Ajax_Response::error(__('History item not found or no template associated.', 'ai-post-scheduler'));
		}
		
		// Get the template
		$template_repository = new AIPS_Template_Repository();
		$template = $template_repository->get_by_id($history_item->template_id);
		
		if (!$template) {
			AIPS_Ajax_Response::error(__('Template not found.', 'ai-post-scheduler'));
		}
		
		// Delete the existing post if it exists
		if ($history_item->post_id) {
			// Verify per-post capability before deleting
			if (!current_user_can('delete_post', $history_item->post_id)) {
				AIPS_Ajax_Response::error(__('You do not have permission to regenerate this post.', 'ai-post-scheduler'));
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
			
			AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
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
		
		AIPS_Ajax_Response::success(array(
			'message' => __('Post regeneration started successfully.', 'ai-post-scheduler'),
			'history_id' => $history_id,
		));
	}
	
	/**
	 * AJAX handler to regenerate multiple posts.
	 *
	 * Delegates the batch harness (soft limit, history, loop) to
	 * AIPS_Bulk_Generator_Service.  Per-item validation guards are moved into
	 * the $generate_fn closure so they return WP_Error (counted as failures)
	 * rather than using continue statements.
	 *
	 * The `aips_post_review_bulk_regenerate_max_batch` filter controls how many
	 * items are processed in one request (soft truncation).
	 */
	public function ajax_bulk_regenerate_posts() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$items = (isset($_POST['items']) && is_array($_POST['items'])) ? wp_unslash($_POST['items']) : array();

		if (empty($items)) {
			AIPS_Ajax_Response::error(__('No posts selected.', 'ai-post-scheduler'));
		}

		$total_requested = count($items);
		$history_service = $this->history_service;
		$generator       = new AIPS_Generator();
		$template_repo   = new AIPS_Template_Repository();

		$result = $this->bulk_generator_service->run(
			$items,
			function ( $item ) use ( $history_service, $generator, $template_repo ) {
				if (!is_array($item)) {
					return new WP_Error(
						'invalid_item',
						__('Invalid item format.', 'ai-post-scheduler')
					);
				}

				$post_id    = isset($item['post_id'])    ? absint($item['post_id'])    : 0;
				$history_id = isset($item['history_id']) ? absint($item['history_id']) : 0;

				if (!$history_id) {
					return new WP_Error(
						'missing_history_id',
						__('Missing history ID.', 'ai-post-scheduler')
					);
				}

				$history_item = $history_service->get_by_id($history_id);

				if (!$history_item || !$history_item->template_id) {
					return new WP_Error(
						'no_history',
						sprintf(
							/* translators: %d: history record ID */
							__('History item not found or no template associated (history ID: %d)', 'ai-post-scheduler'),
							$history_id
						)
					);
				}

				$history_post_id = isset($history_item->post_id) ? absint($history_item->post_id) : 0;
				if (!$history_post_id) {
					return new WP_Error(
						'no_post_id',
						sprintf(
							/* translators: %d: history record ID */
							__('History record %d has no associated post ID', 'ai-post-scheduler'),
							$history_id
						)
					);
				}

				// Validate any client-supplied post_id against the trusted history value.
				if ($post_id && $post_id !== $history_post_id) {
					return new WP_Error(
						'post_mismatch',
						sprintf(
							/* translators: 1: client post ID, 2: history post ID */
							__('Post ID mismatch: client %1$d does not match history %2$d', 'ai-post-scheduler'),
							$post_id,
							$history_post_id
						)
					);
				}

				// Use the trusted ID from the history record from here on.
				$post_id = $history_post_id;

				$post = get_post($post_id);
				if (!$post || $post->post_status !== 'draft') {
					return new WP_Error(
						'not_draft',
						sprintf(
							/* translators: %d: post ID */
							__('Post ID %d not found or not a draft', 'ai-post-scheduler'),
							$post_id
						)
					);
				}

				if (!$history_service->post_has_history_and_completed($post_id)) {
					return new WP_Error(
						'not_in_queue',
						sprintf(
							/* translators: %d: post ID */
							__('Post ID %d is not in the review queue', 'ai-post-scheduler'),
							$post_id
						)
					);
				}

				$template = $template_repo->get_by_id($history_item->template_id);
				if (!$template) {
					return new WP_Error(
						'no_template',
						sprintf(
							/* translators: %d: post ID */
							__('Template not found for post ID %d', 'ai-post-scheduler'),
							$post_id
						)
					);
				}

				if (!current_user_can('delete_post', $post_id)) {
					return new WP_Error(
						'no_permission',
						sprintf(
							/* translators: %d: post ID */
							__('Insufficient permissions to regenerate post ID %d', 'ai-post-scheduler'),
							$post_id
						)
					);
				}

				if (!wp_delete_post($post_id, true)) {
					return new WP_Error(
						'delete_failed',
						sprintf(
							/* translators: %d: post ID */
							__('Failed to delete old post ID %d for regeneration', 'ai-post-scheduler'),
							$post_id
						)
					);
				}

				$history_service->update_history_record($history_id, array(
					'status'        => 'pending',
					'post_id'       => null,
					'error_message' => null,
				));

				$regen_result = $generator->generate_post($template);

				if (is_wp_error($regen_result)) {
					return $regen_result;
				}

				/**
				 * Fires after a post is regenerated from the review queue.
				 *
				 * @param int $history_id History ID of the regenerated post.
				 */
				do_action('aips_post_review_regenerated', $history_id);

				return $regen_result;
			},
			array(
				'limit_filter' => 'aips_post_review_bulk_regenerate_max_batch',
				'limit_mode'   => 'soft',
				'history_type' => 'bulk_regenerate',
				'history_meta' => array(
					'entity_type'  => 'draft_posts',
					'entity_count' => $total_requested,
				),
				'trigger_name' => 'ajax_bulk_regenerate_posts',
				'user_action'  => 'bulk_regenerate_posts',
				'user_message' => sprintf(
					/* translators: %d: number of draft posts */
					__('User initiated bulk regenerate for %d draft posts', 'ai-post-scheduler'),
					$total_requested
				),
			)
		);

		if ($result->failed_count > 0) {
			AIPS_Ajax_Response::error(array(
				'message'       => sprintf(
					/* translators: 1: number of successful regenerations, 2: number of failures */
					__('%1$d posts regeneration started successfully, %2$d failed.', 'ai-post-scheduler'),
					$result->success_count,
					$result->failed_count
				),
				'success_count' => $result->success_count,
				'failed_count'  => $result->failed_count,
			));
		} else {
			AIPS_Ajax_Response::success(array(
				'message'       => sprintf(
					/* translators: %d: number of posts */
					__('%d posts regeneration started successfully.', 'ai-post-scheduler'),
					$result->success_count
				),
				'success_count' => $result->success_count,
				'failed_count'  => $result->failed_count,
			));
		}
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
			AIPS_Ajax_Response::permission_denied();
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
			AIPS_Ajax_Response::error(__('Invalid post ID.', 'ai-post-scheduler'));
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
			AIPS_Ajax_Response::error(__('Post not found or not a draft.', 'ai-post-scheduler'));
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
			AIPS_Ajax_Response::error(__('Post not found in review queue.', 'ai-post-scheduler'));
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
			AIPS_Ajax_Response::error(__('You do not have permission to delete this post.', 'ai-post-scheduler'));
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
			AIPS_Ajax_Response::error(__('Failed to delete post.', 'ai-post-scheduler'));
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
		
		AIPS_Ajax_Response::success(array(
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
			AIPS_Ajax_Response::permission_denied();
		}
		
		$items = (isset($_POST['items']) && is_array($_POST['items'])) ? $_POST['items'] : array();
		
		if (empty($items)) {
			AIPS_Ajax_Response::error(__('No posts selected.', 'ai-post-scheduler'));
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
		
		AIPS_Ajax_Response::success(array(
			'message' => sprintf(__('%d posts deleted successfully.', 'ai-post-scheduler'), $success_count),
			'count' => $success_count,
			'failed' => $failed_count,
		));
	}
}
