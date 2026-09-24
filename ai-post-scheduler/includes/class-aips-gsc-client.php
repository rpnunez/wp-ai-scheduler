<?php
/**
 * Google Search Console Client
 *
 * Minimal Search Console API client authenticated with a Google Cloud
 * service account (JSON key). Search Console data is private to verified
 * site owners, so a plain API key cannot read it: the service account's
 * email must be added as a user of the property in Search Console.
 *
 * Access tokens come from the OAuth 2.0 JWT bearer flow (RS256 signed with
 * the key's private key) and are cached until shortly before they expire.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.6
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_GSC_Client
 */
class AIPS_GSC_Client {

	/**
	 * Option holding the (encrypted) service account JSON.
	 */
	const CREDENTIALS_OPTION = 'aips_gsc_service_account';

	/**
	 * Option holding the Search Console property.
	 */
	const PROPERTY_OPTION = 'aips_gsc_property';

	/**
	 * Read-only Search Console scope.
	 */
	const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

	/**
	 * Default OAuth token endpoint.
	 */
	const TOKEN_URI = 'https://oauth2.googleapis.com/token';

	/**
	 * Search Console API base URL.
	 */
	const API_BASE = 'https://www.googleapis.com/webmasters/v3/';

	/**
	 * Transient caching the access token.
	 */
	const TOKEN_TRANSIENT = 'aips_gsc_access_token';

	/**
	 * Set while disconnect() clears the key, so the sanitizer allows ''.
	 *
	 * @var bool
	 */
	private static $clearing = false;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * @param AIPS_Config|null $config Config.
	 */
	public function __construct(?AIPS_Config $config = null) {
		$this->config = $config ?: AIPS_Config::get_instance();
	}

	/**
	 * Settings sanitizer for the service account option.
	 *
	 * Empty input keeps the saved key (the field is never pre-filled), an
	 * already-encrypted value passes through (sanitize_option runs twice on
	 * save), and a pasted JSON key is validated and encrypted.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_credentials($value): string {
		if (self::$clearing) {
			return '';
		}

		$existing = (string) AIPS_Config::get_instance()->get_option(self::CREDENTIALS_OPTION, '');
		$value    = is_string($value) ? trim($value) : '';

		if ($value === '') {
			return $existing;
		}

		if (AIPS_Secret_Encryption::is_encrypted($value)) {
			return $value;
		}

		$parsed = self::parse_credentials($value);
		if (is_wp_error($parsed)) {
			if (function_exists('add_settings_error')) {
				add_settings_error(self::CREDENTIALS_OPTION, $parsed->get_error_code(), $parsed->get_error_message());
			}
			return $existing;
		}

		delete_transient(self::TOKEN_TRANSIENT);

		return AIPS_Secret_Encryption::encrypt(wp_json_encode($parsed));
	}

	/**
	 * Settings sanitizer for the property: "sc-domain:example.com" or a URL
	 * prefix such as "https://example.com/".
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_property($value): string {
		$value = is_string($value) ? trim(sanitize_text_field($value)) : '';
		if ($value === '') {
			return '';
		}

		if (stripos($value, 'sc-domain:') === 0) {
			$domain = strtolower(preg_replace('/[^A-Za-z0-9.\-]/', '', substr($value, 10)));
			return $domain === '' ? '' : 'sc-domain:' . $domain;
		}

		$url = esc_url_raw($value, array('http', 'https'));
		return $url === '' ? '' : trailingslashit($url);
	}

	/**
	 * Validate a service account JSON key.
	 *
	 * @param string $json JSON text.
	 * @return array|WP_Error client_email, private_key, token_uri.
	 */
	public static function parse_credentials(string $json) {
		$data = json_decode($json, true);

		if (!is_array($data) || ($data['type'] ?? '') !== 'service_account' || empty($data['client_email']) || empty($data['private_key'])) {
			return new WP_Error('aips_gsc_invalid_key', __('That is not a Google service account JSON key. In Google Cloud, open IAM & Admin → Service Accounts → Keys → Add key → JSON, and paste the whole downloaded file.', 'ai-post-scheduler'));
		}

		if (!function_exists('openssl_pkey_get_private') || !openssl_pkey_get_private((string) $data['private_key'])) {
			return new WP_Error('aips_gsc_invalid_private_key', __('The private key in that JSON file could not be read.', 'ai-post-scheduler'));
		}

		$token_uri = isset($data['token_uri']) ? (string) $data['token_uri'] : self::TOKEN_URI;
		if (strpos($token_uri, 'https://oauth2.googleapis.com/') !== 0 && strpos($token_uri, 'https://accounts.google.com/') !== 0) {
			$token_uri = self::TOKEN_URI;
		}

		return array(
			'client_email' => sanitize_email((string) $data['client_email']),
			'private_key'  => (string) $data['private_key'],
			'token_uri'    => $token_uri,
		);
	}

