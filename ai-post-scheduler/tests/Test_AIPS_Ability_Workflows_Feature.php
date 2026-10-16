<?php
/**
 * Tests that the Ability Workflows switch is enforced on the server (AJAX
 * endpoints, not just the menu), and for the run/cancel and change-hook
 * behaviour of the controllers.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ability_Workflows_Feature extends WP_UnitTestCase {

	/** @var AIPS_Ability_Workflow_Repository */
	private $repository;

	/** @var int[] */
	private $workflow_ids = array();

	/** @var array[] Payloads of aips_ability_workflow_changed. */
	private $changes = array();

	public function setUp(): void {
		parent::setUp();

		$this->repository = AIPS_Ability_Workflow_Repository::instance();
		$this->changes    = array();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		add_action(
			'aips_ability_workflow_changed',
			function ( $change ) {
				$this->changes[] = $change;
			}
		);
	}

	public function tearDown(): void {
		foreach ( $this->workflow_ids as $workflow_id ) {
			$this->repository->delete_workflow( $workflow_id );
		}

		remove_all_actions( 'aips_ability_workflow_changed' );
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 0 );
		$_POST    = array();
		$_REQUEST = array();
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	private function enable() {
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 1 );
	}

	private function make_workflow() {
		$workflow_id = $this->repository->create_workflow( array( 'name' => 'Feature test workflow', 'status' => 'active' ) );
		$this->workflow_ids[] = $workflow_id;

		return $workflow_id;
	}

	/**
	 * Call an AJAX handler with a valid nonce and return the decoded JSON.
	 */
	private function call( callable $handler, array $post = array() ) {
		$_POST                = array_merge( array( 'nonce' => wp_create_nonce( 'aips_ajax_nonce' ) ), $post );
		$_REQUEST['nonce']    = $_POST['nonce'];

		ob_start();

		try {
			$handler();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected after wp_send_json_*.
		} catch ( WPAjaxDieStopException $e ) {
			// Expected in full WordPress mode.
		}

		return json_decode( strtok( trim( ob_get_clean() ), "\r\n" ), true );
	}

	// ---------------------------------------------------------------------
	// The switch itself
	// ---------------------------------------------------------------------

	public function test_the_feature_is_off_by_default() {
		$this->assertFalse( AIPS_Ability_Workflows_Feature::is_enabled() );
		$this->assertFalse( (bool) AIPS_Config::get_instance()->get_default_options()['aips_enable_ability_workflows'] );
	}

	public function test_the_feature_follows_the_setting() {
		$this->enable();
		$this->assertTrue( AIPS_Ability_Workflows_Feature::is_enabled() );

		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 0 );
		$this->assertFalse( AIPS_Ability_Workflows_Feature::is_enabled() );
	}

	/**
	 * @return array[] Controller class, handler method.
	 */
	public function handlers() {
		return array(
			'save workflow'    => array( 'AIPS_Ability_Workflows_Controller', 'ajax_save_workflow' ),
			'get workflow'     => array( 'AIPS_Ability_Workflows_Controller', 'ajax_get_workflow' ),
			'list workflows'   => array( 'AIPS_Ability_Workflows_Controller', 'ajax_list_workflows' ),
			'delete workflow'  => array( 'AIPS_Ability_Workflows_Controller', 'ajax_delete_workflow' ),
			'duplicate'        => array( 'AIPS_Ability_Workflows_Controller', 'ajax_duplicate_workflow' ),
			'archive'          => array( 'AIPS_Ability_Workflows_Controller', 'ajax_archive_workflow' ),
			'save steps'       => array( 'AIPS_Ability_Workflows_Controller', 'ajax_save_workflow_steps' ),
			'run now'          => array( 'AIPS_Ability_Workflow_Runs_Controller', 'ajax_run_now' ),
			'list runs'        => array( 'AIPS_Ability_Workflow_Runs_Controller', 'ajax_list_runs' ),
			'get run'          => array( 'AIPS_Ability_Workflow_Runs_Controller', 'ajax_get_run' ),
			'cancel run'       => array( 'AIPS_Ability_Workflow_Runs_Controller', 'ajax_cancel_run' ),
			'list abilities'   => array( 'AIPS_Ability_Catalog_Controller', 'ajax_list_abilities' ),
		);
	}

	/**
	 * @dataProvider handlers
	 */
	public function test_every_endpoint_refuses_requests_while_the_feature_is_disabled( $class, $method ) {
		$workflow_id = $this->make_workflow();
		$controller  = new $class();

		$response = $this->call(
			array( $controller, $method ),
			array( 'workflow_id' => $workflow_id, 'name' => 'Should not be created', 'run_id' => 1 )
		);

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'ability_workflows_disabled', $response['data']['code'] );
		$this->assertSame( 0, $this->repository->list_runs( $workflow_id )['total'], 'No run may be created.' );
		$this->assertSame( array(), $this->changes, 'No change may be made.' );
	}

	public function test_a_disabled_feature_creates_nothing_through_save_workflow() {
		$controller = new AIPS_Ability_Workflows_Controller();

		$this->call( array( $controller, 'ajax_save_workflow' ), array( 'name' => 'Sneaky workflow' ) );

		$this->assertSame( 0, $this->repository->list_workflows( array( 'search' => 'Sneaky workflow' ) )['total'] );
	}

	public function test_endpoints_work_when_the_feature_is_enabled() {
		$this->enable();
		$controller = new AIPS_Ability_Workflows_Controller();

		$response = $this->call( array( $controller, 'ajax_list_workflows' ) );

		$this->assertTrue( $response['success'] );
	}

	// ---------------------------------------------------------------------
	// Change hook
	// ---------------------------------------------------------------------

	public function test_the_change_hook_reports_created_for_a_new_workflow_and_saved_for_an_update() {
		$this->enable();
		$controller = new AIPS_Ability_Workflows_Controller();

		$created = $this->call( array( $controller, 'ajax_save_workflow' ), array( 'name' => 'Hook workflow' ) );
		$this->assertTrue( $created['success'] );
		$workflow_id = (int) $created['data']['workflow']['id'];
		$this->workflow_ids[] = $workflow_id;

		$this->call( array( $controller, 'ajax_save_workflow' ), array( 'name' => 'Hook workflow renamed', 'workflow_id' => $workflow_id ) );

		$this->assertSame( 'created', $this->changes[0]['action'] );
		$this->assertSame( 'saved', $this->changes[1]['action'] );
		$this->assertSame( $workflow_id, $this->changes[1]['workflow_id'] );
	}

	// ---------------------------------------------------------------------
	// Cancel endpoint
	// ---------------------------------------------------------------------

	public function test_cancel_endpoint_refuses_a_finished_run_and_leaves_it_untouched() {
		$this->enable();
		$workflow_id = $this->make_workflow();
		$run_id      = $this->repository->create_run( $workflow_id, 1 );
		$this->repository->update_run_status( $run_id, AIPS_Ability_Workflow_Repository::RUN_STATUS_COMPLETED, array( 'finished_at' => 50 ) );
		$controller = new AIPS_Ability_Workflow_Runs_Controller();

		$response = $this->call( array( $controller, 'ajax_cancel_run' ), array( 'run_id' => $run_id ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'ability_workflow_run_finished', $response['data']['code'] );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_COMPLETED, $this->repository->get_run( $run_id )->status );
		$this->assertSame( array(), $this->changes, 'No cancel event may fire for a run that was not cancelled.' );
	}

	public function test_cancel_endpoint_cancels_a_queued_run_and_fires_the_change_hook() {
		$this->enable();
		$workflow_id = $this->make_workflow();
		$run_id      = $this->repository->create_run( $workflow_id, 1 );
		$controller  = new AIPS_Ability_Workflow_Runs_Controller();

		$response = $this->call( array( $controller, 'ajax_cancel_run' ), array( 'run_id' => $run_id ) );

		$this->assertTrue( $response['success'] );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_CANCELLED, $this->repository->get_run( $run_id )->status );
		$this->assertSame( 'run_cancelled', $this->changes[0]['action'] );
	}

	public function test_run_now_refuses_an_archived_workflow() {
		$this->enable();
		$workflow_id = $this->make_workflow();
		$this->repository->archive_workflow( $workflow_id );
		$controller = new AIPS_Ability_Workflow_Runs_Controller();

		$response = $this->call( array( $controller, 'ajax_run_now' ), array( 'workflow_id' => $workflow_id ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 0, $this->repository->list_runs( $workflow_id )['total'] );
	}
}
