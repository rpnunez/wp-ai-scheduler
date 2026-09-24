<?php
/**
 * Test cases for AIPS_Cache_Factory in disabled mode.
 *
 * Split out of Test_AIPS_Cache_Array_Driver.php: PHPUnit only runs the
 * test class named after its file, so classes sharing a file never ran.
 *
 * @package AI_Post_Scheduler
 */

// ============================================================================
// AIPS_Cache_Factory disabled-mode tests
// ============================================================================

/**
 * @covers AIPS_Cache_Factory
 */
class Test_AIPS_Cache_Factory_Disabled extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		AIPS_Cache_Factory::reset();
		update_option( 'aips_enable_cache_system', '0' );
		AIPS_Cache::reset_system_enabled_flag();
	}

	public function tearDown(): void {
		update_option( 'aips_enable_cache_system', '1' );
		AIPS_Cache::reset_system_enabled_flag();
		AIPS_Cache_Factory::reset();
		parent::tearDown();
	}

	public function test_make_driver_returns_array_driver_when_cache_disabled() {
		// Even if the configured driver is something else, the factory must
		// return an ArrayDriver without attempting other driver setup.
		update_option( 'aips_cache_driver', 'wp_object_cache' );

		$driver = AIPS_Cache_Factory::make_driver();
		$this->assertInstanceOf( 'AIPS_Cache_Array_Driver', $driver );
	}

	public function test_make_driver_ignores_explicit_driver_name_when_disabled() {
		// An explicit $driver_name argument must also be ignored when disabled.
		$driver = AIPS_Cache_Factory::make_driver( 'wp_object_cache' );
		$this->assertInstanceOf( 'AIPS_Cache_Array_Driver', $driver );
	}
}
