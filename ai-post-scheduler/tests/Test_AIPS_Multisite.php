<?php
/**
 * Unit tests for WordPress Multisite compatibility.
 *
 * Verifies network configuration management, capability guards, credential bridging,
 * quota enforcement, subdirectory URL detection, lifecycle table dropping, and blog
 * switching cache isolation.
 *
 * @package AI_Post_Scheduler
 * @since   3.8.0
 */

class Test_AIPS_Multisite extends WP_UnitTestCase {

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {
		parent::tear_down();

		// Clean up site options
		delete_site_option(AIPS_Network_Config::OPTION_KEY);
		delete_option(AIPS_Site_Quota_Service::OPTION_USAGE);
		AIPS_Network_Config::get_settings(true);
	}

	/**
	 * Test AIPS_Network_Config default values and updates.
	 */
	public function test_network_config_defaults_and_updates(): void {
		$defaults = AIPS_Network_Config::get_defaults();
		$this->assertIsArray($defaults);
		$this->assertArrayHasKey('allow_site_ai_override', $defaults);
		$this->assertArrayHasKey('share_network_ai_credentials', $defaults);
		$this->assertArrayHasKey('prevent_ai_generation', $defaults);
		$this->assertArrayHasKey('daily_quota_per_site', $defaults);
		$this->assertTrue($defaults['allow_site_ai_override']);
		$this->assertFalse($defaults['prevent_ai_generation']);

		// Test updating settings
		AIPS_Network_Config::update_settings(array(
			'ai_provider'           => 'openai',
			'ai_model'              => 'gpt-4o',
			'daily_quota_per_site'  => 50,
			'prevent_ai_generation' => true,
		));

		$this->assertSame('openai', AIPS_Network_Config::get_option('ai_provider'));
		$this->assertSame('gpt-4o', AIPS_Network_Config::get_option('ai_model'));
		$this->assertSame(50, AIPS_Network_Config::get_option('daily_quota_per_site'));
		$this->assertTrue(AIPS_Network_Config::is_network_ai_generation_prevented());
	}

	/**
	 * Test AIPS_Capabilities checks.
	 */
	public function test_capabilities_checks(): void {
		// Non-logged-in user
		wp_set_current_user(0);
		$this->assertFalse(AIPS_Capabilities::can_manage_destructive_tools());
		$this->assertFalse(AIPS_Capabilities::can_override_ai_settings());
		$this->assertFalse(AIPS_Capabilities::can_manage_network());

		// Administrator user
		$admin_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($admin_id);

		// In single-site environment:
		if (!is_multisite()) {
			$this->assertTrue(AIPS_Capabilities::can_manage_destructive_tools());
			$this->assertTrue(AIPS_Capabilities::can_override_ai_settings());
			$this->assertFalse(AIPS_Capabilities::can_manage_network());
		}
	}

	/**
	 * Test AIPS_Site_Quota_Service enforcement and recording.
	 */
	public function test_site_quota_service(): void {
		$blog_id = get_current_blog_id();

		// Initially quota should pass
		$this->assertTrue(AIPS_Site_Quota_Service::check_quota($blog_id, true));

		// Test emergency kill-switch
		AIPS_Network_Config::update_settings(array('prevent_ai_generation' => true));
		$result = AIPS_Site_Quota_Service::check_quota($blog_id);
		$this->assertWPError($result);
		$this->assertSame('network_ai_prevented', $result->get_error_code());

		// Re-enable generation and configure daily limit of 2 calls
		AIPS_Network_Config::update_settings(array(
			'prevent_ai_generation' => false,
			'daily_quota_per_site'  => 2,
		));

		// Record 1 call
		AIPS_Site_Quota_Service::record_call($blog_id);
		$usage = AIPS_Site_Quota_Service::get_usage($blog_id);
		$this->assertSame(1, $usage['daily_calls']);
		$this->assertSame(1, $usage['monthly_calls']);

		// Still within limit
		$this->assertTrue(AIPS_Site_Quota_Service::check_quota($blog_id, true));

		// Record second call (reaching limit of 2)
		AIPS_Site_Quota_Service::record_call($blog_id);
		$usage = AIPS_Site_Quota_Service::get_usage($blog_id);
		$this->assertSame(2, $usage['daily_calls']);

		// Next call should be blocked when quota is enforced
		$result = AIPS_Site_Quota_Service::check_quota($blog_id, true);
		$this->assertWPError($result);
		$this->assertSame('site_daily_quota_exceeded', $result->get_error_code());

		// Reset quota
		AIPS_Site_Quota_Service::reset_usage($blog_id);
		$usage = AIPS_Site_Quota_Service::get_usage($blog_id);
		$this->assertSame(0, $usage['daily_calls']);
		$this->assertTrue(AIPS_Site_Quota_Service::check_quota($blog_id, true));
	}

