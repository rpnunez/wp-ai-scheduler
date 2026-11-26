<?php
/**
 * Cloudflare AI Gateway exception.
 *
 * Carries the normalized AIPS error code alongside the human-readable message
 * and the raw HTTP / Cloudflare codes, so the provider can both re-throw in the
 * service's "code: message" convention and inspect the structured values.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Cloudflare_AI_Gateway_Exception
 */
class AIPS_Cloudflare_AI_Gateway_Exception extends Exception {

	/**
	 * @var string Canonical AIPS error code (e.g. 'invalid_api_key').
	 */
	private $error_code;

	/**
	 * @var int HTTP status code, or 0 for a transport-level failure.
	 */
	private $http_status;

	/**
	 * @var int|null Cloudflare numeric error code, when the body carried one.
	 */
	private $cf_code;

	/**
	 * @param string   $error_code  Canonical AIPS error code.
	 * @param string   $message     Human-readable message.
	 * @param int      $http_status HTTP status (0 for transport failures).
	 * @param int|null $cf_code     Cloudflare numeric error code.
	 */
	public function __construct(string $error_code, string $message, int $http_status = 0, ?int $cf_code = null) {
		parent::__construct($message);
		$this->error_code  = $error_code;
		$this->http_status = $http_status;
		$this->cf_code     = $cf_code;
	}

	/**
	 * @return string Canonical AIPS error code.
	 */
	public function get_error_code(): string {
		return $this->error_code;
	}

	/**
	 * @return int HTTP status, or 0 for a transport failure.
	 */
	public function get_http_status(): int {
		return $this->http_status;
	}

	/**
	 * @return int|null Cloudflare numeric error code, when present.
	 */
	public function get_cf_code(): ?int {
		return $this->cf_code;
	}

	/**
	 * Message in the service's "code: message" convention, matching how the
	 * WordPress AI Client adapter prefixes its errors.
	 *
	 * @return string
	 */
	public function to_prefixed_message(): string {
		return $this->error_code . ': ' . $this->getMessage();
	}
}
