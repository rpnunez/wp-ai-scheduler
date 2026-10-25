<?php
/**
 * Schedule Mutation Handler
 *
 * Handles AJAX mutation / state change endpoints for schedules.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Schedule_Mutation_Handler
 */
class AIPS_Schedule_Mutation_Handler {

	/**
	 * @var AIPS_Scheduler
	 */
	private $scheduler;

	/**
	 * @var AIPS_Schedule_Repository_Interface
	 */
	private $schedule_repository;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Scheduler|null                   $scheduler
	 * @param AIPS_Schedule_Repository_Interface|null $schedule_repository
	 */
	public function __construct($scheduler = null, ?AIPS_Schedule_Repository_Interface $schedule_repository = null) {
		$container = AIPS_Container::get_instance();
		$this->scheduler = $scheduler ?: $container->makeIfExists(AIPS_Scheduler::class, function() {
			return new AIPS_Scheduler();
		});
		$this->schedule_repository = $schedule_repository ?: $container->makeIfExists(AIPS_Schedule_Repository_Interface::class, function() {
			return new AIPS_Schedule_Repository();
		});
	}

	public function save_schedule() {
		AIPS_Ajax_Guard::check_admin();

		$data = array(
			'id' => isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0,
			'template_id' => isset($_POST['template_id']) ? absint($_POST['template_id']) : 0,
			'title' => isset($_POST['schedule_title']) ? sanitize_text_field(wp_unslash($_POST['schedule_title'])) : '',
			'frequency' => isset($_POST['frequency']) ? sanitize_text_field(wp_unslash($_POST['frequency'])) : 'daily',
			'start_time' => isset($_POST['start_time']) ? sanitize_text_field(wp_unslash($_POST['start_time'])) : null,
			'is_active' => isset($_POST['is_active']) && 1 === absint($_POST['is_active']) ? 1 : 0,
			'topic' => isset($_POST['topic']) ? sanitize_text_field(wp_unslash($_POST['topic'])) : '',
			'article_structure_id' => isset($_POST['article_structure_id']) && $_POST['article_structure_id'] !== '' ? absint($_POST['article_structure_id']) : null,
			'rotation_pattern' => isset($_POST['rotation_pattern']) && $_POST['rotation_pattern'] !== '' ? sanitize_text_field(wp_unslash($_POST['rotation_pattern'])) : null,
		);

		if (empty($data['template_id'])) {
			AIPS_Ajax_Response::error(__('Please select a template.', 'ai-post-scheduler'));
		}

		$interval_calculator = new AIPS_Interval_Calculator();
		if (!$interval_calculator->is_valid_frequency($data['frequency'])) {
			AIPS_Ajax_Response::error(__('Invalid frequency selected.', 'ai-post-scheduler'));
		}

		$id = $this->scheduler->save_schedule($data);

		if ($id) {
			AIPS_Ajax_Response::success(array(
				'message' => __('Schedule saved successfully.', 'ai-post-scheduler'),
				'schedule_id' => $id
			));
		} else {
			AIPS_Ajax_Response::error(__('Failed to save schedule.', 'ai-post-scheduler'));
		}
	}

	public function delete_schedule() {
		AIPS_Ajax_Guard::check_admin();

		$id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;

		if (!$id) {
			AIPS_Ajax_Response::error(__('Invalid schedule ID.', 'ai-post-scheduler'));
		}

		$schedule = $this->schedule_repository->get_by_id($id);
		if ($schedule && !empty($schedule->campaign_id)) {
			AIPS_Ajax_Response::error(__('This schedule cannot be deleted here because it belongs to a campaign. Delete it from the Campaigns page.', 'ai-post-scheduler'));
		}

		if ($this->schedule_repository->delete($id)) {
			$this->record_schedule_event(AIPS_History_Event_Type::SCHEDULE_DELETED, $id, $schedule, true);
			AIPS_Ajax_Response::success(array(), __('Schedule deleted successfully.', 'ai-post-scheduler'));
		} else {
			$this->record_schedule_event(AIPS_History_Event_Type::SCHEDULE_DELETED, $id, $schedule, false);
			AIPS_Ajax_Response::error(__('Failed to delete schedule.', 'ai-post-scheduler'));
		}
	}

	public function toggle_schedule() {
		AIPS_Ajax_Guard::check_admin();

		$id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;
		$is_active = isset($_POST['is_active']) ? absint($_POST['is_active']) : 0;

		if (!$id) {
			AIPS_Ajax_Response::error(__('Invalid schedule ID.', 'ai-post-scheduler'));
		}

		$result = $this->scheduler->toggle_active($id, $is_active);

		if ($result !== false) {
			AIPS_Ajax_Response::success(array(), __('Schedule updated.', 'ai-post-scheduler'));
		} else {
			AIPS_Ajax_Response::error(__('Failed to update schedule.', 'ai-post-scheduler'));
		}
	}

