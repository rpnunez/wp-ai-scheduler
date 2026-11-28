<?php
/**
 * Shared bulk generation service
 *
 * Provides a unified, hardened execution harness for bulk post generation
 * across Author Topics, the Content Planner, and Topic Research.
 *
 * Responsibilities:
 *   1. Batch limit enforcement (hard limit rejects, soft limit truncates)
 *   2. Large-batch async queuing onto AIPS_Job_Manager (threshold check,
 *      job dispatch, history record)
 *   3. History container lifecycle (create, record per-post activity/warnings,
 *      complete with overall success/failure status)
 *   4. Uniform error collection and AIPS_Bulk_Generation_Result production
 *   5. Native background slice execution (handle_generation_slice,
 *      handle_publish_slice) honoring AIPS_Job_Context
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Value object returned by AIPS_Bulk_Generator_Service::run()
// ---------------------------------------------------------------------------

/**
 * Class AIPS_Bulk_Generation_Result
 *
 * Immutable value object produced by a single bulk-generation run.
 * All properties are public readonly — they can be read like normal
 * public properties but cannot be mutated after construction.
 * Controllers inspect the fields and build their own JSON response.
 */
class AIPS_Bulk_Generation_Result {

	/**
	 * Number of items that were generated successfully.
	 *
	 * @var int
	 */
	public readonly int $success_count;

	/**
	 * Number of items that failed (validation failures count here too).
	 *
	 * For a hard-limit overflow this equals the total number of items that
	 * were submitted; no generation was attempted in that case.
	 *
	 * @var int
	 */
	public readonly int $failed_count;

	/**
	 * Human-readable error strings, one entry per failed item.
	 *
	 * @var string[]
	 */
	public readonly array $errors;

	/**
	 * IDs of every post that was created/regenerated in this run.
	 *
	 * @var int[]
	 */
	public readonly array $post_ids;

	/**
	 * True when the batch was larger than the configured limit.
	 *
	 * For hard-mode this means the entire request was rejected.
	 * For soft-mode this means items were silently truncated.
	 *
	 * @var bool
	 */
	public readonly bool $was_limited;

	/**
	 * The effective bulk limit that was applied.
	 *
	 * Useful so the controller can embed the limit value in its error message
	 * without re-calling apply_filters().
	 *
	 * @var int
	 */
	public readonly int $max_bulk;

	/**
	 * True when the batch was large enough to be dispatched to the async
	 * batch queue rather than processed synchronously.
	 *
	 * Controllers should return an immediate "queued" response to the user
	 * and let the cron workers deliver results asynchronously.
	 *
	 * @var bool
	 */
	public readonly bool $was_queued;

	/**
	 * The job UUID when was_queued is true, or null for synchronous runs.
	 *
	 * @var string|null
	 */
	public readonly ?string $job_id;

	/**
	 * Constructor.
	 *
	 * @param int         $success_count Number of successful generations.
	 * @param int         $failed_count  Number of failures.
	 * @param string[]    $errors        Per-item error strings.
	 * @param int[]       $post_ids      IDs of generated posts.
	 * @param bool        $was_limited   Whether the batch limit was hit.
	 * @param int         $max_bulk      Effective limit that was applied.
	 * @param bool        $was_queued    Whether the batch was queued async.
	 * @param string|null $job_id        Async job UUID when was_queued is true.
	 */
	public function __construct(
		int $success_count,
		int $failed_count,
		array $errors,
		array $post_ids,
		bool $was_limited,
		int $max_bulk,
		bool $was_queued = false,
		?string $job_id = null
	) {
		$this->success_count = $success_count;
		$this->failed_count  = $failed_count;
		$this->errors        = $errors;
		$this->post_ids      = $post_ids;
		$this->was_limited   = $was_limited;
		$this->max_bulk      = $max_bulk;
		$this->was_queued    = $was_queued;
		$this->job_id        = $job_id;
	}
}

// ---------------------------------------------------------------------------
// Service class
// ---------------------------------------------------------------------------

/**
 * Class AIPS_Bulk_Generator_Service
 *
 * Shared harness for all bulk post-generation and publishing actions.
 */
class AIPS_Bulk_Generator_Service {

