<?php
/**
 * Author Topics Query Handler
 *
 * Handles AJAX query / read endpoints for Author Topics.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Author_Topics_Query_Handler
 */
class AIPS_Author_Topics_Query_Handler {

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
	 * @var AIPS_Similarity_Evaluator
	 */
	private $similarity_evaluator;

	/**
	 * @var AIPS_History_Repository_Interface
	 */
	private $history_repository;

	public function __construct(
		?AIPS_Author_Topics_Repository $repository = null,
		?AIPS_Author_Topic_Logs_Repository $logs_repository = null,
		?AIPS_Feedback_Repository $feedback_repository = null,
		$similarity_evaluator = null,
		?AIPS_History_Repository_Interface $history_repository = null
	) {
		$container = AIPS_Container::get_instance();
		$this->repository           = $repository ?: new AIPS_Author_Topics_Repository();
		$this->logs_repository      = $logs_repository ?: new AIPS_Author_Topic_Logs_Repository();
		$this->feedback_repository  = $feedback_repository ?: new AIPS_Feedback_Repository();
		$this->similarity_evaluator = $similarity_evaluator ?: ($container->has(AIPS_Similarity_Evaluator::class) ? $container->make(AIPS_Similarity_Evaluator::class) : new AIPS_Similarity_Evaluator());
		$this->history_repository   = $history_repository ?: ($container->has(AIPS_History_Repository_Interface::class) ? $container->make(AIPS_History_Repository_Interface::class) : new AIPS_History_Repository());
	}

	public function get_topic_logs() {
		AIPS_Ajax_Guard::check_admin();

		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;

		if (!$topic_id) {
			AIPS_Ajax_Response::error(__('Invalid topic ID.', 'ai-post-scheduler'));
		}

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

	public function get_topic_feedback() {
		AIPS_Ajax_Guard::check_admin();

		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;

		if (!$topic_id) {
			AIPS_Ajax_Response::error(__('Invalid topic ID.', 'ai-post-scheduler'));
		}

		$feedback = $this->feedback_repository->get_by_topic($topic_id);

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

	public function get_similar_topics() {
		AIPS_Ajax_Guard::check_admin();

		$topic_id  = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
		$author_id = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;
		$limit     = isset($_POST['limit']) ? absint($_POST['limit']) : 5;

		if (!$topic_id || !$author_id) {
			AIPS_Ajax_Response::error(__('Invalid topic or author ID.', 'ai-post-scheduler'));
		}

		$similar_topics = $this->similarity_evaluator->find_similar_topics($topic_id, $author_id, $limit);

		foreach ($similar_topics as &$item) {
			if (isset($item['id'])) {
				$topic = $this->repository->get_by_id($item['id']);
				if ($topic) {
					$item['topic_title'] = $topic->topic_title;
					$item['status']      = $topic->status;
				}
			}
		}

		AIPS_Ajax_Response::success(array('similar_topics' => $similar_topics));
	}

	public function suggest_related_topics() {
		AIPS_Ajax_Guard::check_admin();

		$author_id = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;
		$limit     = isset($_POST['limit']) ? absint($_POST['limit']) : 10;

		if (!$author_id) {
			AIPS_Ajax_Response::error(__('Invalid author ID.', 'ai-post-scheduler'));
		}

		$suggestions = $this->similarity_evaluator->suggest_related_topics($author_id, $limit);

		AIPS_Ajax_Response::success(array('suggestions' => $suggestions));
	}

	public function get_generation_queue() {
		AIPS_Ajax_Guard::check_admin();

		$topics = $this->repository->get_all_approved_for_queue();

		AIPS_Ajax_Response::success(array('topics' => $topics));
	}

	public function get_bulk_generate_estimate() {
		AIPS_Ajax_Guard::check_admin();

		$estimate         = $this->history_repository->get_estimated_generation_time(20);
		$per_post_seconds = $estimate['per_post_seconds'];
		$sample_size      = $estimate['sample_size'];

		AIPS_Ajax_Response::success(array(
			'per_post_seconds' => $per_post_seconds,
			'sample_size'      => $sample_size,
		));
	}
}
