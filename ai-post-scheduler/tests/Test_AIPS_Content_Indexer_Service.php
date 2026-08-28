<?php
/**
 * Tests for AIPS_Content_Indexer_Service.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Content_Indexer_Service extends WP_UnitTestCase {

	/** @var AIPS_Embeddings_Repository */
	private $embeddings_repo;

	/** @var AIPS_Relationships_Repository */
	private $relationships_repo;

	/** @var AIPS_Content_Indexer_Service */
	private $indexer_service;

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();

		$this->embeddings_repo    = new AIPS_Embeddings_Repository();
		$this->relationships_repo = new AIPS_Relationships_Repository();

		// Mock Embeddings Service returning deterministic unit vectors
		$mock_embeddings_service = $this->createMock( AIPS_Embeddings_Service::class );
		$mock_embeddings_service->method( 'generate_embedding' )->willReturnCallback( function( $text ) {
			if ( false !== strpos( strtolower( $text ), 'alpha' ) ) {
				return array( 1.0, 0.0 );
			} elseif ( false !== strpos( strtolower( $text ), 'beta' ) ) {
				return array( 0.0, 1.0 );
			}
			return array( 0.7071, 0.7071 );
		} );

		$this->indexer_service = new AIPS_Content_Indexer_Service(
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
	 * Test index_post creates embedding record in database.
	 */
	public function test_index_post() {
		$post_id = wp_insert_post( array(
			'post_title'   => 'Alpha Article',
			'post_content' => 'This is alpha content about WordPress AI scheduling.',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		$res = $this->indexer_service->index_post( $post_id, true );
		$this->assertTrue( $res );

		$record = $this->embeddings_repo->get_by_post_id( $post_id );
		$this->assertNotNull( $record );
		$this->assertEquals( 'post', $record['object_type'] );
		$this->assertEquals( array( 1.0, 0.0 ), $record['embedding'] );
	}

	/**
	 * Test continuous sync on_post_save.
	 */
	public function test_on_post_save_indexes_and_removes_on_trash() {
		$post_id = wp_insert_post( array(
			'post_title'   => 'Beta Article',
			'post_content' => 'Beta testing content for automated indexing.',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		$post = get_post( $post_id );
		$this->indexer_service->on_post_save( $post_id, $post );

		$this->assertNotNull( $this->embeddings_repo->get_by_post_id( $post_id ) );

		// Simulate post moving to trash
		$post->post_status = 'trash';
		$this->indexer_service->on_post_save( $post_id, $post );

		$this->assertNull( $this->embeddings_repo->get_by_post_id( $post_id ) );
	}

	/**
	 * Test process_indexing_batch.
	 */
	public function test_process_indexing_batch() {
		$p1 = wp_insert_post( array( 'post_title' => 'Alpha 1', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$p2 = wp_insert_post( array( 'post_title' => 'Beta 2', 'post_status' => 'publish', 'post_type' => 'post' ) );

		$batch_res = $this->indexer_service->process_indexing_batch( 0, 10 );
		$this->assertIsArray( $batch_res );
		$this->assertArrayHasKey( 'processed_count', $batch_res );
		$this->assertGreaterThanOrEqual( 2, $batch_res['processed_count'] );
	}

	/**
	 * Test get_indexing_status returns accurate progress counters.
	 */
	public function test_get_indexing_status() {
		wp_insert_post( array( 'post_title' => 'Post A', 'post_status' => 'publish', 'post_type' => 'post' ) );
		wp_insert_post( array( 'post_title' => 'Post B', 'post_status' => 'publish', 'post_type' => 'post' ) );

		$status = $this->indexer_service->get_indexing_status();
		$this->assertIsArray( $status );
		$this->assertArrayHasKey( 'total_published', $status );
		$this->assertArrayHasKey( 'total_indexed', $status );
		$this->assertArrayHasKey( 'pct_complete', $status );
		$this->assertGreaterThanOrEqual( 2, $status['total_published'] );
	}
}
