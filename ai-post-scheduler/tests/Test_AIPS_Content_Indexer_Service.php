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

		// Mock AI Service returning deterministic unit vectors
		$mock_ai_service = $this->createMock( AIPS_AI_Service_Interface::class );
		$mock_ai_service->method( 'generate_embedding' )->willReturnCallback( function( $text ) {
			if ( false !== strpos( strtolower( $text ), 'alpha' ) ) {
				return array( 1.0, 0.0 );
			} elseif ( false !== strpos( strtolower( $text ), 'beta' ) ) {
				return array( 0.0, 1.0 );
			}
			return array( 0.7071, 0.7071 );
		} );

		$embeddings_service = new AIPS_Embeddings_Service(
			$mock_ai_service,
			new AIPS_Logger()
		);

		$this->indexer_service = new AIPS_Content_Indexer_Service(
			$this->embeddings_repo,
			$this->relationships_repo,
			$embeddings_service
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
		$this->assertEquals( 'post', $record->object_type );
		$this->assertEquals( array( 1.0, 0.0 ), json_decode( $record->embedding, true ) );
	}

	/**
	 * Test a failed embedding write cannot be reported as a successful indexing run.
	 */
	public function test_index_post_returns_error_when_embedding_persistence_fails() {
		$post_id = wp_insert_post( array(
			'post_title'   => 'Alpha Article',
			'post_content' => 'This is alpha content about WordPress AI scheduling.',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		$embeddings_repo = $this->createMock( AIPS_Embeddings_Repository::class );
		$embeddings_repo->method( 'get_by_post_id' )->willReturn( null );
		$embeddings_repo->method( 'upsert' )->willReturn( false );

		$embeddings_service = $this->createMock( AIPS_Embeddings_Service::class );
		$embeddings_service->method( 'generate_embedding' )->willReturn( array( 1.0, 0.0 ) );

		$history_container = new class {
			public $record_errors = array();
			public $failures = array();
			public $successes = array();

			public function record() {}

			public function record_error($message, $details = array(), $error = null) {
				$this->record_errors[] = compact('message', 'details', 'error');
			}

			public function complete_failure($message, $details = array()) {
				$this->failures[] = compact('message', 'details');
			}

			public function complete_success($details = array()) {
				$this->successes[] = $details;
			}
		};

		$history_service = $this->createMock( AIPS_History_Service_Interface::class );
		$history_service->method( 'create' )->willReturn( $history_container );

		$service = new AIPS_Content_Indexer_Service(
			$embeddings_repo,
			$this->relationships_repo,
			$embeddings_service,
			$history_service
		);

		$result = $service->index_post( $post_id, false );

		$this->assertWPError( $result );
		$this->assertSame( 'embedding_persistence_failed', $result->get_error_code() );
		$this->assertCount( 1, $history_container->failures );
		$this->assertCount( 0, $history_container->successes );
		$this->assertCount( 0, $history_container->record_errors );
	}

	/**
	 * Test an embedding API failure produces one History error entry.
	 */
	public function test_index_post_records_embedding_failure_once() {
		$post_id = wp_insert_post( array(
			'post_title'   => 'Alpha Article',
			'post_content' => 'This is alpha content about WordPress AI scheduling.',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		$embeddings_repo = $this->createMock( AIPS_Embeddings_Repository::class );
		$embeddings_repo->method( 'get_by_post_id' )->willReturn( null );

		$embeddings_service = $this->createMock( AIPS_Embeddings_Service::class );
		$embeddings_service->method( 'generate_embedding' )->willReturn(
			new WP_Error( 'embedding_failed', 'Embedding request failed.' )
		);

		$history_container = new class {
			public $record_errors = array();
			public $failures = array();

			public function record() {}

			public function record_error($message, $details = array(), $error = null) {
				$this->record_errors[] = compact('message', 'details', 'error');
			}

			public function complete_failure($message, $details = array()) {
				$this->failures[] = compact('message', 'details');
			}
		};

		$history_service = $this->createMock( AIPS_History_Service_Interface::class );
		$history_service->method( 'create' )->willReturn( $history_container );

		$service = new AIPS_Content_Indexer_Service(
			$embeddings_repo,
			$this->relationships_repo,
			$embeddings_service,
			$history_service
		);

		$result = $service->index_post( $post_id, false );

		$this->assertWPError( $result );
		$this->assertCount( 1, $history_container->failures );
		$this->assertCount( 0, $history_container->record_errors );
	}

	/**
	 * Test the final History entry does not claim relationships were recomputed when disabled.
	 */
	public function test_index_post_omits_relationship_metrics_when_computation_is_disabled() {
		$post_id = wp_insert_post( array(
			'post_title'   => 'Alpha Article',
			'post_content' => 'This is alpha content about WordPress AI scheduling.',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		$embeddings_repo = $this->createMock( AIPS_Embeddings_Repository::class );
		$embeddings_repo->method( 'get_by_post_id' )->willReturn( null );
		$embeddings_repo->method( 'upsert' )->willReturn( 1 );

		$embeddings_service = $this->createMock( AIPS_Embeddings_Service::class );
		$embeddings_service->method( 'generate_embedding' )->willReturn( array( 1.0, 0.0 ) );

		$history_container = new class {
			public $records = array();

			public function record($type, $message, $input = null, $output = null, $context = array()) {
				$this->records[] = compact('type', 'message', 'input', 'output', 'context');
			}

			public function complete_success() {}
		};

		$history_service = $this->createMock( AIPS_History_Service_Interface::class );
		$history_service->method( 'create' )->willReturn( $history_container );

		$service = new AIPS_Content_Indexer_Service(
			$embeddings_repo,
			$this->relationships_repo,
			$embeddings_service,
			$history_service
		);

		$result = $service->index_post( $post_id, false );
		$final_record = end( $history_container->records );

		$this->assertTrue( $result );
		$this->assertArrayNotHasKey( 'relationships_saved', $final_record['input'] );
		$this->assertStringNotContainsString( 'related', $final_record['message'] );
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
		wp_insert_post( array( 'post_title' => 'Alpha 1', 'post_status' => 'publish', 'post_type' => 'post' ) );
		wp_insert_post( array( 'post_title' => 'Beta 2', 'post_status' => 'publish', 'post_type' => 'post' ) );

		$batch_res = $this->indexer_service->process_indexing_batch( 10, 0 );
		$this->assertIsArray( $batch_res );
		$this->assertArrayHasKey( 'success', $batch_res );
		$this->assertArrayHasKey( 'total_indexed', $batch_res );
		$this->assertGreaterThanOrEqual( 2, $batch_res['success'] );
	}

	/**
	 * Test get_indexing_status returns accurate progress counters.
	 */
	public function test_get_indexing_status() {
		wp_insert_post( array( 'post_title' => 'Post A', 'post_status' => 'publish', 'post_type' => 'post' ) );
		wp_insert_post( array( 'post_title' => 'Post B', 'post_status' => 'publish', 'post_type' => 'post' ) );

		$status = $this->indexer_service->get_indexing_status();
		$this->assertIsArray( $status );
		$this->assertArrayHasKey( 'total_posts', $status );
		$this->assertArrayHasKey( 'indexed', $status );
		$this->assertArrayHasKey( 'percent', $status );
		$this->assertGreaterThanOrEqual( 2, $status['total_posts'] );
	}
}
