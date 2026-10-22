<?php
/**
 * Author Topics Mutation Handler
 *
 * Handles AJAX mutation / state change endpoints for Author Topics.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Author_Topics_Mutation_Handler
 */
class AIPS_Author_Topics_Mutation_Handler {

	/**
	 * @var AIPS_Author_Topics_Repository
	 */
	private $repository;

	/**
	 * @var AIPS_Author_Topic_Logs_Repository
	 */
	private $logs_repository;

	/**
	 * @var AIPS_Feedback_Repository
	 */
	private $feedback_repository;

	/**
	 * @var AIPS_Topic_Penalty_Service
	 */
	private $penalty_service;

	/**
	 * @var AIPS_History_Service_Interface
	 */
	private $history_service;

	/**
	 * @var AIPS_Embeddings_Repository
	 */
	private $embeddings_repo;

	/**
	 * @var AIPS_Relationships_Repository
	 */
	private $relationships_repo;

	public function __construct(
		?AIPS_Author_Topics_Repository $repository = null,
		?AIPS_Author_Topic_Logs_Repository $logs_repository = null,
		?AIPS_Feedback_Repository $feedback_repository = null,
		?AIPS_Topic_Penalty_Service $penalty_service = null,
		$history_service = null,
		?AIPS_Embeddings_Repository $embeddings_repo = null,
		?AIPS_Relationships_Repository $relationships_repo = null
	) {
		$container = AIPS_Container::get_instance();
		$this->repository         = $repository ?: new AIPS_Author_Topics_Repository();
		$this->logs_repository    = $logs_repository ?: new AIPS_Author_Topic_Logs_Repository();
		$this->feedback_repository= $feedback_repository ?: new AIPS_Feedback_Repository();
		$this->penalty_service    = $penalty_service ?: new AIPS_Topic_Penalty_Service();
		$this->history_service    = $history_service ?: ($container->has(AIPS_History_Service_Interface::class) ? $container->make(AIPS_History_Service_Interface::class) : new AIPS_History_Service());
		$this->embeddings_repo    = $embeddings_repo ?: ($container->has(AIPS_Embeddings_Repository::class) ? $container->make(AIPS_Embeddings_Repository::class) : new AIPS_Embeddings_Repository());
		$this->relationships_repo = $relationships_repo ?: ($container->has(AIPS_Relationships_Repository::class) ? $container->make(AIPS_Relationships_Repository::class) : new AIPS_Relationships_Repository());
	}

	public function approve_topic() {
		AIPS_Ajax_Guard::check_admin();

		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
		$reason = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '';
		$reason_category = isset($_POST['reason_category']) ? sanitize_text_field(wp_unslash($_POST['reason_category'])) : 'other';
		$source = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : 'UI';

		if (!$topic_id) {
			AIPS_Ajax_Response::error(__('Invalid topic ID.', 'ai-post-scheduler'));
		}

		$result = $this->repository->update_status($topic_id, 'approved', get_current_user_id());

		if ($result) {
			$topic = $this->repository->get_by_id($topic_id);
			$this->logs_repository->log_approval($topic_id, get_current_user_id());
			$this->feedback_repository->record_approval($topic_id, get_current_user_id(), $reason, '', $reason_category, $source);
			$this->penalty_service->apply_reward($topic_id, $reason_category);

			if ($topic) {
				$recorder = new AIPS_History_Event_Recorder($this->history_service);
				$recorder->record(
					AIPS_History_Event::success(
						AIPS_History_Event_Type::TOPIC_APPROVED,
						sprintf(
							__('Topic approved: "%s"', 'ai-post-scheduler'),
							$topic->topic_title
						),
						AIPS_History_Subject::of(AIPS_History_Subject::TYPE_TOPIC, $topic_id, $topic->topic_title),
						array(
							'topic_id' => $topic_id,
							'topic_title' => $topic->topic_title,
							'author_id' => $topic->author_id,
							'reason' => $reason,
							'reason_category' => $reason_category,
							'source' => $source,
							'approved_by' => get_current_user_id(),
						)
					)
				);
			}

			AIPS_Ajax_Response::success(array(), __('Topic approved successfully.', 'ai-post-scheduler'));
		} else {
			AIPS_Ajax_Response::error(__('Failed to approve topic.', 'ai-post-scheduler'));
		}
	}

