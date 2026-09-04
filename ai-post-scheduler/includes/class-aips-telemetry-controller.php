<?php
/**
 * Telemetry Controller
 *
 * Handles Telemetry admin page rendering and AJAX data loading.
 *
 * The AJAX handlers here are kept for the dual-run migration window; the
 * canonical API is now AIPS_Telemetry_Rest_Controller (GET /aips/v1/telemetry).
 *
 * @package AI_Post_Scheduler
 * @since   2.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Telemetry_Controller
 */
class AIPS_Telemetry_Controller {

	/**
	 * @var AIPS_Telemetry_Report_Service
	 */
	private $service;

	/**
	 * Resolve dependencies and register AJAX handlers.
	 */
	public function __construct() {
		$this->service = new AIPS_Telemetry_Report_Service();

		add_action('wp_ajax_aips_get_telemetry', array($this, 'ajax_get_telemetry'));
		add_action('wp_ajax_aips_get_telemetry_details', array($this, 'ajax_get_telemetry_details'));
	}

	/**
	 * Render the Telemetry admin page.
	 *
	 * @return void
	 */
	public function render_page($embedded = false) {
		if (!$this->service->is_enabled()) {
			wp_die(esc_html__('Telemetry is currently disabled.', 'ai-post-scheduler'));
		}

		$embedded       = (bool) $embedded;
		$end_date       = $this->service->default_end_date();
		$start_date     = $this->service->default_start_date();
		$per_page       = AIPS_Telemetry_Report_Service::DEFAULT_PER_PAGE;
		$filter_options = $this->service->get_filter_options();

		include AIPS_PLUGIN_DIR . 'templates/admin/telemetry.php';
	}

	/**
	 * Return telemetry charts and paginated rows for the selected date range.
	 *
	 * @deprecated 3.7.0 Use GET /aips/v1/telemetry.
	 * @return void
	 */
	public function ajax_get_telemetry() {
		if ( ! check_ajax_referer('aips_get_telemetry', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		if (!$this->service->is_enabled()) {
			AIPS_Ajax_Response::error(__('Telemetry is disabled.', 'ai-post-scheduler'));
		}

		$report = $this->service->get_report(
			isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '',
			isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '',
			$_POST,
			isset($_POST['per_page']) ? wp_unslash($_POST['per_page']) : AIPS_Telemetry_Report_Service::DEFAULT_PER_PAGE,
			isset($_POST['page']) ? wp_unslash($_POST['page']) : 1
		);

		AIPS_Ajax_Response::success($report);
	}

	/**
	 * Return a single telemetry row and decoded payload details.
	 *
	 * @deprecated 3.7.0 Use GET /aips/v1/telemetry/{id}.
	 * @return void
	 */
	public function ajax_get_telemetry_details() {
		if ( ! check_ajax_referer('aips_get_telemetry_details', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		if (!$this->service->is_enabled()) {
			AIPS_Ajax_Response::error(__('Telemetry is disabled.', 'ai-post-scheduler'));
		}

		$row_id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;
		if ($row_id < 1) {
			AIPS_Ajax_Response::invalid_request(__('A valid telemetry row ID is required.', 'ai-post-scheduler'));
		}

		$details = $this->service->get_details($row_id);
		if (null === $details) {
			AIPS_Ajax_Response::not_found(__('Telemetry row', 'ai-post-scheduler'));
		}

		AIPS_Ajax_Response::success($details);
	}
}
