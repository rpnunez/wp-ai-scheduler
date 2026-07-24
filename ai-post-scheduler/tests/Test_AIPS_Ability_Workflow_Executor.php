<?php
/**
 * Tests for AIPS_Ability_Workflow_Executor
 *
 * Focused regression coverage for a review finding: a workflow run can
 * span multiple cron invocations (time-budget continuations, retry
 * backoff), and the 'skip' on_success/on_failure strategy's cascade to
 * dependent steps must be reconstructible from persisted step statuses on
 * every invocation — not just accumulated within a single pass — or a
 * cascade interrupted partway through would be lost, letting a step that
 * should stay permanently skipped execute on a later invocation.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ability_Workflow_Executor extends WP_UnitTestCase {

	private $executor;
	private $created_workflow_ids = array();

	public function setUp(): void {
		parent::setUp();
		$this->executor = new AIPS_Ability_Workflow_Executor();
	}

	public function tearDown(): void {
		$repository = AIPS_Ability_Workflow_Repository::instance();

		foreach ( $this->created_workflow_ids as $workflow_id ) {
			$repository->delete_workflow( $workflow_id );
		}

		$this->created_workflow_ids = array();

		parent::tearDown();
	}

	/**
	 * Build an AIPS_Ability_Workflow_Step via from_row(), matching the
	 * JSON-string-per-column shape a real DB row would have.
	 *
	 * @param array $overrides Fields to override on top of sane defaults.
	 * @return AIPS_Ability_Workflow_Step
	 */
	private function make_step( array $overrides = array() ): AIPS_Ability_Workflow_Step {
		$defaults = array(
			'id'             => 1,
			'workflow_id'    => 1,
			'step_key'       => 'step',
			'name'           => null,
			'ability_name'   => 'vendor/ability',
			'position'       => 0,
			'depends_on'     => '[]',
			'input_map'      => '{}',
			'condition_tree' => '{}',
			'output_alias'   => null,
			'on_success'     => wp_json_encode( array( 'strategy' => 'continue' ) ),
			'on_failure'     => wp_json_encode( array( 'strategy' => 'stop' ) ),
			'retry_policy'   => wp_json_encode( array( 'attempts' => 0, 'backoff_seconds' => 5 ) ),
			'created_at'     => 0,
			'updated_at'     => 0,
		);

		$row = (object) array_merge( $defaults, $overrides );

		return AIPS_Ability_Workflow_Step::from_row( $row );
	}

	/**
	 * Invoke the private rebuild_skip_cascade() method directly.
	 *
	 * @param array $steps
	 * @param array $resolved_status
	 * @return array
	 */
	private function invoke_rebuild_skip_cascade( array $steps, array $resolved_status ): array {
		$reflection = new ReflectionClass( $this->executor );
		$method = $reflection->getMethod( 'rebuild_skip_cascade' );
		$method->setAccessible( true );

		return $method->invoke( $this->executor, $steps, $resolved_status );
	}

	public function test_skip_cascade_reconstructed_from_persisted_completed_status() {
		$ancestor = $this->make_step( array(
			'step_key'   => 'ancestor',
			'on_success' => wp_json_encode( array( 'strategy' => 'skip' ) ),
		) );

		$dependent = $this->make_step( array(
			'step_key'   => 'dependent',
			'depends_on' => wp_json_encode( array( 'ancestor' ) ),
		) );

		$grandchild = $this->make_step( array(
			'step_key'   => 'grandchild',
			'depends_on' => wp_json_encode( array( 'dependent' ) ),
		) );

		$steps = array( $ancestor, $dependent, $grandchild );

		// Simulate a fresh invocation where only the ancestor's outcome has
		// been persisted so far — e.g. the previous invocation hit its time
		// budget deadline immediately after completing 'ancestor', before
		// the in-memory-only $skip_keys accumulator (the pre-fix behavior)
		// could reach 'dependent'/'grandchild' in the same pass.
		$resolved_status = array( 'ancestor' => 'completed' );

		$skip_keys = $this->invoke_rebuild_skip_cascade( $steps, $resolved_status );

		$this->assertArrayHasKey( 'dependent', $skip_keys, 'Direct dependent of a skip-strategy ancestor must be reconstructed into the skip set.' );
		$this->assertArrayHasKey( 'grandchild', $skip_keys, 'Transitive dependent must also be reconstructed into the skip set.' );
	}

	public function test_skip_cascade_reconstructed_from_persisted_failed_status() {
		$ancestor = $this->make_step( array(
			'step_key'   => 'ancestor',
			'on_failure' => wp_json_encode( array( 'strategy' => 'skip' ) ),
		) );

		$dependent = $this->make_step( array(
			'step_key'   => 'dependent',
			'depends_on' => wp_json_encode( array( 'ancestor' ) ),
		) );

		$steps = array( $ancestor, $dependent );
		$resolved_status = array( 'ancestor' => 'failed' );

		$skip_keys = $this->invoke_rebuild_skip_cascade( $steps, $resolved_status );

		$this->assertArrayHasKey( 'dependent', $skip_keys );
	}

	public function test_no_cascade_when_strategy_is_continue() {
		$ancestor  = $this->make_step( array( 'step_key' => 'ancestor' ) ); // default on_success.strategy = 'continue'
		$dependent = $this->make_step( array(
			'step_key'   => 'dependent',
			'depends_on' => wp_json_encode( array( 'ancestor' ) ),
		) );

		$steps = array( $ancestor, $dependent );
		$resolved_status = array( 'ancestor' => 'completed' );

		$skip_keys = $this->invoke_rebuild_skip_cascade( $steps, $resolved_status );

		$this->assertArrayNotHasKey( 'dependent', $skip_keys );
	}

	public function test_no_cascade_for_unresolved_ancestor() {
		$ancestor = $this->make_step( array(
			'step_key'   => 'ancestor',
			'on_success' => wp_json_encode( array( 'strategy' => 'skip' ) ),
		) );

		$dependent = $this->make_step( array(
			'step_key'   => 'dependent',
			'depends_on' => wp_json_encode( array( 'ancestor' ) ),
		) );

		$steps = array( $ancestor, $dependent );
		$resolved_status = array(); // ancestor not yet resolved this run

		$skip_keys = $this->invoke_rebuild_skip_cascade( $steps, $resolved_status );

		$this->assertSame( array(), $skip_keys );
	}

	/**
	 * Finding: schedule_continuation()'s underlying schedule_simple() return
	 * value was previously discarded — a scheduling failure left a run
	 * stuck at 'running'/'queued' forever. dispatch_run() must now mark the
	 * just-created run row 'failed' instead of leaving it orphaned.
	 */
	public function test_dispatch_run_marks_run_failed_when_scheduling_fails() {
		$repository = AIPS_Ability_Workflow_Repository::instance();
		$workflow_id = $repository->create_workflow( array( 'name' => 'Test Scheduling Failure Workflow' ) );
		$this->created_workflow_ids[] = $workflow_id;

		$failing_scheduler = new class extends AIPS_Job_Scheduler {
			public function schedule_simple( string $hook, int $fire_at, array $args = array(), array $options = array() ): bool {
				return false;
			}
		};

		$executor = new AIPS_Ability_Workflow_Executor( null, null, null, null, null, $failing_scheduler );

		$result = $executor->dispatch_run( $workflow_id );

		$this->assertInstanceOf( 'WP_Error', $result );

		$runs = $repository->list_runs( $workflow_id );
		$this->assertCount( 1, $runs['items'] );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED, $runs['items'][0]->status );
	}
}
