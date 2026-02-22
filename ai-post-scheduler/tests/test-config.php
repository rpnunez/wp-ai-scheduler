<?php
/**
 * Class Test_AIPS_Config
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Config_Test extends WP_UnitTestCase {

    /**
     * @var AIPS_Config
     */
    private $config;

    public function setUp(): void {
        parent::setUp();
        $this->config = AIPS_Config::get_instance();
    }

    public function test_get_instance() {
        $this->assertInstanceOf('AIPS_Config', $this->config);
        $instance2 = AIPS_Config::get_instance();
        $this->assertSame($this->config, $instance2);
    }

    public function test_get_default_options() {
        $defaults = $this->config->get_default_options();
        $this->assertIsArray($defaults);
        $this->assertArrayHasKey('aips_retry_max_attempts', $defaults);
        $this->assertEquals(3, $defaults['aips_retry_max_attempts']);
    }

    public function test_get_option_with_default() {
        // Test with default fallback
        $this->assertEquals(3, $this->config->get_option('aips_retry_max_attempts'));

        // Test with custom default
        $this->assertEquals('custom', $this->config->get_option('non_existent_option', 'custom'));
    }

    public function test_get_option_stored_value() {
        // Mock get_option via update_option (which our mock environment supports via $GLOBALS['aips_test_options'])
        update_option('test_option', 'stored_value');
        $this->assertEquals('stored_value', $this->config->get_option('test_option'));
    }

    public function test_feature_flags() {
        // Test default state (disabled)
        $this->assertFalse($this->config->is_feature_enabled('test_feature'));

        // Enable feature
        $this->config->enable_feature('test_feature');

        // Should be enabled now (mock update_option updates the internal array in Config?)
        // Wait, AIPS_Config loads feature flags in constructor. Since it's a singleton, it won't reload unless we manually trigger it or bypass singleton.
        // But enable_feature updates the internal array $this->feature_flags.
        $this->assertTrue($this->config->is_feature_enabled('test_feature'));

        // Disable feature
        $this->config->disable_feature('test_feature');
        $this->assertFalse($this->config->is_feature_enabled('test_feature'));
    }

    public function test_environment_detection() {
        // By default in tests, it might be development or production depending on WP_ENVIRONMENT_TYPE constant.
        // In our bootstrap, we didn't define WP_ENVIRONMENT_TYPE, but we might have defined AIPS_DEBUG.

        // Let's just check that it returns a boolean
        $this->assertIsBool($this->config->is_production());
        $this->assertIsBool($this->config->is_development());

        // Since we can't easily redefine constants in PHPUnit once defined, we can't fully test both branches here without runInSeparateProcess.
        // But we can check consistency
        if ($this->config->is_production()) {
            $this->assertFalse($this->config->is_development());
        } else {
            $this->assertTrue($this->config->is_development());
        }
    }

    public function test_get_retry_config() {
        $config = $this->config->get_retry_config();
        $this->assertIsArray($config);
        $this->assertArrayHasKey('max_attempts', $config);
        $this->assertEquals(3, $config['max_attempts']);
    }
}
