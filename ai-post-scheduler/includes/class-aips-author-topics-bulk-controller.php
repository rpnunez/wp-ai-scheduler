<?php
/**
 * Author Topics Bulk Controller
 *
 * Handles AJAX requests for bulk author topic management operations
 * (bulk approve, bulk reject, bulk delete, bulk generate, etc.).
 *
 * @package AI_Post_Scheduler
 * @since 2.0.2
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Author_Topics_Bulk_Controller
 *
 * Manages AJAX endpoints for bulk topic workflow.
 */
class AIPS_Author_Topics_Bulk_Controller {

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
	 * @var AIPS_History_Service Service for history logging
	 */
	private $history_service;

	/**
	 * @var AIPS_Bulk_Generator_Service Shared bulk generation harness
	 */
	private $bulk_generator_service;

	/**
	 * Initialize the controller.
	 *
	 * @param AIPS_Author_Topics_Repository|null      $repository             Topics repository.
	 * @param AIPS_Author_Topic_Logs_Repository|null  $logs_repository        Topic logs repository.
	 * @param AIPS_Feedback_Repository|null           $feedback_repository    Feedback repository.
	 * @param AIPS_Author_Post_Generator|null         $post_generator         Post generator.
	 * @param AIPS_History_Service|null               $history_service        History service.
	 * @param AIPS_Bulk_Generator_Service|null        $bulk_generator_service Bulk generator service.
	 */
	public function __construct(
		$repository = null,
		$logs_repository = null,
		$feedback_repository = null,
		$post_generator = null,
		$history_service = null,
		$bulk_generator_service = null
	) {
		$this->repository             = $repository             ?: new AIPS_Author_Topics_Repository();
		$this->logs_repository        = $logs_repository        ?: new AIPS_Author_Topic_Logs_Repository();
		$this->feedback_repository    = $feedback_repository    ?: new AIPS_Feedback_Repository();
		$this->post_generator         = $post_generator         ?: new AIPS_Author_Post_Generator();
		$this->history_service        = $history_service        ?: new AIPS_History_Service();
		$this->bulk_generator_service = $bulk_generator_service ?: new AIPS_Bulk_Generator_Service( $this->history_service );

		// Register AJAX endpoints
		add_action('wp_ajax_aips_bulk_approve_topics', array($this, 'ajax_bulk_approve_topics'));
		add_action('wp_ajax_aips_bulk_reject_topics', array($this, 'ajax_bulk_reject_topics'));
		add_action('wp_ajax_aips_bulk_delete_topics', array($this, 'ajax_bulk_delete_topics'));
		add_action('wp_ajax_aips_bulk_generate_topics', array($this, 'ajax_bulk_generate_topics'));
		add_action('wp_ajax_aips_bulk_delete_feedback', array($this, 'ajax_bulk_delete_feedback'));
		add_action('wp_ajax_aips_bulk_generate_from_queue', array($this, 'ajax_bulk_generate_from_queue'));
	}

	/**
	 * AJAX handler for bulk approving topics.
	 */
	public function ajax_bulk_approve_topics() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$topic_ids = isset($_POST['topic_ids']) && is_array($_POST['topic_ids']) ? array_map('absint', $_POST['topic_ids']) : array();

		if (empty($topic_ids)) {
			wp_send_json_error(array('message' => __('No topics selected.', 'ai-post-scheduler')));
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

		wp_send_json_success(array(
			'message'       => $message,
			'success_count' => $success_count,
			'failed_count'  => $failed_count,
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

		$topic_ids = isset($_POST['topic_ids']) && is_array($_POST['topic_ids']) ? array_map('absint', $_POST['topic_ids']) : array();

		if (empty($topic_ids)) {
			wp_send_json_error(array('message' => __('No topics selected.', 'ai-post-scheduler')));
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

		wp_send_json_success(array(
			'message'       => $message,
			'success_count' => $success_count,
			'failed_count'  => $failed_count,
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

		$topic_ids = isset($_POST['topic_ids']) && is_array($_POST['topic_ids']) ? array_map('absint', $_POST['topic_ids']) : array();

		if (empty($topic_ids)) {
			wp_send_json_error(array('message' => __('No topics selected.', 'ai-post-scheduler')));
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

		wp_send_json_success(array(
			'message'       => $message,
			'success_count' => $success_count,
			'failed_count'  => $failed_count,
		));
	}

	/**
	 * AJAX handler for bulk generating posts from queue topics.
	 *
	 * Delegates to AIPS_Bulk_Generator_Service via _do_bulk_generate_topics().
	 * The `aips_bulk_run_now_limit` filter and history logging are handled there.
	 */
	public function ajax_bulk_generate_from_queue() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$topic_ids = isset($_POST['topic_ids']) && is_array($_POST['topic_ids']) ? array_map('absint', $_POST['topic_ids']) : array();

		if (empty($topic_ids)) {
			wp_send_json_error(array('message' => __('No topics selected.', 'ai-post-scheduler')));
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
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$topic_ids = isset($_POST['topic_ids']) && is_array($_POST['topic_ids']) ? array_map('absint', $_POST['topic_ids']) : array();

		if (empty($topic_ids)) {
			wp_send_json_error(array('message' => __('No topics selected.', 'ai-post-scheduler')));
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
			wp_send_json_error(array(
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

		wp_send_json_success(array(
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
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$feedback_ids = isset($_POST['feedback_ids']) && is_array($_POST['feedback_ids']) ? array_map('absint', $_POST['feedback_ids']) : array();

		if (empty($feedback_ids)) {
			wp_send_json_error(array('message' => __('No feedback items selected.', 'ai-post-scheduler')));
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

		wp_send_json_success(array(
			'message'       => $message,
			'success_count' => $success_count,
			'failed_count'  => $failed_count,
		));
	}
}
