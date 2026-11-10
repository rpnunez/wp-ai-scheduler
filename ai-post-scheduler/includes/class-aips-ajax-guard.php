<?php
/**
 * AJAX Guard Helper
 *
 * Provides shared authorization and nonce verification helpers for AJAX controllers.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Ajax_Guard
 *
 * Standardizes security verification across AJAX controllers.
 */
class AIPS_Ajax_Guard {

	/**
	 * Verify nonce and capability for an administrative AJAX request.
	 *
	 * Halts request execution with a JSON error if validation fails.
	 *
	 * @param string $capability Capability required (default 'manage_options').
	 * @param string $action     Nonce action name (default 'aips_ajax_nonce').
	 * @param string $query_arg  Request parameter holding nonce (default 'nonce').
	 * @return bool True if valid.
	 */
	public static function check_admin($capability = 'manage_options', $action = 'aips_ajax_nonce', $query_arg = 'nonce') {
		if (!check_ajax_referer($action, $query_arg, false)) {
			AIPS_Ajax_Response::error(
				__('Invalid nonce.', 'ai-post-scheduler'),
				'security_check_failed',
				403
			);
		}

		if (!current_user_can($capability)) {
			AIPS_Ajax_Response::permission_denied();
		}

		return true;
	}

	/**
	 * Non-terminating verification check.
	 *
	 * @param string $capability Capability required.
	 * @param string $action Nonce action name.
	 * @param string $query_arg Request parameter holding nonce.
	 * @return true|WP_Error True if valid, WP_Error if validation fails.
	 */
	public static function verify($capability = 'manage_options', $action = 'aips_ajax_nonce', $query_arg = 'nonce') {
		if (!check_ajax_referer($action, $query_arg, false)) {
			return new WP_Error('security_check_failed', __('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can($capability)) {
			return new WP_Error('permission_denied', __('Permission denied.', 'ai-post-scheduler'));
		}

		return true;
	}
}
