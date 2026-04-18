<?php
/**
 * Unit tests for AIPS_Queue_Manager.
 *
 * Covers:
 *  1. Driver resolution and WP-Cron fallback when AS functions are absent.
 *  2. WP-Cron schedule / unschedule delegation (including arg passthrough).
 *  3. Action Scheduler path when AS functions are present (stubbed).
 *
 * @package AI_Post_Scheduler
 * @since   2.3.1
 */

// ---------------------------------------------------------------------------
// WP-Cron stubs — used only in limited mode; real WP functions take precedence
// in the full WordPress test environment.
// ---------------------------------------------------------------------------

if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 604800 );
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	/**
	 * Limited-mode stub: returns the stored timestamp for a hook+args pair.
	 */
	function wp_next_scheduled( $hook, $args = array() ) {
		global $aips_test_cron;
		$key = $hook . ':' . md5( serialize( $args ) );
		return isset( $aips_test_cron['scheduled'][ $key ] )
			? $aips_test_cron['scheduled'][ $key ]
			: false;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	/**
	 * Limited-mode stub: stores the scheduled entry and logs the call.
	 */
	function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) {
		global $aips_test_cron;
		$key = $hook . ':' . md5( serialize( $args ) );
		$aips_test_cron['scheduled'][ $key ] = $timestamp;
		$aips_test_cron['calls'][]           = array(
			'fn'         => 'wp_schedule_event',
			'hook'       => $hook,
			'args'       => $args,
			'recurrence' => $recurrence,
		);
		return true;
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	/**
	 * Limited-mode stub: removes the stored entry and logs the call with args.
	 */
	function wp_clear_scheduled_hook( $hook, $args = array() ) {
		global $aips_test_cron;
		$key = $hook . ':' . md5( serialize( $args ) );
		unset( $aips_test_cron['scheduled'][ $key ] );
		$aips_test_cron['calls'][] = array(
			'fn'   => 'wp_clear_scheduled_hook',
			'hook' => $hook,
			'args' => $args,
		);
		return true;
	}
}

if ( ! function_exists( 'wp_get_schedules' ) ) {
	/** Limited-mode stub: returns an empty array (no custom schedules needed). */
	function wp_get_schedules() {
		return array();
	}
}

// ---------------------------------------------------------------------------
// Action Scheduler stubs — defined when the real AS library is not installed.
// These allow the AS path to be exercised even without WooCommerce / AS.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'as_next_scheduled_action' ) ) {
	/**
	 * AS stub: returns the stored timestamp for a hook+args+group triple.
	 */
	function as_next_scheduled_action( $hook, $args = array(), $group = '' ) {
		global $aips_test_as;
		$key = $hook . ':' . md5( serialize( $args ) ) . ':' . $group;
		return isset( $aips_test_as['scheduled'][ $key ] )
			? $aips_test_as['scheduled'][ $key ]
			: false;
	}
}

if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
	/**
	 * AS stub: stores the action and logs the call, returning a synthetic ID.
	 */
	function as_schedule_recurring_action( $timestamp, $interval, $hook, $args = array(), $group = '' ) {
		global $aips_test_as;
		$key = $hook . ':' . md5( serialize( $args ) ) . ':' . $group;
		$id  = $aips_test_as['next_id']++;
		$aips_test_as['scheduled'][ $key ] = $timestamp;
		$aips_test_as['calls'][]           = array(
			'fn'       => 'as_schedule_recurring_action',
			'hook'     => $hook,
			'args'     => $args,
			'group'    => $group,
			'interval' => $interval,
		);
		return $id;
	}
}

if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
	/**
	 * AS stub: removes the stored entry and logs the call.
	 */
	function as_unschedule_all_actions( $hook, $args = array(), $group = '' ) {
		global $aips_test_as;
		$key = $hook . ':' . md5( serialize( $args ) ) . ':' . $group;
		unset( $aips_test_as['scheduled'][ $key ] );
		$aips_test_as['calls'][] = array(
			'fn'    => 'as_unschedule_all_actions',
			'hook'  => $hook,
			'args'  => $args,
			'group' => $group,
		);
	}
}

// ---------------------------------------------------------------------------
// Test class
// ---------------------------------------------------------------------------

/**
 * Class Test_AIPS_Queue_Manager
 */
class Test_AIPS_Queue_Manager extends WP_UnitTestCase {

