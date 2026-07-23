<?php
/**
 * System Diagnostics Service
 *
 * Owns the safe maintenance/recovery operations exposed on the Diagnostics >
 * System Status page. Individual AJAX handlers and the Refresh System bundle
 * both delegate here so the logic lives in one place.
 *
 * @package AI_Post_Scheduler
 * @since   2.9.1
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_System_Diagnostics_Service
 */
class AIPS_System_Diagnostics_Service {
	/**
	 * @var AIPS_History_Repository
	 */
	private $history_repository;

	/**
	 * @var AIPS_Bulk_Batch_Job_Store|null
	 */
	private $bulk_batch_job_store;

	/**
	 * @var AIPS_Resilience_Service|null
	 */
	private $resilience_service;

	/**
	 * @var AIPS_Cache_Monitor_Service|null
	 */
	private $cache_monitor_service;

	/**
	 * @var AIPS_Notifications_Repository
	 */
	private $notifications_repository;

	/**
	 * @var AIPS_Date_Time_DB_Repair
	 */
	private $date_time_db_repair;

	public function __construct(
		$history_repository = null,
		$bulk_batch_job_store = null,
		$resilience_service = null,
		$cache_monitor_service = null,
		$notifications_repository = null,
		$date_time_db_repair = null
	) {
		$container = AIPS_Container::get_instance();

		$this->history_repository = $history_repository ?: ($container->has(AIPS_History_Repository::class)
			? $container->make(AIPS_History_Repository::class)
			: new AIPS_History_Repository());

		$this->bulk_batch_job_store = $bulk_batch_job_store ?: ($container->has(AIPS_Bulk_Batch_Job_Store::class)
			? $container->make(AIPS_Bulk_Batch_Job_Store::class)
			: (class_exists('AIPS_Bulk_Batch_Job_Store') ? new AIPS_Bulk_Batch_Job_Store() : null));

		$this->resilience_service = $resilience_service ?: ($container->has(AIPS_Resilience_Service::class)
			? $container->make(AIPS_Resilience_Service::class)
			: (class_exists('AIPS_Resilience_Service') ? new AIPS_Resilience_Service() : null));

		if ($cache_monitor_service) {
			$this->cache_monitor_service = $cache_monitor_service;
		} elseif (class_exists('AIPS_Cache_Monitor_Service') && class_exists('AIPS_Cache_Monitor_Repository') && class_exists('AIPS_Cache_Index')) {
			$this->cache_monitor_service = new AIPS_Cache_Monitor_Service(new AIPS_Cache_Monitor_Repository(), new AIPS_Cache_Index());
		} else {
			$this->cache_monitor_service = null;
		}

		$this->notifications_repository = $notifications_repository ?: ($container->has(AIPS_Notifications_Repository::class)
			? $container->make(AIPS_Notifications_Repository::class)
			: AIPS_Notifications_Repository::instance());

		$this->date_time_db_repair = $date_time_db_repair ?: new AIPS_Date_Time_DB_Repair();
	}

