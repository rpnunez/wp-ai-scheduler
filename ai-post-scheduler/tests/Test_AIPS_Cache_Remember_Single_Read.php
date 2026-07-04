<?php
/**
 * @group cache
 */
class Test_AIPS_Cache_Remember_Single_Read extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		update_option( 'aips_enable_cache_system', '1' );
		AIPS_Cache::reset_system_enabled_flag();
	}

	private function make_counting_cache( array &$calls ): AIPS_Cache {
		$driver = new class( $calls ) implements AIPS_Cache_Driver {
			private $calls;
			private $store = array();
			public function __construct( &$calls ) { $this->calls =& $calls; }
			public function get( $key, $group = 'default' ) {
				$this->calls['get'] = ( $this->calls['get'] ?? 0 ) + 1;
				return $this->store[ $group ][ $key ] ?? null;
			}
			public function set( $key, $value, $ttl = 0, $group = 'default' ) {
				$this->calls['set'] = ( $this->calls['set'] ?? 0 ) + 1;
				$this->store[ $group ][ $key ] = $value;
				return true;
			}
			public function delete( $key, $group = 'default' ) { unset( $this->store[ $group ][ $key ] ); return true; }
			public function flush() { $this->store = array(); return true; }
			public function has( $key, $group = 'default' ) {
				$this->calls['has'] = ( $this->calls['has'] ?? 0 ) + 1;
				return isset( $this->store[ $group ][ $key ] );
			}
		};
		return new AIPS_Cache( $driver );
	}

	public function test_remember_hit_uses_single_driver_read() {
		$calls = array();
		$cache = $this->make_counting_cache( $calls );
		$cache->set( 'k', 'cached', 60 );
		$calls = array();

		$result = $cache->remember( 'k', 60, function () { return 'fresh'; } );

		$this->assertSame( 'cached', $result );
		$this->assertSame( 1, $calls['get'] ?? 0, 'Hit must cost exactly one driver get().' );
		$this->assertArrayNotHasKey( 'has', $calls, 'remember() must not call has().' );
	}

	public function test_remember_miss_computes_and_stores() {
		$calls = array();
		$cache = $this->make_counting_cache( $calls );

		$result = $cache->remember( 'k', 60, function () { return 'fresh'; } );

		$this->assertSame( 'fresh', $result );
		$this->assertSame( 1, $calls['set'] ?? 0 );
	}
}
