<?php
/**
 * Test cases for AIPS_Cache_Wp_Object_Cache_Driver.
 *
 * Split out of Test_AIPS_Cache_Array_Driver.php: PHPUnit only runs the
 * test class named after its file, so classes sharing a file never ran.
 *
 * @package AI_Post_Scheduler
 */

// ============================================================================
// AIPS_Cache_Wp_Object_Cache_Driver tests
// ============================================================================

/**
 * @covers AIPS_Cache_Wp_Object_Cache_Driver
 */
class Test_AIPS_Cache_Wp_Object_Cache_Driver extends WP_UnitTestCase {

	/** @var AIPS_Cache_Wp_Object_Cache_Driver */
	private $driver;

	public function setUp(): void {
		parent::setUp();
		// Reset the in-process store before each test.
		$GLOBALS['_aips_test_wp_cache'] = array();
		$this->driver = new AIPS_Cache_Wp_Object_Cache_Driver( 'aips' );
	}

	public function test_set_and_get() {
		$this->driver->set( 'wpc_key', 'wpc_value' );
		$this->assertSame( 'wpc_value', $this->driver->get( 'wpc_key' ) );
	}

	public function test_get_returns_null_on_miss() {
		$this->assertNull( $this->driver->get( 'wpc_missing' ) );
	}

	public function test_delete_removes_entry() {
		$this->driver->set( 'del', 'x' );
		$this->driver->delete( 'del' );
		$this->assertNull( $this->driver->get( 'del' ) );
	}

	public function test_has_returns_correct_value() {
		$this->assertFalse( $this->driver->has( 'no_key' ) );
		$this->driver->set( 'yes_key', 1 );
		$this->assertTrue( $this->driver->has( 'yes_key' ) );
	}

	public function test_flush_clears_store() {
		$this->driver->set( 'a', 1 );
		$this->driver->flush();
		$this->assertNull( $this->driver->get( 'a' ) );
	}

	public function test_flush_does_not_purge_unrelated_wp_cache_entries() {
		$this->driver->set( 'plugin_key', 'plugin_val' );

		// Store something directly in wp_cache outside our driver's namespace.
		wp_cache_set( 'external_key', 'external_val', 'some_other_plugin' );

		$this->driver->flush();

		// Driver's own entry becomes unreachable.
		$this->assertNull( $this->driver->get( 'plugin_key' ) );

		// The unrelated WP object cache entry is untouched.
		$this->assertSame( 'external_val', wp_cache_get( 'external_key', 'some_other_plugin' ) );
	}

	public function test_flush_returns_true() {
		$this->assertTrue( $this->driver->flush() );
	}

	/**
	 * Object-cache group suffix for the driver's current flush generation.
	 *
	 * @return string
	 */
	private function generation_suffix() {
		$generation = new ReflectionProperty( AIPS_Cache_Wp_Object_Cache_Driver::class, 'generation' );
		$generation->setAccessible( true );
		$value = (int) $generation->getValue( $this->driver );

		return $value > 0 ? '_g' . $value : '';
	}

	public function test_groups_are_namespaced_under_base() {
		$this->driver->set( 'key', 'custom_group_val', 0, 'posts' );
		// Stored in the WP object cache group 'aips_posts'.
		$this->assertSame( 'custom_group_val', wp_cache_get( 'key', 'aips_posts' . $this->generation_suffix() ) );
	}

	public function test_default_group_maps_to_base_group() {
		$this->driver->set( 'key', 'val' );
		// 'default' group maps to the 'aips' base group.
		$this->assertSame( 'val', wp_cache_get( 'key', 'aips' . $this->generation_suffix() ) );
	}
}
