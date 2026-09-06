<?php
/**
 * AJAX Guard Trait
 *
 * Provides a standardized request guard for AJAX controllers and handlers.
 * Enforces consistent nonce validation and capability checks with canonical
 * error response shapes.
 *
 * @package AI_Post_Scheduler
 * @since   2.9.2
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Trait AIPS_Ajax_Guard
 */
trait AIPS_Ajax_Guard {

	/**
	 * Verify nonce and user capability for the current AJAX request.
	 *
	 * Terminates execution with a canonical JSON error response on failure:
	 * - Nonce failure: emits AIPS_Ajax_Response::error('Invalid nonce.')
	 * - Capability failure: emits AIPS_Ajax_Response::permission_denied()
	 *
	 * @param string $nonce      Nonce action name. Default 'aips_ajax_nonce'.
	 * @param string $capability Required WordPress capability. Default 'manage_options'.
	 * @param string $query_arg  Optional. Nonce query argument key in $_REQUEST. Default 'nonce'.
	 * @return void
	 */
	protected function verify_request(
		string $nonce = 'aips_ajax_nonce',
		string $capability = 'manage_options',
		string $query_arg = 'nonce'
	): void {
		if (!check_ajax_referer($nonce, $query_arg, false)) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!empty($capability) && !current_user_can($capability)) {
			AIPS_Ajax_Response::permission_denied();
		}
	}
}
