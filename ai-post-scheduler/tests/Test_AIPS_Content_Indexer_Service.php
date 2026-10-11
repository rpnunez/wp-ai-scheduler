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
		delete_option( 'aips_pending_index_queue' );
		delete_option( 'aips_pending_topic_index_queue' );
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

	/**
	 * Test enqueue_topic_for_indexing buffers and deduplicates topic IDs in the topic queue.
	 */
	public function test_enqueue_topic_for_indexing() {
		$this->indexer_service->enqueue_topic_for_indexing( 101 );
		$this->indexer_service->enqueue_topic_for_indexing( 102 );
		$this->indexer_service->enqueue_topic_for_indexing( 101 ); // Duplicate

		$queue = get_option( 'aips_pending_topic_index_queue' );
		$this->assertIsArray( $queue );
		$this->assertEquals( array( 101, 102 ), $queue );
	}

	/**
	 * Test clear_index clears pending queues.
	 */
	public function test_clear_index_clears_queues() {
		$this->indexer_service->enqueue_post_for_indexing( 201 );
		$this->indexer_service->enqueue_topic_for_indexing( 301 );

		$this->assertNotEmpty( get_option( 'aips_pending_index_queue' ) );
		$this->assertNotEmpty( get_option( 'aips_pending_topic_index_queue' ) );

		$this->indexer_service->clear_index();

		$this->assertFalse( get_option( 'aips_pending_index_queue' ) );
		$this->assertFalse( get_option( 'aips_pending_topic_index_queue' ) );
	}

	/**
	 * Test schedule_queue_worker schedules background event.
	 */
	public function test_schedule_queue_worker() {
		wp_clear_scheduled_hook( 'aips_process_pending_indexer_queue' );
		$this->assertFalse( $this->indexer_service->is_queue_worker_scheduled() );

		$this->indexer_service->schedule_queue_worker( time() + 30 );
		$this->assertTrue( $this->indexer_service->is_queue_worker_scheduled() );

		wp_clear_scheduled_hook( 'aips_process_pending_indexer_queue' );
	}

	/**
	 * Test get_queue_status reports pending counts and worker state.
	 */
	public function test_get_queue_status() {
		delete_option( 'aips_pending_index_queue' );
		delete_option( 'aips_pending_topic_index_queue' );
		wp_clear_scheduled_hook( 'aips_process_pending_indexer_queue' );

		$status = $this->indexer_service->get_queue_status();
		$this->assertSame( 0, $status['pending_count'] );
		$this->assertFalse( $status['is_running'] );

		update_option( 'aips_pending_index_queue', array( 1, 2, 3 ), false );
		update_option( 'aips_pending_topic_index_queue', array( 9 ), false );
		$this->indexer_service->schedule_queue_worker( time() + 30 );

		$status = $this->indexer_service->get_queue_status();
		$this->assertSame( 4, $status['pending_count'] );
		$this->assertSame( 3, $status['pending_posts'] );
		$this->assertSame( 1, $status['pending_topics'] );
		$this->assertTrue( $status['is_running'] );

		wp_clear_scheduled_hook( 'aips_process_pending_indexer_queue' );
	}

	/**
	 * Test enqueue_topics_for_indexing buffers and deduplicates an array of topic IDs.
	 */
	public function test_enqueue_topics_for_indexing_batch() {
		$this->indexer_service->enqueue_topics_for_indexing( array( 10, 20, 10, 30 ) );
		$queue = get_option( 'aips_pending_topic_index_queue' );
		$this->assertEquals( array( 10, 20, 30 ), $queue );

		$this->indexer_service->enqueue_topics_for_indexing( array( 20, 40 ) );
		$queue = get_option( 'aips_pending_topic_index_queue' );
		$this->assertEquals( array( 10, 20, 30, 40 ), $queue );
	}

	/**
	 * Test enqueue_posts_for_indexing buffers and deduplicates an array of post IDs.
	 */
	public function test_enqueue_posts_for_indexing_batch() {
		$this->indexer_service->enqueue_posts_for_indexing( array( 100, 200, 100, 300 ) );
		$queue = get_option( 'aips_pending_index_queue' );
		$this->assertEquals( array( 100, 200, 300 ), $queue );

		$this->indexer_service->enqueue_posts_for_indexing( array( 200, 400 ) );
		$queue = get_option( 'aips_pending_index_queue' );
		$this->assertEquals( array( 100, 200, 300, 400 ), $queue );
	}

	/**
	 * Test process_pending_indexer_queue preserves unattempted queue items when rate limit triggers early break.
	 */
	public function test_process_pending_indexer_queue_preserves_remainder_on_rate_limit() {
		$post1 = wp_insert_post( array(
			'post_title'   => 'Post 1',
			'post_content' => 'Alpha content 1',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );
		$post2 = wp_insert_post( array(
			'post_title'   => 'Post 2',
			'post_content' => 'Alpha content 2',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );
		$post3 = wp_insert_post( array(
			'post_title'   => 'Post 3',
			'post_content' => 'Alpha content 3',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		// Create mock AI service that fails on the second call with rate_limit_exceeded
		$mock_ai_service = $this->createMock( AIPS_AI_Service_Interface::class );
		$calls = 0;
		$mock_ai_service->method( 'generate_embedding' )->willReturnCallback( function() use ( &$calls ) {
			$calls++;
			if ( $calls === 2 ) {
				return new WP_Error( 'rate_limit_exceeded', 'OpenAI rate limit reached' );
			}
			return array( 1.0, 0.0 );
		} );

		$embeddings_service = new AIPS_Embeddings_Service( $mock_ai_service, new AIPS_Logger() );
		$indexer = new AIPS_Content_Indexer_Service(
			$this->embeddings_repo,
			$this->relationships_repo,
			$embeddings_service
		);

		update_option( 'aips_pending_index_queue', array( $post1, $post2, $post3 ) );

		$result = $indexer->process_pending_indexer_queue();
		$this->assertEquals( 1, $result['success'] );
		$this->assertEquals( 1, $result['failed'] );

		// Post 1 was indexed, Post 2 hit rate limit, Post 3 was unattempted.
		// Both Post 2 and Post 3 MUST remain in the queue.
		$remaining_queue = get_option( 'aips_pending_index_queue' );
		$this->assertEquals( array( $post2, $post3 ), $remaining_queue );
	}

	// -------------------------------------------------------------------------
	// ensure_indexed(): idempotence, staleness, locking
	// -------------------------------------------------------------------------

	/**
	 * Build an indexer whose embeddings provider counts its calls.
	 *
	 * @param int $dimensions Dimensions of the returned vectors.
	 * @return array{0:AIPS_Content_Indexer_Service,1:object} Indexer and call counter (->calls).
	 */
	private function make_counting_indexer( int $dimensions = 2 ): array {
		$counter        = new stdClass();
		$counter->calls = 0;

		$mock_ai_service = $this->createMock( AIPS_AI_Service_Interface::class );
		$mock_ai_service->method( 'generate_embedding' )->willReturnCallback( function () use ( $counter, $dimensions ) {
			$counter->calls++;
			return array_fill( 0, $dimensions, 0.5 );
		} );

		AIPS_Config::get_instance()->set_option( 'aips_embeddings_dimensions', $dimensions );

		$embeddings_service = new AIPS_Embeddings_Service( $mock_ai_service, new AIPS_Logger() );
		$indexer            = new AIPS_Content_Indexer_Service( $this->embeddings_repo, $this->relationships_repo, $embeddings_service );

		return array( $indexer, $counter );
	}

	private function make_post( string $title = 'Gamma Article', string $content = 'Gamma body text.' ): int {
		return wp_insert_post( array(
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );
	}

	public function test_ensure_indexed_makes_no_embedding_call_when_row_is_current() {
		list( $indexer, $counter ) = $this->make_counting_indexer();
		$post_id                   = $this->make_post();

		$this->assertTrue( $indexer->ensure_indexed( $post_id ) );
		$this->assertSame( 1, $counter->calls );

		$this->assertTrue( $indexer->ensure_indexed( $post_id ) );
		$this->assertSame( 1, $counter->calls, 'Unchanged text, model and dimensions must not be embedded again.' );
	}

	public function test_ensure_indexed_re_embeds_when_text_changes() {
		list( $indexer, $counter ) = $this->make_counting_indexer();
		$post_id                   = $this->make_post();

		$indexer->ensure_indexed( $post_id );
		wp_update_post( array( 'ID' => $post_id, 'post_content' => 'Completely different body.' ) );
		$indexer->ensure_indexed( $post_id );

		$this->assertSame( 2, $counter->calls );
	}

	public function test_ensure_indexed_re_embeds_when_model_or_dimensions_differ() {
		list( $indexer, $counter ) = $this->make_counting_indexer();
		$post_id                   = $this->make_post();
		$indexer->ensure_indexed( $post_id );
		$this->assertSame( 1, $counter->calls );

		$row = $this->embeddings_repo->get_by_post_id( $post_id );
		$this->embeddings_repo->upsert( 'post', $post_id, array( 0.5, 0.5 ), 'an-older-model', 2, $row->content_hash, 'post' );
		$this->assertTrue( $indexer->is_stale( $this->embeddings_repo->get_by_post_id( $post_id ) ) );
		$indexer->ensure_indexed( $post_id );
		$this->assertSame( 2, $counter->calls, 'A different model makes the row stale.' );

		$row = $this->embeddings_repo->get_by_post_id( $post_id );
		$this->embeddings_repo->upsert( 'post', $post_id, array( 0.5, 0.5, 0.5 ), $row->model, 3, $row->content_hash, 'post' );
		$this->assertTrue( $indexer->is_stale( $this->embeddings_repo->get_by_post_id( $post_id ) ) );
		$indexer->ensure_indexed( $post_id );
		$this->assertSame( 3, $counter->calls, 'A different dimension count makes the row stale.' );
		$this->assertFalse( $indexer->is_stale( $this->embeddings_repo->get_by_post_id( $post_id ) ) );
	}

	public function test_ensure_indexed_force_re_embeds_a_current_row() {
		list( $indexer, $counter ) = $this->make_counting_indexer();
		$post_id                   = $this->make_post();

		$indexer->ensure_indexed( $post_id );
		$indexer->ensure_indexed( $post_id, false, true );

		$this->assertSame( 2, $counter->calls );
	}

	public function test_ensure_indexed_returns_errors_for_missing_and_empty_posts() {
		list( $indexer, $counter ) = $this->make_counting_indexer();

		$missing = $indexer->ensure_indexed( 999999 );
		$this->assertWPError( $missing );
		$this->assertSame( 'post_not_found', $missing->get_error_code() );

		$empty_id = wp_insert_post( array( 'post_title' => '', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post' ), true );
		if ( ! is_wp_error( $empty_id ) && $empty_id ) {
			$empty = $indexer->ensure_indexed( $empty_id );
			$this->assertWPError( $empty );
			$this->assertSame( 'empty_content', $empty->get_error_code() );
		}

		$this->assertSame( 0, $counter->calls );
	}

	public function test_ensure_indexed_skips_the_embedding_call_while_another_worker_holds_the_lock() {
		list( $indexer, $counter ) = $this->make_counting_indexer();
		$post_id                   = $this->make_post();

		$this->assertTrue( AIPS_Lock::acquire( AIPS_Content_Indexer_Service::LOCK_PREFIX . $post_id, 60 ) );

		$result = $indexer->ensure_indexed( $post_id );

		$this->assertWPError( $result );
		$this->assertSame( 'resource_locked', $result->get_error_code() );
		$this->assertSame( 0, $counter->calls, 'A second caller must not pay for the same embedding.' );

		AIPS_Lock::release( AIPS_Content_Indexer_Service::LOCK_PREFIX . $post_id );
		$this->assertTrue( $indexer->ensure_indexed( $post_id ) );
		$this->assertSame( 1, $counter->calls );
	}

	public function test_status_reports_stale_posts_and_stale_only_batches_re_embed_only_those() {
		list( $indexer, $counter ) = $this->make_counting_indexer();
		AIPS_Config::get_instance()->set_option( 'aips_embeddings_scope', 'all' );
		$stale_id   = $this->make_post( 'Stale one', 'Body one' );
		$current_id = $this->make_post( 'Current one', 'Body two' );
		$indexer->ensure_indexed( $stale_id );
		$indexer->ensure_indexed( $current_id );

		$row = $this->embeddings_repo->get_by_post_id( $stale_id );
		$this->embeddings_repo->upsert( 'post', $stale_id, array( 0.5, 0.5 ), 'an-older-model', 2, $row->content_hash, 'post' );
		$counter->calls = 0;

		$status = $indexer->get_indexing_status( array( 'post' ) );
		$this->assertSame( 1, $status['stale'] );

		$result = $indexer->process_indexing_batch( 10, 0, array( 'post' ), 'publish', 'posts', true );
		$this->assertSame( 1, $result['success'] );
		$this->assertSame( 1, $counter->calls, 'Only the stale post is re-embedded.' );
		$this->assertSame( 0, $indexer->get_indexing_status( array( 'post' ) )['stale'] );
	}

	public function test_clearing_post_embeddings_leaves_topic_embeddings() {
		$this->embeddings_repo->upsert( 'topic', 77, array( 0.1, 0.2 ), 'm', 2, 'h' );
		$post_id = $this->make_post();
		$this->embeddings_repo->upsert( 'post', $post_id, array( 0.1, 0.2 ), 'm', 2, 'h', 'post' );

		$this->indexer_service->clear_index( 'post' );

		$this->assertNull( $this->embeddings_repo->get_by_post_id( $post_id ) );
		$this->assertNotNull( $this->embeddings_repo->get_by_topic_id( 77 ) );
	}

	public function test_clear_preview_counts_what_clear_index_deletes() {
		$post_id = $this->make_post();
		$this->embeddings_repo->upsert( 'post', $post_id, array( 0.1, 0.2 ), 'm', 2, 'h', 'post' );
		$this->embeddings_repo->upsert( 'topic', 5, array( 0.1, 0.2 ), 'm', 2, 'h' );

		$preview = $this->indexer_service->get_clear_preview();

		$this->assertSame( 1, $preview['posts'] );
		$this->assertSame( 1, $preview['topics'] );
		$this->assertSame( 2, $preview['embeddings'] );
	}

	public function test_provider_returning_other_dimensions_than_configured_does_not_loop() {
		list( $indexer, $counter ) = $this->make_counting_indexer( 2 );
		AIPS_Config::get_instance()->set_option( 'aips_embeddings_dimensions', 1536 );
		$post_id = $this->make_post();

		$indexer->ensure_indexed( $post_id );
		$indexer->ensure_indexed( $post_id );

		$this->assertSame( 1, $counter->calls, 'The provider\'s real dimension count is what rows are compared with.' );
		$this->assertSame( 0, $indexer->get_indexing_status( array( 'post' ) )['stale'] );
	}

	public function test_stale_only_batch_honors_the_embeddings_scope() {
		list( $indexer, $counter ) = $this->make_counting_indexer();
		AIPS_Config::get_instance()->set_option( 'aips_embeddings_scope', 'aips_only' );
		$in_scope  = $this->make_post( 'In scope', 'Body a' );
		$out_scope = $this->make_post( 'Out of scope', 'Body b' );
		update_post_meta( $in_scope, '_aips_generated_post', 1 );

		foreach ( array( $in_scope, $out_scope ) as $id ) {
			$this->embeddings_repo->upsert( 'post', $id, array( 0.5, 0.5 ), 'an-older-model', 2, 'x', 'post' );
		}

		$result = $indexer->process_indexing_batch( 10, 0, array( 'post' ), 'publish', 'posts', true );

		$this->assertSame( 1, $result['success'], 'Out-of-scope posts are not re-embedded.' );
		$this->assertSame( 1, $counter->calls );
	}

	public function test_automatic_stale_reindex_is_throttled() {
		list( $indexer ) = $this->make_counting_indexer();
		AIPS_Config::get_instance()->set_option( 'aips_indexer_auto_reindex_stale', true );
		AIPS_Config::get_instance()->set_option( 'aips_embeddings_scope', 'all' );
		$post_id = $this->make_post();
		$this->embeddings_repo->upsert( 'post', $post_id, array( 0.5, 0.5 ), 'an-older-model', 2, 'x', 'post' );

		delete_transient( AIPS_Content_Indexer_Service::STALE_REINDEX_THROTTLE );
		$indexer->maybe_start_stale_reindex();

		$this->assertNotFalse( get_transient( AIPS_Content_Indexer_Service::STALE_REINDEX_THROTTLE ) );
		$this->assertFalse( $indexer->maybe_start_stale_reindex(), 'A second attempt within the interval does nothing.' );
	}

	public function test_failed_automatic_stale_start_does_not_set_the_throttle() {
		list( $indexer ) = $this->make_counting_indexer();
		AIPS_Config::get_instance()->set_option( 'aips_indexer_auto_reindex_stale', true );
		AIPS_Config::get_instance()->set_option( 'aips_embeddings_scope', 'all' );
		delete_transient( AIPS_Content_Indexer_Service::STALE_REINDEX_THROTTLE );

		// Nothing is stale, so no run can start.
		$this->assertFalse( $indexer->maybe_start_stale_reindex() );
		$this->assertFalse( get_transient( AIPS_Content_Indexer_Service::STALE_REINDEX_THROTTLE ) );
	}

	public function test_lock_conflict_and_clearing_are_skipped_not_counted_as_failures() {
		list( $indexer, $counter ) = $this->make_counting_indexer();
		AIPS_Config::get_instance()->set_option( 'aips_embeddings_scope', 'all' );
		$post_id = $this->make_post();

		AIPS_Lock::acquire( AIPS_Content_Indexer_Service::LOCK_PREFIX . $post_id, 60 );
		$result = $indexer->process_indexing_batch( 10, 0, array( 'post' ), 'publish', 'posts' );
		AIPS_Lock::release( AIPS_Content_Indexer_Service::LOCK_PREFIX . $post_id );

		$this->assertSame( 0, $result['failed'], 'Another worker holding the post is not a failure.' );
		$this->assertSame( 0, $counter->calls );

		AIPS_Lock::acquire( AIPS_Content_Indexer_Service::CLEAR_LOCK, 60 );
		$cleared = $indexer->ensure_indexed( $post_id );
		AIPS_Lock::release( AIPS_Content_Indexer_Service::CLEAR_LOCK );

		$this->assertWPError( $cleared );
		$this->assertTrue( AIPS_Content_Indexer_Service::is_skippable_error( $cleared ) );
		$this->assertSame( 0, $counter->calls );
	}

	public function test_clear_index_refuses_to_run_twice_at_once() {
		AIPS_Lock::acquire( AIPS_Content_Indexer_Service::CLEAR_LOCK, 60 );

		$this->assertFalse( $this->indexer_service->clear_index() );

		AIPS_Lock::release( AIPS_Content_Indexer_Service::CLEAR_LOCK );
		$this->assertTrue( $this->indexer_service->clear_index() );
	}

	public function test_batch_reports_how_many_busy_posts_were_skipped() {
		list( $indexer ) = $this->make_counting_indexer();
		AIPS_Config::get_instance()->set_option( 'aips_embeddings_scope', 'all' );
		$post_id = $this->make_post();

		AIPS_Lock::acquire( AIPS_Content_Indexer_Service::LOCK_PREFIX . $post_id, 60 );
		$result = $indexer->process_indexing_batch( 10, 0, array( 'post' ), 'publish', 'posts' );
		AIPS_Lock::release( AIPS_Content_Indexer_Service::LOCK_PREFIX . $post_id );

		$this->assertSame( 1, $result['skipped'] );
		$this->assertSame( 0, $result['failed'] );
	}
}