	/**
	 * Saved credentials, decrypted.
	 *
	 * @return array|null
	 */
	public function get_credentials(): ?array {
		$stored = (string) $this->config->get_option(self::CREDENTIALS_OPTION, '');
		if ($stored === '') {
			return null;
		}

		$data = json_decode(AIPS_Secret_Encryption::decrypt($stored), true);
		if (!is_array($data) || empty($data['client_email']) || empty($data['private_key'])) {
			return null;
		}

		return $data;
	}

	/**
	 * Whether a key was saved but can no longer be read (e.g. salts changed).
	 *
	 * @return bool
	 */
	public function has_unreadable_credentials(): bool {
		return (string) $this->config->get_option(self::CREDENTIALS_OPTION, '') !== '' && $this->get_credentials() === null;
	}

	/**
	 * Service account email (to add as a user in Search Console).
	 *
	 * @return string
	 */
	public function get_client_email(): string {
		$credentials = $this->get_credentials();
		return $credentials ? (string) $credentials['client_email'] : '';
	}

	/**
	 * Configured property.
	 *
	 * @return string
	 */
	public function get_property(): string {
		return (string) $this->config->get_option(self::PROPERTY_OPTION, '');
	}

	/**
	 * Whether credentials and a property are saved.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return $this->get_property() !== '' && $this->get_credentials() !== null;
	}

	/**
	 * Remove the saved key and cached token.
	 *
	 * @return void
	 */
	public function disconnect(): void {
		self::$clearing = true;
		$this->config->set_option(self::CREDENTIALS_OPTION, '');
		self::$clearing = false;
		delete_transient(self::TOKEN_TRANSIENT);
	}

	/**
	 * Properties the service account can read.
	 *
	 * @return array[]|WP_Error Each with siteUrl and permissionLevel.
	 */
	public function list_sites() {
		$response = $this->request('GET', 'sites');
		if (is_wp_error($response)) {
			return $response;
		}

		return isset($response['siteEntry']) && is_array($response['siteEntry']) ? $response['siteEntry'] : array();
	}

	/**
	 * Run a Search Analytics query against the configured property.
	 *
	 * @param array $body Request body (startDate, endDate, dimensions, rowLimit, startRow...).
	 * @return array[]|WP_Error Rows (keys, clicks, impressions, ctr, position).
	 */
	public function search_analytics(array $body) {
		$property = $this->get_property();
		if ($property === '') {
			return new WP_Error('aips_gsc_no_property', __('Enter your Search Console property first.', 'ai-post-scheduler'));
		}

		$response = $this->request('POST', 'sites/' . rawurlencode($property) . '/searchAnalytics/query', $body);
		if (is_wp_error($response)) {
			return $response;
		}

		return isset($response['rows']) && is_array($response['rows']) ? $response['rows'] : array();
	}

