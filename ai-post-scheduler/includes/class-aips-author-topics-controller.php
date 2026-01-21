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
	 * @var AIPS_Activity_Repository Repository for activity logging
	 */
	private $activity_repository;
	
	/**
	 * Initialize the controller.
	 */
	public function __construct() {
		$this->repository = new AIPS_Author_Topics_Repository();
		$this->logs_repository = new AIPS_Author_Topic_Logs_Repository();
		$this->feedback_repository = new AIPS_Feedback_Repository();
		$this->post_generator = new AIPS_Author_Post_Generator();
		$this->penalty_service = new AIPS_Topic_Penalty_Service();
		$this->activity_repository = new AIPS_Activity_Repository();
		
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
		add_action('wp_ajax_aips_regenerate_post', array($this, 'ajax_regenerate_post'));
		add_action('wp_ajax_aips_delete_generated_post', array($this, 'ajax_delete_generated_post'));
		add_action('wp_ajax_aips_get_similar_topics', array($this, 'ajax_get_similar_topics'));
		add_action('wp_ajax_aips_suggest_related_topics', array($this, 'ajax_suggest_related_topics'));
		add_action('wp_ajax_aips_compute_topic_embeddings', array($this, 'ajax_compute_topic_embeddings'));
		add_action('wp_ajax_aips_get_generation_queue', array($this, 'ajax_get_generation_queue'));
		add_action('wp_ajax_aips_bulk_generate_from_queue', array($this, 'ajax_bulk_generate_from_queue'));
	}
	
	/**
	 * AJAX handler for approving a topic.
	 */
	public function ajax_approve_topic() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
		$reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';
		$reason_category = isset($_POST['reason_category']) ? sanitize_text_field($_POST['reason_category']) : 'other';
		$source = isset($_POST['source']) ? sanitize_text_field($_POST['source']) : 'UI';
		
		if (!$topic_id) {
			wp_send_json_error(array('message' => __('Invalid topic ID.', 'ai-post-scheduler')));
		}
		
		$result = $this->repository->update_status($topic_id, 'approved', get_current_user_id());
		
		if ($result) {
			// Get topic details for logging
			$topic = $this->repository->get_by_id($topic_id);
			
			// Log the approval
			$this->logs_repository->log_approval($topic_id, get_current_user_id());
			
			// Record feedback with reason
			$this->feedback_repository->record_approval($topic_id, get_current_user_id(), $reason, '', $reason_category, $source);
			
			// Apply reward for approval
			$this->penalty_service->apply_reward($topic_id, $reason_category);
			
			// Log to activity feed
			if ($topic) {
				$this->activity_repository->create(array(
					'event_type' => 'topic_approved',
					'event_status' => 'success',
					'message' => sprintf(
						__('Topic approved: "%s"', 'ai-post-scheduler'),
						$topic->topic_title
					),
					'metadata' => array(
						'topic_id' => $topic_id,
						'topic_title' => $topic->topic_title,
						'author_id' => $topic->author_id,
						'reason' => $reason,
						'reason_category' => $reason_category,
						'source' => $source,
						'approved_by' => get_current_user_id(),
					),
				));
			}
			
			wp_send_json_success(array('message' => __('Topic approved successfully.', 'ai-post-scheduler')));
		} else {
			wp_send_json_error(array('message' => __('Failed to approve topic.', 'ai-post-scheduler')));
		}
	}
	
	/**
	 * AJAX handler for rejecting a topic.
	 */
	public function ajax_reject_topic() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
		$reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';
		$reason_category = isset($_POST['reason_category']) ? sanitize_text_field($_POST['reason_category']) : 'other';
		$source = isset($_POST['source']) ? sanitize_text_field($_POST['source']) : 'UI';
		
		if (!$topic_id) {
			wp_send_json_error(array('message' => __('Invalid topic ID.', 'ai-post-scheduler')));
		}
		
		$result = $this->repository->update_status($topic_id, 'rejected', get_current_user_id());
		
		if ($result) {
			// Get topic details for logging
			$topic = $this->repository->get_by_id($topic_id);
			
			// Log the rejection
			$this->logs_repository->log_rejection($topic_id, get_current_user_id());
			
			// Record feedback with reason
			$this->feedback_repository->record_rejection($topic_id, get_current_user_id(), $reason, '', $reason_category, $source);
			
			// Apply penalty based on reason category
			$this->penalty_service->apply_penalty($topic_id, $reason_category);
			
			// Log to activity feed
			if ($topic) {
				$this->activity_repository->create(array(
					'event_type' => 'topic_rejected',
					'event_status' => 'failed',
					'message' => sprintf(
						__('Topic rejected: "%s"', 'ai-post-scheduler'),
						$topic->topic_title
					),
					'metadata' => array(
						'topic_id' => $topic_id,
						'topic_title' => $topic->topic_title,
						'author_id' => $topic->author_id,
						'reason' => $reason,
						'reason_category' => $reason_category,
						'source' => $source,
						'rejected_by' => get_current_user_id(),
					),
				));
			}
			
			wp_send_json_success(array('message' => __('Topic rejected successfully.', 'ai-post-scheduler')));
		} else {
			wp_send_json_error(array('message' => __('Failed to reject topic.', 'ai-post-scheduler')));
		}
	}
	
	/**
	 * AJAX handler for editing a topic.
	 */
	public function ajax_edit_topic() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
		$new_title = isset($_POST['topic_title']) ? sanitize_text_field($_POST['topic_title']) : '';
		
		if (!$topic_id || empty($new_title)) {
			wp_send_json_error(array('message' => __('Invalid topic ID or title.', 'ai-post-scheduler')));
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
			
			wp_send_json_success(array('message' => __('Topic updated successfully.', 'ai-post-scheduler')));
		} else {
			wp_send_json_error(array('message' => __('Failed to update topic.', 'ai-post-scheduler')));
		}
	}
	
	/**
	 * AJAX handler for deleting a topic.
	 */
	public function ajax_delete_topic() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
		
		if (!$topic_id) {
			wp_send_json_error(array('message' => __('Invalid topic ID.', 'ai-post-scheduler')));
		}
		
		$result = $this->repository->delete($topic_id);
		
		if ($result) {
			wp_send_json_success(array('message' => __('Topic deleted successfully.', 'ai-post-scheduler')));
		} else {
			wp_send_json_error(array('message' => __('Failed to delete topic.', 'ai-post-scheduler')));
		}
	}
	
	/**
	 * AJAX handler for generating a post from a topic.
	 */
	public function ajax_generate_post_from_topic() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
		
		if (!$topic_id) {
			wp_send_json_error(array('message' => __('Invalid topic ID.', 'ai-post-scheduler')));
		}
		
		// Check if topic is approved
		$topic = $this->repository->get_by_id($topic_id);
		if (!$topic || $topic->status !== 'approved') {
			wp_send_json_error(array('message' => __('Only approved topics can generate posts.', 'ai-post-scheduler')));
		}
		
		$result = $this->post_generator->generate_now($topic_id);
		
		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
		}
		
		wp_send_json_success(array(
			'message' => __('Post generated successfully.', 'ai-post-scheduler'),
			'post_id' => $result
		));
	}
	
	/**
	 * AJAX handler for getting topic logs.
	 */
	public function ajax_get_topic_logs() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
		
		if (!$topic_id) {
			wp_send_json_error(array('message' => __('Invalid topic ID.', 'ai-post-scheduler')));
		}
		
		$logs = $this->logs_repository->get_by_topic($topic_id);
		
		// Enrich with user names
		foreach ($logs as &$log) {
			if ($log->user_id) {
				$user = get_user_by('id', $log->user_id);
				$log->user_name = $user ? $user->display_name : 'Unknown';
			}
		}
		
		wp_send_json_success(array('logs' => $logs));
	}
	
	/**
	 * AJAX handler for bulk approving topics.
	 */
	public function ajax_bulk_approve_topics() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$topic_ids = isset($_POST['topic_ids']) ? array_map('absint', $_POST['topic_ids']) : array();
		
		if (empty($topic_ids)) {
			wp_send_json_error(array('message' => __('No topics selected.', 'ai-post-scheduler')));
		}
		
		$success_count = 0;
		foreach ($topic_ids as $topic_id) {
			$result = $this->repository->update_status($topic_id, 'approved', get_current_user_id());
			if ($result) {
				$this->logs_repository->log_approval($topic_id, get_current_user_id());
				$success_count++;
			}
		}
		
		wp_send_json_success(array(
			'message' => sprintf(__('%d topics approved successfully.', 'ai-post-scheduler'), $success_count)
		));
	}
	
	/**
	 * AJAX handler for bulk rejecting topics.
	 */
	public function ajax_bulk_reject_topics() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$topic_ids = isset($_POST['topic_ids']) ? array_map('absint', $_POST['topic_ids']) : array();
		
		if (empty($topic_ids)) {
			wp_send_json_error(array('message' => __('No topics selected.', 'ai-post-scheduler')));
		}
		
		$success_count = 0;
		foreach ($topic_ids as $topic_id) {
			$result = $this->repository->update_status($topic_id, 'rejected', get_current_user_id());
			if ($result) {
				$this->logs_repository->log_rejection($topic_id, get_current_user_id());
				$success_count++;
			}
		}
		
		wp_send_json_success(array(
			'message' => sprintf(__('%d topics rejected successfully.', 'ai-post-scheduler'), $success_count)
		));
	}
	
	/**
	 * AJAX handler for bulk deleting topics.
	 */
	public function ajax_bulk_delete_topics() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$topic_ids = isset($_POST['topic_ids']) ? array_map('absint', $_POST['topic_ids']) : array();
		
		if (empty($topic_ids)) {
			wp_send_json_error(array('message' => __('No topics selected.', 'ai-post-scheduler')));
		}
		
		$success_count = 0;
		foreach ($topic_ids as $topic_id) {
			$result = $this->repository->delete($topic_id);
			if ($result) {
				$success_count++;
			}
		}
		
		wp_send_json_success(array(
			'message' => sprintf(__('%d topics deleted successfully.', 'ai-post-scheduler'), $success_count)
		));
	}
	
	/**
	 * AJAX handler for regenerating a post.
	 */
	public function ajax_regenerate_post() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
		
		if (!$post_id || !$topic_id) {
			wp_send_json_error(array('message' => __('Invalid post or topic ID.', 'ai-post-scheduler')));
		}
		
		$result = $this->post_generator->regenerate_post($post_id, $topic_id);
		
		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
		}
		
		wp_send_json_success(array(
			'message' => __('Post regenerated successfully.', 'ai-post-scheduler'),
			'post_id' => $result
		));
	}
	
	/**
	 * AJAX handler for deleting a generated post.
	 */
	public function ajax_delete_generated_post() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		
		if (!$post_id) {
			wp_send_json_error(array('message' => __('Invalid post ID.', 'ai-post-scheduler')));
		}
		
		$result = wp_delete_post($post_id, true);
		
		if ($result) {
			wp_send_json_success(array('message' => __('Post deleted successfully.', 'ai-post-scheduler')));
		} else {
			wp_send_json_error(array('message' => __('Failed to delete post.', 'ai-post-scheduler')));
		}
	}
	
	/**
	 * AJAX handler for getting topic feedback.
	 */
	public function ajax_get_topic_feedback() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
		
		if (!$topic_id) {
			wp_send_json_error(array('message' => __('Invalid topic ID.', 'ai-post-scheduler')));
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
		
		wp_send_json_success(array('feedback' => $feedback));
	}
	
	/**
	 * AJAX handler for getting similar topics.
	 */
	public function ajax_get_similar_topics() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
		$author_id = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;
		$limit = isset($_POST['limit']) ? absint($_POST['limit']) : 5;
		
		if (!$topic_id || !$author_id) {
			wp_send_json_error(array('message' => __('Invalid topic or author ID.', 'ai-post-scheduler')));
		}
		
		$expansion_service = new AIPS_Topic_Expansion_Service();
		$similar_topics = $expansion_service->find_similar_topics($topic_id, $author_id, $limit);
		
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
		
		wp_send_json_success(array('similar_topics' => $similar_topics));
	}
	
	/**
	 * AJAX handler for suggesting related topics.
	 */
	public function ajax_suggest_related_topics() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$author_id = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;
		$limit = isset($_POST['limit']) ? absint($_POST['limit']) : 10;
		
		if (!$author_id) {
			wp_send_json_error(array('message' => __('Invalid author ID.', 'ai-post-scheduler')));
		}
		
		$expansion_service = new AIPS_Topic_Expansion_Service();
		$suggestions = $expansion_service->suggest_related_topics($author_id, $limit);
		
		wp_send_json_success(array('suggestions' => $suggestions));
	}
	
	/**
	 * AJAX handler for computing topic embeddings.
	 */
	public function ajax_compute_topic_embeddings() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$author_id = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;
		
		if (!$author_id) {
			wp_send_json_error(array('message' => __('Invalid author ID.', 'ai-post-scheduler')));
		}
		
		$expansion_service = new AIPS_Topic_Expansion_Service();
		$stats = $expansion_service->batch_compute_approved_embeddings($author_id);
		
		wp_send_json_success(array(
			'message' => sprintf(
				__('Computed embeddings: %d successful, %d failed, %d skipped (already existed).', 'ai-post-scheduler'),
				$stats['success'],
				$stats['failed'],
				$stats['skipped']
			),
			'stats' => $stats
		));
	}
	
	/**
	 * AJAX handler for getting all approved topics for the generation queue.
	 */
	public function ajax_get_generation_queue() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$topics = $this->repository->get_all_approved_for_queue();
		
		wp_send_json_success(array('topics' => $topics));
	}
	
	/**
	 * AJAX handler for bulk generating posts from queue topics.
	 */
	public function ajax_bulk_generate_from_queue() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$topic_ids = isset($_POST['topic_ids']) ? array_map('absint', $_POST['topic_ids']) : array();
		
		if (empty($topic_ids)) {
			wp_send_json_error(array('message' => __('No topics selected.', 'ai-post-scheduler')));
		}
		
		$success_count = 0;
		$failed_count = 0;
		$errors = array();
		
		foreach ($topic_ids as $topic_id) {
			$result = $this->post_generator->generate_now($topic_id);
			
			if (is_wp_error($result)) {
				$failed_count++;
				$errors[] = sprintf(__('Topic ID %d: %s', 'ai-post-scheduler'), $topic_id, $result->get_error_message());
			} else {
				$success_count++;
			}
		}
		
		$message = sprintf(__('%d post(s) generated successfully.', 'ai-post-scheduler'), $success_count);
		if ($failed_count > 0) {
			$message .= ' ' . sprintf(__('%d failed.', 'ai-post-scheduler'), $failed_count);
		}
		
		wp_send_json_success(array(
			'message' => $message,
			'success_count' => $success_count,
			'failed_count' => $failed_count,
			'errors' => $errors
		));
	}
}
