<?php
/**
 * Test cases for the AIPS Cache framework.
 *
 * Covers AIPS_Cache_Array_Driver, AIPS_Cache_Wp_Object_Cache_Driver, AIPS_Cache,
 * and AIPS_Cache_Factory. All tests run in the in-process fallback environment
 * (no WordPress test library required) so they never touch a real database.
 *
 * @package AI_Post_Scheduler
 * @since   2.3.0
 */

// ============================================================================
// AIPS_Cache_Array_Driver tests
// ============================================================================

/**
 * @covers AIPS_Cache_Array_Driver
 */
class Test_AIPS_Cache_Array_Driver extends WP_UnitTestCase {

	/** @var AIPS_Cache_Array_Driver */
	private $driver;

	public function setUp(): void {
		parent::setUp();
		$this->driver = new AIPS_Cache_Array_Driver();
	}

	// ------------------------------------------------------------------
	// get / set
	// ------------------------------------------------------------------

	public function test_set_and_get_string() {
		$this->driver->set( 'key1', 'hello' );
		$this->assertSame( 'hello', $this->driver->get( 'key1' ) );
	}

	public function test_get_returns_null_on_miss() {
		$this->assertNull( $this->driver->get( 'missing_key' ) );
	}

	public function test_set_and_get_array() {
		$data = array( 'a' => 1, 'b' => 2 );
		$this->driver->set( 'arr', $data );
		$this->assertSame( $data, $this->driver->get( 'arr' ) );
	}

	public function test_set_and_get_with_group() {
		$this->driver->set( 'key', 'group_value', 0, 'mygroup' );
		$this->assertSame( 'group_value', $this->driver->get( 'key', 'mygroup' ) );
	}

	public function test_different_groups_are_isolated() {
		$this->driver->set( 'key', 'value_a', 0, 'group_a' );
		$this->driver->set( 'key', 'value_b', 0, 'group_b' );

		$this->assertSame( 'value_a', $this->driver->get( 'key', 'group_a' ) );
		$this->assertSame( 'value_b', $this->driver->get( 'key', 'group_b' ) );
	}

	public function test_default_group_and_explicit_default_are_the_same() {
		$this->driver->set( 'shared', 'x' );
		$this->assertSame( 'x', $this->driver->get( 'shared', 'default' ) );
	}

	// ------------------------------------------------------------------
	// TTL / expiration
	// ------------------------------------------------------------------

	public function test_expired_entry_returns_null() {
		$this->driver->set( 'ttl_key', 'live', 3600 );

		// Force the expiry timestamp into the past via reflection so we can
		// verify the expiration path without sleeping.
		$prop = new ReflectionProperty( 'AIPS_Cache_Array_Driver', 'expiries' );
		$prop->setAccessible( true );
		$expiries                  = $prop->getValue( $this->driver );
		$expiries['default:ttl_key'] = time() - 1;
		$prop->setValue( $this->driver, $expiries );

		$this->assertNull( $this->driver->get( 'ttl_key' ) );
	}

	public function test_zero_ttl_does_not_expire() {
		$this->driver->set( 'perm', 'permanent', 0 );
		$this->assertSame( 'permanent', $this->driver->get( 'perm' ) );
	}

	// ------------------------------------------------------------------
	// delete
	// ------------------------------------------------------------------

	public function test_delete_removes_entry() {
		$this->driver->set( 'del_key', 'bye' );
		$this->driver->delete( 'del_key' );
		$this->assertNull( $this->driver->get( 'del_key' ) );
	}

	public function test_delete_non_existent_key_does_not_error() {
		$result = $this->driver->delete( 'nonexistent' );
		$this->assertTrue( $result );
	}

	public function test_delete_only_removes_matching_group() {
		$this->driver->set( 'k', 'a', 0, 'g1' );
		$this->driver->set( 'k', 'b', 0, 'g2' );
		$this->driver->delete( 'k', 'g1' );

		$this->assertNull( $this->driver->get( 'k', 'g1' ) );
		$this->assertSame( 'b', $this->driver->get( 'k', 'g2' ) );
	}

	// ------------------------------------------------------------------
	// has
	// ------------------------------------------------------------------

	public function test_has_returns_true_for_existing_key() {
		$this->driver->set( 'exist', 'yes' );
		$this->assertTrue( $this->driver->has( 'exist' ) );
	}

	public function test_has_returns_false_for_missing_key() {
		$this->assertFalse( $this->driver->has( 'nope' ) );
	}

	// ------------------------------------------------------------------
	// flush
	// ------------------------------------------------------------------

	public function test_flush_clears_all_entries() {
		$this->driver->set( 'a', 1 );
		$this->driver->set( 'b', 2 );
		$this->driver->flush();

		$this->assertNull( $this->driver->get( 'a' ) );
		$this->assertNull( $this->driver->get( 'b' ) );
	}

	public function test_flush_returns_true() {
		$this->assertTrue( $this->driver->flush() );
	}
}