	/**
	 * Default item limit when no limit_default option and no filter override are given.
	 *
	 * @var int
	 */
	const DEFAULT_LIMIT = 5;

	/**
	 * Registered strategy callables, keyed by strategy name.
	 *
	 * @var array<string, callable>
	 */
	private static array $custom_strategies = array();

	/**
	 * @var AIPS_History_Service_Interface
	 */
	private $history_service;

	/**
	 * @var AIPS_Job_Manager|null Lazy-loaded job manager.
	 */
	private $job_manager;

	/**
	 * @var AIPS_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param AIPS_History_Service_Interface|null $history_service Injectable for testing.
	 * @param AIPS_Job_Manager|object|null        $job_manager_or_store Injectable job manager.
	 * @param AIPS_Logger|object|null             $logger_or_queue Injectable logger.
	 */
	public function __construct(
		?AIPS_History_Service_Interface $history_service      = null,
		$job_manager_or_store                                 = null,
		$logger_or_queue                                      = null
	) {
		$container             = AIPS_Container::get_instance();
		$this->history_service = $history_service ?: ( $container->has( AIPS_History_Service_Interface::class ) ? $container->make( AIPS_History_Service_Interface::class ) : new AIPS_History_Service() );
		$this->job_manager     = ( $job_manager_or_store instanceof AIPS_Job_Manager ) ? $job_manager_or_store : null;
		$this->logger          = ( $logger_or_queue instanceof AIPS_Logger ) ? $logger_or_queue : new AIPS_Logger();
	}

	/**
	 * Register a custom strategy callable.
	 *
	 * @param string   $strategy Strategy key.
	 * @param callable $handler  fn( $item, string $job_id, object $job ): int|WP_Error
	 * @return void
	 */
	public static function register_strategy( string $strategy, callable $handler ): void {
		self::$custom_strategies[ $strategy ] = $handler;
	}

	/**
	 * Check if a custom strategy is registered.
	 *
	 * @param string $strategy Strategy key.
	 * @return bool
	 */
	public static function has_strategy( string $strategy ): bool {
		return isset( self::$custom_strategies[ $strategy ] );
	}

	/**
	 * Clear registered custom strategies (primarily for testing).
	 *
	 * @return void
	 */
	public static function reset_strategies(): void {
		self::$custom_strategies = array();
	}

	/**
	 * Set a custom job manager (dependency injection).
	 *
	 * @param AIPS_Job_Manager $job_manager
	 */
	public function set_job_manager( AIPS_Job_Manager $job_manager ): void {
		$this->job_manager = $job_manager;
	}

	/**
	 * Lazy-load the job manager.
	 *
	 * @return AIPS_Job_Manager
	 */
	private function get_job_manager(): AIPS_Job_Manager {
		if ( null === $this->job_manager ) {
			$this->job_manager = AIPS_Container::get_instance()->get_job_manager();
		}
		return $this->job_manager;
	}

	/**
	 * Return the configured large-batch threshold.
	 *
	 * @return int
	 */
	public function get_large_batch_threshold(): int {
		$threshold = (int) apply_filters( 'aips_large_batch_threshold', self::DEFAULT_LIMIT );
		return max( 2, $threshold );
	}

	/**
	 * Determine whether an item count qualifies as a large batch.
	 *
	 * @param int $count Total items requested.
	 * @return bool
	 */
	public function needs_batch_queue( int $count ): bool {
		return $count >= $this->get_large_batch_threshold();
	}