	/**
	 * Authenticated API request.
	 *
	 * @param string     $method HTTP method.
	 * @param string     $path   Path below API_BASE.
	 * @param array|null $body   JSON body.
	 * @return array|WP_Error Decoded JSON.
	 */
	private function request(string $method, string $path, ?array $body = null) {
		$token = $this->get_access_token();
		if (is_wp_error($token)) {
			return $token;
		}

		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
			),
		);

		if ($body !== null) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode($body);
		}

		$response = wp_remote_request(self::API_BASE . $path, $args);
		if (is_wp_error($response)) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$data = json_decode((string) wp_remote_retrieve_body($response), true);

		if ($code === 401) {
			delete_transient(self::TOKEN_TRANSIENT);
		}

		if ($code < 200 || $code >= 300) {
			return new WP_Error('aips_gsc_api_error', $this->api_error_message($code, is_array($data) ? $data : array()));
		}

		return is_array($data) ? $data : array();
	}

	/**
	 * OAuth access token (cached).
	 *
	 * @return string|WP_Error
	 */
	private function get_access_token() {
		$cached = get_transient(self::TOKEN_TRANSIENT);
		if (is_string($cached) && $cached !== '') {
			return $cached;
		}

		$credentials = $this->get_credentials();
		if (!$credentials) {
			return new WP_Error('aips_gsc_no_credentials', __('Add a Google service account JSON key under Settings → API Keys.', 'ai-post-scheduler'));
		}

		$assertion = $this->build_jwt($credentials);
		if (is_wp_error($assertion)) {
			return $assertion;
		}

		$response = wp_remote_post($credentials['token_uri'] ?? self::TOKEN_URI, array(
			'timeout' => 20,
			'body'    => array(
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion'  => $assertion,
			),
		));

		if (is_wp_error($response)) {
			return $response;
		}

		$data = json_decode((string) wp_remote_retrieve_body($response), true);
		if ((int) wp_remote_retrieve_response_code($response) !== 200 || empty($data['access_token'])) {
			$detail = is_array($data) && !empty($data['error_description']) ? (string) $data['error_description'] : (is_array($data) && !empty($data['error']) ? (string) $data['error'] : '');
			/* translators: %s: error returned by Google */
			return new WP_Error('aips_gsc_token_error', sprintf(__('Google rejected the service account key: %s', 'ai-post-scheduler'), $detail !== '' ? $detail : __('unknown error', 'ai-post-scheduler')));
		}

		$ttl = max(60, (int) ($data['expires_in'] ?? 3600) - 120);
		set_transient(self::TOKEN_TRANSIENT, (string) $data['access_token'], $ttl);

		return (string) $data['access_token'];
	}

	/**
	 * Signed JWT assertion for the token request.
	 *
	 * @param array $credentials Credentials.
	 * @return string|WP_Error
	 */
	private function build_jwt(array $credentials) {
		$now    = time();
		$header = array('alg' => 'RS256', 'typ' => 'JWT');
		$claims = array(
			'iss'   => $credentials['client_email'],
			'scope' => self::SCOPE,
			'aud'   => $credentials['token_uri'] ?? self::TOKEN_URI,
			'iat'   => $now,
			'exp'   => $now + HOUR_IN_SECONDS,
		);

		$input = self::base64url(wp_json_encode($header)) . '.' . self::base64url(wp_json_encode($claims));

		$key = openssl_pkey_get_private((string) $credentials['private_key']);
		if (!$key || !openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256)) {
			return new WP_Error('aips_gsc_sign_error', __('Could not sign the Google token request with the saved key.', 'ai-post-scheduler'));
		}

		return $input . '.' . self::base64url($signature);
	}

	/**
	 * Human-readable API error.
	 *
	 * @param int   $code HTTP status.
	 * @param array $data Decoded body.
	 * @return string
	 */
	private function api_error_message(int $code, array $data): string {
		if ($code === 403) {
			/* translators: %s: service account email */
			return sprintf(__('Search Console denied access. Add %s as a user (Restricted is enough) of this property in Search Console → Settings → Users and permissions, and check the property name.', 'ai-post-scheduler'), $this->get_client_email());
		}

		$message = isset($data['error']['message']) ? (string) $data['error']['message'] : '';

		/* translators: 1: HTTP status code, 2: error message from Google */
		return sprintf(__('Search Console API error (%1$d): %2$s', 'ai-post-scheduler'), $code, $message !== '' ? $message : __('unknown error', 'ai-post-scheduler'));
	}

	/**
	 * Base64url without padding.
	 *
	 * @param string $data Data.
	 * @return string
	 */
	private static function base64url(string $data): string {
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}
}
