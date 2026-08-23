<?php
/**
 * Tests for AIPS_Ability_Workflows_Controller
 *
 * Regression coverage for two review findings on the Ability Workflows
 * feature: the document validator must actually be wired with a catalog
 * service (otherwise ability-existence checks silently never run), and
 * malformed JSON in the steps payload must not silently wipe an existing
 * workflow's steps.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ability_Workflows_Controller extends WP_UnitTestCase {

	private $admin_user_id;
	private $repository;
	private $workflow_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_user_id );

		$_REQUEST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );

		$this->repository  = AIPS_Ability_Workflow_Repository::instance();
		$this->workflow_id = $this->repository->create_workflow( array( 'name' => 'Test Workflow' ) );
	}

	public function tearDown(): void {
		if ( $this->workflow_id && ! is_wp_error( $this->workflow_id ) ) {
			$this->repository->delete_workflow( $this->workflow_id );
		}

		remove_all_filters( 'aips_ability_provider' );

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

	/**
	 * Finding: the controller previously constructed
	 * AIPS_Ability_Workflow_Document_Validator with no catalog service, so
	 * ability-existence validation silently never ran. With no
	 * aips_ability_provider filter registered, the catalog reports every
	 * ability name as unavailable — saving a step should now be rejected.
	 */
	public function test_save_workflow_steps_rejects_nonexistent_ability() {
		$controller = new AIPS_Ability_Workflows_Controller();

		$steps = array(
			array(
				'step_key'     => 'first_step',
				'ability_name' => 'vendor/does-not-exist',
			),
		);

		$_POST = array(
			'nonce'       => wp_create_nonce( 'aips_ajax_nonce' ),
			'workflow_id' => $this->workflow_id,
			'steps'       => wp_json_encode( $steps ),
		);

		$response = $this->capture_ajax( array( $controller, 'ajax_save_workflow_steps' ) );

		$this->assertFalse( $response['success'], 'Expected save to be rejected for a nonexistent ability_name.' );

		$saved_steps = $this->repository->get_steps( $this->workflow_id );
		$this->assertCount( 0, $saved_steps, 'No step should have been persisted.' );
	}

	/**
	 * Finding: malformed JSON in the steps payload decoded to an empty
	 * array and passed validation trivially, silently wiping any existing
	 * steps via save_steps(). Seed one valid step first, then attempt to
	 * save with malformed JSON and confirm the existing step survives.
	 */
	public function test_save_workflow_steps_rejects_malformed_json_without_wiping_existing_steps() {
		add_filter( 'aips_ability_provider', function () {
			return array(
				'name'   => 'test-provider',
				'list'   => function () {
					return array( 'vendor/real-ability' => array( 'slug' => 'vendor/real-ability' ) );
				},
				'invoke' => function () {
					return array( 'content' => 'ok' );
				},
			);
		} );

		$controller = new AIPS_Ability_Workflows_Controller();

		// Seed one valid, persisted step.
		$_POST = array(
			'nonce'       => wp_create_nonce( 'aips_ajax_nonce' ),
			'workflow_id' => $this->workflow_id,
			'steps'       => wp_json_encode( array(
				array( 'step_key' => 'seed_step', 'ability_name' => 'vendor/real-ability' ),
			) ),
		);
		$seed_response = $this->capture_ajax( array( $controller, 'ajax_save_workflow_steps' ) );
		$this->assertTrue( $seed_response['success'], 'Seed save should succeed.' );
		$this->assertCount( 1, $this->repository->get_steps( $this->workflow_id ) );

		// Now attempt to save with a malformed (non-empty, invalid) JSON payload.
		$_POST = array(
			'nonce'       => wp_create_nonce( 'aips_ajax_nonce' ),
			'workflow_id' => $this->workflow_id,
			'steps'       => '{not valid json',
		);
		$bad_response = $this->capture_ajax( array( $controller, 'ajax_save_workflow_steps' ) );

		$this->assertFalse( $bad_response['success'], 'Malformed JSON payload should be rejected.' );

		$steps_after = $this->repository->get_steps( $this->workflow_id );
		$this->assertCount( 1, $steps_after, 'The previously-saved step must survive a malformed-JSON save attempt.' );
		$this->assertSame( 'seed_step', $steps_after[0]->step_key );
	}
}
