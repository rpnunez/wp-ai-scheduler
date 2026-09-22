<?php
/**
 * Test cases for AIPS_Cache_Factory named instances.
 *
 * Split out of Test_AIPS_Cache_Array_Driver.php: PHPUnit only runs the
 * test class named after its file, so classes sharing a file never ran.
 *
 * @package AI_Post_Scheduler
 */

// ============================================================================
// AIPS_Cache_Factory — named instance tests
// ============================================================================

/**
 * @covers AIPS_Cache_Factory
 */
class Test_AIPS_Cache_Factory_Named extends WP_UnitTestCase {

public function setUp(): void {
parent::setUp();
AIPS_Cache_Factory::reset();
}

public function tearDown(): void {
AIPS_Cache_Factory::reset();
parent::tearDown();
}

// ------------------------------------------------------------------
// named()
// ------------------------------------------------------------------

public function test_named_returns_cache_instance() {
$cache = AIPS_Cache_Factory::named( 'my_cache' );
$this->assertInstanceOf( 'AIPS_Cache', $cache );
}

public function test_named_with_explicit_driver() {
$cache  = AIPS_Cache_Factory::named( 'tmpl', 'array' );
$driver = $cache->get_driver();
$this->assertInstanceOf( 'AIPS_Cache_Array_Driver', $driver );
}

public function test_named_same_name_returns_same_instance() {
$a = AIPS_Cache_Factory::named( 'shared' );
$b = AIPS_Cache_Factory::named( 'shared' );
$this->assertSame( $a, $b );
}

public function test_named_different_names_return_different_instances() {
$a = AIPS_Cache_Factory::named( 'cache_a' );
$b = AIPS_Cache_Factory::named( 'cache_b' );
$this->assertNotSame( $a, $b );
}

public function test_named_instances_have_independent_state() {
$a = AIPS_Cache_Factory::named( 'ns_a', 'array' );
$b = AIPS_Cache_Factory::named( 'ns_b', 'array' );

$a->set( 'x', 'from_a' );
$b->set( 'x', 'from_b' );

$this->assertSame( 'from_a', $a->get( 'x' ) );
$this->assertSame( 'from_b', $b->get( 'x' ) );
}

public function test_named_legacy_session_driver_maps_to_wp_object_cache_driver() {
$cache  = AIPS_Cache_Factory::named( 'sess_cache', 'session' );
$driver = $cache->get_driver();
$this->assertInstanceOf( 'AIPS_Cache_Wp_Object_Cache_Driver', $driver );
}

// ------------------------------------------------------------------
// register()
// ------------------------------------------------------------------

public function test_register_pre_wires_named_instance() {
$my_cache = new AIPS_Cache( new AIPS_Cache_Array_Driver() );
AIPS_Cache_Factory::register( 'custom', $my_cache );

$this->assertSame( $my_cache, AIPS_Cache_Factory::named( 'custom' ) );
}

public function test_register_replaces_existing_instance() {
$old = new AIPS_Cache( new AIPS_Cache_Array_Driver() );
AIPS_Cache_Factory::register( 'replaceable', $old );

$new = new AIPS_Cache( new AIPS_Cache_Array_Driver() );
AIPS_Cache_Factory::register( 'replaceable', $new );

$this->assertSame( $new, AIPS_Cache_Factory::named( 'replaceable' ) );
$this->assertNotSame( $old, AIPS_Cache_Factory::named( 'replaceable' ) );
}

// ------------------------------------------------------------------
// reset() clears named instances
// ------------------------------------------------------------------

public function test_reset_clears_named_instances() {
$a = AIPS_Cache_Factory::named( 'will_be_cleared', 'array' );
AIPS_Cache_Factory::reset();
$b = AIPS_Cache_Factory::named( 'will_be_cleared', 'array' );

$this->assertNotSame( $a, $b, 'After reset, named() should return a fresh instance.' );
}
}
