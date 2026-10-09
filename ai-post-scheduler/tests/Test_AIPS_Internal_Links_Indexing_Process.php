<?php
/**
 * Tests for AIPS_Internal_Links_Indexing_Process (the managed "Index Posts" job).
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Internal_Links_Indexing_Process extends WP_UnitTestCase {

	/** @var AIPS_Background_Process_Repository */
	private $repo;

	/** @var AIPS_Embeddings_Rate_Limiter */
	private $limiter;

	/** @var AIPS_Embeddings_Service|\PHPUnit\Framework\MockObject\MockObject */
	private $embeddings_service;

	/** @var AIPS_Internal_Links_Indexing_Process */
	private $process;

	/** @var int Number of embeddings the mock has generated. */
	private $generated = 0;

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();

		$config = AIPS_Config::get_instance();
		$config->set_option( 'aips_embeddings_scope', 'all' );
		$config->set_option( 'aips_indexer_post_types', array( 'post' ) );
		$config->set_option( 'aips_indexer_batch_size', 10 );
		$config->set_option( 'aips_indexer_quota_pause_enabled', false );
		update_option( 'aips_embeddings_rate_limits_enabled', true );
		update_option( 'aips_embeddings_daily_limit', 50 );
		update_option( 'aips_embeddings_weekly_limit', 0 );
		update_option( 'aips_embeddings_monthly_limit', 0 );

		// Start from an empty site: the WordPress test install ships a sample post.
		foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $existing_id ) {
			wp_delete_post( $existing_id, true );
		}

		$this->repo      = new AIPS_Background_Process_Repository();
		$this->limiter   = new AIPS_Embeddings_Rate_Limiter( $config );
		$this->generated = 0;
		$this->limiter->reset_history();
		$this->limiter->clear_cooldown();

		$limiter    = $this->limiter;
		$test_case  = $this;
		$this->embeddings_service = $this->getMockBuilder( 'AIPS_Embeddings_Service' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'is_enabled', 'is_embeddings_supported', 'generate_embedding', 'get_active_model', 'get_rate_limiter' ) )
			->getMock();
		$this->embeddings_service->method( 'is_enabled' )->willReturn( true );
		$this->embeddings_service->method( 'is_embeddings_supported' )->willReturn( true );
		$this->embeddings_service->method( 'get_active_model' )->willReturn( 'text-embedding-3-small' );
		$this->embeddings_service->method( 'get_rate_limiter' )->willReturn( $limiter );
		$this->embeddings_service->method( 'generate_embedding' )->willReturnCallback( function () use ( $limiter, $test_case ) {
			$limiter->record_usage( 1 );
			$test_case->generated++;
			return array( 0.1, 0.2, 0.3 );
		} );

		$embeddings_repo = new AIPS_Embeddings_Repository();
		$service         = new AIPS_Internal_Links_Service( $embeddings_repo, new AIPS_Internal_Links_Repository(), $this->embeddings_service );

		$this->process = new AIPS_Internal_Links_Indexing_Process( $this->repo, $service, $this->embeddings_service, $config );
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_background_processes' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_embeddings' );
		wp_clear_scheduled_hook( AIPS_Managed_Background_Process::TICK_HOOK, array( AIPS_Internal_Links_Indexing_Process::KEY ) );
		$this->limiter->reset_history();
		delete_option( 'aips_embeddings_rate_limits_enabled' );
		delete_option( 'aips_embeddings_daily_limit' );
		delete_option( 'aips_embeddings_weekly_limit' );
		delete_option( 'aips_embeddings_monthly_limit' );
		parent::tearDown();
	}

	/**
	 * @param int $count Posts to create.
	 * @return int[]
	 */
	private function create_posts( int $count ): array {
		$ids = array();
		for ( $i = 1; $i <= $count; $i++ ) {
			$ids[] = wp_insert_post( array(
				'post_title'   => 'Indexing post ' . $i,
				'post_content' => 'Body of indexing post ' . $i,
				'post_status'  => 'publish',
				'post_type'    => 'post',
			) );
		}
		return $ids;
	}

	public function test_start_creates_a_running_run_and_schedules_a_tick() {
		$this->create_posts( 3 );

		$snapshot = $this->process->start();

		$this->assertIsArray( $snapshot );
		$this->assertSame( 'running', $snapshot['status'] );
		$this->assertGreaterThanOrEqual( 3, $snapshot['total'] );
		$this->assertTrue( $snapshot['can_pause'] );
		$this->assertFalse( $snapshot['can_start'] );
		$this->assertNotFalse( wp_next_scheduled( AIPS_Managed_Background_Process::TICK_HOOK, array( AIPS_Internal_Links_Indexing_Process::KEY ) ) );
	}

	public function test_start_refuses_a_second_run_and_an_empty_run() {
		$result = $this->process->start();
		$this->assertWPError( $result );
		$this->assertSame( 'aips_bg_nothing_to_do', $result->get_error_code() );

		$this->create_posts( 2 );
		$this->assertIsArray( $this->process->start() );

		$again = $this->process->start();
		$this->assertWPError( $again );
		$this->assertSame( 'aips_bg_already_open', $again->get_error_code() );
	}

	public function test_ticks_index_posts_then_complete() {
		$this->create_posts( 3 );
		$this->process->start();

		$this->process->tick();
		$snapshot = $this->process->get_snapshot();
		$this->assertSame( 3, $this->generated );
		$this->assertSame( 3, $snapshot['processed'] );
		$this->assertSame( 3, $snapshot['ai_calls_used'] );
		$this->assertSame( 'running', $snapshot['status'], 'A slice that found work is followed by another to confirm nothing is left.' );

		wp_clear_scheduled_hook( AIPS_Managed_Background_Process::TICK_HOOK, array( AIPS_Internal_Links_Indexing_Process::KEY ) );
		$this->process->tick();

		$snapshot = $this->process->get_snapshot();
		$this->assertSame( 'idle', $snapshot['status'] );
		$this->assertSame( 'completed', $snapshot['last_status'] );
	}

	public function test_pause_stops_ticks_and_resume_continues() {
		$this->create_posts( 3 );
		$this->process->start();

		$this->assertTrue( $this->process->pause() );
		$this->assertSame( 'paused', $this->process->get_snapshot()['status'] );
		$this->assertFalse( wp_next_scheduled( AIPS_Managed_Background_Process::TICK_HOOK, array( AIPS_Internal_Links_Indexing_Process::KEY ) ) );

		$this->process->tick();
		$this->assertSame( 0, $this->generated, 'A paused run must not process anything.' );

		$this->assertTrue( $this->process->resume() );
		$this->assertSame( 'running', $this->process->get_snapshot()['status'] );
		$this->assertNotFalse( wp_next_scheduled( AIPS_Managed_Background_Process::TICK_HOOK, array( AIPS_Internal_Links_Indexing_Process::KEY ) ) );

		$this->process->tick();
		$this->assertSame( 3, $this->generated );
	}

	public function test_cancel_ends_the_run_and_allows_a_new_one() {
		$this->create_posts( 3 );
		$this->process->start();

		$this->assertTrue( $this->process->cancel() );
		$snapshot = $this->process->get_snapshot();
		$this->assertSame( 'idle', $snapshot['status'] );
		$this->assertSame( 'cancelled', $snapshot['last_status'] );
		$this->assertFalse( $this->process->pause() );

		$this->assertIsArray( $this->process->start() );
	}

	public function test_tick_waits_for_quota_instead_of_calling_the_provider() {
		$this->create_posts( 3 );
		update_option( 'aips_embeddings_daily_limit', 2 );
		$this->limiter = new AIPS_Embeddings_Rate_Limiter( AIPS_Config::get_instance() );
		$this->limiter->record_usage( 2 );

		$limiter = $this->limiter;
		$this->embeddings_service = $this->getMockBuilder( 'AIPS_Embeddings_Service' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'is_enabled', 'is_embeddings_supported', 'generate_embedding', 'get_rate_limiter' ) )
			->getMock();
		$this->embeddings_service->method( 'is_enabled' )->willReturn( true );
		$this->embeddings_service->method( 'is_embeddings_supported' )->willReturn( true );
		$this->embeddings_service->method( 'get_rate_limiter' )->willReturn( $limiter );
		$this->embeddings_service->expects( $this->never() )->method( 'generate_embedding' );

		$service = new AIPS_Internal_Links_Service( new AIPS_Embeddings_Repository(), new AIPS_Internal_Links_Repository(), $this->embeddings_service );
		$process = new AIPS_Internal_Links_Indexing_Process( $this->repo, $service, $this->embeddings_service, AIPS_Config::get_instance() );

		$process->start();
		$process->tick();

		$snapshot = $process->get_snapshot();
		$this->assertSame( 'waiting_quota', $snapshot['status'] );
		$this->assertTrue( $snapshot['is_active'], 'A quota wait is still an active, self-resuming run.' );
		$this->assertGreaterThan( time(), $snapshot['next_run_at'] );
		$this->assertSame( 0, $snapshot['processed'] );
	}

	public function test_tick_waits_out_a_cooldown() {
		$this->create_posts( 2 );
		$this->process->start();

		update_option( 'aips_indexer_paused_until', time() + 600, false );
		update_option( 'aips_indexer_pause_reason', 'Provider rate limit', false );

		$this->process->tick();

		$snapshot = $this->process->get_snapshot();
		$this->assertSame( 'cooldown', $snapshot['status'] );
		$this->assertSame( 0, $this->generated );
		$this->assertGreaterThan( time() + 590, $snapshot['next_run_at'] );

		$this->limiter->clear_cooldown();
	}

	public function test_ai_call_budget_pauses_the_run() {
		$this->create_posts( 5 );
		$this->process->start( array( 'ai_budget' => 2 ) );

		$this->process->tick();
		$this->assertSame( 2, $this->generated, 'The slice is capped to the remaining budget.' );

		wp_clear_scheduled_hook( AIPS_Managed_Background_Process::TICK_HOOK, array( AIPS_Internal_Links_Indexing_Process::KEY ) );
		$this->process->tick();

		$snapshot = $this->process->get_snapshot();
		$this->assertSame( 'paused', $snapshot['status'] );
		$this->assertSame( 2, $this->generated );
		$this->assertSame( 2, $snapshot['ai_calls_budget'] );
	}

	public function test_estimate_reports_days_from_the_daily_limit() {
		$this->create_posts( 120 );

		$estimate = $this->process->get_estimate();

		$this->assertGreaterThanOrEqual( 120, $estimate['items'] );
		$this->assertSame( $estimate['items'], $estimate['ai_calls'] );
		$this->assertSame( 50, $estimate['daily_rate'] );
		$this->assertSame( (int) ceil( $estimate['items'] / 50 ), $estimate['days'] );
		$this->assertNotEmpty( $estimate['message'] );
	}
}
