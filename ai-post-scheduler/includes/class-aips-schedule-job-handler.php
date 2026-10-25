<?php
/**
 * Schedule Job Handler
 *
 * Handles AJAX long-running execution / job endpoints for schedules.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Schedule_Job_Handler
 */
class AIPS_Schedule_Job_Handler {

	/**
	 * @var AIPS_Scheduler
	 */
	private $scheduler;

	/**
	 * @var AIPS_Schedule_Repository_Interface
	 */
	private $schedule_repository;

	/**
	 * @var AIPS_History_Repository_Interface
	 */
	private $history_repository;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Scheduler|null                   $scheduler
	 * @param AIPS_Schedule_Repository_Interface|null $schedule_repository
	 * @param AIPS_History_Repository_Interface|null  $history_repository
	 */
	public function __construct($scheduler = null, ?AIPS_Schedule_Repository_Interface $schedule_repository = null, ?AIPS_History_Repository_Interface $history_repository = null) {
		$container = AIPS_Container::get_instance();
		$this->scheduler = $scheduler ?: $container->makeIfExists(AIPS_Scheduler::class, function() {
			return new AIPS_Scheduler();
		});
		$this->schedule_repository = $schedule_repository ?: $container->makeIfExists(AIPS_Schedule_Repository_Interface::class, function() {
			return new AIPS_Schedule_Repository();
		});
		$this->history_repository = $history_repository ?: $container->makeIfExists(AIPS_History_Repository_Interface::class, function() {
			return new AIPS_History_Repository();
		});
	}

	public function run_now() {
		AIPS_Ajax_Guard::check_admin();

		$schedule_id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;
		if ($schedule_id) {
			$result = $this->scheduler->run_schedule_now($schedule_id);
			if (is_wp_error($result)) {
				AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
			} else {
				$post_ids = is_array($result) ? $result : array($result);
				$first_post_id = !empty($post_ids) ? $post_ids[0] : 0;
				$edit_url = $first_post_id ? esc_url_raw(get_edit_post_link($first_post_id, 'raw')) : '';

				$msg = sprintf(
					_n('%d post generated successfully!', '%d posts generated successfully!', count($post_ids), 'ai-post-scheduler'),
					count($post_ids)
				);

				AIPS_Ajax_Response::success(array(
					'message' => $msg,
					'post_ids' => $post_ids,
					'edit_url' => $edit_url
				));
			}
			return;
		}

		$template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;

		if (!$template_id) {
			AIPS_Ajax_Response::error(__('Invalid template ID.', 'ai-post-scheduler'));
		}

		$templates = new AIPS_Templates();
		$template = $templates->get($template_id);

		if (!$template) {
			AIPS_Ajax_Response::error(__('Template not found.', 'ai-post-scheduler'));
		}

		$voice = null;
		if (!empty($template->voice_id)) {
			$voices = new AIPS_Voices();
			$voice = $voices->get($template->voice_id);
		}

		$quantity = max(1, absint($template->post_quantity ?: 1));

		$post_ids = array();
		$errors = array();

		$generator = new AIPS_Generator();
		$topic = isset($_POST['topic']) ? sanitize_text_field(wp_unslash($_POST['topic'])) : '';

		for ($i = 0; $i < $quantity; $i++) {
			$result = $generator->generate_post($template, $voice, $topic, array(
				'creation_method' => 'manual',
				'trigger_context' => array('detail' => __('Run now from Schedules', 'ai-post-scheduler')),
			));

			if ($result instanceof WP_Error) {
				$errors[] = $result->get_error_message();
			} else {
				$post_ids[] = $result;
			}
		}

		if (empty($post_ids) && !empty($errors)) {
			$error_msg = count($errors) > 1
				? __('All generation attempts failed.', 'ai-post-scheduler')
				: $errors[0];
			AIPS_Ajax_Response::error(array('message' => $error_msg, 'errors' => $errors));
		}

		$generated_count = count($post_ids);
		$generated_posts = $this->get_generated_post_modal_data($post_ids);

		$summary_message = sprintf(
			_n('%d post has been generated.', '%d posts have been generated.', $generated_count, 'ai-post-scheduler'),
			$generated_count
		);

		$notice_parts = array();

		if (!empty($errors)) {
			$notice_parts[] = sprintf(
				_n('%d generation attempt failed.', '%d generation attempts failed.', count($errors), 'ai-post-scheduler'),
				count($errors)
			);
		}

		$notice_message = implode(' ', $notice_parts);
		$message        = $summary_message;

		if ('' !== $notice_message) {
			$message .= ' ' . $notice_message;
		}

		AIPS_Ajax_Response::success(array(
			'message'         => $message,
			'summary_message' => $summary_message,
			'notice_message'  => $notice_message,
			'generated_count' => $generated_count,
			'post_ids'        => $post_ids,
			'posts'           => $generated_posts,
			'errors'          => $errors,
			'edit_url'        => !empty($post_ids) ? esc_url_raw(get_edit_post_link($post_ids[0], 'raw')) : ''
		));
	}

