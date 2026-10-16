<?php
/**
 * Tests for AIPS_Ability_Workflows_Process, the managed background process that
 * advances Ability Workflow runs through the shared tick loop.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ability_Workflows_Process extends WP_UnitTestCase {

	/** @var AIPS_Ability_Workflow_Repository */
	private $workflows;

	/** @var AIPS_Background_Process_Repository */
	private $runs;

	/** @var int[] */
	private $workflow_ids = array();

	/** @var array[] Ability invocations in order. */
	private $calls = array();

	/** @var int */
	private $admin_id;

	public function setUp(): void {
		parent::setUp();

		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_background_processes' );

		$this->calls     = array();
		$this->workflows = AIPS_Ability_Workflow_Repository::instance();
		$this->runs      = new AIPS_Background_Process_Repository();
		$this->admin_id  = self::factory()->user->create( array( 'role' => 'administrator' ) );

		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 1 );
	}

	public function tearDown(): void {
		global $wpdb;

		foreach ( $this->workflow_ids as $workflow_id ) {
			$this->workflows->delete_workflow( $workflow_id );
		}

		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_background_processes' );
		wp_clear_scheduled_hook( AIPS_Managed_Background_Process::TICK_HOOK, array( AIPS_Ability_Workflows_Process::KEY ) );
		remove_all_filters( 'aips_ability_provider' );
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 0 );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	// ---------------------------------------------------------------------
	// Fixtures
	// ---------------------------------------------------------------------

	private function register_abilities( array $abilities ) {
		add_filter(
			'aips_ability_provider',
			function () use ( $abilities ) {
				return array(
					'name'   => 'process-fixture',
					'list'   => function () use ( $abilities ) {
						$list = array();

						foreach ( array_keys( $abilities ) as $slug ) {
							$list[ $slug ] = array( 'slug' => $slug, 'annotations' => array( 'destructive' => false ) );
						}

						return $list;
					},
					'invoke' => function ( $slug, $payload ) use ( $abilities ) {
						$this->calls[] = $slug;

						return call_user_func( $abilities[ $slug ], $payload );
					},
				);
			}
		);
	}

	private function ok() {
		return function () {
			return array( 'ok' => true );
		};
	}

	private function make_workflow( array $steps ) {
		$workflow_id = $this->workflows->create_workflow( array( 'name' => 'Process test workflow', 'status' => 'active' ) );
		$this->workflow_ids[] = $workflow_id;
		$this->assertTrue( $this->workflows->save_steps( $workflow_id, $steps ) );

		return $workflow_id;
	}

	private function queue_run( $workflow_id ) {
		wp_set_current_user( $this->admin_id );
		$run_id = $this->workflows->create_run( $workflow_id, $this->workflows->get_workflow( $workflow_id )->version );
		wp_set_current_user( 0 );

		return $run_id;
	}

	private function step( $key, $ability, array $extra = array() ) {
		return array_merge( array( 'step_key' => $key, 'ability_name' => $ability ), $extra );
	}

	private function process() {
		$abilities = new AIPS_Ability_Service();
		$executor  = new AIPS_Ability_Workflow_Executor(
			$this->workflows,
			new AIPS_Ability_Catalog_Service( $abilities ),
			$abilities,
			null,
			null,
			null,
			null,
			function () {
				return true;
			}
		);

		return new AIPS_Ability_Workflows_Process( $this->runs, $this->workflows, $executor );
	}

	private function open_run() {
		return $this->runs->get_open( AIPS_Ability_Workflows_Process::KEY );
	}

	// ---------------------------------------------------------------------
	// Registration and metadata
	// ---------------------------------------------------------------------

	public function test_the_manager_registers_the_process() {
		$process = ( new AIPS_Background_Process_Manager() )->get( AIPS_Ability_Workflows_Process::KEY );

		$this->assertInstanceOf( 'AIPS_Ability_Workflows_Process', $process );
		$this->assertSame( 'ability_workflows', $process->get_key() );
		$this->assertTrue( $process->uses_ai() );
		$this->assertNotSame( '', $process->get_label() );
	}

	public function test_the_process_appears_in_the_manager_snapshots() {
		$keys = wp_list_pluck( ( new AIPS_Background_Process_Manager() )->get_snapshots(), 'key' );

		$this->assertContains( 'ability_workflows', $keys );
		$this->assertContains( 'ability_enrichment', $keys );
	}

	// ---------------------------------------------------------------------
	// Start / kick
	// ---------------------------------------------------------------------

	public function test_start_is_refused_while_the_feature_is_disabled() {
		$this->register_abilities( array( 'vendor/a' => $this->ok() ) );
		$this->queue_run( $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) ) );
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 0 );

		$result = $this->process()->start();

		$this->assertWPError( $result );
		$this->assertSame( 'ability_workflows_disabled', $result->get_error_code() );
	}

	public function test_start_reports_nothing_to_do_without_open_runs() {
		$result = $this->process()->start();

		$this->assertWPError( $result );
		$this->assertSame( 'aips_bg_nothing_to_do', $result->get_error_code() );
	}

	public function test_kick_starts_a_run_when_work_is_queued_and_is_safe_to_repeat() {
		$this->register_abilities( array( 'vendor/a' => $this->ok() ) );
		$this->queue_run( $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) ) );
		$process = $this->process();

		$this->assertTrue( $process->kick() );
		$first = $this->open_run();
		$this->assertNotNull( $first );
		$this->assertSame( 1, $first->total );
		$this->assertNotFalse( wp_next_scheduled( AIPS_Managed_Background_Process::TICK_HOOK, array( AIPS_Ability_Workflows_Process::KEY ) ) );

		$this->assertTrue( $process->kick(), 'Kicking an open process is not an error.' );
		$this->assertSame( $first->id, $this->open_run()->id, 'No second run may be opened.' );
	}

	public function test_kick_with_nothing_queued_is_not_an_error() {
		$this->assertTrue( $this->process()->kick() );
		$this->assertNull( $this->open_run() );
	}

	public function test_kick_reports_a_disabled_feature() {
		$this->register_abilities( array( 'vendor/a' => $this->ok() ) );
		$this->queue_run( $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) ) );
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 0 );

		$this->assertWPError( $this->process()->kick() );
	}

	public function test_a_paused_process_is_left_paused_by_kick() {
		$this->register_abilities( array( 'vendor/a' => $this->ok() ) );
		$this->queue_run( $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) ) );
		$process = $this->process();
		$process->start();
		$this->assertTrue( $process->pause() );

		$this->assertTrue( $process->kick() );

		$this->assertSame( AIPS_Background_Process_Repository::STATUS_PAUSED, $this->open_run()->status );
		$this->assertFalse( wp_next_scheduled( AIPS_Managed_Background_Process::TICK_HOOK, array( AIPS_Ability_Workflows_Process::KEY ) ) );
	}

	// ---------------------------------------------------------------------
	// Slices
	// ---------------------------------------------------------------------

	public function test_a_tick_runs_queued_workflow_runs_to_completion_and_finishes_the_process() {
		$this->register_abilities( array( 'vendor/a' => $this->ok(), 'vendor/b' => $this->ok() ) );
		$run_one = $this->queue_run( $this->make_workflow( array( $this->step( 'a', 'vendor/a' ), $this->step( 'b', 'vendor/b' ) ) ) );
		$run_two = $this->queue_run( $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) ) );
		$process = $this->process();
		$process->start();

		$process->tick();

		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_COMPLETED, $this->workflows->get_run( $run_one )->status );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_COMPLETED, $this->workflows->get_run( $run_two )->status );
		$this->assertSame( array( 'vendor/a', 'vendor/b', 'vendor/a' ), $this->calls );

		$snapshot = $process->get_snapshot();
		$this->assertSame( 'idle', $snapshot['status'], 'The process has no open run once everything finished.' );
		$this->assertSame( 'completed', $snapshot['last_status'] );
		$this->assertSame( 2, $snapshot['processed'] );
		$this->assertSame( 0, $snapshot['failed'] );
	}

	public function test_failed_runs_are_counted_as_failed_not_processed() {
		$this->register_abilities(
			array(
				'vendor/ok'   => $this->ok(),
				'vendor/boom' => function () {
					return new WP_Error( 'boom', 'Exploded.' );
				},
			)
		);
		$this->queue_run( $this->make_workflow( array( $this->step( 'a', 'vendor/ok' ) ) ) );
		$this->queue_run( $this->make_workflow( array( $this->step( 'a', 'vendor/boom' ) ) ) );
		$process = $this->process();
		$process->start();

		$process->tick();

		$snapshot = $process->get_snapshot();
		$this->assertSame( 'completed', $snapshot['last_status'] );
		$this->assertSame( 2, $snapshot['processed'], 'The tick loop counts processed plus failed items.' );
		$this->assertSame( 1, $snapshot['failed'] );
	}

	public function test_a_slice_executes_at_most_the_batch_size_of_runs_then_carries_on() {
		$this->register_abilities( array( 'vendor/a' => $this->ok() ) );
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) );
		$run_ids     = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$run_ids[] = $this->queue_run( $workflow_id );
		}
		$process = $this->process();
		$process->start();

		$process->tick();

		$completed = array_filter(
			$run_ids,
			function ( $id ) {
				return AIPS_Ability_Workflow_Repository::RUN_STATUS_COMPLETED === $this->workflows->get_run( $id )->status;
			}
		);
		$this->assertCount( 3, $completed, 'One slice advances at most three runs.' );
		$this->assertSame( 'running', $this->open_run()->status );
		$this->assertSame( 2, $this->workflows->count_open_runs() );

		$process->tick();

		$this->assertSame( 0, $this->workflows->count_open_runs() );
		$this->assertNull( $this->open_run() );
	}

	public function test_a_run_waiting_out_a_backoff_keeps_the_process_open_and_sets_the_next_delay() {
		$this->register_abilities(
			array(
				'vendor/flaky' => function () {
					return new WP_Error( 'provider_timeout', 'Timed out.' );
				},
			)
		);
		$run_id = $this->queue_run(
			$this->make_workflow(
				array( $this->step( 'a', 'vendor/flaky', array( 'retry_policy' => array( 'attempts' => 3, 'backoff_seconds' => 40 ) ) ) )
			)
		);
		$process = $this->process();
		$process->start();

		// Slice one executes the step, which fails and is parked for its backoff.
		$process->tick();

		$this->assertSame( 1, $this->workflows->count_open_runs() );
		$this->assertSame( 'running', $this->open_run()->status );
		$this->assertCount( 1, $this->calls );

		// Slice two only finds a backing-off run: it does no work and sleeps until the retry.
		$process->tick();

		$this->assertCount( 1, $this->calls, 'A backing-off run is not retried early.' );
		$this->assertSame( 1, $this->workflows->count_open_runs() );
		$delay_method = new ReflectionMethod( $process, 'get_delay' );
		$delay_method->setAccessible( true );
		$this->assertGreaterThan( AIPS_Ability_Workflows_Process::DEFAULT_DELAY, $delay_method->invoke( $process ) );
		$this->assertLessThanOrEqual( 40, $delay_method->invoke( $process ) );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_RUNNING, $this->workflows->get_run( $run_id )->status );
	}

	public function test_ability_calls_are_metered_for_the_ai_budget() {
		$this->register_abilities( array( 'vendor/a' => $this->ok(), 'vendor/b' => $this->ok() ) );
		$this->queue_run( $this->make_workflow( array( $this->step( 'a', 'vendor/a' ), $this->step( 'b', 'vendor/b' ) ) ) );
		$process = $this->process();
		$process->start();

		$process->tick();

		$this->assertSame( 2, $process->get_snapshot()['ai_calls_used'] );
	}

	public function test_disabling_the_feature_fails_the_process_and_leaves_runs_queued() {
		$this->register_abilities( array( 'vendor/a' => $this->ok() ) );
		$run_id  = $this->queue_run( $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) ) );
		$process = $this->process();
		$process->start();
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 0 );

		$process->tick();

		$this->assertCount( 0, $this->calls );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_QUEUED, $this->workflows->get_run( $run_id )->status );
		$this->assertSame( 'failed', $process->get_snapshot()['last_status'] );
	}

	public function test_stopping_the_process_leaves_queued_runs_queued() {
		$this->register_abilities( array( 'vendor/a' => $this->ok() ) );
		$run_id  = $this->queue_run( $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) ) );
		$process = $this->process();
		$process->start();

		$this->assertTrue( $process->cancel() );
		$process->tick();

		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_QUEUED, $this->workflows->get_run( $run_id )->status );
		$this->assertCount( 0, $this->calls );

		// Starting again resumes the work.
		$process->start();
		$process->tick();
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_COMPLETED, $this->workflows->get_run( $run_id )->status );
	}

	public function test_a_cancelled_run_is_skipped_by_the_process() {
		$this->register_abilities( array( 'vendor/a' => $this->ok() ) );
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) );
		$cancelled   = $this->queue_run( $workflow_id );
		$kept        = $this->queue_run( $workflow_id );
		$this->workflows->update_run_status( $cancelled, AIPS_Ability_Workflow_Repository::RUN_STATUS_CANCELLED, array( 'finished_at' => time() ) );
		$process = $this->process();
		$process->start();

		$process->tick();

		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_CANCELLED, $this->workflows->get_run( $cancelled )->status );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_COMPLETED, $this->workflows->get_run( $kept )->status );
		$this->assertSame( array( 'vendor/a' ), $this->calls );
	}

	public function test_the_estimate_describes_the_open_runs() {
		$this->register_abilities( array( 'vendor/a' => $this->ok() ) );
		$process = $this->process();

		$this->assertSame( 0, $process->get_estimate()['items'] );

		$this->queue_run( $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) ) );
		$estimate = $process->get_estimate();

		$this->assertSame( 1, $estimate['items'] );
		$this->assertNotSame( '', $estimate['message'] );
	}

	// ---------------------------------------------------------------------
	// Repository queries behind the process
	// ---------------------------------------------------------------------

	public function test_open_run_queries_only_return_queued_and_running_runs_oldest_first() {
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) );
		$queued      = $this->queue_run( $workflow_id );
		$running     = $this->queue_run( $workflow_id );
		$done        = $this->queue_run( $workflow_id );
		$cancelled   = $this->queue_run( $workflow_id );
		$this->workflows->update_run_status( $running, AIPS_Ability_Workflow_Repository::RUN_STATUS_RUNNING, array( 'started_at' => time() ) );
		$this->workflows->update_run_status( $done, AIPS_Ability_Workflow_Repository::RUN_STATUS_COMPLETED, array( 'finished_at' => time() ) );
		$this->workflows->update_run_status( $cancelled, AIPS_Ability_Workflow_Repository::RUN_STATUS_CANCELLED, array( 'finished_at' => time() ) );

		$this->assertSame( 2, $this->workflows->count_open_runs() );
		$this->assertSame( array( $queued, $running ), $this->workflows->get_open_run_ids( 10 ) );
		$this->assertSame( array( $queued ), $this->workflows->get_open_run_ids( 1 ) );
	}
}
