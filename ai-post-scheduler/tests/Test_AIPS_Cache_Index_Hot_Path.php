<?php
/**
 * @group cache
 */
class Test_AIPS_Cache_Index_Hot_Path extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		update_option( 'aips_enable_cache_system', '1' );
		update_option( 'aips_cache_monitor_enabled', '1' ); // Master switch (Task 5 defaults it off).
		update_option( 'aips_cache_monitor_index_enabled', '1' );
		AIPS_Cache::reset_system_enabled_flag();
	}

	private function count_index_rows(): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aips_cache_index" );
	}

	public function test_array_driver_set_does_not_write_index_rows() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_cache_index" );

		$cache = new AIPS_Cache( new AIPS_Cache_Array_Driver() );
		$cache->set( 'transient_value', 'abc', 60, 'default' );

		$this->assertSame( 0, $this->count_index_rows(), 'Array-driver entries must not be indexed.' );
	}

	public function test_db_driver_set_still_writes_index_rows() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_cache_index" );

		$cache = new AIPS_Cache( new AIPS_Cache_Db_Driver() );
		$cache->set( 'persistent_value', 'abc', 60, 'default' );
		// Task 2 defers index writes to shutdown; flush explicitly.
		if ( method_exists( 'AIPS_Cache_Index', 'flush_pending' ) ) {
			AIPS_Cache_Index::flush_pending();
		}

		$this->assertGreaterThan( 0, $this->count_index_rows(), 'DB-driver entries must still be indexed.' );
	}

	public function test_record_access_is_deferred_and_deduped() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_cache_index" );

		$cache = new AIPS_Cache( new AIPS_Cache_Db_Driver() );
		$cache->set( 'hot_key', 'v', 60, 'default' );
		AIPS_Cache_Index::flush_pending();

		// 3 driver SELECTs are expected; no UPDATE on the index table yet.
		$cache->get( 'hot_key', 'default' );
		$cache->get( 'hot_key', 'default' );
		$cache->get( 'hot_key', 'default' );
		$this->assertSame(
			0,
			(int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}aips_cache_index WHERE cache_key = %s AND last_accessed_at > 0",
				'hot_key'
			) ),
			'last_accessed_at must not be written synchronously.'
		);

		AIPS_Cache_Index::flush_pending();

		$this->assertSame(
			1,
			(int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}aips_cache_index WHERE cache_key = %s AND last_accessed_at > 0",
				'hot_key'
			) ),
			'flush_pending must persist one deduped access row.'
		);
	}

	public function test_record_set_does_not_count_rows_synchronously() {
		global $wpdb;
		$cache = new AIPS_Cache( new AIPS_Cache_Db_Driver() );

		$captured = array();
		add_filter( 'query', function ( $q ) use ( &$captured ) {
			$captured[] = $q;
			return $q;
		} );
		$cache->set( 'count_probe', 'v', 60, 'default' );
		$count_queries = array_filter( $captured, static function ( $q ) {
			return stripos( $q, 'COUNT(*)' ) !== false;
		} );
		$this->assertSame( array(), array_values( $count_queries ),
			'record_set must not run COUNT(*) per write.' );
	}

	// -----------------------------------------------------------------------
	// prune_orphans() hashed-key reconciliation
	//
	// AIPS_Cache_Db_Driver::namespace_key() hashes {prefix}:{rawkey} down to
	// a sha256 digest when it exceeds MAX_KEY_LENGTH (191 chars). The index
	// only ever records the raw, un-prefixed key, so prune_orphans()'s join
	// (exact match / raw-suffix LIKE against aips_cache.cache_key) can never
	// match a hashed row -- these tests guard against that false positive.
	// -----------------------------------------------------------------------

	private function truncate_cache_tables(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_cache_index" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_cache" );
	}

	public function test_prune_orphans_does_not_delete_live_hashed_key_index_row() {
		global $wpdb;
		$this->truncate_cache_tables();
		update_option( 'aips_cache_driver', 'db' );

		$group = 'authors';
		$tier  = 'medium';
		// Mirrors AIPS_Repository_Cache_Key_Builder::build_key()'s shape/length.
		$raw_key = 'aips_repo:authors.get_by_id:' . hash( 'sha256', 'a' ) . ':' . hash( 'sha256', 'b' ) . ':ctx:' . hash( 'sha256', 'c' );

		$prefix     = AIPS_Repository_Cache_Config::build_cache_name( $group, $tier );
		$namespaced = $prefix . ':' . $raw_key;
		$this->assertGreaterThan( AIPS_Cache_Db_Driver::MAX_KEY_LENGTH, strlen( $namespaced ),
			'Precondition: namespaced key must exceed MAX_KEY_LENGTH for this test to be meaningful.' );
		$hashed = hash( 'sha256', $namespaced );

		$now = time();
		$wpdb->insert( $wpdb->prefix . 'aips_cache', array(
			'cache_key'   => $hashed,
			'cache_group' => $group,
			'value'       => maybe_serialize( 'still alive' ),
			'expires_at'  => 0,
			'updated_at'  => $now,
		) );
		$wpdb->insert( $wpdb->prefix . 'aips_cache_index', array(
			'cache_key'   => $raw_key,
			'key_hash'    => hash( 'sha256', $group . ':' . $raw_key ),
			'cache_group' => $group,
			'driver'      => 'db',
			'tier'        => $tier,
			'created_at'  => $now,
			'updated_at'  => $now,
			'expires_at'  => 0,
		) );

		$deleted = ( new AIPS_Cache_Index() )->prune_orphans();

		$this->assertSame( 0, $deleted, 'A live hashed-key entry must not be reported as pruned.' );
		$this->assertSame(
			1,
			(int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}aips_cache_index WHERE cache_key = %s AND cache_group = %s",
				$raw_key,
				$group
			) ),
			'The index row for the live hashed-key entry must survive.'
		);
		$this->assertSame(
			1,
			(int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}aips_cache WHERE cache_key = %s AND cache_group = %s",
				$hashed,
				$group
			) ),
			'The real cache row must be untouched.'
		);
	}

	public function test_prune_orphans_still_deletes_genuinely_orphaned_short_key() {
		global $wpdb;
		$this->truncate_cache_tables();
		update_option( 'aips_cache_driver', 'db' );

		$group   = 'small_group';
		$tier    = 'short';
		$raw_key = 'aips_repo:small.op:' . hash( 'sha256', 'x' );
		$now     = time();

		// No corresponding aips_cache row -- genuinely orphaned.
		$wpdb->insert( $wpdb->prefix . 'aips_cache_index', array(
			'cache_key'   => $raw_key,
			'key_hash'    => hash( 'sha256', $group . ':' . $raw_key ),
			'cache_group' => $group,
			'driver'      => 'db',
			'tier'        => $tier,
			'created_at'  => $now,
			'updated_at'  => $now,
			'expires_at'  => 0,
		) );

		$deleted = ( new AIPS_Cache_Index() )->prune_orphans();

		$this->assertSame( 1, $deleted );
		$this->assertSame(
			0,
			(int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}aips_cache_index WHERE cache_key = %s AND cache_group = %s",
				$raw_key,
				$group
			) ),
			'A genuinely orphaned short-key index row must still be pruned.'
		);
	}

	public function test_prune_orphans_deletes_non_repository_orphan_with_empty_tier() {
		global $wpdb;
		$this->truncate_cache_tables();
		update_option( 'aips_cache_driver', 'db' );

		$group   = 'aips_template_repository';
		$raw_key = 'all:0';
		$now     = time();

		// tier = '' mimics a non-repository-trait AIPS_Cache_Factory::named()
		// usage; no corresponding aips_cache row -- genuinely orphaned.
		$wpdb->insert( $wpdb->prefix . 'aips_cache_index', array(
			'cache_key'   => $raw_key,
			'key_hash'    => hash( 'sha256', $group . ':' . $raw_key ),
			'cache_group' => $group,
			'driver'      => 'db',
			'tier'        => '',
			'created_at'  => $now,
			'updated_at'  => $now,
			'expires_at'  => 0,
		) );

		$deleted = ( new AIPS_Cache_Index() )->prune_orphans();

		$this->assertSame( 1, $deleted,
			'Non-repository (empty tier) orphans must still be pruned -- no regression on the pre-existing path.' );
	}

	public function test_prune_orphans_preserves_non_repository_live_short_key() {
		global $wpdb;
		$this->truncate_cache_tables();
		update_option( 'aips_cache_driver', 'db' );

		$group   = 'aips_template_repository';
		$prefix  = 'aips_template_repository';
		$raw_key = 'id:8';
		$now     = time();

		$wpdb->insert( $wpdb->prefix . 'aips_cache', array(
			'cache_key'   => $prefix . ':' . $raw_key,
			'cache_group' => $group,
			'value'       => maybe_serialize( 'still alive' ),
			'expires_at'  => 0,
			'updated_at'  => $now,
		) );
		$wpdb->insert( $wpdb->prefix . 'aips_cache_index', array(
			'cache_key'   => $raw_key,
			'key_hash'    => hash( 'sha256', $group . ':' . $raw_key ),
			'cache_group' => $group,
			'driver'      => 'db',
			'tier'        => '',
			'created_at'  => $now,
			'updated_at'  => $now,
			'expires_at'  => 0,
		) );

		$deleted = ( new AIPS_Cache_Index() )->prune_orphans();

		$this->assertSame( 0, $deleted );
		$this->assertSame(
			1,
			(int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}aips_cache_index WHERE cache_key = %s AND cache_group = %s",
				$raw_key,
				$group
			) ),
			'A live non-repository short-key entry must survive (regression guard on unchanged phase-1 SQL).'
		);
	}
}
