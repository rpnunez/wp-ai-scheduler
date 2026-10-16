<?php
/**
 * Ability Workflow Executor
 *
 * Runs Ability Workflow runs: resolves step inputs against a run-scoped
 * variable bag, evaluates AND/OR conditions, invokes abilities via
 * AIPS_Ability_Service and persists per-step results.
 *
 * This class has no scheduler of its own. process_run() performs one bounded
 * slice of one run and reports what is left (finished, continue at a given
 * time, or busy); AIPS_Ability_Workflows_Process, a managed background
 * process, calls it for every open run, owns the cron tick, the watchdog and
 * the pause/stop controls, and shows up under Diagnostics > Background
 * Processes. dispatch_run() only records a queued run and wakes that process.
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Ability_Workflow_Executor
 */
class AIPS_Ability_Workflow_Executor {

	/**
	 * process_run() outcomes.
	 *
	 * FINISHED: the run is terminal (or gone); nothing more to do.
	 * CONTINUE: the run is still open; call again at `retry_at`.
	 * BUSY:     another worker holds the run; try again soon.
	 */
	const RESULT_FINISHED = 'finished';
	const RESULT_CONTINUE = 'continue';
	const RESULT_BUSY     = 'busy';

	/**
	 * Default wall-clock budget (seconds) for a single slice of a run before it
	 * yields and asks to be continued, instead of pushing past PHP's
	 * max_execution_time.
	 */
	const DEFAULT_TIME_BUDGET_SECONDS = 20;

	/**
	 * Seconds to wait before continuing a run whose time budget ran out.
	 */
	const CONTINUATION_DELAY_SECONDS = 2;

	/**
	 * Maximum time a worker may hold a run lock before another worker can
	 * recover it after a crash.
	 */
	const RUN_LOCK_TTL_SECONDS = 180;

	/**
	 * Seconds to wait before looking again at a run another worker holds.
	 */
	const BUSY_RETRY_SECONDS = 10;

	/**
	 * Workflow statuses whose runs are refused at dispatch and abandoned if
	 * the workflow changes to one of them while a run is in flight.
	 */
	const NON_RUNNABLE_STATUSES = array( 'archived', 'paused' );

	/**
	 * Step-run statuses that mean "already resolved, do not re-attempt".
	 */
	const RESOLVED_STEP_STATUSES = array( 'completed', 'failed', 'skipped' );

	/**
	 * @var AIPS_Ability_Workflow_Repository
	 */
	private $repository;

	/**
	 * @var AIPS_Ability_Catalog_Service
	 */
	private $catalog;

	/**
	 * @var AIPS_Ability_Service
	 */
	private $ability_service;

	/**
	 * @var AIPS_Ability_Workflow_Condition_Evaluator
	 */
	private $condition_evaluator;

	/**
	 * @var AIPS_Ability_Workflow_Variable_Resolver
	 */
	private $resolver;

	/**
	 * Wakes the background process after a run is queued.
	 *
	 * @var callable|null
	 */
	private $kick;

	/**
	 * @var AIPS_History_Service_Interface
	 */
	private $history_service;

	/**
	 * @var AIPS_Logger_Interface
	 */
	private $logger;

	/**
	 * Lock ownership tokens keyed by run ID.
	 *
	 * @var array<int,string>
	 */
	private $run_locks = array();

	/**
	 * Constructor. Every dependency is optional and resolved from the
	 * container when omitted.
	 */
	public function __construct(
		?AIPS_Ability_Workflow_Repository $repository = null,
		?AIPS_Ability_Catalog_Service $catalog = null,
		?AIPS_Ability_Service $ability_service = null,
		?AIPS_Ability_Workflow_Condition_Evaluator $condition_evaluator = null,
		?AIPS_Ability_Workflow_Variable_Resolver $resolver = null,
		?AIPS_History_Service_Interface $history_service = null,
		?AIPS_Logger_Interface $logger = null,
		?callable $kick = null
	) {
		$container = AIPS_Container::get_instance();

		$this->repository = $repository ?: ( $container->has( AIPS_Ability_Workflow_Repository::class )
			? $container->make( AIPS_Ability_Workflow_Repository::class )
			: AIPS_Ability_Workflow_Repository::instance() );

		$this->ability_service = $ability_service ?: ( $container->has( AIPS_Ability_Service::class )
			? $container->make( AIPS_Ability_Service::class )
			: new AIPS_Ability_Service() );

		$this->catalog = $catalog ?: ( $container->has( AIPS_Ability_Catalog_Service::class )
			? $container->make( AIPS_Ability_Catalog_Service::class )
			: new AIPS_Ability_Catalog_Service( $this->ability_service ) );

		$this->resolver            = $resolver ?: new AIPS_Ability_Workflow_Variable_Resolver();
		$this->condition_evaluator = $condition_evaluator ?: new AIPS_Ability_Workflow_Condition_Evaluator( $this->resolver );
		$this->kick                = $kick;

		$this->history_service = $history_service ?: ( $container->has( AIPS_History_Service_Interface::class )
			? $container->make( AIPS_History_Service_Interface::class )
			: new AIPS_History_Service() );

		$this->logger = $logger ?: ( $container->has( AIPS_Logger_Interface::class )
			? $container->make( AIPS_Logger_Interface::class )
			: new AIPS_Logger() );
	}

