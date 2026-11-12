<?php
/**
 * Test cases for AIPS_Cache_Factory named-instance guards.
 *
 * Split out of Test_AIPS_Cache_Array_Driver.php: PHPUnit only runs the
 * test class named after its file, so classes sharing a file never ran.
 *
 * @package AI_Post_Scheduler
 */

// ============================================================================
// Additional tests added after review
// ============================================================================

/**
 * @covers AIPS_Cache_Factory::named
 */
class Test_AIPS_Cache_Factory_Named_Guard extends WP_UnitTestCase {

public function setUp(): void {
parent::setUp();
AIPS_Cache_Factory::reset();
}

public function tearDown(): void {
AIPS_Cache_Factory::reset();
parent::tearDown();
}

/**
 * When named() is called with a driver_name for an already-registered
 * instance, it must return the existing instance unchanged.
 */
public function test_named_ignores_driver_for_existing_instance() {
$first = AIPS_Cache_Factory::named( 'guarded', 'array' );

// Second call with a different driver: existing instance must be returned.
$second = AIPS_Cache_Factory::named( 'guarded', 'wp_object_cache' );

$this->assertSame( $first, $second, 'named() must return existing instance, ignoring driver arg.' );
$this->assertInstanceOf( 'AIPS_Cache_Array_Driver', $second->get_driver() );
}
}
