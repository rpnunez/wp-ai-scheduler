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
		AIPS_Cache_Factory::reset();
		AIPS_DB_Manager::install_tables();

		$this->embeddings_repo    = new AIPS_Embeddings_Repository();
		$this->relationships_repo = new AIPS_Relationships_Repository();

		$mock_ai_service = $this->createMock( AIPS_AI_Service_Interface::class );
		$mock_ai_service->method( 'is_available' )->willReturn( true );
		$mock_ai_service->method( 'supports_embeddings' )->willReturn( true );
		$mock_ai_service->method( 'generate_embedding' )->willReturnCallback( function( $text ) {
			if ( false !== strpos( strtolower( $text ), 'duplicate' ) ) {
				return array( 1.0, 0.0 );
			}
			return array( 0.0, 1.0 );
		} );

		$embeddings_service = new AIPS_Embeddings_Service(
			$mock_ai_service,
			new AIPS_Logger()
		);

		$this->dedup_service = new AIPS_Deduplication_Service(
			$this->embeddings_repo,
			$this->relationships_repo,
			$embeddings_service,
			AIPS_Config::get_instance(),
			new AIPS_Logger()
		);
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_embeddings' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_relationships' );
		AIPS_Cache_Factory::reset();
		parent::tearDown();
	}

	/**
	 * Test check_post_title_duplicate finds duplicate candidate above threshold.
	 */
	public function test_check_topic_duplicate() {
		$post_id = wp_insert_post( array(
			'post_title'   => 'Existing Duplicate Article',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		$this->embeddings_repo->upsert(
			'post',
			$post_id,
			array( 1.0, 0.0 ),
			'model',
			2,
			'',
			'post'
		);

		// Passing a title containing 'duplicate' generates [0.99, 0.01] -> ~0.99 similarity
		$result = $this->dedup_service->check_pre_generation_duplicate( 'duplicate topic headline', 'post' );
		$this->assertTrue( $result['is_duplicate'] );
		$this->assertEquals( $post_id, $result['matched_post_id'] );
		$this->assertGreaterThanOrEqual( 0.85, $result['similarity'] );
	}

	/**
	 * Test get_cannibalization_audit_results clusters highly similar pairs.
	 */
	public function test_get_cannibalization_audit_results() {
		$p1 = wp_insert_post( array( 'post_title' => 'Article One', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$p2 = wp_insert_post( array( 'post_title' => 'Article Two', 'post_status' => 'publish', 'post_type' => 'post' ) );

		$min_id = min( $p1, $p2 );
		$max_id = max( $p1, $p2 );
		$this->relationships_repo->upsert( 'post', $min_id, 'post', $max_id, 0.94, 'related_post' );

		$audit = $this->dedup_service->get_cannibalization_audit_results( 0.85, 10 );
		$this->assertIsArray( $audit );
		$this->assertCount( 1, $audit );
		$this->assertEquals( 94.0, (float) $audit[0]['similarity_pct'] );
	}
}
