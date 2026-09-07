<?php
/**
 * Tests for AIPS_AI_Provider_Factory.
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

class Test_AIPS_AI_Provider_Factory extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        // The factory caches provider instances per request, and the Meow adapter
        // caches $mwai per instance — reset so each test sees its own globals.
        AIPS_AI_Provider_Factory::reset_cache();
    }

    public function tearDown(): void {
        delete_option('aips_ai_provider');
        AIPS_AI_Provider_Factory::reset_cache();
        parent::tearDown();
    }

    public function test_explicit_id_returns_matching_provider() {
        $provider = AIPS_AI_Provider_Factory::create('meow');
        $this->assertInstanceOf('AIPS_Meow_AI_Provider', $provider);

        $provider = AIPS_AI_Provider_Factory::create('wp_ai_client');
        $this->assertInstanceOf('AIPS_WP_AI_Client_Provider', $provider);
    }

    public function test_unknown_explicit_id_falls_through_to_autodetect() {
        // No backend available in the test environment → Null provider.
        $provider = AIPS_AI_Provider_Factory::create('does_not_exist');
        $this->assertInstanceOf('AIPS_Null_AI_Provider', $provider);
    }

    public function test_option_selects_provider() {
        update_option('aips_ai_provider', 'wp_ai_client');
        $provider = AIPS_AI_Provider_Factory::create();
        $this->assertInstanceOf('AIPS_WP_AI_Client_Provider', $provider);
    }

    public function test_autodetect_prefers_meow_when_available() {
        global $mwai;
        $original = $mwai;
        $mwai = new stdClass();

        try {
            $provider = AIPS_AI_Provider_Factory::create();
            $this->assertInstanceOf('AIPS_Meow_AI_Provider', $provider);
            $this->assertTrue($provider->is_available());
        } finally {
            $mwai = $original;
        }
    }

    public function test_returns_null_provider_when_none_available() {
        global $mwai;
        $original = $mwai;
        $mwai = null;

        try {
            $provider = AIPS_AI_Provider_Factory::create();
            $this->assertInstanceOf('AIPS_Null_AI_Provider', $provider);
            $this->assertFalse($provider->is_available());
        } finally {
            $mwai = $original;
        }
    }

    public function test_available_providers_lists_only_detected_backends() {
        global $mwai;
        $original = $mwai;
        $mwai = new stdClass();

        try {
            $available = AIPS_AI_Provider_Factory::available_providers();
            $this->assertArrayHasKey('meow', $available);
        } finally {
            $mwai = $original;
        }
    }

    public function test_all_providers_lists_every_known_backend() {
        $all = AIPS_AI_Provider_Factory::all_providers();
        $this->assertArrayHasKey('meow', $all);
        $this->assertArrayHasKey('wp_ai_client', $all);
    }
}
