<?php
/**
 * Tests for AIPS_Ability_Workflow_Runs_Controller
 *
 * Focused regression coverage for a review finding: ajax_cancel_run()
 * reported success even for a nonexistent run_id, since
 * AIPS_Ability_Workflow_Repository::update_run_status() returns true
 * whenever $wpdb->update() doesn't return false — including when it
 * matched zero rows.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ability_Workflow_Runs_Controller extends WP_UnitTestCase {

	private $admin_user_id;
	private $repository;

	public function setUp(): void {
		parent::setUp();

		$this->admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_user_id );

		$_REQUEST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );

		$this->repository = AIPS_Ability_Workflow_Repository::instance();
	}

	public function tearDown(): void {
		$_POST    = array();
		$_REQUEST = array();

		parent::tearDown();
	}

	/**
	 * Capture JSON output produced by a controller AJAX method.
	 *
	 * @param callable $callable Controller method to invoke.
	 * @return array Decoded response array.
	 */
	private function capture_ajax( callable $callable ) {
		if ( isset( $_POST['nonce'] ) ) {
			$_REQUEST['nonce'] = $_POST['nonce'];
		}

		ob_start();

		try {
			$callable();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected after wp_send_json_*.
		} catch ( WPAjaxDieStopException $e ) {
			// Expected in full WordPress mode when no buffered output exists yet.
		}

		return json_decode( strtok( trim( ob_get_clean() ), "\r\n" ), true );
	}

	public function test_cancel_run_returns_not_found_for_nonexistent_run() {
		$controller = new AIPS_Ability_Workflow_Runs_Controller();

		$_POST = array(
			'nonce'  => wp_create_nonce( 'aips_ajax_nonce' ),
			'run_id' => 999999,
		);

		$response = $this->capture_ajax( array( $controller, 'ajax_cancel_run' ) );

		$this->assertFalse( $response['success'], 'Canceling a nonexistent run_id must not report success.' );
	}

	public function test_cancel_run_succeeds_for_existing_run() {
		$workflow_id = $this->repository->create_workflow( array( 'name' => 'Test Cancel Run Workflow' ) );
		$run_id      = $this->repository->create_run( $workflow_id, 1, array() );

		$controller = new AIPS_Ability_Workflow_Runs_Controller();

		$_POST = array(
			'nonce'  => wp_create_nonce( 'aips_ajax_nonce' ),
			'run_id' => $run_id,
		);

		$response = $this->capture_ajax( array( $controller, 'ajax_cancel_run' ) );

		$this->assertTrue( $response['success'] );

		$run = $this->repository->get_run( $run_id );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_CANCELLED, $run->status );

		$this->repository->delete_workflow( $workflow_id );
	}
}
