<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * AIPS_Error_Handler
 *
 * Centralized helpers for constructing and logging errors in the
 * AI Post Scheduler plugin.
 */
class AIPS_Error_Handler {

	/**
	 * Centralized helper for text-generation errors.
	 *
	 * Creates a WP_Error and logs it via the provided log callback. Any
	 * resilience/circuit-breaker bookkeeping should be handled by the caller
	 * to keep this helper decoupled from specific services.
	 *
	 * @param string        $code         Error code.
	 * @param string        $message      Error message.
	 * @param string        $prompt       Original prompt sent to AI.
	 * @param array         $options      Options used for the AI call.
	 * @param callable|null $log_callback Callback compatible with AIPS_AI_Service::log_call.
	 *
	 * @return WP_Error
	 */
	public static function make_text_error($code, $message, $prompt, $options, $log_callback = null) {
		$error = new WP_Error($code, $message);

		if (is_callable($log_callback)) {
			call_user_func($log_callback, 'text', $prompt, null, $options, $error->get_error_message());
		}

		return $error;
	}
}