	// -----------------------------------------------------------------------
	// Public API
	// -----------------------------------------------------------------------

	/**
	 * Queue a workflow run and wake the background process that executes it.
	 * Never executes synchronously — this is what "Run Now" and scheduled
	 * triggers both call.
	 *
	 * @param int   $workflow_id     Workflow ID.
	 * @param array $trigger_context Trigger context payload (e.g. manual user, or scheduled trigger config).
	 * @return int|WP_Error New run ID, or WP_Error.
	 */
	public function dispatch_run( int $workflow_id, array $trigger_context = array() ) {
		if ( ! AIPS_Ability_Workflows_Feature::is_enabled() ) {
			return new WP_Error( 'ability_workflows_disabled', __( 'Ability Workflows are disabled.', 'ai-post-scheduler' ) );
		}

		$workflow = $this->repository->get_workflow( $workflow_id );

		if ( ! $workflow ) {
			return new WP_Error( 'ability_workflow_not_found', __( 'Workflow not found.', 'ai-post-scheduler' ) );
		}

		if ( in_array( $workflow->status, self::NON_RUNNABLE_STATUSES, true ) ) {
			return new WP_Error(
				'ability_workflow_not_runnable',
				/* translators: %s: workflow status */
				sprintf( __( 'A %s workflow cannot be run.', 'ai-post-scheduler' ), $workflow->status )
			);
		}

		$correlation_id = AIPS_Correlation_ID::generate();

		$run_id = $this->repository->create_run( $workflow_id, $workflow->version, $trigger_context, $correlation_id );

		AIPS_Correlation_ID::reset();

		if ( is_wp_error( $run_id ) ) {
			return $run_id;
		}

		$kicked = $this->wake_process();

		if ( is_wp_error( $kicked ) ) {
			// Nothing will ever pick the run up: fail it rather than leave it
			// orphaned at 'queued'.
			$this->repository->update_run_status(
				$run_id,
				AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED,
				array( 'finished_at' => time() )
			);

			return $kicked;
		}

		return $run_id;
	}

	/**
	 * Cancel a queued or running run.
	 *
	 * Takes effect immediately for the status and the History record. A worker
	 * that is already executing a step finishes that step, then stops before
	 * starting the next one.
	 *
	 * @param int $run_id Run ID.
	 * @return true|WP_Error True when the run was cancelled.
	 */
	public function cancel_run( int $run_id ) {
		$run = $this->repository->get_run( $run_id );

		if ( ! $run ) {
			return new WP_Error( 'ability_workflow_run_not_found', __( 'Workflow run not found.', 'ai-post-scheduler' ) );
		}

		if ( in_array( $run->status, AIPS_Ability_Workflow_Repository::RUN_TERMINAL_STATUSES, true ) ) {
			return new WP_Error(
				'ability_workflow_run_finished',
				/* translators: %s: run status */
				sprintf( __( 'This run has already finished (%s).', 'ai-post-scheduler' ), $run->status )
			);
		}

		$this->repository->update_run_status(
			$run_id,
			AIPS_Ability_Workflow_Repository::RUN_STATUS_CANCELLED,
			array( 'finished_at' => time() )
		);

		$this->terminate_history( $run, __( 'Workflow run cancelled.', 'ai-post-scheduler' ) );

		return true;
	}

