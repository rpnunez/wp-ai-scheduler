<?php
/**
 * Schedule Query Handler
 *
 * Handles AJAX query / read endpoints for schedules.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Schedule_Query_Handler
 */
class AIPS_Schedule_Query_Handler {

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
	 * @param AIPS_Schedule_Repository_Interface|null $schedule_repository
	 * @param AIPS_History_Repository_Interface|null  $history_repository
	 */
	public function __construct(?AIPS_Schedule_Repository_Interface $schedule_repository = null, ?AIPS_History_Repository_Interface $history_repository = null) {
		$container = AIPS_Container::get_instance();
		$this->schedule_repository = $schedule_repository ?: $container->makeIfExists(AIPS_Schedule_Repository_Interface::class, function() {
			return new AIPS_Schedule_Repository();
		});
		$this->history_repository = $history_repository ?: $container->makeIfExists(AIPS_History_Repository_Interface::class, function() {
			return new AIPS_History_Repository();
		});
	}

	/**
	 * Get schedule status read model for the status strip.
	 */
	public function get_schedule_status_read_model() {
		AIPS_Ajax_Guard::check_admin();

		$cache = AIPS_Cache_Factory::make();
		$cache_key = 'aips_schedule_status_strip_v2';
		$cached = $cache->get($cache_key);
		if (is_array($cached)) {
			AIPS_Ajax_Response::success($cached);
		}

		$families = array(
			AIPS_Unified_Schedule_Service::TYPE_TEMPLATE => 'aips_generate_scheduled_posts',
			AIPS_Unified_Schedule_Service::TYPE_AUTHOR_TOPIC => 'aips_generate_author_topics',
			AIPS_Unified_Schedule_Service::TYPE_AUTHOR_POST => 'aips_generate_author_posts',
		);

		$next_runs = array();
		foreach ($families as $family => $hook) {
			$next_runs[$family] = wp_next_scheduled($hook) ?: null;
		}

		$queue_hooks = array(
			'aips_process_schedule_batch',
			'aips_process_author_topics_slice',
			'aips_retry_failed_author_slices_topics',
			'aips_process_author_post_slice',
			'aips_retry_failed_author_slices_posts',
			'aips_process_bulk_batch',
			'aips_process_author_embeddings',
			'aips_index_posts_batch',
		);
		$queue_depth = array_fill_keys($queue_hooks, 0);
		$queue_timeline = array();
		$now = time();
		$next_24h = $now + DAY_IN_SECONDS;
		$cron = _get_cron_array();
		if (is_array($cron)) {
			foreach ($cron as $timestamp => $hooks) {
				if ((int) $timestamp > $next_24h) {
					continue;
				}
				foreach ($queue_hooks as $hook) {
					if (!isset($hooks[$hook]) || !is_array($hooks[$hook])) {
						continue;
					}
					$count = count($hooks[$hook]);
					$queue_depth[$hook] += $count;
					$queue_timeline[] = array(
						'timestamp' => (int) $timestamp,
						'hook' => $hook,
						'count' => $count,
					);
				}
			}
		}

		$active_schedules = (new AIPS_Unified_Schedule_Service())->get_active_schedules_summary();
		$incomplete_batches = $this->schedule_repository->get_incomplete_batches();

		$payload = array(
			'next_runs' => $next_runs,
			'queue_depth' => $queue_depth,
			'queue_timeline' => $queue_timeline,
			'active_schedules' => $active_schedules,
			'incomplete_batches' => $incomplete_batches,
			'timestamp' => $now,
			'links' => array(
				'schedules' => AIPS_Admin_Menu_Helper::get_page_url('schedules'),
				'history' => AIPS_Admin_Menu_Helper::get_page_url('history'),
				'telemetry' => AIPS_Admin_Menu_Helper::get_page_url('telemetry'),
				'system_status' => AIPS_Admin_Menu_Helper::get_page_url('system-status'),
			),
		);

		$cache->set($cache_key, $payload, 60);
		AIPS_Ajax_Response::success($payload);
	}

	/**
	 * Get total post count for a set of schedules.
	 */
	public function get_schedules_post_count() {
		AIPS_Ajax_Guard::check_admin();

		$ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('absint', $_POST['ids']) : array();
		$ids = array_filter($ids);

		if (empty($ids)) {
			AIPS_Ajax_Response::success(array('count' => 0));
		}

		$count = $this->schedule_repository->get_post_count_for_schedules($ids);

		AIPS_Ajax_Response::success(array('count' => $count));
	}

	/**
	 * Get history logs for a schedule.
	 */
	public function get_schedule_history() {
		AIPS_Ajax_Guard::check_admin();

		$schedule_id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;
		if (!$schedule_id) {
			AIPS_Ajax_Response::error(__('Invalid schedule ID.', 'ai-post-scheduler'));
		}

		$schedule = $this->schedule_repository->get_by_id($schedule_id);

		if (!$schedule) {
			AIPS_Ajax_Response::error(__('Schedule not found.', 'ai-post-scheduler'));
		}

		if (empty($schedule->schedule_history_id)) {
			AIPS_Ajax_Response::success(array('entries' => array()));
		}

		$logs = $this->history_repository->get_logs_by_history_id(
			absint($schedule->schedule_history_id),
			array(AIPS_History_Type::ACTIVITY, AIPS_History_Type::ERROR)
		);

		$entries = AIPS_History_Event_View::from_logs($logs);

		AIPS_Ajax_Response::success(array('entries' => $entries));
	}

	/**
	 * Get unified history logs across all schedule types.
	 */
	public function get_unified_schedule_history() {
		AIPS_Ajax_Guard::check_admin();

		$id    = isset($_POST['id']) ? absint($_POST['id']) : 0;
		$type  = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';
		$limit = isset($_POST['limit']) ? absint($_POST['limit']) : 0;

		if (!$id || empty($type)) {
			AIPS_Ajax_Response::error(__('Invalid parameters.', 'ai-post-scheduler'));
		}

		$service = new AIPS_Unified_Schedule_Service();
		$entries = $service->get_history($id, $type, $limit);

		AIPS_Ajax_Response::success(array('entries' => $entries));
	}
}