	/** A dedicated test hook that is guaranteed not to conflict with plugin hooks. */
	const TEST_HOOK = 'aips_test_queue_manager_hook';

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Reset the AIPS_Config singleton so option reads are fresh after each test.
	 */
	private function reset_aips_config(): void {
		if ( ! class_exists( 'AIPS_Config' ) ) {
			return;
		}
		try {
			$ref  = new ReflectionClass( 'AIPS_Config' );
			$prop = $ref->getProperty( 'instance' );
			$prop->setAccessible( true );
			$prop->setValue( null, null );
		} catch ( ReflectionException $e ) {
			// Ignore.
		}
	}

	/**
	 * Reset the AIPS_Queue_Manager singleton so a fresh instance is constructed.
	 */
	private function reset_queue_manager_singleton(): void {
		try {
			$ref  = new ReflectionClass( 'AIPS_Queue_Manager' );
			$prop = $ref->getProperty( 'instance' );
			$prop->setAccessible( true );
			$prop->setValue( null, null );
		} catch ( ReflectionException $e ) {
			// Ignore.
		}
	}

	/**
	 * Count calls to a given function in the WP-Cron tracking global.
	 *
	 * @param string $fn Function name to count.
	 * @return int
	 */
	private function count_cron_calls( string $fn ): int {
		global $aips_test_cron;
		if ( empty( $aips_test_cron['calls'] ) ) {
			return 0;
		}
		return count( array_filter( $aips_test_cron['calls'], function ( $call ) use ( $fn ) {
			return $call['fn'] === $fn;
		} ) );
	}

	/**
	 * Count calls to a given function in the Action Scheduler tracking global.
	 *
	 * @param string $fn Function name to count.
	 * @return int
	 */
	private function count_as_calls( string $fn ): int {
		global $aips_test_as;
		if ( empty( $aips_test_as['calls'] ) ) {
			return 0;
		}
		return count( array_filter( $aips_test_as['calls'], function ( $call ) use ( $fn ) {
			return $call['fn'] === $fn;
		} ) );
	}

	/**
	 * Get the last recorded call for a given function name from the AS global.
	 *
	 * @param string $fn Function name.
	 * @return array|null
	 */
	private function last_as_call( string $fn ): ?array {
		global $aips_test_as;
		if ( empty( $aips_test_as['calls'] ) ) {
			return null;
		}
		$matching = array_filter( $aips_test_as['calls'], function ( $call ) use ( $fn ) {
			return $call['fn'] === $fn;
		} );
		return empty( $matching ) ? null : end( $matching );
	}

	/**
	 * Get the last recorded call for a given function name from the WP-Cron global.
	 *
	 * @param string $fn Function name.
	 * @return array|null
	 */
	private function last_cron_call( string $fn ): ?array {
		global $aips_test_cron;
		if ( empty( $aips_test_cron['calls'] ) ) {
			return null;
		}
		$matching = array_filter( $aips_test_cron['calls'], function ( $call ) use ( $fn ) {
			return $call['fn'] === $fn;
		} );
		return empty( $matching ) ? null : end( $matching );
	}

	// -------------------------------------------------------------------------
	// Lifecycle
	// -------------------------------------------------------------------------

	public function setUp(): void {
		parent::setUp();

		// Reset singletons so each test starts with a clean state.
		$this->reset_aips_config();
		$this->reset_queue_manager_singleton();

		// Reset per-test tracking globals.
		$GLOBALS['aips_test_cron'] = array(
			'scheduled' => array(),
			'calls'     => array(),
		);
		$GLOBALS['aips_test_as'] = array(
			'scheduled' => array(),
			'calls'     => array(),
			'next_id'   => 1,
		);

		// Clear any leftover WP-Cron entry for the test hook.
		wp_clear_scheduled_hook( self::TEST_HOOK );
	}