	/**
	 * Perform one bounded slice of a run: execute (or resume) its steps until
	 * the run finishes, a step needs a backoff, or the time budget runs out.
	 *
	 * @param int    $run_id         Run ID.
	 * @param string $correlation_id Correlation ID; defaults to the run's own.
	 * @return array{state:string, retry_at:int, worked:bool} See the RESULT_* constants.
	 */
	public function process_run( int $run_id, string $correlation_id = '' ): array {
		$run = $this->repository->get_run( $run_id );

		// Nothing to do (and no History row to open) for a run that is gone,
		// cancelled, or already finished.
		if ( ! $run || in_array( $run->status, AIPS_Ability_Workflow_Repository::RUN_TERMINAL_STATUSES, true ) ) {
			return $this->result( self::RESULT_FINISHED );
		}

		if ( '' === $correlation_id ) {
			$correlation_id = (string) $run->correlation_id;
		}

		if ( '' !== $correlation_id ) {
			AIPS_Correlation_ID::set( $correlation_id );
		} else {
			$correlation_id = AIPS_Correlation_ID::generate();
		}

		if ( ! AIPS_Ability_Workflows_Feature::is_enabled() ) {
			$this->repository->update_run_status( $run_id, AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED, array( 'finished_at' => time() ) );
			$this->terminate_history( $run, __( 'Ability Workflows were disabled while this run was queued.', 'ai-post-scheduler' ) );
			AIPS_Correlation_ID::reset();

			return $this->result( self::RESULT_FINISHED );
		}

		if ( !$this->acquire_run_lock( $run_id ) ) {
			// Another worker holds the run (or a crashed one holds the lock until
			// it expires). The caller looks again after the retry delay, so the
			// run is not dropped.
			AIPS_Correlation_ID::reset();

			return $this->result( self::RESULT_BUSY, time() + self::BUSY_RETRY_SECONDS );
		}

		$history          = false;
		$previous_user_id = get_current_user_id();
		$result           = $this->result( self::RESULT_FINISHED );

		try {
			$history = $this->resolve_history( $run );

			$principal = $this->establish_execution_principal( $run_id );

			if ( is_wp_error( $principal ) ) {
				if ( $history ) {
					$history->complete_failure( $principal->get_error_message(), array( 'error_code' => $principal->get_error_code() ) );
				}

				$this->repository->update_run_status(
					$run_id,
					AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED,
					array( 'finished_at' => time() )
				);

				return $result;
			}

			$result = $this->run_internal( $run_id, $correlation_id, $history );
		} catch ( \Throwable $e ) {
			$this->logger->log(
				'Ability workflow run failed with unexpected error: ' . $e->getMessage(),
				'error',
				array( 'run_id' => $run_id, 'trace' => $e->getTraceAsString() )
			);

			if ( $history ) {
				$history->complete_failure(
					sprintf( __( 'Unexpected error: %s', 'ai-post-scheduler' ), $e->getMessage() ),
					array( 'exception_class' => get_class( $e ) )
				);
			}

			$this->repository->update_run_status(
				$run_id,
				AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED,
				array( 'finished_at' => time() )
			);

			$result = $this->result( self::RESULT_FINISHED, 0, true );
		} finally {
			wp_set_current_user( $previous_user_id );
			$this->release_run_lock( $run_id );
			AIPS_Correlation_ID::reset();
		}

		return $result;
	}

	/**
	 * Ask the background process to pick up queued runs.
	 *
	 * @return true|WP_Error
	 */
	private function wake_process() {
		if ( $this->kick ) {
			$result = call_user_func( $this->kick );

			return is_wp_error( $result ) ? $result : true;
		}

		$process = AIPS_Container::get_instance()->make( AIPS_Background_Process_Manager::class )->get( AIPS_Ability_Workflows_Process::KEY );

		if ( ! $process instanceof AIPS_Ability_Workflows_Process ) {
			return new WP_Error( 'ability_workflow_dispatch_failed', __( 'The workflow background process is not available.', 'ai-post-scheduler' ) );
		}

		return $process->kick();
	}

	/**
	 * Build a process_run()/run_internal() result.
	 *
	 * @param string $state    One of the RESULT_* constants.
	 * @param int    $retry_at Unix time at which the run is worth calling again (0 = as soon as convenient).
	 * @param bool   $worked   Whether this call executed or skipped at least one step.
	 * @return array{state:string, retry_at:int, worked:bool}
	 */
	private function result( string $state, int $retry_at = 0, bool $worked = false ): array {
		return array(
			'state'    => $state,
			'retry_at' => $retry_at,
			'worked'   => $worked,
		);
	}

	/**
	 * Get the History container that records a run, creating it on the first
	 * invocation. Later invocations (time-budget continuations and retries)
	 * resume the same container, so one run is one History row that is closed
	 * exactly once, when the run reaches a terminal status.
	 *
	 * @param AIPS_Ability_Workflow_Run $run Run being processed.
	 * @return AIPS_History_Container|false
	 */
	private function resolve_history( AIPS_Ability_Workflow_Run $run ) {
		if ( $run->history_id ) {
			$existing = AIPS_History_Container::load_existing( AIPS_History_Repository::instance(), $run->history_id );

			if ( $existing ) {
				return $existing;
			}
		}

		$history = $this->history_service->create(
			'ability_workflow_run',
			array( 'run_id' => $run->id, 'creation_method' => 'ability_workflow_run' )
		);

		if ( $history && $history->get_id() ) {
			$this->repository->set_run_history_id( $run->id, (int) $history->get_id() );
		}

		return $history;
	}

	/**
	 * Close a run's History row as terminated (cancelled, disabled, ...).
	 *
	 * @param AIPS_Ability_Workflow_Run $run     Run whose History to close.
	 * @param string                    $message Reason shown in History.
	 * @return void
	 */
	private function terminate_history( AIPS_Ability_Workflow_Run $run, string $message ): void {
		if ( ! $run->history_id ) {
			return;
		}

		$history = AIPS_History_Container::load_existing( AIPS_History_Repository::instance(), $run->history_id );

		if ( $history ) {
			$history->complete_terminated( $message );
		}
	}

	/**
	 * Whether the run was cancelled while this invocation was working.
	 *
	 * @param int $run_id Run ID.
	 * @return bool
	 */
	private function is_run_cancelled( int $run_id ): bool {
		$run = $this->repository->get_run( $run_id );

		return $run && AIPS_Ability_Workflow_Repository::RUN_STATUS_CANCELLED === $run->status;
	}

