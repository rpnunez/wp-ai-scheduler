<?php
/**
 * Cloudflare AI Gateway AJAX controller.
 *
 * Serves the "Test connection" button in the Cloudflare AI Gateway settings
 * section: it verifies the stored API token and, when a text model is set, runs
 * a tiny completion, so the admin can confirm the credentials without waiting
 * for a real generation. Registered in AIPS_Ajax_Registry.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Cloudflare_AI_Gateway_Controller
 */
class AIPS_Cloudflare_AI_Gateway_Controller {

	const ACTION = 'aips_cf_gateway_test_connection';
	const NONCE  = 'aips_ajax_nonce';

	/**
	 * @var AIPS_Cloudflare_AI_Gateway_Client|null Injected client (tests); null builds from config.
	 */
	private $client;

	/**
	 * @param AIPS_Cloudflare_AI_Gateway_Client|null $client Optional client override for tests.
	 */
	public function __construct(?AIPS_Cloudflare_AI_Gateway_Client $client = null) {
		$this->client = $client;
		add_action('wp_ajax_' . self::ACTION, array($this, 'ajax_test_connection'));
	}

	/**
	 * Verify the token and, when possible, run a one-line completion.
	 *
	 * @return void
	 */
	public function ajax_test_connection(): void {
		$this->verify_request();

		$config = new AIPS_Cloudflare_AI_Gateway_Config();

		if ($config->get_account_id() === '' || !$config->has_api_token()) {
			AIPS_Ajax_Response::error(array(
				'message' => __('Enter and save a Cloudflare account ID and API token before testing the connection.', 'ai-post-scheduler'),
				'code'    => 'not_configured',
			));
		}

		$client = $this->client ?: new AIPS_Cloudflare_AI_Gateway_Client($config);

		// 1. Token check (cheap, needs no model).
		try {
			$verify = $client->verify_token();
		} catch (AIPS_Cloudflare_AI_Gateway_Exception $e) {
			AIPS_Ajax_Response::error(array('message' => $e->getMessage(), 'code' => $e->get_error_code()));
			return; // Unreachable after wp_send_json; keeps static analysers happy.
		}

		$status = isset($verify['result']['status']) ? (string) $verify['result']['status'] : '';
		if ($status !== 'active') {
			AIPS_Ajax_Response::error(array(
				'message' => __('The Cloudflare API token was rejected or is not active.', 'ai-post-scheduler'),
				'code'    => 'invalid_api_key',
			));
		}

		// 2. Optional end-to-end completion when a text model is configured.
		if ($config->get_text_model() === '') {
			AIPS_Ajax_Response::success(array(
				'message' => __('API token verified. Set a text model to test content generation.', 'ai-post-scheduler'),
			));
		}

		$provider = new AIPS_Cloudflare_AI_Gateway_Provider($config, $client);

		try {
			$reply = $provider->generate_text(
				'Reply with the single word: OK',
				array('max_tokens' => 16, 'temperature' => 0)
			);
		} catch (Exception $e) {
			AIPS_Ajax_Response::error(array(
				'message' => $e->getMessage(),
				'code'    => $provider->extract_error_code($e->getMessage()),
			));
			return;
		}

		$snippet = trim((string) $reply);
		if (function_exists('mb_substr')) {
			$snippet = mb_substr($snippet, 0, 80);
		} else {
			$snippet = substr($snippet, 0, 80);
		}

		AIPS_Ajax_Response::success(array(
			'message' => sprintf(
				/* translators: %s: the model's reply. */
				__('Connection successful. The model replied: %s', 'ai-post-scheduler'),
				esc_html($snippet)
			),
		));
	}

	/**
	 * Validate the AJAX nonce and capability.
	 *
	 * @return void
	 */
	private function verify_request(): void {
		if (!check_ajax_referer(self::NONCE, 'nonce', false)) {
			AIPS_Ajax_Response::error(array('message' => __('Invalid nonce.', 'ai-post-scheduler'), 'code' => 'invalid_nonce'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
	}
}