	/**
	 * Build the ordered task list used for bundled refresh operations.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_refresh_task_definitions() {
		return array(
			'cache_maintenance' => array(
				'label'        => __('Cache maintenance', 'ai-post-scheduler'),
				'button_label' => __('Prune Cache Data', 'ai-post-scheduler'),
				'group'        => 'cleanup_repair',
				'action'       => 'aips_status_cache_maintenance',
				'callback'     => array($this, 'run_cache_maintenance'),
			),
			'cleanup_notifications' => array(
				'label'        => __('Notification cleanup', 'ai-post-scheduler'),
				'button_label' => __('Clean Old Notifications', 'ai-post-scheduler'),
				'group'        => 'cleanup_repair',
				'action'       => 'aips_status_cleanup_notifications',
				'callback'     => array($this, 'cleanup_notifications'),
			),
			'cleanup_stale_jobs_cache' => array(
				'label'        => __('Stale batch jobs/cache cleanup', 'ai-post-scheduler'),
				'button_label' => __('Cleanup Stale Batch Jobs/Cache', 'ai-post-scheduler'),
				'group'        => 'recovery',
				'action'       => 'aips_status_cleanup_stale_jobs_cache',
				'callback'     => array($this, 'cleanup_stale_jobs_cache'),
			),
			'clear_partial_generations' => array(
				'label'        => __('Clear stuck partial generations', 'ai-post-scheduler'),
				'button_label' => __('Clear Stuck Partial Generations', 'ai-post-scheduler'),
				'group'        => 'recovery',
				'action'       => 'aips_status_clear_partial_generations',
				'callback'     => array($this, 'clear_partial_generations'),
			),
			'repair_campaign_data' => array(
				'label'        => __('Campaign data repair', 'ai-post-scheduler'),
				'button_label' => __('Repair Campaign Data', 'ai-post-scheduler'),
				'group'        => 'recovery',
				'action'       => 'aips_status_repair_campaign_data',
				'callback'     => array($this, 'repair_campaign_data'),
			),
			'repair_datetime' => array(
				'label'        => __('Schedule timing repair', 'ai-post-scheduler'),
				'button_label' => __('Repair Schedule Timings', 'ai-post-scheduler'),
				'group'        => 'cleanup_repair',
				'action'       => 'aips_status_repair_datetime',
				'callback'     => array($this, 'repair_datetime'),
			),
			'reschedule_missed_cron' => array(
				'label'        => __('Reschedule cron events', 'ai-post-scheduler'),
				'button_label' => __('Reschedule Missed Cron Hooks', 'ai-post-scheduler'),
				'group'        => 'recovery',
				'action'       => 'aips_status_reschedule_missed_cron',
				'callback'     => array($this, 'reschedule_missed_cron'),
			),
			'retry_failed_slices' => array(
				'label'        => __('Retry failed slices', 'ai-post-scheduler'),
				'button_label' => __('Retry Failed Slices', 'ai-post-scheduler'),
				'group'        => 'recovery',
				'action'       => 'aips_status_retry_failed_slices',
				'callback'     => array($this, 'retry_failed_slices'),
			),
			'reset_resilience' => array(
				'label'        => __('Reset resilience state', 'ai-post-scheduler'),
				'button_label' => __('Reset Resilience', 'ai-post-scheduler'),
				'group'        => 'cleanup_repair',
				'action'       => 'aips_status_reset_resilience',
				'callback'     => array($this, 'reset_resilience'),
			),
			'rebuild_caches' => array(
				'label'        => __('Rebuild caches', 'ai-post-scheduler'),
				'button_label' => __('Rebuild Caches', 'ai-post-scheduler'),
				'group'        => 'cleanup_repair',
				'action'       => 'aips_rebuild_caches',
				'callback'     => array($this, 'rebuild_caches'),
			),
		);
	}

	/**
	 * Return checkbox metadata for the Refresh System task selector.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_refresh_task_groups() {
		$definitions = $this->get_refresh_task_definitions();
		$group_map   = array(
			'recovery'       => array(
				'label' => __('Recovery', 'ai-post-scheduler'),
				'steps' => array(
					'reschedule_missed_cron',
					'retry_failed_slices',
					'repair_campaign_data',
					'clear_partial_generations',
					'cleanup_stale_jobs_cache',
				),
			),
			'cleanup_repair' => array(
				'label' => __('Cleanup & repair', 'ai-post-scheduler'),
				'steps' => array(
					'cache_maintenance',
					'cleanup_notifications',
					'reset_resilience',
					'repair_datetime',
					'rebuild_caches',
				),
			),
		);
		$groups      = array();

		foreach ($group_map as $group_key => $group_config) {
			$tasks = array();

			foreach ($group_config['steps'] as $step) {
				if (!isset($definitions[$step])) {
					continue;
				}

				$tasks[] = array(
					'step'   => $step,
					'label'  => $definitions[$step]['button_label'],
					'action' => $definitions[$step]['action'],
				);
			}

			if (!empty($tasks)) {
				$groups[] = array(
					'key'   => $group_key,
					'label' => $group_config['label'],
					'tasks' => $tasks,
				);
			}
		}

		return $groups;
	}

	/**
	 * Filter selected task IDs into the bundled execution order.
	 *
	 * @param array<string>|null $selected_tasks Optional selected task IDs.
	 * @return array<string, array<string, mixed>>
	 */
	private function get_selected_refresh_tasks($selected_tasks = null) {
		$definitions = $this->get_refresh_task_definitions();

		if (null === $selected_tasks) {
			return $definitions;
		}

		if (!is_array($selected_tasks)) {
			return array();
		}

		$sanitized_tasks = array_map('sanitize_key', $selected_tasks);
		$filtered_tasks  = array_filter($sanitized_tasks);
		$selected_lookup = array_fill_keys($filtered_tasks, true);
		$selected_order  = array();

		foreach ($definitions as $step => $definition) {
			if (isset($selected_lookup[$step])) {
				$selected_order[$step] = $definition;
			}
		}

		return $selected_order;
	}

	/**
	 * Unschedule and re-register every plugin cron event.
	 *
	 * @return array
	 */
	public function reschedule_missed_cron() {
		$cron_events    = AI_Post_Scheduler::get_cron_events();
		$rescheduled    = 0;
		$base_timestamp = AIPS_DateTime::now()->timestamp() + MINUTE_IN_SECONDS;

		foreach ($cron_events as $hook => $config) {
			$schedule = isset($config['schedule']) ? $config['schedule'] : 'hourly';
			wp_unschedule_hook($hook);
			if (wp_schedule_event($base_timestamp, $schedule, $hook) !== false) {
				$rescheduled++;
			}
		}

		return array(
			'success' => true,
			'message' => sprintf(__('Flushed and rescheduled %d cron events.', 'ai-post-scheduler'), $rescheduled),
		);
	}