	public function bulk_run_now_schedules() {
		AIPS_Ajax_Guard::check_admin();

		$ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('absint', $_POST['ids']) : array();
		$ids = array_filter($ids);

		if (empty($ids)) {
			AIPS_Ajax_Response::error(__('No schedule IDs provided.', 'ai-post-scheduler'));
		}

		$slice_plan = (new AIPS_Batch_Slicer())->get_slice_plan(count($ids), array(
			'context' => 'schedule',
		));
		if (!empty($slice_plan['needs_slicing'])) {
			$this->log_bulk_slicing_notice(
				'bulk_schedule_run_now',
				sprintf(
					/* translators: 1: selected schedules, 2: slice count, 3: threshold */
					__('Bulk Run Now selected %1$d schedules. Processing will be split into %2$d slices because it exceeds threshold %3$d.', 'ai-post-scheduler'),
					count($ids),
					(int) $slice_plan['slice_count'],
					(int) $slice_plan['threshold']
				),
				array(
					'item_count'   => count($ids),
					'slice_count'  => (int) $slice_plan['slice_count'],
					'slice_size'   => (int) $slice_plan['slice_size'],
					'threshold'    => (int) $slice_plan['threshold'],
					'endpoint'     => 'aips_bulk_run_now_schedules',
				)
			);
		}

		$post_ids = array();
		$errors = array();

		$slice_size = isset($slice_plan['slice_size']) ? max(1, (int) $slice_plan['slice_size']) : count($ids);
		$id_slices  = array_chunk($ids, $slice_size);

		foreach ($id_slices as $id_slice) {
			foreach ($id_slice as $schedule_id) {
				$result = $this->scheduler->run_schedule_now($schedule_id);
				if (is_wp_error($result)) {
					$errors[] = sprintf(
						/* translators: 1: schedule ID, 2: error message */
						__('Schedule #%1$d: %2$s', 'ai-post-scheduler'),
						$schedule_id,
						$result->get_error_message()
					);
				} else {
					$schedule_post_ids = is_array($result) ? $result : array($result);
					$post_ids = array_merge($post_ids, $schedule_post_ids);
				}
			}
		}

		if (empty($post_ids) && !empty($errors)) {
			AIPS_Ajax_Response::error(array(
				'message' => __('All schedule runs failed.', 'ai-post-scheduler'),
				'errors'  => $errors,
			));
		}

		$message = sprintf(
			_n('%d post generated successfully!', '%d posts generated successfully!', count($post_ids), 'ai-post-scheduler'),
			count($post_ids)
		);

		if (!empty($errors)) {
			$message .= ' ' . sprintf(
				_n('(%d failed)', '(%d failed)', count($errors), 'ai-post-scheduler'),
				count($errors)
			);
		}

		AIPS_Ajax_Response::success(array(
			'message'  => $message,
			'post_ids' => $post_ids,
			'errors'   => $errors,
		));
	}

	public function unified_run_now() {
		AIPS_Ajax_Guard::check_admin();

		$id       = isset($_POST['id']) ? absint($_POST['id']) : 0;
		$type     = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';
		$quantity = isset($_POST['quantity']) ? min(AIPS_Author_Post_Generator::MAX_POSTS_PER_RUN, max(1, absint($_POST['quantity']))) : null;
		$advance_schedule = !isset($_POST['advance_schedule']) || rest_sanitize_boolean(wp_unslash($_POST['advance_schedule']));

		if (!$id || empty($type)) {
			AIPS_Ajax_Response::error(__('Invalid parameters.', 'ai-post-scheduler'));
		}

		$service = new AIPS_Unified_Schedule_Service();
		$result  = $service->run_now($id, $type, $quantity, $advance_schedule);

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
		}

