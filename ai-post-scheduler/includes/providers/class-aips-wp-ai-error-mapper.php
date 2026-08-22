<?php
/**
 * WordPress AI Error Mapper
 *
 * Translates WordPress core AI Client WP_Error objects and exceptions into
 * normalized provider exceptions and error codes.
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_WP_AI_Error_Mapper {

	/**
	 * Convert a WP_Error result into an exception carrying its code and message.
	 *
	 * @param WP_Error $error Error returned by the AI Client.
	 * @return void
	 * @throws Exception Always.
	 */
	public function throw_from_wp_error(WP_Error $error): void {
		$code    = $error->get_error_code();
		$message = $error->get_error_message();

		throw new Exception($code ? $code . ': ' . $message : $message);
	}

	/**
	 * Extract canonical provider error code from an error message.
	 *
	 * @param string $message Error message text.
	 * @return string Error code.
	 */
	public function extract_error_code(string $message): string {
		if (preg_match('/^([a-z0-9_\-]+):\s/i', $message, $matches)) {
			return $matches[1];
		}

		return AIPS_Resilience_Service::extract_error_code_from_message($message);
	}

	/**
	 * Normalize a Throwable into an Exception instance.
	 *
	 * @param Throwable $e Thrown error or exception.
	 * @return Exception
	 */
	public function to_exception(Throwable $e): Exception {
		return $e instanceof Exception ? $e : new Exception($e->getMessage(), 0, $e);
	}
}
