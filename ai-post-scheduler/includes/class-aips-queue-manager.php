<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIPS_Queue_Manager
 *
 * Unified queue abstraction that routes scheduling operations to either
 * WP-Cron or Action Scheduler depending on the `aips_queue_manager` option.
 *
 * All activation, deactivation, boot-time registration, and flush operations
 * in the plugin should go through this class rather than calling wp_schedule_event(),
 * wp_clear_scheduled_hook(), wp_next_scheduled(), etc., directly.
 *
 * Supported drivers:
 *   - 'wpcron'           : Standard WordPress cron (default).
 *   - 'action_scheduler' : Action Scheduler library
 *                          (requires the ActionScheduler class to be available).
 *
 * @package AI_Post_Scheduler
 * @since   2.4.0
 */
class AIPS_Queue_Manager {

	/**
	 * Driver identifier for WP-Cron.
	 */
	const DRIVER_WPCRON = 'wpcron';

	/**
	 * Driver identifier for Action Scheduler.
	 */
	const DRIVER_ACTION_SCHEDULER = 'action_scheduler';

	/**
	 * @var self|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * @var string Currently active driver identifier.
	 */
	private $driver;

	/**
	 * Get the shared singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Resolves the active driver from plugin options.
	 */
	public function __construct() {
		$configured = AIPS_Config::get_instance()->get_option( 'aips_queue_manager', self::DRIVER_WPCRON );

		// Fall back to WP-Cron if Action Scheduler is configured but not available.
		if ( $configured === self::DRIVER_ACTION_SCHEDULER && ! self::is_action_scheduler_available() ) {
			$configured = self::DRIVER_WPCRON;
		}

		$this->driver = $configured;
	}

	/**
	 * Return the currently active driver identifier.
	 *
	 * @return string One of the DRIVER_* constants.
	 */
	public function get_driver(): string {
		return $this->driver;
	}

	/**
	 * Check whether Action Scheduler is installed and its API is available.
	 *
	 * @return bool
	 */
	public static function is_action_scheduler_available(): bool {
		return function_exists( 'as_schedule_recurring_action' )
			&& function_exists( 'as_unschedule_all_actions' )
			&& function_exists( 'as_next_scheduled_action' );
	}

	// -------------------------------------------------------------------------
	// Scheduling API
	// -------------------------------------------------------------------------

	/**
	 * Schedule a recurring event for the given hook if it is not already scheduled.
	 *
	 * @param int    $timestamp Unix timestamp for the first run.
	 * @param string $recurrence WP schedule name (e.g. 'hourly', 'daily') used
	 *                           for WP-Cron; also converted to seconds for AS.
	 * @param string $hook       The action hook to fire.
	 * @param array  $args       Optional arguments to pass to the hook.
	 * @return bool True on success, false on failure.
	 */
	public function schedule_recurring( int $timestamp, string $recurrence, string $hook, array $args = array() ): bool {
		if ( $this->driver === self::DRIVER_ACTION_SCHEDULER ) {
			return $this->as_schedule_recurring( $timestamp, $recurrence, $hook, $args );
		}

		return $this->wpcron_schedule_event( $timestamp, $recurrence, $hook, $args );
	}

	/**
	 * Unschedule all instances of the given hook.
	 *
	 * @param string $hook The action hook to clear.
	 * @param array  $args Optional arguments to match when clearing.
	 * @return void
	 */
	public function unschedule_hook( string $hook, array $args = array() ): void {
		if ( $this->driver === self::DRIVER_ACTION_SCHEDULER ) {
			$this->as_unschedule_all( $hook, $args );
		} else {
			wp_unschedule_hook( $hook );
		}
	}

	/**
	 * Get the next scheduled timestamp for the given hook.
	 *
	 * Returns false/null when the hook is not scheduled.
	 *
	 * @param string $hook The action hook to check.
	 * @param array  $args Optional arguments to match.
	 * @return int|bool|null Unix timestamp or false/null if not scheduled.
	 */
	public function next_scheduled( string $hook, array $args = array() ) {
		if ( $this->driver === self::DRIVER_ACTION_SCHEDULER ) {
			return $this->as_next_scheduled( $hook, $args );
		}

		return wp_next_scheduled( $hook, $args );
	}

