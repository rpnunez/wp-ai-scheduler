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
		$this->repo->upsert_relationship( 'post', 10, 'post', 20, 0.88, 'related_post' );
		$this->repo->upsert_relationship( 'post', 10, 'post', 30, 0.72, 'related_post' );

		$related = $this->repo->get_related_objects( 'post', 10, 0.65, 5 );
		$this->assertCount( 2, $related );
		$this->assertEquals( 20, (int) $related[0]['object_id'] );
		$this->assertEquals( 0.88, (float) $related[0]['similarity'] );
		$this->assertEquals( 30, (int) $related[1]['object_id'] );
	}

	/**
	 * Test get_related_posts helper with threshold filtering.
	 */
	public function test_get_related_posts() {
		$this->repo->upsert_relationship( 'post', 10, 'post', 20, 0.90, 'related_post' );
		$this->repo->upsert_relationship( 'post', 10, 'post', 30, 0.50, 'related_post' );

		// Threshold 0.65 should only return post 20
		$posts = $this->repo->get_related_posts( 10, 0.65, 5 );
		$this->assertCount( 1, $posts );
		$this->assertEquals( 20, (int) $posts[0]['post_id'] );
	}

	/**
	 * Test delete_for_object removes relationships where object is source or target.
	 */
	public function test_delete_for_object() {
		$this->repo->upsert_relationship( 'post', 1, 'post', 2, 0.85, 'related_post' );
		$this->repo->upsert_relationship( 'post', 3, 'post', 1, 0.75, 'related_post' );

		$this->assertEquals( 2, $this->repo->get_total_relationships() );
		$this->repo->delete_for_object( 'post', 1 );
		$this->assertEquals( 0, $this->repo->get_total_relationships() );
	}

	/**
	 * Test get_graph_data formats nodes and links correctly.
	 */
	public function test_get_graph_data() {
		$this->repo->upsert_relationship( 'post', 1, 'post', 2, 0.80, 'related_post' );
		$this->repo->upsert_relationship( 'post', 1, 'post', 3, 0.70, 'related_post' );

		$graph = $this->repo->get_graph_data( 0.65, 50, 'post', 1 );
		$this->assertIsArray( $graph );
		$this->assertArrayHasKey( 'nodes', $graph );
		$this->assertArrayHasKey( 'links', $graph );
		$this->assertGreaterThanOrEqual( 1, count( $graph['nodes'] ) );
		$this->assertGreaterThanOrEqual( 1, count( $graph['links'] ) );
	}

	/**
	 * Test find_high_similarity_clusters.
	 */
	public function test_find_high_similarity_clusters() {
		$this->repo->upsert_relationship( 'post', 100, 'post', 200, 0.95, 'related_post' );
		$this->repo->upsert_relationship( 'post', 300, 'post', 400, 0.60, 'related_post' );

		$clusters = $this->repo->find_high_similarity_clusters( 0.90, 10 );
		$this->assertCount( 1, $clusters );
		$this->assertEquals( 100, (int) $clusters[0]['source_id'] );
		$this->assertEquals( 200, (int) $clusters[0]['target_id'] );
		$this->assertEquals( 0.95, (float) $clusters[0]['similarity'] );
	}
}
