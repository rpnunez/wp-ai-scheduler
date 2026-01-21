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
	 * Initialize the controller.
	 */
	public function __construct() {
		$this->repository = new AIPS_Author_Topics_Repository();
		$this->logs_repository = new AIPS_Author_Topic_Logs_Repository();
		$this->feedback_repository = new AIPS_Feedback_Repository();
		$this->post_generator = new AIPS_Author_Post_Generator();
		
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
		add_action('wp_ajax_aips_regenerate_post', array($this, 'ajax_regenerate_post'));
		add_action('wp_ajax_aips_delete_generated_post', array($this, 'ajax_delete_generated_post'));
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
		
		if (!$topic_id) {
			wp_send_json_error(array('message' => __('Invalid topic ID.', 'ai-post-scheduler')));
		}
		
		$result = $this->repository->update_status($topic_id, 'approved', get_current_user_id());
		
		if ($result) {
			// Log the approval
			$this->logs_repository->log_approval($topic_id, get_current_user_id());
			
			// Record feedback with reason
			$this->feedback_repository->record_approval($topic_id, get_current_user_id(), $reason);
			
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
		
		if (!$topic_id) {
			wp_send_json_error(array('message' => __('Invalid topic ID.', 'ai-post-scheduler')));
		}
		
		$result = $this->repository->update_status($topic_id, 'rejected', get_current_user_id());
		
		if ($result) {
			// Log the rejection
			$this->logs_repository->log_rejection($topic_id, get_current_user_id());
			
			// Record feedback with reason
			$this->feedback_repository->record_rejection($topic_id, get_current_user_id(), $reason);
			
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
}
