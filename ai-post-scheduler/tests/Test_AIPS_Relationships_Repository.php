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
		AIPS_DB_Manager::install_tables();
		$this->repo = new AIPS_Relationships_Repository();
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_relationships' );
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
}
