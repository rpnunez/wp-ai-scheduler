<?php
/**
 * AI Failover Policy
 *
 * Evaluates whether an error produced by an AI connector qualifies for
 * retry/failover against another connector candidate and determines the
 * appropriate cooldown duration.
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_AI_Failover_Policy {

	/** Default connector cooldown after a provider-specific failure. */
	public const DEFAULT_COOLDOWN_SECONDS = 60;

	/** Longer cooldown for credential, billing, and quota failures. */
	public const CONFIGURATION_COOLDOWN_SECONDS = 900;

	/** Connector failures that should cool down immediately. */
	public const CONFIGURATION_FAILURE_CODES = array(
		'invalid_api_key',
		'billing_not_active',
		'insufficient_quota',
		'wpai_connector_not_approved',
	);

	/** Connector failure codes caused by the request rather than the connector. */
	public const NON_FAILOVER_CODES = array(
		'content_policy_violation',
		'context_length_exceeded',
		'invalid_request_error',
		'json_query_unavailable',
	);

	/**
	 * Whether an error is appropriate to retry against another connector.
	 *
	 * @param string $error_code Canonical provider error code.
	 * @param string $message    Provider error message.
	 * @return bool
	 */
	public function should_fail_over(string $error_code, string $message = ''): bool {
		if ($error_code === 'prompt_invalid_argument') {
			return stripos($message, 'No models found for provider') !== false;
		}

		return !in_array($error_code, self::NON_FAILOVER_CODES, true);
	}

	/**
	 * Whether an error is a configuration/credential/billing/approval failure.
	 *
	 * @param string $error_code Canonical provider error code.
	 * @return bool
	 */
	public function is_configuration_failure(string $error_code): bool {
		return in_array($error_code, self::CONFIGURATION_FAILURE_CODES, true);
	}

	/**
	 * Determine the cooldown duration for a given error code.
	 *
	 * @param string $error_code Canonical provider error code.
	 * @return int Cooldown in seconds.
	 */
	public function get_cooldown_seconds(string $error_code): int {
		$cooldown = $this->is_configuration_failure($error_code)
			? self::CONFIGURATION_COOLDOWN_SECONDS
			: self::DEFAULT_COOLDOWN_SECONDS;

		/**
		 * Filters the connector cooldown duration in seconds.
		 *
		 * @param int    $cooldown   Cooldown duration in seconds.
		 * @param string $error_code Canonical error code.
		 */
		return (int) apply_filters('aips_wp_ai_connector_cooldown', $cooldown, $error_code);
	}
}
