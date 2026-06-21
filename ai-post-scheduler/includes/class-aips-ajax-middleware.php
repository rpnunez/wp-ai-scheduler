<?php
/**
 * AJAX Middleware
 *
 * Centralised filter callbacks that enrich AJAX error responses before they
 * are sent to the client.  Registered once during the AJAX boot phase so every
 * controller automatically benefits without bespoke error-handling logic.
 *
 * @package AI_Post_Scheduler
 * @since 2.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Ajax_Middleware
 *
 * Hooks into the 'aips_ajax_error_response' filter (fired by
 * AIPS_Ajax_Response::error()) to enrich resilience-layer errors with
 * user-friendly messages and machine-readable retry metadata.
 */
class AIPS_Ajax_Middleware {

	/**
	 * Register all middleware filter callbacks.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter('aips_ajax_error_response', array(__CLASS__, 'enrich_resilience_errors'), 10, 2);
	}

	/**
	 * Enrich rate-limit and circuit-breaker error responses.
	 *
	 * Rewrites the user-facing message and attaches a `retry_after` field
	 * (seconds) so the JS layer can display accurate retry guidance without
	 * any per-handler logic.
	 *
	 * @param array  $response The outgoing error response array.
	 * @param string $code     The WP_Error / AIPS error code.
	 * @return array Modified response array.
	 */
	public static function enrich_resilience_errors( $response, $code ) {
		if ( $code === 'rate_limit_exceeded' ) {
			$service     = new AIPS_Resilience_Service();
			$retry_after = $service->get_rate_limit_retry_after();

			$response['retry_after'] = $retry_after;
			$response['message']     = self::format_rate_limit_message( $retry_after );
		}

		return $response;
	}

	/**
	 * Build a localised, human-readable rate-limit message.
	 *
	 * @param int $retry_after Seconds until the next slot opens (0 if unknown).
	 * @return string
	 */
	private static function format_rate_limit_message( $retry_after ) {
		$base = __( 'Requests to the AI scheduler are temporarily paused by the rate limiter.', 'ai-post-scheduler' );

		if ( $retry_after > 0 ) {
			$mins = (int) floor( $retry_after / 60 );
			$secs = $retry_after % 60;

			if ( $mins > 0 ) {
				$when = $secs > 0
					? sprintf( '%dm %ds', $mins, $secs )
					: sprintf( '%dm', $mins );
			} else {
				$when = sprintf( '%ds', $secs );
			}

			/* translators: %s: human-readable duration, e.g. "1m 42s" or "45s" */
			$base .= ' ' . sprintf( __( 'You can try again in %s.', 'ai-post-scheduler' ), $when );
		}

		return $base;
	}
}
