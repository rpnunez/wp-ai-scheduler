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
 * Registers and handles AJAX actions for the System Status page. All
 * operation logic lives in AIPS_System_Diagnostics_Service; handlers here only
 * verify nonces/capabilities, sanitize input, and shape the JSON response.
 */
class AIPS_System_Status_Controller {
	/**
	 * @var AIPS_Resilience_Service|null
	 */
	private $resilience_service;

	/**
	 * @var AIPS_System_Diagnostics_Service
	 */
	private $diagnostics_service;

	/**
	 * @var AIPS_Container
	 */
	private $container;

	public function __construct() {
		$this->container = AIPS_Container::get_instance();

		$this->resilience_service = $this->container->has(AIPS_Resilience_Service::class)
			? $this->container->make(AIPS_Resilience_Service::class)
			: (class_exists('AIPS_Resilience_Service') ? new AIPS_Resilience_Service() : null);

		$this->diagnostics_service = $this->container->has(AIPS_System_Diagnostics_Service::class)
			? $this->container->make(AIPS_System_Diagnostics_Service::class)
			: new AIPS_System_Diagnostics_Service();

		add_action('wp_ajax_aips_reset_circuit_breaker', array($this, 'ajax_reset_circuit_breaker'));
		add_action('wp_ajax_aips_status_reschedule_missed_cron', array($this, 'ajax_reschedule_missed_cron'));
		add_action('wp_ajax_aips_status_retry_failed_slices', array($this, 'ajax_retry_failed_slices'));
		add_action('wp_ajax_aips_status_repair_campaign_data', array($this, 'ajax_repair_campaign_data'));
		add_action('wp_ajax_aips_status_clear_partial_generations', array($this, 'ajax_clear_partial_generations'));
		add_action('wp_ajax_aips_status_cleanup_stale_jobs_cache', array($this, 'ajax_cleanup_stale_jobs_cache'));
		add_action('wp_ajax_aips_rebuild_caches', array($this, 'ajax_rebuild_caches'));
		add_action('wp_ajax_aips_status_refresh_system', array($this, 'ajax_refresh_system'));
		add_action('wp_ajax_aips_status_cache_maintenance', array($this, 'ajax_cache_maintenance'));
		add_action('wp_ajax_aips_status_cleanup_notifications', array($this, 'ajax_cleanup_notifications'));
		add_action('wp_ajax_aips_status_reset_resilience', array($this, 'ajax_reset_resilience'));
		add_action('wp_ajax_aips_status_repair_datetime', array($this, 'ajax_repair_datetime'));
	}

	/**
	 * Verify the per-action nonce and capability, terminating on failure.
	 *
	 * @param string $action AJAX action name (doubles as nonce action).
	 * @return void
	 */
	private function verify_request($action) {
		if ( ! check_ajax_referer($action, 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
	}

	/**
	 * AJAX: Reset the AI service circuit breaker.
	 *
	 * @return void
	 */
	public function ajax_reset_circuit_breaker() {
		$this->verify_request('aips_reset_circuit_breaker');

		if ($this->resilience_service && method_exists($this->resilience_service, 'reset_circuit_breaker')) {
			$this->resilience_service->reset_circuit_breaker();
		}

		AIPS_Ajax_Response::success(array('reset' => true));
	}

	public function ajax_reschedule_missed_cron() {
		$this->verify_request('aips_status_reschedule_missed_cron');

		AIPS_Ajax_Response::success($this->diagnostics_service->reschedule_missed_cron());
	}

	public function ajax_retry_failed_slices() {
		$this->verify_request('aips_status_retry_failed_slices');

		AIPS_Ajax_Response::success($this->diagnostics_service->retry_failed_slices());
	}

	public function ajax_repair_campaign_data() {
		$this->verify_request('aips_status_repair_campaign_data');

		AIPS_Ajax_Response::success($this->diagnostics_service->repair_campaign_data());
	}

	public function ajax_clear_partial_generations() {
		$this->verify_request('aips_status_clear_partial_generations');

		AIPS_Ajax_Response::success($this->diagnostics_service->clear_partial_generations());
	}

	public function ajax_cleanup_stale_jobs_cache() {
		$this->verify_request('aips_status_cleanup_stale_jobs_cache');

		AIPS_Ajax_Response::success($this->diagnostics_service->cleanup_stale_jobs_cache());
	}

	public function ajax_rebuild_caches() {
		$this->verify_request('aips_rebuild_caches');

		$subsystem = isset($_POST['subsystem']) ? sanitize_key(wp_unslash($_POST['subsystem'])) : 'all';

		AIPS_Ajax_Response::success($this->diagnostics_service->rebuild_caches($subsystem));
	}

	/**
	 * AJAX: Run the selected safe maintenance operations in one request.
	 *
	 * Responds success even when individual steps fail; the payload carries
	 * per-step results so the UI can surface partial failures.
	 *
	 * @return void
	 */
	public function ajax_refresh_system() {
		$this->verify_request('aips_status_refresh_system');

		$tasks = null;
		if (isset($_POST['tasks'])) {
			$tasks = wp_unslash($_POST['tasks']);
			$tasks = is_array($tasks) ? $tasks : array();
		}

		$result = $this->diagnostics_service->refresh_system($tasks);
		if (isset($result['success']) && false === $result['success']) {
			AIPS_Ajax_Response::error($result['message']);
		}

		AIPS_Ajax_Response::success($result);
	}

	public function ajax_cache_maintenance() {
		$this->verify_request('aips_status_cache_maintenance');

		$result = $this->diagnostics_service->run_cache_maintenance();
		if (empty($result['success'])) {
			AIPS_Ajax_Response::error($result['message']);
		}

		AIPS_Ajax_Response::success($result);
	}

	public function ajax_cleanup_notifications() {
		$this->verify_request('aips_status_cleanup_notifications');

		AIPS_Ajax_Response::success($this->diagnostics_service->cleanup_notifications(30));
	}

	public function ajax_reset_resilience() {
		$this->verify_request('aips_status_reset_resilience');

		$result = $this->diagnostics_service->reset_resilience();
		if (empty($result['success'])) {
			AIPS_Ajax_Response::error($result['message']);
		}

		AIPS_Ajax_Response::success($result);
	}

	public function ajax_repair_datetime() {
		$this->verify_request('aips_status_repair_datetime');

		AIPS_Ajax_Response::success($this->diagnostics_service->repair_datetime());
	}

}
