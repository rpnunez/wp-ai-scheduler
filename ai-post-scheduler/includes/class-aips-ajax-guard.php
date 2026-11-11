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

	const PROFILE_DEFAULT = 'default';
	const PROFILE_SCHEDULE_STATUS = 'schedule_status';
	const PROFILE_LINK_REPORT = 'link_report';
	const PROFILE_CONTENT_INDEXER = 'content_indexer';

	/**
	 * Verify nonce and capability for an administrative AJAX request.
	 *
	 * Failures are HTTP 200 JSON errors. Admin screens only handle the jQuery
	 * success callback, so a 403 leaves them stuck on "Loading…". Error codes
	 * match the pre-decomposition controllers for each endpoint family.
	 *
	 * @param string       $capability Capability required (default 'manage_options').
	 * @param string       $action     Nonce action name (default 'aips_ajax_nonce').
	 * @param string       $query_arg  Request parameter holding nonce (default 'nonce').
	 * @param string|array $profile    Response profile name, or an override array
	 *                                 with nonce_message, nonce_code, capability_message,
	 *                                 and capability_code.
	 * @return bool True if valid.
	 */
	public static function check_admin($capability = 'manage_options', $action = 'aips_ajax_nonce', $query_arg = 'nonce', $profile = self::PROFILE_DEFAULT) {
		$response = self::response_profile($profile);

		if (!check_ajax_referer($action, $query_arg, false)) {
			AIPS_Ajax_Response::error(
				$response['nonce_message'],
				$response['nonce_code'],
				200
			);
		}

		if (!current_user_can($capability)) {
			AIPS_Ajax_Response::error(
				$response['capability_message'],
				$response['capability_code'],
				200
			);
		}

		return true;
	}

	/**
	 * Nonce and capability error payload for an endpoint family.
	 *
	 * @param string|array $profile Profile name or override array.
	 * @return array{nonce_message:string,nonce_code:string,capability_message:string,capability_code:string}
	 */
	private static function response_profile($profile) {
		$profiles = array(
			self::PROFILE_DEFAULT => array(
				'nonce_message' => __('Invalid nonce.', 'ai-post-scheduler'),
				'nonce_code' => 'error',
				'capability_message' => __('Permission denied.', 'ai-post-scheduler'),
				'capability_code' => 'permission_denied',
			),
			self::PROFILE_SCHEDULE_STATUS => array(
				'nonce_message' => __('Invalid nonce.', 'ai-post-scheduler'),
				'nonce_code' => 'error',
				'capability_message' => __('Unauthorized.', 'ai-post-scheduler'),
				'capability_code' => 'error',
			),
			self::PROFILE_LINK_REPORT => array(
				'nonce_message' => __('Security check failed. Please refresh the page.', 'ai-post-scheduler'),
				'nonce_code' => 'invalid_nonce',
				'capability_message' => __('Permission denied.', 'ai-post-scheduler'),
				'capability_code' => 'permission_denied',
			),
			self::PROFILE_CONTENT_INDEXER => array(
				'nonce_message' => __('Security check failed. Please refresh the page.', 'ai-post-scheduler'),
				'nonce_code' => 'invalid_nonce',
				'capability_message' => __('Permission denied.', 'ai-post-scheduler'),
				'capability_code' => 'forbidden',
			),
		);

		$resolved = $profiles[self::PROFILE_DEFAULT];
		if (is_array($profile)) {
			$resolved = array_merge($resolved, $profile);
		} elseif (is_string($profile) && isset($profiles[$profile])) {
			$resolved = $profiles[$profile];
		}

		return $resolved;
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