	public function reject_topic() {
		AIPS_Ajax_Guard::check_admin();

		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
		$reason = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '';
		$reason_category = isset($_POST['reason_category']) ? sanitize_text_field(wp_unslash($_POST['reason_category'])) : 'other';
		$source = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : 'UI';

		if (!$topic_id) {
			AIPS_Ajax_Response::error(__('Invalid topic ID.', 'ai-post-scheduler'));
		}

		$result = $this->repository->update_status($topic_id, 'rejected', get_current_user_id());

		if ($result) {
			$this->embeddings_repo->delete('topic', $topic_id);
			$this->relationships_repo->delete_for_object('topic', $topic_id);

			$topic = $this->repository->get_by_id($topic_id);
			$this->logs_repository->log_rejection($topic_id, get_current_user_id());
			$this->feedback_repository->record_rejection($topic_id, get_current_user_id(), $reason, '', $reason_category, $source);
			$this->penalty_service->apply_penalty($topic_id, $reason_category);

			if ($topic) {
				$recorder = new AIPS_History_Event_Recorder($this->history_service);
				$recorder->record(
					AIPS_History_Event::failure(
						AIPS_History_Event_Type::TOPIC_REJECTED,
						sprintf(
							__('Topic rejected: "%s"', 'ai-post-scheduler'),
							$topic->topic_title
						),
						AIPS_History_Subject::of(AIPS_History_Subject::TYPE_TOPIC, $topic_id, $topic->topic_title),
						array(
							'topic_id' => $topic_id,
							'topic_title' => $topic->topic_title,
							'author_id' => $topic->author_id,
							'reason' => $reason,
							'reason_category' => $reason_category,
							'source' => $source,
							'rejected_by' => get_current_user_id(),
						)
					)
				);
			}

			AIPS_Ajax_Response::success(array(), __('Topic rejected successfully.', 'ai-post-scheduler'));
		} else {
			AIPS_Ajax_Response::error(__('Failed to reject topic.', 'ai-post-scheduler'));
		}
	}

	public function edit_topic() {
		AIPS_Ajax_Guard::check_admin();

		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
		$new_title = isset($_POST['topic_title']) ? sanitize_text_field(wp_unslash($_POST['topic_title'])) : '';

		if (!$topic_id || empty($new_title)) {
			AIPS_Ajax_Response::error(__('Invalid topic ID or title.', 'ai-post-scheduler'));
		}

		$old_topic = $this->repository->get_by_id($topic_id);
		$old_title = $old_topic ? $old_topic->topic_title : '';

		$result = $this->repository->update($topic_id, array('topic_title' => $new_title));

		if ($result) {
			$this->logs_repository->log_edit($topic_id, get_current_user_id(), $old_title, $new_title);
			$this->embeddings_repo->delete('topic', $topic_id);
			$this->relationships_repo->delete_for_object('topic', $topic_id);
			$this->feedback_repository->record_edit($topic_id, get_current_user_id(), $old_title, $new_title);

			AIPS_Ajax_Response::success(array(), __('Topic updated successfully.', 'ai-post-scheduler'));
		} else {
			AIPS_Ajax_Response::error(__('Failed to update topic.', 'ai-post-scheduler'));
		}
	}

