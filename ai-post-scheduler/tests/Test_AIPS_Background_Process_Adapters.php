<?php
/**
 * Tests for the Author Topic Embeddings and Bulk Generation processes, the
 * bulk job store additions, and the per-run AI budget.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Background_Process_Adapters extends WP_UnitTestCase {

	/** @var AIPS_Background_Process_Repository */
	private $runs;

	/** @var AIPS_Embeddings_Rate_Limiter */
	private $limiter;

	/** @var AIPS_Embeddings_Repository */
	private $embeddings_repo;

	/** @var AIPS_Job_Repository */
	private $job_repo;

	/** @var AIPS_Job_Manager */
	private $job_manager;

	/** @var int Embeddings the mock has generated. */
	public $generated = 0;

	/** @var bool Whether the mock should report a rate limit instead of generating. */
	public $rate_limited = false;

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();

		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_author_topics' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_embeddings' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_jobs' );

		$config = AIPS_Config::get_instance();
		$config->set_option( 'aips_indexer_batch_size', 10 );
		$config->set_option( 'aips_indexer_quota_pause_enabled', false );
		update_option( 'aips_embeddings_rate_limits_enabled', true );
		update_option( 'aips_embeddings_daily_limit', 100 );
		update_option( 'aips_embeddings_weekly_limit', 0 );
		update_option( 'aips_embeddings_monthly_limit', 0 );

		$container             = AIPS_Container::get_instance();
		$this->runs            = new AIPS_Background_Process_Repository();
		$this->limiter         = new AIPS_Embeddings_Rate_Limiter( $config );
		$this->embeddings_repo = new AIPS_Embeddings_Repository();
		$this->job_repo        = $container->get_job_repository();
		$this->job_manager     = $container->get_job_manager();
		$this->generated       = 0;
		$this->rate_limited    = false;
		$this->limiter->reset_history();
		$this->limiter->clear_cooldown();
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_background_processes' );
		wp_clear_scheduled_hook( AIPS_Managed_Background_Process::TICK_HOOK, array( AIPS_Author_Embeddings_Process::KEY ) );
		$this->limiter->reset_history();
		delete_option( 'aips_embeddings_rate_limits_enabled' );
		delete_option( 'aips_embeddings_daily_limit' );
		delete_option( 'aips_embeddings_weekly_limit' );
		delete_option( 'aips_embeddings_monthly_limit' );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Author topic embeddings
	// -------------------------------------------------------------------------

	/**
	 * @return AIPS_Author_Embeddings_Process
	 */
	private function author_process(): AIPS_Author_Embeddings_Process {
		$limiter = $this->limiter;
		$repo    = $this->embeddings_repo;
		$test    = $this;

		$service = $this->getMockBuilder( 'AIPS_Embeddings_Service' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'is_enabled', 'compute_topic_embedding', 'get_rate_limiter', 'get_embeddings_repository' ) )
			->getMock();
		$service->method( 'is_enabled' )->willReturn( true );
		$service->method( 'get_rate_limiter' )->willReturn( $limiter );
		$service->method( 'get_embeddings_repository' )->willReturn( $repo );
		$service->method( 'compute_topic_embedding' )->willReturnCallback( function ( $topic_id ) use ( $limiter, $repo, $test ) {
			if ( $test->rate_limited ) {
				return new WP_Error( 'rate_limit_exceeded', 'Rate limit reached.' );
			}
			$limiter->record_usage( 1 );
			$test->generated++;
			$repo->upsert( 'topic', $topic_id, array( 0.1, 0.2 ), 'm', 2, md5( (string) $topic_id ) );
			return array( 0.1, 0.2 );
		} );

		return new AIPS_Author_Embeddings_Process( $this->runs, $service, AIPS_Config::get_instance(), $repo );
	}

	/**
	 * @param int    $author_id Author.
	 * @param string $status    Topic status.
	 * @return int Topic ID.
	 */
	private function topic( int $author_id, string $status = 'approved' ): int {
		return (int) ( new AIPS_Author_Topics_Repository() )->create( array(
			'author_id'   => $author_id,
			'topic_title' => 'Topic ' . wp_generate_password( 6, false ),
			'status'      => $status,
		) );
	}

	public function test_author_embeddings_processes_unembedded_topics_and_completes() {
		$this->topic( 1 );
		$this->topic( 1, 'pending' );
		$this->topic( 2, 'rejected' ); // Rejected topics are not embedded.

		$process  = $this->author_process();
		$snapshot = $process->start();
		$this->assertIsArray( $snapshot );
		$this->assertSame( 2, $snapshot['total'] );

		$process->tick();

		$this->assertSame( 2, $this->generated );
		$snapshot = $process->get_snapshot();
		$this->assertSame( 'idle', $snapshot['status'] );
		$this->assertSame( 'completed', $snapshot['last_status'] );
		$this->assertSame( 2, $snapshot['ai_calls_used'] );
		$this->assertTrue( $process->uses_ai() );
	}

	public function test_author_embeddings_can_be_scoped_to_one_author() {
		$this->topic( 1 );
		$this->topic( 2 );
		$this->topic( 2 );

		$process  = $this->author_process();
		$snapshot = $process->start( array( 'author_id' => 2 ) );

		$this->assertSame( 2, $snapshot['total'] );
		$this->assertSame( 2, (int) $this->runs->get_open( AIPS_Author_Embeddings_Process::KEY )->options['author_id'] );

		$process->tick();
		$this->assertSame( 2, $this->generated );
		$this->assertSame( 1, $this->embeddings_repo->get_unindexed_topic_count(), "The other author's topic is untouched." );
	}

	public function test_author_embeddings_wait_when_the_provider_rate_limits() {
		$this->topic( 1 );
		$process = $this->author_process();
		$process->start();

		$this->rate_limited = true;
		$process->tick();

		$snapshot = $process->get_snapshot();
		$this->assertSame( 'running', $snapshot['status'] );
		$this->assertSame( 0, $snapshot['processed'], 'A rate-limited topic is retried, not skipped.' );
		$this->assertNotSame( '', $snapshot['message'] );

		$this->rate_limited = false;
		wp_clear_scheduled_hook( AIPS_Managed_Background_Process::TICK_HOOK, array( AIPS_Author_Embeddings_Process::KEY ) );
		$process->tick();
		$this->assertSame( 1, $this->generated );
	}

	public function test_author_embeddings_wait_for_quota_without_calling_the_provider() {
		$this->topic( 1 );
		update_option( 'aips_embeddings_daily_limit', 1 );
		$this->limiter = new AIPS_Embeddings_Rate_Limiter( AIPS_Config::get_instance() );
		$this->limiter->record_usage( 1 );

		$process = $this->author_process();
		$process->start();
		$process->tick();

		$this->assertSame( 0, $this->generated );
		$this->assertSame( 'waiting_quota', $process->get_snapshot()['status'] );
	}

	public function test_ai_budget_pauses_the_run_and_resume_lifts_it() {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->topic( 1 );
		}

		$process = $this->author_process();
		$process->start( array( 'ai_budget' => 2 ) );

		$process->tick();
		$this->assertSame( 2, $this->generated, 'The slice is capped to the budget.' );

		wp_clear_scheduled_hook( AIPS_Managed_Background_Process::TICK_HOOK, array( AIPS_Author_Embeddings_Process::KEY ) );
		$process->tick();
		$snapshot = $process->get_snapshot();
		$this->assertSame( 'paused', $snapshot['status'] );
		$this->assertSame( 2, $snapshot['ai_calls_budget'] );

		$this->assertTrue( $process->resume() );
		$this->assertSame( 0, $process->get_snapshot()['ai_calls_budget'], 'Resuming an exhausted budget carries on without one.' );

		$process->tick();
		$this->assertSame( 5, $this->generated );
	}

	public function test_author_embeddings_nothing_to_do_and_disabled_estimate() {
		$process = $this->author_process();

		$result = $process->start();
		$this->assertWPError( $result );
		$this->assertSame( 'aips_bg_nothing_to_do', $result->get_error_code() );

		$this->topic( 3 );
		$estimate = $process->get_estimate( array( 'author_id' => 3 ) );
		$this->assertSame( 1, $estimate['items'] );
		$this->assertSame( 1, $estimate['ai_calls'] );
		$this->assertSame( 0, $process->get_estimate( array( 'author_id' => 4 ) )['items'] );
	}

	// -------------------------------------------------------------------------
	// Bulk generation
	// -------------------------------------------------------------------------

	/**
	 * Create a bulk job.
	 *
	 * @param string $job_type Job type.
	 * @return string Job ID.
	 */
	private function bulk_job( string $job_type = 'bulk_post_generation' ): string {
		return $this->job_manager->dispatch_batch(
			$job_type,
			array( 1, 2, 3, 4 ),
			2,
			60,
			array( 'history_meta' => array( 'template_id' => 7 ) )
		);
	}

	public function test_bulk_generation_reports_open_jobs_and_cannot_be_started_here() {
		$process = new AIPS_Bulk_Generation_Process( $this->job_manager );

		$idle = $process->get_snapshot();
		$this->assertSame( 'idle', $idle['status'] );
		$this->assertFalse( $idle['can_start'] );

		$job_id = $this->bulk_job();
		$snap   = $process->get_snapshot();
		$this->assertSame( 'running', $snap['status'] );
		$this->assertSame( 4, $snap['total'] );
		$this->assertTrue( $snap['can_pause'] );
		$this->assertFalse( $snap['can_start'] );

		$start = $process->start();
		$this->assertWPError( $start );
		$this->assertSame( 'aips_bg_start_elsewhere', $start->get_error_code() );

		$this->job_manager->cancel( $job_id );
	}

	public function test_bulk_generation_ignores_jobs_of_other_types() {
		$other = $this->job_manager->dispatch( 'unrelated_test_type', array( 1, 2 ) );

		$this->assertSame( 'idle', ( new AIPS_Bulk_Generation_Process( $this->job_manager ) )->get_snapshot()['status'] );
		$this->job_manager->cancel( $other );
	}

	public function test_bulk_generation_pause_and_resume() {
		$process = new AIPS_Bulk_Generation_Process( $this->job_manager );
		$job_id  = $this->bulk_job();

		$this->assertTrue( $process->pause() );
		$stored = $this->job_repo->find_by_id( $job_id );
		$this->assertSame( 'paused', $stored->get_status() );
		$this->assertSame( 'paused', $process->get_snapshot()['status'] );
		$this->assertFalse( $process->pause(), 'Nothing left to pause.' );

		$this->assertTrue( $process->resume() );
		$stored = $this->job_repo->find_by_id( $job_id );
		$this->assertSame( 'pending', $stored->get_status() );
		$this->assertSame( 'running', $process->get_snapshot()['status'] );

		$this->job_manager->cancel( $job_id );
	}

	public function test_bulk_generation_cancel() {
		$process = new AIPS_Bulk_Generation_Process( $this->job_manager );
		$job_id  = $this->bulk_job();

		$this->assertTrue( $process->cancel() );
		$stored = $this->job_repo->find_by_id( $job_id );
		$this->assertSame( 'cancelled', $stored->get_status() );
		$this->assertSame( 'idle', $process->get_snapshot()['status'] );
	}

	// -------------------------------------------------------------------------
	// Estimate endpoint
	// -------------------------------------------------------------------------

	/**
	 * @param callable $callable Controller method.
	 * @return array Decoded JSON response.
	 */
	private function call_ajax( callable $callable ): array {
		$_REQUEST = array_merge( $_REQUEST, $_POST );
		ob_start();
		try {
			$callable();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected after wp_send_json_*.
		}
		return (array) json_decode( ob_get_clean(), true );
	}

	public function test_estimate_endpoint_only_offers_a_budget_where_it_is_honored() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$controller = new AIPS_Background_Processes_Controller();

		$supports = array(
			'internal_links_indexing'  => true,
			'author_topic_embeddings'  => true,
			'content_indexer_queue'    => false,
			'relationships_recompute'  => false,
		);

		foreach ( $supports as $key => $expected ) {
			$_POST = array(
				'nonce'   => wp_create_nonce( AIPS_Background_Processes_Controller::NONCE_ACTION ),
				'process' => $key,
			);

			$response = $this->call_ajax( array( $controller, 'ajax_estimate' ) );

			$this->assertTrue( $response['success'], $key );
			$this->assertSame( $expected, $response['data']['supports_budget'], $key );
		}

		$_POST = array();
	}

	// -------------------------------------------------------------------------
	// Registry and rebuild-all
	// -------------------------------------------------------------------------

	public function test_manager_registers_the_new_processes() {
		$keys = array_keys( ( new AIPS_Background_Process_Manager() )->all() );

		$this->assertContains( 'author_topic_embeddings', $keys );
		$this->assertContains( 'bulk_generation', $keys );
	}

	public function test_recompute_estimate_follows_the_requested_mode() {
		$post = wp_insert_post( array( 'post_title' => 'A', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$this->embeddings_repo->upsert( 'post', $post, array( 1.0, 0.0 ), 'm', 2, md5( 'a' ), 'post' );
		( new AIPS_Relationships_Repository() )->sync_for_source( 'post', $post, array( array( 'target_type' => 'post', 'target_id' => $post + 1, 'similarity' => 0.9 ) ), 'related_post' );

		AIPS_Config::get_instance()->set_option( 'aips_indexer_post_types', array( 'post' ) );
		$process = new AIPS_Relationships_Recompute_Process();

		$this->assertSame( 0, $process->get_estimate()['items'], 'Default mode skips posts that already have related posts.' );
		$this->assertGreaterThanOrEqual( 1, $process->get_estimate( array( 'mode' => 'all' ) )['items'] );
	}
}
