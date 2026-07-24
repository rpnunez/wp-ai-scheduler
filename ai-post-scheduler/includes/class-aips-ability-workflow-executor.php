<?php
/**
 * Ability Workflow Executor
 *
 * Runs a single Ability Workflow run: resolves step inputs against a
 * run-scoped variable bag, evaluates AND/OR conditions, invokes abilities
 * via AIPS_Ability_Service, persists per-step results, and self-chains via
 * a dedicated cron hook when it runs out of time budget or a step needs a
 * backoff retry.
 *
 * A workflow run is NOT modeled as a bulk-batch job — bulk batch jobs are
 * flat, order-independent item lists dispatched as pre-scheduled parallel
 * slices, whereas workflow steps run in dependency order with conditional
 * branching that can only be evaluated once earlier steps have produced
 * output. Instead this class registers its own single-event hook
 * (self::HOOK) and reschedules itself to continue where it left off.
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
	 * Single-event cron hook this executor's process_run() is bound to.
	 */
	const HOOK = 'aips_run_ability_workflow';

	/**
	 * Default wall-clock budget (seconds) for a single cron invocation before
	 * scheduling a continuation event instead of pushing past PHP's
	 * max_execution_time.
	 */
	const DEFAULT_TIME_BUDGET_SECONDS = 20;

	/**
	 * Delay (seconds) before a continuation event fires when the time budget
	 * for the current invocation is exhausted mid-run.
	 */
	const CONTINUATION_DELAY_SECONDS = 2;

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
	 * @var AIPS_Job_Scheduler
	 */
	private $job_scheduler;

	/**
	 * @var AIPS_History_Service_Interface
	 */
	private $history_service;

	/**
	 * @var AIPS_Logger_Interface
	 */
	private $logger;

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
		?AIPS_Job_Scheduler $job_scheduler = null,
		?AIPS_History_Service_Interface $history_service = null,
		?AIPS_Logger_Interface $logger = null
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
		$this->job_scheduler       = $job_scheduler ?: new AIPS_Job_Scheduler();

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
	 * Dispatch a workflow run to cron. Never executes synchronously — this
	 * is what "Run Now" and scheduled triggers both call.
	 *
	 * @param int   $workflow_id     Workflow ID.
	 * @param array $trigger_context Trigger context payload (e.g. manual user, or scheduled trigger config).
	 * @return int|WP_Error New run ID, or WP_Error.
	 */
	public function dispatch_run( int $workflow_id, array $trigger_context = array() ) {
		$workflow = $this->repository->get_workflow( $workflow_id );

		if ( ! $workflow ) {
			return new WP_Error( 'ability_workflow_not_found', __( 'Workflow not found.', 'ai-post-scheduler' ) );
		}

		$correlation_id = AIPS_Correlation_ID::generate();

		$run_id = $this->repository->create_run( $workflow_id, $workflow->version, $trigger_context, $correlation_id );

		AIPS_Correlation_ID::reset();

		if ( is_wp_error( $run_id ) ) {
			return $run_id;
		}

		$scheduled = $this->job_scheduler->schedule_simple(
			self::HOOK,
			time(),
			array( $run_id, $correlation_id ),
			array(
				'job_type'       => 'ability_workflow_run',
				'correlation_id' => $correlation_id,
				'metadata'       => array( 'workflow_id' => $workflow_id, 'run_id' => $run_id ),
			)
		);

		if ( ! $scheduled ) {
			// Mark the already-created run row failed instead of leaving it
			// orphaned at 'queued' forever with nothing to ever process it.
			$this->repository->update_run_status(
				$run_id,
				AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED,
				array( 'finished_at' => time() )
			);

			return new WP_Error( 'ability_workflow_dispatch_failed', __( 'Failed to schedule workflow run.', 'ai-post-scheduler' ) );
		}

		return $run_id;
	}

	/**
	 * Cron callback bound to self::HOOK. Executes (or resumes) a run.
	 *
	 * @param int    $run_id         Run ID.
	 * @param string $correlation_id Correlation ID to resume under.
	 * @return void
	 */
	public function process_run( int $run_id, string $correlation_id = '' ): void {
		if ( '' !== $correlation_id ) {
			AIPS_Correlation_ID::set( $correlation_id );
		} else {
			$correlation_id = AIPS_Correlation_ID::generate();
		}

		$history = $this->history_service->create(
			'ability_workflow_run',
			array( 'run_id' => $run_id, 'creation_method' => 'ability_workflow_run' )
		);

		try {
			$this->run_internal( $run_id, $correlation_id, $history );
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
		} finally {
			AIPS_Correlation_ID::reset();
		}
	}

	// -----------------------------------------------------------------------
	// Internals
	// -----------------------------------------------------------------------

	/**
	 * Execute (or resume) a run's steps within this invocation's time budget.
	 *
	 * @param int                      $run_id         Run ID.
	 * @param string                   $correlation_id Correlation ID.
	 * @param AIPS_History_Container   $history        History container for this invocation.
	 * @return void
	 */
	private function run_internal( int $run_id, string $correlation_id, $history ): void {
		$run = $this->repository->get_run( $run_id );

		if ( ! $run || in_array( $run->status, AIPS_Ability_Workflow_Repository::RUN_TERMINAL_STATUSES, true ) ) {
			return;
		}

		$workflow = $this->repository->get_workflow( $run->workflow_id );

		if ( ! $workflow ) {
			$this->repository->update_run_status( $run_id, AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED, array( 'finished_at' => time() ) );
			return;
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
			return;
		}

		$time_budget = max( 1, min( $max_runtime_seconds, self::DEFAULT_TIME_BUDGET_SECONDS ) );
		$deadline    = microtime( true ) + $time_budget;

		$existing_step_runs = $this->repository->get_step_runs( $run_id );

		$step_run_by_key = array();
		$resolved_status  = array();
		foreach ( $existing_step_runs as $step_run ) {
			$step_run_by_key[ $step_run->step_key ] = $step_run;
			if ( in_array( $step_run->status, self::RESOLVED_STEP_STATUSES, true ) ) {
				$resolved_status[ $step_run->step_key ] = $step_run->status;
			}
		}

		$variables = $this->build_variables( $run, $steps, $existing_step_runs );

		// Rebuilt from persisted step statuses/strategies on every
		// invocation (not just accumulated within this pass) so a 'skip'
		// cascade triggered by an earlier invocation isn't lost if the run
		// was interrupted (time budget / retry backoff) before every
		// dependent step in the cascade had been reached.
		$skip_keys = $this->rebuild_skip_cascade( $steps, $resolved_status );

		foreach ( $steps as $step ) {
			if ( isset( $resolved_status[ $step->step_key ] ) ) {
				continue;
			}

			if ( microtime( true ) >= $deadline ) {
				if ( ! $this->schedule_continuation( $run_id, $correlation_id, self::CONTINUATION_DELAY_SECONDS ) ) {
					// Without a scheduled continuation nothing will ever
					// resume this run — fail it now instead of leaving it
					// stuck at 'running' indefinitely.
					$this->finish_run( $run_id, AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED, $history, __( 'Failed to schedule workflow continuation.', 'ai-post-scheduler' ) );
				}
				return;
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
				continue;
			}

			if ( ! $this->condition_evaluator->evaluate( $step->condition_tree, $variables ) ) {
				$this->record_skipped_step( $run_id, $run->workflow_id, $step, $history );
				$resolved_status[ $step->step_key ] = 'skipped';
				continue;
			}

			$outcome = $this->execute_step(
				$run_id,
				$run->workflow_id,
				$step,
				$variables,
				$allow_destructive,
				$history,
				$correlation_id,
				$step_run_by_key[ $step->step_key ] ?? null
			);

			if ( ! empty( $outcome['retry_scheduled'] ) ) {
				return;
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
					return;
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
					return;
				}

				if ( 'skip' === $strategy ) {
					$this->mark_dependents_skipped( $step->step_key, $steps, $skip_keys );
				}
			}
		}

		$this->finish_run( $run_id, AIPS_Ability_Workflow_Repository::RUN_STATUS_COMPLETED, $history );
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
	 * @param AIPS_History_Container|false     $history           History container for this invocation.
	 * @param string                           $correlation_id    Correlation ID for this run, threaded into any retry dispatch.
	 * @param AIPS_Ability_Workflow_Step_Run|null $existing_step_run Existing step-run row, when resuming a retry.
	 * @return array { status: string, output: array, retry_scheduled: bool }
	 */
	private function execute_step( int $run_id, int $workflow_id, AIPS_Ability_Workflow_Step $step, array $variables, bool $allow_destructive, $history, string $correlation_id, ?AIPS_Ability_Workflow_Step_Run $existing_step_run = null ): array {
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
			return $this->fail_step( $run_id, $step, $step_run_id, $ability->get_error_message(), $attempt, $history, $correlation_id );
		}

		if ( ! empty( $ability['is_destructive'] ) && ! $allow_destructive ) {
			return $this->fail_step( $run_id, $step, $step_run_id, __( 'Destructive abilities are not allowed for this workflow.', 'ai-post-scheduler' ), $attempt, $history, $correlation_id );
		}

		if ( $history ) {
			/* translators: 1: ability name, 2: step key */
			$history->record( 'ai_request', sprintf( __( 'Invoking ability "%1$s" for step "%2$s"', 'ai-post-scheduler' ), $step->ability_name, $step->step_key ), $resolved_input );
		}

		$response = $this->ability_service->invoke( $step->ability_name, $resolved_input );

		if ( is_wp_error( $response ) ) {
			if ( $history ) {
				$history->record( 'error', $response->get_error_message(), $resolved_input );
			}

			return $this->fail_step( $run_id, $step, $step_run_id, $response->get_error_message(), $attempt, $history, $correlation_id );
		}

		if ( $history ) {
			/* translators: 1: ability name, 2: step key */
			$history->record( 'ai_response', sprintf( __( 'Ability "%1$s" completed for step "%2$s"', 'ai-post-scheduler' ), $step->ability_name, $step->step_key ), null, $response );
		}

		$this->repository->update_step_run_status( $step_run_id, 'completed', array( 'output_snapshot' => $response, 'finished_at' => time() ) );

		return array( 'status' => 'completed', 'output' => $response, 'retry_scheduled' => false );
	}

	/**
	 * Handle a failed step attempt: schedule a backoff retry when the
	 * retry_policy allows another attempt, otherwise mark the step failed.
	 *
	 * @param int                        $run_id      Run ID.
	 * @param AIPS_Ability_Workflow_Step $step        Step that failed.
	 * @param int                        $step_run_id Step-run row ID.
	 * @param string                     $message     Failure message.
	 * @param int                        $attempt     Attempt number just made.
	 * @param AIPS_History_Container|false $history   History container.
	 * @param string                     $correlation_id Correlation ID for this run.
	 * @return array { status: string, output: array, retry_scheduled: bool }
	 */
	private function fail_step( int $run_id, AIPS_Ability_Workflow_Step $step, int $step_run_id, string $message, int $attempt, $history, string $correlation_id ): array {
		$max_attempts    = isset( $step->retry_policy['attempts'] ) ? (int) $step->retry_policy['attempts'] : 0;
		$backoff_seconds = isset( $step->retry_policy['backoff_seconds'] ) ? max( 1, (int) $step->retry_policy['backoff_seconds'] ) : 5;

		if ( $attempt <= $max_attempts ) {
			$this->repository->update_step_run_status(
				$step_run_id,
				'pending',
				array(
					'error'       => array( 'message' => $message, 'attempts' => $attempt, 'next_retry_at' => time() + $backoff_seconds ),
					'finished_at' => 0,
				)
			);

			if ( ! $this->schedule_continuation( $run_id, $correlation_id, $backoff_seconds ) ) {
				// No further progress is possible without a scheduled retry —
				// fail the run outright rather than leaving it stuck at
				// 'running' forever with nothing left to resume it.
				$this->finish_run(
					$run_id,
					AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED,
					$history,
					/* translators: %s: step key */
					sprintf( __( 'Failed to schedule a retry for step "%s".', 'ai-post-scheduler' ), $step->step_key )
				);
			}

			return array( 'status' => 'failed', 'output' => array(), 'retry_scheduled' => true );
		}

		$this->repository->update_step_run_status(
			$step_run_id,
			'failed',
			array(
				'error'       => array( 'message' => $message, 'attempts' => $attempt ),
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
	 * Schedule a single continuation/retry event on self::HOOK.
	 *
	 * @param int    $run_id         Run ID.
	 * @param string $correlation_id Correlation ID to resume under.
	 * @param int    $delay_seconds  Delay before the event fires.
	 * @return bool True if the event was scheduled successfully.
	 */
	private function schedule_continuation( int $run_id, string $correlation_id, int $delay_seconds ): bool {
		return $this->job_scheduler->schedule_simple(
			self::HOOK,
			time() + $delay_seconds,
			array( $run_id, $correlation_id ),
			array( 'job_type' => 'ability_workflow_run', 'correlation_id' => $correlation_id )
		);
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