	/**
	 * Option name of a run's lock.
	 *
	 * @param int $run_id Workflow run ID.
	 * @return string
	 */
	private function run_lock_key( int $run_id ): string {
		return 'aips_ability_workflow_lock_' . $run_id;
	}

	/**
	 * Acquire an atomic, expiring lock for a workflow run.
	 *
	 * The repository claims the lock with an INSERT IGNORE on the unique
	 * option_name key and takes an expired lock over with a compare-and-swap,
	 * so two cron workers cannot both win it.
	 *
	 * @param int $run_id Workflow run ID.
	 * @return bool
	 */
	private function acquire_run_lock( int $run_id ): bool {
		$token = wp_generate_uuid4();

		if ( ! $this->repository->acquire_lock( $this->run_lock_key( $run_id ), $token, self::RUN_LOCK_TTL_SECONDS ) ) {
			return false;
		}

		$this->run_locks[ $run_id ] = $token;

		return true;
	}

	/**
	 * Extend the lock this worker holds. A step can take a while, and the
	 * lock must not expire under a worker that is still making progress.
	 *
	 * @param int $run_id Workflow run ID.
	 * @return bool False when the lock is no longer ours.
	 */
	private function refresh_run_lock( int $run_id ): bool {
		return isset( $this->run_locks[ $run_id ] )
			&& $this->repository->refresh_lock( $this->run_lock_key( $run_id ), $this->run_locks[ $run_id ], self::RUN_LOCK_TTL_SECONDS );
	}

	/**
	 * Release a workflow run lock.
	 *
	 * @param int $run_id Workflow run ID.
	 * @return void
	 */
	private function release_run_lock( int $run_id ): void {
		if ( isset( $this->run_locks[ $run_id ] ) ) {
			$this->repository->release_lock( $this->run_lock_key( $run_id ), $this->run_locks[ $run_id ] );
		}

		unset( $this->run_locks[ $run_id ] );
	}

	/**
	 * Restore and re-authorize the user who dispatched a queued run.
	 *
	 * Ability permission callbacks execute in the current WordPress user
	 * context. Cron starts as user 0, so failing to restore the immutable run
	 * principal makes permission-aware abilities fail and leaves the security
	 * boundary undefined. The filter supports an explicitly configured service
	 * user for system-triggered runs; it must never silently elevate to an
	 * administrator.
	 *
	 * @param int $run_id Workflow run ID.
	 * @return int|WP_Error Effective user ID, or an authorization error.
	 */
	private function establish_execution_principal( int $run_id ) {
		$run = $this->repository->get_run( $run_id );

		if ( !$run ) {
			return new WP_Error( 'ability_workflow_run_not_found', __( 'Workflow run not found.', 'ai-post-scheduler' ) );
		}

		$user_id = (int) $run->created_by;

		if ( $user_id <= 0 ) {
			/**
			 * Filters the service user used for a system-triggered workflow run.
			 *
			 * @param int                       $user_id Service user ID. Default 0.
			 * @param AIPS_Ability_Workflow_Run $run     Workflow run.
			 */
			$user_id = (int) apply_filters( 'aips_ability_workflow_execution_user_id', 0, $run );
		}

		$user = $user_id > 0 ? get_userdata( $user_id ) : false;

		if ( !$user ) {
			return new WP_Error( 'ability_workflow_principal_missing', __( 'No valid execution user is configured for this workflow run.', 'ai-post-scheduler' ) );
		}

		if ( !user_can( $user, 'manage_options' ) ) {
			return new WP_Error( 'ability_workflow_principal_forbidden', __( 'The workflow execution user is no longer allowed to manage workflows.', 'ai-post-scheduler' ) );
		}

		wp_set_current_user( $user_id );

		return $user_id;
	}

	// -----------------------------------------------------------------------
	// Internals
	// -----------------------------------------------------------------------

