<?php
/**
 * Tests for AIPS_Ability_Workflow_Repository
 *
 * Focused regression coverage for a review finding: list_runs() did not
 * treat per_page <= 0 as "no limit" the way list_workflows() does, so
 * LIMIT 0 silently returned zero rows instead of every run.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ability_Workflow_Repository extends WP_UnitTestCase {

	private $repository;
	private $created_workflow_ids = array();

	public function setUp(): void {
		parent::setUp();
		$this->repository = AIPS_Ability_Workflow_Repository::instance();
	}

	public function tearDown(): void {
		foreach ( $this->created_workflow_ids as $workflow_id ) {
			$this->repository->delete_workflow( $workflow_id );
		}

		$this->created_workflow_ids = array();

		parent::tearDown();
	}

	public function test_list_runs_with_per_page_zero_returns_all_runs() {
		$workflow_id = $this->repository->create_workflow( array( 'name' => 'Test List Runs Workflow' ) );
		$this->created_workflow_ids[] = $workflow_id;

		for ( $i = 0; $i < 3; $i++ ) {
			$this->repository->create_run( $workflow_id, 1, array() );
		}

		$result = $this->repository->list_runs( $workflow_id, array( 'per_page' => 0 ) );

		$this->assertSame( 3, $result['total'] );
		$this->assertCount( 3, $result['items'], 'per_page=0 must mean "no limit", not zero rows.' );
	}

	public function test_list_runs_respects_positive_per_page() {
		$workflow_id = $this->repository->create_workflow( array( 'name' => 'Test List Runs Paginated Workflow' ) );
		$this->created_workflow_ids[] = $workflow_id;

		for ( $i = 0; $i < 3; $i++ ) {
			$this->repository->create_run( $workflow_id, 1, array() );
		}

		$result = $this->repository->list_runs( $workflow_id, array( 'per_page' => 2, 'page' => 1 ) );

		$this->assertSame( 3, $result['total'] );
		$this->assertCount( 2, $result['items'] );
	}
}
