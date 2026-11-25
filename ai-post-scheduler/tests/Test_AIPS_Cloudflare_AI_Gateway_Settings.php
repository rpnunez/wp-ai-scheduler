<?php
/**
 * Tests for the Cloudflare AI Gateway settings and AIPS_Cloudflare_AI_Gateway_Config.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Cloudflare_AI_Gateway_Settings extends WP_UnitTestCase {

	const ACCOUNT_ID = '0123456789abcdef0123456789abcdef';
	const TOKEN      = 'cfut_TestToken-123_abcdefghijklmnop';

	/**
	 * Option keys owned by the Cloudflare provider.
	 *
	 * @var string[]
	 */
	private $keys = array(
		AIPS_Cloudflare_AI_Gateway_Config::OPTION_ACCOUNT_ID,
		AIPS_Cloudflare_AI_Gateway_Config::OPTION_API_TOKEN,
		AIPS_Cloudflare_AI_Gateway_Config::OPTION_GATEWAY_ID,
		AIPS_Cloudflare_AI_Gateway_Config::OPTION_TEXT_MODEL,
		AIPS_Cloudflare_AI_Gateway_Config::OPTION_IMAGE_MODEL,
		AIPS_Cloudflare_AI_Gateway_Config::OPTION_EMBEDDING_MODEL,
		AIPS_Cloudflare_AI_Gateway_Config::OPTION_LOG_PAYLOADS,
	);

	public function setUp(): void {
		parent::setUp();
		$this->reset_options();
	}

	public function tearDown(): void {
		$this->reset_options();
		parent::tearDown();
	}

	private function reset_options(): void {
		foreach ($this->keys as $key) {
			delete_option($key);
		}
		AIPS_Config::get_instance()->flush_option_cache();
	}

	/**
	 * Save an option the way the settings AJAX handler does: definition
	 * sanitize, then sanitize_option(), then set_option() (which runs
	 * sanitize_option() once more inside update_option()).
	 */
	private function save_like_settings_ajax(string $key, $raw_value): void {
		$definition = AIPS_Settings_Registry::instance()->get_definition($key);
		$value      = $definition->sanitize($raw_value);
		$value      = sanitize_option($key, $value);
		AIPS_Config::get_instance()->set_option($key, $value);
	}

	private function register_wp_setting(string $key): void {
		$definition = AIPS_Settings_Registry::instance()->get_definition($key);
		register_setting('aips_settings', $key, array('sanitize_callback' => $definition->get_sanitize_callback()));
	}

	// ------------------------------------------------------------------
	// Registry
	// ------------------------------------------------------------------

	public function test_section_is_registered_on_engine_tab() {
		$sections = AIPS_Settings_Registry::instance()->get_sections_by_tab('settings-ai');

		$this->assertArrayHasKey('aips_cloudflare_ai_gateway_section', $sections);
	}

	public function test_all_settings_are_registered_in_cloudflare_section() {
		$registry = AIPS_Settings_Registry::instance();

		foreach ($this->keys as $key) {
			$this->assertTrue($registry->has_definition($key), "{$key} should be registered");

			$definition = $registry->get_definition($key);
			$this->assertSame('settings-ai', $definition->get_tab());
			$this->assertSame('aips_cloudflare_ai_gateway_section', $definition->get_section());
			$this->assertSame(
				array('field' => 'aips_ai_provider', 'value' => 'cloudflare_ai_gateway'),
				$definition->get_dependencies(),
				"{$key} should only show for the Cloudflare provider"
			);
		}
	}

	public function test_defaults() {
		$defaults = AIPS_Config::get_instance()->get_default_options();

		$this->assertSame('', $defaults['aips_cf_account_id']);
		$this->assertSame('', $defaults['aips_cf_api_token']);
		$this->assertSame('default', $defaults['aips_cf_gateway_id']);
		$this->assertSame('', $defaults['aips_cf_text_model']);
		$this->assertSame('@cf/black-forest-labs/flux-1-schnell', $defaults['aips_cf_image_model']);
		$this->assertSame('@cf/baai/bge-m3', $defaults['aips_cf_embedding_model']);
		$this->assertFalse($defaults['aips_cf_log_payloads']);
	}

	public function test_token_uses_masked_secret_control() {
		$definition = AIPS_Settings_Registry::instance()->get_definition('aips_cf_api_token');
		$options    = $definition->get_control_options();

		$this->assertSame(AIPS_Setting_Definition::CONTROL_SECRET, $definition->get_control_type());
		$this->assertTrue($options['mask_stored_value']);
	}

	// ------------------------------------------------------------------
	// Token storage
	// ------------------------------------------------------------------

	public function test_token_is_encrypted_at_rest_and_decrypts() {
		if (!function_exists('openssl_encrypt')) {
			$this->markTestSkipped('OpenSSL is required for encryption.');
		}

		$this->save_like_settings_ajax('aips_cf_api_token', self::TOKEN);

		$stored = get_option('aips_cf_api_token');
		$this->assertTrue(AIPS_Secret_Encryption::is_encrypted($stored));
		$this->assertStringNotContainsString(self::TOKEN, $stored);

		$config = new AIPS_Cloudflare_AI_Gateway_Config();
		$this->assertSame(self::TOKEN, $config->get_api_token());
	}

	public function test_token_is_not_double_encrypted_when_sanitize_runs_repeatedly() {
		if (!function_exists('openssl_encrypt')) {
			$this->markTestSkipped('OpenSSL is required for encryption.');
		}

		$this->register_wp_setting('aips_cf_api_token');
		$this->save_like_settings_ajax('aips_cf_api_token', self::TOKEN);

		$config = new AIPS_Cloudflare_AI_Gateway_Config();
		$this->assertSame(self::TOKEN, $config->get_api_token());

		unregister_setting('aips_settings', 'aips_cf_api_token');
	}

	public function test_blank_submission_keeps_stored_token() {
		$this->register_wp_setting('aips_cf_api_token');
		$this->save_like_settings_ajax('aips_cf_api_token', self::TOKEN);
		$before = get_option('aips_cf_api_token');

		$this->save_like_settings_ajax('aips_cf_api_token', '');
		$this->save_like_settings_ajax('aips_cf_api_token', '   ');

		$this->assertSame($before, get_option('aips_cf_api_token'));
		$this->assertSame(self::TOKEN, (new AIPS_Cloudflare_AI_Gateway_Config())->get_api_token());

		unregister_setting('aips_settings', 'aips_cf_api_token');
	}

	public function test_new_token_replaces_old_token() {
		$this->save_like_settings_ajax('aips_cf_api_token', self::TOKEN);
		$this->save_like_settings_ajax('aips_cf_api_token', 'cfut_another-token');

		$this->assertSame('cfut_another-token', (new AIPS_Cloudflare_AI_Gateway_Config())->get_api_token());
	}

	public function test_token_sanitizer_strips_unsafe_characters() {
		$this->save_like_settings_ajax('aips_cf_api_token', " cfut_abc<script>def \n");

		$this->assertSame('cfut_abcscriptdef', (new AIPS_Cloudflare_AI_Gateway_Config())->get_api_token());
	}

	public function test_rendered_token_field_never_contains_the_secret() {
		$this->save_like_settings_ajax('aips_cf_api_token', self::TOKEN);

		$definition = AIPS_Settings_Registry::instance()->get_definition('aips_cf_api_token');
		$stored     = get_option('aips_cf_api_token');
		$html       = (new AIPS_Settings_Form_Renderer())->get_field_html($definition, $stored);

		$this->assertStringContainsString('type="password"', $html);
		$this->assertStringContainsString('value=""', $html);
		$this->assertStringNotContainsString(self::TOKEN, $html);
		$this->assertStringNotContainsString($stored, $html);
		$this->assertStringContainsString('leave blank to keep', $html);
	}

	public function test_unmasked_secret_controls_keep_existing_behaviour() {
		$definition = new AIPS_Setting_Definition(array(
			'key'          => 'aips_test_plain_secret',
			'type'         => AIPS_Setting_Definition::TYPE_STRING,
			'control_type' => AIPS_Setting_Definition::CONTROL_SECRET,
		));

		$html = (new AIPS_Settings_Form_Renderer())->get_field_html($definition, 'visible_value');

		$this->assertStringContainsString('value="visible_value"', $html);
	}

	// ------------------------------------------------------------------
	// Config value object
	// ------------------------------------------------------------------

	public function test_unconfigured_config_lists_missing_requirements() {
		$config = new AIPS_Cloudflare_AI_Gateway_Config();

		$this->assertFalse($config->is_configured());
		$this->assertFalse($config->has_api_token());
		$this->assertCount(3, $config->get_missing_requirements());
		$this->assertSame('', $config->get_api_base_url());
		$this->assertSame('default', $config->get_gateway_id());
	}

	public function test_fully_configured_config() {
		update_option('aips_cf_account_id', self::ACCOUNT_ID);
		$this->save_like_settings_ajax('aips_cf_api_token', self::TOKEN);
		update_option('aips_cf_gateway_id', 'aips-prod');
		update_option('aips_cf_text_model', 'google/gemini-3-flash');
		update_option('aips_cf_log_payloads', 1);
		AIPS_Config::get_instance()->flush_option_cache();

		$config = new AIPS_Cloudflare_AI_Gateway_Config();

		$this->assertTrue($config->is_configured());
		$this->assertSame(array(), $config->get_missing_requirements());
		$this->assertSame(self::ACCOUNT_ID, $config->get_account_id());
		$this->assertSame('aips-prod', $config->get_gateway_id());
		$this->assertSame('google/gemini-3-flash', $config->get_text_model());
		$this->assertSame('@cf/black-forest-labs/flux-1-schnell', $config->get_image_model());
		$this->assertSame('@cf/baai/bge-m3', $config->get_embedding_model());
		$this->assertTrue($config->should_log_payloads());
		$this->assertSame(
			'https://api.cloudflare.com/client/v4/accounts/' . self::ACCOUNT_ID . '/ai',
			$config->get_api_base_url()
		);
	}

	public function test_undecryptable_token_is_reported() {
		update_option('aips_cf_api_token', AIPS_Secret_Encryption::PREFIX . base64_encode(str_repeat('x', 64)));
		AIPS_Config::get_instance()->flush_option_cache();

		$config  = new AIPS_Cloudflare_AI_Gateway_Config();
		$missing = implode(' ', $config->get_missing_requirements());

		$this->assertFalse($config->has_api_token());
		$this->assertStringContainsString('cannot be decrypted', $missing);
	}

	public function test_debug_output_redacts_the_token() {
		$this->save_like_settings_ajax('aips_cf_api_token', self::TOKEN);

		$config = new AIPS_Cloudflare_AI_Gateway_Config();
		$config->get_api_token(); // Resolve the decrypted token first.

		$dump = print_r($config, true);

		$this->assertStringNotContainsString(self::TOKEN, $dump);
		$this->assertStringContainsString('[redacted]', $dump);
	}

	/**
	 * @dataProvider sanitizer_cases
	 */
	public function test_sanitizers(string $method, $input, string $expected) {
		$this->assertSame($expected, call_user_func(array('AIPS_Cloudflare_AI_Gateway_Config', $method), $input));
	}

	public function sanitizer_cases(): array {
		return array(
			'account id lowercased'         => array('sanitize_account_id', ' 0123456789ABCDEF0123456789ABCDEF ', '0123456789abcdef0123456789abcdef'),
			'account id strips junk'        => array('sanitize_account_id', 'abc-123"><x', 'abc123'),
			'account id non-scalar'         => array('sanitize_account_id', array('x'), ''),
			'gateway id slug'               => array('sanitize_gateway_id', 'My Gateway_01', 'mygateway_01'),
			'gateway id empty defaults'     => array('sanitize_gateway_id', '  ', 'default'),
			'workers ai model kept'         => array('sanitize_model_id', '@cf/baai/bge-m3', '@cf/baai/bge-m3'),
			'third-party model kept'        => array('sanitize_model_id', 'openai/gpt-4.1:latest', 'openai/gpt-4.1:latest'),
			'dynamic route kept'            => array('sanitize_model_id', 'dynamic/blog-posts', 'dynamic/blog-posts'),
			'model strips spaces and quote' => array('sanitize_model_id', ' google/gemini 3"flash ', 'google/gemini3flash'),
		);
	}
}