	public function bulk_delete_schedules() {
		AIPS_Ajax_Guard::check_admin();

		$ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('absint', $_POST['ids']) : array();
		$ids = array_filter($ids);

		if (empty($ids)) {
			AIPS_Ajax_Response::error(__('No schedule IDs provided.', 'ai-post-scheduler'));
		}

		$campaign_owned = $this->schedule_repository->get_campaign_owned_ids($ids);
		if (!empty($campaign_owned)) {
			AIPS_Ajax_Response::error(__('One or more selected schedules belong to a campaign and cannot be deleted here.', 'ai-post-scheduler'));
		}

		$deleted = $this->schedule_repository->delete_bulk($ids);
		$this->record_schedule_bulk_delete($ids, ($deleted !== false) ? (int) $deleted : 0, $deleted !== false);

		if ($deleted !== false) {
			AIPS_Ajax_Response::success(array(
				'message' => sprintf(
					_n('%d schedule deleted successfully.', '%d schedules deleted successfully.', $deleted, 'ai-post-scheduler'),
					$deleted
				),
				'deleted' => $deleted,
			));
		} else {
			AIPS_Ajax_Response::error(__('Failed to delete schedules.', 'ai-post-scheduler'));
		}
	}

	public function bulk_toggle_schedules() {
		AIPS_Ajax_Guard::check_admin();

		$ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('absint', $_POST['ids']) : array();
		$ids = array_filter($ids);
		$is_active = isset($_POST['is_active']) ? absint($_POST['is_active']) : 0;

		if (empty($ids)) {
			AIPS_Ajax_Response::error(__('No schedule IDs provided.', 'ai-post-scheduler'));
		}

		$updated = $this->schedule_repository->set_active_bulk($ids, $is_active);

		if ($updated !== false) {
			$count = (int) $updated ?: count($ids);
			$action_label = $is_active ? __('activated', 'ai-post-scheduler') : __('paused', 'ai-post-scheduler');
			AIPS_Ajax_Response::success(array(
				'message' => sprintf(
					_n('%1$d schedule %2$s successfully.', '%1$d schedules %2$s successfully.', $count, 'ai-post-scheduler'),
					$count,
					$action_label
				),
				'updated' => $updated,
				'is_active' => $is_active,
			));
		} else {
			AIPS_Ajax_Response::error(__('Failed to update schedules.', 'ai-post-scheduler'));
		}
	}

	public function unified_toggle() {
		AIPS_Ajax_Guard::check_admin();

		$id        = isset($_POST['id']) ? absint($_POST['id']) : 0;
		$type      = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';
		$is_active = isset($_POST['is_active']) ? absint($_POST['is_active']) : 0;

		if (!$id || empty($type)) {
			AIPS_Ajax_Response::error(__('Invalid parameters.', 'ai-post-scheduler'));
		}

		$service = new AIPS_Unified_Schedule_Service();
		$result  = $service->toggle($id, $type, $is_active);

		if ($result !== false) {
			$label = $is_active
				? __('Schedule activated.', 'ai-post-scheduler')
				: __('Schedule paused.', 'ai-post-scheduler');
			AIPS_Ajax_Response::success(array('message' => $label, 'is_active' => $is_active));
		} else {
			AIPS_Ajax_Response::error(__('Failed to update schedule.', 'ai-post-scheduler'));
		}
	}

	public function unified_bulk_toggle() {
		AIPS_Ajax_Guard::check_admin();

		$items     = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : array();
		$is_active = isset($_POST['is_active']) ? absint($_POST['is_active']) : 0;

		if (empty($items)) {
			AIPS_Ajax_Response::error(__('No items provided.', 'ai-post-scheduler'));
		}

		$service = new AIPS_Unified_Schedule_Service();
		$updated = 0;
		$errors  = array();

		foreach ($items as $item) {
			$id   = isset($item['id']) ? absint($item['id']) : 0;
			$type = isset($item['type']) ? sanitize_key($item['type']) : '';
			if (!$id || empty($type)) {
				continue;
			}
			$result = $service->toggle($id, $type, $is_active);
			if ($result !== false) {
				$updated++;
			} else {
				$errors[] = $id;
			}
		}

		$action_label = $is_active
			? __('activated', 'ai-post-scheduler')
			: __('paused', 'ai-post-scheduler');

		AIPS_Ajax_Response::success(array(
			'message' => sprintf(
				_n('%1$d schedule %2$s.', '%1$d schedules %2$s.', $updated, 'ai-post-scheduler'),
				$updated,
				$action_label
			),
			'updated'   => $updated,
			'errors'    => $errors,
			'is_active' => $is_active,
		));
	}

