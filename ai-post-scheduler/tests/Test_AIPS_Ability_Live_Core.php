<?php
/**
 * Live checks against the abilities WordPress core registers itself.
 *
 * Nothing here is faked: AIPS_Ability_Service talks to the real
 * wp_get_abilities()/wp_get_ability() registry, so these tests prove the
 * adapter, catalog, destructive gate and workflow executor work against the
 * real WP_Ability implementation of the WordPress under test. They skip when
 * the Abilities API is not available (WordPress older than 6.9).
 *
 * @group abilities-live
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ability_Live_Core extends WP_UnitTestCase {

	/** @var AIPS_Ability_Workflow_Repository */
	private $repository;

	/** @var int[] */
	private $workflow_ids = array();

	/** @var int|null The init counter before this test faked it. */
	private $init_count = null;

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'wp_get_abilities' ) || ! function_exists( 'wp_get_ability' ) || ! class_exists( 'WP_Abilities_Registry' ) ) {
			$this->markTestSkipped( 'The WordPress Abilities API is not available.' );
		}

		$this->reset_registries();

		if ( ! wp_get_ability( 'core/get-site-info' ) ) {
			$this->markTestSkipped( 'This WordPress does not register core/get-site-info.' );
		}

		$this->repository = AIPS_Ability_Workflow_Repository::instance();
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 1 );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Start the real registries from scratch.
	 *
	 * The registry refuses to initialise until `init` has fired, and the test
	 * harness restores the action counters, so `init` reads as not fired inside a
	 * test. Mark it fired and rebuild the registries so core registers its
	 * abilities exactly as it does on a real request.
	 */
	private function reset_registries() {
		global $wp_actions;

		$this->init_count = isset( $wp_actions['init'] ) ? $wp_actions['init'] : null;

		if ( empty( $wp_actions['init'] ) ) {
			$wp_actions['init'] = 1;
		}

		// The harness unhooks core's registration so tests start with an empty
		// registry; put it back for these live checks (WP_UnitTestCase restores
		// the hooks after each test).
		remove_action( 'wp_abilities_api_categories_init', '_unhook_core_ability_categories_registration', 1 );
		remove_action( 'wp_abilities_api_init', '_unhook_core_abilities_registration', 1 );

		// A real registered ability that declares no input schema, as many plugins' do.
		add_action(
			'wp_abilities_api_init',
			function () {
				wp_register_ability(
					'aips-test/ping',
					array(
						'label'               => 'Ping',
						'description'         => 'Returns a fixed answer and takes no input.',
						'category'            => 'site',
						'execute_callback'    => function () {
							return array( 'pong' => true );
						},
						'permission_callback' => '__return_true',
						'meta'                => array( 'annotations' => array( 'readonly' => true ) ),
					)
				);
			},
			20
		);

		foreach ( array( 'WP_Abilities_Registry', 'WP_Ability_Categories_Registry' ) as $class ) {
			if ( class_exists( $class ) ) {
				$property = new ReflectionProperty( $class, 'instance' );
				$property->setAccessible( true );
				$property->setValue( null, null );
			}
		}
	}

	public function tearDown(): void {
		global $wp_actions;

		foreach ( $this->workflow_ids as $workflow_id ) {
			$this->repository->delete_workflow( $workflow_id );
		}

		if ( null === $this->init_count ) {
			unset( $wp_actions['init'] );
		} else {
			$wp_actions['init'] = $this->init_count;
		}

		foreach ( array( 'WP_Abilities_Registry', 'WP_Ability_Categories_Registry' ) as $class ) {
			if ( class_exists( $class ) ) {
				$property = new ReflectionProperty( $class, 'instance' );
				$property->setAccessible( true );
				$property->setValue( null, null );
			}
		}

		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 0 );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	private function catalog() {
		return new AIPS_Ability_Catalog_Service( new AIPS_Ability_Service() );
	}

	public function test_the_adapter_lists_the_registered_core_abilities() {
		$abilities = ( new AIPS_Ability_Service() )->list_available();

		$this->assertIsArray( $abilities );
		$this->assertArrayHasKey( 'core/get-site-info', $abilities );
		$this->assertTrue( ( new AIPS_Ability_Service() )->is_available( 'core/get-site-info' ) );
		$this->assertFalse( ( new AIPS_Ability_Service() )->is_available( 'core/this-does-not-exist' ) );
	}

	public function test_the_catalog_reads_real_labels_schemas_and_annotations() {
		$ability = $this->catalog()->get_ability( 'core/get-site-info' );

		$this->assertIsArray( $ability );
		$this->assertSame( 'core/get-site-info', $ability['name'] );
		$this->assertNotSame( '', $ability['label'] );
		$this->assertNotEmpty( $ability['output_schema'], 'Core declares an output schema.' );
		$this->assertTrue( $ability['annotations']['readonly'], 'Core marks its information abilities read-only.' );
		$this->assertFalse( $ability['requires_approval'], 'A read-only ability needs no approval.' );
	}

	/**
	 * @dataProvider input_less_abilities
	 */
	public function test_input_less_core_abilities_run_with_an_empty_payload( $slug ) {
		if ( ! wp_get_ability( $slug ) ) {
			$this->markTestSkipped( $slug . ' is not registered here.' );
		}

		$result = ( new AIPS_Ability_Service() )->invoke( $slug, array() );

		$this->assertNotWPError( $result, 'An empty payload must reach an input-less ability as null.' );
		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
	}

	public function input_less_abilities() {
		return array(
			'site info'        => array( 'core/get-site-info' ),
			'environment info' => array( 'core/get-environment-info' ),
			'user info'        => array( 'core/get-user-info' ),
		);
	}

	public function test_get_site_info_returns_this_sites_real_data() {
		$result = ( new AIPS_Ability_Service() )->invoke( 'core/get-site-info', array() );

		$this->assertNotWPError( $result );
		$this->assertSame( get_bloginfo( 'name' ), $result['name'] );
		$this->assertSame( get_bloginfo( 'version' ), $result['version'] );
	}

	public function test_wordpress_rejects_an_empty_array_for_an_ability_without_an_input_schema() {
		$ability = wp_get_ability( 'aips-test/ping' );
		$this->assertNotNull( $ability, 'The fixture ability must be registered on the real registry.' );

		// Documents the WordPress behaviour the adapter works around, then proves the work-around.
		$this->assertWPError( $ability->validate_input( array() ) );
		$this->assertTrue( $ability->validate_input( null ) );

		$result = ( new AIPS_Ability_Service() )->invoke( 'aips-test/ping', array() );

		$this->assertNotWPError( $result );
		$this->assertSame( array( 'pong' => true ), $result );
	}

	public function test_an_ability_the_user_may_not_run_reports_a_permission_error() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$result = ( new AIPS_Ability_Service() )->invoke( 'core/get-environment-info', array() );

		// Core limits environment info to administrators; the adapter must not bypass that.
		$this->assertWPError( $result );
	}

	public function test_a_workflow_runs_real_core_abilities_end_to_end() {
		$workflow_id = $this->repository->create_workflow( array( 'name' => 'Live core workflow', 'status' => 'active' ) );
		$this->workflow_ids[] = $workflow_id;
		$this->assertTrue(
			$this->repository->save_steps(
				$workflow_id,
				array(
					array( 'step_key' => 'site', 'ability_name' => 'core/get-site-info' ),
					array(
						'step_key'       => 'again',
						'ability_name'   => 'core/get-site-info',
						'depends_on'     => array( 'site' ),
						'condition_tree' => array(
							'rules' => array( array( 'left' => '{{steps.site.output.name}}', 'operator' => 'is_not_empty' ) ),
						),
					),
				)
			)
		);
		$run_id = $this->repository->create_run( $workflow_id, $this->repository->get_workflow( $workflow_id )->version );
		wp_set_current_user( 0 );

		$result = ( new AIPS_Ability_Workflow_Executor( null, null, null, null, null, null, null, '__return_true' ) )->process_run( $run_id );

		$this->assertSame( AIPS_Ability_Workflow_Executor::RESULT_FINISHED, $result['state'] );
		$run = $this->repository->get_run( $run_id );
		$this->assertSame( AIPS_Ability_Workflow_Repository::RUN_STATUS_COMPLETED, $run->status );

		$step_runs = array();
		foreach ( $this->repository->get_step_runs( $run_id ) as $step_run ) {
			$step_runs[ $step_run->step_key ] = $step_run;
		}
		$this->assertSame( 'completed', $step_runs['site']->status );
		$this->assertSame( get_bloginfo( 'name' ), $step_runs['site']->output_snapshot['name'] );
		$this->assertSame( 'completed', $step_runs['again']->status );
	}
}
