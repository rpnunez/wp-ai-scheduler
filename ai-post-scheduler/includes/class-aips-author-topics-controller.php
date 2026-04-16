<?php
/**
 * Author Topics Controller
 *
 * Handles AJAX requests for author topic management (approve, reject, edit, delete, etc.).
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Author_Topics_Controller
 *
 * Manages AJAX endpoints for topic approval workflow.
 */
class AIPS_Author_Topics_Controller {

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
	 * @var AIPS_Author_Topics_Repository Repository for topics
	 */
	private $repository;

	/**
	 * @var AIPS_Author_Topic_Logs_Repository Repository for logs
	 */
	private $logs_repository;

	/**
	 * @var AIPS_Feedback_Repository Repository for feedback
	 */
	private $feedback_repository;

	/**
	 * @var AIPS_Author_Post_Generator Post generator
	 */
	private $post_generator;

	/**
	 * @var AIPS_Topic_Penalty_Service Penalty service
	 */
	private $penalty_service;

	/**
	 * @var AIPS_History_Service_Interface Service for history logging
	 */
	private $history_service;

	/**
	 * @var AIPS_Topic_Expansion_Service Service for topic expansion/similarity
	 */
	private $expansion_service;

	/**
	 * @var AIPS_History_Repository_Interface Repository for history data
	 */
	private $history_repository;

	/**
	 * @var AIPS_Bulk_Generator_Service Shared bulk generation harness
	 */
	private $bulk_generator_service;

	/**
	 * Initialize the controller.
	 *
	 * @param AIPS_Topic_Expansion_Service|null  $expansion_service      Topic expansion service.
	 * @param AIPS_History_Repository_Interface|null $history_repository  History repository.
	 * @param AIPS_Bulk_Generator_Service|null   $bulk_generator_service Bulk generator service.
	 */
	public function __construct($expansion_service = null, ?AIPS_History_Repository_Interface $history_repository = null, $bulk_generator_service = null) {
		$container = AIPS_Container::get_instance();
		$this->repository             = new AIPS_Author_Topics_Repository();
		$this->logs_repository        = new AIPS_Author_Topic_Logs_Repository();
		$this->feedback_repository    = new AIPS_Feedback_Repository();
		$this->post_generator         = new AIPS_Author_Post_Generator();
		$this->penalty_service        = new AIPS_Topic_Penalty_Service();
		$this->history_service        = $container->has(AIPS_History_Service_Interface::class) ? $container->make(AIPS_History_Service_Interface::class) : new AIPS_History_Service();
		$this->expansion_service      = $expansion_service ?: new AIPS_Topic_Expansion_Service();
		$this->history_repository     = $history_repository ?: ($container->has(AIPS_History_Repository_Interface::class) ? $container->make(AIPS_History_Repository_Interface::class) : new AIPS_History_Repository());
		$this->bulk_generator_service = $bulk_generator_service ?: new AIPS_Bulk_Generator_Service( $this->history_service );

		// Register AJAX endpoints
		add_action('wp_ajax_aips_approve_topic', array($this, 'ajax_approve_topic'));
		add_action('wp_ajax_aips_reject_topic', array($this, 'ajax_reject_topic'));
		add_action('wp_ajax_aips_edit_topic', array($this, 'ajax_edit_topic'));
		add_action('wp_ajax_aips_delete_topic', array($this, 'ajax_delete_topic'));
		add_action('wp_ajax_aips_generate_post_from_topic', array($this, 'ajax_generate_post_from_topic'));
		add_action('wp_ajax_aips_get_topic_logs', array($this, 'ajax_get_topic_logs'));
		add_action('wp_ajax_aips_get_topic_feedback', array($this, 'ajax_get_topic_feedback'));
		add_action('wp_ajax_aips_bulk_approve_topics', array($this, 'ajax_bulk_approve_topics'));
		add_action('wp_ajax_aips_bulk_reject_topics', array($this, 'ajax_bulk_reject_topics'));
		add_action('wp_ajax_aips_bulk_delete_topics', array($this, 'ajax_bulk_delete_topics'));
		add_action('wp_ajax_aips_bulk_generate_topics', array($this, 'ajax_bulk_generate_topics'));
		add_action('wp_ajax_aips_bulk_delete_feedback', array($this, 'ajax_bulk_delete_feedback'));
		add_action('wp_ajax_aips_regenerate_post', array($this, 'ajax_regenerate_post'));
		add_action('wp_ajax_aips_delete_generated_post', array($this, 'ajax_delete_generated_post'));
		add_action('wp_ajax_aips_get_similar_topics', array($this, 'ajax_get_similar_topics'));
		add_action('wp_ajax_aips_suggest_related_topics', array($this, 'ajax_suggest_related_topics'));
		add_action('wp_ajax_aips_compute_topic_embeddings', array($this, 'ajax_compute_topic_embeddings'));
		add_action('wp_ajax_aips_get_generation_queue', array($this, 'ajax_get_generation_queue'));
		add_action('wp_ajax_aips_bulk_generate_from_queue', array($this, 'ajax_bulk_generate_from_queue'));
		add_action('wp_ajax_aips_get_bulk_generate_estimate', array($this, 'ajax_get_bulk_generate_estimate'));
	}