	public function unified_bulk_delete() {
		AIPS_Ajax_Guard::check_admin();

		$items = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : array();

		if (empty($items)) {
			AIPS_Ajax_Response::error(__('No items provided.', 'ai-post-scheduler'));
		}

		$service       = new AIPS_Unified_Schedule_Service();
		$deleted_count = 0;
		$deleted_items = array();
		$failed_items  = array();

		foreach ($items as $item) {
			$id   = isset($item['id']) ? absint($item['id']) : 0;
			$type = isset($item['type']) ? sanitize_key($item['type']) : '';

			if (!$id || empty($type)) {
				continue;
			}

			$result = $service->delete($id, $type);
			if (is_wp_error($result)) {
				$failed_items[] = array(
					'id'      => $id,
					'type'    => $type,
					'message' => $result->get_error_message(),
				);
			} else {
				$deleted_count++;
				$deleted_items[] = array(
					'id'   => $id,
					'type' => $type,
				);
			}
		}

		if (!empty($deleted_items)) {
			$this->record_schedule_bulk_delete(array_column($deleted_items, 'id'), $deleted_count, true, $deleted_items);
		}

		if ($deleted_count === 0) {
			AIPS_Ajax_Response::error(array(
				'message'      => __('No selected schedules could be deleted.', 'ai-post-scheduler'),
				'deleted'      => 0,
				'deleted_items'=> array(),
				'failed_items' => $failed_items,
			));
		}

		$message = sprintf(
			_n('%d schedule deleted successfully.', '%d schedules deleted successfully.', $deleted_count, 'ai-post-scheduler'),
			$deleted_count
		);

		if (!empty($failed_items)) {
			$message .= ' ' . sprintf(
				_n('(%d could not be deleted)', '(%d could not be deleted)', count($failed_items), 'ai-post-scheduler'),
				count($failed_items)
			);
		}

		AIPS_Ajax_Response::success(array(
			'message'      => $message,
			'deleted'      => $deleted_count,
			'deleted_items'=> $deleted_items,
			'failed_items' => $failed_items,
		));
	}

	public function reset_schedule_circuit() {
		AIPS_Ajax_Guard::check_admin();

		$id   = isset($_POST['id']) ? absint($_POST['id']) : 0;
		$type = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';

		if (!$id || empty($type)) {
			AIPS_Ajax_Response::error(__('Invalid parameters.', 'ai-post-scheduler'));
		}

		if ($type !== AIPS_Unified_Schedule_Service::TYPE_TEMPLATE) {
			AIPS_Ajax_Response::error(__('Circuit reset is only available for template schedules.', 'ai-post-scheduler'));
		}

		$result = $this->schedule_repository->update($id, array('circuit_state' => 'closed'));
		$this->record_schedule_event(AIPS_History_Event_Type::SCHEDULE_CIRCUIT_RESET, $id, $this->schedule_repository->get_by_id($id), (bool) $result);

		if ($result) {
			AIPS_Ajax_Response::success(array(
				'message' => __('Circuit breaker reset successfully. The schedule will attempt to run on its next trigger.', 'ai-post-scheduler'),
				'circuit_state' => 'closed',
			));
		} else {
			AIPS_Ajax_Response::error(__('Failed to reset circuit breaker.', 'ai-post-scheduler'));
		}
	}

	public function record_schedule_event($event_type, $schedule_id, $schedule, $success) {
		$label = ($schedule && !empty($schedule->title)) ? (string) $schedule->title : '';
		$verbs = array(
			AIPS_History_Event_Type::SCHEDULE_DELETED       => array(__('deleted', 'ai-post-scheduler'), __('delete', 'ai-post-scheduler')),
			AIPS_History_Event_Type::SCHEDULE_CIRCUIT_RESET => array(__('circuit breaker reset', 'ai-post-scheduler'), __('reset the circuit breaker for', 'ai-post-scheduler')),
		);
		$verb = isset($verbs[$event_type]) ? $verbs[$event_type] : array($event_type, $event_type);

		AIPS_History_Event_Recorder::instance()->record_entity_change(
			$event_type,
			'schedule_lifecycle',
			AIPS_History_Subject::TYPE_SCHEDULE,
			$schedule_id,
			$label,
			$success,
			__('Schedule', 'ai-post-scheduler'),
			$verb[0],
			$verb[1]
		);
	}

	public function record_schedule_bulk_delete(array $ids, $deleted, $success, array $items = array()) {
		$message = $success
			? sprintf(_n('%d schedule deleted', '%d schedules deleted', $deleted, 'ai-post-scheduler'), $deleted)
			: __('Failed to delete schedules', 'ai-post-scheduler');
		$input   = array('schedule_ids' => array_values(array_map('intval', $ids)), 'deleted' => (int) $deleted);
		if (!empty($items)) {
			$input['items'] = array_values($items);
		}
		$event   = $success
			? AIPS_History_Event::success(AIPS_History_Event_Type::SCHEDULE_DELETED, $message, null, array(), $input)
			: AIPS_History_Event::failure(AIPS_History_Event_Type::SCHEDULE_DELETED, $message, null, array(), $input);

		AIPS_History_Event_Recorder::instance()->record_lifecycle(
			$event,
			'schedule_lifecycle',
			array('event' => __('Bulk schedule delete', 'ai-post-scheduler'), 'detail' => sprintf(__('%d schedules selected', 'ai-post-scheduler'), count($ids)))
		);
	}
}
