<?php
/**
 * Tests for AIPS_Relationships_Repository.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Relationships_Repository extends WP_UnitTestCase {

	/** @var AIPS_Relationships_Repository */
	private $repo;

	public function setUp(): void {
		parent::setUp();
		AIPS_Cache_Factory::reset();
		AIPS_DB_Manager::install_tables();
		$this->repo = new AIPS_Relationships_Repository();
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_relationships' );
		AIPS_Cache_Factory::reset();
		parent::tearDown();
	}

	/**
	 * Test upsert and retrieval of related objects.
	 */
	public function test_upsert_and_get_related_objects() {
		$p1 = wp_insert_post( array( 'post_title' => 'Post 1', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$p2 = wp_insert_post( array( 'post_title' => 'Post 2', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$p3 = wp_insert_post( array( 'post_title' => 'Post 3', 'post_status' => 'publish', 'post_type' => 'post' ) );

		$this->repo->upsert( 'post', $p1, 'post', $p2, 0.88, 'related_post' );
		$this->repo->upsert( 'post', $p1, 'post', $p3, 0.72, 'related_post' );

		$related = $this->repo->get_related( 'post', $p1, 5, 0.65, 'related_post' );
		$this->assertCount( 2, $related );
		$this->assertEquals( $p2, (int) $related[0]->target_id );
		$this->assertEquals( 0.88, (float) $related[0]->similarity );
		$this->assertEquals( $p3, (int) $related[1]->target_id );
	}

	/**
	 * Test get_related threshold filtering.
	 */
	public function test_get_related_filtering() {
		$p1 = wp_insert_post( array( 'post_title' => 'Post 10', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$p2 = wp_insert_post( array( 'post_title' => 'Post 20', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$p3 = wp_insert_post( array( 'post_title' => 'Post 30', 'post_status' => 'publish', 'post_type' => 'post' ) );

		$this->repo->upsert( 'post', $p1, 'post', $p2, 0.90, 'related_post' );
		$this->repo->upsert( 'post', $p1, 'post', $p3, 0.50, 'related_post' );

		// Threshold 0.65 should only return post 20
		$posts = $this->repo->get_related( 'post', $p1, 5, 0.65, 'related_post' );
		$this->assertCount( 1, $posts );
		$this->assertEquals( $p2, (int) $posts[0]->target_id );
	}

	/**
	 * Test delete_for_object removes relationships where object is source or target.
	 */
	public function test_delete_for_object() {
		$p1 = wp_insert_post( array( 'post_title' => 'Post A', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$p2 = wp_insert_post( array( 'post_title' => 'Post B', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$p3 = wp_insert_post( array( 'post_title' => 'Post C', 'post_status' => 'publish', 'post_type' => 'post' ) );

		$this->repo->upsert( 'post', $p1, 'post', $p2, 0.85, 'related_post' );
		$this->repo->upsert( 'post', $p3, 'post', $p1, 0.75, 'related_post' );

		$this->assertCount( 1, $this->repo->get_related( 'post', $p1, 5, 0.50 ) );
		$this->repo->delete_for_object( 'post', $p1 );
		$this->assertCount( 0, $this->repo->get_related( 'post', $p1, 5, 0.50 ) );
	}

	/**
	 * Test get_graph_data formats nodes and edges correctly.
	 */
	public function test_get_graph_data() {
		$p1 = wp_insert_post( array( 'post_title' => 'Central Post', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$p2 = wp_insert_post( array( 'post_title' => 'Neighbor One', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$p3 = wp_insert_post( array( 'post_title' => 'Neighbor Two', 'post_status' => 'publish', 'post_type' => 'post' ) );

		$this->repo->upsert( 'post', $p1, 'post', $p2, 0.80, 'related_post' );
		$this->repo->upsert( 'post', $p1, 'post', $p3, 0.70, 'related_post' );

		$graph = $this->repo->get_graph_data( 'post', $p1, 50, 0.65 );
		$this->assertIsArray( $graph );
		$this->assertArrayHasKey( 'nodes', $graph );
		$this->assertArrayHasKey( 'edges', $graph );
		$this->assertGreaterThanOrEqual( 1, count( $graph['nodes'] ) );
		$this->assertGreaterThanOrEqual( 1, count( $graph['edges'] ) );
	}

	/**
	 * Test get_top_duplicate_pairs.
	 */
	public function test_get_top_duplicate_pairs() {
		$p1 = wp_insert_post( array( 'post_title' => 'Dupe A', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$p2 = wp_insert_post( array( 'post_title' => 'Dupe B', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$p3 = wp_insert_post( array( 'post_title' => 'Other C', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$p4 = wp_insert_post( array( 'post_title' => 'Other D', 'post_status' => 'publish', 'post_type' => 'post' ) );

		$min_id = min( $p1, $p2 );
		$max_id = max( $p1, $p2 );
		$this->repo->upsert( 'post', $min_id, 'post', $max_id, 0.95, 'related_post' );

		$min_id2 = min( $p3, $p4 );
		$max_id2 = max( $p3, $p4 );
		$this->repo->upsert( 'post', $min_id2, 'post', $max_id2, 0.60, 'related_post' );

		$clusters = $this->repo->get_top_duplicate_pairs( 0.90, 10 );
		$this->assertCount( 1, $clusters );
		$this->assertEquals( $min_id, (int) $clusters[0]->source_id );
		$this->assertEquals( $max_id, (int) $clusters[0]->target_id );
		$this->assertEquals( 0.95, (float) $clusters[0]->similarity );
	}

	/**
	 * get_related() joins wp_posts on post_status. Trashing a target post
	 * through the native WP flow must evict the cached read via
	 * AIPS_Post_Lifecycle_Cache_Invalidator.
	 */
	public function test_get_related_refreshes_when_target_is_trashed() {
		$invalidator = new AIPS_Post_Lifecycle_Cache_Invalidator( $this->repo );
		$invalidator->register();

		try {
			$p1 = wp_insert_post( array( 'post_title' => 'Source', 'post_status' => 'publish', 'post_type' => 'post' ) );
			$p2 = wp_insert_post( array( 'post_title' => 'Target', 'post_status' => 'publish', 'post_type' => 'post' ) );

			$this->repo->upsert( 'post', $p1, 'post', $p2, 0.90, 'related_post' );
			$this->assertCount( 1, $this->repo->get_related( 'post', $p1, 5, 0.50 ) );

			wp_trash_post( $p2 );

			$this->assertCount( 0, $this->repo->get_related( 'post', $p1, 5, 0.50 ) );
		} finally {
			remove_action( 'transition_post_status', array( $invalidator, 'on_transition_post_status' ), 10 );
			remove_action( 'deleted_post', array( $invalidator, 'on_deleted_post' ), 10 );
		}
	}

	/**
	 * sync_for_source() replaces a source's rows; a previously cached read must
	 * not keep serving the pre-sync neighbors.
	 */
	public function test_sync_for_source_invalidates_cached_reads() {
		$p1 = wp_insert_post( array( 'post_title' => 'S', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$p2 = wp_insert_post( array( 'post_title' => 'T1', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$p3 = wp_insert_post( array( 'post_title' => 'T2', 'post_status' => 'publish', 'post_type' => 'post' ) );

		$this->repo->sync_for_source( 'post', $p1, array( array( 'target_type' => 'post', 'target_id' => $p2, 'similarity' => 0.9 ) ) );
		$this->assertSame( 1, $this->repo->count() );
		$this->assertEquals( $p2, (int) $this->repo->get_related( 'post', $p1, 5, 0.5 )[0]->target_id );

		$this->repo->sync_for_source( 'post', $p1, array(
			array( 'target_type' => 'post', 'target_id' => $p2, 'similarity' => 0.9 ),
			array( 'target_type' => 'post', 'target_id' => $p3, 'similarity' => 0.8 ),
		) );

		$this->assertSame( 2, $this->repo->count() );
		$this->assertCount( 2, $this->repo->get_related( 'post', $p1, 5, 0.5 ) );

		$this->repo->clear_all();
		$this->assertSame( 0, $this->repo->count() );
	}
}