	/**
	 * Register cron event handler callbacks for all plugin hooks.
	 *
	 * For Action Scheduler this is a no-op at the WordPress hook level because
	 * AS invokes hooks directly via do_action(); the plugin only needs to have
	 * add_action() calls in place, which boot_cron() already provides.
	 *
	 * For WP-Cron, also registers the cron_schedules filter so custom intervals
	 * are available.
	 *
	 * @return void
	 */
	public function register_cron_intervals(): void {
		if ( $this->driver === self::DRIVER_WPCRON ) {
			add_filter( 'cron_schedules', function ( $schedules ) {
				return AIPS_Scheduler::instance()->add_cron_intervals( $schedules );
			} );
		}
	}

	// -------------------------------------------------------------------------
	// Bulk scheduling helpers (activate / flush)
	// -------------------------------------------------------------------------

	/**
	 * Schedule all plugin cron events during plugin activation or after a flush.
	 *
	 * Clears any existing instances first to avoid duplicates, then registers
	 * each event exactly once using a 60-second offset so a burst of AI calls
	 * does not fire immediately after activation/flush.
	 *
	 * @param bool $clear_first Whether to unschedule existing events before rescheduling. Default true.
	 * @return array{scheduled: string[], failed: string[]} Results per hook label.
	 */
	public function schedule_all_events( bool $clear_first = true ): array {
		$cron_events = AI_Post_Scheduler::get_cron_events();
		$scheduled   = array();
		$failed      = array();

		foreach ( $cron_events as $hook => $config ) {
			$recurrence = isset( $config['schedule'] ) ? $config['schedule'] : 'hourly';
			$label      = isset( $config['label'] )    ? $config['label']    : $hook;

			if ( $clear_first ) {
				$this->unschedule_hook( $hook );
			}

			// Only schedule if not already present (handles clear_first = false).
			if ( $this->next_scheduled( $hook ) ) {
				$scheduled[] = $label;
				continue;
			}

			$success = $this->schedule_recurring( time() + 60, $recurrence, $hook );

			if ( $success ) {
				$scheduled[] = $label;
			} else {
				$failed[] = $label;
			}
		}

		return array(
			'scheduled' => $scheduled,
			'failed'    => $failed,
		);
	}

	/**
	 * Unschedule all plugin cron events (used during plugin deactivation).
	 *
	 * @return void
	 */
	public function unschedule_all_events(): void {
		foreach ( array_keys( AI_Post_Scheduler::get_cron_events() ) as $hook ) {
			$this->unschedule_hook( $hook );
		}
	}

	/**
	 * Flush and re-register all plugin cron events.
	 *
	 * Equivalent to calling schedule_all_events( true ).
	 *
	 * @return array{scheduled: string[], failed: string[]} Results per hook label.
	 */
	public function flush_and_reschedule(): array {
		return $this->schedule_all_events( true );
	}

	// -------------------------------------------------------------------------
	// System-status helpers
	// -------------------------------------------------------------------------

	/**
	 * Return a human-readable label for the active driver.
	 *
	 * @return string
	 */
	public function get_driver_label(): string {
		if ( $this->driver === self::DRIVER_ACTION_SCHEDULER ) {
			return __( 'Action Scheduler', 'ai-post-scheduler' );
		}

		return __( 'WP-Cron', 'ai-post-scheduler' );
	}