	/**
	 * Execute (or resume) a run's steps within this slice's time budget.
	 *
	 * @param int                    $run_id         Run ID.
	 * @param string                 $correlation_id Correlation ID.
	 * @param AIPS_History_Container $history        History container for the run.
	 * @return array{state:string, retry_at:int, worked:bool}
	 */
	private function run_internal( int $run_id, string $correlation_id, $history ): array {
		$run = $this->repository->get_run( $run_id );

		if ( ! $run || in_array( $run->status, AIPS_Ability_Workflow_Repository::RUN_TERMINAL_STATUSES, true ) ) {
			return $this->result( self::RESULT_FINISHED );
		}

		$workflow = $this->repository->get_workflow( $run->workflow_id );

		if ( ! $workflow ) {
			$this->finish_run( $run_id, AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED, $history, __( 'Workflow no longer exists.', 'ai-post-scheduler' ) );
			return $this->result( self::RESULT_FINISHED, 0, true );
		}

		if ( in_array( $workflow->status, self::NON_RUNNABLE_STATUSES, true ) ) {
			$this->finish_run( $run_id, AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED, $history, __( 'Workflow was archived or paused while this run was in progress.', 'ai-post-scheduler' ) );
			return $this->result( self::RESULT_FINISHED, 0, true );
		}

		// Steps and settings are read live, so a run must not continue against
		// a definition that changed after it was dispatched: step runs are keyed
		// by step_key and a renamed, reordered or re-gated step would be
		// skipped, repeated or run under different rules.
		if ( (int) $workflow->version !== (int) $run->workflow_version ) {
			$this->finish_run( $run_id, AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED, $history, __( 'Workflow was modified while this run was in progress.', 'ai-post-scheduler' ) );
			return $this->result( self::RESULT_FINISHED, 0, true );
		}

		$existing_step_runs = $this->repository->get_step_runs( $run_id );

		// A step waiting out its retry backoff is not due yet: report when it
		// will be so the caller does not spin on this run.
		$retry_at = $this->pending_retry_at( $existing_step_runs );

		if ( $retry_at > time() ) {
			return $this->result( self::RESULT_CONTINUE, $retry_at );
		}

		if ( AIPS_Ability_Workflow_Repository::RUN_STATUS_QUEUED === $run->status ) {
			$this->repository->update_run_status( $run_id, AIPS_Ability_Workflow_Repository::RUN_STATUS_RUNNING, array( 'started_at' => time() ) );
		}

		$steps = $this->repository->get_steps( $run->workflow_id );

		$settings            = $workflow->settings;
		$max_steps           = isset( $settings['max_steps'] ) ? (int) $settings['max_steps'] : 20;
		$max_runtime_seconds = isset( $settings['max_runtime_seconds'] ) ? (int) $settings['max_runtime_seconds'] : 120;
		$allow_destructive   = ! empty( $settings['allow_destructive_abilities'] );

		if ( count( $steps ) > $max_steps ) {
			$this->finish_run( $run_id, AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED, $history, __( 'Workflow exceeds the configured max_steps limit.', 'ai-post-scheduler' ) );
			return $this->result( self::RESULT_FINISHED, 0, true );
		}

		$time_budget = max( 1, min( $max_runtime_seconds, self::DEFAULT_TIME_BUDGET_SECONDS ) );
		$deadline    = microtime( true ) + $time_budget;
		$worked      = false;

		$step_run_by_key = array();
		$resolved_status  = array();
		foreach ( $existing_step_runs as $step_run ) {
			$step_run_by_key[ $step_run->step_key ] = $step_run;
			if ( in_array( $step_run->status, self::RESOLVED_STEP_STATUSES, true ) ) {
				$resolved_status[ $step_run->step_key ] = $step_run->status;
			}
		}

		$variables = $this->build_variables( $run, $steps, $existing_step_runs );

		// Rebuilt from persisted step statuses/strategies on every slice (not
		// just accumulated within this pass) so a 'skip' cascade triggered by an
		// earlier slice isn't lost if the run was interrupted (time budget /
		// retry backoff) before every dependent step in the cascade had been
		// reached.
		$skip_keys = $this->rebuild_skip_cascade( $steps, $resolved_status );

		foreach ( $steps as $step ) {
			if ( isset( $resolved_status[ $step->step_key ] ) ) {
				continue;
			}

			if ( $this->is_run_cancelled( $run_id ) ) {
				// cancel_run() already closed the History row; this covers a
				// cancellation that arrived by another route.
				if ( $history ) {
					$history->complete_terminated( __( 'Workflow run cancelled.', 'ai-post-scheduler' ) );
				}
				return $this->result( self::RESULT_FINISHED, 0, $worked );
			}

			if ( ! $this->refresh_run_lock( $run_id ) ) {
				// Another worker took the run over; two workers must not run steps.
				return $this->result( self::RESULT_BUSY, time() + self::BUSY_RETRY_SECONDS, $worked );
			}

			if ( microtime( true ) >= $deadline ) {
				if ( $history ) {
					$history->record( 'activity', __( 'Time budget reached; the run continues in the next slice.', 'ai-post-scheduler' ) );
				}
				return $this->result( self::RESULT_CONTINUE, time() + self::CONTINUATION_DELAY_SECONDS, $worked );
			}

			$deps_satisfied = true;
			foreach ( $step->depends_on as $dep_key ) {
				if ( ( $resolved_status[ $dep_key ] ?? '' ) !== 'completed' ) {
					$deps_satisfied = false;
					break;
				}
			}

			if ( isset( $skip_keys[ $step->step_key ] ) || ! $deps_satisfied ) {
				$this->record_skipped_step( $run_id, $run->workflow_id, $step, $history );
				$resolved_status[ $step->step_key ] = 'skipped';
				$worked = true;
				continue;
			}

			if ( ! $this->condition_evaluator->evaluate( $step->condition_tree, $variables ) ) {
				$this->record_skipped_step( $run_id, $run->workflow_id, $step, $history );
				$resolved_status[ $step->step_key ] = 'skipped';
				$worked = true;
				continue;
			}

			$outcome = $this->execute_step(
				$run_id,
				$run->workflow_id,
				$step,
				$variables,
				$allow_destructive,
				$history,
				$step_run_by_key[ $step->step_key ] ?? null
			);
			$worked = true;

			if ( ! empty( $outcome['retry_scheduled'] ) ) {
				return $this->result( self::RESULT_CONTINUE, (int) $outcome['retry_at'], true );
			}

			$resolved_status[ $step->step_key ] = $outcome['status'];

			if ( 'completed' === $outcome['status'] ) {
				$alias = $step->output_alias ?: $step->step_key;
				$variables['steps'][ $alias ] = array(
					'output' => $outcome['output'],
					'status' => 'completed',
				);

				$strategy = isset( $step->on_success['strategy'] ) ? $step->on_success['strategy'] : 'continue';

				if ( 'stop' === $strategy ) {
					$this->finish_run( $run_id, AIPS_Ability_Workflow_Repository::RUN_STATUS_COMPLETED, $history );
					return $this->result( self::RESULT_FINISHED, 0, true );
				}

				if ( 'skip' === $strategy ) {
					$this->mark_dependents_skipped( $step->step_key, $steps, $skip_keys );
				}
			} else {
				$strategy = isset( $step->on_failure['strategy'] ) ? $step->on_failure['strategy'] : 'stop';

				if ( 'stop' === $strategy ) {
					$this->finish_run(
						$run_id,
						AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED,
						$history,
						/* translators: %s: step key */
						sprintf( __( 'Step "%s" failed.', 'ai-post-scheduler' ), $step->step_key )
					);
					return $this->result( self::RESULT_FINISHED, 0, true );
				}

				if ( 'skip' === $strategy ) {
					$this->mark_dependents_skipped( $step->step_key, $steps, $skip_keys );
				}
			}
		}

		$this->finish_run( $run_id, AIPS_Ability_Workflow_Repository::RUN_STATUS_COMPLETED, $history );

		return $this->result( self::RESULT_FINISHED, 0, true );
	}

