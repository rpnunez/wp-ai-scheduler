<?php
/**
 * Test cases for AIPS_Cache in disabled mode.
 *
 * Split out of Test_AIPS_Cache_Array_Driver.php: PHPUnit only runs the
 * test class named after its file, so classes sharing a file never ran.
 *
 * @package AI_Post_Scheduler
 */

// ============================================================================
// AIPS_Cache disabled-mode tests
// ============================================================================

/**
 * @covers AIPS_Cache
 */
class Test_AIPS_Cache_Disabled extends WP_UnitTestCase {

	/** @var AIPS_Cache */
	private $cache;

	public function setUp(): void {
		parent::setUp();

		// Disable the cache system.
		update_option( 'aips_enable_cache_system', '0' );
		AIPS_Cache::reset_system_enabled_flag();

		$this->cache = new AIPS_Cache( new AIPS_Cache_Array_Driver() );
	}

	public function tearDown(): void {
		// Re-enable the cache system and reset the static flag.
		update_option( 'aips_enable_cache_system', '1' );
		AIPS_Cache::reset_system_enabled_flag();

		AIPS_Config::get_instance()->flush_option_cache();
		AIPS_Cache_Factory::reset();

		parent::tearDown();
	}

	// ------------------------------------------------------------------
	// get()
	// ------------------------------------------------------------------

	public function test_get_returns_default_when_disabled() {
		// Nothing is stored; should return $default directly.
		$this->assertNull( $this->cache->get( 'key' ) );
	}

	public function test_get_returns_caller_default_when_disabled() {
		$this->assertSame( 'my_default', $this->cache->get( 'key', 'default', 'my_default' ) );
	}

	// ------------------------------------------------------------------
	// set() / has()
	// ------------------------------------------------------------------

	public function test_set_returns_true_when_disabled() {
		$result = $this->cache->set( 'key', 'value' );
		$this->assertTrue( $result );
	}

	public function test_set_does_not_persist_value_when_disabled() {
		$this->cache->set( 'key', 'stored' );
		// get() must still return null — the value was never written.
		$this->assertNull( $this->cache->get( 'key' ) );
	}

	public function test_has_returns_false_when_disabled() {
		$this->cache->set( 'key', 'value' );
		$this->assertFalse( $this->cache->has( 'key' ) );
	}

	// ------------------------------------------------------------------
	// delete() / flush()
	// ------------------------------------------------------------------

	public function test_delete_returns_true_when_disabled() {
		$this->assertTrue( $this->cache->delete( 'key' ) );
	}

	public function test_flush_returns_true_when_disabled() {
		$this->assertTrue( $this->cache->flush() );
	}

	// ------------------------------------------------------------------
	// remember()
	// ------------------------------------------------------------------

	public function test_remember_always_calls_callback_when_disabled() {
		$calls = 0;
		$cb    = function() use ( &$calls ) {
			$calls++;
			return 'fresh';
		};

		$result1 = $this->cache->remember( 'key', 60, $cb );
		$result2 = $this->cache->remember( 'key', 60, $cb );

		$this->assertSame( 'fresh', $result1 );
		$this->assertSame( 'fresh', $result2 );
		// Both calls must have invoked the callback because nothing is cached.
		$this->assertSame( 2, $calls );
	}

	public function test_remember_does_not_store_value_when_disabled() {
		$this->cache->remember( 'memo', 60, function() {
			return 'computed';
		});

		// A subsequent has() call must confirm nothing was stored.
		$this->assertFalse( $this->cache->has( 'memo' ) );
	}

	// ------------------------------------------------------------------
	// increment() / decrement()
	// ------------------------------------------------------------------

	public function test_increment_returns_step_when_disabled() {
		// No prior state; should return 0 + step.
		$this->assertSame( 1, $this->cache->increment( 'counter' ) );
		$this->assertSame( 5, $this->cache->increment( 'counter', 5 ) );
	}

	public function test_decrement_returns_negative_step_when_disabled() {
		$this->assertSame( -1, $this->cache->decrement( 'counter' ) );
		$this->assertSame( -3, $this->cache->decrement( 'counter', 3 ) );
	}
}