	/**
	 * Immediately schedule retry hooks for failed author slices (topics + posts).
	 *
	 * @return array
	 */
	public function retry_failed_slices() {
		$scheduled = 0;
		$now       = AIPS_DateTime::now()->timestamp();

		if (wp_schedule_single_event($now, 'aips_retry_failed_author_slices_topics')) {
			$scheduled++;
		}
		if (wp_schedule_single_event($now, 'aips_retry_failed_author_slices_posts')) {
			$scheduled++;
		}

		return array(
			'success' => true,
			'message' => sprintf(__('Scheduled %d failed slice retry hooks.', 'ai-post-scheduler'), $scheduled),
		);
	}

	/**
	 * Backfill missing campaign IDs on history rows.
	 *
	 * @return array
	 */
	public function repair_campaign_data() {
		$this->history_repository->repair_missing_campaign_ids();

		return array(
			'success' => true,
			'message' => __('Campaign data repair completed. Refresh Campaigns or Content if you want to verify repaired counts and filters immediately.', 'ai-post-scheduler'),
		);
	}

	/**
	 * Reconcile stuck partial generations by firing the components-updated hook.
	 *
	 * @return array
	 */
	public function clear_partial_generations() {
		$partials   = $this->history_repository->get_partial_generations(array('per_page' => 10));
		$reconciled = 0;

		if (!empty($partials['items'])) {
			foreach ($partials['items'] as $item) {
				if (!empty($item->post_id)) {
					do_action('aips_post_components_updated', (int) $item->post_id, array(), array());
					$reconciled++;
				}
			}
		}

		return array(
			'success' => true,
			'message' => sprintf(__('Reconciled %d partial generations.', 'ai-post-scheduler'), $reconciled),
		);
	}

	/**
	 * Delete old bulk batch jobs and flush the plugin cache.
	 *
	 * @return array
	 */
	public function cleanup_stale_jobs_cache() {
		$deleted = 0;

		if ($this->bulk_batch_job_store) {
			$deleted = $this->bulk_batch_job_store->cleanup_old_jobs();
		}

		$cache_flushed = false;
		if (class_exists('AIPS_Cache_Factory')) {
			$cache         = AIPS_Cache_Factory::make();
			$cache_flushed = $cache ? (bool) $cache->flush() : false;
		}

		return array(
			'success' => true,
			'message' => sprintf(
				__('Cleaned %1$d stale jobs. Cache flushed: %2$s.', 'ai-post-scheduler'),
				$deleted,
				$cache_flushed ? __('yes', 'ai-post-scheduler') : __('no', 'ai-post-scheduler')
			),
		);
	}

	/**
	 * Rebuild cache subsystems via the invalidation bus.
	 *
	 * @param string $subsystem Subsystem key or 'all'.
	 * @return array
	 */
	public function rebuild_caches($subsystem = 'all') {
		$subsystems         = AIPS_Cache_Policy::get_subsystems();
		$allowed_subsystems = array_keys($subsystems);

		if ('all' !== $subsystem && !in_array($subsystem, $allowed_subsystems, true)) {
			$subsystem = 'all';
		}

		$affected          = AIPS_Cache_Invalidation_Bus::rebuild($subsystem);
		$subsystem_label   = ('all' === $subsystem) ? __('All subsystems', 'ai-post-scheduler') : (isset($subsystems[$subsystem]['label']) ? (string) $subsystems[$subsystem]['label'] : $subsystem);
		$affected_display  = !empty($affected) ? implode(', ', $affected) : __('none', 'ai-post-scheduler');

		AIPS_Logger::instance()->log('Cache rebuild requested from admin tool.', 'info', array('subsystem' => $subsystem, 'affected_caches' => $affected));

		return array(
			'success'   => true,
			'message'   => sprintf(__('Rebuilt caches for %1$s. Affected caches: %2$s', 'ai-post-scheduler'), $subsystem_label, $affected_display),
			'subsystem' => $subsystem,
			'affected'  => $affected,
		);
	}

	/**
	 * Prune expired cache entries, orphaned index rows, and old cache events.
	 *
	 * @return array
	 */
	public function run_cache_maintenance() {
		if (!$this->cache_monitor_service) {
			return array(
				'success' => false,
				'message' => __('Cache monitor service is unavailable.', 'ai-post-scheduler'),
			);
		}

		$result = $this->cache_monitor_service->run_maintenance();

		return array(
			'success' => true,
			'message' => sprintf(
				__('Cache maintenance complete: %1$d expired entries, %2$d orphaned index rows, %3$d old events pruned.', 'ai-post-scheduler'),
				isset($result['pruned_index']) ? (int) $result['pruned_index'] : 0,
				isset($result['pruned_orphans']) ? (int) $result['pruned_orphans'] : 0,
				isset($result['pruned_events']) ? (int) $result['pruned_events'] : 0
			),
			'pruned'  => $result,
		);
	}