	/**
	 * When the earliest pending retry becomes due (0 when nothing is waiting).
	 *
	 * @param AIPS_Ability_Workflow_Step_Run[] $step_runs Step runs of the run.
	 * @return int
	 */
	private function pending_retry_at( array $step_runs ): int {
		$retry_at = 0;

		foreach ( $step_runs as $step_run ) {
			if ( 'pending' === $step_run->status && ! empty( $step_run->error['next_retry_at'] ) ) {
				$due      = (int) $step_run->error['next_retry_at'];
				$retry_at = 0 === $retry_at ? $due : min( $retry_at, $due );
			}
		}

		return $retry_at;
	}

	/**
	 * Execute a single step: resolve input, enforce destructive-ability
	 * gating, invoke the ability, persist the outcome. Reuses an in-progress
	 * step-run row across retries rather than creating a duplicate.
	 *
	 * @param int                              $run_id            Run ID.
	 * @param int                              $workflow_id       Workflow ID.
	 * @param AIPS_Ability_Workflow_Step       $step              Step to execute.
	 * @param array                            $variables         Current variable bag.
	 * @param bool                             $allow_destructive Whether destructive abilities are allowed.
	 * @param AIPS_History_Container|false     $history           History container for the run.
	 * @param AIPS_Ability_Workflow_Step_Run|null $existing_step_run Existing step-run row, when resuming a retry.
	 * @return array { status: string, output: array, retry_scheduled: bool, retry_at?: int }
	 */
	private function execute_step( int $run_id, int $workflow_id, AIPS_Ability_Workflow_Step $step, array $variables, bool $allow_destructive, $history, ?AIPS_Ability_Workflow_Step_Run $existing_step_run = null ): array {
		$resolved_input = $this->resolver->resolve_map( $step->input_map, $variables );

		if ( $existing_step_run ) {
			$attempt     = (int) ( $existing_step_run->error['attempts'] ?? 0 ) + 1;
			$step_run_id = $existing_step_run->id;
			$this->repository->update_step_run_status( $step_run_id, 'running', array( 'input_snapshot' => $resolved_input, 'started_at' => time() ) );
		} else {
			$attempt     = 1;
			$step_run_id = $this->repository->create_step_run( $run_id, $workflow_id, $step, $resolved_input );

			if ( is_wp_error( $step_run_id ) ) {
				return array( 'status' => 'failed', 'output' => array(), 'retry_scheduled' => false );
			}

			$this->repository->update_step_run_status( $step_run_id, 'running', array( 'started_at' => time() ) );
		}

		$ability = $this->catalog->get_ability( $step->ability_name );

		if ( is_wp_error( $ability ) ) {
			return $this->fail_step( $run_id, $step, $step_run_id, $ability, $attempt, $history );
		}

		$requires_approval = array_key_exists( 'requires_approval', $ability )
			? ! empty( $ability['requires_approval'] )
			: ! empty( $ability['is_destructive'] );

		if ( $requires_approval && ! $allow_destructive ) {
			$message = ! empty( $ability['is_destructive'] )
				? __( 'Destructive abilities are not allowed for this workflow.', 'ai-post-scheduler' )
				: __( 'This ability does not declare whether it is destructive, so it is not allowed for this workflow. Enable "Allow destructive abilities" to run it.', 'ai-post-scheduler' );

			return $this->fail_step( $run_id, $step, $step_run_id, new WP_Error( 'ability_workflow_destructive_forbidden', $message ), $attempt, $history );
		}

		if ( $history ) {
			/* translators: 1: ability name, 2: step key */
			$history->record(
				'ai_request',
				sprintf( __( 'Invoking ability "%1$s" for step "%2$s"', 'ai-post-scheduler' ), $step->ability_name, $step->step_key ),
				array( 'input_keys' => array_keys( $resolved_input ) )
			);
		}

		$response = $this->ability_service->invoke( $step->ability_name, $resolved_input );

		if ( is_wp_error( $response ) ) {
			if ( $history ) {
				$history->record( 'error', $response->get_error_message(), array( 'error_code' => $response->get_error_code() ) );
			}

			return $this->fail_step( $run_id, $step, $step_run_id, $response, $attempt, $history );
		}

		if ( $history ) {
			/* translators: 1: ability name, 2: step key */
			$history->record(
				'ai_response',
				sprintf( __( 'Ability "%1$s" completed for step "%2$s"', 'ai-post-scheduler' ), $step->ability_name, $step->step_key ),
				null,
				array( 'output_keys' => array_keys( $response ) )
			);
		}

		$this->repository->update_step_run_status( $step_run_id, 'completed', array( 'output_snapshot' => $response, 'finished_at' => time() ) );

		return array( 'status' => 'completed', 'output' => $response, 'retry_scheduled' => false );
	}

