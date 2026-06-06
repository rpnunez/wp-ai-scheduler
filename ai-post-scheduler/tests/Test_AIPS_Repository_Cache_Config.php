<?php
/**
 * Tests for AIPS_Repository_Cache_Config.
 *
 * @package AI_Post_Scheduler
 */

if (!class_exists('AIPS_Repository_Cache_Config')) {
	require_once dirname(__DIR__) . '/includes/class-aips-repository-cache-config.php';
}

class Test_AIPS_Repository_Cache_Config extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		AIPS_Cache_Factory::reset();
		update_option( 'aips_cache_driver', 'wp_object_cache' );
		update_option( 'aips_enable_cache_system', '1' );
		AIPS_Cache::reset_system_enabled_flag();
	}

	public function tearDown(): void {
		AIPS_Cache_Factory::reset();
		delete_option( 'aips_cache_driver' );
		delete_option( 'aips_enable_cache_system' );
		AIPS_Cache::reset_system_enabled_flag();
		remove_all_filters( 'wp_doing_cron' );
		parent::tearDown();
	}

	public function test_get_tier_config_returns_request_defaults() {
		$config = AIPS_Repository_Cache_Config::get_tier_config( 'request' );

		$this->assertSame( 'request', $config['tier'] );
		$this->assertSame( 'array', $config['driver_name'] );
		$this->assertSame( 0, $config['default_ttl'] );
		$this->assertFalse( $config['persistent_allowed'] );
		$this->assertFalse( $config['bypass_on_cron'] );
		$this->assertFalse( $config['allow_stale_reads'] );
	}

	public function test_get_tier_config_returns_configured_driver_for_persistent_tiers() {
		$config = AIPS_Repository_Cache_Config::get_tier_config( 'medium' );

		$this->assertSame( 'medium', $config['tier'] );
		$this->assertSame( 'wp_object_cache', $config['driver_name'] );
		$this->assertTrue( $config['persistent_allowed'] );
		$this->assertSame( HOUR_IN_SECONDS, $config['default_ttl'] );
		$this->assertTrue( $config['allow_stale_reads'] );
	}

	public function test_get_tier_config_falls_back_to_array_for_unsupported_persistent_driver() {
		update_option( 'aips_cache_driver', 'session' );

		$config = AIPS_Repository_Cache_Config::get_tier_config( 'medium' );

		$this->assertSame( 'array', $config['driver_name'] );
	}

	public function test_get_tier_config_returns_none_defaults_for_unknown_tier() {
		$config = AIPS_Repository_Cache_Config::get_tier_config( 'unknown' );

		$this->assertSame( 'none', $config['tier'] );
		$this->assertSame( 'none', $config['driver_name'] );
		$this->assertSame( 0, $config['default_ttl'] );
		$this->assertFalse( $config['persistent_allowed'] );
		$this->assertTrue( $config['bypass_on_cron'] );
		$this->assertFalse( $config['allow_stale_reads'] );
	}

	public function test_resolve_ttl_uses_explicit_policy_ttl_when_present() {
		$this->assertSame(
			45,
			AIPS_Repository_Cache_Config::resolve_ttl(
				array(
					'tier' => 'long',
					'ttl'  => 45,
				)
			)
		);
	}

	public function test_resolve_ttl_uses_tier_default_when_policy_has_no_ttl() {
		$this->assertSame(
			5 * MINUTE_IN_SECONDS,
			AIPS_Repository_Cache_Config::resolve_ttl(
				array(
					'tier' => 'short',
				)
			)
		);
	}

	public function test_resolve_cache_instance_returns_named_array_cache_for_request_tier() {
		$cache = AIPS_Repository_Cache_Config::resolve_cache_instance(
			'aips_repository_cache',
			array(
				'tier' => 'request',
			)
		);

		$this->assertInstanceOf( 'AIPS_Cache', $cache );
		$this->assertInstanceOf( 'AIPS_Cache_Array_Driver', $cache->get_driver() );
	}

	public function test_request_tier_cache_does_not_persist_across_factory_reset() {
		$first_cache = AIPS_Repository_Cache_Config::resolve_cache_instance(
			'aips_repository_cache',
			array(
				'tier' => 'request',
			)
		);

		$this->assertInstanceOf( 'AIPS_Cache', $first_cache );
		$first_cache->set( 'request_only_key', 'value', 0, 'test_group' );
		$this->assertSame( 'value', $first_cache->get( 'request_only_key', 'test_group' ) );

		AIPS_Cache_Factory::reset();

		$second_cache = AIPS_Repository_Cache_Config::resolve_cache_instance(
			'aips_repository_cache',
			array(
				'tier' => 'request',
			)
		);

		$this->assertInstanceOf( 'AIPS_Cache', $second_cache );
		$this->assertInstanceOf( 'AIPS_Cache_Array_Driver', $second_cache->get_driver() );
		$this->assertNull( $second_cache->get( 'request_only_key', 'test_group' ) );
	}

	public function test_resolve_cache_instance_returns_named_persistent_cache_for_medium_tier() {
		$cache = AIPS_Repository_Cache_Config::resolve_cache_instance(
			'aips_repository_cache',
			array(
				'tier' => 'medium',
			)
		);

		$this->assertInstanceOf( 'AIPS_Cache', $cache );
		$this->assertInstanceOf( 'AIPS_Cache_Wp_Object_Cache_Driver', $cache->get_driver() );
	}

	public function test_resolve_cache_instance_reuses_named_cache_for_same_group_and_tier() {
		$first = AIPS_Repository_Cache_Config::resolve_cache_instance(
			'aips_repository_cache',
			array(
				'tier' => 'medium',
			)
		);

		$second = AIPS_Repository_Cache_Config::resolve_cache_instance(
			'aips_repository_cache',
			array(
				'tier' => 'medium',
			)
		);

		$this->assertSame( $first, $second );
	}

	public function test_resolve_cache_instance_returns_null_for_none_tier() {
		$this->assertNull(
			AIPS_Repository_Cache_Config::resolve_cache_instance(
				'aips_repository_cache',
				array(
					'tier' => 'none',
				)
			)
		);
	}

	public function test_resolve_cache_instance_bypasses_during_cron_when_tier_defaults_to_bypass() {
		add_filter( 'wp_doing_cron', '__return_true' );

		$this->assertNull(
			AIPS_Repository_Cache_Config::resolve_cache_instance(
				'aips_repository_cache',
				array(
					'tier' => 'short',
				)
			)
		);
	}

	public function test_resolve_cache_instance_allows_policy_to_override_cron_bypass() {
		add_filter( 'wp_doing_cron', '__return_true' );

		$cache = AIPS_Repository_Cache_Config::resolve_cache_instance(
			'aips_repository_cache',
			array(
				'tier'            => 'short',
				'bypass_on_cron'  => false,
			)
		);

		$this->assertInstanceOf( 'AIPS_Cache', $cache );
		$this->assertInstanceOf( 'AIPS_Cache_Wp_Object_Cache_Driver', $cache->get_driver() );
	}
}