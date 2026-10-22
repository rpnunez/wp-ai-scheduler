<?php
/**
 * Test Author Slice Scheduler Retry Batching
 */
class Test_Author_Slice_Scheduler_Retry extends WP_UnitTestCase {

	public function test_get_by_ids_batching_and_bounded_queries() {
		global $wpdb;

		$repo = new AIPS_Authors_Repository();
		$ids  = array();

		for ( $i = 1; $i <= 10; $i++ ) {
			$ids[] = (int) $repo->create( array(
				'name'      => "Retry Author {$i}",
				'is_active' => 1,
			) );
		}

		$start_queries = $wpdb->num_queries;
		$authors       = $repo->get_by_ids( $ids );
		$queries_taken = $wpdb->num_queries - $start_queries;

		$this->assertCount( 10, $authors );
		$this->assertSame( 1, $queries_taken );

		foreach ( $ids as $id ) {
			$repo->delete( $id );
		}
	}
}