		if ($type === AIPS_Unified_Schedule_Service::TYPE_TEMPLATE) {
			$post_ids = is_array($result) ? $result : array($result);
			$first_post_id = !empty($post_ids) ? $post_ids[0] : 0;
			$edit_url = $first_post_id ? esc_url_raw(get_edit_post_link($first_post_id, 'raw')) : '';

			$msg = sprintf(
				_n('Schedule executed — %d post generated successfully!', 'Schedule executed — %d posts generated successfully!', count($post_ids), 'ai-post-scheduler'),
				count($post_ids)
			);

			AIPS_Ajax_Response::success(array(
				'message'  => $msg,
				'post_ids' => $post_ids,
				'post_id'  => $first_post_id,
				'edit_url' => $edit_url,
			));
		} elseif ($type === AIPS_Unified_Schedule_Service::TYPE_AUTHOR_TOPIC) {
			$count = is_array($result) ? count($result) : 0;
			AIPS_Ajax_Response::success(array(
				'message' => sprintf(
					_n('%d topic generated successfully!', '%d topics generated successfully!', $count, 'ai-post-scheduler'),
					$count
				),
			));
		} elseif ($type === AIPS_Unified_Schedule_Service::TYPE_AUTHOR_POST) {
			$post_ids = is_array($result) ? array_values(array_filter(array_map('absint', $result))) : array();
			$post_id  = !empty($post_ids) ? $post_ids[0] : 0;
			$edit_url = 1 === count($post_ids) && $post_id ? esc_url_raw(get_edit_post_link($post_id, 'raw')) : '';
			AIPS_Ajax_Response::success(array(
				'message'  => sprintf(
					_n('%d post generated successfully from author topics!', '%d posts generated successfully from author topics!', count($post_ids), 'ai-post-scheduler'),
					count($post_ids)
				),
				'post_ids' => $post_ids,
				'post_id'  => $post_id,
				'edit_url' => $edit_url,
			));
		} elseif ($type === AIPS_Unified_Schedule_Service::TYPE_BLUEPRINT || $type === AIPS_Unified_Schedule_Service::TYPE_AUTHOR_WORKFLOW) {
			$topics = isset($result[AIPS_Unified_Schedule_Service::TYPE_AUTHOR_TOPIC]) ? $result[AIPS_Unified_Schedule_Service::TYPE_AUTHOR_TOPIC] : array();
			$posts  = isset($result[AIPS_Unified_Schedule_Service::TYPE_AUTHOR_POST]) ? $result[AIPS_Unified_Schedule_Service::TYPE_AUTHOR_POST] : array();

			$topic_count = is_array($topics) ? count($topics) : 0;
			$post_ids    = is_array($posts) ? array_values(array_filter(array_map('absint', $posts))) : array();
			$post_id     = !empty($post_ids) ? $post_ids[0] : 0;
			$edit_url    = 1 === count($post_ids) && $post_id ? esc_url_raw(get_edit_post_link($post_id, 'raw')) : '';

			AIPS_Ajax_Response::success(array(
				'message'  => sprintf(
					/* translators: 1: number of topics generated, 2: number of posts generated */
					__('Blueprint executed — %1$d topics and %2$d posts generated.', 'ai-post-scheduler'),
					$topic_count,
					count($post_ids)
				),
				'post_ids' => $post_ids,
				'post_id'  => $post_id,
				'edit_url' => $edit_url,
			));
		} else {
			AIPS_Ajax_Response::success(array(), __('Schedule executed successfully.', 'ai-post-scheduler'));
		}
	}

	public function unified_bulk_run_now() {
		AIPS_Ajax_Guard::check_admin();

		$items = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : array();

		if (empty($items)) {
			AIPS_Ajax_Response::error(__('No items provided.', 'ai-post-scheduler'));
		}

		$slice_plan = (new AIPS_Batch_Slicer())->get_slice_plan(count($items), array(
			'context' => 'schedule',
		));
		if (!empty($slice_plan['needs_slicing'])) {
			$this->log_bulk_slicing_notice(
				'bulk_unified_run_now',
				sprintf(
					/* translators: 1: selected schedules, 2: slice count, 3: threshold */
					__('Unified Bulk Run Now selected %1$d schedules. Processing will be split into %2$d slices because it exceeds threshold %3$d.', 'ai-post-scheduler'),
					count($items),
					(int) $slice_plan['slice_count'],
					(int) $slice_plan['threshold']
				),
				array(
					'item_count'   => count($items),
					'slice_count'  => (int) $slice_plan['slice_count'],
					'slice_size'   => (int) $slice_plan['slice_size'],
					'threshold'    => (int) $slice_plan['threshold'],
					'endpoint'     => 'aips_unified_bulk_run_now',
				)
			);
		}

		$service  = new AIPS_Unified_Schedule_Service();
		$success  = 0;
		$errors   = array();

		$slice_size  = isset($slice_plan['slice_size']) ? max(1, (int) $slice_plan['slice_size']) : count($items);
		$item_slices = array_chunk($items, $slice_size);

		foreach ($item_slices as $item_slice) {
			foreach ($item_slice as $item) {
				$id   = isset($item['id']) ? absint($item['id']) : 0;
				$type = isset($item['type']) ? sanitize_key($item['type']) : '';
				if (!$id || empty($type)) {
					continue;
				}

				$result = $service->run_now($id, $type);
				if (is_wp_error($result)) {
					$errors[] = sprintf(
						/* translators: 1: ID, 2: error */
						__('ID %1$d (%2$s): %3$s', 'ai-post-scheduler'),
						$id,
						$type,
						$result->get_error_message()
					);
				} else {
					$success++;
				}
			}
		}

		if ($success === 0 && !empty($errors)) {
			AIPS_Ajax_Response::error(array(
				'message' => __('All scheduled runs failed.', 'ai-post-scheduler'),
				'errors'  => $errors,
			));
		}

		$message = sprintf(
			_n('%d schedule ran successfully!', '%d schedules ran successfully!', $success, 'ai-post-scheduler'),
			$success
		);
		if (!empty($errors)) {
			$message .= ' ' . sprintf(
				_n('(%d failed)', '(%d failed)', count($errors), 'ai-post-scheduler'),
				count($errors)
			);
		}

		AIPS_Ajax_Response::success(array(
			'message' => $message,
			'success' => $success,
			'errors'  => $errors,
		));
	}

	public function resume_schedule_batch() {
		AIPS_Ajax_Guard::check_admin();

		$id   = isset($_POST['id']) ? absint($_POST['id']) : 0;
		$type = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';

		if (!$id || empty($type)) {
			AIPS_Ajax_Response::error(__('Invalid parameters.', 'ai-post-scheduler'));
		}

		if ($type !== AIPS_Unified_Schedule_Service::TYPE_TEMPLATE) {
			AIPS_Ajax_Response::error(__('Batch resume is only available for template schedules.', 'ai-post-scheduler'));
		}

		$schedule = $this->schedule_repository->get_by_id($id);
		if (!$schedule) {
			AIPS_Ajax_Response::error(__('Schedule not found.', 'ai-post-scheduler'));
		}

		if (empty($schedule->batch_progress)) {
			AIPS_Ajax_Response::error(__('No incomplete batch found for this schedule.', 'ai-post-scheduler'));
		}

		$batch_progress = json_decode($schedule->batch_progress, true);
		if (!is_array($batch_progress) || !isset($batch_progress['completed'], $batch_progress['total'])) {
			AIPS_Ajax_Response::error(__('Invalid batch progress data.', 'ai-post-scheduler'));
		}

		if ($batch_progress['completed'] >= $batch_progress['total']) {
			AIPS_Ajax_Response::error(__('Batch is already complete.', 'ai-post-scheduler'));
		}

		$result = $this->scheduler->run_schedule_now($id);

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
		}

		$post_ids = is_array($result) ? $result : array($result);
		$msg = sprintf(
			_n('Batch resumed — %d post generated successfully!', 'Batch resumed — %d posts generated successfully!', count($post_ids), 'ai-post-scheduler'),
			count($post_ids)
		);

		AIPS_Ajax_Response::success(array(
			'message' => $msg,
			'post_ids' => $post_ids,
		));
	}

	public function get_generated_post_modal_data($post_ids) {
		$posts = array();

		if (!empty($post_ids) && function_exists('_prime_post_caches')) {
			_prime_post_caches(array_unique(array_filter(array_map('intval', $post_ids))), false, true);
		}

		foreach ($post_ids as $post_id) {
			$post_id = absint($post_id);

			if (!$post_id) {
				continue;
			}

			$post = get_post($post_id);

			if (!$post instanceof WP_Post) {
				continue;
			}

			$title = get_the_title($post_id);
			if ('' === trim($title)) {
				$title = __('(no title)', 'ai-post-scheduler');
			}

			$excerpt = wp_strip_all_tags(get_the_excerpt($post));
			if ('' === trim($excerpt)) {
				$excerpt = __('No excerpt available.', 'ai-post-scheduler');
			}

			$content_snippet = wp_trim_words(
				wp_strip_all_tags((string) $post->post_content),
				28,
				'...'
			);

			if ('' === trim($content_snippet)) {
				$content_snippet = __('No content available.', 'ai-post-scheduler');
			}

			$view_url = 'publish' === $post->post_status
				? get_permalink($post_id)
				: get_preview_post_link($post);

			if (!$view_url) {
				$view_url = get_edit_post_link($post_id, 'raw');
			}

			$posts[] = array(
				'id'              => $post_id,
				'title'           => $title,
				'excerpt'         => $excerpt,
				'content_snippet' => $content_snippet,
				'post_content'    => wpautop(wp_kses_post((string) $post->post_content)),
				'edit_url'        => esc_url_raw(get_edit_post_link($post_id, 'raw')),
				'view_url'        => esc_url_raw($view_url),
			);
		}

		return $posts;
	}

	public function log_bulk_slicing_notice($history_type, $message, $context = array()) {
		$history_service = new AIPS_History_Service($this->history_repository);
		$history = $history_service->create($history_type, array(
			'creation_method' => $history_type,
			'user_id'         => get_current_user_id(),
			'source'          => 'manual_ui',
		));

		if (!$history) {
			return;
		}

		$history->record(
			'activity',
			$message,
			array(
				'event_type'   => 'bulk_slicing_notice',
				'event_status' => 'success',
			),
			null,
			$context
		);
		$history->complete_success();
	}
}
