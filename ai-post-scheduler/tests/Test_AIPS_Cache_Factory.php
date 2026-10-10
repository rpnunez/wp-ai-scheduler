<?php
/**
 * Test cases for AIPS_Cache_Factory.
 *
 * Split out of Test_AIPS_Cache_Array_Driver.php: PHPUnit only runs the
 * test class named after its file, so classes sharing a file never ran.
 *
 * @package AI_Post_Scheduler
 */

/**
 * @covers AIPS_Cache_Factory
 */
class Test_AIPS_Cache_Factory extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		AIPS_Cache_Factory::reset();
	}

	public function tearDown(): void {
		AIPS_Cache_Factory::reset();
		parent::tearDown();
	}

	public function test_make_returns_cache_instance() {
		$cache = AIPS_Cache_Factory::make( 'array' );
		$this->assertInstanceOf( 'AIPS_Cache', $cache );
	}

	public function test_make_array_driver_returns_array_driver() {
		$cache  = AIPS_Cache_Factory::make( 'array' );
		$driver = $cache->get_driver();
		$this->assertInstanceOf( 'AIPS_Cache_Array_Driver', $driver );
	}

	public function test_make_wp_object_cache_driver() {
		$GLOBALS['_aips_test_wp_cache'] = array();
		$cache  = AIPS_Cache_Factory::make( 'wp_object_cache' );
		$driver = $cache->get_driver();
		$this->assertInstanceOf( 'AIPS_Cache_Wp_Object_Cache_Driver', $driver );
	}

	public function test_make_unknown_driver_falls_back_to_array() {
		$cache  = AIPS_Cache_Factory::make( 'nonexistent_driver' );
		$driver = $cache->get_driver();
		$this->assertInstanceOf( 'AIPS_Cache_Array_Driver', $driver );
	}


	public function test_instance_returns_same_object_on_repeated_calls() {
		$a = AIPS_Cache_Factory::instance();
		$b = AIPS_Cache_Factory::instance();
		$this->assertSame( $a, $b );
	}

	public function test_reset_clears_singleton() {
		$a = AIPS_Cache_Factory::instance();
		AIPS_Cache_Factory::reset();
		$b = AIPS_Cache_Factory::instance();
		$this->assertNotSame( $a, $b );
	}

	public function test_make_driver_array_returns_array_driver_instance() {
		$driver = AIPS_Cache_Factory::make_driver( 'array' );
		$this->assertInstanceOf( 'AIPS_Cache_Driver', $driver );
		$this->assertInstanceOf( 'AIPS_Cache_Array_Driver', $driver );
	}

	public function test_make_legacy_session_driver_migrates_to_wp_object_cache_driver() {
		$cache  = AIPS_Cache_Factory::make( 'session' );
		$driver = $cache->get_driver();
		$this->assertInstanceOf( 'AIPS_Cache_Wp_Object_Cache_Driver', $driver );
	}

	public function test_make_legacy_redis_driver_migrates_to_wp_object_cache_driver() {
		$cache  = AIPS_Cache_Factory::make( 'redis' );
		$driver = $cache->get_driver();
		$this->assertInstanceOf( 'AIPS_Cache_Wp_Object_Cache_Driver', $driver );
	}
}
