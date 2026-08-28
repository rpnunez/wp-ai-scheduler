<?php
/**
 * Tests for AIPS_Deduplication_Service.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Deduplication_Service extends WP_UnitTestCase {

	/** @var AIPS_Embeddings_Repository */
	private $embeddings_repo;

	/** @var AIPS_Relationships_Repository */
	private $relationships_repo;

	/** @var AIPS_Deduplication_Service */
	private $dedup_service;

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();

		$this->embeddings_repo    = new AIPS_Embeddings_Repository();
		$this->relationships_repo = new AIPS_Relationships_Repository();

		$mock_embeddings_service = $this->createMock( AIPS_Embeddings_Service::class );
		$mock_embeddings_service->method( 'generate_embedding' )->willReturnCallback( function( $text ) {
			if ( false !== strpos( strtolower( $text ), 'duplicate' ) ) {
				return array( 0.99, 0.01 );
			}
			return array( 0.10, 0.99 );
		} );

		$this->dedup_service = new AIPS_Deduplication_Service(
			$this->embeddings_repo,
			$this->relationships_repo,
			$mock_embeddings_service
		);
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_embeddings' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_relationships' );
		parent::tearDown();
	}

	/**
	 * Test check_topic_duplicate finds duplicate candidate above threshold.
	 */
	public function test_check_topic_duplicate() {
		$post_id = wp_insert_post( array(
			'post_title'   => 'Existing Duplicate Article',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		$this->embeddings_repo->upsert( array(
			'object_type'      => 'post',
			'object_post_type' => 'post',
			'object_id'        => $post_id,
			'embedding'        => array( 1.0, 0.0 ),
			'dimensions'       => 2,
		) );

		// Passing a topic that generates [0.99, 0.01] should have > 0.95 cosine similarity
		$result = $this->dedup_service->check_topic_duplicate( 'duplicate topic headline', 0.85 );
		$this->assertTrue( $result['is_duplicate'] );
		$this->assertNotEmpty( $result['matches'] );
		$this->assertEquals( $post_id, $result['matches'][0]['id'] );
	}

	/**
	 * Test get_cannibalization_audit_results clusters highly similar pairs.
	 */
	public function test_get_cannibalization_audit_results() {
		$p1 = wp_insert_post( array( 'post_title' => 'Article One', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$p2 = wp_insert_post( array( 'post_title' => 'Article Two', 'post_status' => 'publish', 'post_type' => 'post' ) );

		$this->relationships_repo->upsert_relationship( 'post', $p1, 'post', $p2, 0.94, 'related_post' );

		$audit = $this->dedup_service->get_cannibalization_audit_results( 0.85, 10 );
		$this->assertIsArray( $audit );
		$this->assertCount( 1, $audit );
		$this->assertEquals( 'Critical Cannibalization Risk', $audit[0]['risk_level'] );
		$this->assertEquals( 94, $audit[0]['similarity_pct'] );
	}
}
