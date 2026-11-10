<?php
/**
 * Author Topics Job Handler
 *
 * Handles AJAX long-running generation & execution endpoints for Author Topics.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Author_Topics_Job_Handler
 */
class AIPS_Author_Topics_Job_Handler {

	/**
	 * @var AIPS_Author_Topics_Repository
	 */
	private $repository;

	/**
	 * @var AIPS_Author_Post_Generator
	 */
	private $post_generator;

	/**
	 * @var AIPS_History_Service_Interface
	 */
	private $history_service;

	/**
	 * @var AIPS_Bulk_Generator_Service
	 */
	private $bulk_generator_service;

	public function __construct(
		?AIPS_Author_Topics_Repository $repository = null,
		?AIPS_Author_Post_Generator $post_generator = null,
		$history_service = null,
		?AIPS_Bulk_Generator_Service $bulk_generator_service = null
	) {
		$container = AIPS_Container::get_instance();
		$this->repository             = $repository ?: new AIPS_Author_Topics_Repository();
		$this->post_generator         = $post_generator ?: new AIPS_Author_Post_Generator();
		$this->history_service        = $history_service ?: ($container->has(AIPS_History_Service_Interface::class) ? $container->make(AIPS_History_Service_Interface::class) : new AIPS_History_Service());
		$this->bulk_generator_service = $bulk_generator_service ?: new AIPS_Bulk_Generator_Service($this->history_service);
	}

