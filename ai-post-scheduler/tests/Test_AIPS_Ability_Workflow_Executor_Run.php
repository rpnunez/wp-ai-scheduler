<?php
/**
 * End-to-end tests for AIPS_Ability_Workflow_Executor::process_run().
 *
 * Runs real workflows (real repository, real History) against a fake ability
 * provider and a stub that records process wake-ups, covering the
 * run loop: ordering and variable passing, conditions, dependencies, the
 * destructive gate, retries and backoff, the time budget, cancellation,
 * definition changes, locking and History handling.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ability_Workflow_Executor_Run extends WP_UnitTestCase {

	/** @var AIPS_Ability_Workflow_Repository */
	private $repository;

	/** @var int Times the executor woke the background process. */
	private $kicks = 0;

	/** @var true|WP_Error What the wake-up stub returns. */
	private $kick_result = true;

	/** @var array[] Ability invocations in order: slug, payload, user_id. */
	private $calls = array();

	/** @var int[] */
	private $workflow_ids = array();

	/** @var int */
	private $admin_id;

	public function setUp(): void {
		parent::setUp();

		$this->calls      = array();
		$this->repository = AIPS_Ability_Workflow_Repository::instance();
		$this->admin_id   = self::factory()->user->create( array( 'role' => 'administrator' ) );

		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 1 );

		$this->kicks       = 0;
		$this->kick_result = true;
	}

	public function tearDown(): void {
		foreach ( $this->workflow_ids as $workflow_id ) {
			$this->repository->delete_workflow( $workflow_id );
		}

		remove_all_filters( 'aips_ability_provider' );
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 0 );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	// ---------------------------------------------------------------------
	// Fixtures
	// ---------------------------------------------------------------------

	/**
	 * Register fake abilities.
	 *
	 * @param array $abilities Slug => array( 'run' => callable( $payload ), 'annotations' => array|null ).
	 */
	private function register_abilities( array $abilities ) {
		add_filter(
			'aips_ability_provider',
			function () use ( $abilities ) {
				return array(
					'name'   => 'executor-fixture',
					'list'   => function () use ( $abilities ) {
						$list = array();

						foreach ( $abilities as $slug => $definition ) {
							$list[ $slug ] = array( 'slug' => $slug );

							if ( isset( $definition['annotations'] ) ) {
								$list[ $slug ]['annotations'] = $definition['annotations'];
							}
						}

						return $list;
					},
					'invoke' => function ( $slug, $payload ) use ( $abilities ) {
						$this->calls[] = array(
							'slug'    => $slug,
							'payload' => $payload,
							'user_id' => get_current_user_id(),
						);

						return call_user_func( $abilities[ $slug ]['run'], $payload );
					},
				);
			}
		);
	}

	/**
	 * A fake ability that returns a fixed result and declares itself safe.
	 */
	private function safe( $result = array( 'ok' => true ) ) {
		return array(
			'run'         => function () use ( $result ) {
				return $result;
			},
			'annotations' => array( 'destructive' => false ),
		);
	}

	private function make_workflow( array $steps, array $settings = array(), $status = 'active' ) {
		$workflow_id = $this->repository->create_workflow(
			array(
				'name'     => 'Run test workflow',
				'status'   => $status,
				'settings' => $settings,
			)
		);
		$this->workflow_ids[] = $workflow_id;

		$saved = $this->repository->save_steps( $workflow_id, $steps );
		$this->assertTrue( $saved, 'Fixture steps must save.' );

		return $workflow_id;
	}

	/**
	 * Create a queued run as the admin, then drop to user 0 like cron.
	 */
	private function make_run( $workflow_id ) {
		wp_set_current_user( $this->admin_id );
		$workflow = $this->repository->get_workflow( $workflow_id );
		$run_id   = $this->repository->create_run( $workflow_id, $workflow->version, array( 'type' => 'manual', 'topic' => 'Bikes' ), 'corr-test' );
		wp_set_current_user( 0 );

		return $run_id;
	}

	private function executor() {
		$abilities = new AIPS_Ability_Service();

		return new AIPS_Ability_Workflow_Executor(
			$this->repository,
			new AIPS_Ability_Catalog_Service( $abilities ),
			$abilities,
			null,
			null,
			null,
			null,
			function () {
				$this->kicks++;

				return $this->kick_result;
			}
		);
	}

	private function step( $key, $ability, array $extra = array() ) {
		return array_merge(
			array(
				'step_key'     => $key,
				'ability_name' => $ability,
			),
			$extra
		);
	}

	private function step_runs_by_key( $run_id ) {
		$by_key = array();

		foreach ( $this->repository->get_step_runs( $run_id ) as $step_run ) {
			$by_key[ $step_run->step_key ] = $step_run;
		}

		return $by_key;
	}

	private function called_slugs() {
		return array_column( $this->calls, 'slug' );
	}

	private function history_status( $run_id ) {
		$run = $this->repository->get_run( $run_id );
		$row = AIPS_History_Repository::instance()->get_by_id( $run->history_id );

		return $row ? $row->status : null;
	}

	// ---------------------------------------------------------------------
	// Ordering, variables, History
	// ---------------------------------------------------------------------

	public function test_steps_run_in_order_and_outputs_feed_later_steps() {
		$this->register_abilities(
			array(
				'vendor/outline' => array(
					'run'         => function () {
						return array( 'title' => 'Hello' );
					},
					'annotations' => array( 'destructive' => false ),
				),
				'vendor/write'   => $this->safe( array( 'done' => true ) ),
			)
		);
		$workflow_id = $this->make_workflow(
			array(
				$this->step( 'outline', 'vendor/outline', array( 'input_map' => array( 'topic' => '{{trigger.topic}}' ) ) ),
				// No output_alias: the step is addressed by its key.
				$this->step( 'write', 'vendor/write', array( 'depends_on' => array( 'outline' ), 'input_map' => array( 'text' => '{{steps.outline.output.title}} world' ) ) ),
			)
		);
		$run_id = $this->make_run( $workflow_id );

		$this->executor()->process_run( $run_id, 'corr-test' );

		$this->assertSame( array( 'vendor/outline', 'vendor/write' ), $this->called_slugs() );
		$this->assertSame( array( 'topic' => 'Bikes' ), $this->calls[0]['payload'] );
		$this->assertSame( array( 'text' => 'Hello world' ), $this->calls[1]['payload'] );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_COMPLETED, $this->repository->get_run( $run_id )->status );

		$step_runs = $this->step_runs_by_key( $run_id );
		$this->assertSame( 'completed', $step_runs['outline']->status );
		$this->assertSame( array( 'title' => 'Hello' ), $step_runs['outline']->output_snapshot );
		$this->assertSame( 'completed', $step_runs['write']->status );
	}

	public function test_abilities_run_as_the_dispatching_user_and_cron_user_is_restored() {
		$this->register_abilities( array( 'vendor/a' => $this->safe() ) );
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) );
		$run_id      = $this->make_run( $workflow_id );

		$this->executor()->process_run( $run_id );

		$this->assertSame( $this->admin_id, $this->calls[0]['user_id'] );
		$this->assertSame( 0, get_current_user_id() );
	}

	public function test_successful_run_closes_its_history_row_once() {
		$this->register_abilities( array( 'vendor/a' => $this->safe() ) );
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) );
		$run_id      = $this->make_run( $workflow_id );

		$this->executor()->process_run( $run_id );

		$run = $this->repository->get_run( $run_id );
		$this->assertNotNull( $run->history_id, 'The run must remember its History row.' );
		$this->assertSame( 'completed', $this->history_status( $run_id ) );
	}

	public function test_failed_run_closes_its_history_row_as_failed() {
		$this->register_abilities(
			array(
				'vendor/a' => array(
					'run'         => function () {
						return new WP_Error( 'boom', 'Exploded.' );
					},
					'annotations' => array( 'destructive' => false ),
				),
			)
		);
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) );
		$run_id      = $this->make_run( $workflow_id );

		$this->executor()->process_run( $run_id );

		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED, $this->repository->get_run( $run_id )->status );
		$this->assertSame( 'failed', $this->history_status( $run_id ) );
		$step_run = $this->step_runs_by_key( $run_id )['a'];
		$this->assertSame( 'failed', $step_run->status );
		$this->assertSame( 'boom', $step_run->error['code'] );
	}

	public function test_terminal_run_is_ignored_and_opens_no_history() {
		$this->register_abilities( array( 'vendor/a' => $this->safe() ) );
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) );
		$run_id      = $this->make_run( $workflow_id );
		$this->repository->update_run_status( $run_id, AIPS_Ability_Workflow_Repository::RUN_STATUS_COMPLETED, array( 'finished_at' => time() ) );

		$result = $this->executor()->process_run( $run_id );

		$this->assertCount( 0, $this->calls );
		$this->assertNull( $this->repository->get_run( $run_id )->history_id );
		$this->assertSame( AIPS_Ability_Workflow_Executor::RESULT_FINISHED, $result['state'] );
		$this->assertFalse( $result['worked'] );
	}

	public function test_an_unknown_run_is_reported_finished() {
		$result = $this->executor()->process_run( 987654321 );

		$this->assertSame( AIPS_Ability_Workflow_Executor::RESULT_FINISHED, $result['state'] );
		$this->assertFalse( $result['worked'] );
	}

	// ---------------------------------------------------------------------
	// Conditions and dependencies
	// ---------------------------------------------------------------------

	public function test_false_condition_skips_the_step_and_its_dependents() {
		$this->register_abilities(
			array(
				'vendor/a' => $this->safe( array( 'count' => 1 ) ),
				'vendor/b' => $this->safe(),
				'vendor/c' => $this->safe(),
				'vendor/d' => $this->safe(),
			)
		);
		$workflow_id = $this->make_workflow(
			array(
				$this->step( 'a', 'vendor/a' ),
				$this->step(
					'b',
					'vendor/b',
					array(
						'depends_on'     => array( 'a' ),
						'condition_tree' => array(
							'operator' => 'AND',
							'rules'    => array( array( 'left' => '{{steps.a.output.count}}', 'operator' => 'greater_than', 'right' => 5 ) ),
						),
					)
				),
				$this->step( 'c', 'vendor/c', array( 'depends_on' => array( 'b' ) ) ),
				$this->step( 'd', 'vendor/d' ),
			)
		);
		$run_id = $this->make_run( $workflow_id );

		$this->executor()->process_run( $run_id );

		$this->assertSame( array( 'vendor/a', 'vendor/d' ), $this->called_slugs() );
		$step_runs = $this->step_runs_by_key( $run_id );
		$this->assertSame( 'skipped', $step_runs['b']->status );
		$this->assertSame( 'skipped', $step_runs['c']->status );
		$this->assertSame( 'completed', $step_runs['d']->status );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_COMPLETED, $this->repository->get_run( $run_id )->status );
	}

	public function test_true_condition_runs_the_step() {
		$this->register_abilities(
			array(
				'vendor/a' => $this->safe( array( 'count' => 9 ) ),
				'vendor/b' => $this->safe(),
			)
		);
		$workflow_id = $this->make_workflow(
			array(
				$this->step( 'a', 'vendor/a' ),
				$this->step(
					'b',
					'vendor/b',
					array(
						'condition_tree' => array(
							'rules' => array( array( 'left' => '{{steps.a.output.count}}', 'operator' => 'greater_than', 'right' => 5 ) ),
						),
					)
				),
			)
		);
		$run_id = $this->make_run( $workflow_id );

		$this->executor()->process_run( $run_id );

		$this->assertSame( array( 'vendor/a', 'vendor/b' ), $this->called_slugs() );
	}

	public function test_failure_with_continue_strategy_skips_dependents_but_not_independent_steps() {
		$this->register_abilities(
			array(
				'vendor/a' => array(
					'run'         => function () {
						return new WP_Error( 'boom', 'Exploded.' );
					},
					'annotations' => array( 'destructive' => false ),
				),
				'vendor/b' => $this->safe(),
				'vendor/c' => $this->safe(),
			)
		);
		$workflow_id = $this->make_workflow(
			array(
				$this->step( 'a', 'vendor/a', array( 'on_failure' => array( 'strategy' => 'continue' ) ) ),
				$this->step( 'b', 'vendor/b', array( 'depends_on' => array( 'a' ) ) ),
				$this->step( 'c', 'vendor/c' ),
			)
		);
		$run_id = $this->make_run( $workflow_id );

		$this->executor()->process_run( $run_id );

		$this->assertSame( array( 'vendor/a', 'vendor/c' ), $this->called_slugs() );
		$step_runs = $this->step_runs_by_key( $run_id );
		$this->assertSame( 'failed', $step_runs['a']->status );
		$this->assertSame( 'skipped', $step_runs['b']->status );
		$this->assertSame( 'completed', $step_runs['c']->status );
	}

	public function test_stop_strategy_ends_the_run_and_leaves_later_steps_untouched() {
		$this->register_abilities(
			array(
				'vendor/a' => array(
					'run'         => function () {
						return new WP_Error( 'boom', 'Exploded.' );
					},
					'annotations' => array( 'destructive' => false ),
				),
				'vendor/b' => $this->safe(),
			)
		);
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/a' ), $this->step( 'b', 'vendor/b' ) ) );
		$run_id      = $this->make_run( $workflow_id );

		$this->executor()->process_run( $run_id );

		$this->assertSame( array( 'vendor/a' ), $this->called_slugs() );
		$this->assertArrayNotHasKey( 'b', $this->step_runs_by_key( $run_id ) );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED, $this->repository->get_run( $run_id )->status );
	}

	public function test_success_stop_strategy_completes_the_run_early() {
		$this->register_abilities( array( 'vendor/a' => $this->safe(), 'vendor/b' => $this->safe() ) );
		$workflow_id = $this->make_workflow(
			array(
				$this->step( 'a', 'vendor/a', array( 'on_success' => array( 'strategy' => 'stop' ) ) ),
				$this->step( 'b', 'vendor/b' ),
			)
		);
		$run_id = $this->make_run( $workflow_id );

		$this->executor()->process_run( $run_id );

		$this->assertSame( array( 'vendor/a' ), $this->called_slugs() );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_COMPLETED, $this->repository->get_run( $run_id )->status );
	}

	// ---------------------------------------------------------------------
	// Destructive gate
	// ---------------------------------------------------------------------

	public function test_ability_that_declares_nothing_is_blocked_unless_destructive_abilities_are_allowed() {
		$this->register_abilities(
			array(
				'vendor/unannotated' => array(
					'run' => function () {
						return array( 'ok' => true );
					},
				),
			)
		);
		$steps       = array( $this->step( 'a', 'vendor/unannotated' ) );
		$workflow_id = $this->make_workflow( $steps );
		$run_id      = $this->make_run( $workflow_id );

		$this->executor()->process_run( $run_id );

		$this->assertCount( 0, $this->calls, 'The ability must not run.' );
		$step_run = $this->step_runs_by_key( $run_id )['a'];
		$this->assertSame( 'failed', $step_run->status );
		$this->assertSame( 'ability_workflow_destructive_forbidden', $step_run->error['code'] );
	}

	public function test_unannotated_ability_runs_when_destructive_abilities_are_allowed() {
		$this->register_abilities(
			array(
				'vendor/unannotated' => array(
					'run' => function () {
						return array( 'ok' => true );
					},
				),
			)
		);
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/unannotated' ) ), array( 'allow_destructive_abilities' => true ) );
		$run_id      = $this->make_run( $workflow_id );

		$this->executor()->process_run( $run_id );

		$this->assertSame( array( 'vendor/unannotated' ), $this->called_slugs() );
	}

	public function test_unannotated_ability_runs_when_the_workflow_approves_it() {
		$this->register_abilities(
			array(
				'vendor/unannotated' => array(
					'run' => function () {
						return array( 'ok' => true );
					},
				),
			)
		);
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/unannotated' ) ), array( 'approved_abilities' => array( 'vendor/unannotated' ) ) );
		$run_id      = $this->make_run( $workflow_id );

		$this->executor()->process_run( $run_id );

		$this->assertSame( array( 'vendor/unannotated' ), $this->called_slugs() );
		$this->assertSame( 'completed', $this->step_runs_by_key( $run_id )['a']->status );
	}

	public function test_approving_one_ability_does_not_approve_another() {
		$this->register_abilities(
			array(
				'vendor/approved' => array( 'run' => function () {
					return array( 'ok' => true );
				} ),
				'vendor/other'    => array( 'run' => function () {
					return array( 'ok' => true );
				} ),
			)
		);
		$workflow_id = $this->make_workflow(
			array( $this->step( 'a', 'vendor/approved' ), $this->step( 'b', 'vendor/other', array( 'on_failure' => array( 'strategy' => 'continue' ) ) ) ),
			array( 'approved_abilities' => array( 'vendor/approved' ) )
		);
		$run_id = $this->make_run( $workflow_id );

		$this->executor()->process_run( $run_id );

		$this->assertSame( array( 'vendor/approved' ), $this->called_slugs() );
		$this->assertSame( 'ability_workflow_destructive_forbidden', $this->step_runs_by_key( $run_id )['b']->error['code'] );
	}

	public function test_approval_never_covers_an_ability_declared_destructive() {
		$this->register_abilities(
			array(
				'vendor/delete' => array(
					'run'         => function () {
						return array( 'deleted' => 1 );
					},
					'annotations' => array( 'destructive' => true ),
				),
			)
		);
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/delete' ) ), array( 'approved_abilities' => array( 'vendor/delete' ) ) );
		$run_id      = $this->make_run( $workflow_id );

		$this->executor()->process_run( $run_id );

		$this->assertCount( 0, $this->calls );
		$this->assertSame( 'ability_workflow_destructive_forbidden', $this->step_runs_by_key( $run_id )['a']->error['code'] );
	}

	public function test_approval_gate_matrix() {
		$executor    = $this->executor();
		$unannotated = array( 'name' => 'v/u', 'requires_approval' => true, 'is_destructive' => false );
		$destructive = array( 'name' => 'v/d', 'requires_approval' => true, 'is_destructive' => true );
		$safe        = array( 'name' => 'v/s', 'requires_approval' => false, 'is_destructive' => false );

		$this->assertNull( $executor->get_approval_error( $safe, false ) );
		$this->assertNotNull( $executor->get_approval_error( $unannotated, false ) );
		$this->assertNull( $executor->get_approval_error( $unannotated, false, array( 'v/u' ) ) );
		$this->assertNotNull( $executor->get_approval_error( $unannotated, false, array( 'v/other' ) ) );
		$this->assertNotNull( $executor->get_approval_error( $destructive, false, array( 'v/d' ) ) );
		$this->assertNull( $executor->get_approval_error( $destructive, true ) );
		$this->assertNull( $executor->get_approval_error( $unannotated, true ) );
	}

	public function test_explicitly_destructive_ability_is_blocked() {
		$this->register_abilities(
			array(
				'vendor/delete' => array(
					'run'         => function () {
						return array( 'deleted' => 1 );
					},
					'annotations' => array( 'destructive' => true ),
				),
			)
		);
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/delete' ) ) );
		$run_id      = $this->make_run( $workflow_id );

		$this->executor()->process_run( $run_id );

		$this->assertCount( 0, $this->calls );
		$this->assertSame( 'ability_workflow_destructive_forbidden', $this->step_runs_by_key( $run_id )['a']->error['code'] );
	}

	public function test_read_only_and_non_destructive_abilities_run_without_approval() {
		$this->register_abilities(
			array(
				'vendor/read'  => array(
					'run'         => function () {
						return array( 'ok' => true );
					},
					'annotations' => array( 'readonly' => true, 'destructive' => true ),
				),
				'vendor/write' => $this->safe(),
			)
		);
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/read' ), $this->step( 'b', 'vendor/write' ) ) );
		$run_id      = $this->make_run( $workflow_id );

		$this->executor()->process_run( $run_id );

		$this->assertSame( array( 'vendor/read', 'vendor/write' ), $this->called_slugs() );
	}

	// ---------------------------------------------------------------------
	// Retries and the time budget
	// ---------------------------------------------------------------------

	/**
	 * Make a parked retry due now, as if its backoff had elapsed.
	 */
	private function make_retry_due( $run_id, $step_key ) {
		$step_run = $this->step_runs_by_key( $run_id )[ $step_key ];
		$error    = $step_run->error;

		$error['next_retry_at'] = time() - 1;
		$this->repository->update_step_run_status( $step_run->id, 'pending', array( 'error' => $error ) );
	}

	public function test_transient_failure_parks_the_step_and_the_retry_reuses_one_history_row() {
		$attempts = 0;
		$this->register_abilities(
			array(
				'vendor/flaky' => array(
					'run'         => function () use ( &$attempts ) {
						$attempts++;

						return 1 === $attempts ? new WP_Error( 'provider_timeout', 'Timed out.' ) : array( 'ok' => true );
					},
					'annotations' => array( 'destructive' => false ),
				),
			)
		);
		$workflow_id = $this->make_workflow(
			array( $this->step( 'a', 'vendor/flaky', array( 'retry_policy' => array( 'attempts' => 2, 'backoff_seconds' => 7 ) ) ) )
		);
		$run_id   = $this->make_run( $workflow_id );
		$executor = $this->executor();

		$before = time();
		$result  = $executor->process_run( $run_id );

		$run = $this->repository->get_run( $run_id );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_RUNNING, $run->status );
		$this->assertSame( AIPS_Ability_Workflow_Executor::RESULT_CONTINUE, $result['state'] );
		$this->assertGreaterThanOrEqual( $before + 7, $result['retry_at'] );
		$this->assertTrue( $result['worked'] );
		$step_run = $this->step_runs_by_key( $run_id )['a'];
		$this->assertSame( 'pending', $step_run->status );
		$this->assertSame( 1, $step_run->error['attempts'] );
		$this->assertSame( 'provider_timeout', $step_run->error['code'] );

		// The retry is the same run in the same History row, still open.
		$history_id = $run->history_id;
		$this->assertNotNull( $history_id );
		$this->assertSame( 'processing', $this->history_status( $run_id ) );

		// Asking again before the backoff has elapsed does not run the step.
		$early = $executor->process_run( $run_id );
		$this->assertSame( AIPS_Ability_Workflow_Executor::RESULT_CONTINUE, $early['state'] );
		$this->assertFalse( $early['worked'] );
		$this->assertSame( $result['retry_at'], $early['retry_at'] );
		$this->assertSame( 1, $attempts );

		$this->make_retry_due( $run_id, 'a' );
		$final = $executor->process_run( $run_id );

		$run = $this->repository->get_run( $run_id );
		$this->assertSame( AIPS_Ability_Workflow_Executor::RESULT_FINISHED, $final['state'] );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_COMPLETED, $run->status );
		$this->assertSame( $history_id, $run->history_id, 'No second History row may be opened.' );
		$this->assertSame( 'completed', $this->history_status( $run_id ) );
		$this->assertSame( 2, $attempts );
		$this->assertCount( 1, $this->repository->get_step_runs( $run_id ), 'The retry reuses the step-run row.' );
	}

	public function test_failure_after_the_last_retry_fails_the_step_and_run() {
		$this->register_abilities(
			array(
				'vendor/down' => array(
					'run'         => function () {
						return new WP_Error( 'provider_timeout', 'Timed out.' );
					},
					'annotations' => array( 'destructive' => false ),
				),
			)
		);
		$workflow_id = $this->make_workflow(
			array( $this->step( 'a', 'vendor/down', array( 'retry_policy' => array( 'attempts' => 1, 'backoff_seconds' => 1 ) ) ) )
		);
		$run_id   = $this->make_run( $workflow_id );
		$executor = $this->executor();

		$executor->process_run( $run_id );
		$this->make_retry_due( $run_id, 'a' );
		$executor->process_run( $run_id );

		$this->assertCount( 2, $this->calls, 'One attempt plus one retry.' );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED, $this->repository->get_run( $run_id )->status );
		$this->assertSame( 'failed', $this->step_runs_by_key( $run_id )['a']->status );
		$this->assertSame( 'failed', $this->history_status( $run_id ) );
	}

	public function test_permanent_errors_are_not_retried() {
		$this->register_abilities(
			array(
				'vendor/missing' => array(
					'run'         => function () {
						return new WP_Error( 'post_not_found', 'No such post.' );
					},
					'annotations' => array( 'destructive' => false ),
				),
			)
		);
		$workflow_id = $this->make_workflow(
			array( $this->step( 'a', 'vendor/missing', array( 'retry_policy' => array( 'attempts' => 5, 'backoff_seconds' => 1 ) ) ) )
		);
		$run_id = $this->make_run( $workflow_id );

		$result = $this->executor()->process_run( $run_id );

		$this->assertCount( 1, $this->calls, 'A permanent error is not retried.' );
		$this->assertSame( AIPS_Ability_Workflow_Executor::RESULT_FINISHED, $result['state'] );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED, $this->repository->get_run( $run_id )->status );
	}

	public function test_exhausted_time_budget_asks_to_continue_and_the_next_slice_finishes_the_run() {
		$this->register_abilities(
			array(
				'vendor/slow'  => array(
					'run'         => function () {
						usleep( 1200000 );

						return array( 'ok' => true );
					},
					'annotations' => array( 'destructive' => false ),
				),
				'vendor/after' => $this->safe(),
			)
		);
		$workflow_id = $this->make_workflow(
			array( $this->step( 'a', 'vendor/slow' ), $this->step( 'b', 'vendor/after' ) ),
			array( 'max_runtime_seconds' => 1 )
		);
		$run_id   = $this->make_run( $workflow_id );
		$executor = $this->executor();

		$first = $executor->process_run( $run_id );

		$this->assertSame( array( 'vendor/slow' ), $this->called_slugs() );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_RUNNING, $this->repository->get_run( $run_id )->status );
		$this->assertSame( AIPS_Ability_Workflow_Executor::RESULT_CONTINUE, $first['state'] );
		$this->assertGreaterThan( 0, $first['retry_at'] );
		$this->assertSame( 'processing', $this->history_status( $run_id ), 'The History row stays open across slices.' );

		$second = $executor->process_run( $run_id );

		$this->assertSame( AIPS_Ability_Workflow_Executor::RESULT_FINISHED, $second['state'] );
		$this->assertSame( array( 'vendor/slow', 'vendor/after' ), $this->called_slugs(), 'The completed step is not repeated.' );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_COMPLETED, $this->repository->get_run( $run_id )->status );
		$this->assertSame( 'completed', $this->history_status( $run_id ) );
	}

	public function test_workflow_over_the_max_steps_limit_fails_the_run() {
		$this->register_abilities( array( 'vendor/a' => $this->safe() ) );
		$workflow_id = $this->make_workflow(
			array( $this->step( 'a', 'vendor/a' ), $this->step( 'b', 'vendor/a' ), $this->step( 'c', 'vendor/a' ) ),
			array( 'max_steps' => 2 )
		);
		$run_id = $this->make_run( $workflow_id );

		$this->executor()->process_run( $run_id );

		$this->assertCount( 0, $this->calls );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED, $this->repository->get_run( $run_id )->status );
	}

	// ---------------------------------------------------------------------
	// Cancellation
	// ---------------------------------------------------------------------

	public function test_cancelling_during_a_step_stops_before_the_next_step() {
		$executor = $this->executor();
		$run_id   = 0;
		$this->register_abilities(
			array(
				'vendor/a' => array(
					'run'         => function () use ( $executor, &$run_id ) {
						$executor->cancel_run( $run_id );

						return array( 'ok' => true );
					},
					'annotations' => array( 'destructive' => false ),
				),
				'vendor/b' => $this->safe(),
			)
		);
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/a' ), $this->step( 'b', 'vendor/b' ) ) );
		$run_id      = $this->make_run( $workflow_id );

		$executor->process_run( $run_id );

		$this->assertSame( array( 'vendor/a' ), $this->called_slugs(), 'The step after the cancellation must not run.' );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_CANCELLED, $this->repository->get_run( $run_id )->status );
		$this->assertArrayNotHasKey( 'b', $this->step_runs_by_key( $run_id ) );
	}

	public function test_cancelling_a_run_that_has_started_terminates_its_history_row() {
		$this->register_abilities(
			array(
				'vendor/flaky' => array(
					'run'         => function () {
						return new WP_Error( 'provider_timeout', 'Timed out.' );
					},
					'annotations' => array( 'destructive' => false ),
				),
			)
		);
		$workflow_id = $this->make_workflow(
			array( $this->step( 'a', 'vendor/flaky', array( 'retry_policy' => array( 'attempts' => 3, 'backoff_seconds' => 60 ) ) ) )
		);
		$run_id   = $this->make_run( $workflow_id );
		$executor = $this->executor();
		$executor->process_run( $run_id );
		$this->assertSame( 'processing', $this->history_status( $run_id ) );

		$this->assertTrue( $executor->cancel_run( $run_id ) );
		$executor->process_run( $run_id );

		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_CANCELLED, $this->repository->get_run( $run_id )->status );
		$this->assertSame( 'terminated', $this->history_status( $run_id ) );
		$this->assertCount( 1, $this->calls, 'The retry after cancellation must not run.' );
	}

	public function test_cancelling_a_queued_run_prevents_it_starting_and_opens_no_history() {
		$this->register_abilities( array( 'vendor/a' => $this->safe() ) );
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) );
		$run_id      = $this->make_run( $workflow_id );
		$executor    = $this->executor();

		$this->assertTrue( $executor->cancel_run( $run_id ) );
		$executor->process_run( $run_id );

		$this->assertCount( 0, $this->calls );
		$this->assertNull( $this->repository->get_run( $run_id )->history_id );
	}

	public function test_a_finished_run_cannot_be_cancelled() {
		$this->register_abilities( array( 'vendor/a' => $this->safe() ) );
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) );
		$run_id      = $this->make_run( $workflow_id );
		$executor    = $this->executor();
		$executor->process_run( $run_id );

		$result = $executor->cancel_run( $run_id );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_workflow_run_finished', $result->get_error_code() );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_COMPLETED, $this->repository->get_run( $run_id )->status );
	}

	public function test_cancelling_an_unknown_run_reports_not_found() {
		$result = $this->executor()->cancel_run( 987654321 );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_workflow_run_not_found', $result->get_error_code() );
	}

	// ---------------------------------------------------------------------
	// Workflow status, version, feature flag
	// ---------------------------------------------------------------------

	public function test_dispatch_refuses_archived_and_paused_workflows() {
		$this->register_abilities( array( 'vendor/a' => $this->safe() ) );

		foreach ( array( 'archived', 'paused' ) as $status ) {
			$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ), array(), $status );
			$result      = $this->executor()->dispatch_run( $workflow_id );

			$this->assertWPError( $result, $status );
			$this->assertSame( 'ability_workflow_not_runnable', $result->get_error_code() );
			$this->assertSame( 0, $this->repository->list_runs( $workflow_id )['total'], 'No run row may be created.' );
		}

		$this->assertSame( 0, $this->kicks );
	}

	public function test_dispatch_allows_draft_workflows_for_testing() {
		$this->register_abilities( array( 'vendor/a' => $this->safe() ) );
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ), array(), 'draft' );
		wp_set_current_user( $this->admin_id );

		$run_id = $this->executor()->dispatch_run( $workflow_id );

		$this->assertIsInt( $run_id );
		$this->assertSame( 1, $this->kicks, 'The background process must be woken.' );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_QUEUED, $this->repository->get_run( $run_id )->status );
	}

	public function test_dispatch_is_refused_when_the_feature_is_disabled() {
		$this->register_abilities( array( 'vendor/a' => $this->safe() ) );
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) );
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 0 );

		$result = $this->executor()->dispatch_run( $workflow_id );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_workflows_disabled', $result->get_error_code() );
		$this->assertSame( 0, $this->kicks );
	}

	public function test_queued_run_fails_when_the_feature_is_disabled_before_it_starts() {
		$this->register_abilities( array( 'vendor/a' => $this->safe() ) );
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) );
		$run_id      = $this->make_run( $workflow_id );
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 0 );

		$this->executor()->process_run( $run_id );

		$this->assertCount( 0, $this->calls );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED, $this->repository->get_run( $run_id )->status );
	}

	public function test_run_fails_when_the_workflow_is_modified_after_dispatch() {
		$this->register_abilities( array( 'vendor/a' => $this->safe(), 'vendor/b' => $this->safe() ) );
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) );
		$run_id      = $this->make_run( $workflow_id );

		$this->repository->save_steps( $workflow_id, array( $this->step( 'a', 'vendor/a' ), $this->step( 'b', 'vendor/b' ) ) );
		$this->executor()->process_run( $run_id );

		$this->assertCount( 0, $this->calls, 'A run must not execute a definition it was not dispatched against.' );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED, $this->repository->get_run( $run_id )->status );
	}

	public function test_run_fails_when_the_workflow_settings_change_after_dispatch() {
		$this->register_abilities( array( 'vendor/a' => $this->safe() ) );
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) );
		$run_id      = $this->make_run( $workflow_id );

		$this->repository->update_workflow( $workflow_id, array( 'settings' => array( 'allow_destructive_abilities' => true ) ) );
		$this->executor()->process_run( $run_id );

		$this->assertCount( 0, $this->calls );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED, $this->repository->get_run( $run_id )->status );
	}

	public function test_run_fails_when_the_workflow_is_archived_mid_run() {
		$this->register_abilities( array( 'vendor/a' => $this->safe() ) );
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) );
		$run_id      = $this->make_run( $workflow_id );

		$this->repository->archive_workflow( $workflow_id );
		$this->executor()->process_run( $run_id );

		$this->assertCount( 0, $this->calls );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED, $this->repository->get_run( $run_id )->status );
	}

	public function test_run_fails_when_the_workflow_was_deleted() {
		$this->register_abilities( array( 'vendor/a' => $this->safe() ) );
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) );
		$run_id      = $this->make_run( $workflow_id );

		$this->repository->delete_workflow( $workflow_id );
		$this->executor()->process_run( $run_id );

		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED, $this->repository->get_run( $run_id )->status );
	}

	// ---------------------------------------------------------------------
	// Locking and principal
	// ---------------------------------------------------------------------

	public function test_a_run_locked_by_another_worker_reports_busy_and_is_left_alone() {
		$this->register_abilities( array( 'vendor/a' => $this->safe() ) );
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) );
		$run_id      = $this->make_run( $workflow_id );

		$this->assertTrue( $this->repository->acquire_lock( 'aips_ability_workflow_lock_' . $run_id, 'other-worker', 60 ) );

		$result = $this->executor()->process_run( $run_id );

		$this->assertCount( 0, $this->calls );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_QUEUED, $this->repository->get_run( $run_id )->status );
		$this->assertSame( AIPS_Ability_Workflow_Executor::RESULT_BUSY, $result['state'] );
		$this->assertFalse( $result['worked'] );
		$this->assertGreaterThanOrEqual( time() + AIPS_Ability_Workflow_Executor::BUSY_RETRY_SECONDS - 1, $result['retry_at'] );
		$this->assertNull( $this->repository->get_run( $run_id )->history_id, 'A busy run must not open a History row.' );

		$this->repository->release_lock( 'aips_ability_workflow_lock_' . $run_id, 'other-worker' );
	}

	public function test_the_lock_is_released_after_a_run() {
		$this->register_abilities( array( 'vendor/a' => $this->safe() ) );
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) );
		$run_id      = $this->make_run( $workflow_id );

		$this->executor()->process_run( $run_id );

		$this->assertTrue( $this->repository->acquire_lock( 'aips_ability_workflow_lock_' . $run_id, 'next-worker', 60 ) );
		$this->repository->release_lock( 'aips_ability_workflow_lock_' . $run_id, 'next-worker' );
	}

	public function test_run_fails_when_the_dispatching_user_lost_the_capability() {
		$this->register_abilities( array( 'vendor/a' => $this->safe() ) );
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/a' ) ) );
		$run_id      = $this->make_run( $workflow_id );
		get_userdata( $this->admin_id )->set_role( 'subscriber' );

		$this->executor()->process_run( $run_id );

		$this->assertCount( 0, $this->calls );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED, $this->repository->get_run( $run_id )->status );
		$this->assertSame( 'failed', $this->history_status( $run_id ) );
	}

	public function test_an_unexpected_exception_fails_the_run_and_releases_the_lock() {
		$this->register_abilities(
			array(
				'vendor/explodes' => array(
					'run'         => function () {
						throw new RuntimeException( 'kaboom' );
					},
					'annotations' => array( 'destructive' => false ),
				),
			)
		);
		$workflow_id = $this->make_workflow( array( $this->step( 'a', 'vendor/explodes' ) ) );
		$run_id      = $this->make_run( $workflow_id );

		$this->executor()->process_run( $run_id );

		$run = $this->repository->get_run( $run_id );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED, $run->status );
		$this->assertTrue( $this->repository->acquire_lock( 'aips_ability_workflow_lock_' . $run_id, 'next-worker', 60 ) );
		$this->repository->release_lock( 'aips_ability_workflow_lock_' . $run_id, 'next-worker' );
	}
}
