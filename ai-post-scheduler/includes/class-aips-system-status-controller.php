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
 * Registers and handles AJAX actions for the System Status page,
 * including the telemetry table pagination endpoint.
 */
class AIPS_System_Status_Controller {

	public function __construct() {
		add_action('wp_ajax_aips_get_telemetry',         array($this, 'ajax_get_telemetry'));
		add_action('wp_ajax_aips_reset_circuit_breaker', array($this, 'ajax_reset_circuit_breaker'));
	}

	/**
	 * AJAX: Return a paginated page of telemetry rows.
	 *
	 * @return void
	 */
	public function ajax_get_telemetry() {
		check_ajax_referer('aips_get_telemetry', 'nonce');

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$page     = max(1, (int) (isset($_POST['page']) ? $_POST['page'] : 1));
		$per_page = 10;
		$offset   = ($page - 1) * $per_page;

		$repo  = new AIPS_Telemetry_Repository();
		$rows  = $repo->get_page($per_page, $offset);
		$total = $repo->count();

		AIPS_Ajax_Response::success(array(
			'rows'        => $rows,
			'total'       => $total,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil($total / max(1, $per_page)),
			'page'        => $page,
		));
	}

	/**
	 * AJAX: Reset the AI service circuit breaker.
	 *
	 * @return void
	 */
	public function ajax_reset_circuit_breaker() {
		check_ajax_referer('aips_reset_circuit_breaker', 'nonce');

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		if (class_exists('AIPS_Resilience_Service')) {
			$service = new AIPS_Resilience_Service();
			if (method_exists($service, 'reset_circuit_breaker')) {
				$service->reset_circuit_breaker();
			}
		}

		AIPS_Ajax_Response::success(array('reset' => true));
	}
}
