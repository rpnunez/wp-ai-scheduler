<?php
/**
 * Tests for AIPS_Config
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Config_Test extends WP_UnitTestCase {

	private $config;

	public function setUp(): void {
		parent::setUp();
		// Reset options
		$GLOBALS['aips_test_options'] = array();

		// Get instance
		$this->config = AIPS_Config::get_instance();
	}

	public function tearDown(): void {
		// Clean up options
		$GLOBALS['aips_test_options'] = array();
		parent::tearDown();
	}

	/**
	 * Test singleton instance
	 */
	public function test_get_instance() {
		$instance1 = AIPS_Config::get_instance();
		$instance2 = AIPS_Config::get_instance();

		$this->assertSame($instance1, $instance2);
		$this->assertInstanceOf('AIPS_Config', $instance1);
	}

	/**
	 * Test default options
	 */
	public function test_get_default_options() {
		$defaults = $this->config->get_default_options();

		$this->assertIsArray($defaults);
		$this->assertArrayHasKey('aips_ai_model', $defaults);
		$this->assertArrayHasKey('aips_max_tokens', $defaults);
		$this->assertArrayHasKey('aips_retry_max_attempts', $defaults);

		// Check specific default values
		$this->assertEquals(2000, $defaults['aips_max_tokens']);
		$this->assertEquals(3, $defaults['aips_retry_max_attempts']);
	}

	/**
	 * Test getting options
	 */
	public function test_get_option() {
		// Test getting non-existent option (should return default)
		$value = $this->config->get_option('aips_max_tokens');
		$this->assertEquals(2000, $value);

		// Test getting non-existent option with custom default
		$value = $this->config->get_option('non_existent_option', 'custom_default');
		$this->assertEquals('custom_default', $value);

		// Test getting existing option
		add_option('aips_max_tokens', 4000);
		$value = $this->config->get_option('aips_max_tokens');
		$this->assertEquals(4000, $value);
	}

	/**
	 * Test AI config
	 */
	public function test_get_ai_config() {
		// Set some options
		add_option('aips_ai_model', 'gpt-4');
		add_option('aips_max_tokens', 4000);
		add_option('aips_temperature', 0.5);

		$config = $this->config->get_ai_config();

		$this->assertIsArray($config);
		$this->assertEquals('gpt-4', $config['model']);
		$this->assertEquals(4000, $config['max_tokens']);
		$this->assertEquals(0.5, $config['temperature']);
	}

	/**
	 * Test Retry config
	 */
	public function test_get_retry_config() {
		// Set some options
		add_option('aips_enable_retry', 1);
		add_option('aips_retry_max_attempts', 5);

		$config = $this->config->get_retry_config();

		$this->assertIsArray($config);
		// Note: 'enabled' is hardcoded to false in current implementation
		$this->assertFalse($config['enabled']);
		$this->assertEquals(5, $config['max_attempts']);
		$this->assertTrue($config['exponential']);
		$this->assertTrue($config['jitter']);
	}

	/**
	 * Test Rate Limit config
	 */
	public function test_get_rate_limit_config() {
		// Set some options
		add_option('aips_enable_rate_limiting', 1);
		add_option('aips_rate_limit_requests', 20);
		add_option('aips_rate_limit_period', 120);

		$config = $this->config->get_rate_limit_config();

		$this->assertIsArray($config);
		$this->assertTrue($config['enabled']);
		$this->assertEquals(20, $config['requests']);
		$this->assertEquals(120, $config['period']);
	}

	/**
	 * Test Circuit Breaker config
	 */
	public function test_get_circuit_breaker_config() {
		// Set some options
		add_option('aips_enable_circuit_breaker', 1);
		add_option('aips_circuit_breaker_threshold', 10);
		add_option('aips_circuit_breaker_timeout', 600);

		$config = $this->config->get_circuit_breaker_config();

		$this->assertIsArray($config);
		$this->assertTrue($config['enabled']);
		$this->assertEquals(10, $config['failure_threshold']);
		$this->assertEquals(600, $config['timeout']);
	}

	/**
	 * Test Feature Flags
	 */
	public function test_feature_flags() {
		// Test default (false)
		$this->assertFalse($this->config->is_feature_enabled('test_feature'));

		// Test default override
		$this->assertTrue($this->config->is_feature_enabled('test_feature', true));

		// Enable feature
		$this->config->enable_feature('test_feature');

		// Verify enabled
		// Note: The mock update_option sets the value in $GLOBALS['aips_test_options']
		// But AIPS_Config caches feature flags in private property $feature_flags.
		// However, enable_feature updates both cache and option.
		$this->assertTrue($this->config->is_feature_enabled('test_feature'));

		// Disable feature
		$this->config->disable_feature('test_feature');
		$this->assertFalse($this->config->is_feature_enabled('test_feature'));
	}

	/**
	 * Test Environment Detection
	 */
	public function test_environment() {
		// By default in test environment (mocked via is_debug_mode or WP_ENVIRONMENT_TYPE check)
		// AIPS_Config checks defined('WP_ENVIRONMENT_TYPE').
		// bootstrap.php does not define WP_ENVIRONMENT_TYPE.
		// It checks defined('AIPS_DEBUG') && AIPS_DEBUG.

		// Assuming AIPS_DEBUG is not defined, is_debug_mode() returns false.
		// So is_production() returns true.

		$this->assertTrue($this->config->is_production());
		$this->assertFalse($this->config->is_development());
		$this->assertEquals('production', $this->config->get_environment());
	}
}
