<?php
/**
 * Tests for the safety nets around queued ability work: restarting a process
 * that has items but no open run, kicking from the workflow list, and failing
 * runs that were left open with no activity.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ability_Workflow_Resilience extends WP_UnitTestCase {

	/** @var AIPS_Ability_Workflow_Repository */
	private $workflows;

	/** @var AIPS_Background_Process_Repository */
	private $runs;

	/** @var int[] */
	private $workflow_ids = array();

	/** @var int */
	private $admin_id;

	public function setUp(): void {
		parent::setUp();

		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_background_processes' );

		$this->workflows = AIPS_Ability_Workflow_Repository::instance();
		$this->runs      = new AIPS_Background_Process_Repository();
		$this->admin_id  = self::factory()->user->create( array( 'role' => 'administrator' ) );

		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 1 );
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_enrichment', 1 );
	}

	public function tearDown(): void {
		global $wpdb;

		foreach ( $this->workflow_ids as $workflow_id ) {
			$this->workflows->delete_workflow( $workflow_id );
		}

		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_background_processes' );
		wp_clear_scheduled_hook( AIPS_Managed_Background_Process::TICK_HOOK, array( AIPS_Ability_Workflows_Process::KEY ) );
		wp_clear_scheduled_hook( AIPS_Managed_Background_Process::TICK_HOOK, array( AIPS_Ability_Enrichment_Process::KEY ) );
		remove_all_filters( 'aips_ability_workflow_stale_after' );
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 0 );
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_enrichment', 0 );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	private function make_workflow() {
		$workflow_id = $this->workflows->create_workflow( array( 'name' => 'Resilience workflow', 'status' => 'active' ) );
		$this->workflow_ids[] = $workflow_id;
		$this->workflows->save_steps( $workflow_id, array( array( 'step_key' => 'a', 'ability_name' => 'vendor/a' ) ) );

		return $workflow_id;
	}

	private function queue_run( $workflow_id ) {
		wp_set_current_user( $this->admin_id );
		$run_id = $this->workflows->create_run( $workflow_id, $this->workflows->get_workflow( $workflow_id )->version );
		wp_set_current_user( 0 );

		return $run_id;
	}

	/**
	 * Pretend a run was last touched `$seconds_ago` seconds ago.
	 */
	private function age_run( $run_id, $seconds_ago ) {
		global $wpdb;

		$then = time() - $seconds_ago;
		$wpdb->update( $wpdb->prefix . 'aips_ability_workflow_runs', array( 'created_at' => $then, 'started_at' => 0 ), array( 'id' => $run_id ) );
	}

	private function workflows_process() {
		$abilities = new AIPS_Ability_Service();
		$executor  = new AIPS_Ability_Workflow_Executor(
			$this->workflows,
			new AIPS_Ability_Catalog_Service( $abilities ),
			$abilities,
			null,
			null,
			null,
			null,
			'__return_true'
		);

		return new AIPS_Ability_Workflows_Process( $this->runs, $this->workflows, $executor );
	}

	private function enrichment_process() {
		return new AIPS_Ability_Enrichment_Process( $this->runs, new AIPS_Ability_Enrichment_Queue_Repository() );
	}

	// ---------------------------------------------------------------------
	// Restarting a process that has items but no open run
	// ---------------------------------------------------------------------

	public function test_ensure_scheduled_starts_a_workflow_run_when_runs_are_queued_but_no_run_is_open() {
		$this->queue_run( $this->make_workflow() );
		$process = $this->workflows_process();

		$this->assertNull( $this->runs->get_open( AIPS_Ability_Workflows_Process::KEY ) );

		$process->ensure_scheduled();

		$open = $this->runs->get_open( AIPS_Ability_Workflows_Process::KEY );
		$this->assertNotNull( $open );
		$this->assertSame( 'running', $open->status );
		$this->assertNotFalse( wp_next_scheduled( AIPS_Managed_Background_Process::TICK_HOOK, array( AIPS_Ability_Workflows_Process::KEY ) ) );
	}

	public function test_ensure_scheduled_starts_enrichment_when_posts_are_queued_but_no_run_is_open() {
		( new AIPS_Ability_Enrichment_Queue_Repository() )->enqueue( self::factory()->post->create(), 1 );

		$this->enrichment_process()->ensure_scheduled();

		$this->assertNotNull( $this->runs->get_open( AIPS_Ability_Enrichment_Process::KEY ) );
	}

	public function test_ensure_scheduled_does_nothing_when_nothing_is_queued() {
		$this->workflows_process()->ensure_scheduled();
		$this->enrichment_process()->ensure_scheduled();

		$this->assertNull( $this->runs->get_open( AIPS_Ability_Workflows_Process::KEY ) );
		$this->assertNull( $this->runs->get_open( AIPS_Ability_Enrichment_Process::KEY ) );
	}

	public function test_ensure_scheduled_does_not_start_a_disabled_feature() {
		$this->queue_run( $this->make_workflow() );
		( new AIPS_Ability_Enrichment_Queue_Repository() )->enqueue( self::factory()->post->create(), 1 );
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 0 );
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_enrichment', 0 );

		$this->workflows_process()->ensure_scheduled();
		$this->enrichment_process()->ensure_scheduled();

		$this->assertNull( $this->runs->get_open( AIPS_Ability_Workflows_Process::KEY ) );
		$this->assertNull( $this->runs->get_open( AIPS_Ability_Enrichment_Process::KEY ) );
	}

	public function test_ensure_scheduled_leaves_a_paused_process_paused() {
		$this->queue_run( $this->make_workflow() );
		$process = $this->workflows_process();
		$process->start();
		$process->pause();
		wp_clear_scheduled_hook( AIPS_Managed_Background_Process::TICK_HOOK, array( AIPS_Ability_Workflows_Process::KEY ) );

		$process->ensure_scheduled();

		$this->assertSame( AIPS_Background_Process_Repository::STATUS_PAUSED, $this->runs->get_open( AIPS_Ability_Workflows_Process::KEY )->status );
		$this->assertFalse( wp_next_scheduled( AIPS_Managed_Background_Process::TICK_HOOK, array( AIPS_Ability_Workflows_Process::KEY ) ) );
	}

	public function test_ensure_scheduled_requeues_a_missing_tick_of_an_open_run() {
		$this->queue_run( $this->make_workflow() );
		$process = $this->workflows_process();
		$process->start();
		wp_clear_scheduled_hook( AIPS_Managed_Background_Process::TICK_HOOK, array( AIPS_Ability_Workflows_Process::KEY ) );

		$process->ensure_scheduled();

		$this->assertNotFalse( wp_next_scheduled( AIPS_Managed_Background_Process::TICK_HOOK, array( AIPS_Ability_Workflows_Process::KEY ) ) );
	}

	public function test_the_heartbeat_restarts_processes_with_waiting_items() {
		$this->queue_run( $this->make_workflow() );
		( new AIPS_Ability_Enrichment_Queue_Repository() )->enqueue( self::factory()->post->create(), 1 );

		$payload = ( new AIPS_Background_Process_Manager() )->get_heartbeat_payload();

		$this->assertNotNull( $this->runs->get_open( AIPS_Ability_Workflows_Process::KEY ) );
		$this->assertNotNull( $this->runs->get_open( AIPS_Ability_Enrichment_Process::KEY ) );
		$by_key = array_column( $payload['processes'], null, 'key' );
		$this->assertTrue( $by_key['ability_workflows']['is_active'] );
		$this->assertTrue( $by_key['ability_enrichment']['is_active'] );
	}

	// ---------------------------------------------------------------------
	// Kick from the workflow list
	// ---------------------------------------------------------------------

	public function test_opening_the_workflow_list_restarts_stalled_execution() {
		$this->queue_run( $this->make_workflow() );
		wp_set_current_user( $this->admin_id );
		$_POST = array( 'nonce' => wp_create_nonce( 'aips_ajax_nonce' ) );
		$_REQUEST['nonce'] = $_POST['nonce'];
		$controller = new AIPS_Ability_Workflows_Controller();

		ob_start();
		try {
			$controller->ajax_list_workflows();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected after wp_send_json_*.
		} catch ( WPAjaxDieStopException $e ) {
			// Expected in full WordPress mode.
		}
		$response = json_decode( strtok( trim( ob_get_clean() ), "\r\n" ), true );

		$this->assertTrue( $response['success'] );
		$this->assertNotNull( $this->runs->get_open( AIPS_Ability_Workflows_Process::KEY ) );

		$_POST    = array();
		$_REQUEST = array();
	}

	// ---------------------------------------------------------------------
	// Stale run queries
	// ---------------------------------------------------------------------

	public function test_runs_record_when_they_were_created() {
		$run_id = $this->queue_run( $this->make_workflow() );

		$run = $this->workflows->get_run( $run_id );

		$this->assertGreaterThan( time() - 5, $run->created_at );
		$this->assertLessThanOrEqual( time(), $run->created_at );
	}

	public function test_stale_run_ids_cover_old_open_runs_only() {
		$workflow_id = $this->make_workflow();
		$old_queued  = $this->queue_run( $workflow_id );
		$old_running = $this->queue_run( $workflow_id );
		$fresh       = $this->queue_run( $workflow_id );
		$old_done    = $this->queue_run( $workflow_id );
		$this->workflows->update_run_status( $old_running, 'running', array( 'started_at' => 0 ) );
		$this->workflows->update_run_status( $old_done, AIPS_Ability_Workflow_Repository::RUN_STATUS_COMPLETED, array( 'finished_at' => time() ) );
		foreach ( array( $old_queued, $old_running, $old_done ) as $id ) {
			$this->age_run( $id, 10 * HOUR_IN_SECONDS );
		}

		$stale = $this->workflows->get_stale_run_ids( time() - 6 * HOUR_IN_SECONDS );

		$this->assertContains( $old_queued, $stale );
		$this->assertContains( $old_running, $stale );
		$this->assertNotContains( $fresh, $stale );
		$this->assertNotContains( $old_done, $stale, 'Finished runs are never stale.' );
	}

	public function test_recent_step_activity_keeps_an_old_run_from_being_stale() {
		$workflow_id = $this->make_workflow();
		$run_id      = $this->queue_run( $workflow_id );
		$this->age_run( $run_id, 10 * HOUR_IN_SECONDS );
		$step        = $this->workflows->get_steps( $workflow_id )[0];
		$step_run_id = $this->workflows->create_step_run( $run_id, $workflow_id, $step, array() );

		$this->assertContains( $run_id, $this->workflows->get_stale_run_ids( time() - 6 * HOUR_IN_SECONDS ) );

		$this->workflows->update_step_run_status( $step_run_id, 'running', array( 'started_at' => time() - 60 ) );

		$this->assertNotContains( $run_id, $this->workflows->get_stale_run_ids( time() - 6 * HOUR_IN_SECONDS ) );
	}

	// ---------------------------------------------------------------------
	// Failing stale runs
	// ---------------------------------------------------------------------

	public function test_fail_stale_runs_fails_old_open_runs_and_closes_their_history() {
		$workflow_id = $this->make_workflow();
		$stale       = $this->queue_run( $workflow_id );
		$fresh       = $this->queue_run( $workflow_id );
		$this->age_run( $stale, 8 * HOUR_IN_SECONDS );

		$history = ( new AIPS_History_Service() )->create( 'ability_workflow_run', array( 'run_id' => $stale, 'creation_method' => 'ability_workflow_run' ) );
		$this->workflows->set_run_history_id( $stale, (int) $history->get_id() );

		$failed = $this->workflows_process_executor()->fail_stale_runs( 6 * HOUR_IN_SECONDS );

		$this->assertSame( 1, $failed );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED, $this->workflows->get_run( $stale )->status );
		$this->assertGreaterThan( 0, $this->workflows->get_run( $stale )->finished_at );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_QUEUED, $this->workflows->get_run( $fresh )->status );
		$this->assertSame( 'terminated', AIPS_History_Repository::instance()->get_by_id( $history->get_id() )->status );
	}

	public function test_fail_stale_runs_leaves_a_run_another_worker_holds() {
		$stale = $this->queue_run( $this->make_workflow() );
		$this->age_run( $stale, 8 * HOUR_IN_SECONDS );
		$this->assertTrue( $this->workflows->acquire_lock( 'aips_ability_workflow_lock_' . $stale, 'busy-worker', 60 ) );

		$failed = $this->workflows_process_executor()->fail_stale_runs( 6 * HOUR_IN_SECONDS );

		$this->assertSame( 0, $failed );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_QUEUED, $this->workflows->get_run( $stale )->status );

		$this->workflows->release_lock( 'aips_ability_workflow_lock_' . $stale, 'busy-worker' );
	}

	public function test_fail_stale_runs_never_uses_a_threshold_below_a_minute() {
		$run_id = $this->queue_run( $this->make_workflow() );
		$this->age_run( $run_id, 30 );

		$this->assertSame( 0, $this->workflows_process_executor()->fail_stale_runs( 1 ) );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_QUEUED, $this->workflows->get_run( $run_id )->status );
	}

	public function test_fail_stale_runs_releases_the_lock_it_takes() {
		$stale = $this->queue_run( $this->make_workflow() );
		$this->age_run( $stale, 8 * HOUR_IN_SECONDS );

		$this->workflows_process_executor()->fail_stale_runs( 6 * HOUR_IN_SECONDS );

		$this->assertTrue( $this->workflows->acquire_lock( 'aips_ability_workflow_lock_' . $stale, 'next', 60 ) );
		$this->workflows->release_lock( 'aips_ability_workflow_lock_' . $stale, 'next' );
	}

	private function workflows_process_executor() {
		$abilities = new AIPS_Ability_Service();

		return new AIPS_Ability_Workflow_Executor(
			$this->workflows,
			new AIPS_Ability_Catalog_Service( $abilities ),
			$abilities,
			null,
			null,
			null,
			null,
			'__return_true'
		);
	}

	// ---------------------------------------------------------------------
	// The daily sweep
	// ---------------------------------------------------------------------

	public function test_the_sweep_fails_stale_runs_and_reports_how_many() {
		$workflow_id = $this->make_workflow();
		$first       = $this->queue_run( $workflow_id );
		$second      = $this->queue_run( $workflow_id );
		$this->age_run( $first, 8 * HOUR_IN_SECONDS );
		$this->age_run( $second, 8 * HOUR_IN_SECONDS );

		$this->assertSame( 2, $this->workflows_process()->sweep_stale_runs() );
	}

	public function test_the_sweep_does_nothing_while_the_process_is_paused() {
		$run_id  = $this->queue_run( $this->make_workflow() );
		$process = $this->workflows_process();
		$process->start();
		$process->pause();
		$this->age_run( $run_id, 8 * HOUR_IN_SECONDS );

		$this->assertSame( 0, $process->sweep_stale_runs() );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_QUEUED, $this->workflows->get_run( $run_id )->status );
	}

	public function test_the_sweep_does_nothing_while_the_feature_is_disabled() {
		$run_id = $this->queue_run( $this->make_workflow() );
		$this->age_run( $run_id, 8 * HOUR_IN_SECONDS );
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 0 );

		$this->assertSame( 0, $this->workflows_process()->sweep_stale_runs() );
	}

	public function test_the_stale_threshold_is_filterable_and_zero_disables_the_sweep() {
		$run_id = $this->queue_run( $this->make_workflow() );
		$this->age_run( $run_id, 2 * HOUR_IN_SECONDS );
		$process = $this->workflows_process();

		$this->assertSame( 0, $process->sweep_stale_runs(), 'Two hours is inside the six-hour default.' );

		add_filter(
			'aips_ability_workflow_stale_after',
			function () {
				return HOUR_IN_SECONDS;
			}
		);
		$this->assertSame( 1, $process->sweep_stale_runs() );

		$other = $this->queue_run( $this->make_workflow() );
		$this->age_run( $other, 2 * HOUR_IN_SECONDS );
		remove_all_filters( 'aips_ability_workflow_stale_after' );
		add_filter( 'aips_ability_workflow_stale_after', '__return_zero' );
		$this->assertSame( 0, $process->sweep_stale_runs() );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_QUEUED, $this->workflows->get_run( $other )->status );
	}
}
