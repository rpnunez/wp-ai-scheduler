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
	 * Execute a callable and convert thrown failures into a logged WP_Error.
	 *
	 * @param callable $callable         Callback to execute.
	 * @param string   $fallback_message Message returned when the callback throws.
	 *
	 * @return mixed|WP_Error
	 */
	public static function safe_call($callable, $fallback_message) {
		$message = self::normalize_fallback_message($fallback_message);

		if (is_callable($callable) === false) {
			self::log_safe_call_failure(
				new InvalidArgumentException('AIPS_Error_Handler::safe_call received a non-callable value.'),
				$message
			);

			return new WP_Error('invalid_callback', $message);
		}

		try {
			return call_user_func($callable);
		} catch (Throwable $throwable) {
			self::log_safe_call_failure($throwable, $message);

			return new WP_Error(
				'safe_call_failed',
				$message,
				array(
					'exception_class'   => get_class($throwable),
					'exception_code'    => $throwable->getCode(),
					'exception_message' => $throwable->getMessage(),
				)
			);
		}
	}

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

	/**
	 * Normalize safe_call fallback text.
	 *
	 * @param string $fallback_message Requested fallback message.
	 *
	 * @return string
	 */
	private static function normalize_fallback_message($fallback_message) {
		if (is_string($fallback_message)) {
			$fallback_message = trim($fallback_message);
		}

		if (!empty($fallback_message)) {
			return $fallback_message;
		}

		return __('An unexpected error occurred while processing the request.', 'ai-post-scheduler');
	}

	/**
	 * Log a safe_call failure through the shared plugin logger.
	 *
	 * @param Throwable $throwable Throwable that was caught.
	 * @param string    $message   Fallback error message returned to the caller.
	 *
	 * @return void
	 */
	private static function log_safe_call_failure(Throwable $throwable, $message) {
		AIPS_Logger::instance()->error(
			'AIPS safe_call caught an exception: ' . $throwable->getMessage(),
			array(
				'exception_class' => get_class($throwable),
				'exception_code'  => $throwable->getCode(),
				'fallback'        => $message,
				'file'            => $throwable->getFile(),
				'line'            => $throwable->getLine(),
			)
		);
	}
}