	/**
	 * Run a bulk generation batch.
	 *
	 * @param array    $items       Sanitized items to process.
	 * @param callable $generate_fn fn( $item ): int|WP_Error
	 * @param array    $options     Options array.
	 * @return AIPS_Bulk_Generation_Result
	 */
	public function run( array $items, callable $generate_fn, array $options = array() ): AIPS_Bulk_Generation_Result {
		$limit_filter   = isset( $options['limit_filter'] ) ? (string) $options['limit_filter'] : 'aips_bulk_run_now_limit';
		$limit_default  = isset( $options['limit_default'] ) ? (int) $options['limit_default'] : self::DEFAULT_LIMIT;
		$limit_mode     = isset( $options['limit_mode'] ) ? (string) $options['limit_mode'] : 'hard';
		$queue_job_type = isset( $options['queue_job_type'] ) ? (string) $options['queue_job_type'] : '';

		$max_bulk = absint( apply_filters( $limit_filter, $limit_default ) );
		if ( $max_bulk < 1 ) {
			$max_bulk = $limit_default;
		}

		$was_limited = false;

		// Large-batch async queuing (when queue_job_type is provided)
		if ( '' !== $queue_job_type && $this->needs_batch_queue( count( $items ) ) ) {
			return $this->dispatch_async( $items, $options, $queue_job_type, $max_bulk );
		}

		if ( count( $items ) > $max_bulk ) {
			if ( 'soft' === $limit_mode ) {
				$was_limited = true;
				$items       = array_slice( $items, 0, $max_bulk );
			} else {
				return new AIPS_Bulk_Generation_Result(
					0,
					count( $items ),
					array(),
					array(),
					true,
					$max_bulk
				);
			}
		}

		// Create history container for synchronous run
		$history_type    = isset( $options['history_type'] ) ? (string) $options['history_type'] : 'bulk_generation';
		$history_meta    = isset( $options['history_meta'] ) ? (array) $options['history_meta'] : array();
		$trigger_name    = isset( $options['trigger_name'] ) ? (string) $options['trigger_name'] : 'bulk_generator_service';
		$user_action     = isset( $options['user_action'] ) ? (string) $options['user_action'] : 'bulk_generate';
		$user_message    = isset( $options['user_message'] ) ? (string) $options['user_message']
			: sprintf( __( 'User initiated bulk generation for %d items', 'ai-post-scheduler' ), count( $items ) );
		$error_formatter = isset( $options['error_formatter'] ) && is_callable( $options['error_formatter'] )
			? $options['error_formatter']
			: null;

		$meta = array_merge(
			array(
				'user_id'    => get_current_user_id(),
				'source'     => 'manual_ui',
				'trigger'    => $trigger_name,
				'item_count' => count( $items ),
			),
			$history_meta
		);

		$history = $this->history_service->create( $history_type, array_merge( $meta, array( 'record_kind' => AIPS_History_Record_Kind::JOB ) ) );
		AIPS_Generation_Trigger::record(
			$history,
			array(
				'event'  => __( 'Bulk generation', 'ai-post-scheduler' ),
				'detail' => sprintf( __( '%1$d items via %2$s', 'ai-post-scheduler' ), count( $items ), $trigger_name ),
			),
			'manual'
		);
		$history->record_user_action( $user_action, $user_message, array( 'item_count' => count( $items ) ) );

		if ( $was_limited ) {
			$history->record(
				'activity',
				sprintf( __( 'Batch was limited to %d items to avoid timeouts.', 'ai-post-scheduler' ), count( $items ) ),
				null,
				null,
				array( 'truncated_to' => count( $items ) )
			);
		}

		$success_count = 0;
		$failed_count  = 0;
		$errors        = array();
		$post_ids      = array();

		foreach ( $items as $item ) {
			$result = call_user_func( $generate_fn, $item );

			if ( is_wp_error( $result ) ) {
				$failed_count++;

				if ( $error_formatter ) {
					$error_msg = call_user_func( $error_formatter, $item, $result->get_error_message() );
				} elseif ( ! is_scalar( $item ) ) {
					_doing_it_wrong(
						__METHOD__,
						'AIPS_Bulk_Generator_Service::run() received a non-scalar item without an error_formatter option. Provide an error_formatter to generate meaningful error messages.',
						'1.8.0'
					);
					$error_msg = sprintf( '%s: %s', wp_json_encode( $item ), $result->get_error_message() );
				} else {
					$error_msg = sprintf( '%s: %s', (string) $item, $result->get_error_message() );
				}
				$errors[] = $error_msg;
				$history->record(
					'warning',
					$error_msg,
					null,
					null,
					array( 'item' => $item, 'error_code' => $result->get_error_code() )
				);
			} else {
				$success_count++;
				$post_id_or_ids = is_array( $result ) ? $result : (int) $result;
				$post_ids[]     = $post_id_or_ids;
				$history->record(
					'activity',
					sprintf( __( 'Post %s generated successfully', 'ai-post-scheduler' ), is_array( $post_id_or_ids ) ? implode( ',', $post_id_or_ids ) : $post_id_or_ids ),
					null,
					null,
					array( 'item' => $item, 'post_id' => $post_id_or_ids )
				);
			}
		}

		if ( $failed_count > 0 ) {
			$history->complete_failure(
				sprintf( __( 'Bulk generation completed with %d failures', 'ai-post-scheduler' ), $failed_count ),
				array( 'success_count' => $success_count, 'failed_count' => $failed_count )
			);
		} else {
			$history->complete_success(
				array( 'success_count' => $success_count, 'failed_count' => 0 )
			);
		}

		return new AIPS_Bulk_Generation_Result(
			$success_count,
			$failed_count,
			$errors,
			$post_ids,
			$was_limited,
			$max_bulk
		);
	}

