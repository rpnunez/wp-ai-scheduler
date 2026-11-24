<?php
/**
 * Schedule Generation Service
 *
 * Owns the execution of scheduled post generations, claim-first locking,
 * budget-aware resumable batch processing, and integration with AIPS_Job_Manager.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Schedule_Generation_Service {

	/**
	 * @var AIPS_Schedule_Repository_Interface
	 */
	private $repository;

	/**
	 * @var AIPS_Template_Repository
	 */
	private $template_repository;

	/**
	 * @var AIPS_Generator
	 */
	private $generator;

	/**
	 * @var AIPS_History_Service_Interface
	 */
	private $history_service;

	/**
	 * @var AIPS_History_Repository_Interface
	 */
	private $history_repository;

	/**
	 * @var AIPS_Interval_Calculator
	 */
	private $interval_calculator;

	/**
	 * @var AIPS_Template_Type_Selector
	 */
	private $template_type_selector;

	/**
	 * @var AIPS_Logger_Interface
	 */
	private $logger;

	/**
	 * @var AIPS_Generation_Execution_Runner
	 */
	private $runner;

	/**
	 * @var AIPS_Schedule_Result_Handler
	 */
	private $result_handler;

	/**
	 * @var AIPS_Cron_Manager
	 */
	private $cron_manager;

	/**
	 * @var AIPS_Batch_Queue_Service|null Lazy-loaded batch queue service.
	 */
	private $batch_queue_service;

	/**
	 * Legacy single cron event hook (for backward compatibility).
	 */
	const QUEUED_DUE_SCHEDULE_HOOK = 'aips_process_single_due_schedule';

	/**
	 * run_state status for an automated batch that stopped early to avoid a
	 * script timeout and is waiting for its resume cooldown.
	 */
	const RUN_STATE_YIELDED = 'yielded';

	/**
	 * Minimum seconds that must remain before another post is started.
	 */
	const MIN_TIME_BUDGET_MARGIN = 15;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Schedule_Repository_Interface|null $repository
	 * @param AIPS_Template_Repository|null         $template_repository
	 * @param AIPS_Generator|null                   $generator
	 * @param AIPS_History_Service_Interface|null   $history_service
	 * @param AIPS_Template_Type_Selector|null      $template_type_selector
	 * @param AIPS_Logger_Interface|null            $logger
	 * @param AIPS_Generation_Execution_Runner|null $runner
	 * @param AIPS_Schedule_Result_Handler|null     $result_handler
	 * @param AIPS_Cron_Manager|null                $cron_manager
	 */
	public function __construct(
		?AIPS_Schedule_Repository_Interface $repository = null,
		$template_repository = null,
		$generator = null,
		?AIPS_History_Service_Interface $history_service = null,
		$template_type_selector = null,
		?AIPS_Logger_Interface $logger = null,
		$runner = null,
		$result_handler = null,
		?AIPS_Cron_Manager $cron_manager = null
	) {
		$container                 = AIPS_Container::get_instance();
		$this->repository          = $repository ?: ( $container->has( AIPS_Schedule_Repository_Interface::class ) ? $container->make( AIPS_Schedule_Repository_Interface::class ) : new AIPS_Schedule_Repository() );
		$this->template_repository = $template_repository ?: new AIPS_Template_Repository();
		$this->generator           = $generator ?: new AIPS_Generator();
		$this->history_repository  = $container->has( AIPS_History_Repository_Interface::class ) ? $container->make( AIPS_History_Repository_Interface::class ) : new AIPS_History_Repository();
		$this->history_service     = $history_service ?: ( $container->has( AIPS_History_Service_Interface::class ) ? $container->make( AIPS_History_Service_Interface::class ) : new AIPS_History_Service( $this->history_repository ) );
		$this->interval_calculator = new AIPS_Interval_Calculator();
		$this->template_type_selector = $template_type_selector ?: new AIPS_Template_Type_Selector();
		$this->logger              = $logger ?: ( $container->has( AIPS_Logger_Interface::class ) ? $container->make( AIPS_Logger_Interface::class ) : new AIPS_Logger() );
		$this->runner              = $runner ?: new AIPS_Generation_Execution_Runner( $this->history_service, $this->logger );
		$this->result_handler      = $result_handler ?: new AIPS_Schedule_Result_Handler( $this->repository, $this->history_service, $this->history_repository, $this->logger );
		$this->cron_manager        = $cron_manager ?: ( $container->has( AIPS_Cron_Manager::class ) ? $container->make( AIPS_Cron_Manager::class ) : new AIPS_Cron_Manager() );
	}

	/**
	 * Set generator (useful for testing or late injection).
	 *
	 * @param AIPS_Generator $generator
	 */
	public function set_generator( $generator ) {
		$this->generator = $generator;
	}

	/**
	 * Set schedule repository.
	 *
	 * @param AIPS_Schedule_Repository_Interface $repository
	 */
	public function set_repository( AIPS_Schedule_Repository_Interface $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Set template repository.
	 *
	 * @param AIPS_Template_Repository $repository
	 */
	public function set_template_repository( $repository ) {
		$this->template_repository = $repository;
	}

	/**
	 * Set execution runner.
	 *
	 * @param AIPS_Generation_Execution_Runner $runner
	 */
	public function set_runner( $runner ) {
		$this->runner = $runner;
	}

	/**
	 * Set batch queue service.
	 *
	 * @param AIPS_Batch_Queue_Service $service
	 */
	public function set_batch_queue_service( AIPS_Batch_Queue_Service $service ) {
		$this->batch_queue_service = $service;
	}

	/**
	 * Lazy-load batch queue service.
	 *
	 * @return AIPS_Batch_Queue_Service
	 */
	private function get_batch_queue_service(): AIPS_Batch_Queue_Service {
		if ( null === $this->batch_queue_service ) {
			$this->batch_queue_service = new AIPS_Batch_Queue_Service();
		}
		return $this->batch_queue_service;
	}

	/**
	 * Handle a background job slice for schedule execution or batch slices.
	 *
	 * @param AIPS_Job_Context $context
	 * @return AIPS_Job_Slice_Result
	 */
	public function handle_job_slice( AIPS_Job_Context $context ): AIPS_Job_Slice_Result {
		$record   = $context->get_record();
		$job_type = $record->get_job_type();
		$payload  = $record->get_payload();

		if ( 'schedule_batch_slice' === $job_type ) {
			$schedule_id    = isset( $payload['schedule_id'] ) ? (int) $payload['schedule_id'] : 0;
			$start_index    = isset( $payload['start_index'] ) ? (int) $payload['start_index'] : 0;
			$batch_size     = isset( $payload['batch_size'] ) ? (int) $payload['batch_size'] : 1;
			$total_quantity = isset( $payload['total_quantity'] ) ? (int) $payload['total_quantity'] : 1;
			$correlation_id = isset( $payload['correlation_id'] ) ? (string) $payload['correlation_id'] : '';

			$this->process_batch_slice( $schedule_id, $start_index, $batch_size, $total_quantity, $correlation_id );
			return AIPS_Job_Slice_Result::success( $batch_size );
		}

		$schedule_id       = isset( $payload['schedule_id'] ) ? (int) $payload['schedule_id'] : 0;
		$quantity_override = isset( $payload['quantity_override'] ) ? (int) $payload['quantity_override'] : null;
		$advance_schedule  = isset( $payload['advance_schedule'] ) ? (bool) $payload['advance_schedule'] : true;
		$is_manual         = isset( $payload['is_manual'] ) ? (bool) $payload['is_manual'] : false;

		if ( $schedule_id <= 0 ) {
			return AIPS_Job_Slice_Result::failure( array( 'Invalid schedule ID' ) );
		}

		if ( $is_manual ) {
			$result = $this->process_single_schedule( $schedule_id, $quantity_override, $advance_schedule );
		} else {
			$schedule = $this->repository->get_due_schedule_by_id( $schedule_id );
			if ( ! $schedule ) {
				return AIPS_Job_Slice_Result::success( 1 );
			}
			$result = $this->execute_schedule_with_lock( $schedule );
		}

		if ( is_wp_error( $result ) ) {
			return AIPS_Job_Slice_Result::failure( array( $result->get_error_message() ) );
		}

		$count = is_array( $result ) ? count( $result ) : 1;
		return AIPS_Job_Slice_Result::success( $count );
	}

	/**
	 * Process a specific slice of posts for a scheduled batch job.
	 *
	 * @param int    $schedule_id    Schedule ID.
	 * @param int    $start_index    Zero-based index of the first post in this slice.
	 * @param int    $batch_size     Number of posts to generate in this slice.
	 * @param int    $total_quantity Total posts the schedule should generate overall.
	 * @param string $correlation_id Correlation ID for tracing.
	 * @return void
	 */
	public function process_batch_slice(
		int $schedule_id,
		int $start_index,
		int $batch_size,
		int $total_quantity,
		string $correlation_id = ''
	): void {
		if ( '' !== $correlation_id ) {
			AIPS_Correlation_ID::set( $correlation_id );
		} else {
			AIPS_Correlation_ID::generate();
		}

		try {
			$this->do_process_batch_slice( $schedule_id, $start_index, $batch_size, $total_quantity );
		} finally {
			AIPS_Correlation_ID::reset();
		}
	}

	/**
	 * Internal implementation of process_batch_slice.
	 *
	 * @param int $schedule_id
	 * @param int $start_index
	 * @param int $batch_size
	 * @param int $total_quantity
	 */
	private function do_process_batch_slice(
		int $schedule_id,
		int $start_index,
		int $batch_size,
		int $total_quantity
	): void {
		$this->logger->log(
			sprintf(
				'Batch slice: schedule %d, posts %d-%d of %d',
				$schedule_id,
				$start_index + 1,
				min( $start_index + $batch_size, $total_quantity ),
				$total_quantity
			),
			'info'
		);

		$schedule = $this->repository->get_by_id( $schedule_id );

		if ( ! $schedule ) {
			$this->logger->log( 'Batch slice: schedule ' . $schedule_id . ' not found — skipping.', 'error' );
			return;
		}

		$current_run_state = $this->get_decoded_run_state( $schedule );
		if ( $this->should_skip_batch_slice( $schedule_id, $current_run_state ) ) {
			return;
		}

		if ( isset( $schedule->is_active ) && ! (bool) $schedule->is_active ) {
			$this->logger->log( 'Batch slice: schedule ' . $schedule_id . ' is inactive — skipping.', 'info' );
			return;
		}

		$actual_template_model = $this->template_repository->get_by_id( $schedule->template_id );

		if ( ! $actual_template_model ) {
			$this->logger->log( 'Batch slice: template not found for schedule ' . $schedule_id . ' — skipping.', 'error' );
			return;
		}

		$schedule_obj = (object) array_merge( (array) $actual_template_model, (array) $schedule );
		$schedule_obj->schedule_id = $schedule->id;
		$schedule_obj->name        = $actual_template_model->name;

		$article_structure_id = $this->template_type_selector->select_structure( $schedule_obj );

		$template = AIPS_Template_Entry::from_template_and_overrides(
			(int) $schedule->template_id,
			$actual_template_model,
			$total_quantity,
			$article_structure_id
		);

		$topic   = isset( $schedule->topic ) && '' !== $schedule->topic ? (string) $schedule->topic : null;
		$context = new AIPS_Template_Context( $template, null, $topic, 'scheduled' );
		$context->set_trigger_context( $this->build_schedule_trigger_context( $schedule_obj ) );

		$config        = AIPS_Config::get_instance();
		$history       = $this->result_handler->get_or_create_schedule_history( $schedule_id );
		$delay_seconds = $config->get_generation_delay_seconds();
		$dispatched_at = isset( $current_run_state['dispatched_at'] ) ? (int) $current_run_state['dispatched_at'] : AIPS_DateTime::now()->timestamp();
		$corr_id       = isset( $current_run_state['correlation_id'] ) && '' !== $current_run_state['correlation_id']
			? (string) $current_run_state['correlation_id']
			: (string) AIPS_Correlation_ID::get();

		// Prevent AI Generation turned on before this slice ran: terminate and mark resumable.
		if ( $config->is_scheduled_ai_generation_prevented() ) {
			$setting_label = $config->get_scheduled_ai_generation_prevention_label();
			$this->result_handler->handle_execution_terminated_by_setting(
				$schedule_obj,
				$history,
				false,
				$setting_label,
				array(
					'message_override' => sprintf(
						/* translators: 1: starting post index, 2: total posts, 3: setting label */
						__( 'Batch slice starting at post %1$d of %2$d was terminated early due to %3$s being enabled.', 'ai-post-scheduler' ),
						$start_index + 1,
						$total_quantity,
						$setting_label
					),
					'event_type' => AIPS_History_Event_Type::BATCH_SLICE_TERMINATED,
					'total'      => $total_quantity,
					'completed'  => max( 0, $start_index ),
					'run_state'  => array(
						'completed'      => max( 0, $start_index ),
						'total'          => $total_quantity,
						'dispatched_at'  => $dispatched_at,
						'correlation_id' => $corr_id,
						'resumable'      => true,
						'resume_index'   => max( 0, $start_index ),
					),
				)
			);
			return;
		}

		$this->repository->update_run_state( $schedule_id, array(
			'status'         => 'batch_processing',
			'completed'      => $start_index,
			'total'          => $total_quantity,
			'dispatched_at'  => $dispatched_at,
			'correlation_id' => $corr_id,
			'timestamp'      => AIPS_DateTime::now()->toIso8601(),
		) );

		$successful_post_ids = array();
		$errors              = array();

		for ( $i = 0; $i < $batch_size; $i++ ) {
			$post_index = $start_index + $i;

			if ( $post_index >= $total_quantity ) {
				break;
			}

			// Prevent AI Generation turned on mid-slice: stop and persist a resumable cursor.
			if ( $i > 0 && $config->is_scheduled_ai_generation_prevented() ) {
				$setting_label = $config->get_scheduled_ai_generation_prevention_label();
				$resume_index  = $start_index + count( $successful_post_ids );
				$this->result_handler->handle_execution_terminated_by_setting(
					$schedule_obj,
					$history,
					false,
					$setting_label,
					array(
						'message_override' => sprintf(
							/* translators: 1: post index, 2: total posts, 3: setting label */
							__( 'Batch slice terminated at post %1$d of %2$d due to %3$s being enabled.', 'ai-post-scheduler' ),
							$resume_index + 1,
							$total_quantity,
							$setting_label
						),
						'event_type' => AIPS_History_Event_Type::BATCH_SLICE_TERMINATED,
						'total'      => $total_quantity,
						'completed'  => $resume_index,
						'run_state'  => array(
							'completed'      => $resume_index,
							'total'          => $total_quantity,
							'dispatched_at'  => $dispatched_at,
							'correlation_id' => $corr_id,
							'resumable'      => true,
							'resume_index'   => $resume_index,
						),
					)
				);
				return;
			}

			if ( $i > 0 ) {
				AIPS_Cache::reset_request_cache();
				if ( $delay_seconds > 0 && ! $this->is_time_exhausted( $delay_seconds + self::MIN_TIME_BUDGET_MARGIN ) ) {
					sleep( $delay_seconds );
				}
			}

			$post_id = $this->generator->generate_post( $context );

			if ( is_wp_error( $post_id ) ) {
				$errors[] = $post_id;
				$this->logger->log(
					sprintf(
						'Batch slice: post %d of %d failed for schedule %d: %s',
						$post_index + 1,
						$total_quantity,
						$schedule_id,
						$post_id->get_error_message()
					),
					'error'
				);
				break;
			} else {
				$successful_post_ids[] = $post_id;
				$this->logger->log(
					sprintf(
						'Batch slice: post %d of %d generated (post ID %d) for schedule %d',
						$post_index + 1,
						$total_quantity,
						$post_id,
						$schedule_id
					),
					'info'
				);
			}

			$completed_so_far = $start_index + count( $successful_post_ids );
			$this->repository->update_batch_progress( $schedule_id, $completed_so_far, $total_quantity, $completed_so_far - 1, $successful_post_ids );
			$this->repository->update_run_state( $schedule_id, array(
				'status'         => ( $completed_so_far >= $total_quantity ) ? 'success' : 'batch_processing',
				'completed'      => $completed_so_far,
				'total'          => $total_quantity,
				'dispatched_at'  => $dispatched_at,
				'correlation_id' => $corr_id,
				'timestamp'      => AIPS_DateTime::now()->toIso8601(),
			) );
		}

		$total_completed = $start_index + count( $successful_post_ids );

		if ( ! empty( $errors ) ) {
			$this->repository->update_run_state( $schedule_id, array(
				'status'         => $total_completed > 0 ? 'partial' : 'failed',
				'error_code'     => $errors[0]->get_error_code(),
				'error_message'  => $errors[0]->get_error_message(),
				'completed'      => $total_completed,
				'total'          => $total_quantity,
				'dispatched_at'  => $dispatched_at,
				'correlation_id' => $corr_id,
				'timestamp'      => AIPS_DateTime::now()->toIso8601(),
			) );
			$this->result_handler->handle_post_execution_cleanup( $schedule_obj, $errors[0] );
			return;
		}

		if ( $total_completed >= $total_quantity ) {
			$this->logger->log(
				sprintf( 'Batch queue finished all %d posts for schedule %d', $total_quantity, $schedule_id ),
				'info'
			);

			$this->repository->clear_batch_progress( $schedule_id );
			$this->repository->update_run_state( $schedule_id, array(
				'status'         => 'success',
				'completed'      => $total_quantity,
				'total'          => $total_quantity,
				'dispatched_at'  => $dispatched_at,
				'correlation_id' => $corr_id,
				'timestamp'      => AIPS_DateTime::now()->toIso8601(),
			) );

			if ( $history ) {
				$history->record(
					'activity',
					sprintf(
						/* translators: 1: schedule name, 2: post quantity */
						__( 'Batch queue finished generating %2$d posts for schedule "%1$s"', 'ai-post-scheduler' ),
						$actual_template_model->name,
						$total_quantity
					),
					array(
						'event_type'   => 'batch_queue_finished',
						'event_status' => 'success',
					),
					null,
					array(
						'schedule_id'    => $schedule_id,
						'total_quantity' => $total_quantity,
					)
				);
			}

			$this->result_handler->handle_post_execution_cleanup( $schedule_obj, 1 );
			do_action( 'aips_schedule_execution_completed', $schedule_id, $successful_post_ids, $schedule_obj );
		}
	}

	/**
	 * Decide whether to skip re-dispatching a large batch.
	 *
	 * @param object                   $schedule
	 * @param AIPS_Batch_Queue_Service $batch_service
	 * @return bool
	 */
	private function should_skip_batch_redispatch( object $schedule, AIPS_Batch_Queue_Service $batch_service ): bool {
		$run_state = $this->get_decoded_run_state( $schedule );
		$status    = isset( $run_state['status'] ) ? (string) $run_state['status'] : '';

		if ( 'batch_queued' === $status || 'batch_processing' === $status ) {
			$schedule_id = isset( $schedule->schedule_id ) ? (int) $schedule->schedule_id : (int) $schedule->id;
			$has_pending = $batch_service->has_pending_slices( $schedule_id );
			if ( ! $has_pending && isset( $run_state['dispatched_at'] ) ) {
				$window      = $batch_service->get_window_seconds();
				$age         = AIPS_DateTime::now()->timestamp() - (int) $run_state['dispatched_at'];
				$has_pending = ( $age < ( $window + AIPS_Batch_Queue_Service::REDISPATCH_GRACE_SECONDS ) );
			}
			if ( $has_pending ) {
				$this->logger->log(
					sprintf(
						'Schedule %d already has an active batch queue (status: %s); skipping duplicate dispatch.',
						$schedule_id,
						$status
					),
					'info'
				);
				return true;
			}
		}

		return false;
	}

	/**
	 * Decode run_state JSON column safely.
	 *
	 * @param object $schedule
	 * @return array
	 */
	private function get_decoded_run_state( object $schedule ): array {
		if ( empty( $schedule->run_state ) ) {
			return array();
		}
		if ( is_array( $schedule->run_state ) ) {
			return $schedule->run_state;
		}
		$decoded = json_decode( (string) $schedule->run_state, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Check if a batch slice should be skipped.
	 *
	 * @param int   $schedule_id
	 * @param array $run_state
	 * @return bool
	 */
	private function should_skip_batch_slice( int $schedule_id, array $run_state ): bool {
		$status = isset( $run_state['status'] ) ? (string) $run_state['status'] : '';
		if ( in_array( $status, array( 'partial', 'failed', 'success', 'completed', 'cancelled', AIPS_History_Event_Status::TERMINATED ), true ) ) {
			$this->logger->log(
				sprintf(
					'Batch slice: schedule %d is marked as %s — skipping slice.',
					$schedule_id,
					$status
				),
				'info'
			);
			return true;
		}
		return false;
	}

	/**
	 * Process scheduled posts that are due.
	 *
	 * @return void
	 */
	public function process_due_schedules(): void {
		$this->logger->log( 'Starting scheduled post generation', 'info' );

		$due_limit = $this->get_batch_queue_service()->get_large_batch_threshold();
		$due_schedules = $this->repository->get_due_schedules( AIPS_DateTime::now()->timestamp(), $due_limit );

		if ( empty( $due_schedules ) ) {
			$this->logger->log( 'No scheduled posts due', 'info' );
			return;
		}

		$first_schedule  = array_shift( $due_schedules );
		$stagger_seconds = max( 0, (int) apply_filters( 'aips_due_schedule_stagger_seconds', 30 ) );

		if ( ! empty( $due_schedules ) ) {
			$due_items = array();
			foreach ( $due_schedules as $sched ) {
				$due_items[] = array(
					'schedule_id' => (int) $sched->schedule_id,
				);
			}

			try {
				$job_manager = AIPS_Container::get_instance()->get_job_manager();
				$job_manager->dispatch_staggered(
					AIPS_Job_Registry::TYPE_SCHEDULE_POST_GENERATION,
					$due_items,
					array(
						'interval_seconds' => $stagger_seconds,
						'start_delay'      => $stagger_seconds,
					)
				);
			} catch ( Throwable $e ) {
				$this->logger->log( 'Failed to dispatch staggered schedules: ' . $e->getMessage(), 'error' );
			}
		}

		if ( $first_schedule ) {
			$this->execute_schedule_with_lock( $first_schedule );
		}
	}

	/**
	 * Run one schedule queued by process_due_schedules() or by a yielded batch.
	 *
	 * @param int $schedule_id Schedule ID.
	 * @return void
	 */
	public function process_queued_due_schedule( $schedule_id ): void {
		$schedule = $this->repository->get_due_schedule_by_id( (int) $schedule_id );

		if ( ! $schedule ) {
			$this->logger->log(
				sprintf( 'Queued schedule %d is no longer due (already claimed, rescheduled, or inactive); skipping.', (int) $schedule_id ),
				'info'
			);
			return;
		}

		$this->execute_schedule_with_lock( $schedule );
	}

	/**
	 * Check if execution time is close to exhaustion.
	 *
	 * @param int $safety_margin_seconds
	 * @return bool
	 */
	private function is_time_exhausted( int $safety_margin_seconds = self::MIN_TIME_BUDGET_MARGIN ): bool {
		$max_execution_time = (int) ini_get( 'max_execution_time' );
		if ( $max_execution_time <= 0 ) {
			return false;
		}

		$start_time = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : 0;
		if ( $start_time <= 0 ) {
			return false;
		}

		$elapsed   = microtime( true ) - $start_time;
		$remaining = $max_execution_time - $elapsed;

		return $remaining < $safety_margin_seconds;
	}

	/**
	 * Run a specific schedule immediately.
	 *
	 * @param int      $schedule_id
	 * @param int|null $quantity_override
	 * @param bool     $advance_schedule
	 * @return int|int[]|WP_Error
	 */
	public function process_single_schedule( $schedule_id, $quantity_override = null, $advance_schedule = true ) {
		$schedule = $this->repository->get_by_id( $schedule_id );

		if ( ! $schedule ) {
			return new WP_Error( 'schedule_not_found', __( 'Schedule not found.', 'ai-post-scheduler' ) );
		}

		$template_data = $this->template_repository->get_by_id( $schedule->template_id );

		if ( ! $template_data ) {
			return new WP_Error( 'template_not_found', __( 'Template not found.', 'ai-post-scheduler' ) );
		}

		$schedule_with_template = (object) array_merge( (array) $template_data, (array) $schedule );
		$schedule_with_template->schedule_id = $schedule->id;
		$schedule_with_template->name        = $template_data->name;
		$original_next_run = isset( $schedule->next_run ) ? (int) $schedule->next_run : null;

		if ( $advance_schedule && 'once' !== $schedule->frequency ) {
			$base_run = ! empty( $schedule->next_run ) ? (int) $schedule->next_run : AIPS_DateTime::now()->timestamp();
			$next_run = $this->interval_calculator->calculate_next_run(
				$schedule->frequency,
				$base_run
			);
			$this->repository->update( $schedule->id, array( 'next_run' => $next_run ) );
		}

		AIPS_Correlation_ID::generate();

		try {
			$result = $this->execute_schedule_logic( $schedule_with_template, true, $quantity_override, $advance_schedule, $original_next_run );
		} finally {
			AIPS_Correlation_ID::reset();
		}
		return $result;
	}

	/**
	 * Execute a schedule with claim-first locking.
	 *
	 * @param object $schedule Schedule object.
	 * @return mixed
	 */
	public function execute_schedule_with_lock( $schedule ) {
		$execution_result = null;

		$this->runner->run(
			function() use ( $schedule, &$execution_result ) {
				$original_next_run = $schedule->next_run;
				$run_state         = $this->get_decoded_run_state( $schedule );

				if (
					isset( $run_state['status'], $run_state['resume_next_run'] )
					&& self::RUN_STATE_YIELDED === $run_state['status']
					&& (int) $run_state['resume_next_run'] > (int) $original_next_run
				) {
					$new_next_run = (int) $run_state['resume_next_run'];
				} elseif ( 'once' === $schedule->frequency ) {
					$new_next_run = AIPS_DateTime::now()->addSeconds( HOUR_IN_SECONDS )->timestamp();
				} else {
					$new_next_run = $this->interval_calculator->calculate_next_run(
						$schedule->frequency,
						$original_next_run
					);
				}

				$lock_result = $this->repository->claim_due_schedule(
					(int) $schedule->schedule_id,
					(int) $original_next_run,
					(int) $new_next_run
				);

				if ( false === $lock_result ) {
					$this->logger->log(
						'Schedule ' . $schedule->schedule_id . ' was already claimed by another worker; skipping duplicate execution.',
						'info',
						array(
							'expected_next_run'  => (int) $original_next_run,
							'attempted_next_run' => (int) $new_next_run,
						)
					);
					return;
				}

				$this->logger->log(
					'Claimed schedule ' . $schedule->schedule_id . ' for execution.',
					'info',
					array(
						'expected_next_run' => (int) $original_next_run,
						'new_next_run'      => (int) $new_next_run,
						'frequency'         => $schedule->frequency,
					)
				);

				$schedule->claimed_next_run = (int) $new_next_run;
				$execution_result = $this->execute_schedule_logic( $schedule, false, null, true, null );
			},
			'schedule_execution',
			array( 'schedule_id' => $schedule->schedule_id ),
			function( \Throwable $e, $correlation_id ) use ( $schedule ) {
				do_action( 'aips_system_error', array(
					'title'          => __( 'Schedule processing exception', 'ai-post-scheduler' ),
					'message'        => sprintf(
						/* translators: 1: schedule ID, 2: error message */
						__( 'Schedule %1$d encountered an unexpected error: %2$s', 'ai-post-scheduler' ),
						$schedule->schedule_id,
						$e->getMessage()
					),
					'source'         => 'schedule_processor',
					'correlation_id' => $correlation_id,
					'error_code'     => 'schedule_exception',
					'error_data'     => array(
						'schedule_id' => $schedule->schedule_id,
						'file'        => $e->getFile(),
						'line'        => $e->getLine(),
					),
				) );
			}
		);

		return $execution_result;
	}

	/**
	 * Build trigger context for the schedule execution.
	 *
	 * @param object $schedule
	 * @return array
	 */
	private function build_schedule_trigger_context( $schedule ): array {
		return array(
			'source'         => 'schedule',
			'schedule_id'    => isset( $schedule->schedule_id ) ? (int) $schedule->schedule_id : null,
			'template_id'    => isset( $schedule->template_id ) ? (int) $schedule->template_id : null,
			'trigger_name'   => 'cron_scheduler',
			'correlation_id' => (string) AIPS_Correlation_ID::get(),
			'strategy'       => 'template',
		);
	}

	/**
	 * Main schedule execution logic.
	 *
	 * @param object   $schedule
	 * @param bool     $is_manual
	 * @param int|null $quantity_override
	 * @param bool     $advance_schedule
	 * @param int|null $restore_next_run
	 * @return mixed
	 */
	private function execute_schedule_logic( $schedule, $is_manual = false, $quantity_override = null, $advance_schedule = true, $restore_next_run = null ) {
		$config = AIPS_Config::get_instance();

		if ( $config->is_scheduled_ai_generation_prevented() ) {
			return $this->terminate_run_by_setting(
				$schedule,
				$is_manual,
				$this->resolve_post_quantity( $schedule, $quantity_override ),
				$restore_next_run,
				$config
			);
		}

		if ( ! $is_manual ) {
			do_action( 'aips_schedule_execution_started', $schedule->schedule_id );

			$this->logger->log( 'Processing schedule: ' . $schedule->schedule_id, 'info', array(
				'template_id'   => $schedule->template_id,
				'template_name' => $schedule->name,
				'topic'         => isset( $schedule->topic ) ? $schedule->topic : '',
			) );
		}

		$post_quantity        = $this->resolve_post_quantity( $schedule, $quantity_override );
		$article_structure_id = $this->template_type_selector->select_structure( $schedule );

		$event_type       = $is_manual ? 'manual_schedule_started' : 'schedule_executed';
		$log_activity_msg = $is_manual
			? sprintf( __( 'Manual execution of schedule "%s" started', 'ai-post-scheduler' ), $schedule->name )
			: sprintf( __( 'Schedule "%s" started execution', 'ai-post-scheduler' ), $schedule->name );

		$history = $this->result_handler->get_or_create_schedule_history( $schedule->schedule_id );

		if ( $history ) {
			$history->record(
				'activity',
				$log_activity_msg,
				array(
					'event_type'   => $event_type,
					'event_status' => 'success',
				),
				null,
				array(
					'schedule_id'          => $schedule->schedule_id,
					'template_id'          => $schedule->template_id,
					'frequency'            => $schedule->frequency,
					'topic'                => isset( $schedule->topic ) ? $schedule->topic : '',
					'article_structure_id' => $article_structure_id,
				)
			);
		}

		$batch_service = $this->get_batch_queue_service();
		if ( $batch_service->needs_batch_queue( $post_quantity ) ) {
			return $this->dispatch_large_batch( $schedule, $batch_service, $post_quantity, $is_manual, $history );
		}

		$template = AIPS_Template_Entry::from_template_and_overrides(
			(int) $schedule->template_id,
			$schedule,
			$post_quantity,
			$article_structure_id
		);

		$topic           = isset( $schedule->topic ) ? $schedule->topic : null;
		$creation_method = $is_manual ? 'manual' : 'scheduled';

		$context = new AIPS_Template_Context( $template, null, $topic, $creation_method );
		$context->set_trigger_context( $this->build_schedule_trigger_context( $schedule ) );

		list( $overall_result, $batch_finished ) = $this->execute_batch_progress( $schedule, $context, $post_quantity, $is_manual );

		if ( ! $is_manual && is_wp_error( $overall_result ) && 'batch_interrupted_timeout' === $overall_result->get_error_code() ) {
			$this->defer_yielded_batch( $schedule, $overall_result, $history );
			return $overall_result;
		}

		if ( ! $is_manual ) {
			$this->result_handler->handle_post_execution_cleanup( $schedule, $overall_result );
		} else {
			$this->template_type_selector->invalidate_count_cache( $schedule->schedule_id );

			if ( $advance_schedule && ! is_wp_error( $overall_result ) ) {
				$this->repository->update_last_run( $schedule->schedule_id, AIPS_DateTime::now()->timestamp() );
				if ( 'once' === $schedule->frequency ) {
					$this->repository->update( $schedule->schedule_id, array(
						'is_active' => 0,
						'status'    => 'completed',
					) );
				}
			}
		}

		if ( is_wp_error( $overall_result ) ) {
			$this->result_handler->handle_execution_failure( $schedule, $overall_result, $history, $is_manual );
		} else {
			$this->result_handler->handle_execution_success( $schedule, $overall_result, $history, $is_manual );
		}

		return $overall_result;
	}

	/**
	 * Defer a yielded batch.
	 *
	 * @param object   $schedule
	 * @param WP_Error $result
	 * @param mixed    $history
	 */
	private function defer_yielded_batch( $schedule, $result, $history ): void {
		$schedule_id = (int) $schedule->schedule_id;
		$data        = (array) $result->get_error_data();
		$now         = AIPS_DateTime::now()->timestamp();
		$resume_at   = $now + AIPS_Config::get_instance()->get_batch_resume_cooldown_seconds();
		$claimed     = isset( $schedule->claimed_next_run ) ? (int) $schedule->claimed_next_run : 0;

		if ( $claimed <= $now ) {
			$claimed = ! empty( $schedule->frequency ) && 'once' !== $schedule->frequency
				? $this->interval_calculator->calculate_next_run( $schedule->frequency, $now )
				: $resume_at + HOUR_IN_SECONDS;
		}

		$run_state = array(
			'status'    => self::RUN_STATE_YIELDED,
			'completed' => isset( $data['completed'] ) ? (int) $data['completed'] : 0,
			'total'     => isset( $data['total'] ) ? (int) $data['total'] : 0,
			'timestamp' => AIPS_DateTime::now()->toIso8601(),
		);

		if ( $claimed > $resume_at ) {
			$run_state['resume_next_run'] = $claimed;
			$run_state['resume_at']       = $resume_at;
			$this->repository->update_run_state( $schedule_id, $run_state );
			$this->repository->update( $schedule_id, array( 'next_run' => $resume_at ) );

			try {
				$job_manager = AIPS_Container::get_instance()->get_job_manager();
				$job_manager->dispatch(
					AIPS_Job_Registry::TYPE_SCHEDULE_POST_GENERATION,
					array( 'schedule_id' => $schedule_id ),
					array( 'delay' => max( 0, $resume_at - $now ) )
				);
			} catch ( Throwable $e ) {
				$this->logger->log( 'Failed to queue yielded schedule job: ' . $e->getMessage(), 'error' );
			}
		} else {
			$resume_at = $claimed;
			$this->repository->update_run_state( $schedule_id, $run_state );
		}

		$this->logger->log(
			sprintf(
				'Schedule %d yielded after %d of %d posts to avoid a script timeout; resuming at %d.',
				$schedule_id,
				$run_state['completed'],
				$run_state['total'],
				$resume_at
			),
			'info'
		);

		if ( $history ) {
			$history->record(
				'activity',
				sprintf(
					/* translators: 1: schedule name, 2: completed count, 3: total requested */
					__( 'Schedule "%1$s" yielded after %2$d of %3$d posts to avoid a script timeout; scheduled to resume.', 'ai-post-scheduler' ),
					$schedule->name,
					$run_state['completed'],
					$run_state['total']
				),
				array(
					'event_type'   => 'batch_yielded',
					'event_status' => 'info',
				),
				null,
				array(
					'schedule_id' => $schedule_id,
					'completed'   => $run_state['completed'],
					'total'       => $run_state['total'],
					'resume_at'   => $resume_at,
				)
			);
		}
	}

	/**
	 * Resolve effective post quantity.
	 *
	 * @param object   $schedule
	 * @param int|null $quantity_override
	 * @return int
	 */
	private function resolve_post_quantity( $schedule, $quantity_override = null ): int {
		if ( null !== $quantity_override && (int) $quantity_override > 0 ) {
			return (int) $quantity_override;
		}

		if ( isset( $schedule->post_quantity ) && (int) $schedule->post_quantity > 0 ) {
			return (int) $schedule->post_quantity;
		}

		if ( isset( $schedule->template_id ) ) {
			$template = $this->template_repository->get_by_id( $schedule->template_id );
			if ( $template && isset( $template->post_quantity ) && (int) $template->post_quantity > 0 ) {
				return (int) $template->post_quantity;
			}
		}

		return 1;
	}

	/**
	 * Terminate run when global AI generation setting is off.
	 *
	 * @param object      $schedule
	 * @param bool        $is_manual
	 * @param int         $post_quantity
	 * @param int|null    $restore_next_run
	 * @param AIPS_Config $config
	 * @return WP_Error
	 */
	private function terminate_run_by_setting( $schedule, $is_manual, $post_quantity, $restore_next_run, $config ) {
		$termination_options = array(
			'total'     => $post_quantity,
			'completed' => 0,
		);

		if ( null !== $restore_next_run ) {
			$termination_options['restore_next_run'] = (int) $restore_next_run;
		}

		if ( ! $is_manual && isset( $schedule->frequency ) && 'once' !== $schedule->frequency ) {
			$termination_options['update_last_run'] = true;
		}

		return $this->result_handler->handle_execution_terminated_by_setting(
			$schedule,
			$this->result_handler->get_or_create_schedule_history( $schedule->schedule_id ),
			$is_manual,
			$config->get_scheduled_ai_generation_prevention_label(),
			$termination_options
		);
	}

	/**
	 * Dispatch large batch.
	 *
	 * @param object                   $schedule
	 * @param AIPS_Batch_Queue_Service $batch_service
	 * @param int                      $post_quantity
	 * @param bool                     $is_manual
	 * @param mixed                    $history
	 * @return mixed
	 */
	private function dispatch_large_batch( $schedule, $batch_service, $post_quantity, $is_manual, $history ) {
		if ( $this->should_skip_batch_redispatch( $schedule, $batch_service ) ) {
			return null;
		}

		$now            = AIPS_DateTime::now()->timestamp();
		$correlation_id = (string) AIPS_Correlation_ID::get();

		$dispatch_summary = $batch_service->dispatch(
			(int) $schedule->schedule_id,
			$post_quantity,
			$now,
			$correlation_id
		);

		if ( is_wp_error( $dispatch_summary ) ) {
			$this->repository->update_run_state( $schedule->schedule_id, array(
				'status'         => 'failed',
				'error_code'     => $dispatch_summary->get_error_code(),
				'error_message'  => $dispatch_summary->get_error_message(),
				'completed'      => 0,
				'total'          => $post_quantity,
				'correlation_id' => $correlation_id,
				'timestamp'      => AIPS_DateTime::now()->toIso8601(),
			) );

			$this->logger->log(
				'Batch queue dispatch failed for schedule ' . $schedule->schedule_id . ': ' . $dispatch_summary->get_error_message(),
				'error'
			);

			if ( $history ) {
				$history->record(
					'warning',
					$dispatch_summary->get_error_message(),
					array(
						'event_type'   => 'batch_queue_dispatch_failed',
						'event_status' => 'failed',
					),
					null,
					array(
						'schedule_id'   => $schedule->schedule_id,
						'post_quantity' => $post_quantity,
						'error_code'    => $dispatch_summary->get_error_code(),
					)
				);
			}

			if ( $is_manual ) {
				return $dispatch_summary;
			}
			return null;
		}

		$this->repository->update_run_state( $schedule->schedule_id, array(
			'status'         => 'batch_queued',
			'completed'      => 0,
			'total'          => $post_quantity,
			'dispatched_at'  => $now,
			'correlation_id' => $correlation_id,
			'timestamp'      => AIPS_DateTime::now()->toIso8601(),
		) );

		$this->logger->log(
			sprintf(
				'Large-batch queue dispatched for schedule %d: %d posts in %d batches over %d seconds.',
				$schedule->schedule_id,
				$post_quantity,
				isset( $dispatch_summary['num_batches'] ) ? $dispatch_summary['num_batches'] : 0,
				isset( $dispatch_summary['window_seconds'] ) ? $dispatch_summary['window_seconds'] : 0
			),
			'info'
		);

		if ( $history ) {
			$history->record(
				'activity',
				sprintf(
					/* translators: 1: schedule name, 2: total post count, 3: number of batches */
					__( 'Dispatched %2$d posts for schedule "%1$s" across %3$d background batches to avoid timeout.', 'ai-post-scheduler' ),
					$schedule->name,
					$post_quantity,
					isset( $dispatch_summary['num_batches'] ) ? $dispatch_summary['num_batches'] : 0
				),
				array(
					'event_type'   => 'batch_queue_dispatched',
					'event_status' => 'info',
				),
				null,
				array_merge(
					array(
						'schedule_id'   => $schedule->schedule_id,
						'post_quantity' => $post_quantity,
					),
					$dispatch_summary
				)
			);
		}

		if ( $is_manual ) {
			return $dispatch_summary;
		}
		return null;
	}

	/**
	 * Execute resumable batch progress loop.
	 *
	 * @param object                $schedule
	 * @param AIPS_Template_Context $context
	 * @param int                   $post_quantity
	 * @param bool                  $is_manual
	 * @return array{0: int|int[]|WP_Error, 1: bool}
	 */
	public function execute_batch_progress( $schedule, $context, $post_quantity, $is_manual ): array {
		$config                 = AIPS_Config::get_instance();
		$delay_seconds          = $config->get_generation_delay_seconds();
		$interrupted_by_timeout = false;
		$overall_result         = null;
		$batch_finished         = false;
		$generated_ids          = array();
		$errors                 = array();

		$schedule_id    = isset( $schedule->schedule_id ) ? (int) $schedule->schedule_id : (int) $schedule->id;
		$start_index    = 0;
		$previous_posts = 0;
		$previous_ids   = array();

		if ( $is_manual ) {
			// A manual run always starts fresh; never inherit a stale scheduled cursor.
			$this->repository->clear_batch_progress( $schedule_id );
		} else {
			$current_progress = $this->repository->get_batch_progress( $schedule_id );
			if ( $current_progress && isset( $current_progress['last_index'], $current_progress['total'] ) && (int) $current_progress['total'] === $post_quantity ) {
				$last_index = (int) $current_progress['last_index'];
				if ( $last_index >= 0 && $last_index < ( $post_quantity - 1 ) ) {
					$previous_ids   = isset( $current_progress['post_ids'] ) && is_array( $current_progress['post_ids'] )
						? array_map( 'absint', $current_progress['post_ids'] )
						: array();
					$start_index    = ! empty( $previous_ids ) ? count( $previous_ids ) : ( $last_index + 1 );
					$previous_posts = ! empty( $previous_ids ) ? count( $previous_ids ) : ( isset( $current_progress['completed'] ) ? (int) $current_progress['completed'] : $start_index );
				}
			} elseif ( $current_progress ) {
				// Quantity changed since the cursor was written: discard the stale progress.
				$this->repository->clear_batch_progress( $schedule_id );
			}
		}

		$all_post_ids    = $previous_ids;
		$delay           = $is_manual ? 0 : $delay_seconds;
		$loop_started_at = microtime( true );

		for ( $i = $start_index; $i < $post_quantity; $i++ ) {
			if ( $i > $start_index ) {
				AIPS_Cache::reset_request_cache();
				$done_this_run  = $i - $start_index;
				$avg_seconds    = ( microtime( true ) - $loop_started_at ) / max( 1, $done_this_run );
				$needed_seconds = max( self::MIN_TIME_BUDGET_MARGIN, (int) ceil( $avg_seconds * 1.5 ) + $delay );
				if ( $this->is_time_exhausted( $needed_seconds ) ) {
					$interrupted_by_timeout = true;
					$overall_result         = new WP_Error(
						'batch_interrupted_timeout',
						sprintf(
							__( 'Execution interrupted to prevent script timeout. Completed %1$d of %2$d posts.', 'ai-post-scheduler' ),
							$previous_posts + count( $generated_ids ),
							$post_quantity
						),
						array(
							'completed' => $previous_posts + count( $generated_ids ),
							'total'     => $post_quantity,
						)
					);
					break;
				}
				if ( $delay > 0 ) {
					sleep( $delay );
				}
			}

			$result = $this->generator->generate_post( $context );

			if ( is_wp_error( $result ) ) {
				$errors[] = $result;
				if ( ! $is_manual ) {
					$completed_so_far = $previous_posts + count( $generated_ids );
					$this->repository->update_run_state( $schedule_id, array(
						'status'        => $completed_so_far > 0 ? 'partial' : 'failed',
						'error_code'    => $result->get_error_code(),
						'error_message' => $result->get_error_message(),
						'completed'     => $completed_so_far,
						'total'         => $post_quantity,
						'timestamp'     => AIPS_DateTime::now()->toIso8601(),
					) );
				}
				break;
			}

			$generated_ids[] = $result;
			$all_post_ids[]  = $result;

			if ( ! $is_manual ) {
				$this->repository->update_batch_progress(
					$schedule_id,
					$previous_posts + count( $generated_ids ),
					$post_quantity,
					$i,
					$all_post_ids
				);
			}
		}

		if ( empty( $errors ) && ! $interrupted_by_timeout ) {
			$batch_finished = true;
			if ( ! $is_manual ) {
				$this->repository->clear_batch_progress( $schedule_id );
				$this->repository->update_run_state( $schedule_id, array(
					'status'    => 'success',
					'completed' => $previous_posts + count( $generated_ids ),
					'total'     => $post_quantity,
					'timestamp' => AIPS_DateTime::now()->toIso8601(),
				) );
			}
			$overall_result = count( $all_post_ids ) > 1 ? $all_post_ids : ( $all_post_ids[0] ?? null );
		} elseif ( ! empty( $errors ) ) {
			$overall_result = ! empty( $all_post_ids )
				? new WP_Error(
					'batch_partially_failed',
					sprintf(
						/* translators: 1: completed count, 2: total, 3: error message */
						__( '%1$d of %2$d posts generated before error: %3$s', 'ai-post-scheduler' ),
						$previous_posts + count( $generated_ids ),
						$post_quantity,
						$errors[0]->get_error_message()
					)
				)
				: $errors[0];
		}

		return array( $overall_result, $batch_finished );
	}
}