	/**
	 * Handle a failed step attempt: park the step for a backoff retry when
	 * the retry_policy allows another attempt, otherwise mark the step failed.
	 *
	 * @param int                        $run_id      Run ID.
	 * @param AIPS_Ability_Workflow_Step $step        Step that failed.
	 * @param int                        $step_run_id Step-run row ID.
	 * @param string|WP_Error            $failure     Failure message or structured error.
	 * @param int                        $attempt     Attempt number just made.
	 * @param AIPS_History_Container|false $history   History container.
	 * @return array { status: string, output: array, retry_scheduled: bool, retry_at?: int }
	 */
	private function fail_step( int $run_id, AIPS_Ability_Workflow_Step $step, int $step_run_id, $failure, int $attempt, $history ): array {
		$error_code     = is_wp_error( $failure ) ? $failure->get_error_code() : 'ability_workflow_step_failed';
		$message        = is_wp_error( $failure ) ? $failure->get_error_message() : (string) $failure;
		$raw_error_data = is_wp_error( $failure ) ? $failure->get_error_data() : null;
		$error_data     = is_array( $raw_error_data ) && isset( $raw_error_data['status'] )
			? array( 'status' => (int) $raw_error_data['status'] )
			: null;
		$max_attempts    = isset( $step->retry_policy['attempts'] ) ? (int) $step->retry_policy['attempts'] : 0;
		$backoff_seconds = isset( $step->retry_policy['backoff_seconds'] ) ? max( 1, (int) $step->retry_policy['backoff_seconds'] ) : 5;
		$permanent_error_codes = array(
			'ability_invalid_input',
			'ability_invalid_permissions',
			'ability_workflow_destructive_forbidden',
			'content_not_provided',
			'insufficient_capabilities',
			'post_not_found',
		);
		$is_permanent = in_array( $error_code, $permanent_error_codes, true );

		if ( !$is_permanent && $attempt <= $max_attempts ) {
			$retry_at = time() + $backoff_seconds;

			$this->repository->update_step_run_status(
				$step_run_id,
				'pending',
				array(
					'error'       => array( 'code' => $error_code, 'message' => $message, 'data' => $error_data, 'attempts' => $attempt, 'next_retry_at' => $retry_at ),
					'finished_at' => 0,
				)
			);

			if ( $history ) {
				/* translators: 1: step key, 2: attempt number, 3: seconds */
				$history->record( 'activity', sprintf( __( 'Step "%1$s" failed (attempt %2$d); retrying in %3$d s.', 'ai-post-scheduler' ), $step->step_key, $attempt, $backoff_seconds ) );
			}

			return array( 'status' => 'failed', 'output' => array(), 'retry_scheduled' => true, 'retry_at' => $retry_at );
		}

		$this->repository->update_step_run_status(
			$step_run_id,
			'failed',
			array(
				'error'       => array( 'code' => $error_code, 'message' => $message, 'data' => $error_data, 'attempts' => $attempt ),
				'finished_at' => time(),
			)
		);

		return array( 'status' => 'failed', 'output' => array(), 'retry_scheduled' => false );
	}