	/**
	 * Persist a large-batch job and dispatch async via AIPS_Job_Manager.
	 *
	 * @param array  $items          Items to queue.
	 * @param array  $options        Raw options from run() caller.
	 * @param string $queue_job_type Strategy key.
	 * @param int    $max_bulk       Effective per-request limit.
	 * @return AIPS_Bulk_Generation_Result Result with was_queued = true.
	 */
	private function dispatch_async(
		array $items,
		array $options,
		string $queue_job_type,
		int $max_bulk
	): AIPS_Bulk_Generation_Result {
		$job_type = ( 'review_publish' === $queue_job_type )
			? AIPS_Job_Registry::TYPE_BULK_POST_PUBLISH
			: AIPS_Job_Registry::TYPE_BULK_POST_GENERATION;

		$options['strategy']       = $queue_job_type;
		$options['queue_job_type'] = $queue_job_type;
		if ( ! isset( $options['user_id'] ) ) {
			$options['user_id'] = get_current_user_id();
		}
		unset( $options['error_formatter'] );

		try {
			$job_id = $this->get_job_manager()->dispatch_batch( $job_type, $items, $options );
		} catch ( Throwable $e ) {
			return new AIPS_Bulk_Generation_Result(
				0,
				count( $items ),
				array( $e->getMessage() ),
				array(),
				false,
				$max_bulk
			);
		}

		// Log dispatch summary to history
		$history_type = isset( $options['history_type'] ) ? (string) $options['history_type'] : 'bulk_generation';
		$history_meta = isset( $options['history_meta'] ) ? (array) $options['history_meta'] : array();
		$trigger_name = isset( $options['trigger_name'] ) ? (string) $options['trigger_name'] : 'bulk_generator_service';
		$user_action  = isset( $options['user_action'] ) ? (string) $options['user_action'] : 'bulk_generate';
		$user_message = isset( $options['user_message'] ) ? (string) $options['user_message']
			: sprintf( __( 'User initiated bulk generation for %d items', 'ai-post-scheduler' ), count( $items ) );

		$meta = array_merge(
			array(
				'user_id'    => get_current_user_id(),
				'source'     => 'manual_ui',
				'trigger'    => $trigger_name,
				'item_count' => count( $items ),
				'job_id'     => $job_id,
			),
			$history_meta
		);

		$history = $this->history_service->create( $history_type, array_merge( $meta, array( 'record_kind' => AIPS_History_Record_Kind::JOB ) ) );
		AIPS_Generation_Trigger::record(
			$history,
			array(
				'event'  => __( 'Bulk generation (queued)', 'ai-post-scheduler' ),
				'detail' => sprintf( __( 'Bulk job %1$s, %2$d items via %3$s', 'ai-post-scheduler' ), $job_id, count( $items ), $trigger_name ),
			),
			'manual'
		);
		$history->record_user_action( $user_action, $user_message, array( 'item_count' => count( $items ) ) );
		$history->record(
			'activity',
			sprintf(
				__( 'Large batch detected (%1$d items): queued for background processing via Job Manager.', 'ai-post-scheduler' ),
				count( $items )
			),
			null,
			null,
			array(
				'job_id'   => $job_id,
				'job_type' => $queue_job_type,
				'total'    => count( $items ),
			)
		);
		$history->complete_success( array( 'queued' => true, 'item_count' => count( $items ) ) );

		return new AIPS_Bulk_Generation_Result(
			0,
			0,
			array(),
			array(),
			false,
			$max_bulk,
			true,
			$job_id
		);
	}