	/**
	 * AJAX handler for approving a topic.
	 */
	public function ajax_approve_topic() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
		$reason = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '';
		$reason_category = isset($_POST['reason_category']) ? sanitize_text_field(wp_unslash($_POST['reason_category'])) : 'other';
		$source = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : 'UI';

		if (!$topic_id) {
			AIPS_Ajax_Response::error(__('Invalid topic ID.', 'ai-post-scheduler'));
		}

		$result = $this->repository->update_status($topic_id, 'approved', get_current_user_id());

		if ($result) {
			// Get topic details for logging
			$topic = $this->repository->get_by_id($topic_id);

			// Log the approval
			$this->logs_repository->log_approval($topic_id, get_current_user_id());

			// Record feedback with reason context.
			$this->feedback_repository->record_approval($topic_id, get_current_user_id(), $reason, '', $reason_category, $source);

			// Apply reward for approval
			$this->penalty_service->apply_reward($topic_id, $reason_category);

			// Log to activity feed using History Container
			if ($topic) {
				$approve_history = $this->history_service->create('topic_approval', array(
					'topic_id' => $topic_id,
				));
				$approve_history->record(
					'activity',
					sprintf(
						__('Topic approved: "%s"', 'ai-post-scheduler'),
						$topic->topic_title
					),
					array(
						'event_type' => 'topic_approved',
						'event_status' => 'success',
					),
					null,
					array(
						'topic_id' => $topic_id,
						'topic_title' => $topic->topic_title,
						'author_id' => $topic->author_id,
						'reason' => $reason,
						'reason_category' => $reason_category,
						'source' => $source,
						'approved_by' => get_current_user_id(),
					)
				);
			}

			AIPS_Ajax_Response::success(array(), __('Topic approved successfully.', 'ai-post-scheduler'));
		} else {
			AIPS_Ajax_Response::error(__('Failed to approve topic.', 'ai-post-scheduler'));
		}
	}

	/**
	 * AJAX handler for rejecting a topic.
	 */
	public function ajax_reject_topic() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
		$reason = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '';
		$reason_category = isset($_POST['reason_category']) ? sanitize_text_field(wp_unslash($_POST['reason_category'])) : 'other';
		$source = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : 'UI';

		if (!$topic_id) {
			AIPS_Ajax_Response::error(__('Invalid topic ID.', 'ai-post-scheduler'));
		}

		$result = $this->repository->update_status($topic_id, 'rejected', get_current_user_id());

		if ($result) {
			// Get topic details for logging
			$topic = $this->repository->get_by_id($topic_id);

			// Log the rejection
			$this->logs_repository->log_rejection($topic_id, get_current_user_id());

			// Record feedback with reason context.
			$this->feedback_repository->record_rejection($topic_id, get_current_user_id(), $reason, '', $reason_category, $source);

			// Apply penalty based on reason category
			$this->penalty_service->apply_penalty($topic_id, $reason_category);

			// Log to activity feed using History Container
			if ($topic) {
				$reject_history = $this->history_service->create('topic_rejection', array(
					'topic_id' => $topic_id,
				));
				$reject_history->record(
					'activity',
					sprintf(
						__('Topic rejected: "%s"', 'ai-post-scheduler'),
						$topic->topic_title
					),
					array(
						'event_type' => 'topic_rejected',
						'event_status' => 'failed',
					),
					null,
					array(
						'topic_id' => $topic_id,
						'topic_title' => $topic->topic_title,
						'author_id' => $topic->author_id,
						'reason' => $reason,
						'reason_category' => $reason_category,
						'source' => $source,
						'rejected_by' => get_current_user_id(),
					)
				);
			}

			AIPS_Ajax_Response::success(array(), __('Topic rejected successfully.', 'ai-post-scheduler'));
		} else {
			AIPS_Ajax_Response::error(__('Failed to reject topic.', 'ai-post-scheduler'));
		}
	}

	/**
	 * AJAX handler for editing a topic.
	 */
	public function ajax_edit_topic() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
		$new_title = isset($_POST['topic_title']) ? sanitize_text_field(wp_unslash($_POST['topic_title'])) : '';

		if (!$topic_id || empty($new_title)) {
			AIPS_Ajax_Response::error(__('Invalid topic ID or title.', 'ai-post-scheduler'));
		}

		// Get old title for logging
		$topic = $this->repository->get_by_id($topic_id);
		$old_title = $topic ? $topic->topic_title : '';

		$result = $this->repository->update($topic_id, array('topic_title' => $new_title));

		if ($result) {
			// Log the edit
			$this->logs_repository->log_edit(
				$topic_id,
				get_current_user_id(),
				"Changed from: {$old_title}"
			);

			AIPS_Ajax_Response::success(array(), __('Topic updated successfully.', 'ai-post-scheduler'));
		} else {
			AIPS_Ajax_Response::error(__('Failed to update topic.', 'ai-post-scheduler'));
		}
	}

	/**
	 * AJAX handler for deleting a topic.
	 */
	public function ajax_delete_topic() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;

		if (!$topic_id) {
			AIPS_Ajax_Response::error(__('Invalid topic ID.', 'ai-post-scheduler'));
		}

		$result = $this->repository->delete($topic_id);

		if ($result) {
			AIPS_Ajax_Response::success(array(), __('Topic deleted successfully.', 'ai-post-scheduler'));
		} else {
			AIPS_Ajax_Response::error(__('Failed to delete topic.', 'ai-post-scheduler'));
		}
	}

	/**
	 * AJAX handler for generating a post from a topic.
	 */
	public function ajax_generate_post_from_topic() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;

		if (!$topic_id) {
			AIPS_Ajax_Response::error(__('Invalid topic ID.', 'ai-post-scheduler'));
		}

		// Check if topic is approved
		$topic = $this->repository->get_by_id($topic_id);
		if (!$topic || $topic->status !== 'approved') {
			AIPS_Ajax_Response::error(__('Only approved topics can generate posts.', 'ai-post-scheduler'));
		}

		// Create history container for manual generation
		$history = $this->history_service->create('manual_generation', array(
			'topic_id' => $topic_id,
			'user_id' => get_current_user_id(),
			'source' => 'manual_ui',
			'trigger' => 'ajax_generate_post_from_topic'
		));

		$history->record_user_action(
			'manual_topic_generation',
			sprintf(__('User manually triggered post generation from topic: %s', 'ai-post-scheduler'), $topic->topic_title),
			array('topic_id' => $topic_id, 'topic_title' => $topic->topic_title)
		);

		$result = $this->post_generator->generate_now($topic_id);

		if (is_wp_error($result)) {
			$history->record_error(
				sprintf(__('Manual post generation failed for topic: %s', 'ai-post-scheduler'), $topic->topic_title),
				array('topic_id' => $topic_id, 'error_code' => 'GENERATION_FAILED'),
				$result
			);
			$history->complete_failure($result->get_error_message(), array('topic_id' => $topic_id));
			AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
		}

		$history->record('activity', __('Post generated successfully from topic', 'ai-post-scheduler'), null, null, array(
			'post_id' => $result,
			'topic_id' => $topic_id
		));
		$history->complete_success(array('post_id' => $result, 'topic_id' => $topic_id));

		AIPS_Ajax_Response::success(array(
			'message' => __('Post generated successfully.', 'ai-post-scheduler'),
			'post_id' => $result
		));
	}

	/**
	 * AJAX handler for getting topic logs.
	 */
	public function ajax_get_topic_logs() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;

		if (!$topic_id) {
			AIPS_Ajax_Response::error(__('Invalid topic ID.', 'ai-post-scheduler'));
		}

		// Ensure topic exists before fetching logs
		$topic = $this->repository->get_by_id($topic_id);
		if (!$topic) {
			AIPS_Ajax_Response::error(__('Topic not found.', 'ai-post-scheduler'));
		}

		$logs = $this->logs_repository->get_by_topic($topic_id, 200);
		foreach ($logs as &$log) {
			if ($log->user_id) {
				$user = get_user_by('id', $log->user_id);
				$log->user_name = $user ? $user->display_name : 'Unknown';
			}
		}

		AIPS_Ajax_Response::success(array('logs' => $logs));
	}

	/**
	 * AJAX handler for bulk approving topics.
	 */
	public function ajax_bulk_approve_topics() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$topic_ids = isset($_POST['topic_ids']) && is_array($_POST['topic_ids']) ? array_map('absint', $_POST['topic_ids']) : array();

		if (empty($topic_ids)) {
			AIPS_Ajax_Response::error(__('No topics selected.', 'ai-post-scheduler'));
		}

		$success_count = 0;
		$failed_count  = 0;
		foreach ($topic_ids as $topic_id) {
			$result = $this->repository->update_status($topic_id, 'approved', get_current_user_id());
			if ($result) {
				$this->logs_repository->log_approval($topic_id, get_current_user_id());
				$success_count++;
			} else {
				$failed_count++;
			}
		}

		$message = sprintf(__('%d topics approved successfully.', 'ai-post-scheduler'), $success_count);
		if ($failed_count > 0) {
			$message .= ' ' . sprintf(__('%d failed.', 'ai-post-scheduler'), $failed_count);
		}

		AIPS_Ajax_Response::success(array(
			'message'       => $message,
			'success_count' => $success_count,
			'failed_count'  => $failed_count,
		));
	}

	/**
	 * AJAX handler for bulk rejecting topics.
	 */
	public function ajax_bulk_reject_topics() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$topic_ids = isset($_POST['topic_ids']) && is_array($_POST['topic_ids']) ? array_map('absint', $_POST['topic_ids']) : array();

		if (empty($topic_ids)) {
			AIPS_Ajax_Response::error(__('No topics selected.', 'ai-post-scheduler'));
		}

		$success_count = 0;
		$failed_count  = 0;
		foreach ($topic_ids as $topic_id) {
			$result = $this->repository->update_status($topic_id, 'rejected', get_current_user_id());
			if ($result) {
				$this->logs_repository->log_rejection($topic_id, get_current_user_id());
				$success_count++;
			} else {
				$failed_count++;
			}
		}

		$message = sprintf(__('%d topics rejected successfully.', 'ai-post-scheduler'), $success_count);
		if ($failed_count > 0) {
			$message .= ' ' . sprintf(__('%d failed.', 'ai-post-scheduler'), $failed_count);
		}

		AIPS_Ajax_Response::success(array(
			'message'       => $message,
			'success_count' => $success_count,
			'failed_count'  => $failed_count,
		));
	}

	/**
	 * AJAX handler for bulk deleting topics.
	 */
	public function ajax_bulk_delete_topics() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$topic_ids = isset($_POST['topic_ids']) && is_array($_POST['topic_ids']) ? array_map('absint', $_POST['topic_ids']) : array();

		if (empty($topic_ids)) {
			AIPS_Ajax_Response::error(__('No topics selected.', 'ai-post-scheduler'));
		}

		// Create history container for bulk delete operation
		$history = $this->history_service->create('bulk_delete', array(
			'user_id' => get_current_user_id(),
			'source' => 'manual_ui',
			'trigger' => 'ajax_bulk_delete_topics',
			'entity_type' => 'topics',
			'entity_count' => count($topic_ids)
		));

		$history->record_user_action(
			'bulk_delete_topics',
			sprintf(__('User initiated bulk delete for %d topics', 'ai-post-scheduler'), count($topic_ids)),
			array('topic_ids' => $topic_ids, 'topic_count' => count($topic_ids))
		);

		$success_count = 0;
		$failed_count  = 0;
		foreach ($topic_ids as $topic_id) {
			$result = $this->repository->delete($topic_id);
			if ($result) {
				$success_count++;
			} else {
				$failed_count++;
				$history->record('warning', sprintf(__('Failed to delete topic ID %d', 'ai-post-scheduler'), $topic_id), null, null, array('topic_id' => $topic_id));
			}
		}

		$history->record('activity', sprintf(__('Deleted %d topics', 'ai-post-scheduler'), $success_count), null, null, array(
			'deleted_count' => $success_count,
			'requested_count' => count($topic_ids)
		));

		$message = sprintf(__('%d topics deleted successfully.', 'ai-post-scheduler'), $success_count);
		if ($failed_count > 0) {
			$message .= ' ' . sprintf(__('%d failed.', 'ai-post-scheduler'), $failed_count);
			$history->complete_success(array('deleted_count' => $success_count, 'failed_count' => $failed_count));
		} else {
			$history->complete_success(array('deleted_count' => $success_count));
		}

		AIPS_Ajax_Response::success(array(
			'message'       => $message,
			'success_count' => $success_count,
			'failed_count'  => $failed_count,
		));
	}

	/**
	 * AJAX handler for regenerating a post.
	 */
	public function ajax_regenerate_post() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;

		if (!$post_id || !$topic_id) {
			AIPS_Ajax_Response::error(__('Invalid post or topic ID.', 'ai-post-scheduler'));
		}

		// Create history container for regeneration
		$history = $this->history_service->create('manual_regeneration', array(
			'user_id' => get_current_user_id(),
			'source' => 'manual_ui',
			'trigger' => 'ajax_regenerate_post',
			'post_id' => $post_id,
			'topic_id' => $topic_id
		));

		$history->record_user_action(
			'regenerate_post',
			sprintf(__('User initiated post regeneration for post ID %d from topic ID %d', 'ai-post-scheduler'), $post_id, $topic_id),
			array('post_id' => $post_id, 'topic_id' => $topic_id)
		);

		$result = $this->post_generator->regenerate_post($post_id, $topic_id);

		if (is_wp_error($result)) {
			$history->record_error(
				sprintf(__('Post regeneration failed for post ID %d', 'ai-post-scheduler'), $post_id),
				array('post_id' => $post_id, 'topic_id' => $topic_id, 'error_code' => 'REGENERATION_FAILED'),
				$result
			);
			$history->complete_failure($result->get_error_message(), array('post_id' => $post_id, 'topic_id' => $topic_id));
			AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
		}

		$history->record('activity', __('Post regenerated successfully', 'ai-post-scheduler'), null, null, array(
			'post_id' => $result,
			'original_post_id' => $post_id,
			'topic_id' => $topic_id
		));
		$history->complete_success(array('post_id' => $result));

		AIPS_Ajax_Response::success(array(
			'message' => __('Post regenerated successfully.', 'ai-post-scheduler'),
			'post_id' => $result
		));
	}

	/**
	 * AJAX handler for deleting a generated post.
	 */
	public function ajax_delete_generated_post() {
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

		$result = wp_delete_post($post_id, true);

		if ($result) {
			AIPS_Ajax_Response::success(array(), __('Post deleted successfully.', 'ai-post-scheduler'));
		} else {
			AIPS_Ajax_Response::error(__('Failed to delete post.', 'ai-post-scheduler'));
		}
	}

	/**
	 * AJAX handler for getting topic feedback.
	 */
	public function ajax_get_topic_feedback() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;

		if (!$topic_id) {
			AIPS_Ajax_Response::error(__('Invalid topic ID.', 'ai-post-scheduler'));
		}

		$feedback = $this->feedback_repository->get_by_topic($topic_id);

		// Get user display names
		foreach ($feedback as $item) {
			if ($item->user_id) {
				$user = get_userdata($item->user_id);
				$item->user_name = $user ? $user->display_name : __('Unknown', 'ai-post-scheduler');
			} else {
				$item->user_name = __('System', 'ai-post-scheduler');
			}
		}

		AIPS_Ajax_Response::success(array('feedback' => $feedback));
	}

	/**
	 * AJAX handler for getting similar topics.
	 */
	public function ajax_get_similar_topics() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
		$author_id = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;
		$limit = isset($_POST['limit']) ? absint($_POST['limit']) : 5;

		if (!$topic_id || !$author_id) {
			AIPS_Ajax_Response::error(__('Invalid topic or author ID.', 'ai-post-scheduler'));
		}

		$similar_topics = $this->expansion_service->find_similar_topics($topic_id, $author_id, $limit);

		// Enrich with topic details
		foreach ($similar_topics as &$item) {
			if (isset($item['id'])) {
				$topic = $this->repository->get_by_id($item['id']);
				if ($topic) {
					$item['topic_title'] = $topic->topic_title;
					$item['status'] = $topic->status;
				}
			}
		}

		AIPS_Ajax_Response::success(array('similar_topics' => $similar_topics));
	}

	/**
	 * AJAX handler for suggesting related topics.
	 */
	public function ajax_suggest_related_topics() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$author_id = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;
		$limit = isset($_POST['limit']) ? absint($_POST['limit']) : 10;

		if (!$author_id) {
			AIPS_Ajax_Response::error(__('Invalid author ID.', 'ai-post-scheduler'));
		}

		$suggestions = $this->expansion_service->suggest_related_topics($author_id, $limit);

		AIPS_Ajax_Response::success(array('suggestions' => $suggestions));
	}

	/**
	 * AJAX handler for computing topic embeddings.
	 *
	 * Schedules background jobs instead of computing embeddings inline.
	 * When author_id === 0, schedules one job per author; otherwise schedules a single job.
	 */
	public function ajax_compute_topic_embeddings() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$author_id = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;
		$batch_size = isset($_POST['batch_size']) ? absint($_POST['batch_size']) : 20;

		// Sanitize batch size
		$batch_size = max(1, min(100, $batch_size));

		$queued_count = 0;

		if ($author_id === 0) {
			// Schedule one job per author
			$authors_repo = new AIPS_Authors_Repository();
			$authors = $authors_repo->get_all();

			foreach ($authors as $author) {
				$this->schedule_embeddings_job((int) $author->id, $batch_size, 0);
				$queued_count++;
			}

			$message = sprintf(
				__('Queued embeddings processing for %d author(s). Processing will run in the background.', 'ai-post-scheduler'),
				$queued_count
			);
		} else {
			// Schedule one job for the given author
			$this->schedule_embeddings_job($author_id, $batch_size, 0);
			$queued_count = 1;

			$message = sprintf(
				__('Queued embeddings processing for author ID %d. Processing will run in the background.', 'ai-post-scheduler'),
				$author_id
			);
		}

		AIPS_Ajax_Response::success(array(
			'message' => $message,
			'queued_count' => $queued_count
		));
	}

	/**
	 * Schedule a background embeddings processing job.
	 *
	 * @param int $author_id         Author ID.
	 * @param int $batch_size        Batch size for processing.
	 * @param int $last_processed_id Last processed topic ID.
	 * @return void
	 */
	private function schedule_embeddings_job($author_id, $batch_size, $last_processed_id) {
		$args = array(
			'author_id'         => $author_id,
			'batch_size'        => $batch_size,
			'last_processed_id' => $last_processed_id,
		);

		// Schedule to run in a few seconds
		$timestamp = time() + 5;

		// Prefer Action Scheduler if available, otherwise use wp_schedule_single_event
		if (function_exists('as_schedule_single_action')) {
			call_user_func('as_schedule_single_action', $timestamp, 'aips_process_author_embeddings', $args, 'aips-embeddings');
		} else {
			wp_schedule_single_event($timestamp, 'aips_process_author_embeddings', array($args));
		}
	}

	/**
	 * AJAX handler for getting all approved topics for the generation queue.
	 */
	public function ajax_get_generation_queue() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$topics = $this->repository->get_all_approved_for_queue();

		AIPS_Ajax_Response::success(array('topics' => $topics));
	}

	/**
	 * AJAX handler for bulk generating posts from queue topics.
	 *
	 * Delegates to AIPS_Bulk_Generator_Service via _do_bulk_generate_topics().
	 * The `aips_bulk_run_now_limit` filter and history logging are handled there.
	 */
	public function ajax_bulk_generate_from_queue() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$topic_ids = isset($_POST['topic_ids']) && is_array($_POST['topic_ids']) ? array_map('absint', $_POST['topic_ids']) : array();

		if (empty($topic_ids)) {
			AIPS_Ajax_Response::error(__('No topics selected.', 'ai-post-scheduler'));
		}

		$this->_do_bulk_generate_topics(
			$topic_ids,
			array(
				'history_type' => 'bulk_generation',
				'history_meta' => array( 'topic_count' => count( $topic_ids ) ),
				'trigger_name' => 'ajax_bulk_generate_from_queue',
				'user_action'  => 'bulk_generation',
				'user_message' => sprintf(
					/* translators: %d: number of topics */
					__( 'User initiated bulk generation for %d topics', 'ai-post-scheduler' ),
					count( $topic_ids )
				),
				'error_formatter' => function ( $topic_id, $msg ) {
					/* translators: 1: topic ID, 2: error message */
					return sprintf( __( 'Topic ID %1$d: %2$s', 'ai-post-scheduler' ), $topic_id, $msg );
				},
			)
		);
	}

	/**
	 * AJAX handler for bulk generating posts from topics.
	 *
	 * Delegates to AIPS_Bulk_Generator_Service via _do_bulk_generate_topics().
	 * The `aips_bulk_run_now_limit` filter and history logging are handled there.
	 */
	public function ajax_bulk_generate_topics() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$topic_ids = isset($_POST['topic_ids']) && is_array($_POST['topic_ids']) ? array_map('absint', $_POST['topic_ids']) : array();

		if (empty($topic_ids)) {
			AIPS_Ajax_Response::error(__('No topics selected.', 'ai-post-scheduler'));
		}

		$this->_do_bulk_generate_topics(
			$topic_ids,
			array(
				'history_type' => 'bulk_generate',
				'history_meta' => array( 'entity_type' => 'topics', 'entity_count' => count( $topic_ids ) ),
				'trigger_name' => 'ajax_bulk_generate_topics',
				'user_action'  => 'bulk_generate_topics',
				'user_message' => sprintf(
					/* translators: %d: number of topics */
					__( 'User initiated bulk generation for %d topics', 'ai-post-scheduler' ),
					count( $topic_ids )
				),
				'error_formatter' => function ( $topic_id, $msg ) {
					/* translators: 1: topic ID, 2: error message */
					return sprintf( __( 'Topic ID %1$d: %2$s', 'ai-post-scheduler' ), $topic_id, $msg );
				},
			)
		);
	}

	/**
	 * Shared bulk generation driver used by ajax_bulk_generate_topics() and
	 * ajax_bulk_generate_from_queue().
	 *
	 * Delegates the batch harness (limit enforcement, loop, history) to
	 * AIPS_Bulk_Generator_Service and emits the JSON response.
	 *
	 * @param int[]  $topic_ids Sanitized topic IDs to process.
	 * @param array  $options   Options forwarded to AIPS_Bulk_Generator_Service::run().
	 */
	private function _do_bulk_generate_topics( array $topic_ids, array $options ): void {
		$post_generator = $this->post_generator;

		$result = $this->bulk_generator_service->run(
			$topic_ids,
			function ( $topic_id ) use ( $post_generator ) {
				return $post_generator->generate_now( $topic_id );
			},
			$options
		);

		if ( $result->was_limited ) {
			AIPS_Ajax_Response::error(array(
				'message' => sprintf(
					/* translators: 1: selected count, 2: max allowed */
					__( 'Too many topics selected (%1$d). Please select no more than %2$d at a time for immediate generation.', 'ai-post-scheduler' ),
					$result->failed_count,
					$result->max_bulk
				),
			));
			return;
		}

		$message = sprintf(
			/* translators: %d: number of posts */
			__( '%d post(s) generated successfully.', 'ai-post-scheduler' ),
			$result->success_count
		);
		if ( $result->failed_count > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: number of failures */
				__( '%d failed.', 'ai-post-scheduler' ),
				$result->failed_count
			);
		}

		AIPS_Ajax_Response::success(array(
			'message'       => $message,
			'success_count' => $result->success_count,
			'failed_count'  => $result->failed_count,
			'errors'        => $result->errors,
		));
	}

	/**
	 * AJAX handler for bulk deleting feedback items.
	 */
	public function ajax_bulk_delete_feedback() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$feedback_ids = isset($_POST['feedback_ids']) && is_array($_POST['feedback_ids']) ? array_map('absint', $_POST['feedback_ids']) : array();

		if (empty($feedback_ids)) {
			AIPS_Ajax_Response::error(__('No feedback items selected.', 'ai-post-scheduler'));
		}

		// Create history container for bulk delete operation
		$history = $this->history_service->create('bulk_delete_feedback', array(
			'user_id' => get_current_user_id(),
			'source' => 'manual_ui',
			'trigger' => 'ajax_bulk_delete_feedback',
			'entity_type' => 'feedback',
			'entity_count' => count($feedback_ids)
		));

		$history->record_user_action(
			'bulk_delete_feedback',
			sprintf(__('User initiated bulk delete for %d feedback items', 'ai-post-scheduler'), count($feedback_ids)),
			array('feedback_ids' => $feedback_ids, 'feedback_count' => count($feedback_ids))
		);

		$success_count = 0;
		$failed_count  = 0;
		foreach ($feedback_ids as $feedback_id) {
			$result = $this->feedback_repository->delete($feedback_id);
			if ($result) {
				$success_count++;
			} else {
				$failed_count++;
				$history->record('warning', sprintf(__('Failed to delete feedback ID %d', 'ai-post-scheduler'), $feedback_id), null, null, array('feedback_id' => $feedback_id));
			}
		}

		$history->record('activity', sprintf(__('Deleted %d feedback items', 'ai-post-scheduler'), $success_count), null, null, array(
			'deleted_count' => $success_count,
			'requested_count' => count($feedback_ids)
		));

		$message = sprintf(__('%d feedback item(s) deleted successfully.', 'ai-post-scheduler'), $success_count);
		if ($failed_count > 0) {
			$message .= ' ' . sprintf(__('%d failed.', 'ai-post-scheduler'), $failed_count);
			$history->complete_success(array('deleted_count' => $success_count, 'failed_count' => $failed_count));
		} else {
			$history->complete_success(array('deleted_count' => $success_count));
		}

		AIPS_Ajax_Response::success(array(
			'message'       => $message,
			'success_count' => $success_count,
			'failed_count'  => $failed_count,
		));
	}

	/**
	 * AJAX handler that returns a per-post generation time estimate for the
	 * bulk-generate progress bar.
	 *
	 * Reads up to 20 of the most recently stored `_aips_post_generation_total_time`
	 * post meta values (written by AIPS_Author_Post_Generator::generate_now()) and
	 * returns their average as `per_post_seconds`. Falls back to a conservative
	 * default of 30 seconds when no historical data is available.
	 */
	public function ajax_get_bulk_generate_estimate() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		// Use the history repository to get the estimate based on historical performance
		$estimate           = $this->history_repository->get_estimated_generation_time(20);

		$per_post_seconds   = $estimate['per_post_seconds'];
		$sample_size        = $estimate['sample_size'];

		AIPS_Ajax_Response::success(array(
			'per_post_seconds' => $per_post_seconds,
			'sample_size'      => $sample_size,
		));
	}
}
