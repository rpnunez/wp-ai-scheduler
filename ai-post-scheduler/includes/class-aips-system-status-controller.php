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

		$page     = max(1, isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1);
		$per_page = 10;

		$repo        = new AIPS_Telemetry_Repository();
		$total       = $repo->count();
		$total_pages = max(1, (int) ceil($total / max(1, $per_page)));
		$page        = min($page, $total_pages);
		$offset      = ($page - 1) * $per_page;
		$rows        = $repo->get_page($per_page, $offset);

		AIPS_Ajax_Response::success(array(
			'rows'        => $rows,
			'total'       => $total,
			'per_page'    => $per_page,
			'total_pages' => $total_pages,
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
