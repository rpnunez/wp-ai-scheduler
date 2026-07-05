<?php
/**
 * @group cache
 */
class Test_AIPS_Cache_Tag_Version_Memo extends WP_UnitTestCase {

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
				$this->store[ $group ][ $key ] = $value;
				return true;
			}
			public function delete( $key, $group = 'default' ) { unset( $this->store[ $group ][ $key ] ); return true; }
			public function flush() { $this->store = array(); return true; }
			public function has( $key, $group = 'default' ) { return isset( $this->store[ $group ][ $key ] ); }
		};
		return new AIPS_Cache( $driver );
	}

	public function test_repeated_get_tag_version_reads_driver_once() {
		$calls = array();
		$cache = $this->make_counting_cache( $calls );

		$this->assertSame( 1, $cache->get_tag_version( 'authors', 'aips_authors' ) );
		$this->assertSame( 1, $cache->get_tag_version( 'authors', 'aips_authors' ) );
		$this->assertSame( 1, $cache->get_tag_version( 'authors', 'aips_authors' ) );

		$this->assertSame( 1, $calls['get'] ?? 0, 'Tag version must be memoized after first read (including misses).' );
	}

	public function test_bump_updates_memo_without_extra_reads() {
		$calls = array();
		$cache = $this->make_counting_cache( $calls );

		$cache->get_tag_version( 'authors', 'aips_authors' ); // seeds memo (1 get)
		$new = $cache->bump_tag_version( 'authors', 'aips_authors' );
		$calls = array();

		$this->assertSame( $new, $cache->get_tag_version( 'authors', 'aips_authors' ) );
		$this->assertSame( 0, $calls['get'] ?? 0, 'Post-bump reads must come from the memo.' );
	}

	public function test_flush_clears_memo() {
		$calls = array();
		$cache = $this->make_counting_cache( $calls );

		$cache->get_tag_version( 'authors', 'aips_authors' );
		$cache->flush();
		$calls = array();
		$cache->get_tag_version( 'authors', 'aips_authors' );

		$this->assertSame( 1, $calls['get'] ?? 0, 'flush() must invalidate the tag-version memo.' );
	}
}
