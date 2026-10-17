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

		$this->reset_ability_singletons();
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 1 );
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
		$this->reset_ability_singletons();
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 0 );

		$_POST    = array();
		$_REQUEST = array();

		parent::tearDown();
	}

	/**
	 * Drop the container's cached ability services.
	 *
	 * AIPS_Ability_Service caches its resolved provider and catalog per
	 * instance, and the container keeps one instance for the whole PHP
	 * process. Without this, whichever test runs first decides which provider
	 * every later test sees.
	 */
	private function reset_ability_singletons() {
		$property = ( new ReflectionClass( AIPS_Container::class ) )->getProperty( 'singletons' );
		$property->setAccessible( true );
		$cached = $property->getValue( AIPS_Container::get_instance() );

		unset(
			$cached[ AIPS_Ability_Service::class ],
			$cached[ AIPS_Ability_Catalog_Service::class ],
			$cached[ AIPS_Ability_Workflow_Executor::class ]
		);

		$property->setValue( AIPS_Container::get_instance(), $cached );
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

	private function save_workflow( array $post ) {
		$_POST = array_merge( array( 'nonce' => wp_create_nonce( 'aips_ajax_nonce' ) ), $post );

		return $this->capture_ajax( array( new AIPS_Ability_Workflows_Controller(), 'ajax_save_workflow' ) );
	}

	public function test_saving_a_workflow_stores_only_known_settings_within_bounds() {
		$response = $this->save_workflow(
			array(
				'workflow_id' => $this->workflow_id,
				'name'        => 'Edited name',
				'status'      => 'active',
				'settings'    => wp_json_encode(
					array(
						'max_steps'                   => 5000,
						'max_runtime_seconds'         => -3,
						'allow_destructive_abilities' => 1,
						'log_payloads'                => false,
						'evil'                        => '<script>',
					)
				),
			)
		);

		$this->assertTrue( $response['success'] );

		$workflow = $this->repository->get_workflow( $this->workflow_id );
		$this->assertSame( 'Edited name', $workflow->name );
		$this->assertSame( 'active', $workflow->status );
		$this->assertSame(
			array(
				'max_steps'                   => 100,
				'max_runtime_seconds'         => 1,
				'allow_destructive_abilities' => true,
				'approved_abilities'          => array(),
			),
			$workflow->settings
		);
	}

	public function test_saving_a_workflow_rejects_an_unknown_status() {
		$response = $this->save_workflow( array( 'workflow_id' => $this->workflow_id, 'name' => 'Whatever', 'status' => 'exploded' ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'draft', $this->repository->get_workflow( $this->workflow_id )->status );
	}

	public function test_the_trigger_is_always_manual() {
		$this->save_workflow( array( 'workflow_id' => $this->workflow_id, 'name' => 'Trigger test', 'trigger_type' => 'scheduled' ) );

		$this->assertSame( 'manual', $this->repository->get_workflow( $this->workflow_id )->trigger_type );
	}

	public function test_editing_settings_bumps_the_version_so_runs_in_flight_stop() {
		$before = $this->repository->get_workflow( $this->workflow_id )->version;

		$this->save_workflow( array( 'workflow_id' => $this->workflow_id, 'name' => 'Version test', 'settings' => wp_json_encode( array( 'max_steps' => 3 ) ) ) );

		$this->assertSame( $before + 1, $this->repository->get_workflow( $this->workflow_id )->version );
	}

	public function test_a_workflow_can_be_reactivated_by_editing_its_status() {
		$this->repository->archive_workflow( $this->workflow_id );
		$this->assertSame( 'archived', $this->repository->get_workflow( $this->workflow_id )->status );

		$response = $this->save_workflow( array( 'workflow_id' => $this->workflow_id, 'name' => 'Back again', 'status' => 'active' ) );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'active', $this->repository->get_workflow( $this->workflow_id )->status );
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

	private function register_two_abilities() {
		add_filter( 'aips_ability_provider', function () {
			return array(
				'name'   => 'test-provider',
				'list'   => function () {
					return array(
						'vendor/one' => array( 'slug' => 'vendor/one' ),
						'vendor/two' => array( 'slug' => 'vendor/two' ),
					);
				},
				'invoke' => function () {
					return array( 'ok' => true );
				},
			);
		} );
	}

	private function save_steps_with_approval( array $step_abilities, $approved ) {
		$steps = array();
		foreach ( $step_abilities as $key => $ability ) {
			$steps[] = array( 'step_key' => $key, 'ability_name' => $ability );
		}

		$_POST = array(
			'nonce'              => wp_create_nonce( 'aips_ajax_nonce' ),
			'workflow_id'        => $this->workflow_id,
			'steps'              => wp_json_encode( $steps ),
			'approved_abilities' => is_string( $approved ) ? $approved : wp_json_encode( $approved ),
		);

		return $this->capture_ajax( array( new AIPS_Ability_Workflows_Controller(), 'ajax_save_workflow_steps' ) );
	}

	public function test_saving_steps_stores_approved_abilities_that_the_steps_use() {
		$this->register_two_abilities();

		$response = $this->save_steps_with_approval( array( 'a' => 'vendor/one' ), array( 'vendor/one', 'vendor/two', 'vendor/one', 'Bad Name!', 5 ) );

		$this->assertTrue( $response['success'] );
		$this->assertSame( array( 'vendor/one' ), $response['data']['approved_abilities'], 'Duplicates, unused and malformed names are dropped.' );
		$this->assertSame( array( 'vendor/one' ), $this->repository->get_workflow( $this->workflow_id )->settings['approved_abilities'] );
	}

	public function test_saving_steps_without_an_approval_list_leaves_the_stored_list_alone() {
		$this->register_two_abilities();
		$this->save_steps_with_approval( array( 'a' => 'vendor/one' ), array( 'vendor/one' ) );

		$_POST = array(
			'nonce'       => wp_create_nonce( 'aips_ajax_nonce' ),
			'workflow_id' => $this->workflow_id,
			'steps'       => wp_json_encode( array( array( 'step_key' => 'a', 'ability_name' => 'vendor/one' ) ) ),
		);
		$this->capture_ajax( array( new AIPS_Ability_Workflows_Controller(), 'ajax_save_workflow_steps' ) );

		$this->assertSame( array( 'vendor/one' ), $this->repository->get_workflow( $this->workflow_id )->settings['approved_abilities'] );
	}

	public function test_saving_settings_keeps_the_approved_list_and_a_new_list_replaces_it() {
		$this->register_two_abilities();
		$this->save_steps_with_approval( array( 'a' => 'vendor/one' ), array( 'vendor/one' ) );

		$this->save_workflow( array( 'workflow_id' => $this->workflow_id, 'name' => 'Renamed', 'status' => 'draft', 'settings' => wp_json_encode( array( 'max_steps' => 10 ) ) ) );
		$this->assertSame( array( 'vendor/one' ), $this->repository->get_workflow( $this->workflow_id )->settings['approved_abilities'], 'The settings form does not send the list.' );

		$this->save_workflow( array( 'workflow_id' => $this->workflow_id, 'name' => 'Renamed', 'status' => 'draft', 'settings' => wp_json_encode( array( 'approved_abilities' => array( 'vendor/two', 'x y' ) ) ) ) );
		$this->assertSame( array( 'vendor/two' ), $this->repository->get_workflow( $this->workflow_id )->settings['approved_abilities'] );
	}
}
