<?php
/**
 * Tests for Content Indexer Queue sessions, concurrency, and AI-call attribution.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Content_Indexer_Queue_Session extends WP_UnitTestCase {

	/** @var AIPS_Background_Process_Repository */
	private $run_repo;

	/** @var AIPS_Content_Indexer_Queue_Repository */
	private $queue_repo;

	/** @var AIPS_Embeddings_Repository */
	private $embeddings_repo;

	/** @var AIPS_Relationships_Repository */
	private $relationships_repo;

	/** @var AIPS_Embeddings_Rate_Limiter */
	private $limiter;

	/** @var AIPS_Content_Indexer_Service */
	private $indexer;

	/** @var AIPS_Content_Indexer_Queue_Process */
	private $process;

	/** @var int Number of provider embedding calls attempted. */
	public $provider_calls = 0;

	/** @var array<int,WP_Error> Map of call number => WP_Error to simulate failure. */
	public $fail_on_calls = array();

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();

		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_background_processes' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_embeddings' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_relationships' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_author_topics' );

		delete_option( AIPS_Content_Indexer_Queue_Repository::STATE_OPTION );
		delete_option( AIPS_Content_Indexer_Queue_Repository::LEGACY_POST_OPTION );
		delete_option( AIPS_Content_Indexer_Queue_Repository::LEGACY_TOPIC_OPTION );
		delete_option( AIPS_Content_Indexer_Queue_Repository::PAUSED_OPTION );
		wp_clear_scheduled_hook( 'aips_process_pending_indexer_queue' );

		$config = AIPS_Config::get_instance();
		$config->set_option( 'aips_embeddings_enabled', true );
		$config->set_option( 'aips_embeddings_scope', 'all' );
		$config->set_option( 'aips_indexer_post_types', array( 'post' ) );
		$config->set_option( 'aips_indexer_batch_size', 10 );
		$config->set_option( 'aips_indexer_quota_pause_enabled', false );
		$config->set_option( 'aips_embeddings_persistent_cache_enabled', false );
		$config->set_option( 'aips_indexer_consecutive_error_threshold', 5 );
		$config->set_option( 'aips_auto_index_on_publish', false );
		update_option( 'aips_embeddings_rate_limits_enabled', true );
		update_option( 'aips_embeddings_daily_limit', 100 );
		update_option( 'aips_embeddings_weekly_limit', 0 );
		update_option( 'aips_embeddings_monthly_limit', 0 );

		$this->run_repo           = new AIPS_Background_Process_Repository();
		$this->queue_repo         = new AIPS_Content_Indexer_Queue_Repository( $this->run_repo );
		$this->embeddings_repo    = new AIPS_Embeddings_Repository();
		$this->relationships_repo = new AIPS_Relationships_Repository();
		$this->limiter            = new AIPS_Embeddings_Rate_Limiter( $config );
		$this->limiter->reset_history();
		$this->limiter->clear_cooldown();
		$this->limiter->reset_provider_call_count();

		$this->provider_calls = 0;
		$this->fail_on_calls  = array();

		$test    = $this;
		$mock_ai = $this->createMock( AIPS_AI_Service_Interface::class );
		$mock_ai->method( 'generate_embedding' )->willReturnCallback( function ( $text ) use ( $test ) {
			$test->provider_calls++;
			if ( isset( $test->fail_on_calls[ $test->provider_calls ] ) ) {
				return $test->fail_on_calls[ $test->provider_calls ];
			}
			return array( 0.6, 0.8 );
		} );

		$embeddings_service = new AIPS_Embeddings_Service(
			$mock_ai,
			new AIPS_Logger(),
			$config,
			$this->limiter,
			$this->embeddings_repo
		);

		$this->indexer = new AIPS_Content_Indexer_Service(
			$this->embeddings_repo,
			$this->relationships_repo,
			$embeddings_service,
			null,
			new AIPS_Logger(),
			$config,
			null,
			null,
			null,
			$this->queue_repo
		);

		$this->process = new AIPS_Content_Indexer_Queue_Process( $this->indexer, $this->limiter );
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_background_processes' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_embeddings' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_relationships' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_author_topics' );
		delete_option( AIPS_Content_Indexer_Queue_Repository::STATE_OPTION );
		delete_option( AIPS_Content_Indexer_Queue_Repository::LEGACY_POST_OPTION );
		delete_option( AIPS_Content_Indexer_Queue_Repository::LEGACY_TOPIC_OPTION );
		delete_option( AIPS_Content_Indexer_Queue_Repository::PAUSED_OPTION );
		delete_option( 'aips_embeddings_rate_limits_enabled' );
		delete_option( 'aips_embeddings_daily_limit' );
		delete_option( 'aips_embeddings_weekly_limit' );
		delete_option( 'aips_embeddings_monthly_limit' );
		delete_option( 'aips_embeddings_scope' );
		delete_option( 'aips_auto_index_on_publish' );
		delete_option( 'aips_indexer_consecutive_error_threshold' );
		wp_clear_scheduled_hook( 'aips_process_pending_indexer_queue' );
		$this->limiter->reset_history();
		$this->limiter->clear_cooldown();
		$this->limiter->reset_provider_call_count();
		parent::tearDown();
	}

	/**
	 * @param string $title   Title.
	 * @param string $status  Post status.
	 * @return int Post ID.
	 */
	private function make_post( string $title, string $status = 'publish' ): int {
		return (int) wp_insert_post( array(
			'post_title'   => $title,
			'post_content' => 'Content for ' . $title . ' ' . wp_generate_password( 8, false ),
			'post_status'  => $status,
			'post_type'    => 'post',
		) );
	}

	public function test_session_starts_on_enqueue_and_ignores_duplicate_pending_items() {
		$p1 = $this->make_post( 'Post 1' );
		$p2 = $this->make_post( 'Post 2' );

		$this->indexer->enqueue_posts_for_indexing( array( $p1, $p2 ) );

		$snap = $this->process->get_snapshot();
		$this->assertSame( 'running', $snap['status'] );
		$this->assertGreaterThan( 0, $snap['run_id'] );
		$this->assertSame( 2, $snap['total'] );
		$this->assertSame( 0, $snap['processed'] );
		$this->assertSame( 2, $snap['pending'] );
		$this->assertTrue( $snap['tracks_ai_calls'] );

		// Enqueueing an already-pending item does not increase total.
		$this->indexer->enqueue_post_for_indexing( $p1 );
		$snap2 = $this->process->get_snapshot();
		$this->assertSame( $snap['run_id'], $snap2['run_id'] );
		$this->assertSame( 2, $snap2['total'] );

		// Adding distinct new work during the open session increases total.
		$p3 = $this->make_post( 'Post 3' );
		$this->indexer->enqueue_post_for_indexing( $p3 );
		$snap3 = $this->process->get_snapshot();
		$this->assertSame( $snap['run_id'], $snap3['run_id'] );
		$this->assertSame( 3, $snap3['total'] );
		$this->assertSame( 3, $snap3['pending'] );
	}

	public function test_legacy_pending_items_are_adopted_without_inventing_completed_counts() {
		$p1 = $this->make_post( 'Legacy Post 1' );
		$p2 = $this->make_post( 'Legacy Post 2' );

		// Simulate pre-existing legacy queue options before any session row exists.
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_background_processes' );
		delete_option( AIPS_Content_Indexer_Queue_Repository::STATE_OPTION );
		update_option( 'aips_pending_index_queue', array( $p1, $p2 ), false );
		$this->assertNull( $this->run_repo->get_open( AIPS_Content_Indexer_Queue_Process::KEY ) );

		$snap = $this->process->get_snapshot();
		$this->assertSame( 'running', $snap['status'] );
		$this->assertGreaterThan( 0, $snap['run_id'] );
		$this->assertSame( 2, $snap['total'] );
		$this->assertSame( 0, $snap['processed'], 'Historical completed counts are never invented.' );
		$this->assertSame( 2, $snap['pending'] );
	}

	public function test_new_work_and_later_edits_to_processed_items_increase_total_and_explain_percentage() {
		AIPS_Config::get_instance()->set_option( 'aips_indexer_batch_size', 2 );

		$p1 = $this->make_post( 'First Post' );
		$p2 = $this->make_post( 'Second Post' );
		$this->indexer->enqueue_posts_for_indexing( array( $p1, $p2 ) );

		// Claim and process only the first item so the session stays open at 50%.
		AIPS_Config::get_instance()->set_option( 'aips_indexer_batch_size', 1 );
		$this->indexer->process_pending_indexer_queue();

		$snap = $this->process->get_snapshot();
		$this->assertSame( 'running', $snap['status'] );
		$this->assertSame( 1, $snap['processed'] );
		$this->assertSame( 2, $snap['total'] );
		$this->assertSame( 50, $snap['percent'] );
		$this->assertSame( 1, $snap['ai_calls_used'] );

		// A later edit to already-processed $p1 plus a new $p3 increases total from 2 to 4 (percent drops from 50% to 25%).
		$p3 = $this->make_post( 'Third Post' );
		$this->indexer->enqueue_posts_for_indexing( array( $p1, $p3 ) );

		$snap_after = $this->process->get_snapshot();
		$this->assertSame( $snap['run_id'], $snap_after['run_id'] );
		$this->assertSame( 1, $snap_after['processed'] );
		$this->assertSame( 4, $snap_after['total'] );
		$this->assertSame( 25, $snap_after['percent'] );
		$this->assertStringContainsString( 'New items were added', $snap_after['message'] );
	}

	public function test_edit_arriving_while_item_is_in_flight_remains_queued_for_another_pass() {
		$p1 = $this->make_post( 'In-Flight Post' );
		$this->indexer->enqueue_post_for_indexing( $p1 );

		// Worker claims $p1 (version 1).
		$claim = $this->queue_repo->claim_slice( 1, 'worker_a' );
		$this->assertCount( 1, $claim['items'] );
		$this->assertSame( 1, $claim['items'][0]['claimed_version'] );

		// While $p1 is in flight, the user edits and saves $p1 again.
		$this->indexer->enqueue_post_for_indexing( $p1 );

		$snap_mid = $this->process->get_snapshot();
		$this->assertSame( 2, $snap_mid['total'], 'The new edit adds a second pass to the session total.' );
		$this->assertSame( 1, $snap_mid['in_flight'] );
		$this->assertSame( 1, $snap_mid['pending'] );

		// Worker A finishes its pass and acknowledges version 1.
		$ack = $this->queue_repo->acknowledge_item(
			'worker_a',
			'post',
			$p1,
			1,
			AIPS_Content_Indexer_Queue_Repository::OUTCOME_SUCCESS,
			1
		);

		$this->assertTrue( $ack['acknowledged'] );
		$this->assertFalse( $ack['completed_session'], 'Session must stay open because the newer edit is still pending.' );
		$this->assertSame( 1, $ack['remaining'] );

		// Duplicate acknowledgement for the same token/version is a harmless no-op.
		$dup = $this->queue_repo->acknowledge_item(
			'worker_a',
			'post',
			$p1,
			1,
			AIPS_Content_Indexer_Queue_Repository::OUTCOME_SUCCESS,
			1
		);
		$this->assertFalse( $dup['acknowledged'] );

		$snap_after_first = $this->process->get_snapshot();
		$this->assertSame( 1, $snap_after_first['processed'] );
		$this->assertSame( 1, $snap_after_first['ai_calls_used'] );

		// Second pass claims version 2 and completes the session.
		$claim2 = $this->queue_repo->claim_slice( 1, 'worker_b' );
		$this->assertCount( 1, $claim2['items'] );
		$this->assertSame( 2, $claim2['items'][0]['claimed_version'] );

		$ack2 = $this->queue_repo->acknowledge_item(
			'worker_b',
			'post',
			$p1,
			2,
			AIPS_Content_Indexer_Queue_Repository::OUTCOME_SUCCESS,
			1
		);
		$this->assertTrue( $ack2['acknowledged'] );
		$this->assertTrue( $ack2['completed_session'] );

		$final_snap = $this->process->get_snapshot();
		$this->assertSame( 'idle', $final_snap['status'] );
		$this->assertSame( 'completed', $final_snap['last_status'] );
		$this->assertSame( 2, $final_snap['processed'] );
		$this->assertSame( 2, $final_snap['total'] );
		$this->assertSame( 2, $final_snap['ai_calls_used'] );
	}

	public function test_concurrent_workers_claim_distinct_slices_and_expired_claims_recover() {
		$p1 = $this->make_post( 'Post 1' );
		$p2 = $this->make_post( 'Post 2' );
		$this->indexer->enqueue_posts_for_indexing( array( $p1, $p2 ) );

		$claim1 = $this->queue_repo->claim_slice( 1, 'worker_1' );
		$claim2 = $this->queue_repo->claim_slice( 1, 'worker_2' );
		$claim3 = $this->queue_repo->claim_slice( 1, 'worker_3' );

		$this->assertCount( 1, $claim1['items'] );
		$this->assertCount( 1, $claim2['items'] );
		$this->assertCount( 0, $claim3['items'] );
		$this->assertNotSame( $claim1['items'][0]['id'], $claim2['items'][0]['id'] );

		// Simulate worker_1 crashing by backdating its claimed_at timestamp past CLAIM_TTL.
		$state = get_option( AIPS_Content_Indexer_Queue_Repository::STATE_OPTION );
		$key1  = $claim1['items'][0]['key'];
		$state['items'][ $key1 ]['claimed_at'] = time() - ( AIPS_Content_Indexer_Queue_Repository::CLAIM_TTL + 10 );
		update_option( AIPS_Content_Indexer_Queue_Repository::STATE_OPTION, $state, false );

		// Worker 2 finishes its item; session must not complete while item 1 is still unacknowledged.
		$ack2 = $this->queue_repo->acknowledge_item(
			'worker_2',
			'post',
			$claim2['items'][0]['id'],
			$claim2['items'][0]['claimed_version'],
			AIPS_Content_Indexer_Queue_Repository::OUTCOME_SUCCESS,
			1
		);
		$this->assertTrue( $ack2['acknowledged'] );
		$this->assertFalse( $ack2['completed_session'] );

		// Worker 3 can now claim the expired item 1 and complete the session without double-counting item 2.
		$recovered = $this->queue_repo->claim_slice( 1, 'worker_3' );
		$this->assertCount( 1, $recovered['items'] );
		$this->assertSame( $claim1['items'][0]['id'], $recovered['items'][0]['id'] );

		// Stale worker_1 trying to acknowledge late is rejected because worker_3 now owns the token.
		$late_ack = $this->queue_repo->acknowledge_item(
			'worker_1',
			'post',
			$claim1['items'][0]['id'],
			$claim1['items'][0]['claimed_version'],
			AIPS_Content_Indexer_Queue_Repository::OUTCOME_SUCCESS,
			1
		);
		$this->assertFalse( $late_ack['acknowledged'] );

		$ack3 = $this->queue_repo->acknowledge_item(
			'worker_3',
			'post',
			$recovered['items'][0]['id'],
			$recovered['items'][0]['claimed_version'],
			AIPS_Content_Indexer_Queue_Repository::OUTCOME_SUCCESS,
			1
		);
		$this->assertTrue( $ack3['acknowledged'] );
		$this->assertTrue( $ack3['completed_session'] );

		$snap = $this->process->get_snapshot();
		$this->assertSame( 2, $snap['processed'] );
		$this->assertSame( 2, $snap['total'] );
	}

	public function test_skipped_permanent_failures_and_retryable_failures_are_accounted_accurately() {
		$draft_post     = $this->make_post( 'Draft Skipped Post', 'draft' );
		$failing_post   = $this->make_post( 'Permanent Failure Post', 'publish' );
		$rate_lim_post  = $this->make_post( 'Rate Limited Retryable Post', 'publish' );

		// Ensure no embedding was written by any global save_post listener during post creation.
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_embeddings' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_background_processes' );
		delete_option( AIPS_Content_Indexer_Queue_Repository::STATE_OPTION );
		delete_option( AIPS_Content_Indexer_Queue_Repository::LEGACY_POST_OPTION );

		// Reset any calls made during post creation hooks.
		$this->provider_calls = 0;
		$this->limiter->reset_provider_call_count();
		$this->limiter->clear_cooldown();

		// 1st provider call (for $failing_post) returns a permanent error;
		// 2nd provider call (for $rate_lim_post) returns a retryable rate-limit error.
		$this->fail_on_calls[1] = new WP_Error( 'invalid_request', 'Invalid input payload.' );
		$this->fail_on_calls[2] = new WP_Error( 'rate_limit_exceeded', '429 Too Many Requests' );

		$this->indexer->enqueue_posts_for_indexing( array( $draft_post, $failing_post, $rate_lim_post ) );
		$this->indexer->process_pending_indexer_queue();

		$snap = $this->process->get_snapshot();
		// $draft_post was skipped (resolved: +1 processed, 0 failed, 0 AI calls)
		// $failing_post permanently failed (resolved: +1 processed, +1 failed, +1 AI call)
		// $rate_lim_post hit rate limit (retryable: +0 processed, remains pending, +1 AI call attempted)
		$this->assertSame( 2, $snap['processed'] );
		$this->assertSame( 1, $snap['failed'] );
		$this->assertSame( 3, $snap['total'] );
		$this->assertSame( 1, $snap['pending'] );
		$this->assertSame( 2, $snap['ai_calls_used'] );

		// Clear cooldown and let the retryable post succeed on the next run.
		$this->limiter->clear_cooldown();
		$this->fail_on_calls = array();
		$this->indexer->process_pending_indexer_queue();

		$final = $this->process->get_snapshot();
		$this->assertSame( 'idle', $final['status'] );
		$this->assertSame( 'completed', $final['last_status'] );
		$this->assertSame( 3, $final['processed'] );
		$this->assertSame( 1, $final['failed'] );
		$this->assertSame( 3, $final['total'] );
		$this->assertSame( 3, $final['ai_calls_used'] );
	}

	public function test_pause_resume_and_stop_preserve_session_counts_and_keep_finished_session_visible() {
		AIPS_Config::get_instance()->set_option( 'aips_indexer_batch_size', 1 );

		$p1 = $this->make_post( 'Pause Test 1' );
		$p2 = $this->make_post( 'Pause Test 2' );
		$this->indexer->enqueue_posts_for_indexing( array( $p1, $p2 ) );

		$this->indexer->process_pending_indexer_queue();
		$run_id = $this->process->get_snapshot()['run_id'];

		$this->assertTrue( $this->process->pause() );
		$paused = $this->process->get_snapshot();
		$this->assertSame( 'paused', $paused['status'] );
		$this->assertSame( $run_id, $paused['run_id'] );
		$this->assertSame( 1, $paused['processed'] );
		$this->assertSame( 2, $paused['total'] );
		$this->assertSame( 1, $paused['ai_calls_used'] );

		$this->assertTrue( $this->process->resume() );
		$resumed = $this->process->get_snapshot();
		$this->assertSame( 'running', $resumed['status'] );
		$this->assertSame( $run_id, $resumed['run_id'] );
		$this->assertSame( 1, $resumed['processed'] );

		// Stop cancels the session, clears pending work, and keeps completed counts visible until a new session starts.
		$this->assertTrue( $this->process->cancel() );
		$stopped = $this->process->get_snapshot();
		$this->assertSame( 'idle', $stopped['status'] );
		$this->assertSame( 'cancelled', $stopped['last_status'] );
		$this->assertSame( $run_id, $stopped['run_id'] );
		$this->assertSame( 1, $stopped['processed'] );
		$this->assertSame( 2, $stopped['total'] );
		$this->assertSame( 1, $stopped['ai_calls_used'] );
		$this->assertSame( 0, $stopped['pending'] );
		$this->assertEmpty( get_option( 'aips_pending_index_queue' ) );

		// Enqueueing new work starts a fresh session ID.
		$p3 = $this->make_post( 'New Session Post' );
		$this->indexer->enqueue_post_for_indexing( $p3 );
		$next_snap = $this->process->get_snapshot();
		$this->assertGreaterThan( $run_id, $next_snap['run_id'] );
		$this->assertSame( 1, $next_snap['total'] );
		$this->assertSame( 0, $next_snap['processed'] );
	}

	public function test_ai_call_attribution_excludes_cache_hits_and_quota_blocks() {
		AIPS_Config::get_instance()->set_option( 'aips_embeddings_persistent_cache_enabled', true );

		$p1 = $this->make_post( 'Shared Content Post' );
		$p2 = $this->make_post( 'Duplicate Content Post' );
		// Make $p2 have identical text so it hits the in-memory / transient embedding cache.
		$post1_obj = get_post( $p1 );
		wp_update_post( array(
			'ID'           => $p2,
			'post_title'   => $post1_obj->post_title,
			'post_content' => $post1_obj->post_content,
		) );

		$this->indexer->enqueue_posts_for_indexing( array( $p1, $p2 ) );
		$this->indexer->process_pending_indexer_queue();

		$snap = $this->process->get_snapshot();
		$this->assertSame( 'completed', $snap['last_status'] );
		$this->assertSame( 2, $snap['processed'] );
		$this->assertSame( 1, $snap['ai_calls_used'], 'Cache hit for the second post must count as 0 provider calls.' );
	}
}
