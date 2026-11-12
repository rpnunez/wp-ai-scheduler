<?php
/**
 * Test cases for AIPS_Cache_Db_Driver.
 *
 * Split out of Test_AIPS_Cache_Array_Driver.php: PHPUnit only runs the
 * test class named after its file, so classes sharing a file never ran.
 * Rewritten against the real aips_cache table; the original cases targeted
 * a $wpdb stub (get_row_return_val) that no longer exists.
 *
 * @package AI_Post_Scheduler
 */

/**
 * @covers AIPS_Cache_Db_Driver
 */
class Test_AIPS_Cache_Db_Driver extends WP_UnitTestCase {

	/** @var AIPS_Cache_Db_Driver */
	private $driver;

	/**
	 * Per-test cache group, so rows can never leak between tests or runs.
	 *
	 * @var string
	 */
	private $group;

	public function setUp(): void {
		parent::setUp();
		$this->driver = new AIPS_Cache_Db_Driver();
		$this->group  = 'dbdrv-' . uniqid();
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'aips_cache', array( 'cache_group' => $this->group ) );
		remove_all_filters( 'query' );
		parent::tearDown();
	}

	/**
	 * Force a row's expiry into the past.
	 *
	 * @param string $key Cache key.
	 */
	private function expire( $key ) {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'aips_cache',
			array( 'expires_at' => 1 ),
			array(
				'cache_key'   => $key,
				'cache_group' => $this->group,
			)
		);
	}

	// ------------------------------------------------------------------
	// set()
	// ------------------------------------------------------------------

	public function test_set_with_ttl_returns_true() {
		$this->assertTrue( $this->driver->set( 'key', 'value', 3600, $this->group ) );
	}

	public function test_set_without_ttl_returns_true() {
		$this->assertTrue( $this->driver->set( 'key', 'value', 0, $this->group ) );
	}

	public function test_set_returns_false_when_db_has_error() {
		global $wpdb;

		// Break the REPLACE so wpdb records an error; set() checks last_error.
		add_filter(
			'query',
			function ( $query ) use ( $wpdb ) {
				if ( 0 === stripos( ltrim( $query ), 'REPLACE' ) && false !== strpos( $query, $wpdb->prefix . 'aips_cache' ) ) {
					return 'REPLACE INTO `' . $wpdb->prefix . 'aips_cache_missing_table` (x) VALUES (1)';
				}
				return $query;
			}
		);

		$suppress = $wpdb->suppress_errors( true );
		$result   = $this->driver->set( 'key', 'value', 0, $this->group );
		$wpdb->suppress_errors( $suppress );

		$this->assertFalse( $result );
	}

	// ------------------------------------------------------------------
	// get()
	// ------------------------------------------------------------------

	public function test_get_returns_null_on_miss() {
		$this->assertNull( $this->driver->get( 'missing', $this->group ) );
	}

	public function test_get_returns_value_on_hit_no_expiry() {
		$this->driver->set( 'my_key', 'cached_value', 0, $this->group );
		$this->assertSame( 'cached_value', $this->driver->get( 'my_key', $this->group ) );
	}

	public function test_get_returns_value_for_non_expired_row() {
		$this->driver->set( 'live_key', 42, 3600, $this->group );
		$this->assertSame( 42, $this->driver->get( 'live_key', $this->group ) );
	}

	public function test_get_returns_null_for_expired_row() {
		$this->driver->set( 'expired_key', 'stale', 3600, $this->group );
		$this->expire( 'expired_key' );

		$this->assertNull( $this->driver->get( 'expired_key', $this->group ) );
	}

	public function test_get_unserializes_array_value() {
		$data = array( 'foo' => 'bar', 'num' => 7 );
		$this->driver->set( 'arr_key', $data, 0, $this->group );

		$this->assertSame( $data, $this->driver->get( 'arr_key', $this->group ) );
	}

	public function test_scalar_types_round_trip() {
		foreach ( array( 'int' => 7, 'float' => 1.5, 'true' => true, 'false' => false, 'string' => '42' ) as $key => $value ) {
			$this->driver->set( $key, $value, 0, $this->group );
			$this->assertSame( $value, $this->driver->get( $key, $this->group ), $key );
		}
	}

	// ------------------------------------------------------------------
	// delete() / has()
	// ------------------------------------------------------------------

	public function test_delete_removes_the_entry() {
		$this->driver->set( 'any_key', 'value', 0, $this->group );

		$this->assertTrue( $this->driver->delete( 'any_key', $this->group ) );
		$this->assertNull( $this->driver->get( 'any_key', $this->group ) );
	}

	public function test_has_returns_false_on_miss() {
		$this->assertFalse( $this->driver->has( 'nope', $this->group ) );
	}

	public function test_has_returns_true_on_hit() {
		$this->driver->set( 'present_key', 'present', 0, $this->group );
		$this->assertTrue( $this->driver->has( 'present_key', $this->group ) );
	}

	// ------------------------------------------------------------------
	// purge_expired()
	// ------------------------------------------------------------------

	public function test_purge_expired_removes_only_expired_rows() {
		$this->driver->set( 'expired', 'old', 3600, $this->group );
		$this->driver->set( 'forever', 'kept', 0, $this->group );
		$this->expire( 'expired' );

		// Returns the number of rows deleted (other groups may contribute).
		$this->assertGreaterThanOrEqual( 1, (int) $this->driver->purge_expired() );
		$this->assertSame( 'kept', $this->driver->get( 'forever', $this->group ) );

		global $wpdb;
		$this->assertSame(
			'0',
			(string) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}aips_cache WHERE cache_group = %s AND cache_key = %s",
					$this->group,
					'expired'
				)
			)
		);
	}

	// ------------------------------------------------------------------
	// Key prefix / namespace
	// ------------------------------------------------------------------

	public function test_namespace_key_with_prefix() {
		$driver = new AIPS_Cache_Db_Driver( 'myprefix' );
		$method = new ReflectionMethod( 'AIPS_Cache_Db_Driver', 'namespace_key' );
		$method->setAccessible( true );

		$this->assertSame( 'myprefix:testkey', $method->invoke( $driver, 'testkey' ) );
	}

	public function test_namespace_key_without_prefix() {
		$method = new ReflectionMethod( 'AIPS_Cache_Db_Driver', 'namespace_key' );
		$method->setAccessible( true );

		$this->assertSame( 'testkey', $method->invoke( $this->driver, 'testkey' ) );
	}
}