	/**
	 * Persist a skipped-step row (dependency unmet, condition false, or
	 * cascaded from an upstream 'skip' strategy).
	 *
	 * @param int                        $run_id      Run ID.
	 * @param int                        $workflow_id Workflow ID.
	 * @param AIPS_Ability_Workflow_Step $step        Step being skipped.
	 * @param AIPS_History_Container|false $history   History container.
	 * @return void
	 */
	private function record_skipped_step( int $run_id, int $workflow_id, AIPS_Ability_Workflow_Step $step, $history ): void {
		$step_run_id = $this->repository->create_step_run( $run_id, $workflow_id, $step, array() );

		if ( ! is_wp_error( $step_run_id ) ) {
			$this->repository->update_step_run_status( $step_run_id, 'skipped', array( 'started_at' => time(), 'finished_at' => time() ) );
		}

		if ( $history ) {
			/* translators: %s: step key */
			$history->record( 'activity', sprintf( __( 'Step "%s" skipped', 'ai-post-scheduler' ), $step->step_key ) );
		}
	}

	/**
	 * Mark every step that transitively depends on $step_key as skipped for
	 * the remainder of this run, without touching independent branches.
	 *
	 * @param string                       $step_key  Step key whose dependents should be skipped.
	 * @param AIPS_Ability_Workflow_Step[] $steps     All steps for the workflow.
	 * @param array                        $skip_keys Accumulator, passed by reference.
	 * @return void
	 */
	private function mark_dependents_skipped( string $step_key, array $steps, array &$skip_keys ): void {
		foreach ( $steps as $candidate ) {
			if ( in_array( $step_key, $candidate->depends_on, true ) && ! isset( $skip_keys[ $candidate->step_key ] ) ) {
				$skip_keys[ $candidate->step_key ] = true;
				$this->mark_dependents_skipped( $candidate->step_key, $steps, $skip_keys );
			}
		}
	}

	/**
	 * Reconstruct the full skip-cascade set from persisted step statuses and
	 * strategies. Called at the start of every run_internal() invocation so
	 * that a 'skip' strategy's cascade is idempotently rebuilt from durable
	 * state rather than relying on the in-memory accumulation of a single
	 * pass through the steps loop — a run can span multiple cron
	 * invocations (time budget continuations, retry backoff), and without
	 * this, a cascade only partially applied before an interruption would
	 * be lost, letting a step that should stay permanently skipped execute
	 * on a later invocation.
	 *
	 * @param AIPS_Ability_Workflow_Step[] $steps           All steps for the workflow.
	 * @param array                        $resolved_status Map of step_key => resolved status ('completed'/'failed'/'skipped').
	 * @return array Map of step_key => true for every step that must be treated as skipped.
	 */
	private function rebuild_skip_cascade( array $steps, array $resolved_status ): array {
		$skip_keys = array();

		foreach ( $steps as $step ) {
			$status = $resolved_status[ $step->step_key ] ?? '';

			if ( 'completed' === $status ) {
				$strategy = isset( $step->on_success['strategy'] ) ? $step->on_success['strategy'] : 'continue';
			} elseif ( 'failed' === $status ) {
				$strategy = isset( $step->on_failure['strategy'] ) ? $step->on_failure['strategy'] : 'stop';
			} else {
				continue;
			}

			if ( 'skip' === $strategy ) {
				$this->mark_dependents_skipped( $step->step_key, $steps, $skip_keys );
			}
		}

		return $skip_keys;
	}

	/**
	 * Build the run's variable bag from trigger context + prior completed step outputs.
	 *
	 * @param AIPS_Ability_Workflow_Run        $run       The run.
	 * @param AIPS_Ability_Workflow_Step[]      $steps     All steps for the workflow (for output_alias lookup).
	 * @param AIPS_Ability_Workflow_Step_Run[]  $step_runs Existing step-run rows.
	 * @return array { trigger: array, steps: array }
	 */
	private function build_variables( AIPS_Ability_Workflow_Run $run, array $steps, array $step_runs ): array {
		$steps_by_key = array();
		foreach ( $steps as $step ) {
			$steps_by_key[ $step->step_key ] = $step;
		}

		$variables = array(
			'trigger' => $run->trigger_context,
			'steps'   => array(),
		);

		foreach ( $step_runs as $step_run ) {
			if ( 'completed' !== $step_run->status ) {
				continue;
			}

			$step  = $steps_by_key[ $step_run->step_key ] ?? null;
			$alias = ( $step && $step->output_alias ) ? $step->output_alias : $step_run->step_key;

			$variables['steps'][ $alias ] = array(
				'output' => $step_run->output_snapshot,
				'status' => $step_run->status,
			);
		}

		return $variables;
	}

	/**
	 * Finalize a run's terminal status and complete its history container.
	 *
	 * @param int                          $run_id  Run ID.
	 * @param string                       $status  Terminal status.
	 * @param AIPS_History_Container|false $history History container.
	 * @param string                       $failure_message Optional failure message when $status is 'failed'.
	 * @return void
	 */
	private function finish_run( int $run_id, string $status, $history, string $failure_message = '' ): void {
		$this->repository->update_run_status( $run_id, $status, array( 'finished_at' => time() ) );

		if ( ! $history ) {
			return;
		}

		if ( AIPS_Ability_Workflow_Repository::RUN_STATUS_COMPLETED === $status ) {
			$history->complete_success();
		} else {
			$history->complete_failure( '' !== $failure_message ? $failure_message : __( 'Workflow run failed.', 'ai-post-scheduler' ) );
		}
	}
}
