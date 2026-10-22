<?php
/**
 * Tests provider-agnostic availability checks.
 *
 * Verifies active-provider readiness checks and the surfaces that were
 * previously hard-coded to class_exists('Meow_MWAI_Core').
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

if (!function_exists('aips_test_wp_ai_client_connector_configured')) {
    function aips_test_wp_ai_client_connector_configured($configured) {
        global $aips_wp_ai_client_test_configured;

        return isset($aips_wp_ai_client_test_configured)
            ? (bool) $aips_wp_ai_client_test_configured
            : $configured;
    }
}

class Test_AIPS_Provider_Availability extends WP_UnitTestCase {

    public function setUp(): void {
        global $aips_wp_ai_client_test_configured;

        parent::setUp();
        $aips_wp_ai_client_test_configured = true;
        add_filter('aips_wp_ai_client_has_configured_connector', 'aips_test_wp_ai_client_connector_configured');
        AIPS_AI_Provider_Factory::reset_cache();
    }

    public function tearDown(): void {
        global $aips_wp_ai_client_test_builder, $aips_wp_ai_client_test_configured, $mwai;

        $aips_wp_ai_client_test_builder = null;
        $aips_wp_ai_client_test_configured = null;
        $mwai = null;
        remove_filter('aips_wp_ai_client_has_configured_connector', 'aips_test_wp_ai_client_connector_configured');
        delete_option('aips_ai_provider');
        AIPS_AI_Provider_Factory::reset_cache();
        parent::tearDown();
    }

    private function make_ready_wp_ai_client() {
        global $aips_wp_ai_client_test_builder;

        if (!function_exists('wp_ai_client_prompt') || !class_exists('AIPS_Test_WP_AI_Client_Builder')) {
            $this->markTestSkipped('WP AI Client test fakes not loaded (run with the full suite).');
        }

        $aips_wp_ai_client_test_builder = new AIPS_Test_WP_AI_Client_Builder();
    }

    public function test_has_available_provider_with_wp_ai_client_only() {
        global $mwai;

        $mwai = null;
        $this->make_ready_wp_ai_client();

        $this->assertTrue(AIPS_AI_Provider_Factory::has_available_provider());
    }

    public function test_has_available_provider_false_when_nothing_ready() {
        global $mwai, $aips_wp_ai_client_test_builder, $aips_wp_ai_client_test_configured;

        $mwai = null;
        $aips_wp_ai_client_test_builder = new WP_Error('no_connector', 'Nothing configured.');
        $aips_wp_ai_client_test_configured = false;

        $this->assertFalse(AIPS_AI_Provider_Factory::has_available_provider());
    }

    public function test_has_available_provider_reflects_selected_provider_readiness() {
        global $mwai;

        $mwai = null;
        $this->make_ready_wp_ai_client();

        update_option('aips_ai_provider', 'meow');

        $this->assertFalse(AIPS_AI_Provider_Factory::has_available_provider());
        $this->assertTrue(AIPS_AI_Provider_Factory::has_any_available_provider());
    }

    public function test_campaign_warnings_omit_missing_provider_when_wp_ai_client_ready() {
        global $mwai;

        $mwai = null;
        $this->make_ready_wp_ai_client();
        update_option('aips_ai_provider', 'wp_ai_client');

        $warnings = $this->build_campaign_warnings();
        $types = wp_list_pluck($warnings, 'type');

        $this->assertNotContains('missing_ai_provider', $types);
        $this->assertNotContains('missing_ai_engine', $types);
    }

    public function test_campaign_warnings_report_missing_provider_when_nothing_ready() {
        global $mwai, $aips_wp_ai_client_test_builder, $aips_wp_ai_client_test_configured;

        $mwai = null;
        $aips_wp_ai_client_test_builder = new WP_Error('no_connector', 'Nothing configured.');
        $aips_wp_ai_client_test_configured = false;

        $warnings = $this->build_campaign_warnings();
        $types = wp_list_pluck($warnings, 'type');

        $this->assertContains('missing_ai_provider', $types);
    }

    /**
     * Invoke AIPS_Campaigns_Controller::build_campaign_warnings() with neutral
     * inputs so only the provider-availability warning can differ.
     *
     * @return array
     */
    private function build_campaign_warnings() {
        $controller = new AIPS_Campaigns_Controller();
        $method = new ReflectionMethod(AIPS_Campaigns_Controller::class, 'build_campaign_warnings');
        $method->setAccessible(true);

        $campaign = (object) array('id' => 1, 'status' => 'active', 'is_archived' => 0);
        $health = array(
            'inactive_schedule_count'      => 0,
            'empty_template_prompt_count'  => 0,
            'has_future_run'               => true,
            'failed_last_run'              => 0,
        );

        return $method->invoke($controller, $campaign, $health);
    }
}
