<?php
/**
 * AI Connector Health Store
 *
 * Persists and retrieves connector health, success timestamps, consecutive
 * failure counts, and cooldown state in WordPress transients.
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_AI_Connector_Health_Store {

	/**
	 * Connector failure codes that trigger immediate cooldown.
	 */
	public const CONFIGURATION_FAILURE_CODES = array(
		'invalid_api_key',
		'billing_not_active',
		'insufficient_quota',
		'wpai_connector_not_approved',
	);

	/**
	 * @var AIPS_AI_Connector_Registry|null Optional registry for checking approval status.
	 */
	private $registry;

	/**
	 * Constructor.
	 *
	 * @param AIPS_AI_Connector_Registry|null $registry Optional registry service.
	 */
	public function __construct(?AIPS_AI_Connector_Registry $registry = null) {
		$this->registry = $registry;
	}

	/**
	 * Build the transient key for connector health.
	 *
	 * @param string $connector_id Connector ID.
	 * @return string
	 */
	public function get_health_key(string $connector_id): string {
		return 'aips_wp_ai_health_' . md5($connector_id);
	}

	/**
	 * Read connector health state.
	 *
	 * @param string $connector_id Connector ID.
	 * @return array
	 */
	public function get_health(string $connector_id): array {
		$health = get_transient($this->get_health_key($connector_id));

		return is_array($health) ? $health : array();
	}

	/**
	 * Record a successful connector operation.
	 *
	 * @param string $connector_id Connector ID.
	 * @return void
	 */
	public function record_success(string $connector_id): void {
		set_transient(
			$this->get_health_key($connector_id),
			array(
				'state'        => 'healthy',
				'failures'     => 0,
				'last_success' => time(),
			),
			DAY_IN_SECONDS
		);
	}

	/**
	 * Record connector-specific failure state and cooldown.
	 *
	 * @param string $connector_id     Connector ID.
	 * @param string $error_code       Canonical error code.
	 * @param int    $cooldown_seconds Duration of cooldown in seconds.
	 * @return void
	 */
	public function record_failure(string $connector_id, string $error_code, int $cooldown_seconds): void {
		$health   = $this->get_health($connector_id);
		$failures = isset($health['failures']) ? (int) $health['failures'] + 1 : 1;

		set_transient(
			$this->get_health_key($connector_id),
			array(
				'state'           => 'cooling_down',
				'failures'        => $failures,
				'last_failure'    => time(),
				'last_error_code' => sanitize_key($error_code),
				'cooldown_until'  => time() + max(0, $cooldown_seconds),
			),
			DAY_IN_SECONDS
		);
	}

	/**
	 * Whether a connector remains inside its failure cooldown.
	 *
	 * @param string    $connector_id Connector ID.
	 * @param bool|null $is_approved  Optional explicit approval status.
	 * @return bool
	 */
	public function is_cooling_down(string $connector_id, ?bool $is_approved = null): bool {
		$health = $this->get_health($connector_id);
		$error  = isset($health['last_error_code']) ? (string) $health['last_error_code'] : '';

		if ($error === 'wpai_connector_not_approved') {
			$approved = $is_approved;
			if ($approved === null && $this->registry !== null) {
				$approved = $this->registry->get_connector_approval_status($connector_id);
			}
			if ($approved === true) {
				return false;
			}
		}

		$ready_to_cool_down = (int) ($health['failures'] ?? 0) >= 2
			|| in_array($error, self::CONFIGURATION_FAILURE_CODES, true);

		return $ready_to_cool_down
			&& !empty($health['cooldown_until'])
			&& (int) $health['cooldown_until'] > time();
	}
}
