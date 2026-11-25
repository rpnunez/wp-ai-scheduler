<?php
/**
 * Cloudflare AI Gateway configuration.
 *
 * Read-only value object over the Cloudflare AI Gateway settings. The provider
 * and HTTP client read their configuration from here, never from options
 * directly, and this is the only place the stored API token is decrypted.
 *
 * Also owns the sanitize callbacks for those settings, so the stored format
 * and the read path stay in one file.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Cloudflare_AI_Gateway_Config
 */
class AIPS_Cloudflare_AI_Gateway_Config {

	const OPTION_ACCOUNT_ID      = 'aips_cf_account_id';
	const OPTION_API_TOKEN       = 'aips_cf_api_token';
	const OPTION_GATEWAY_ID      = 'aips_cf_gateway_id';
	const OPTION_TEXT_MODEL      = 'aips_cf_text_model';
	const OPTION_IMAGE_MODEL     = 'aips_cf_image_model';
	const OPTION_EMBEDDING_MODEL = 'aips_cf_embedding_model';
	const OPTION_LOG_PAYLOADS    = 'aips_cf_log_payloads';

	const DEFAULT_GATEWAY_ID      = 'default';
	const DEFAULT_IMAGE_MODEL     = '@cf/black-forest-labs/flux-1-schnell';
	const DEFAULT_EMBEDDING_MODEL = '@cf/baai/bge-m3';

	/**
	 * Cloudflare API v4 base URL.
	 */
	const API_BASE = 'https://api.cloudflare.com/client/v4';

	/**
	 * @var string
	 */
	private $account_id;

	/**
	 * @var string Stored (normally encrypted) token value.
	 */
	private $stored_token;

	/**
	 * @var string|null Decrypted token, resolved lazily.
	 */
	private $token = null;

	/**
	 * @var string
	 */
	private $gateway_id;

	/**
	 * @var string
	 */
	private $text_model;

	/**
	 * @var string
	 */
	private $image_model;

	/**
	 * @var string
	 */
	private $embedding_model;

	/**
	 * @var bool
	 */
	private $log_payloads;

	/**
	 * @param AIPS_Config|null $config Config instance (defaults to the singleton).
	 */
	public function __construct($config = null) {
		$config = $config ?: AIPS_Config::get_instance();

		$this->account_id      = self::sanitize_account_id($config->get_option(self::OPTION_ACCOUNT_ID));
		$this->stored_token    = trim((string) $config->get_option(self::OPTION_API_TOKEN));
		$this->gateway_id      = self::sanitize_gateway_id($config->get_option(self::OPTION_GATEWAY_ID));
		$this->text_model      = self::sanitize_model_id($config->get_option(self::OPTION_TEXT_MODEL));
		$this->image_model     = self::sanitize_model_id($config->get_option(self::OPTION_IMAGE_MODEL));
		$this->embedding_model = self::sanitize_model_id($config->get_option(self::OPTION_EMBEDDING_MODEL));
		$this->log_payloads    = (bool) $config->get_option(self::OPTION_LOG_PAYLOADS);
	}

	/**
	 * @return string Cloudflare account ID (32 hex characters), or ''.
	 */
	public function get_account_id(): string {
		return $this->account_id;
	}

	/**
	 * Decrypted API token.
	 *
	 * @return string Token, or '' when none is stored or it cannot be decrypted.
	 */
	public function get_api_token(): string {
		if ($this->token === null) {
			$this->token = $this->stored_token === '' ? '' : AIPS_Secret_Encryption::decrypt($this->stored_token);
		}

		return $this->token;
	}

	/**
	 * @return bool Whether a usable (decryptable) token is stored.
	 */
	public function has_api_token(): bool {
		return $this->get_api_token() !== '';
	}

	/**
	 * @return string Gateway ID (never empty; falls back to "default").
	 */
	public function get_gateway_id(): string {
		return $this->gateway_id;
	}

	/**
	 * @return string Chat model, e.g. "google/gemini-3-flash" or "dynamic/my-route".
	 */
	public function get_text_model(): string {
		return $this->text_model;
	}

	/**
	 * @return string Image model, e.g. "@cf/black-forest-labs/flux-1-schnell".
	 */
	public function get_image_model(): string {
		return $this->image_model;
	}