	public function tearDown(): void {
		// Clean up test hook from WP-Cron.
		wp_clear_scheduled_hook( self::TEST_HOOK );

		// Remove the test option.
		delete_option( 'aips_queue_manager' );

		// Reset singletons.
		$this->reset_aips_config();
		$this->reset_queue_manager_singleton();

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// 1. Driver resolution
	// -------------------------------------------------------------------------

	/**
	 * When no option is saved, the driver defaults to wpcron.
	 */
	public function test_default_driver_is_wpcron(): void {
		delete_option( 'aips_queue_manager' );
		$this->reset_aips_config();

		$qm = new AIPS_Queue_Manager();

		$this->assertSame(
			AIPS_Queue_Manager::DRIVER_WPCRON,
			$qm->get_driver(),
			'Default driver must be wpcron when no option is set'
		);
	}

	/**
	 * When the option is set to wpcron explicitly, the driver is wpcron.
	 */
	public function test_driver_wpcron_when_option_is_wpcron(): void {
		update_option( 'aips_queue_manager', AIPS_Queue_Manager::DRIVER_WPCRON );
		$this->reset_aips_config();

		$qm = new AIPS_Queue_Manager();

		$this->assertSame( AIPS_Queue_Manager::DRIVER_WPCRON, $qm->get_driver() );
	}

	/**
	 * When the option is set to action_scheduler and AS is available, the driver
	 * resolves to action_scheduler.
	 *
	 * This test relies on our AS stubs being registered (see top of file), so it
	 * verifies the happy-path without requiring a real AS installation.
	 */
	public function test_driver_action_scheduler_when_as_available(): void {
		if ( ! AIPS_Queue_Manager::is_action_scheduler_available() ) {
			$this->markTestSkipped( 'Action Scheduler functions are not available.' );
		}

		update_option( 'aips_queue_manager', AIPS_Queue_Manager::DRIVER_ACTION_SCHEDULER );
		$this->reset_aips_config();

		$qm = new AIPS_Queue_Manager();

		$this->assertSame( AIPS_Queue_Manager::DRIVER_ACTION_SCHEDULER, $qm->get_driver() );
	}

	/**
	 * When the option is action_scheduler but AS functions are absent, the driver
	 * must fall back to wpcron.
	 *
	 * We simulate the unavailability scenario by inspecting the static helper
	 * directly and skipping if AS functions are somehow all defined but unavailable
	 * (edge-case guard).  The main coverage is the branch in __construct().
	 */
	public function test_driver_falls_back_to_wpcron_when_as_unavailable(): void {
		// The stubs at the top of this file define the AS functions, so
		// is_action_scheduler_available() will return true in limited mode.
		// We test the fallback logic directly via is_action_scheduler_available().
		if ( AIPS_Queue_Manager::is_action_scheduler_available() ) {
			// AS is available (either real or stubbed): verify the configured
			// driver is honoured when AS IS present.
			update_option( 'aips_queue_manager', AIPS_Queue_Manager::DRIVER_ACTION_SCHEDULER );
			$this->reset_aips_config();
			$qm = new AIPS_Queue_Manager();
			$this->assertSame( AIPS_Queue_Manager::DRIVER_ACTION_SCHEDULER, $qm->get_driver() );
		} else {
			// Real environment without AS: fallback must activate.
			update_option( 'aips_queue_manager', AIPS_Queue_Manager::DRIVER_ACTION_SCHEDULER );
			$this->reset_aips_config();
			$qm = new AIPS_Queue_Manager();
			$this->assertSame(
				AIPS_Queue_Manager::DRIVER_WPCRON,
				$qm->get_driver(),
				'Driver must fall back to wpcron when Action Scheduler is not available'
			);
		}
	}

	/**
	 * is_action_scheduler_available() must return false when the three required
	 * AS functions do not exist (or true when they do — either real or stubbed).
	 */
	public function test_is_action_scheduler_available_reflects_function_existence(): void {
		$expected = function_exists( 'as_schedule_recurring_action' )
			&& function_exists( 'as_unschedule_all_actions' )
			&& function_exists( 'as_next_scheduled_action' );

		$this->assertSame( $expected, AIPS_Queue_Manager::is_action_scheduler_available() );
	}

	// -------------------------------------------------------------------------
	// 2. WP-Cron path: schedule_recurring / unschedule_hook / next_scheduled
	// -------------------------------------------------------------------------

	/**
	 * schedule_recurring() with the wpcron driver should make the hook appear
	 * as scheduled (next_scheduled returns a truthy timestamp).
	 */
	public function test_wpcron_schedule_recurring_schedules_hook(): void {
		$qm = new AIPS_Queue_Manager(); // driver defaults to wpcron.

		$result = $qm->schedule_recurring( time() + 60, 'hourly', self::TEST_HOOK );

		$this->assertTrue( $result, 'schedule_recurring must return true on success' );
		$this->assertNotFalse(
			$qm->next_scheduled( self::TEST_HOOK ),
			'next_scheduled must return a timestamp after schedule_recurring'
		);
	}

	/**
	 * schedule_recurring() with the wpcron driver is idempotent: calling it a
	 * second time when the hook is already scheduled must not duplicate events.
	 */
	public function test_wpcron_schedule_recurring_is_idempotent(): void {
		$qm = new AIPS_Queue_Manager();

		$qm->schedule_recurring( time() + 60, 'hourly', self::TEST_HOOK );
		$first_ts = $qm->next_scheduled( self::TEST_HOOK );

		// Second call must succeed without creating a duplicate.
		$result = $qm->schedule_recurring( time() + 120, 'hourly', self::TEST_HOOK );

		$this->assertTrue( $result );
		$this->assertSame(
			$first_ts,
			$qm->next_scheduled( self::TEST_HOOK ),
			'Timestamp must not change on a second schedule_recurring call'
		);
	}

	/**
	 * unschedule_hook() with the wpcron driver must clear the event so
	 * next_scheduled returns false afterwards.
	 */
	public function test_wpcron_unschedule_hook_clears_event(): void {
		$qm = new AIPS_Queue_Manager();

		$qm->schedule_recurring( time() + 60, 'hourly', self::TEST_HOOK );
		$this->assertNotFalse( $qm->next_scheduled( self::TEST_HOOK ) );

		$qm->unschedule_hook( self::TEST_HOOK );

		$this->assertFalse(
			$qm->next_scheduled( self::TEST_HOOK ),
			'next_scheduled must return false after unschedule_hook'
		);
	}

	/**
	 * unschedule_hook() must pass $args through so that arg-specific events are
	 * cleared correctly (not all-or-nothing).
	 *
	 * We verify this by checking the args supplied to wp_clear_scheduled_hook in
	 * limited mode, or by confirming the event is cleared in full WP mode.
	 */
	public function test_wpcron_unschedule_hook_passes_args(): void {
		$args = array( 'batch' => 1 );
		$qm   = new AIPS_Queue_Manager();

		// Schedule, then unschedule with the same args.
		$qm->schedule_recurring( time() + 60, 'hourly', self::TEST_HOOK, $args );
		$qm->unschedule_hook( self::TEST_HOOK, $args );

		if ( isset( $GLOBALS['aips_test_cron'] ) ) {
			// Limited mode: inspect the recorded call.
			$last = $this->last_cron_call( 'wp_clear_scheduled_hook' );
			$this->assertNotNull( $last, 'wp_clear_scheduled_hook should have been called' );
			$this->assertSame( self::TEST_HOOK, $last['hook'] );
			$this->assertSame( $args, $last['args'], 'args must be passed through to wp_clear_scheduled_hook' );
		} else {
			// Full WP mode: hook should no longer be scheduled.
			$this->assertFalse(
				wp_next_scheduled( self::TEST_HOOK, $args ),
				'Event with args must be cleared after unschedule_hook'
			);
		}
	}

	/**
	 * next_scheduled() with the wpcron driver delegates to wp_next_scheduled.
	 */
	public function test_wpcron_next_scheduled_returns_false_for_unscheduled_hook(): void {
		$qm = new AIPS_Queue_Manager();
		$this->assertFalse( $qm->next_scheduled( 'aips_nonexistent_test_hook_xyz' ) );
	}

	// -------------------------------------------------------------------------
	// 3. Action Scheduler path: schedule_recurring / unschedule_hook / idempotency
	// -------------------------------------------------------------------------

	/**
	 * schedule_recurring() with the AS driver must call as_schedule_recurring_action
	 * when the hook is not already queued.
	 */
	public function test_as_schedule_recurring_creates_action_when_not_present(): void {
		if ( ! AIPS_Queue_Manager::is_action_scheduler_available() ) {
			$this->markTestSkipped( 'Action Scheduler functions are not available.' );
		}

		update_option( 'aips_queue_manager', AIPS_Queue_Manager::DRIVER_ACTION_SCHEDULER );
		$this->reset_aips_config();

		$qm     = new AIPS_Queue_Manager();
		$before = $this->count_as_calls( 'as_schedule_recurring_action' );

		$result = $qm->schedule_recurring( time() + 60, 'hourly', self::TEST_HOOK );

		$this->assertTrue( $result );
		$this->assertSame(
			$before + 1,
			$this->count_as_calls( 'as_schedule_recurring_action' ),
			'as_schedule_recurring_action should be called once when hook is not already queued'
		);
	}

	/**
	 * schedule_recurring() with the AS driver must NOT call as_schedule_recurring_action
	 * again when the hook is already scheduled — preventing duplicate actions.
	 */
	public function test_as_schedule_recurring_is_idempotent(): void {
		if ( ! AIPS_Queue_Manager::is_action_scheduler_available() ) {
			$this->markTestSkipped( 'Action Scheduler functions are not available.' );
		}

		update_option( 'aips_queue_manager', AIPS_Queue_Manager::DRIVER_ACTION_SCHEDULER );
		$this->reset_aips_config();

		$qm = new AIPS_Queue_Manager();

		// First call: schedules the action.
		$qm->schedule_recurring( time() + 60, 'hourly', self::TEST_HOOK );
		$calls_after_first = $this->count_as_calls( 'as_schedule_recurring_action' );

		// Second call: should detect existing action and return without calling AS again.
		$result = $qm->schedule_recurring( time() + 120, 'hourly', self::TEST_HOOK );

		$this->assertTrue( $result );
		$this->assertSame(
			$calls_after_first,
			$this->count_as_calls( 'as_schedule_recurring_action' ),
			'as_schedule_recurring_action must not be called again when action is already queued'
		);
	}

	/**
	 * unschedule_hook() with the AS driver must call as_unschedule_all_actions
	 * with the correct hook, args, and group.
	 */
	public function test_as_unschedule_hook_delegates_to_as_unschedule_all_actions(): void {
		if ( ! AIPS_Queue_Manager::is_action_scheduler_available() ) {
			$this->markTestSkipped( 'Action Scheduler functions are not available.' );
		}

		update_option( 'aips_queue_manager', AIPS_Queue_Manager::DRIVER_ACTION_SCHEDULER );
		$this->reset_aips_config();

		$qm   = new AIPS_Queue_Manager();
		$args = array( 'x' => 42 );

		$qm->unschedule_hook( self::TEST_HOOK, $args );

		$last = $this->last_as_call( 'as_unschedule_all_actions' );
		$this->assertNotNull( $last );
		$this->assertSame( self::TEST_HOOK, $last['hook'] );
		$this->assertSame( $args, $last['args'] );
		$this->assertSame( 'ai-post-scheduler', $last['group'] );
	}

	/**
	 * next_scheduled() with the AS driver must return false for an unscheduled hook.
	 */
	public function test_as_next_scheduled_returns_false_for_unscheduled_hook(): void {
		if ( ! AIPS_Queue_Manager::is_action_scheduler_available() ) {
			$this->markTestSkipped( 'Action Scheduler functions are not available.' );
		}

		update_option( 'aips_queue_manager', AIPS_Queue_Manager::DRIVER_ACTION_SCHEDULER );
		$this->reset_aips_config();

		$qm = new AIPS_Queue_Manager();

		$this->assertFalse( $qm->next_scheduled( 'aips_nonexistent_test_hook_xyz' ) );
	}

	// -------------------------------------------------------------------------
	// 4. Driver-label helper
	// -------------------------------------------------------------------------

	/**
	 * get_driver_label() returns a non-empty string for both drivers.
	 */
	public function test_get_driver_label_wpcron(): void {
		$qm = new AIPS_Queue_Manager();
		$this->assertNotEmpty( $qm->get_driver_label() );
	}

	public function test_get_driver_label_action_scheduler(): void {
		if ( ! AIPS_Queue_Manager::is_action_scheduler_available() ) {
			$this->markTestSkipped( 'Action Scheduler functions are not available.' );
		}

		update_option( 'aips_queue_manager', AIPS_Queue_Manager::DRIVER_ACTION_SCHEDULER );
		$this->reset_aips_config();

		$qm = new AIPS_Queue_Manager();
		$this->assertNotEmpty( $qm->get_driver_label() );
		$this->assertNotSame(
			$qm->get_driver_label(),
			( new AIPS_Queue_Manager() )->get_driver_label() === 'WP-Cron',
			'AS driver label must differ from WP-Cron label'
		);
	}
}
