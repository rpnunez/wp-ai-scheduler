<?php
/**
 * Test Settings Rendering
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Test_Settings extends WP_UnitTestCase {
    private $settings;

    public function setUp(): void {
        parent::setUp();
        // Since we are not loading the full plugin, we need to mock or ensure constants are defined
        if (!defined('AIPS_PLUGIN_URL')) {
            define('AIPS_PLUGIN_URL', 'http://example.com/');
        }
        if (!defined('AIPS_VERSION')) {
            define('AIPS_VERSION', '1.0.0');
        }

        require_once dirname(dirname(__FILE__)) . '/ai-post-scheduler/includes/class-aips-settings.php';
        $this->settings = new AIPS_Settings();
    }

    public function test_unsplash_access_key_field_callback_renders_correctly() {
        // Set an option value
        update_option('aips_unsplash_access_key', 'test_key_123');

        // Capture output
        ob_start();
        $this->settings->unsplash_access_key_field_callback();
        $output = ob_get_clean();

        // Check for password input
        $this->assertStringContainsString('<input type="password"', $output);
        $this->assertStringContainsString('name="aips_unsplash_access_key"', $output);
        $this->assertStringContainsString('value="test_key_123"', $output);

        // Check for toggle button
        $this->assertStringContainsString('class="button aips-toggle-password"', $output);
        $this->assertStringContainsString('data-target="#aips_unsplash_access_key"', $output);

        // Check for copy button
        $this->assertStringContainsString('class="button aips-copy-input-btn"', $output);
        $this->assertStringContainsString('data-target="#aips_unsplash_access_key"', $output);
    }
}
