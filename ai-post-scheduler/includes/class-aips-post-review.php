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
		add_action('wp_ajax_aips_get_post_preview', array($this, 'ajax_get_post_preview'));
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
	 * AJAX handler to get post preview data.
	 */
	public function ajax_get_post_preview() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

		if (!$post_id) {
			AIPS_Ajax_Response::error(__('Invalid post ID.', 'ai-post-scheduler'));
		}

		$post = get_post($post_id);

		if (!$post) {
			AIPS_Ajax_Response::error(__('Post not found.', 'ai-post-scheduler'));
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
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
		
		$page = isset($_POST['page']) ? absint($_POST['page']) : 1;
		$search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
		$template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
		$post_type = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : '';

		$draft_posts = $this->get_draft_posts(array(
			'page' => $page,
			'search' => $search,
			'template_id' => $template_id,
			'post_type' => $post_type,
		));
		
		AIPS_Ajax_Response::success($draft_posts);
	}
	
	/**
	 * Record a one-off review-queue action in its own history container and close it.
	 *
	 * Review actions are events, not generation runs: the container is closed as
	 * completed (or failed) immediately so it never lingers as "processing".
	 *
	 * @param int    $post_id      Related post ID, or 0 when there is none.
	 * @param string $message      Human-readable message.
	 * @param string $event_type   Event type (e.g. 'post_published').
	 * @param string $event_status 'success' or 'failed'.
	 * @param array  $context      Extra context stored with the event.
	 * @return void
	 */
	private function log_review_action($post_id, $message, $event_type, $event_status, $context = array()) {
		$post_id = absint($post_id);
		$subject = $post_id
			? AIPS_History_Subject::of(AIPS_History_Subject::TYPE_POST, $post_id)
			: AIPS_History_Subject::none();

		// The recorder writes the Trigger Source / Trigger Method entries, closes the
		// container, and never throws, so History cannot break the review action itself.
		AIPS_History_Event_Recorder::instance()->record_simple(
			$event_type,
			$event_status !== 'failed',
			$message,
			'post_review_action',
			array('event' => __('Review queue action', 'ai-post-scheduler')),
			'manual',
			$context,
			$subject
		);
	}

	/**
	 * Append a review-queue event to an existing container.
	 *
	 * @param AIPS_History_Container $history      Container to record into.
	 * @param int                    $post_id      Related post ID, or 0.
	 * @param string                 $message      Human-readable message.
	 * @param string                 $event_type   Event type.
	 * @param string                 $event_status 'success' or 'failed'.
	 * @param array                  $context      Extra context.
	 * @return void
	 */
	private function record_review_event($history, $post_id, $message, $event_type, $event_status, $context = array()) {
		if ($post_id) {
			$context = array_merge(array('post_id' => (int) $post_id), $context);
		}

		$history->record(
			'activity',
			$message,
			array('event_type' => $event_type, 'event_status' => $event_status),
			null,
			$context
		);
	}

	/**
	 * Close a bulk review container: failed when nothing succeeded, partial when
	 * only some items did, completed otherwise.
	 *
	 * @param AIPS_History_Container $history       Container to close.
	 * @param int                    $failed_count  Items that failed.
	 * @param int                    $success_count Items that succeeded.
	 * @param string                 $summary       Summary message.
	 * @return void
	 */
	private function close_review_container($history, $failed_count, $success_count, $summary) {
		$this->record_review_event(
			$history,
			0,
			$summary,
			'post_published',
			$failed_count > 0 && $success_count === 0 ? 'failed' : 'success',
			array('published' => (int) $success_count, 'failed' => (int) $failed_count)
		);

		if ($failed_count > 0 && $success_count === 0) {
			$history->complete_failure($summary);
		} elseif ($failed_count > 0) {
			$history->complete_partial($summary);
		} else {
			$history->complete_success();
		}
	}

	/**
	 * AJAX handler to publish a single post.
	 */
	public function ajax_publish_post() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		
		if (!current_user_can('manage_options')) {
			$this->log_review_action(0, __('Post publish failed: Permission denied', 'ai-post-scheduler'), 'post_published', 'failed');
			AIPS_Ajax_Response::permission_denied();
		}
		
		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

		$result = $this->publish_review_post($post_id);

		if (is_wp_error($result)) {
			$this->log_review_action($post_id, sprintf(__('Post publish failed: %s', 'ai-post-scheduler'), $result->get_error_message()), 'post_published', 'failed', array('error' => $result->get_error_message()));
			AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
		}

		// Log the publish activity
		$this->log_review_action($post_id, __('Post published from review queue', 'ai-post-scheduler'), 'post_published', 'success');

		AIPS_Ajax_Response::success(array(
			'message' => __('Post published successfully.', 'ai-post-scheduler'),
			'post_id' => $post_id,
		));
	}
	
	/**
	 * AJAX handler to publish multiple posts.
	 */
	public function ajax_bulk_publish_posts() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		
		if (!current_user_can('manage_options')) {
			$this->log_review_action(0, __('Bulk publish failed: Permission denied', 'ai-post-scheduler'), 'post_published', 'failed');
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
			$this->log_review_action(0, __('Bulk publish failed: No posts selected', 'ai-post-scheduler'), 'post_published', 'failed');
			AIPS_Ajax_Response::error(__('No posts selected.', 'ai-post-scheduler'));
		}
		
		// Large selections publish in the background so the request returns
		// immediately; small ones publish synchronously. The cutoff is admin-
		// configurable (Settings → Bulk Publish Background Threshold, default 30);
		// the filter still allows a programmatic override.
		$configured      = (int) AIPS_Config::get_instance()->get_option('aips_bulk_publish_async_threshold');
		$async_threshold = max(2, (int) apply_filters('aips_bulk_publish_async_threshold', $configured > 0 ? $configured : 30));

		if (count($post_ids) >= $async_threshold) {
			$this->dispatch_bulk_publish_async($post_ids);
			return;
		}

		// A summary container for the whole action; each post also gets its own
		// linked row so its publish outcome is traceable from the post.
		$bulk_history  = $this->history_service->create('post_review_action', array());
		$success_count = 0;
		$failed_count  = 0;

		if (function_exists('_prime_post_caches')) {
			_prime_post_caches(array_unique(array_map('intval', $post_ids)), false, true);
		}

		foreach ($post_ids as $post_id) {
			$result = $this->publish_review_post($post_id);

			if (is_wp_error($result)) {
				$failed_count++;
				$this->log_review_action($post_id, sprintf(__('Bulk publish failed: %s', 'ai-post-scheduler'), $result->get_error_message()), 'post_published', 'failed', array('error' => $result->get_error_message()));
			} else {
				$success_count++;
				$this->log_review_action($post_id, __('Post published from review queue (bulk)', 'ai-post-scheduler'), 'post_published', 'success');
			}
		}

		$this->close_review_container(
			$bulk_history,
			$failed_count,
			$success_count,
			sprintf(
				/* translators: 1: published count, 2: failed count */
				__('Bulk publish from review queue: %1$d published, %2$d failed', 'ai-post-scheduler'),
				$success_count,
				$failed_count
			)
		);

		AIPS_Ajax_Response::success(array(
			'message' => sprintf(__('%d posts published successfully.', 'ai-post-scheduler'), $success_count),
			'count'   => $success_count,
			'failed'  => $failed_count,
		));
	}

	/**
	 * Publish a single draft post from the review queue.
	 *
	 * Shared by the single, synchronous-bulk, and background-bulk publish paths.
	 * Runs the review-queue guards, then uses wp_publish_post() — WordPress's
	 * purpose-built draft→publish transition. It is lighter than wp_update_post()
	 * (no field re-sanitisation, no revision) while still firing the publish
	 * transition hooks, and is the same call WordPress uses to publish scheduled
	 * posts. The post-publish action fires here so it runs exactly once regardless
	 * of caller; logging is left to the caller so each path can record into its
	 * own history container.
	 *
	 * @param int $post_id Draft post ID to publish.
	 * @return int|WP_Error Published post ID, or WP_Error on failure.
	 */
	public function publish_review_post($post_id) {
		$post_id = absint($post_id);

		if (!$post_id) {
			return new WP_Error('aips_invalid_post_id', __('Invalid post ID.', 'ai-post-scheduler'));
		}

		// Verify the post exists and is a draft managed by this plugin.
		$post = get_post($post_id);
		if (!$post || $post->post_status !== 'draft') {
			return new WP_Error('aips_not_draft', __('Post not found or not a draft.', 'ai-post-scheduler'));
		}

		// Verify the post is in the review queue (has a completed history record).
		if (!$this->history_service->post_has_history_and_completed($post_id)) {
			return new WP_Error('aips_not_in_review_queue', __('Post not found in review queue.', 'ai-post-scheduler'));
		}

		// Per-post capability check.
		if (!current_user_can('publish_post', $post_id)) {
			return new WP_Error('aips_cannot_publish', __('You do not have permission to publish this post.', 'ai-post-scheduler'));
		}

		// wp_publish_post() returns void; confirm the transition from the stored status.
		wp_publish_post($post_id);
		clean_post_cache($post_id);

		if ('publish' !== get_post_status($post_id)) {
			return new WP_Error('aips_publish_failed', __('Failed to publish the post.', 'ai-post-scheduler'));
		}

		/**
		 * Fires after a post is published from the review queue.
		 *
		 * @param int $post_id Post ID that was published.
		 */
		do_action('aips_post_review_published', $post_id);

		return $post_id;
	}

	/**
	 * Queue a large bulk-publish selection for background processing.
	 *
	 * Reuses the shared AIPS_Bulk_Generator_Service dispatch harness with the
	 * 'review_publish' job type (registered in boot_cron()). The request returns
	 * immediately; AIPS_Bulk_Batch_Processor publishes each post in cron slices
	 * and progress is visible under Diagnostics → Background Processes.
	 *
	 * Publishing has no AI rate limits, so the batch spread is tightened relative
	 * to generation (scoped to this dispatch only) so the posts go live promptly.
	 *
	 * @param int[] $post_ids Sanitised draft post IDs to publish.
	 * @return void Emits a JSON response and exits.
	 */
	private function dispatch_bulk_publish_async(array $post_ids) {
		$count = count($post_ids);

		// Publishing is cheap and rate-limit free: fire the slices close together
		// instead of across the generation window. Scoped to this dispatch only.
		$tighten_window = static function() { return max(0, (int) apply_filters('aips_bulk_publish_window_seconds', 60)); };
		$cap_slices     = static function() { return max(1, (int) apply_filters('aips_bulk_publish_max_slices', 4)); };
		add_filter('aips_batch_window_seconds', $tighten_window);
		add_filter('aips_batch_max_slices', $cap_slices);

		try {
			$result = $this->bulk_generator_service->run(
				$post_ids,
				array($this, 'publish_review_post'),
				array(
					'queue_job_type'  => 'review_publish',
					'history_type'    => 'review_publish',
					'trigger_name'    => 'ajax_bulk_publish_posts',
					'user_action'     => 'bulk_publish',
					/* translators: %d: number of posts */
					'user_message'    => sprintf(__('User initiated bulk publish for %d posts', 'ai-post-scheduler'), $count),
					// Persisted into the job so the cron strategy can publish as the
					// acting administrator (cron has no current user of its own).
					'history_meta'    => array('acting_user_id' => get_current_user_id()),
					// Never truncate/reject on the rare synchronous fallback (when the
					// global large-batch threshold is filtered above this selection).
					'limit_default'   => $count,
					'limit_mode'      => 'soft',
					'error_formatter' => static function($post_id, $msg) {
						/* translators: 1: post ID, 2: error message */
						return sprintf(__('Post ID %1$d: %2$s', 'ai-post-scheduler'), (int) $post_id, $msg);
					},
				)
			);
		} finally {
			remove_filter('aips_batch_window_seconds', $tighten_window);
			remove_filter('aips_batch_max_slices', $cap_slices);
		}

		if ($result->was_queued) {
			AIPS_Ajax_Response::success(array(
				'queued'  => true,
				'job_id'  => $result->job_id,
				'count'   => 0,
				'failed'  => 0,
				/* translators: %d: number of posts */
				'message' => sprintf(__('Publishing %d posts in the background. They will go live shortly.', 'ai-post-scheduler'), $count),
			));
		}

		// Synchronous fallback (large-batch queuing disabled or unavailable): the
		// harness published inline, so report its real counts.
		AIPS_Ajax_Response::success(array(
			'count'   => $result->success_count,
			'failed'  => $result->failed_count,
			/* translators: %d: number of posts published */
			'message' => sprintf(__('%d posts published successfully.', 'ai-post-scheduler'), $result->success_count),
		));
	}
	
	/**
	 * AJAX handler to regenerate a post.
	 */
	public function ajax_regenerate_post() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		
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
		$result = $generator->generate_post($template, null, null, array(
			'creation_method' => 'regenerate',
			'trigger_context' => array('detail' => sprintf(__('Regeneration of History #%d', 'ai-post-scheduler'), (int) $history_id)),
		));
		
		if (is_wp_error($result)) {
			// Log the regeneration failure
			$this->log_review_action(0, sprintf(__('Post regeneration failed: %s', 'ai-post-scheduler'), $result->get_error_message()), 'post_regenerated', 'failed', array('error' => $result->get_error_message()));
			
			AIPS_Ajax_Response::wp_error($result);
			return;
		}
		
		// Log the regeneration success
		$this->log_review_action($result, __('Post regenerated from review queue', 'ai-post-scheduler'), 'post_regenerated', 'success');
		
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
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

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

				$regen_result = $generator->generate_post($template, null, null, array(
					'creation_method' => 'regenerate',
					'trigger_context' => array('detail' => sprintf(__('Bulk regeneration of History #%d', 'ai-post-scheduler'), (int) $history_id)),
				));

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
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		
		if (!current_user_can('manage_options')) {
			$this->log_review_action(0, __('Post delete failed: Permission denied', 'ai-post-scheduler'), 'post_deleted', 'failed');
			AIPS_Ajax_Response::permission_denied();
		}
		
		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
		
		if (!$post_id) {
			$this->log_review_action(0, __('Post delete failed: Invalid post ID', 'ai-post-scheduler'), 'post_deleted', 'failed');
			AIPS_Ajax_Response::error(__('Invalid post ID.', 'ai-post-scheduler'));
		}
		
		// Verify the post exists and is a draft
		$post = get_post($post_id);
		if (!$post || $post->post_status !== 'draft') {
			$this->log_review_action($post_id, __('Post delete failed: Post not found or not a draft', 'ai-post-scheduler'), 'post_deleted', 'failed');
			AIPS_Ajax_Response::error(__('Post not found or not a draft.', 'ai-post-scheduler'));
		}
		
		// Verify the post is in the review queue
		if (!$this->history_service->post_has_history_and_completed($post_id)) {
			$this->log_review_action($post_id, __('Post delete failed: Post not in review queue', 'ai-post-scheduler'), 'post_deleted', 'failed');
			AIPS_Ajax_Response::error(__('Post not found in review queue.', 'ai-post-scheduler'));
		}
		
		// Check per-post capability
		if (!current_user_can('delete_post', $post_id)) {
			$this->log_review_action($post_id, __('Post delete failed: Insufficient permissions', 'ai-post-scheduler'), 'post_deleted', 'failed');
			AIPS_Ajax_Response::error(__('You do not have permission to delete this post.', 'ai-post-scheduler'));
		}
		
		// Delete the post
		$result = wp_delete_post($post_id, true);
		
		if (!$result) {
			$this->log_review_action($post_id, __('Post delete failed: Unable to delete post', 'ai-post-scheduler'), 'post_deleted', 'failed');
			AIPS_Ajax_Response::error(__('Failed to delete post.', 'ai-post-scheduler'));
		}
		
		// Update history if history_id is provided
		if ($history_id) {
			$this->history_service->update_history_record($history_id, array(
				'post_id' => null,
			));
		}
		
		// Log the delete activity
		$this->log_review_action($post_id, __('Draft post deleted from review queue', 'ai-post-scheduler'), 'post_deleted', 'success');
		
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
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		
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