	public function save_author_topic() {
		AIPS_Ajax_Guard::check_admin();

		$author_id   = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;
		$topic_title = isset($_POST['topic']) ? sanitize_text_field(wp_unslash($_POST['topic'])) : (isset($_POST['topic_title']) ? sanitize_text_field(wp_unslash($_POST['topic_title'])) : '');
		$status      = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : 'approved';

		if (!$author_id || empty($topic_title)) {
			AIPS_Ajax_Response::error(__('Author ID and topic title are required.', 'ai-post-scheduler'));
		}

		$allowed_statuses = array('pending', 'approved', 'rejected');
		if (!in_array($status, $allowed_statuses, true)) {
			$status = 'approved';
		}

		$topic_data = array(
			'author_id'    => $author_id,
			'topic_title'  => $topic_title,
			'topic_prompt' => isset($_POST['topic_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['topic_prompt'])) : '',
			'status'       => $status,
			'score'        => isset($_POST['score']) ? absint($_POST['score']) : 50,
			'metadata'     => isset($_POST['metadata']) ? sanitize_text_field(wp_unslash($_POST['metadata'])) : '',
		);

		$topic_id = $this->repository->create($topic_data);

		if ($topic_id) {
			$this->logs_repository->log(
				$topic_id,
				'created',
				sprintf(
					__('Topic "%s" created manually.', 'ai-post-scheduler'),
					$topic_title
				),
				get_current_user_id()
			);

			AIPS_Ajax_Response::success(
				array(
					'topic_id'    => $topic_id,
					'author_id'   => $author_id,
					'topic_title' => $topic_title,
					'status'      => $status,
				),
				__('Topic saved successfully.', 'ai-post-scheduler')
			);
		} else {
			AIPS_Ajax_Response::error(__('Failed to save topic.', 'ai-post-scheduler'));
		}
	}

	public function delete_topic() {
		AIPS_Ajax_Guard::check_admin();

		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;

		if (!$topic_id) {
			AIPS_Ajax_Response::error(__('Invalid topic ID.', 'ai-post-scheduler'));
		}

		$result = $this->repository->delete($topic_id);

		if ($result) {
			$this->embeddings_repo->delete('topic', $topic_id);
			$this->relationships_repo->delete_for_object('topic', $topic_id);
			AIPS_Ajax_Response::success(array(), __('Topic deleted successfully.', 'ai-post-scheduler'));
		} else {
			AIPS_Ajax_Response::error(__('Failed to delete topic.', 'ai-post-scheduler'));
		}
	}

	public function bulk_approve_topics() {
		AIPS_Ajax_Guard::check_admin();

		$topic_ids = isset($_POST['topic_ids']) && is_array($_POST['topic_ids']) ? array_map('absint', $_POST['topic_ids']) : array();

		if (empty($topic_ids)) {
			AIPS_Ajax_Response::error(__('No topics selected.', 'ai-post-scheduler'));
		}

		$reason          = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '';
		$reason_category = isset($_POST['reason_category']) ? sanitize_text_field(wp_unslash($_POST['reason_category'])) : 'other';
		$source          = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : 'manual_ui';

		$topic_ids     = array_values(array_unique(array_filter($topic_ids)));
		$user_id       = get_current_user_id();
		$update_result = $this->repository->update_status_bulk($topic_ids, 'approved', $user_id);

		$success_count = 0;
		$failed_count  = 0;

		if (false === $update_result) {
			$failed_count = count($topic_ids);
		} else {
			$existing_ids = $this->repository->get_existing_ids($topic_ids);
			foreach ($topic_ids as $topic_id) {
				if (!in_array($topic_id, $existing_ids, true)) {
					$failed_count++;
					continue;
				}

				$this->logs_repository->log_approval($topic_id, $user_id);

				if (!empty($reason) || !empty($reason_category)) {
					$this->feedback_repository->record_approval($topic_id, $user_id, $reason, '', $reason_category, $source);
					$this->penalty_service->apply_reward($topic_id, $reason_category);
				}

				$success_count++;
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

	public function bulk_reject_topics() {
		AIPS_Ajax_Guard::check_admin();

		$topic_ids = isset($_POST['topic_ids']) && is_array($_POST['topic_ids']) ? array_map('absint', $_POST['topic_ids']) : array();

		if (empty($topic_ids)) {
			AIPS_Ajax_Response::error(__('No topics selected.', 'ai-post-scheduler'));
		}

		$reason          = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '';
		$reason_category = isset($_POST['reason_category']) ? sanitize_text_field(wp_unslash($_POST['reason_category'])) : 'other';
		$source          = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : 'manual_ui';

		$topic_ids     = array_values(array_unique(array_filter($topic_ids)));
		$user_id       = get_current_user_id();
		$update_result = $this->repository->update_status_bulk($topic_ids, 'rejected', $user_id);

		$success_count = 0;
		$failed_count  = 0;

		if (false === $update_result) {
			$failed_count = count($topic_ids);
		} else {
			$existing_ids = $this->repository->get_existing_ids($topic_ids);
			foreach ($topic_ids as $topic_id) {
				if (!in_array($topic_id, $existing_ids, true)) {
					$failed_count++;
					continue;
				}

				$this->embeddings_repo->delete('topic', $topic_id);
				$this->relationships_repo->delete_for_object('topic', $topic_id);
				$this->logs_repository->log_rejection($topic_id, $user_id);

				if (!empty($reason) || !empty($reason_category)) {
					$this->feedback_repository->record_rejection($topic_id, $user_id, $reason, '', $reason_category, $source);
					$this->penalty_service->apply_penalty($topic_id, $reason_category);
				}

				$success_count++;
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

	public function bulk_delete_topics() {
		AIPS_Ajax_Guard::check_admin();

		$topic_ids = isset($_POST['topic_ids']) && is_array($_POST['topic_ids']) ? array_map('absint', $_POST['topic_ids']) : array();

		if (empty($topic_ids)) {
			AIPS_Ajax_Response::error(__('No topics selected.', 'ai-post-scheduler'));
		}

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
				$this->embeddings_repo->delete('topic', $topic_id);
				$this->relationships_repo->delete_for_object('topic', $topic_id);
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

	public function bulk_delete_feedback() {
		AIPS_Ajax_Guard::check_admin();

		$feedback_ids = isset($_POST['feedback_ids']) && is_array($_POST['feedback_ids']) ? array_map('absint', $_POST['feedback_ids']) : array();

		if (empty($feedback_ids)) {
			AIPS_Ajax_Response::error(__('No feedback items selected.', 'ai-post-scheduler'));
		}

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

	public function delete_generated_post() {
		AIPS_Ajax_Guard::check_admin();

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
}
