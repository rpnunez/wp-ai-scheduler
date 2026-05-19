<?php
/**
 * System Status Controller
 *
 * Handles AJAX actions specific to the System Status admin page.
 *
 * @package AI_Post_Scheduler
 * @since   2.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}


/**
 * Class AIPS_System_Status_Controller
 *
 * Registers and handles AJAX actions for the System Status page.
 */
class AIPS_System_Status_Controller {
	/**
	 * @var AIPS_Resilience_Service|null
	 */
	private $resilience_service;

	/**
	 * @var AIPS_History_Repository
	 */
	private $history_repository;

	/**
	 * @var AIPS_Bulk_Batch_Job_Store|null
	 */
	private $bulk_batch_job_store;

	/**
	 * @var AIPS_Container
	 */
	private $container;

	public function __construct() {
		$this->container = AIPS_Container::get_instance();

		$this->resilience_service = $this->container->makeIfExists(
			AIPS_Resilience_Service::class,
			null
		);

		$this->history_repository = $this->container->makeIfExists(
			AIPS_History_Repository::class,
			AIPS_History_Repository::class
		);

		$this->bulk_batch_job_store = $this->container->makeIfExists(
			AIPS_Bulk_Batch_Job_Store::class,
			function() {
				return class_exists('AIPS_Bulk_Batch_Job_Store') ? new AIPS_Bulk_Batch_Job_Store() : null;
			}
		);

		add_action('wp_ajax_aips_reset_circuit_breaker', array($this, 'ajax_reset_circuit_breaker'));
		add_action('wp_ajax_aips_status_reschedule_missed_cron', array($this, 'ajax_reschedule_missed_cron'));
		add_action('wp_ajax_aips_status_retry_failed_slices', array($this, 'ajax_retry_failed_slices'));
		add_action('wp_ajax_aips_status_clear_partial_generations', array($this, 'ajax_clear_partial_generations'));
		add_action('wp_ajax_aips_status_cleanup_stale_jobs_cache', array($this, 'ajax_cleanup_stale_jobs_cache'));
		add_action('wp_ajax_aips_rebuild_caches', array($this, 'ajax_rebuild_caches'));
	}

	/**
	 * AJAX: Reset the AI service circuit breaker.
	 *
	 * @return void
	 */
	public function ajax_reset_circuit_breaker() {
		if ( ! check_ajax_referer('aips_reset_circuit_breaker', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		if ($this->resilience_service && method_exists($this->resilience_service, 'reset_circuit_breaker')) {
			$this->resilience_service->reset_circuit_breaker();
		}

		AIPS_Ajax_Response::success(array('reset' => true));
	}

	public function ajax_reschedule_missed_cron() {
		if ( ! check_ajax_referer('aips_status_reschedule_missed_cron', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$cron_events = AI_Post_Scheduler::get_cron_events();
		$rescheduled = 0;
		$base_timestamp = AIPS_DateTime::now()->timestamp() + MINUTE_IN_SECONDS;
		foreach ($cron_events as $hook => $config) {
			$schedule = isset($config['schedule']) ? $config['schedule'] : 'hourly';
			wp_unschedule_hook($hook);
			if (wp_schedule_event($base_timestamp, $schedule, $hook) !== false) {
				$rescheduled++;
			}
		}
		AIPS_Ajax_Response::success(array('message' => sprintf(__('Flushed and rescheduled %d cron events.', 'ai-post-scheduler'), $rescheduled)));
	}

	public function ajax_retry_failed_slices() {
		if ( ! check_ajax_referer('aips_status_retry_failed_slices', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		// Immediately schedule retry hooks for both topics and posts.
		// This bypasses the 5-minute delay and processes failed slices now.
		$scheduled = 0;
		$now = AIPS_DateTime::now()->timestamp();
		if (wp_schedule_single_event($now, 'aips_retry_failed_author_slices_topics')) {
			$scheduled++;
		}
		if (wp_schedule_single_event($now, 'aips_retry_failed_author_slices_posts')) {
			$scheduled++;
		}
		AIPS_Ajax_Response::success(array('message' => sprintf(__('Scheduled %d failed slice retry hooks.', 'ai-post-scheduler'), $scheduled)));
	}

	public function ajax_clear_partial_generations() {
		if ( ! check_ajax_referer('aips_status_clear_partial_generations', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$partials = $this->history_repository->get_partial_generations(array('per_page' => 10));
		$reconciled = 0;
		if (!empty($partials['items'])) {
			foreach ($partials['items'] as $item) {
				if (!empty($item->post_id)) {
					do_action('aips_post_components_updated', (int) $item->post_id, array(), array());
					$reconciled++;
				}
			}
		}
		AIPS_Ajax_Response::success(array('message' => sprintf(__('Reconciled %d partial generations.', 'ai-post-scheduler'), $reconciled)));
	}

	public function ajax_cleanup_stale_jobs_cache() {
		if ( ! check_ajax_referer('aips_status_cleanup_stale_jobs_cache', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$deleted = 0;
		if ($this->bulk_batch_job_store) {
			$deleted = $this->bulk_batch_job_store->cleanup_old_jobs();
		}
		$cache_flushed = false;
		if (class_exists('AIPS_Cache_Factory')) {
			$cache = AIPS_Cache_Factory::make();
			$cache_flushed = $cache ? (bool) $cache->flush() : false;
		}
		AIPS_Ajax_Response::success(array('message' => sprintf(__('Cleaned %1$d stale jobs. Cache flushed: %2$s.', 'ai-post-scheduler'), $deleted, $cache_flushed ? __('yes', 'ai-post-scheduler') : __('no', 'ai-post-scheduler'))));
	}

	public function ajax_rebuild_caches() {
		if ( ! check_ajax_referer('aips_rebuild_caches', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$subsystem = isset($_POST['subsystem']) ? sanitize_key(wp_unslash($_POST['subsystem'])) : 'all';
		$subsystems = AIPS_Cache_Policy::get_subsystems();
		$allowed_subsystems = array_keys($subsystems);
		if ('all' !== $subsystem && !in_array($subsystem, $allowed_subsystems, true)) {
			$subsystem = 'all';
		}

		$affected = AIPS_Cache_Invalidation_Bus::rebuild($subsystem);
		$subsystem_label = ('all' === $subsystem) ? __('All subsystems', 'ai-post-scheduler') : (isset($subsystems[$subsystem]['label']) ? (string) $subsystems[$subsystem]['label'] : $subsystem);
		$affected_display = !empty($affected) ? implode(', ', $affected) : __('none', 'ai-post-scheduler');

		AIPS_Logger::instance()->log('Cache rebuild requested from admin tool.', 'info', array('subsystem' => $subsystem, 'affected_caches' => $affected));
		AIPS_Ajax_Response::success(array(
			'message' => sprintf(__('Rebuilt caches for %1$s. Affected caches: %2$s', 'ai-post-scheduler'), $subsystem_label, $affected_display),
			'subsystem' => $subsystem,
			'affected' => $affected,
		));
	}

}
