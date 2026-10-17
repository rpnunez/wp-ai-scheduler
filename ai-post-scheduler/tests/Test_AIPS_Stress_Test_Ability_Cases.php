<?php
/**
 * Tests for the `abilities` group of Diagnostics > Stress Test.
 *
 * @package AI_Post_Scheduler
 */

/**
 * AI service stub that reports no provider, to prove the ability cases do not
 * depend on AIPS's own AI provider.
 */
class AIPS_Test_Stress_No_AI_Service {
	public function is_available() {
		return false;
	}

	public function clear_call_log() {}

	public function get_call_log() {
		return array();
	}
}

class Test_AIPS_Stress_Test_Ability_Cases extends WP_UnitTestCase {

	/** @var array[] Invocations seen by the fixture provider. */
	private $calls = array();

	public function setUp(): void {
		parent::setUp();

		$this->calls = array();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 1 );
	}

	public function tearDown(): void {
		( new AIPS_Stress_Test_Ability_Cases() )->cleanup();
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 0 );
		remove_all_filters( 'aips_ability_provider' );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	// ---------------------------------------------------------------------
	// Fixtures
	// ---------------------------------------------------------------------

	/**
	 * Register a fixture provider.
	 *
	 * @param array $abilities Slug => array( 'raw' => extra ability fields, 'run' => callable( $payload ) ).
	 */
	private function register_abilities( array $abilities ) {
		add_filter(
			'aips_ability_provider',
			function () use ( $abilities ) {
				return array(
					'name'   => 'stress-fixture',
					'list'   => function () use ( $abilities ) {
						$list = array();

						foreach ( $abilities as $slug => $definition ) {
							$list[ $slug ] = array_merge( array( 'slug' => $slug ), isset( $definition['raw'] ) ? $definition['raw'] : array() );
						}

						return $list;
					},
					'invoke' => function ( $slug, $payload ) use ( $abilities ) {
						$this->calls[] = $slug;

						return call_user_func( $abilities[ $slug ]['run'], $payload );
					},
				);
			}
		);
	}

	private function cases() {
		return new AIPS_Stress_Test_Ability_Cases();
	}

	private function site_info() {
		return array(
			'raw' => array( 'annotations' => array( 'readonly' => true ), 'provider' => 'wordpress', 'category' => 'site' ),
			'run' => function () {
				return array( 'name' => 'Test Site', 'version' => '7.1' );
			},
		);
	}

	private function ai_ability( $key, $answer ) {
		return array(
			'raw' => array(
				'annotations'   => array( 'destructive' => false ),
				'provider'      => 'wordpress',
				'category'      => 'ai',
				'output_schema' => array( 'type' => 'object', 'properties' => array( $key => array( 'type' => 'string' ) ) ),
			),
			'run' => function () use ( $answer ) {
				return $answer;
			},
		);
	}

	// ---------------------------------------------------------------------
	// Definitions
	// ---------------------------------------------------------------------

	public function test_the_group_defines_its_cases_that_need_no_ai_provider_and_only_workflow_cases_create_data() {
		$this->register_abilities( array( 'core/get-site-info' => $this->site_info() ) );

		$definitions = $this->cases()->get_definitions();

		$this->assertSame(
			array( 'ability_discovery', 'ability_invoke_core', 'ability_invoke_ai', 'ability_destructive_gate', 'workflow_two_step', 'workflow_retry_backoff', 'workflow_cancel' ),
			wp_list_pluck( $definitions, 'id' )
		);

		foreach ( $definitions as $definition ) {
			$this->assertSame( 'abilities', $definition['group'] );
			$this->assertSame( 0 === strpos( $definition['id'], 'workflow_' ), $definition['creates'], $definition['id'] );
			$this->assertFalse( $definition['needs_ai'], $definition['id'] );
			$this->assertNotSame( '', $definition['label'] );
			$this->assertNotSame( '', $definition['description'] );
		}
	}

	public function test_has_case_recognises_only_the_groups_cases() {
		$this->register_abilities( array( 'core/get-site-info' => $this->site_info() ) );

		$this->assertTrue( $this->cases()->has_case( 'ability_discovery' ) );
		$this->assertFalse( $this->cases()->has_case( 'generate_title' ) );
		$this->assertFalse( $this->cases()->has_case( 'ability_made_up' ) );
	}

	public function test_an_unknown_case_fails_cleanly() {
		$this->register_abilities( array( 'core/get-site-info' => $this->site_info() ) );

		$result = $this->cases()->run( 'ability_made_up' );

		$this->assertSame( 'failed', $result['status'] );
		$this->assertSame( 0, $result['ability_calls'] );
	}

	// ---------------------------------------------------------------------
	// Discovery
	// ---------------------------------------------------------------------

	public function test_discovery_reports_counts_by_provider_category_and_approval() {
		$this->register_abilities(
			array(
				'core/get-site-info' => $this->site_info(),
				'ai/title-generation' => $this->ai_ability( 'title', array( 'title' => 'X' ) ),
				'vendor/unannotated' => array( 'raw' => array( 'provider' => 'vendor' ), 'run' => function () { return array(); } ),
				'vendor/destructive' => array( 'raw' => array( 'provider' => 'vendor', 'annotations' => array( 'destructive' => true ) ), 'run' => function () { return array(); } ),
			)
		);

		$result = $this->cases()->run( 'ability_discovery' );

		$this->assertSame( 'passed', $result['status'] );
		$this->assertSame( 4, $result['plugin_value']['abilities'] );
		$this->assertSame( array( 'vendor' => 2, 'wordpress' => 2 ), $result['plugin_value']['by_provider'] );
		$this->assertSame( 2, $result['plugin_value']['require_approval'] );
		$this->assertSame( 1, $result['plugin_value']['no_annotation'] );
		$this->assertSame( 0, $result['plugin_value']['unavailable'] );
		$this->assertCount( 4, $result['ai_value'] );
		$this->assertSame( 0, $result['ability_calls'], 'Discovery invokes nothing.' );
		$this->assertStringContainsString( '4 abilities', $result['summary'] );
	}

	public function test_discovery_fails_when_nothing_is_registered() {
		$this->register_abilities( array() );

		$result = $this->cases()->run( 'ability_discovery' );

		$this->assertSame( 'failed', $result['status'] );
		$this->assertNotSame( '', $result['error'] );
	}

	// ---------------------------------------------------------------------
	// Core invocation
	// ---------------------------------------------------------------------

	public function test_core_invocation_calls_site_info_twenty_times_and_reports_latency() {
		$this->register_abilities(
			array(
				'core/get-site-info'        => $this->site_info(),
				'core/get-environment-info' => array(
					'raw' => array( 'annotations' => array( 'readonly' => true ) ),
					'run' => function () {
						return array( 'php' => '8.2', 'db' => 'mariadb' );
					},
				),
			)
		);

		$result = $this->cases()->run( 'ability_invoke_core' );

		$this->assertSame( 'passed', $result['status'] );
		$this->assertCount( AIPS_Stress_Test_Ability_Cases::CORE_INVOCATIONS + 1, $this->calls );
		$this->assertSame( AIPS_Stress_Test_Ability_Cases::CORE_INVOCATIONS + 1, $result['ability_calls'] );
		$latency = $result['plugin_value']['latency'];
		$this->assertSame( 20, $latency['calls'] );
		$this->assertSame( 0, $latency['errors'] );
		$this->assertLessThanOrEqual( $latency['max_ms'], $latency['p95_ms'] );
		$this->assertLessThanOrEqual( $latency['p95_ms'], $latency['p50_ms'] );
		$this->assertLessThanOrEqual( $latency['p50_ms'], $latency['min_ms'] );
		$this->assertSame( array( 'ok' => true, 'fields' => array( 'php', 'db' ) ), $result['plugin_value']['environment'] );
		$this->assertSame( array( 'name' => 'Test Site', 'version' => '7.1' ), $result['ai_value'] );
	}

	public function test_core_invocation_fails_and_counts_error_codes_when_calls_fail() {
		$this->register_abilities(
			array(
				'core/get-site-info' => array(
					'raw' => array( 'annotations' => array( 'readonly' => true ) ),
					'run' => function () {
						return new WP_Error( 'ability_invalid_permissions', 'Nope.' );
					},
				),
			)
		);

		$result = $this->cases()->run( 'ability_invoke_core' );

		$this->assertSame( 'failed', $result['status'] );
		$this->assertSame( array( 'ability_invalid_permissions' => 20 ), $result['plugin_value']['error_codes'] );
		$this->assertStringContainsString( '20 of 20', $result['error'] );
	}

	public function test_core_invocation_reports_a_forbidden_environment_read_without_failing() {
		$this->register_abilities(
			array(
				'core/get-site-info'        => $this->site_info(),
				'core/get-environment-info' => array(
					'raw' => array( 'annotations' => array( 'readonly' => true ) ),
					'run' => function () {
						return new WP_Error( 'ability_invalid_permissions', 'Admins only.' );
					},
				),
			)
		);

		$result = $this->cases()->run( 'ability_invoke_core' );

		$this->assertSame( 'passed', $result['status'] );
		$this->assertSame( array( 'error' => 'ability_invalid_permissions' ), $result['plugin_value']['environment'] );
	}

	public function test_core_invocation_is_skipped_when_site_info_is_not_registered() {
		$this->register_abilities( array( 'vendor/other' => array( 'run' => function () { return array(); } ) ) );

		$result = $this->cases()->run( 'ability_invoke_core' );

		$this->assertSame( 'passed', $result['status'] );
		$this->assertStringStartsWith( 'Skipped', $result['summary'] );
		$this->assertTrue( $result['artifacts']['skipped'] );
		$this->assertSame( 0, $result['ability_calls'] );
	}

	// ---------------------------------------------------------------------
	// AI invocation
	// ---------------------------------------------------------------------

	public function test_ai_invocation_calls_each_installed_ai_ability_once() {
		$this->register_abilities(
			array(
				'ai/title-generation' => $this->ai_ability( 'title', array( 'title' => 'A Title' ) ),
				'ai/summarization'    => $this->ai_ability( 'content', array( 'content' => 'A summary.' ) ),
			)
		);

		$result = $this->cases()->run( 'ability_invoke_ai' );

		$this->assertSame( 'passed', $result['status'] );
		$this->assertSame( array( 'ai/title-generation', 'ai/summarization' ), $this->calls );
		$this->assertSame( 2, $result['ability_calls'] );
		$this->assertSame( 'ok', $result['plugin_value']['ai/title-generation']['status'] );
		$this->assertSame( array( 'title' => 'A Title' ), $result['ai_value']['ai/title-generation'] );
	}

	public function test_ai_invocation_with_only_one_ability_installed_still_passes() {
		$this->register_abilities( array( 'ai/title-generation' => $this->ai_ability( 'title', array( 'title' => 'A Title' ) ) ) );

		$result = $this->cases()->run( 'ability_invoke_ai' );

		$this->assertSame( 'passed', $result['status'] );
		$this->assertSame( 'not_installed', $result['plugin_value']['ai/summarization']['status'] );
		$this->assertSame( 1, $result['ability_calls'] );
	}

	public function test_ai_invocation_is_skipped_when_no_ai_ability_is_installed() {
		$this->register_abilities( array( 'core/get-site-info' => $this->site_info() ) );

		$result = $this->cases()->run( 'ability_invoke_ai' );

		$this->assertSame( 'passed', $result['status'] );
		$this->assertStringStartsWith( 'Skipped', $result['summary'] );
		$this->assertSame( 0, $result['ability_calls'] );
	}

	public function test_ai_invocation_fails_when_an_ability_errors() {
		$this->register_abilities(
			array(
				'ai/title-generation' => array(
					'raw' => array( 'annotations' => array( 'destructive' => false ) ),
					'run' => function () {
						return new WP_Error( 'no_provider', 'No provider configured.' );
					},
				),
			)
		);

		$result = $this->cases()->run( 'ability_invoke_ai' );

		$this->assertSame( 'failed', $result['status'] );
		$this->assertStringContainsString( 'No provider configured.', $result['error'] );
		$this->assertSame( 'no_provider', $result['plugin_value']['ai/title-generation']['code'] );
	}

	public function test_ai_invocation_fails_when_the_answer_misses_a_declared_output_field() {
		$this->register_abilities( array( 'ai/title-generation' => $this->ai_ability( 'title', array( 'unexpected' => 'x' ) ) ) );

		$result = $this->cases()->run( 'ability_invoke_ai' );

		$this->assertSame( 'failed', $result['status'] );
		$this->assertSame( 'unexpected_shape', $result['plugin_value']['ai/title-generation']['status'] );
		$this->assertSame( array( 'title' ), $result['plugin_value']['ai/title-generation']['missing'] );
	}

	// ---------------------------------------------------------------------
	// Destructive gate
	// ---------------------------------------------------------------------

	public function test_the_gate_case_checks_every_annotation_shape_with_and_without_approval() {
		$this->register_abilities( array( 'core/get-site-info' => $this->site_info() ) );

		$result = $this->cases()->run( 'ability_destructive_gate' );

		$this->assertSame( 'passed', $result['status'] );
		$this->assertCount( 12, $result['plugin_value'] );
		$this->assertSame( 0, $result['ability_calls'], 'The gate case invokes nothing.' );
		$this->assertCount( 0, $this->calls );

		$blocked = array();
		foreach ( $result['plugin_value'] as $row ) {
			if ( $row['blocked'] ) {
				$blocked[] = $row['ability'] . ( 'none' === $row['setting'] ? '' : '+' . $row['setting'] );
				$this->assertSame( 'ability_workflow_destructive_forbidden', $row['error_code'] );
			}
		}
		sort( $blocked );
		$this->assertSame( array( 'destructive', 'destructive+approved', 'unannotated' ), $blocked, 'Unannotated stand-ins are blocked until allowed or approved; a destructive one is blocked unless destructive abilities are allowed, whatever the approved list says.' );
	}

	// ---------------------------------------------------------------------
	// Workflow cases
	// ---------------------------------------------------------------------

	private function provider_filter_left_behind() {
		global $wp_filter;

		return isset( $wp_filter['aips_ability_provider'] ) && isset( $wp_filter['aips_ability_provider']->callbacks[999] );
	}

	private function assert_nothing_left_behind( $result ) {
		$this->assertSame( 0, $this->cases()->count_leftovers(), 'The workflow must be removed.' );
		$this->assertFalse( $this->provider_filter_left_behind(), 'The fixture provider must be removed.' );

		if ( ! empty( $result['plugin_value']['history']['id'] ) ) {
			$this->assertNull( AIPS_History_Repository::instance()->get_by_id( $result['plugin_value']['history']['id'] ), 'The History record must be removed.' );
		}
	}

	public function test_the_two_step_case_passes_with_typed_values_one_history_record_and_cleans_up() {
		$this->register_abilities( array( 'core/get-site-info' => $this->site_info() ) );

		$result = $this->cases()->run( 'workflow_two_step' );

		$this->assertSame( 'passed', $result['status'], (string) ( $result['error'] ?? '' ) );
		$this->assertSame( 2, $result['ability_calls'] );
		$this->assertSame( 'completed', $result['plugin_value']['run_status'] );
		$this->assertSame( 3, $result['plugin_value']['received']['count'], 'The number keeps its type.' );
		$this->assertSame( 'completed', $result['plugin_value']['history']['status'] );
		$this->assert_nothing_left_behind( $result );
	}

	public function test_the_retry_case_parks_waits_retries_once_and_cleans_up() {
		$this->register_abilities( array( 'core/get-site-info' => $this->site_info() ) );

		$started = microtime( true );
		$result  = $this->cases()->run( 'workflow_retry_backoff' );

		$this->assertSame( 'passed', $result['status'], (string) ( $result['error'] ?? '' ) );
		$this->assertSame( 2, $result['plugin_value']['attempts'] );
		$this->assertSame( 2, $result['ability_calls'], 'One failed attempt and one retry.' );
		$this->assertGreaterThanOrEqual( 0.9, microtime( true ) - $started, 'The retry waits out its one second backoff.' );
		$this->assertSame( 'completed', $result['plugin_value']['history']['status'] );
		$this->assert_nothing_left_behind( $result );
	}

	public function test_the_cancel_case_stops_after_the_first_step_and_terminates_history() {
		$this->register_abilities( array( 'core/get-site-info' => $this->site_info() ) );

		$result = $this->cases()->run( 'workflow_cancel' );

		$this->assertSame( 'passed', $result['status'], (string) ( $result['error'] ?? '' ) );
		$this->assertSame( 1, $result['ability_calls'], 'Only the first step ran.' );
		$this->assertSame( 'cancelled', $result['plugin_value']['run_status'] );
		$this->assertSame( array( 'first' ), $result['plugin_value']['step_runs'] );
		$this->assertSame( 'terminated', $result['plugin_value']['history']['status'] );
		$this->assert_nothing_left_behind( $result );
	}

	public function test_workflow_cases_are_skipped_not_failed_when_workflows_are_disabled() {
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 0 );
		$this->register_abilities( array( 'core/get-site-info' => $this->site_info() ) );

		foreach ( array( 'workflow_two_step', 'workflow_retry_backoff', 'workflow_cancel' ) as $case_id ) {
			$result = $this->cases()->run( $case_id );

			$this->assertSame( 'passed', $result['status'], $case_id );
			$this->assertStringContainsString( 'Skipped', $result['summary'], $case_id );
			$this->assertTrue( $result['artifacts']['skipped'], $case_id );
			$this->assertSame( 0, $result['ability_calls'], $case_id );
		}

		$this->assertSame( 0, $this->cases()->count_leftovers() );
	}

	public function test_cleanup_removes_leftover_stress_workflows_with_runs_and_history_but_nothing_else() {
		$repository = AIPS_Ability_Workflow_Repository::instance();
		$stress_id  = $repository->create_workflow( array( 'name' => AIPS_Stress_Test_Ability_Cases::WORKFLOW_PREFIX . 'left behind' ) );
		$normal_id  = $repository->create_workflow( array( 'name' => 'A real workflow' ) );
		$run_id     = $repository->create_run( $stress_id, 1, array( 'type' => 'stress_test' ) );
		$history_id = AIPS_History_Service::instance()->create( 'ability_workflow_run', array( 'source' => 'test' ) )->get_id();
		$repository->set_run_history_id( $run_id, $history_id );

		$this->assertSame( 1, $this->cases()->count_leftovers() );

		$this->assertSame( 1, $this->cases()->cleanup() );

		$this->assertNull( $repository->get_workflow( $stress_id ) );
		$this->assertNull( $repository->get_run( $run_id ) );
		$this->assertNull( AIPS_History_Repository::instance()->get_by_id( $history_id ) );
		$this->assertNotNull( $repository->get_workflow( $normal_id ), 'Only stress workflows are removed.' );
		$this->assertSame( 0, $this->cases()->count_leftovers() );

		$repository->delete_workflow( $normal_id );
	}

	public function test_delete_workflow_with_runs_removes_step_runs_and_reports_history_ids() {
		$repository  = AIPS_Ability_Workflow_Repository::instance();
		$workflow_id = $repository->create_workflow( array( 'name' => 'Disposable' ) );
		$repository->save_steps( $workflow_id, array( array( 'step_key' => 'a', 'ability_name' => 'vendor/one' ) ) );
		$run_id = $repository->create_run( $workflow_id, 1 );
		$repository->set_run_history_id( $run_id, 987654 );
		$step = $repository->get_steps( $workflow_id )[0];
		$repository->create_step_run( $run_id, $workflow_id, $step, array() );

		$removed = $repository->delete_workflow_with_runs( $workflow_id );

		$this->assertSame( 1, $removed['runs'] );
		$this->assertSame( array( 987654 ), $removed['history_ids'] );
		$this->assertNull( $repository->get_workflow( $workflow_id ) );
		$this->assertNull( $repository->get_run( $run_id ) );
		$this->assertSame( array(), $repository->get_step_runs( $run_id ) );
	}

	// ---------------------------------------------------------------------
	// Integration with the stress test service
	// ---------------------------------------------------------------------

	private function service() {
		return new AIPS_Stress_Test_Service( new AIPS_Test_Stress_No_AI_Service(), new AIPS_Test_Stress_Logger_For_Abilities() );
	}

	public function test_the_service_lists_the_ability_cases_in_their_own_group() {
		$this->register_abilities( array( 'core/get-site-info' => $this->site_info() ) );
		$service = $this->service();

		$ids = wp_list_pluck( $service->get_cases(), 'id' );

		foreach ( array( 'ability_discovery', 'ability_invoke_core', 'ability_invoke_ai', 'ability_destructive_gate', 'workflow_two_step', 'workflow_retry_backoff', 'workflow_cancel' ) as $id ) {
			$this->assertContains( $id, $ids );
			$this->assertTrue( $service->has_case( $id ) );
		}

		$groups = array_unique( wp_list_pluck( $service->get_cases(), 'group' ) );
		$this->assertContains( 'abilities', $groups );
		$this->assertContains( 'components', $groups, 'The existing groups are untouched.' );
	}

	public function test_an_ability_case_runs_through_the_service_without_an_ai_provider() {
		$this->register_abilities( array( 'core/get-site-info' => $this->site_info() ) );

		$result = $this->service()->run( 'ability_discovery' );

		$this->assertSame( 'ability_discovery', $result['case'] );
		$this->assertSame( 'abilities', $result['group'] );
		$this->assertSame( 'passed', $result['status'], (string) $result['error'] );
		$this->assertArrayHasKey( 'duration_ms', $result );
		$this->assertSame( array(), $result['ai_calls'], 'The AI call log is empty: no AIPS AI call was made.' );
		$this->assertSame( 0, $result['ability_calls'] );
	}

	public function test_the_envelope_reports_ability_calls() {
		$this->register_abilities( array( 'core/get-site-info' => $this->site_info() ) );

		$result = $this->service()->run( 'ability_invoke_core' );

		$this->assertSame( 'passed', $result['status'], (string) $result['error'] );
		$this->assertSame( 20, $result['ability_calls'] );
	}

	public function test_existing_ai_cases_still_require_an_ai_provider() {
		$this->register_abilities( array( 'core/get-site-info' => $this->site_info() ) );

		$result = $this->service()->run( 'generate_title' );

		$this->assertSame( 'failed', $result['status'] );
		$this->assertStringContainsString( 'No AI provider', $result['error'] );
	}

	public function test_an_exception_in_an_ability_case_is_reported_as_a_failed_row() {
		$this->register_abilities(
			array(
				'core/get-site-info' => array(
					'raw' => array( 'annotations' => array( 'readonly' => true ) ),
					'run' => function () {
						throw new RuntimeException( 'provider exploded' );
					},
				),
			)
		);

		$result = $this->service()->run( 'ability_invoke_core' );

		$this->assertSame( 'failed', $result['status'] );
	}
}

class AIPS_Test_Stress_Logger_For_Abilities implements AIPS_Logger_Interface {
	public function log( $message, $level = 'info', $context = array() ) {}
	public function addSeparator( $text ) {}

	public function test_the_service_counts_and_cleans_leftover_stress_workflows() {
		$this->register_abilities( array( 'core/get-site-info' => $this->site_info() ) );
		AIPS_Ability_Workflow_Repository::instance()->create_workflow( array( 'name' => AIPS_Stress_Test_Ability_Cases::WORKFLOW_PREFIX . 'left behind' ) );
		$service = $this->service();

		$this->assertSame( 1, $service->count_test_data()['workflows'] );

		$deleted = $service->cleanup_test_data();

		$this->assertSame( 1, $deleted['workflows'] );
		$this->assertSame( 0, $service->count_test_data()['workflows'] );
	}
}