	/**
	 * @return string Embedding model, e.g. "@cf/baai/bge-m3".
	 */
	public function get_embedding_model(): string {
		return $this->embedding_model;
	}

	/**
	 * @return bool Whether Cloudflare should store prompt/response bodies in its logs.
	 */
	public function should_log_payloads(): bool {
		return $this->log_payloads;
	}

	/**
	 * Base URL of the account's AI REST API, e.g.
	 * https://api.cloudflare.com/client/v4/accounts/{account_id}/ai
	 *
	 * @return string URL, or '' when no account ID is set.
	 */
	public function get_api_base_url(): string {
		if ($this->account_id === '') {
			return '';
		}

		return self::API_BASE . '/accounts/' . rawurlencode($this->account_id) . '/ai';
	}

	/**
	 * Whether everything needed for text generation is configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return empty($this->get_missing_requirements());
	}

	/**
	 * Human-readable list of what is missing for text generation.
	 *
	 * @return string[] Empty when ready.
	 */
	public function get_missing_requirements(): array {
		$missing = array();

		if ($this->account_id === '') {
			$missing[] = __('Cloudflare account ID is not set.', 'ai-post-scheduler');
		}

		if ($this->stored_token === '') {
			$missing[] = __('Cloudflare API token is not set.', 'ai-post-scheduler');
		} elseif (!$this->has_api_token()) {
			$missing[] = __('The saved Cloudflare API token cannot be decrypted (the site security salts may have changed). Enter the token again.', 'ai-post-scheduler');
		}

		if ($this->text_model === '') {
			$missing[] = __('Cloudflare text model is not set.', 'ai-post-scheduler');
		}

		return $missing;
	}

	/**
	 * Keep the token out of var_dump()/print_r() output and debug logs.
	 *
	 * @return array
	 */
	public function __debugInfo() {
		return array(
			'account_id'      => $this->account_id,
			'api_token'       => $this->stored_token === '' ? '' : '[redacted]',
			'gateway_id'      => $this->gateway_id,
			'text_model'      => $this->text_model,
			'image_model'     => $this->image_model,
			'embedding_model' => $this->embedding_model,
			'log_payloads'    => $this->log_payloads,
		);
	}

	// ---------------------------------------------------------------------
	// Sanitize callbacks (referenced from AIPS_Settings_Registry).
	// ---------------------------------------------------------------------

	/**
	 * Account IDs are 32 lowercase hex characters; anything else is stripped.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_account_id($value): string {
		$value = is_scalar($value) ? strtolower(trim((string) $value)) : '';

		return (string) preg_replace('/[^a-f0-9]/', '', $value);
	}

	/**
	 * Gateway IDs are slugs; empty falls back to "default".
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_gateway_id($value): string {
		$value = is_scalar($value) ? strtolower(trim((string) $value)) : '';
		$value = (string) preg_replace('/[^a-z0-9_-]/', '', $value);

		return $value === '' ? self::DEFAULT_GATEWAY_ID : $value;
	}

	/**
	 * Model IDs such as "@cf/baai/bge-m3", "google/gemini-3-flash",
	 * "dynamic/my-route" or "openai/gpt-4.1:latest".
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_model_id($value): string {
		$value = is_scalar($value) ? trim((string) $value) : '';

		return (string) preg_replace('#[^A-Za-z0-9@/._:\-]#', '', $value);
	}

	/**
	 * Encrypt a newly entered token; keep the stored one when the field is
	 * submitted blank.
	 *
	 * Idempotent: an already-encrypted value passes through unchanged. That
	 * matters because a save can run this callback more than once
	 * (AIPS_Setting_Definition::sanitize(), then sanitize_option() inside
	 * update_option()).
	 *
	 * @param mixed $value Raw value.
	 * @return string Encrypted token (or plain when OpenSSL is unavailable).
	 */
	public static function sanitize_api_token($value): string {
		$value = is_scalar($value) ? trim((string) $value) : '';

		if (AIPS_Secret_Encryption::is_encrypted($value)) {
			return $value;
		}

		// Cloudflare tokens are URL-safe: letters, digits, "_" and "-".
		$value = (string) preg_replace('/[^A-Za-z0-9_\-]/', '', $value);

		if ($value === '') {
			return trim((string) AIPS_Config::get_instance()->get_option(self::OPTION_API_TOKEN));
		}

		return AIPS_Secret_Encryption::encrypt($value);
	}
}