	// -----------------------------------------------------------------------
	// Job Manager Slice Runner Handlers
	// -----------------------------------------------------------------------

	/**
	 * Main runner tick callback invoked by AIPS_Job_Manager.
	 *
	 * @param AIPS_Job_Context $context
	 * @return AIPS_Job_Slice_Result
	 */
	public function handle_job_slice( AIPS_Job_Context $context ): AIPS_Job_Slice_Result {
		if ( AIPS_Job_Registry::TYPE_BULK_POST_PUBLISH === $context->get_job_type() ) {
			return $this->handle_publish_slice( $context );
		}

		return $this->handle_generation_slice( $context );
	}

	/**
	 * Process a batch slice of bulk post generation.
	 *
	 * @param AIPS_Job_Context $context
	 * @return AIPS_Job_Slice_Result
	 */
	public function handle_generation_slice( AIPS_Job_Context $context ): AIPS_Job_Slice_Result {
		$payload    = $context->get_payload();
		$items      = isset( $payload['items'] ) && is_array( $payload['items'] ) ? $payload['items'] : array();
		$total      = count( $items );
		$cursor     = $context->get_cursor_id();
		$batch_size = isset( $payload['batch_size'] ) ? max( 1, (int) $payload['batch_size'] ) : 5;
		$strategy   = isset( $payload['strategy'] ) ? (string) $payload['strategy'] : ( isset( $payload['queue_job_type'] ) ? (string) $payload['queue_job_type'] : 'author_topic_post' );
		$job_id     = $context->get_job_id();

		if ( $cursor >= $total ) {
			return AIPS_Job_Slice_Result::complete();
		}

		$history = $this->history_service->create(
			'bulk_batch_slice',
			array(
				'job_id'         => $job_id,
				'job_type'       => $strategy,
				'start_index'    => $cursor,
				'batch_size'     => $batch_size,
				'total_quantity' => $total,
			)
		);

		AIPS_Generation_Trigger::record(
			$history,
			array(
				'event'  => __( 'Bulk batch slice', 'ai-post-scheduler' ),
				'detail' => sprintf( __( 'Bulk job %1$s (%2$s)', 'ai-post-scheduler' ), $job_id, $strategy ),
			),
			'scheduled'
		);

		$processed = 0;
		$failed    = 0;
		$results   = array();

		while ( $cursor < $total && ( $processed + $failed ) < $batch_size ) {
			if ( ( $processed + $failed ) > 0 && $context->should_yield() ) {
				break;
			}

			$item = $items[ $cursor ];

			try {
				$res = $this->execute_item( $strategy, $item, $payload, $job_id );
				if ( is_wp_error( $res ) ) {
					$failed++;
					$history->record(
						'warning',
						$res->get_error_message(),
						null,
						null,
						array( 'item' => $item, 'error_code' => $res->get_error_code() )
					);
				} else {
					$processed++;
					$post_id_val = is_array( $res ) ? $res : (int) $res;
					$results[]   = $post_id_val;
					$history->record(
						'activity',
						sprintf( __( 'Post %s generated successfully', 'ai-post-scheduler' ), is_array( $post_id_val ) ? implode( ',', $post_id_val ) : $post_id_val ),
						null,
						null,
						array( 'item' => $item, 'post_id' => $post_id_val )
					);
				}
			} catch ( Throwable $e ) {
				$failed++;
				$this->logger->log( sprintf( 'Bulk slice item error in job %s: %s', $job_id, $e->getMessage() ), 'error' );
				$history->record( 'warning', $e->getMessage(), null, null, array( 'item' => $item, 'exception' => $e->getMessage() ) );
			}

			$cursor++;
		}

		if ( $failed > 0 ) {
			$history->complete_failure(
				sprintf( __( 'Batch slice completed with %d failures', 'ai-post-scheduler' ), $failed ),
				array( 'success_count' => $processed, 'failed_count' => $failed )
			);
		} else {
			$history->complete_success( array( 'success_count' => $processed, 'failed_count' => 0 ) );
		}

		$is_complete = ( $cursor >= $total );
		if ( $is_complete ) {
			return AIPS_Job_Slice_Result::complete( $processed, $failed, $results );
		}

		return AIPS_Job_Slice_Result::continue( $processed, $cursor, $failed, $results );
	}