	/**
	 * Delete old read notifications.
	 *
	 * @param int $days Retention window in days.
	 * @return array
	 */
	public function cleanup_notifications($days = 30) {
		$deleted = $this->notifications_repository->cleanup_old($days);

		return array(
			'success' => true,
			'message' => sprintf(__('Deleted %1$d read notifications older than %2$d days.', 'ai-post-scheduler'), (int) $deleted, (int) $days),
			'deleted' => (int) $deleted,
		);
	}

	/**
	 * Reset the AI circuit breaker and rate limiter.
	 *
	 * @return array
	 */
	public function reset_resilience() {
		if (!$this->resilience_service) {
			return array(
				'success' => false,
				'message' => __('Resilience service is unavailable.', 'ai-post-scheduler'),
			);
		}

		$reset = array();
		if (method_exists($this->resilience_service, 'reset_circuit_breaker')) {
			$this->resilience_service->reset_circuit_breaker();
			$reset[] = __('circuit breaker', 'ai-post-scheduler');
		}
		if (method_exists($this->resilience_service, 'reset_rate_limiter')) {
			$this->resilience_service->reset_rate_limiter();
			$reset[] = __('rate limiter', 'ai-post-scheduler');
		}

		return array(
			'success' => true,
			'message' => sprintf(__('Reset resilience state: %s.', 'ai-post-scheduler'), implode(', ', $reset)),
		);
	}

	/**
	 * Repair legacy/corrupted date-time values and backfill schedule next runs.
	 *
	 * @return array
	 */
	public function repair_datetime() {
		$summary = $this->date_time_db_repair->run();

		return array(
			'success' => true,
			'message' => sprintf(
				__('Schedule timing repair complete: %1$d columns converted, %2$d null values normalized, %3$d schedule, %4$d author, and %5$d source next-run values fixed.', 'ai-post-scheduler'),
				isset($summary['converted_columns']) ? (int) $summary['converted_columns'] : 0,
				isset($summary['normalized_null_values']) ? (int) $summary['normalized_null_values'] : 0,
				isset($summary['fixed_schedule_next_runs']) ? (int) $summary['fixed_schedule_next_runs'] : 0,
				isset($summary['fixed_author_next_runs']) ? (int) $summary['fixed_author_next_runs'] : 0,
				isset($summary['fixed_source_next_runs']) ? (int) $summary['fixed_source_next_runs'] : 0
			),
			'summary' => $summary,
		);
	}

	/**
	 * Run one or more safe maintenance operations in one pass.
	 *
	 * Cleanup steps run first, repairs next, scheduling after, and the cache
	 * rebuild last so rebuilt caches are not flushed by earlier steps. A
	 * failing step is reported but never aborts the remaining steps.
	 *
	 * @param array<string>|null $selected_tasks Optional selected task IDs.
	 * @return array
	 */
	public function refresh_system($selected_tasks = null) {
		$sequence = $this->get_selected_refresh_tasks($selected_tasks);

		if (empty($sequence)) {
			return array(
				'success' => false,
				'steps'   => array(),
				'message' => __('Select at least one maintenance task to run.', 'ai-post-scheduler'),
			);
		}

		$steps     = array();
		$succeeded = 0;
		$failed    = 0;

		foreach ($sequence as $step => $definition) {
			try {
				$result = call_user_func($definition['callback']);
			} catch (Throwable $t) {
				$result = array(
					'success' => false,
					'message' => $t->getMessage(),
				);
			}

			$success = !empty($result['success']);
			if ($success) {
				$succeeded++;
			} else {
				$failed++;
			}

			$steps[] = array(
				'step'    => $step,
				'label'   => $definition['label'],
				'success' => $success,
				'message' => isset($result['message']) ? (string) $result['message'] : '',
			);
		}

		AIPS_Logger::instance()->log('System refresh executed from Diagnostics.', 'info', array(
			'succeeded'      => $succeeded,
			'failed'         => $failed,
			'selected_tasks' => array_keys($sequence),
		));

		return array(
			'success'   => true,
			'steps'     => $steps,
			'succeeded' => $succeeded,
			'failed'    => $failed,
			'message'   => sprintf(
				__('System refresh complete: %1$d of %2$d operations succeeded.', 'ai-post-scheduler'),
				$succeeded,
				count($sequence)
			),
		);
	}
}
