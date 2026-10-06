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
		add_action('wp_ajax_aips_status_prune_telemetry', array($this, 'ajax_prune_telemetry'));
		add_action('wp_ajax_aips_status_purge_telemetry', array($this, 'ajax_purge_telemetry'));
		add_action('wp_ajax_aips_status_prune_history_logs', array($this, 'ajax_prune_history_logs'));
		add_action('wp_ajax_aips_status_clean_orphaned_embeddings', array($this, 'ajax_clean_orphaned_embeddings'));
		add_action('wp_ajax_aips_status_optimize_table', array($this, 'ajax_optimize_table'));
		add_action('wp_ajax_aips_status_get_tables', array($this, 'ajax_get_tables'));
	}

	/**
	 * Verify the per-action nonce and capability, terminating on failure.
	 *
	 * @param string $action AJAX action name (doubles as nonce action).
	 * @return void
	 */
	private function verify_request($action) {
		$valid = check_ajax_referer($action, 'nonce', false) || check_ajax_referer('aips_ajax_nonce', 'nonce', false);
		if (!$valid) {
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

	public function ajax_prune_telemetry() {
		$this->verify_request('aips_status_prune_telemetry');

		$result = $this->diagnostics_service->prune_telemetry();
		AIPS_Ajax_Response::success($result);
	}

	public function ajax_purge_telemetry() {
		$this->verify_request('aips_status_purge_telemetry');

		$result = $this->diagnostics_service->purge_all_telemetry();
		if (!empty($result['success'])) {
			AIPS_Ajax_Response::success($result);
		} else {
			AIPS_Ajax_Response::error(isset($result['message']) ? $result['message'] : __('Failed to purge telemetry.', 'ai-post-scheduler'));
		}
	}

	public function ajax_prune_history_logs() {
		$this->verify_request('aips_status_prune_history_logs');

		$result = $this->diagnostics_service->prune_history_logs();
		AIPS_Ajax_Response::success($result);
	}

	public function ajax_clean_orphaned_embeddings() {
		$this->verify_request('aips_status_clean_orphaned_embeddings');

		$result = $this->diagnostics_service->clean_orphaned_embeddings();
		AIPS_Ajax_Response::success($result);
	}

	public function ajax_optimize_table() {
		$this->verify_request('aips_status_optimize_table');

		$table_name = isset($_POST['table']) ? sanitize_text_field(wp_unslash($_POST['table'])) : '';
		if (empty($table_name)) {
			AIPS_Ajax_Response::error(__('Missing table name.', 'ai-post-scheduler'));
		}

		$result = $this->diagnostics_service->optimize_table($table_name);
		if (!empty($result['success'])) {
			AIPS_Ajax_Response::success($result);
		} else {
			AIPS_Ajax_Response::error($result['message']);
		}
	}

	public function ajax_get_tables() {
		$this->verify_request('aips_status_get_tables');

		$tables       = $this->diagnostics_service->get_tables_status();
		$tot_records  = 0;
		$tot_data     = 0;
		$tot_index    = 0;
		$tot_overhead = 0;
		foreach ($tables as $t) {
			$tot_records  += isset($t['records']) ? (int) $t['records'] : 0;
			$tot_data     += isset($t['data_size']) ? (int) $t['data_size'] : 0;
			$tot_index    += isset($t['index_size']) ? (int) $t['index_size'] : 0;
			$tot_overhead += isset($t['overhead']) ? (int) $t['overhead'] : 0;
		}

		AIPS_Ajax_Response::success(array(
			'tables' => $tables,
			'totals' => array(
				'records'            => $tot_records,
				'formatted_records'  => number_format_i18n($tot_records),
				'data_size'          => $tot_data,
				'formatted_data'     => size_format($tot_data, 2),
				'index_size'         => $tot_index,
				'formatted_index'    => size_format($tot_index, 2),
				'overhead'           => $tot_overhead,
				'formatted_overhead' => size_format($tot_overhead, 2),
			),
		));
	}
}