	/**
	 * Process a batch slice of bulk post publishing from Pending Review.
	 *
	 * @param AIPS_Job_Context $context
	 * @return AIPS_Job_Slice_Result
	 */
	public function handle_publish_slice( AIPS_Job_Context $context ): AIPS_Job_Slice_Result {
		$payload    = $context->get_payload();
		$items      = isset( $payload['items'] ) && is_array( $payload['items'] ) ? $payload['items'] : array();
		$total      = count( $items );
		$cursor     = $context->get_cursor_id();
		$batch_size = isset( $payload['batch_size'] ) ? max( 1, (int) $payload['batch_size'] ) : 25;
		$job_id     = $context->get_job_id();

		if ( $cursor >= $total ) {
			return AIPS_Job_Slice_Result::complete();
		}

		$acting_user_id = isset( $payload['history_meta']['acting_user_id'] )
			? (int) $payload['history_meta']['acting_user_id']
			: ( isset( $payload['user_id'] ) ? (int) $payload['user_id'] : 0 );

		if ( $acting_user_id > 0 && get_current_user_id() !== $acting_user_id ) {
			wp_set_current_user( $acting_user_id );
		}

		$history = $this->history_service->create(
			'bulk_batch_slice',
			array(
				'job_id'         => $job_id,
				'job_type'       => 'review_publish',
				'start_index'    => $cursor,
				'batch_size'     => $batch_size,
				'total_quantity' => $total,
			)
		);

		AIPS_Generation_Trigger::record(
			$history,
			array(
				'event'  => __( 'Bulk publish slice', 'ai-post-scheduler' ),
				'detail' => sprintf( __( 'Bulk job %s (Publish)', 'ai-post-scheduler' ), $job_id ),
			),
			'scheduled'
		);

		$processed = 0;
		$failed    = 0;
		$results   = array();
		$review    = AIPS_Post_Review::instance();

		while ( $cursor < $total && ( $processed + $failed ) < $batch_size ) {
			if ( ( $processed + $failed ) > 0 && $context->should_yield() ) {
				break;
			}

			$post_id = (int) $items[ $cursor ];

			try {
				$res = $review->publish_review_post( $post_id );
				if ( is_wp_error( $res ) ) {
					$failed++;
					$history->record( 'warning', $res->get_error_message(), null, null, array( 'post_id' => $post_id ) );
				} else {
					$processed++;
					$results[] = $post_id;
					$history->record(
						'activity',
						sprintf( __( 'Post %d published successfully', 'ai-post-scheduler' ), $post_id ),
						null,
						null,
						array( 'post_id' => $post_id )
					);
				}
			} catch ( Throwable $e ) {
				$failed++;
				$this->logger->log( sprintf( 'Bulk publish error for post %d in job %s: %s', $post_id, $job_id, $e->getMessage() ), 'error' );
				$history->record( 'warning', $e->getMessage(), null, null, array( 'post_id' => $post_id, 'exception' => $e->getMessage() ) );
			}

			$cursor++;
		}

		if ( $failed > 0 ) {
			$history->complete_failure(
				sprintf( __( 'Publish slice completed with %d failures', 'ai-post-scheduler' ), $failed ),
				array( 'success_count' => $processed, 'failed_count' => $failed )
			);
		} else {
			$history->complete_success( array( 'success_count' => $processed, 'failed_count' => 0 ) );
		}

		$is_complete = ( $cursor >= $total );
		if ( $is_complete ) {
			return AIPS_Job_Slice_Result::complete( $processed, $failed, $results );
		}

		return AIPS_Job_Slice_Result::continue( $processed, $cursor, $failed, $results );
	}

