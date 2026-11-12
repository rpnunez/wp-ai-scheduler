<?php
/**
 * Guard tests for the scheduling boundary lint detector.
 *
 * These lock in the two detection forms that previously let scheduling calls
 * escape the guard: the explicit-global "\wp_schedule_single_event(" form and
 * dynamic calls by name such as call_user_func('as_schedule_single_action').
 *
 * The helpers under test are pure, so requiring the tool file defines them
 * without running the directory scan (see the direct-run guard in that file).
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Scheduling_Boundary_Check extends WP_UnitTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once dirname( __DIR__ ) . '/tools/check-scheduling-boundary.php';
	}

	/**
	 * A plain global call is flagged.
	 */
	public function test_flags_direct_global_call() {
		$this->assertSame(
			array( 'wp_schedule_single_event' ),
			aips_scheduling_boundary_scan( 'wp_schedule_single_event( $ts, $hook );' )
		);
	}

	/**
	 * The explicit-global form with a leading backslash is flagged — it calls
	 * the same global function and must not slip through.
	 */
	public function test_flags_leading_backslash_global_call() {
		$this->assertSame(
			array( 'wp_schedule_single_event' ),
			aips_scheduling_boundary_scan( 'return \wp_schedule_single_event( $ts, $hook );' )
		);
	}

	/**
	 * A dynamic call by name (call_user_func with a quoted name) is flagged.
	 */
	public function test_flags_call_user_func_string_form() {
		$this->assertSame(
			array( 'as_schedule_single_action' ),
			aips_scheduling_boundary_scan( "call_user_func( 'as_schedule_single_action', \$ts );" )
		);
	}

	/**
	 * A callable array referencing the name as a string is flagged.
	 */
	public function test_flags_callable_array_string_form() {
		$this->assertSame(
			array( 'wp_next_scheduled' ),
			aips_scheduling_boundary_scan( '$cb = array( $this, "wp_next_scheduled" );' )
		);
	}

	/**
	 * Method and static calls that merely share the name are NOT flagged.
	 */
	public function test_ignores_method_and_static_calls() {
		$this->assertSame(
			array(),
			aips_scheduling_boundary_scan(
				'$this->wp_next_scheduled( $h ); Foo::as_unschedule_action( $h );'
			)
		);
	}

	/**
	 * A genuinely namespaced call (backslash preceded by an identifier) is NOT
	 * flagged, since it resolves to a different function.
	 */
	public function test_ignores_namespaced_call() {
		$this->assertSame(
			array(),
			aips_scheduling_boundary_scan( 'Acme\Cron\wp_next_scheduled( $h );' )
		);
	}

	/**
	 * A longer identifier that merely ends in a backend name is NOT flagged.
	 */
	public function test_ignores_identifier_suffix_collision() {
		$this->assertSame(
			array(),
			aips_scheduling_boundary_scan( 'my_wp_next_scheduled( $h );' )
		);
	}

	/**
	 * A bare mention in a comment (no call, no quotes) is NOT flagged.
	 */
	public function test_ignores_comment_mention() {
		$this->assertSame(
			array(),
			aips_scheduling_boundary_scan( '// see wp_next_scheduled for details' )
		);
	}
}