	/**
	 * Test AIPS_Credential_Bridge fallback filtering.
	 */
	public function test_credential_bridge_filtering(): void {
		// Without network sharing
		AIPS_Network_Config::update_settings(array(
			'share_network_ai_credentials' => false,
		));

		$default_mwai = AIPS_Credential_Bridge::filter_default_mwai_options(array('dummy' => 'val'));
		$this->assertSame(array('dummy' => 'val'), $default_mwai);

		// With network sharing enabled
		AIPS_Network_Config::update_settings(array(
			'share_network_ai_credentials' => true,
			'ai_provider'                  => 'openai',
			'ai_model'                     => 'gpt-4o',
		));

		$filtered = AIPS_Credential_Bridge::filter_default_mwai_options(array('dummy' => 'val'));
		$this->assertIsArray($filtered);
	}

	/**
	 * Test AIPS_Multisite_Lifecycle table dropping filter.
	 */
	public function test_lifecycle_filter_wpmu_drop_tables(): void {
		global $wpdb;
		$prefix = (function_exists('is_multisite') && is_multisite())
			? $wpdb->get_blog_prefix(2)
			: "{$wpdb->prefix}2_";

		$initial_tables = array("{$prefix}posts", "{$prefix}comments");
		$dropped_tables = AIPS_Multisite_Lifecycle::filter_wpmu_drop_tables($initial_tables, 2);

		$this->assertContains("{$prefix}posts", $dropped_tables);
		$this->assertContains("{$prefix}aips_templates", $dropped_tables);
		$this->assertContains("{$prefix}aips_schedule", $dropped_tables);
		$this->assertContains("{$prefix}aips_history", $dropped_tables);
		$this->assertContains("{$prefix}aips_link_index", $dropped_tables);
		$this->assertContains("{$prefix}aips_sources", $dropped_tables);

		// Verify all custom tables are accounted for
		$this->assertCount(count($initial_tables) + count(AIPS_DB_Manager::get_table_names()), $dropped_tables);
	}

	/**
	 * Test deterministic cron jitter calculation.
	 */
	public function test_cron_jitter_distribution(): void {
		$jitter1 = AIPS_Multisite::get_cron_jitter(1);
		$jitter2 = AIPS_Multisite::get_cron_jitter(2);
		$jitter3 = AIPS_Multisite::get_cron_jitter(3);

		$this->assertSame((1 * 37) % 1800, $jitter1);
		$this->assertSame((2 * 37) % 1800, $jitter2);
		$this->assertSame((3 * 37) % 1800, $jitter3);

		$this->assertNotSame($jitter1, $jitter2);
		$this->assertNotSame($jitter2, $jitter3);

		// Default on single site without blog_id parameter is 0
		if (!is_multisite()) {
			$this->assertSame(0, AIPS_Multisite::get_cron_jitter());
		}
	}

	/**
	 * Test switch_blog cache isolation.
	 */
	public function test_switch_blog_isolation(): void {
		$action_fired = false;
		$switched_to  = 0;

		add_action('aips_switch_blog', function($new_blog_id, $prev_blog_id) use (&$action_fired, &$switched_to) {
			$action_fired = true;
			$switched_to  = $new_blog_id;
		}, 10, 2);

		AIPS_Multisite::on_switch_blog(2, 1);

		$this->assertTrue($action_fired);
		$this->assertSame(2, $switched_to);
	}
}
