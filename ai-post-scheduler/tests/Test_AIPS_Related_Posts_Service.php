<?php
/**
 * Tests for AIPS_Related_Posts_Service.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Related_Posts_Service extends WP_UnitTestCase {

	/** @var AIPS_Relationships_Repository */
	private $relationships_repo;

	/** @var AIPS_Embeddings_Repository */
	private $embeddings_repo;

	/** @var AIPS_Related_Posts_Service */
	private $service;

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();

		$this->relationships_repo = new AIPS_Relationships_Repository();
		$this->embeddings_repo    = new AIPS_Embeddings_Repository();
		$this->service            = new AIPS_Related_Posts_Service(
			$this->relationships_repo,
			$this->embeddings_repo
		);
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_embeddings' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_relationships' );
		parent::tearDown();
	}

	/**
	 * Test get_related_posts returns hydrated published post data.
	 */
	public function test_get_related_posts_for_post() {
		$p1 = wp_insert_post( array( 'post_title' => 'Main Guide', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$p2 = wp_insert_post( array( 'post_title' => 'Related Guide A', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$p3 = wp_insert_post( array( 'post_title' => 'Related Guide B', 'post_status' => 'publish', 'post_type' => 'post' ) );

		$this->relationships_repo->upsert( 'post', $p1, 'post', $p2, 0.85, 'related_post' );
		$this->relationships_repo->upsert( 'post', $p1, 'post', $p3, 0.75, 'related_post' );

		$related = $this->service->get_related_posts( $p1, array( 'count' => 5, 'min_similarity' => 0.70 ) );
		$this->assertCount( 2, $related );
		$this->assertEquals( 'Related Guide A', $related[0]['title'] );
		$this->assertEquals( 0.85, $related[0]['similarity'] );
		$this->assertEquals( 'Related Guide B', $related[1]['title'] );
	}

	/**
	 * Test render_related_posts_html renders grid and list layouts.
	 */
	public function test_render_related_posts_html_layouts() {
		$p1 = wp_insert_post( array( 'post_title' => 'Source Article', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$p2 = wp_insert_post( array( 'post_title' => 'Target Article', 'post_status' => 'publish', 'post_type' => 'post' ) );

		$this->relationships_repo->upsert( 'post', $p1, 'post', $p2, 0.90, 'related_post' );

		// Grid layout
		$html_grid = $this->service->render_related_posts_html( $p1, array(
			'layout'  => 'grid',
			'heading' => 'You Might Also Like',
		) );
		$this->assertStringContainsString( 'aips-related-grid', $html_grid );
		$this->assertStringContainsString( 'Target Article', $html_grid );
		$this->assertStringContainsString( 'You Might Also Like', $html_grid );

		// List layout
		$html_list = $this->service->render_related_posts_html( $p1, array(
			'layout'  => 'list',
			'heading' => 'Further Reading',
		) );
		$this->assertStringContainsString( 'aips-related-list', $html_list );
		$this->assertStringContainsString( 'Target Article', $html_list );
	}

	/**
	 * Test render returns empty string when no relationships exist.
	 */
	public function test_render_empty_when_no_relations() {
		$p1   = wp_insert_post( array( 'post_title' => 'Isolated Post', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$html = $this->service->render_related_posts_html( $p1 );
		$this->assertEmpty( $html );
	}
}