	public function generate_post_from_topic() {
		AIPS_Ajax_Guard::check_admin();

		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;

		if (!$topic_id) {
			AIPS_Ajax_Response::error(__('Invalid topic ID.', 'ai-post-scheduler'));
		}

		$topic = $this->repository->get_by_id($topic_id);
		if (!$topic || $topic->status !== 'approved') {
			AIPS_Ajax_Response::error(__('Only approved topics can generate posts.', 'ai-post-scheduler'));
		}

		$history = $this->history_service->create('manual_generation', array(
			'topic_id' => $topic_id,
			'user_id' => get_current_user_id(),
			'source' => 'manual_ui',
			'trigger' => 'ajax_generate_post_from_topic'
		));

		AIPS_Generation_Trigger::record(
			$history,
			array(
				'event'    => __('Manual post generation from topic', 'ai-post-scheduler'),
				'topic_id' => (int) $topic_id,
				'topic'    => (string) $topic->topic_title,
			),
			'manual'
		);

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

	public function regenerate_post() {
		AIPS_Ajax_Guard::check_admin();

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		$topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;

		if (!$post_id || !$topic_id) {
			AIPS_Ajax_Response::error(__('Invalid post or topic ID.', 'ai-post-scheduler'));
		}

		$history = $this->history_service->create('manual_regeneration', array(
			'user_id' => get_current_user_id(),
			'source' => 'manual_ui',
			'trigger' => 'ajax_regenerate_post',
			'post_id' => $post_id,
			'topic_id' => $topic_id
		));

		AIPS_Generation_Trigger::record(
			$history,
			array(
				'event'    => __('Manual post regeneration', 'ai-post-scheduler'),
				'post_id'  => (int) $post_id,
				'topic_id' => (int) $topic_id,
			),
			'manual_regeneration'
		);

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

	public function compute_topic_embeddings() {
		AIPS_Ajax_Guard::check_admin();

		if (!AIPS_Config::get_instance()->get_option('aips_embeddings_enabled', true)) {
			AIPS_Ajax_Response::error(__('The vector embeddings system is disabled in settings.', 'ai-post-scheduler'));
		}

		$author_id = isset($_POST['author_id']) ? absint($_POST['author_id']) : 0;

		$result = AIPS_Container::get_instance()
			->make(AIPS_Background_Process_Manager::class)
			->control(AIPS_Author_Embeddings_Process::KEY, AIPS_Background_Process_Manager::ACTION_START, array('author_id' => $author_id));

		if (is_wp_error($result)) {
			$code = $result->get_error_code();

			if ('aips_bg_nothing_to_do' === $code) {
				AIPS_Ajax_Response::success(array(
					'message'      => __('Every topic already has an embedding.', 'ai-post-scheduler'),
					'queued_count' => 0,
				));
			}

			if ('aips_bg_already_open' === $code) {
				$run        = ( new AIPS_Background_Process_Repository() )->get_open(AIPS_Author_Embeddings_Process::KEY);
				$run_author = ($run && isset($run->options['author_id'])) ? (int) $run->options['author_id'] : 0;

				if ($run && AIPS_Background_Process_Repository::STATUS_PAUSED === $run->status) {
					AIPS_Ajax_Response::error(__('Topic embeddings are paused. Resume the run from Diagnostics > Background Processes to continue.', 'ai-post-scheduler'));
				}

				if ($run && 0 !== $run_author && $run_author !== $author_id) {
					AIPS_Ajax_Response::error(__('Embeddings for another author are already being processed. Wait for that run to finish, or stop it from Diagnostics > Background Processes, then try again.', 'ai-post-scheduler'));
				}

				AIPS_Ajax_Response::success(array(
					'message'      => __('Topic embeddings are already being processed. See Diagnostics > Background Processes.', 'ai-post-scheduler'),
					'queued_count' => 0,
				));
			}

			AIPS_Ajax_Response::error($result->get_error_message());
		}

		AIPS_Ajax_Response::success(array(
			'message'      => sprintf(
				_n(
					'Queued embeddings for %d topic. Processing runs in the background and can be paused from Diagnostics > Background Processes.',
					'Queued embeddings for %d topics. Processing runs in the background and can be paused from Diagnostics > Background Processes.',
					(int) $result['total'],
					'ai-post-scheduler'
				),
				(int) $result['total']
			),
			'queued_count' => (int) $result['total'],
		));
	}

	public function bulk_generate_from_queue() {
		AIPS_Ajax_Guard::check_admin();

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
					__( 'User initiated bulk generation for %d topics', 'ai-post-scheduler' ),
					count( $topic_ids )
				),
				'error_formatter' => function ( $topic_id, $msg ) {
					return sprintf( __( 'Topic ID %1$d: %2$s', 'ai-post-scheduler' ), $topic_id, $msg );
				},
			)
		);
	}

	public function bulk_generate_topics() {
		AIPS_Ajax_Guard::check_admin();

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
					__( 'User initiated bulk generation for %d topics', 'ai-post-scheduler' ),
					count( $topic_ids )
				),
				'error_formatter' => function ( $topic_id, $msg ) {
					return sprintf( __( 'Topic ID %1$d: %2$s', 'ai-post-scheduler' ), $topic_id, $msg );
				},
			)
		);
	}

	private function _do_bulk_generate_topics( array $topic_ids, array $options ): void {
		$post_generator = $this->post_generator;

		$options = array_merge(
			array( 'queue_job_type' => 'author_topic_post' ),
			$options
		);

		$result = $this->bulk_generator_service->run(
			$topic_ids,
			function ( $topic_id ) use ( $post_generator ) {
				return $post_generator->generate_now( $topic_id );
			},
			$options
		);

		if ( $result->was_queued ) {
			AIPS_Ajax_Response::success(array(
				'message' => sprintf(
					__( 'Bulk generation queued for %d topics. Posts will be created in the background.', 'ai-post-scheduler' ),
					count( $topic_ids )
				),
				'queued'        => true,
				'job_id'        => $result->job_id,
				'success_count' => 0,
				'failed_count'  => 0,
				'errors'        => array(),
			));
			return;
		}

		if ( $result->was_limited ) {
			AIPS_Ajax_Response::error(array(
				'message' => sprintf(
					__( 'Too many topics selected (%1$d). Please select no more than %2$d at a time for immediate generation.', 'ai-post-scheduler' ),
					$result->failed_count,
					$result->max_bulk
				),
			));
			return;
		}

		$message = sprintf(
			__( '%d post(s) generated successfully.', 'ai-post-scheduler' ),
			$result->success_count
		);
		if ( $result->failed_count > 0 ) {
			$message .= ' ' . sprintf(
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
}