	/**
	 * Execute a single item under the given strategy.
	 *
	 * @param string $strategy Strategy name.
	 * @param mixed  $item     Item from items collection.
	 * @param array  $payload  Job payload.
	 * @param string $job_id   Job UUID.
	 * @return int|WP_Error
	 */
	private function execute_item( string $strategy, $item, array $payload, string $job_id ) {
		if ( isset( self::$custom_strategies[ $strategy ] ) && is_callable( self::$custom_strategies[ $strategy ] ) ) {
			$job_obj = (object) array(
				'job_id'   => $job_id,
				'job_type' => $strategy,
				'options'  => $payload,
				'items'    => isset( $payload['items'] ) && is_array( $payload['items'] ) ? $payload['items'] : array(),
			);
			return call_user_func( self::$custom_strategies[ $strategy ], $item, $job_id, $job_obj );
		}

		if ( 'author_topic_post' === $strategy ) {
			return AIPS_Author_Post_Generator::instance()->generate_now(
				(int) $item,
				'author_topic_post',
				array( 'detail' => sprintf( __( 'Bulk job %s (Author topics)', 'ai-post-scheduler' ), $job_id ) )
			);
		}

		if ( 'planner_post' === $strategy ) {
			$template_id = isset( $payload['history_meta']['template_id'] )
				? (int) $payload['history_meta']['template_id']
				: 0;

			if ( $template_id <= 0 ) {
				return new WP_Error(
					'planner_post_missing_template',
					__( 'planner_post strategy requires a template_id stored in job options.', 'ai-post-scheduler' )
				);
			}

			$template = ( new AIPS_Template_Repository() )->get_by_id( $template_id );

			if ( ! $template || empty( $template->is_active ) ) {
				return new WP_Error(
					'planner_post_template_not_found',
					sprintf( __( 'Template %d not found or inactive for planner_post strategy.', 'ai-post-scheduler' ), $template_id )
				);
			}

			$topic   = is_array( $item ) ? ( $item['topic'] ?? (string) $item ) : (string) $item;
			$context = new AIPS_Template_Context( $template, null, $topic, 'planner_post' );
			$context->set_trigger_context(
				array(
					'detail' => sprintf( __( 'Bulk job %s (Planner)', 'ai-post-scheduler' ), $job_id ),
				)
			);
			$generator = new AIPS_Generator();

			return $generator->generate_post( $context );
		}

		if ( 'trending_topic_post' === $strategy ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) || ! isset( $item['topic'] ) ) {
				return new WP_Error(
					'invalid_trending_topic_item',
					__( 'Item must be an array with id and topic keys.', 'ai-post-scheduler' )
				);
			}

			$template_id = isset( $payload['history_meta']['template_id'] )
				? (int) $payload['history_meta']['template_id']
				: 0;

			if ( $template_id <= 0 ) {
				return new WP_Error(
					'trending_topic_post_missing_template',
					__( 'trending_topic_post strategy requires a template_id stored in job options.', 'ai-post-scheduler' )
				);
			}

			$template = ( new AIPS_Template_Repository() )->get_by_id( $template_id );

			if ( ! $template || empty( $template->is_active ) ) {
				return new WP_Error(
					'trending_topic_post_template_not_found',
					sprintf( __( 'Template %d not found or inactive for trending_topic_post strategy.', 'ai-post-scheduler' ), $template_id )
				);
			}

			$context = new AIPS_Template_Context( $template, null, (string) $item['topic'], 'trending_topic_post' );
			$context->set_trigger_context(
				array(
					'detail' => sprintf( __( 'Bulk job %s (Trending topics)', 'ai-post-scheduler' ), $job_id ),
				)
			);
			$generator = new AIPS_Generator();
			$post_id   = $generator->generate_post( $context );

			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			update_post_meta( $post_id, AIPS_Post_Manager::META_TRENDING_TOPIC_ID, absint( $item['id'] ) );
			update_post_meta( $post_id, AIPS_Post_Manager::META_TRENDING_TOPIC_TEXT, sanitize_text_field( (string) $item['topic'] ) );

			// Mark the trending topic generated so the async path matches the synchronous one.
			( new AIPS_Trending_Topics_Repository() )->update_status_bulk( array( absint( $item['id'] ) ), 'generated' );

			return $post_id;
		}

		return new WP_Error( 'unknown_bulk_strategy', sprintf( __( 'Unknown bulk strategy: %s', 'ai-post-scheduler' ), $strategy ) );
	}
}
