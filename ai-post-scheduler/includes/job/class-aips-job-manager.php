<?php
/**
 * AIPS Job Manager — orchestrator for asynchronous background jobs and tasks.
 *
 * Implements high-level job lifecycle management, async and synchronous dispatching,
 * staggered batch execution, time/memory budgeting, and retry backoff.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Job_Manager {

	/** @var AIPS_Job_Registry */
	private $registry;

	/** @var AIPS_Job_Repository */
	private $repository;

	/** @var AIPS_Cron_Manager */
	private $cron_manager;

	/** @var AIPS_Cron_Backend_Interface */
	private $cron_backend;

	/** @var AIPS_Logger */
	private $logger;

	/**
	 * @param AIPS_Job_Registry|null            $registry
	 * @param AIPS_Job_Repository|null          $repository
	 * @param AIPS_Cron_Manager|null           $cron_manager
	 * @param AIPS_Logger|null                 $logger
	 * @param AIPS_Cron_Backend_Interface|null $cron_backend
	 */
	public function __construct(
		?AIPS_Job_Registry $registry = null,
		?AIPS_Job_Repository $repository = null,
		?AIPS_Cron_Manager $cron_manager = null,
		?AIPS_Logger $logger = null,
		?AIPS_Cron_Backend_Interface $cron_backend = null
	) {
		$container          = AIPS_Container::get_instance();
		$this->registry     = $registry ?: ( $container->has( AIPS_Job_Registry::class ) ? $container->make( AIPS_Job_Registry::class ) : new AIPS_Job_Registry() );
		$this->repository   = $repository ?: ( $container->has( AIPS_Job_Repository::class ) ? $container->make( AIPS_Job_Repository::class ) : new AIPS_Job_Repository() );
		$this->cron_manager = $cron_manager ?: ( $container->has( AIPS_Cron_Manager::class ) ? $container->make( AIPS_Cron_Manager::class ) : new AIPS_Cron_Manager() );
		$this->cron_backend = $cron_backend ?: ( $container->has( AIPS_Cron_Backend_Interface::class ) ? $container->make( AIPS_Cron_Backend_Interface::class ) : AIPS_Cron_Backend_Resolver::resolve( $this->cron_manager ) );
		$this->logger       = $logger ?: new AIPS_Logger();

		$this->register_hook_listeners();
	}

	/**
	 * Access the backing cron backend.
	 *
	 * @return AIPS_Cron_Backend_Interface
	 */
	public function get_cron_backend(): AIPS_Cron_Backend_Interface {
		return $this->cron_backend;
	}

	/**
	 * Register actions for all registered job hooks.
	 */
	public function register_hook_listeners(): void {
		foreach ( $this->registry->get_hooks() as $hook ) {
			if ( ! has_action( $hook, array( $this, 'handle_tick' ) ) ) {
				add_action( $hook, array( $this, 'handle_tick' ), 10, 1 );
			}
		}
	}

	// -------------------------------------------------------------------------
	// Dispatching & Scheduling API
	// -------------------------------------------------------------------------

	/**
	 * Queue an asynchronous background job.
	 *
	 * @param string $job_type Job type slug registered in AIPS_Job_Registry.
	 * @param array  $payload  Arbitrary input payload.
	 * @param array  $options  Scheduling options (delay, group, total, correlation_id).
	 * @return string Unique job ID (UUID).
	 * @throws InvalidArgumentException When job type is unknown.
	 */
	public function dispatch( string $job_type, array $payload = array(), array $options = array() ): string {
		$type_spec = $this->registry->get( $job_type );
		if ( null === $type_spec ) {
			throw new InvalidArgumentException( sprintf( 'Unknown job type: %s', $job_type ) );
		}

		$job_id      = wp_generate_uuid4();
		$group_name  = isset( $options['group'] ) ? (string) $options['group'] : $type_spec->get_group();
		$delay       = isset( $options['delay'] ) ? max( 0, (int) $options['delay'] ) : 0;
		$next_run_at = AIPS_DateTime::now()->timestamp() + $delay;
		$total       = isset( $options['total'] ) ? max( 0, (int) $options['total'] ) : 0;
		$max_retries = isset( $options['max_retries'] ) ? max( 0, (int) $options['max_retries'] ) : $type_spec->get_max_retries();

		$this->repository->insert(
			array(
				'job_id'      => $job_id,
				'job_type'    => $job_type,
				'group_name'  => $group_name,
				'status'      => AIPS_Job_Record::STATUS_PENDING,
				'total'       => $total,
				'payload'     => $payload,
				'max_retries' => $max_retries,
				'next_run_at' => $next_run_at,
			)
		);

		$hook = $type_spec->get_hook();
		$this->cron_backend->schedule_single( $next_run_at, $hook, array( $job_id ), $group_name );

		$this->logger->log( sprintf( 'Dispatched job %s (%s) with run at %d', $job_id, $job_type, $next_run_at ), 'info' );

		return $job_id;
	}

	/**
	 * Execute a job immediately in the current PHP request (for WP-CLI, AJAX, tests).
	 *
	 * @param string $job_type Job type slug.
	 * @param array  $payload  Job input payload.
	 * @param array  $options  Execution options.
	 * @return AIPS_Job_Record Final state of the executed job.
	 */
	public function dispatch_sync( string $job_type, array $payload = array(), array $options = array() ): AIPS_Job_Record {
		$type_spec = $this->registry->get( $job_type );
		if ( null === $type_spec ) {
			throw new InvalidArgumentException( sprintf( 'Unknown job type: %s', $job_type ) );
		}

		$job_id      = wp_generate_uuid4();
		$group_name  = isset( $options['group'] ) ? (string) $options['group'] : $type_spec->get_group();
		$total       = isset( $options['total'] ) ? max( 0, (int) $options['total'] ) : ( isset( $payload['items'] ) && is_array( $payload['items'] ) ? count( $payload['items'] ) : 0 );
		$max_retries = isset( $options['max_retries'] ) ? max( 0, (int) $options['max_retries'] ) : $type_spec->get_max_retries();

		$this->repository->insert(
			array(
				'job_id'      => $job_id,
				'job_type'    => $job_type,
				'group_name'  => $group_name,
				'status'      => AIPS_Job_Record::STATUS_PENDING,
				'total'       => $total,
				'payload'     => $payload,
				'max_retries' => $max_retries,
				'next_run_at' => AIPS_DateTime::now()->timestamp(),
			)
		);

		$this->handle_tick( $job_id );

		$record = $this->repository->find( $job_id );
		return $record ?: new AIPS_Job_Record( array( 'job_id' => $job_id, 'status' => AIPS_Job_Record::STATUS_FAILED ) );
	}

	/**
	 * Slice a collection of items into batches and queue them under one parent tracking job.
	 *
	 * @param string $job_type Job type slug.
	 * @param array  $items    Collection of items to process.
	 * @param array  $options  Batch options (batch_size, delay, group).
	 * @return string Root job ID tracking the batch.
	 */
	public function dispatch_batch( string $job_type, array $items, array $options = array() ): string {
		$type_spec = $this->registry->get( $job_type );
		$batch_size = isset( $options['batch_size'] ) ? max( 1, (int) $options['batch_size'] ) : ( $type_spec ? $type_spec->get_batch_size() : 10 );

		$options['total'] = count( $items );
		$payload          = array(
			'items'      => $items,
			'batch_size' => $batch_size,
		);

		return $this->dispatch( $job_type, $payload, $options );
	}

	/**
	 * Schedule multiple items with staggered delays between them.
	 *
	 * @param string $job_type Job type slug.
	 * @param array  $items    List of payload arrays.
	 * @param array  $options  Stagger options (interval_seconds, start_delay).
	 * @return string[] Array of scheduled job IDs.
	 */
	public function dispatch_staggered( string $job_type, array $items, array $options = array() ): array {
		$interval_seconds = isset( $options['interval_seconds'] ) ? max( 0, (int) $options['interval_seconds'] ) : 30;
		$delay            = isset( $options['start_delay'] ) ? max( 0, (int) $options['start_delay'] ) : 0;

		$job_ids = array();
		foreach ( $items as $item_payload ) {
			$item_options          = $options;
			$item_options['delay'] = $delay;
			$payload               = is_array( $item_payload ) ? $item_payload : array( 'data' => $item_payload );

			$job_ids[] = $this->dispatch( $job_type, $payload, $item_options );
			$delay    += $interval_seconds;
		}

		return $job_ids;
	}

	// -------------------------------------------------------------------------
	// Lifecycle & State Control
	// -------------------------------------------------------------------------

	/**
	 * Pause an active or running job.
	 *
	 * @param string $job_id
	 * @return bool
	 */
	public function pause( string $job_id ): bool {
		$record = $this->repository->find( $job_id );
		if ( ! $record || ! $record->is_active() ) {
			return false;
		}

		$type_spec = $this->registry->get( $record->get_job_type() );
		$hook      = $type_spec ? $type_spec->get_hook() : AIPS_Job_Registry::DEFAULT_TICK_HOOK;

		$this->cron_backend->unschedule( $hook, array( $job_id ) );

		return $this->repository->update( $job_id, array( 'status' => AIPS_Job_Record::STATUS_PAUSED ) );
	}

	/**
	 * Resume a paused job, scheduling its next tick.
	 *
	 * @param string $job_id
	 * @return bool
	 */
	public function resume( string $job_id ): bool {
		$record = $this->repository->find( $job_id );
		if ( ! $record || ! $record->is_paused() ) {
			return false;
		}

		$type_spec = $this->registry->get( $record->get_job_type() );
		$hook      = $type_spec ? $type_spec->get_hook() : AIPS_Job_Registry::DEFAULT_TICK_HOOK;
		$group     = $type_spec ? $type_spec->get_group() : AIPS_Job_Registry::GROUP_DEFAULT;
		$next_run  = AIPS_DateTime::now()->timestamp();

		$this->repository->update(
			$job_id,
			array(
				'status'      => AIPS_Job_Record::STATUS_PENDING,
				'next_run_at' => $next_run,
			)
		);

		return $this->cron_backend->schedule_single( $next_run, $hook, array( $job_id ), $group );
	}

	/**
	 * Cancel a job and unschedule any pending ticks.
	 *
	 * @param string $job_id
	 * @return bool
	 */
	public function cancel( string $job_id ): bool {
		$record = $this->repository->find( $job_id );
		if ( ! $record || $record->is_completed() || $record->is_cancelled() ) {
			return false;
		}

		$type_spec = $this->registry->get( $record->get_job_type() );
		$hook      = $type_spec ? $type_spec->get_hook() : AIPS_Job_Registry::DEFAULT_TICK_HOOK;

		$this->cron_backend->unschedule( $hook, array( $job_id ) );

		return $this->repository->update(
			$job_id,
			array(
				'status'      => AIPS_Job_Record::STATUS_CANCELLED,
				'finished_at' => AIPS_DateTime::now()->timestamp(),
			)
		);
	}

	/**
	 * Manually retry a failed or stalled job.
	 *
	 * @param string $job_id
	 * @return bool
	 */
	public function retry( string $job_id ): bool {
		$record = $this->repository->find( $job_id );
		if ( ! $record || ! $record->is_failed() ) {
			return false;
		}

		$type_spec = $this->registry->get( $record->get_job_type() );
		$hook      = $type_spec ? $type_spec->get_hook() : AIPS_Job_Registry::DEFAULT_TICK_HOOK;
		$group     = $type_spec ? $type_spec->get_group() : AIPS_Job_Registry::GROUP_DEFAULT;
		$next_run  = AIPS_DateTime::now()->timestamp();

		$this->repository->update(
			$job_id,
			array(
				'status'        => AIPS_Job_Record::STATUS_PENDING,
				'attempts'      => 0,
				'error_message' => null,
				'next_run_at'   => $next_run,
			)
		);

		return $this->cron_backend->schedule_single( $next_run, $hook, array( $job_id ), $group );
	}

	/**
	 * Pause all active jobs, optionally filtered by job type.
	 *
	 * @param string|null $job_type
	 * @return int Number of paused jobs.
	 */
	public function pause_all( ?string $job_type = null ): int {
		$active = $this->repository->get_active_jobs( $job_type );
		$paused = 0;
		foreach ( $active as $job ) {
			if ( $this->pause( $job->get_job_id() ) ) {
				$paused++;
			}
		}
		return $paused;
	}

	/**
	 * Cancel all active jobs, optionally filtered by job type.
	 *
	 * @param string|null $job_type
	 * @return int Number of cancelled jobs.
	 */
	public function cancel_all( ?string $job_type = null ): int {
		$active    = $this->repository->get_active_jobs( $job_type );
		$cancelled = 0;
		foreach ( $active as $job ) {
			if ( $this->cancel( $job->get_job_id() ) ) {
				$cancelled++;
			}
		}
		return $cancelled;
	}

	// -------------------------------------------------------------------------
	// Observability & Diagnostics API
	// -------------------------------------------------------------------------

	public function get_job( string $job_id ): ?AIPS_Job_Record {
		return $this->repository->find( $job_id );
	}

	public function get_status( string $job_id ): ?string {
		$record = $this->repository->find( $job_id );
		return $record ? $record->get_status() : null;
	}

	public function get_active_jobs( ?string $job_type = null ): array {
		return $this->repository->get_active_jobs( $job_type );
	}

	public function get_jobs( array $filters = array(), int $limit = 50, int $offset = 0 ): array {
		return $this->repository->query( $filters, $limit, $offset );
	}

	public function cleanup_finished_jobs( int $older_than_seconds ): int {
		$cutoff = AIPS_DateTime::now()->timestamp() - $older_than_seconds;
		return $this->repository->prune_finished( $cutoff );
	}

	// -------------------------------------------------------------------------
	// Runner Tick Entrypoint
	// -------------------------------------------------------------------------

	/**
	 * Cron execution handler invoked when a job hook fires.
	 *
	 * @param string $job_id
	 * @return void
	 */
	public function handle_tick( string $job_id ): void {
		$record = $this->repository->find( $job_id );
		if ( ! $record ) {
			return;
		}

		// Abort if paused, cancelled, or already completed
		if ( $record->is_paused() || $record->is_cancelled() || $record->is_completed() ) {
			return;
		}

		$type_spec = $this->registry->get( $record->get_job_type() );
		if ( ! $type_spec ) {
			$this->repository->update(
				$job_id,
				array(
					'status'        => AIPS_Job_Record::STATUS_FAILED,
					'error_message' => sprintf( 'Unregistered job type: %s', $record->get_job_type() ),
					'finished_at'   => AIPS_DateTime::now()->timestamp(),
				)
			);
			return;
		}

		$now = AIPS_DateTime::now()->timestamp();
		$this->repository->update(
			$job_id,
			array(
				'status'       => AIPS_Job_Record::STATUS_RUNNING,
				'started_at'   => $record->get_started_at() > 0 ? $record->get_started_at() : $now,
				'last_tick_at' => $now,
			)
		);

		$context = new AIPS_Job_Context( $record, $this->logger );

		try {
			$handler = $this->resolve_handler( $type_spec );
			if ( ! is_callable( $handler ) ) {
				throw new RuntimeException( sprintf( 'Handler for %s is not callable', $record->get_job_type() ) );
			}

			/** @var AIPS_Job_Slice_Result|mixed $result */
			$result = call_user_func( $handler, $context );

			$slice_result = ( $result instanceof AIPS_Job_Slice_Result )
				? $result
				: ( is_array( $result )
					? new AIPS_Job_Slice_Result(
						isset( $result['processed'] ) ? (int) $result['processed'] : 0,
						isset( $result['failed'] ) ? (int) $result['failed'] : 0,
						isset( $result['cursor'] ) ? (int) $result['cursor'] : 0,
						isset( $result['complete'] ) ? (bool) $result['complete'] : true,
						isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : array()
					)
					: AIPS_Job_Slice_Result::complete() );

			$this->repository->increment_progress(
				$job_id,
				$slice_result->get_processed_count(),
				$slice_result->get_failed_count(),
				$slice_result->get_next_cursor()
			);

			if ( $slice_result->is_complete() ) {
				$this->repository->update(
					$job_id,
					array(
						'status'      => AIPS_Job_Record::STATUS_COMPLETED,
						'result'      => $slice_result->get_result_data(),
						'finished_at' => AIPS_DateTime::now()->timestamp(),
					)
				);
				$context->log( 'Completed successfully.' );
			} else {
				// Re-read record to check whether it was paused/cancelled while running
				$fresh_record = $this->repository->find( $job_id );
				if ( $fresh_record && ! $fresh_record->is_active() ) {
					return;
				}

				// Schedule next continuation tick
				$next_tick = AIPS_DateTime::now()->timestamp() + 5;
				$this->repository->update(
					$job_id,
					array(
						'status'      => AIPS_Job_Record::STATUS_PENDING,
						'next_run_at' => $next_tick,
					)
				);

				$this->cron_backend->schedule_single(
					$next_tick,
					$type_spec->get_hook(),
					array( $job_id ),
					$type_spec->get_group()
				);

				$context->log( sprintf( 'Yielded slice; next tick scheduled at %d', $next_tick ) );
			}
		} catch ( Throwable $exception ) {
			$this->handle_tick_failure( $job_id, $record, $type_spec, $exception );
		}
	}

	/**
	 * Handle failure with retry backoff policy.
	 */
	private function handle_tick_failure( string $job_id, AIPS_Job_Record $record, AIPS_Job_Type $type_spec, Throwable $exception ): void {
		$attempts    = $record->get_attempts() + 1;
		$max_retries = $record->get_max_retries();
		$error_msg   = $exception->getMessage();

		$this->logger->log(
			sprintf( 'Job %s attempt %d/%d failed: %s', $job_id, $attempts, $max_retries, $error_msg ),
			'error'
		);

		if ( $attempts < $max_retries ) {
			$backoff   = $type_spec->get_retry_backoff_sec() * ( 2 ** ( $attempts - 1 ) );
			$next_tick = AIPS_DateTime::now()->timestamp() + $backoff;

			$this->repository->update(
				$job_id,
				array(
					'status'        => AIPS_Job_Record::STATUS_PENDING,
					'attempts'      => $attempts,
					'error_message' => $error_msg,
					'next_run_at'   => $next_tick,
				)
			);

			$this->cron_backend->schedule_single(
				$next_tick,
				$type_spec->get_hook(),
				array( $job_id ),
				$type_spec->get_group()
			);
		} else {
			$this->repository->update(
				$job_id,
				array(
					'status'        => AIPS_Job_Record::STATUS_FAILED,
					'attempts'      => $attempts,
					'error_message' => $error_msg,
					'finished_at'   => AIPS_DateTime::now()->timestamp(),
				)
			);
		}
	}

	/**
	 * Resolve handler callable from job type specification.
	 *
	 * @param AIPS_Job_Type $type_spec
	 * @return callable|null
	 */
	private function resolve_handler( AIPS_Job_Type $type_spec ) {
		$handler = $type_spec->get_handler();

		if ( is_callable( $handler ) ) {
			return $handler;
		}

		if ( is_string( $handler ) ) {
			$container = AIPS_Container::get_instance();
			$instance  = $container->has( $handler )
				? $container->make( $handler )
				: ( class_exists( $handler ) ? new $handler() : null );

			if ( $instance ) {
				if ( method_exists( $instance, 'handle_job_slice' ) ) {
					return array( $instance, 'handle_job_slice' );
				}
				if ( is_callable( $instance ) ) {
					return $instance;
				}
			}
		}

		return null;
	}
}
