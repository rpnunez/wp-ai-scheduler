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
	 * @param string|array|WP_Error $message     The error message to display, a WP_Error to normalize, or an array with 'message' key for backward compatibility.
	 * @param string                $code        Optional. Error code for programmatic handling, used only when $message is not a WP_Error. Default 'error'.
	 * @param int                   $http_status Optional. HTTP status code. Default 200 (WordPress AJAX convention).
	 * @param array                 $data        Optional. Additional error context data, merged under (and overridden by) data carried on a WP_Error. Default empty array.
	 * @return void Exits execution after sending JSON response.
	 */
	public static function error($message, $code = 'error', $http_status = 200, $data = array()) {
		// Accept a WP_Error directly: use its first error's code/message, and
		// merge any attached error data (e.g. new WP_Error($code, $msg, array('http_status' => 404))).
		if (is_wp_error($message)) {
			$wp_error   = $message;
			$code       = $wp_error->get_error_code() ?: $code;
			$error_data = $wp_error->get_error_data($code);
			$message    = $wp_error->get_error_message() ?: __('An error occurred.', 'ai-post-scheduler');

			if (is_array($error_data)) {
				if (isset($error_data['http_status'])) {
					$http_status = (int) $error_data['http_status'];
					unset($error_data['http_status']);
				}
				$data = array_merge($data, $error_data);
			}
		} elseif (is_array($message)) {
			$array_data = $message;
			$message = isset($array_data['message']) ? $array_data['message'] : __('An error occurred.', 'ai-post-scheduler');
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
