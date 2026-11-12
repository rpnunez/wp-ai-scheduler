<?php
/** Atomic group replacement preserves existing mappings on write errors. */
class Test_AIPS_Sources_Group_Persistence extends WP_UnitTestCase {
	public function test_failed_insert_rolls_back_previous_group_assignments() {
		global $wpdb;
		AIPS_DB_Manager::install_tables();
		$repo = new AIPS_Sources_Repository();
		$id = $repo->create( array( 'url' => 'https://example.com/atomic-groups' ) );
		$this->assertTrue( $repo->set_source_terms( $id, array( 11, 12 ) ) );
		$fail_insert = static function( $query ) use ( $wpdb ) {
			return str_starts_with( $query, "INSERT INTO `{$wpdb->prefix}aips_source_group_terms`" ) ? 'INVALID group replacement fixture' : $query;
		};
		$previous = $wpdb->suppress_errors( true );
		add_filter( 'query', $fail_insert );
		try {
			$this->assertFalse( $repo->set_source_terms( $id, array( 99 ) ) );
		} finally {
			remove_filter( 'query', $fail_insert );
			$wpdb->suppress_errors( $previous );
		}
		$ids = $repo->get_source_term_ids( $id );
		sort( $ids );
		$this->assertSame( array( 11, 12 ), $ids );
		$this->assertTrue( $repo->set_source_terms( $id, array() ) );
		$this->assertSame( array(), $repo->get_source_term_ids( $id ) );
		$repo->delete( $id );
	}
}
