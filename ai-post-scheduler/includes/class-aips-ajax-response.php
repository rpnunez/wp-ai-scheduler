<?php
/**
 * AJAX Response
 *
 * Standardized response helper for all AJAX endpoints.
 * Ensures consistent JSON shape across all AJAX handlers.
 *
 * @package AI_Post_Scheduler
 * @since 2.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Ajax_Response
 *
 * Static helper methods for sending consistent AJAX responses.
 * All controllers should use these methods instead of calling
 * wp_send_json_success() and wp_send_json_error() directly.
 */
class AIPS_Ajax_Response {

	/**
	 * Send a successful AJAX response.
	 *
	 * Response shape: { success: true, data: { message: '...', ...data } }
	 *
	 * @param array  $data    Optional. Additional data to include in the response. Default empty array.
	 * @param string $message Optional. Success message. Default empty string.
	 * @return void Exits execution after sending JSON response.
	 */
	public static function success($data = array(), $message = '') {
		$response = array();

		// Add message first if provided
		if (!empty($message)) {
			$response['message'] = $message;
		}

		// Merge additional data
		if (!empty($data)) {
			$response = array_merge($response, $data);
		}

		wp_send_json_success($response);
	}

	/**
	 * Send an error AJAX response.
	 *
	 * Response shape: { success: false, data: { message: '...', code: '...', ...data } }
	 *
	 * @param string|array $message     The error message to display, or an array with 'message' key for backward compatibility.
	 * @param string       $code        Optional. Error code for programmatic handling. Default 'error'.
	 * @param int          $http_status Optional. HTTP status code. Default 200 (WordPress AJAX convention).
	 * @param array        $data        Optional. Additional error context data. Default empty array.
	 * @return void Exits execution after sending JSON response.
	 */
	public static function error($message, $code = 'error', $http_status = 200, $data = array()) {
		// Backward compatibility: accept array('message' => '...', ...) format
		if (is_array($message)) {
			$array_data = $message;
			$message = isset($array_data['message']) ? $array_data['message'] : 'An error occurred.';
			unset($array_data['message']);

			// Extract code if provided in array
			if (isset($array_data['code'])) {
				$code = $array_data['code'];
				unset($array_data['code']);
			}

			// Merge remaining array data
			$data = array_merge($array_data, $data);
		}

		$response = array(
			'message' => $message,
			'code'    => $code,
		);

		// Merge additional error data
		if (!empty($data)) {
			$response = array_merge($response, $data);
		}

		wp_send_json_error($response, $http_status);
	}

	/**
	 * Send a permission denied error response.
	 *
	 * Convenience wrapper for the common "Permission denied" error.
	 *
	 * @return void Exits execution after sending JSON response.
	 */
	public static function permission_denied() {
		self::error(
			__('Permission denied.', 'ai-post-scheduler'),
			'permission_denied',
			403
		);
	}

	/**
	 * Send an invalid request error response.
	 *
	 * Convenience wrapper for validation failures.
	 *
	 * @param string $message Optional. Custom validation message. Default generic message.
	 * @return void Exits execution after sending JSON response.
	 */
	public static function invalid_request($message = '') {
		if (empty($message)) {
			$message = __('Invalid request.', 'ai-post-scheduler');
		}

		self::error($message, 'invalid_request', 400);
	}

	/**
	 * Send a "not found" error response.
	 *
	 * Convenience wrapper for resource not found errors.
	 *
	 * @param string $resource Optional. Name of the resource (e.g., 'Template', 'Schedule'). Default generic message.
	 * @return void Exits execution after sending JSON response.
	 */
	public static function not_found($resource = '') {
		if (empty($resource)) {
			$message = __('Resource not found.', 'ai-post-scheduler');
		} else {
			/* translators: %s: Resource name (e.g., Template, Schedule) */
			$message = sprintf(__('%s not found.', 'ai-post-scheduler'), $resource);
		}

		self::error($message, 'not_found', 404);
	}
}
