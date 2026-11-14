<?php
/**
 * AIPS Cron Registry — declarative single source of truth for every WP-Cron
 * hook the plugin owns.
 *
 * The registry separates three concerns that were previously scattered across
 * the bootstrap, settings, diagnostics and DB-manager classes:
 *
 *  1. Manager-owned recurring events  — reconciled centrally by
 *     {@see AIPS_Cron_Manager::sync_recurring_schedules()} and surfaced to the
 *     legacy {@see AI_Post_Scheduler::get_cron_events()} shim.
 *  2. Optional, leaf-scheduled events — recurring jobs whose scheduling logic
 *     still lives in the owning service (duplicate scan, Search Console sync,
 *     source fetch/maintenance). The registry only needs to know their hook
 *     names so deactivation and diagnostics can account for them.
 *  3. Single / background-tick events — parameterised one-off cron slices. The
 *     registry tracks them so deactivation can strip every pending occurrence
 *     regardless of the arguments they were scheduled with.
 *
 * It also centralises the lazy dispatch closures that {@see AI_Post_Scheduler}
 * registers in `boot_cron()` / `boot_common()`, so only the service handling
 * the currently firing hook is ever instantiated.
 *
 * This class is pure (no runtime state): every method returns freshly-built
 * data so tests can inspect it without touching the global `$wp_cron` array.
 *
 * NOTE: Leaf services still schedule themselves directly in this phase. A
 * follow-up (Phase 2) will migrate those internal `wp_schedule_*` call sites to
 * route through {@see AIPS_Cron_Manager}. See issue #2369.
 *
 * @package AI_Post_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Cron_Registry {

	/**
	 * Singleton instance.
	 *
	 * @var AIPS_Cron_Registry|null
	 */
	private static $instance = null;

	/**
	 * Get the shared registry instance.
	 *
	 * @return AIPS_Cron_Registry
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	// -------------------------------------------------------------------------
	// ActionScheduler groups
	// -------------------------------------------------------------------------

	const GROUP_INDEXER        = 'aips-indexer';
	const GROUP_INTERNAL_LINKS = 'aips-internal-links';
	const GROUP_EMBEDDINGS     = 'aips-embeddings';
	const GROUP_DEFAULT        = 'aips-default';

	/**
	 * Every ActionScheduler group registered by the plugin.
	 *
	 * Used by deactivation cleanup to clear orphaned actions across all plugin groups.
	 *
	 * @return string[]
	 */
	public function get_action_scheduler_groups() {
		return array(
			self::GROUP_INDEXER,
			self::GROUP_INTERNAL_LINKS,
			self::GROUP_EMBEDDINGS,
			self::GROUP_DEFAULT,
		);
	}

	// -------------------------------------------------------------------------
	// Recurring events (manager-owned)
	// -------------------------------------------------------------------------

	/**
	 * Every manager-owned recurring event, independent of feature flags.
	 *
	 * Each definition is keyed by hook name and contains:
	 *   - label:      human-readable name for Diagnostics.
	 *   - recurrence: a cron schedule slug, or a callable returning one.
	 *   - enabled:    optional callable returning bool; absent/null means the
	 *                 event is always active.
	 *
	 * Keeping the full list separate from the "currently active" view prevents a
	 * disabled feature flag from hiding a hook during deactivation or diagnostics
	 * (one of the root causes called out in issue #2369).
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_recurring_events() {
		return array(
			'aips_generate_scheduled_posts' => array(
				'label'      => __( 'Post Generation', 'ai-post-scheduler' ),
				'recurrence' => 'hourly',
			),
			'aips_generate_author_topics' => array(
				'label'      => __( 'Author Topic Generation', 'ai-post-scheduler' ),
				'recurrence' => 'hourly',
			),
			'aips_generate_author_posts' => array(
				'label'      => __( 'Author Post Generation', 'ai-post-scheduler' ),
				'recurrence' => 'hourly',
			),
			'aips_scheduled_research' => array(
				'label'      => __( 'Automated Research', 'ai-post-scheduler' ),
				'recurrence' => 'daily',
			),
			'aips_notification_rollups' => array(
				'label'      => __( 'Notification Rollups', 'ai-post-scheduler' ),
				'recurrence' => 'daily',
			),
			'aips_cleanup_export_files' => array(
				'label'      => __( 'Export Cleanup', 'ai-post-scheduler' ),
				'recurrence' => 'daily',
			),
			'aips_cleanup_bulk_batch_jobs' => array(
				'label'      => __( 'Bulk Batch Job Cleanup', 'ai-post-scheduler' ),
				'recurrence' => 'daily',
			),
			'aips_cache_monitor_maintenance' => array(
				'label'      => __( 'Cache Monitor Maintenance', 'ai-post-scheduler' ),
				'recurrence' => 'daily',
			),
			'aips_database_prune_cleanup' => array(
				'label'      => __( 'Database Prune & Retention Cleanup', 'ai-post-scheduler' ),
				'recurrence' => array( $this, 'resolve_prune_recurrence' ),
				'enabled'    => static function () {
					return (bool) AIPS_Config::get_instance()->get_option( 'aips_auto_prune_enabled' );
				},
			),
			'aips_notif_digest_cron' => array(
				'label'      => __( 'Notification Digest Check', 'ai-post-scheduler' ),
				'recurrence' => 'fifteen_minutes',
				'enabled'    => static function () {
					return (bool) AIPS_Config::get_instance()->get_option( 'aips_notif_digest_enabled' );
				},
			),
		);
	}

	/**
	 * Resolve the configured database-prune recurrence, clamped to a known slug.
	 *
	 * @return string
	 */
	public function resolve_prune_recurrence() {
		$interval = sanitize_key( (string) AIPS_Config::get_instance()->get_option( 'aips_telemetry_prune_interval', 'daily' ) );

		return in_array( $interval, array( 'daily', 'twicedaily', 'weekly' ), true ) ? $interval : 'daily';
	}

	/**
	 * Whether a recurring event definition is currently enabled.
	 *
	 * @param array<string,mixed> $definition Event definition.
	 * @return bool
	 */
	public function is_event_enabled( array $definition ) {
		if ( ! isset( $definition['enabled'] ) || null === $definition['enabled'] ) {
			return true;
		}

		return (bool) call_user_func( $definition['enabled'] );
	}

	/**
	 * Resolve a recurring event definition's recurrence slug.
	 *
	 * @param array<string,mixed> $definition Event definition.
	 * @return string
	 */
	public function resolve_recurrence( array $definition ) {
		$recurrence = isset( $definition['recurrence'] ) ? $definition['recurrence'] : 'hourly';

		if ( is_callable( $recurrence ) ) {
			$recurrence = (string) call_user_func( $recurrence );
		}

		return (string) $recurrence;
	}

	/**
	 * The subset of recurring events that are currently active, with their
	 * recurrence resolved to a concrete slug.
	 *
	 * This is the view consumed by activation and the legacy
	 * {@see AI_Post_Scheduler::get_cron_events()} shim, so conditional features
	 * (prune, digest) only appear while enabled — matching historic behaviour.
	 *
	 * @return array<string,array{schedule:string,label:string}>
	 */
	public function get_active_recurring_events() {
		$active = array();

		foreach ( $this->get_recurring_events() as $hook => $definition ) {
			if ( ! $this->is_event_enabled( $definition ) ) {
				continue;
			}

			$active[ $hook ] = array(
				'schedule' => $this->resolve_recurrence( $definition ),
				'label'    => isset( $definition['label'] ) ? $definition['label'] : $hook,
			);
		}

		return $active;
	}

	/**
	 * Hook names for every manager-owned recurring event (enabled or not).
	 *
	 * @return string[]
	 */
	public function get_recurring_hooks() {
		return array_keys( $this->get_recurring_events() );
	}

	// -------------------------------------------------------------------------
	// Optional, leaf-scheduled recurring events
	// -------------------------------------------------------------------------

	/**
	 * Recurring events whose scheduling is still owned by their service, keyed
	 * by hook with a diagnostics label. The registry only needs these names so
	 * that deactivation and diagnostics can account for them.
	 *
	 * @return array<string,string>
	 */
	public function get_optional_service_hooks() {
		return array(
			AIPS_Duplicate_Group_Service::CRON_HOOK   => __( 'Scheduled Duplicate Scan', 'ai-post-scheduler' ),
			AIPS_GSC_Keywords_Service::CRON_HOOK       => __( 'Search Console Keyword Sync', 'ai-post-scheduler' ),
			AIPS_Sources_Cron::HOOK                    => __( 'Source Fetch', 'ai-post-scheduler' ),
			AIPS_Sources_Cron::CONTINUATION_HOOK       => __( 'Source Fetch (continuation)', 'ai-post-scheduler' ),
			AIPS_Sources_Maintenance::HOOK             => __( 'Source Archive Maintenance', 'ai-post-scheduler' ),
			AIPS_Sources_Maintenance::CONTINUATION_HOOK => __( 'Source Archive Maintenance (continuation)', 'ai-post-scheduler' ),
		);
	}

	// -------------------------------------------------------------------------
	// Single / background-tick events
	// -------------------------------------------------------------------------

	/**
	 * Parameterised single / background-tick hooks, keyed by hook name with a
	 * diagnostics label.
	 *
	 * These are scheduled with per-slice arguments, so cleanup must use
	 * {@see wp_unschedule_hook()} (argument-agnostic) rather than
	 * {@see wp_clear_scheduled_hook()} (which only matches `array()` args).
	 *
	 * @return array<string,string>
	 */
	public function get_single_event_hooks() {
		return array(
			AIPS_Schedule_Processor::QUEUED_DUE_SCHEDULE_HOOK => __( 'Queued Due Schedule', 'ai-post-scheduler' ),
			AIPS_Batch_Queue_Service::HOOK                    => __( 'Schedule Batch Slice', 'ai-post-scheduler' ),
			'aips_resume_terminated_batches'                  => __( 'Resume Terminated Batches', 'ai-post-scheduler' ),
			'aips_process_author_topics_slice'                => __( 'Author Topic Slice', 'ai-post-scheduler' ),
			'aips_retry_failed_author_slices_topics'          => __( 'Author Topic Slice Retry', 'ai-post-scheduler' ),
			'aips_process_author_post_slice'                  => __( 'Author Post Slice', 'ai-post-scheduler' ),
			'aips_retry_failed_author_slices_posts'           => __( 'Author Post Slice Retry', 'ai-post-scheduler' ),
			'aips_process_author_embeddings'                  => __( 'Author Embeddings', 'ai-post-scheduler' ),
			AIPS_Publish_Linking_Service::CRON_HOOK           => __( 'Publish Linking', 'ai-post-scheduler' ),
			AIPS_Link_Index_Service::SCAN_TICK_HOOK           => __( 'Link Index Scan Tick', 'ai-post-scheduler' ),
			AIPS_Autolink_Run_Service::TICK_HOOK              => __( 'Auto-link Run Tick', 'ai-post-scheduler' ),
			AIPS_Managed_Background_Process::TICK_HOOK        => __( 'Managed Background Process Tick', 'ai-post-scheduler' ),
			AIPS_Bulk_Batch_Processor::HOOK                   => __( 'Bulk Batch Slice', 'ai-post-scheduler' ),
			'aips_index_posts_batch'                          => __( 'Internal Links Index Batch', 'ai-post-scheduler' ),
			'aips_process_pending_indexer_queue'              => __( 'Pending Indexer Queue', 'ai-post-scheduler' ),
		);
	}

	// -------------------------------------------------------------------------
	// Legacy hooks (clear-only)
	// -------------------------------------------------------------------------

	/**
	 * Historic hook names that are no longer scheduled but may still linger in
	 * the cron table on long-lived installs. Deactivation clears these so no
	 * orphaned events survive an upgrade-then-deactivate sequence.
	 *
	 * @return string[]
	 */
	public function get_legacy_hooks() {
		return array(
			'aips_daily_rollup_insights',
			'aips_weekly_send_digest',
			'aips_monthly_send_digest',
		);
	}

	// -------------------------------------------------------------------------
	// Aggregate views
	// -------------------------------------------------------------------------

	/**
	 * Every plugin cron hook name — recurring (all, flags ignored), optional
	 * leaf-scheduled, single/tick, and legacy — de-duplicated.
	 *
	 * This is the authoritative list {@see AIPS_Cron_Manager::unschedule_all_plugin_crons()}
	 * iterates to guarantee a clean deactivation.
	 *
	 * @return string[]
	 */
	public function get_all_hooks() {
		$hooks = array_merge(
			$this->get_recurring_hooks(),
			array_keys( $this->get_optional_service_hooks() ),
			array_keys( $this->get_single_event_hooks() ),
			$this->get_legacy_hooks()
		);

		return array_values( array_unique( $hooks ) );
	}

	// -------------------------------------------------------------------------
	// Lazy dispatch handlers
	// -------------------------------------------------------------------------

	/**
	 * Lazy cron dispatch closures, grouped by boot context.
	 *
	 * Each entry maps a hook to `['callback' => callable, 'accepted_args' => int]`.
	 * The closures resolve their service only when the hook actually fires, so a
	 * cron request for a single hook never instantiates the whole scheduler
	 * object graph.
	 *
	 * Contexts:
	 *   - 'common': registered in every request context (so WP-CLI / "run now"
	 *               tools can fire them), mirroring the historic placement in
	 *               `boot_common()`.
	 *   - 'cron':   registered only during WP-Cron execution.
	 *
	 * Hooks whose dispatch is owned by a dedicated handler object
	 * (aips_scheduled_research → AIPS_Research_Controller,
	 * aips_notification_rollups → AIPS_Notifications_Event_Handler) are
	 * intentionally absent here; those objects register their own callbacks.
	 *
	 * @param string $context Either 'common' or 'cron'.
	 * @return array<string,array{callback:callable,accepted_args:int}>
	 */
	public function get_dispatch_handlers( $context ) {
		if ( 'common' === $context ) {
			return $this->get_common_dispatch_handlers();
		}

		if ( 'cron' === $context ) {
			return $this->get_cron_dispatch_handlers();
		}

		return array();
	}

	/**
	 * Dispatch handlers registered in every request context.
	 *
	 * @return array<string,array{callback:callable,accepted_args:int}>
	 */
	private function get_common_dispatch_handlers() {
		return array(
			'aips_process_pending_indexer_queue' => array(
				'callback'      => static function () {
					AIPS_Container::get_instance()->make( AIPS_Content_Indexer_Service::class )->process_pending_indexer_queue();
				},
				'accepted_args' => 1,
			),
			AIPS_Managed_Background_Process::TICK_HOOK => array(
				'callback'      => static function ( $process_key ) {
					AIPS_Container::get_instance()->make( AIPS_Background_Process_Manager::class )->tick( (string) $process_key );
				},
				'accepted_args' => 1,
			),
		);
	}

	/**
	 * Dispatch handlers registered only during WP-Cron execution.
	 *
	 * @return array<string,array{callback:callable,accepted_args:int}>
	 */
	private function get_cron_dispatch_handlers() {
		return array(
			'aips_generate_scheduled_posts' => array(
				'callback'      => static function () {
					AIPS_Scheduler::instance()->process();
				},
				'accepted_args' => 1,
			),
			AIPS_Schedule_Processor::QUEUED_DUE_SCHEDULE_HOOK => array(
				'callback'      => static function ( $schedule_id ) {
					AIPS_Scheduler::instance()->process_queued_due_schedule( (int) $schedule_id );
				},
				'accepted_args' => 1,
			),
			AIPS_Batch_Queue_Service::HOOK => array(
				'callback'      => static function ( $schedule_id, $start_index, $batch_size, $total_quantity, $correlation_id = '' ) {
					AIPS_Scheduler::instance()->process_batch(
						(int) $schedule_id,
						(int) $start_index,
						(int) $batch_size,
						(int) $total_quantity,
						(string) $correlation_id
					);
				},
				'accepted_args' => 5,
			),
			'aips_resume_terminated_batches' => array(
				'callback'      => static function () {
					AIPS_Scheduler::instance()->resume_terminated_batches();
				},
				'accepted_args' => 1,
			),
			'aips_generate_author_topics' => array(
				'callback'      => static function () {
					AIPS_Author_Topics_Scheduler::instance()->process_topic_generation();
				},
				'accepted_args' => 1,
			),
			'aips_process_author_topics_slice' => array(
				'callback'      => static function ( $author_id, $correlation_id = '' ) {
					AIPS_Author_Topics_Scheduler::instance()->process_author_slice(
						(int) $author_id,
						(string) $correlation_id
					);
				},
				'accepted_args' => 2,
			),
			'aips_retry_failed_author_slices_topics' => array(
				'callback'      => static function ( $author_ids_json, $correlation_id = '' ) {
					AIPS_Author_Topics_Scheduler::instance()->retry_failed_topic_slices(
						(string) $author_ids_json,
						(string) $correlation_id
					);
				},
				'accepted_args' => 2,
			),
			'aips_generate_author_posts' => array(
				'callback'      => static function () {
					AIPS_Author_Post_Generator::instance()->process();
				},
				'accepted_args' => 1,
			),
			'aips_process_author_post_slice' => array(
				'callback'      => static function ( $author_id, $correlation_id = '' ) {
					AIPS_Author_Post_Generator::instance()->process_author_slice(
						(int) $author_id,
						(string) $correlation_id
					);
				},
				'accepted_args' => 2,
			),
			'aips_retry_failed_author_slices_posts' => array(
				'callback'      => static function ( $author_ids_json, $correlation_id = '' ) {
					AIPS_Author_Post_Generator::instance()->retry_failed_post_slices(
						(string) $author_ids_json,
						(string) $correlation_id
					);
				},
				'accepted_args' => 2,
			),
			AIPS_Publish_Linking_Service::CRON_HOOK => array(
				'callback'      => static function ( $post_id ) {
					AIPS_Container::get_instance()->make( AIPS_Publish_Linking_Service::class )->process( $post_id );
				},
				'accepted_args' => 1,
			),
			AIPS_Duplicate_Group_Service::CRON_HOOK => array(
				'callback'      => static function () {
					( new AIPS_Duplicate_Group_Service() )->run_scheduled_scan();
				},
				'accepted_args' => 1,
			),
			AIPS_GSC_Keywords_Service::CRON_HOOK => array(
				'callback'      => static function () {
					AIPS_Container::get_instance()->make( AIPS_GSC_Keywords_Service::class )->sync();
				},
				'accepted_args' => 1,
			),
			AIPS_Link_Index_Service::SCAN_TICK_HOOK => array(
				'callback'      => static function ( $job_id ) {
					AIPS_Container::get_instance()->make( AIPS_Link_Index_Service::class )->process_scan_tick( $job_id );
				},
				'accepted_args' => 1,
			),
			AIPS_Autolink_Run_Service::TICK_HOOK => array(
				'callback'      => static function ( $job_id ) {
					AIPS_Container::get_instance()->make( AIPS_Autolink_Run_Service::class )->process_tick( $job_id );
				},
				'accepted_args' => 1,
			),
			AIPS_Bulk_Batch_Processor::HOOK => array(
				'callback'      => static function ( $job_id, $start_index, $batch_size, $total_quantity, $correlation_id = '' ) {
					AIPS_Bulk_Batch_Processor::instance()->process(
						(string) $job_id,
						(int) $start_index,
						(int) $batch_size,
						(int) $total_quantity,
						(string) $correlation_id
					);
				},
				'accepted_args' => 5,
			),
			'aips_cleanup_bulk_batch_jobs' => array(
				'callback'      => static function () {
					$store    = new AIPS_Bulk_Batch_Job_Store();
					$deleted  = $store->cleanup_old_jobs();
					$deleted += ( new AIPS_Background_Process_Repository() )->cleanup_old();
					if ( $deleted > 0 ) {
						( new AIPS_Logger() )->log(
							sprintf( 'Bulk batch job cleanup: deleted %d old job rows.', $deleted ),
							'info'
						);
					}
				},
				'accepted_args' => 1,
			),
			'aips_cache_monitor_maintenance' => array(
				'callback'      => static function () {
					$repository  = new AIPS_Cache_Monitor_Repository();
					$cache_index = new AIPS_Cache_Index();
					$service     = new AIPS_Cache_Monitor_Service( $repository, $cache_index );
					$result      = $service->run_maintenance();
					( new AIPS_Logger() )->log(
						sprintf( 'Cache Monitor maintenance complete: %s', wp_json_encode( $result ) ),
						'info'
					);
				},
				'accepted_args' => 1,
			),
			'aips_database_prune_cleanup' => array(
				'callback'      => static function () {
					$service = AIPS_Container::get_instance()->make( AIPS_DB_Prune_Service::class );
					$result  = $service->run_automated_prune();
					( new AIPS_Logger() )->log(
						sprintf( 'Database prune cleanup complete: %s', wp_json_encode( $result ) ),
						'info'
					);
				},
				'accepted_args' => 1,
			),
			'aips_process_author_embeddings' => array(
				'callback'      => static function ( $args ) {
					AIPS_Embeddings_Cron::instance()->process_author_embeddings( $args );
				},
				'accepted_args' => 1,
			),
			'aips_index_posts_batch' => array(
				'callback'      => static function ( $args ) {
					( new AIPS_Internal_Links_Controller() )->process_indexing_batch_cron( $args );
				},
				'accepted_args' => 1,
			),
			'aips_cleanup_export_files' => array(
				'callback'      => array( 'AIPS_Session_To_JSON', 'handle_export_cleanup' ),
				'accepted_args' => 1,
			),
		);
	}
}
