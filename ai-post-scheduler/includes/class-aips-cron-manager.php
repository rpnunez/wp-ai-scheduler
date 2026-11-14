<?php
/**
 * AIPS Cron Manager — the single, testable layer between the plugin and the
 * WordPress WP-Cron API.
 *
 * Every plugin interaction with `wp_schedule_event()`, `wp_schedule_single_event()`,
 * `wp_next_scheduled()`, `wp_unschedule_event()`, `wp_clear_scheduled_hook()` and
 * `wp_unschedule_hook()` is meant to flow through this wrapper so that:
 *
 *   - scheduling is reconciled from the declarative {@see AIPS_Cron_Registry}
 *     rather than duplicated across activation, settings and diagnostics;
 *   - deactivation can guarantee zero orphaned events (recurring, single, or
 *     parameterised) via {@see unschedule_all_plugin_crons()};
 *   - tests can assert scheduling behaviour against the registry without
 *     relying on the fragile global `$wp_cron` array.
 *
 * NOTE: In this phase the central lifecycle (activation, deactivation, boot,
 * diagnostics, DB manager, settings) routes through the manager, while leaf
 * services still call WP-Cron directly. A follow-up (Phase 2, issue #2369) will
 * migrate those remaining call sites.
 *
 * @package AI_Post_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Cron_Manager {

	/**
	 * Declarative registry of plugin cron hooks.
	 *
	 * @var AIPS_Cron_Registry
	 */
	private $registry;

	/**
	 * @param AIPS_Cron_Registry|null $registry Optional registry (defaults to the shared instance).
	 */
	public function __construct( AIPS_Cron_Registry $registry = null ) {
		$this->registry = $registry instanceof AIPS_Cron_Registry ? $registry : AIPS_Cron_Registry::instance();
	}

	/**
	 * Access the backing registry.
	 *
	 * @return AIPS_Cron_Registry
	 */
	public function registry() {
		return $this->registry;
	}

	// -------------------------------------------------------------------------
	// Thin WP-Cron primitives
	// -------------------------------------------------------------------------

	/**
	 * Schedule a recurring event, skipping the call if one is already scheduled
	 * for the same hook + args (WP-Cron forbids duplicate recurring events).
	 *
	 * @param string   $hook       Cron hook name.
	 * @param string   $recurrence Schedule slug (e.g. 'hourly').
	 * @param int|null $timestamp  First-run timestamp (defaults to now).
	 * @param array    $args       Arguments passed to the hook.
	 * @return bool True when an event is (or already was) scheduled; false on failure.
	 */
	public function schedule_recurring( $hook, $recurrence, $timestamp = null, array $args = array() ) {
		$existing = wp_get_scheduled_event( $hook, $args );

		if ( $existing && isset( $existing->schedule ) && $existing->schedule === $recurrence ) {
			// Already scheduled with the requested recurrence — nothing to do.
			return true;
		}

		if ( $existing ) {
			// A different recurrence (or a lingering single event) is scheduled
			// for this hook+args: clear it first so the requested recurrence
			// actually takes effect instead of being silently ignored.
			$this->clear_hook( $hook, $args );
		}

		$timestamp = ( null === $timestamp ) ? time() : (int) $timestamp;

		return wp_schedule_event( $timestamp, $recurrence, $hook, $args ) !== false;
	}

	/**
	 * Schedule a one-off event.
	 *
	 * @param string $hook      Cron hook name.
	 * @param int    $timestamp Run timestamp.
	 * @param array  $args      Arguments passed to the hook.
	 * @param bool   $dedupe    When true, skip if an identical event is already scheduled.
	 * @return bool True when the event is (or already was) scheduled; false on failure.
	 */
	public function schedule_single( $hook, $timestamp, array $args = array(), $dedupe = true ) {
		if ( $dedupe && $this->next_scheduled( $hook, $args ) !== false ) {
			return true;
		}

		return (bool) wp_schedule_single_event( (int) $timestamp, $hook, $args );
	}

	/**
	 * Next scheduled timestamp for a hook + args.
	 *
	 * @param string $hook Cron hook name.
	 * @param array  $args Arguments the event was scheduled with.
	 * @return int|false Timestamp, or false when nothing is scheduled.
	 */
	public function next_scheduled( $hook, array $args = array() ) {
		$timestamp = wp_next_scheduled( $hook, $args );
		if ( false !== $timestamp ) {
			return $timestamp;
		}

		if ( function_exists( 'as_next_scheduled_action' ) ) {
			$as_next = as_next_scheduled_action( $hook, $args );
			if ( false !== $as_next && null !== $as_next ) {
				return is_object( $as_next ) && method_exists( $as_next, 'getTimestamp' ) ? $as_next->getTimestamp() : (int) $as_next;
			}
		}

		return false;
	}

	/**
	 * Remove a single scheduled occurrence (one timestamp) of a hook.
	 *
	 * @param int    $timestamp Scheduled timestamp.
	 * @param string $hook      Cron hook name.
	 * @param array  $args      Arguments the event was scheduled with.
	 * @return bool
	 */
	public function unschedule_occurrence( $timestamp, $hook, array $args = array() ) {
		return (bool) wp_unschedule_event( (int) $timestamp, $hook, $args );
	}

	/**
	 * Clear every occurrence matching a hook + specific args.
	 *
	 * Mirrors {@see wp_clear_scheduled_hook()}: only events whose args equal the
	 * ones passed are removed. Also cleans matching ActionScheduler actions if present.
	 * Use {@see unschedule_all_for_hook()} to strip events regardless of their arguments.
	 *
	 * @param string $hook Cron hook name.
	 * @param array  $args Arguments to match.
	 * @return int|false Number cleared, or false on failure.
	 */
	public function clear_hook( $hook, array $args = array() ) {
		$result = wp_clear_scheduled_hook( $hook, $args );

		if ( function_exists( 'as_unschedule_action' ) ) {
			as_unschedule_action( $hook, $args );
		}

		return $result;
	}

	/**
	 * Remove every occurrence of a hook regardless of the arguments it was
	 * scheduled with (wraps {@see wp_unschedule_hook()} and ActionScheduler).
	 *
	 * @param string $hook Cron hook name.
	 * @return int|false Number cleared, or false on failure.
	 */
	public function unschedule_all_for_hook( $hook ) {
		$result = wp_unschedule_hook( $hook );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( $hook );
		}

		return $result;
	}

	// -------------------------------------------------------------------------
	// Registry-driven reconciliation
	// -------------------------------------------------------------------------

	/**
	 * Reconcile every manager-owned recurring event against its current enabled
	 * state and recurrence.
	 *
	 * For each event:
	 *   - disabled  → clear every occurrence of the hook;
	 *   - enabled   → ensure exactly one event exists with the correct
	 *                 recurrence, re-scheduling when the recurrence slug drifted.
	 *
	 * This replaces the ad-hoc scheduling loop in activation and the per-request
	 * reconciliation that previously lived in `boot_admin()`.
	 *
	 * @return void
	 */
	public function sync_recurring_schedules() {
		foreach ( $this->registry->get_recurring_events() as $hook => $definition ) {
			$enabled = $this->registry->is_event_enabled( $definition );

			if ( ! $enabled ) {
				$this->clear_hook( $hook );
				continue;
			}

			$recurrence = $this->registry->resolve_recurrence( $definition );
			$event      = wp_get_scheduled_event( $hook );

			if ( $event && isset( $event->schedule ) && $event->schedule === $recurrence ) {
				// Already scheduled with the right recurrence — nothing to do.
				continue;
			}

			if ( $event ) {
				// Recurrence drifted (or a one-off event lingered): clear and reschedule.
				$this->clear_hook( $hook );
			}

			wp_schedule_event( time(), $recurrence, $hook );
		}
	}

	/**
	 * Unschedule every plugin cron hook — recurring, optional leaf-scheduled,
	 * single/tick (with any arguments), and legacy — leaving zero orphaned
	 * events behind. Called from {@see AI_Post_Scheduler::deactivate()}.
	 *
	 * Clears both WP-Cron events and ActionScheduler actions across all registered
	 * plugin hooks and groups (issue #2375).
	 *
	 * @return void
	 */
	public function unschedule_all_plugin_crons() {
		foreach ( $this->registry->get_all_hooks() as $hook ) {
			$this->unschedule_all_for_hook( $hook );
		}

		// Clear all registered ActionScheduler groups to ensure zero orphaned actions
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			foreach ( $this->registry->get_action_scheduler_groups() as $group ) {
				as_unschedule_all_actions( '', null, $group );
			}
		}
	}

	/**
	 * Lightweight drift-safety reconciliation for admin page loads.
	 *
	 * Replaces the unconditional `ensure_schedule()` object construction that
	 * `boot_admin()` previously ran on every request. Only the two leaf-scheduled
	 * services (duplicate scan, Search Console sync) are reconciled here, and only
	 * when there is actually something to reconcile — a wanted schedule, saved
	 * credentials, or a stale event already in the cron table.
	 *
	 * Manager-owned recurring events are intentionally NOT force-rescheduled on
	 * every admin load: that happens at activation (sync_recurring_schedules()),
	 * on option changes (Settings reconcile hooks), and via the Diagnostics
	 * "reschedule missed cron" repair. This keeps a deliberately-unscheduled
	 * recurring event from being silently re-created on the next page view.
	 * Ongoing enable/disable/connect transitions are handled by event-driven
	 * reconcile hooks.
	 *
	 * @return void
	 */
	public function reconcile_admin_schedules() {
		$config = AIPS_Config::get_instance();

		// Duplicate scan: reconcile when a schedule is wanted, or a stale event lingers.
		$dup_schedule = (string) $config->get_option( 'aips_duplicate_scan_schedule', 'off' );
		$dup_wanted   = in_array( $dup_schedule, array( 'daily', 'weekly' ), true );

		if ( $dup_wanted || $this->next_scheduled( AIPS_Duplicate_Group_Service::CRON_HOOK ) !== false ) {
			( new AIPS_Duplicate_Group_Service() )->ensure_schedule();
		}

		// Search Console sync: reconcile when credentials are saved (possibly
		// configured), or a stale event lingers from a prior connection.
		$gsc_has_credentials = (string) $config->get_option( AIPS_GSC_Client::CREDENTIALS_OPTION, '' ) !== '';

		if ( $gsc_has_credentials || $this->next_scheduled( AIPS_GSC_Keywords_Service::CRON_HOOK ) !== false ) {
			AIPS_Container::get_instance()->make( AIPS_GSC_Keywords_Service::class )->ensure_schedule();
		}
	}

	// -------------------------------------------------------------------------
	// Lazy dispatch registration
	// -------------------------------------------------------------------------

	/**
	 * Register the registry's lazy dispatch closures for a boot context.
	 *
	 * Each hook gets exactly one callback, resolving its service only when the
	 * hook fires, so a cron request for a single hook never constructs the whole
	 * scheduler object graph.
	 *
	 * @param string $context Either 'common' or 'cron'.
	 * @return void
	 */
	public function register_dispatch_handlers( $context ) {
		foreach ( $this->registry->get_dispatch_handlers( $context ) as $hook => $handler ) {
			$accepted_args = isset( $handler['accepted_args'] ) ? (int) $handler['accepted_args'] : 1;
			add_action( $hook, $handler['callback'], 10, $accepted_args );
		}
	}
}