	/**
	 * Build a per-hook status array suitable for System Status diagnostics.
	 *
	 * Each value is an associative array with 'label', 'value', and 'status'.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function get_hook_status(): array {
		$cron_events = AI_Post_Scheduler::get_cron_events();
		$status      = array();

		foreach ( $cron_events as $hook => $config ) {
			$label   = isset( $config['label'] ) ? $config['label'] : $hook;
			$next_ts = $this->next_scheduled( $hook );

			$status[ $hook ] = array(
				'label'  => $label,
				'value'  => $next_ts
					? wp_date( 'Y-m-d H:i:s', (int) $next_ts )
					: __( 'Not Scheduled', 'ai-post-scheduler' ),
				'status' => $next_ts ? 'ok' : 'error',
			);
		}

		return $status;
	}

	// -------------------------------------------------------------------------
	// Private helpers — Action Scheduler
	// -------------------------------------------------------------------------

	/**
	 * Schedule a recurring Action Scheduler action if not already queued.
	 *
	 * @param int    $timestamp  First-run Unix timestamp.
	 * @param string $recurrence WP schedule name (converted to seconds).
	 * @param string $hook       Action hook.
	 * @param array  $args       Optional arguments.
	 * @return bool
	 */
	private function as_schedule_recurring( int $timestamp, string $recurrence, string $hook, array $args ): bool {
		if ( ! self::is_action_scheduler_available() ) {
			return false;
		}

		$interval = $this->recurrence_to_seconds( $recurrence );
		if ( $interval <= 0 ) {
			return false;
		}

		// Action Scheduler uses a unique group per plugin to aid filtering in its admin UI.
		$group = 'ai-post-scheduler';

		try {
			$action_id = as_schedule_recurring_action( $timestamp, $interval, $hook, $args, $group );
			return $action_id > 0;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Unschedule all Action Scheduler actions for the given hook.
	 *
	 * @param string $hook Action hook.
	 * @param array  $args Optional arguments to match.
	 * @return void
	 */
	private function as_unschedule_all( string $hook, array $args ): void {
		if ( ! self::is_action_scheduler_available() ) {
			return;
		}

		try {
			as_unschedule_all_actions( $hook, $args, 'ai-post-scheduler' );
		} catch ( \Exception $e ) {
			// Silently swallow — AS may not have the table yet during early activation.
		}
	}

	/**
	 * Get the next scheduled timestamp from Action Scheduler for the given hook.
	 *
	 * @param string $hook Action hook.
	 * @param array  $args Optional arguments.
	 * @return int|bool Unix timestamp or false if not scheduled.
	 */
	private function as_next_scheduled( string $hook, array $args ) {
		if ( ! self::is_action_scheduler_available() ) {
			return false;
		}

		try {
			$next = as_next_scheduled_action( $hook, $args, 'ai-post-scheduler' );
			return $next !== false ? (int) $next : false;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	// -------------------------------------------------------------------------
	// Private helpers — WP-Cron
	// -------------------------------------------------------------------------

	/**
	 * Schedule a recurring WP-Cron event if it is not already scheduled.
	 *
	 * @param int    $timestamp  First-run Unix timestamp.
	 * @param string $recurrence WP schedule name.
	 * @param string $hook       Action hook.
	 * @param array  $args       Optional arguments.
	 * @return bool
	 */
	private function wpcron_schedule_event( int $timestamp, string $recurrence, string $hook, array $args ): bool {
		if ( wp_next_scheduled( $hook, $args ) ) {
			return true;
		}

		$result = wp_schedule_event( $timestamp, $recurrence, $hook, $args );
		return $result !== false;
	}

	// -------------------------------------------------------------------------
	// Utility
	// -------------------------------------------------------------------------

	/**
	 * Convert a WordPress schedule name to its interval in seconds.
	 *
	 * Falls back to the registered WP schedules array for custom intervals.
	 *
	 * @param string $recurrence WP schedule name.
	 * @return int Interval in seconds, or 0 if unknown.
	 */
	private function recurrence_to_seconds( string $recurrence ): int {
		$built_in = array(
			'minutely'   => MINUTE_IN_SECONDS,
			'twicehourly' => 30 * MINUTE_IN_SECONDS,
			'hourly'     => HOUR_IN_SECONDS,
			'twicedaily' => 12 * HOUR_IN_SECONDS,
			'daily'      => DAY_IN_SECONDS,
			'weekly'     => WEEK_IN_SECONDS,
		);

		if ( isset( $built_in[ $recurrence ] ) ) {
			return $built_in[ $recurrence ];
		}

		// Try custom intervals registered via cron_schedules filter.
		$schedules = wp_get_schedules();
		if ( isset( $schedules[ $recurrence ]['interval'] ) ) {
			return (int) $schedules[ $recurrence ]['interval'];
		}

		return 0;
	}
}
