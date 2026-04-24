<?php
/**
 * Bulk Batch Processor
 *
 * Strategy registry + cron callback for the async bulk-generation pipeline.
 *
 * Why a strategy registry?
 * ------------------------
 * PHP closures are not serialisable and therefore cannot be stored in the
 * database or passed as WP-Cron event arguments.  AIPS_Bulk_Batch_Processor
 * solves this by decoupling the job descriptor (stored in
 * AIPS_Bulk_Batch_Job_Store) from the callable that processes each item.
 *
 * At boot time, each caller registers a named strategy:
 *
 *   AIPS_Bulk_Batch_Processor::instance()->register(
 *       'author_topic_post',
 *       function( $topic_id, $job_id, $job ) {
 *           return AIPS_Author_Post_Generator::instance()->generate_now( (int) $topic_id );
 *       }
 *   );
 *
 * When the `aips_process_bulk_batch` cron hook fires, process() looks up
 * the strategy by job_type, slices the stored items, and calls the handler
 * for each item in the slice.
 *
 * Cron hook signature
 * -------------------
 * Hook:  aips_process_bulk_batch
 * Args:  [ job_id (string), start_index (int), batch_size (int),
 *          total_quantity (int), correlation_id (string) ]
 *
 * @package AI_Post_Scheduler
 * @since   2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Bulk_Batch_Processor
 *
 * Singleton strategy registry and cron callback handler for the async
 * bulk-generation pipeline introduced in 2.6.0.
 */
class AIPS_Bulk_Batch_Processor {

	/**
	 * WordPress cron hook name for bulk-batch single events.
	 *
	 * @var string
	 */
	const HOOK = 'aips_process_bulk_batch';

	/**
	 * @var self|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * Registered strategy callables, keyed by job_type.
	 *
	 * @var array<string, callable>
	 */
	private $strategies = array();

	/**
	 * @var AIPS_Bulk_Batch_Job_Store
	 */
	private $job_store;

	/**
	 * @var AIPS_History_Service
	 */
	private $history_service;

	/**
	 * @var AIPS_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Bulk_Batch_Job_Store|null $job_store       Injectable for testing.
	 * @param AIPS_History_Service|null      $history_service Injectable for testing.
	 * @param AIPS_Logger|null               $logger          Injectable for testing.
	 */
	public function __construct(
		?AIPS_Bulk_Batch_Job_Store $job_store       = null,
		?AIPS_History_Service      $history_service = null,
		?AIPS_Logger               $logger          = null
	) {
		$this->job_store       = $job_store       ?: new AIPS_Bulk_Batch_Job_Store();
		$this->history_service = $history_service ?: new AIPS_History_Service();
		$this->logger          = $logger          ?: new AIPS_Logger();
	}

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

	// -----------------------------------------------------------------------
	// Registry
	// -----------------------------------------------------------------------

	/**
	 * Register a strategy callable for a given job type.
	 *
	 * The callable receives three arguments:
	 *   - $item    — a single item from the stored items array
	 *   - $job_id  — UUID string of the parent job
	 *   - $job     — the full job object from AIPS_Bulk_Batch_Job_Store::get()
	 *                (includes $job->options for reading per-job context like template_id)
	 *
	 * It must return an int (post ID) or WP_Error.
	 *
	 * @param string   $job_type Strategy key; must match the `job_type` used
	 *                           when creating the job via AIPS_Bulk_Batch_Job_Store.
	 * @param callable $handler  fn( $item, string $job_id, object $job ): int|WP_Error
	 * @return void
	 */
	public function register( string $job_type, callable $handler ): void {
		$this->strategies[ $job_type ] = $handler;
	}

	/**
	 * Return true when a strategy is registered for the given type.
	 *
	 * @param string $job_type Strategy key to check.
	 * @return bool
	 */
	public function has_strategy( string $job_type ): bool {
		return isset( $this->strategies[ $job_type ] );
	}

	// -----------------------------------------------------------------------
	// Cron callback
	// -----------------------------------------------------------------------

	/**
	 * Process one batch slice of a queued bulk-generation job.
	 *
	 * This is the callback bound to the `aips_process_bulk_batch` cron hook.
	 * It retrieves the job from the store, slices the items array, and calls
	 * the registered strategy for each item in the slice.
	 *
	 * @param string $job_id         UUID of the job to process.
	 * @param int    $start_index    Zero-based index of the first item in this slice.
	 * @param int    $batch_size     Number of items to process in this slice.
	 * @param int    $total_quantity Total item count across all slices.
	 * @param string $correlation_id Correlation ID for tracing.
	 * @return void
	 */
	public function process(
		string $job_id,
		int    $start_index,
		int    $batch_size,
		int    $total_quantity,
		string $correlation_id = ''
	): void {
		if ( ! empty( $correlation_id ) ) {
			AIPS_Correlation_ID::set( $correlation_id );
		}

		$this->logger->log(
			sprintf(
				'Bulk batch processor: starting job %s slice [%d, +%d] of %d',
				$job_id,
				$start_index,
				$batch_size,
				$total_quantity
			),
			'info'
		);

		// Load the job descriptor.
		$job = $this->job_store->get( $job_id );

		if ( ! $job ) {
			$this->logger->log(
				sprintf( 'Bulk batch processor: job %s not found — skipping.', $job_id ),
				'warning'
			);
			return;
		}

		if ( $job->status === AIPS_Bulk_Batch_Job_Store::STATUS_COMPLETED ) {
			$this->logger->log(
				sprintf( 'Bulk batch processor: job %s already completed; skipping duplicate slice.', $job_id ),
				'warning'
			);
			return;
		}

		// Look up the registered strategy.
		$job_type = $job->job_type;
		if ( ! $this->has_strategy( $job_type ) ) {
			$this->logger->log(
				sprintf( 'Bulk batch processor: no strategy registered for job type "%s" — skipping.', $job_type ),
				'warning'
			);
			$this->job_store->update_status( $job_id, AIPS_Bulk_Batch_Job_Store::STATUS_FAILED );
			return;
		}

		$strategy = $this->strategies[ $job_type ];

		// Slice the items for this batch.
		$items_slice = array_slice( $job->items, $start_index, $batch_size );

		if ( empty( $items_slice ) ) {
			$this->logger->log(
				sprintf( 'Bulk batch processor: empty slice for job %s at start_index %d — skipping.', $job_id, $start_index ),
				'warning'
			);
			return;
		}

		if ( $job->status === AIPS_Bulk_Batch_Job_Store::STATUS_PENDING ) {
			$this->job_store->start_processing( $job_id );
			$this->logger->log(
				sprintf( 'Bulk batch processor: job %s transitioned from pending to processing.', $job_id ),
				'info'
			);
		}

		// Create a history container for this slice.
		$history = $this->history_service->create(
			'bulk_batch_slice',
			array(
				'job_id'         => $job_id,
				'job_type'       => $job_type,
				'start_index'    => $start_index,
				'batch_size'     => $batch_size,
				'total_quantity' => $total_quantity,
				'correlation_id' => $correlation_id,
			)
		);

		$history->record(
			'activity',
			sprintf(
				/* translators: 1: start index, 2: batch size, 3: total */
				__( 'Processing bulk batch slice: items %1$d–%2$d of %3$d', 'ai-post-scheduler' ),
				$start_index + 1,
				min( $start_index + $batch_size, $total_quantity ),
				$total_quantity
			),
			null,
			null,
			array(
				'job_id'      => $job_id,
				'job_type'    => $job_type,
				'start_index' => $start_index,
				'batch_size'  => $batch_size,
			)
		);

		// Execute the strategy for each item in the slice.
		$success_count = 0;
		$failed_count  = 0;

		foreach ( $items_slice as $item ) {
			try {
				$result = call_user_func( $strategy, $item, $job_id, $job );

				if ( is_wp_error( $result ) ) {
					$failed_count++;
					$history->record(
						'warning',
						$result->get_error_message(),
						null,
						null,
						array( 'item' => $item, 'error_code' => $result->get_error_code() )
					);
				} else {
					$success_count++;
					$post_result = is_array( $result ) ? $result : (int) $result;
					$history->record(
						'activity',
						/* translators: %s: post ID */
						sprintf( __( 'Post %s generated successfully', 'ai-post-scheduler' ), is_array( $post_result ) ? implode( ',', $post_result ) : $post_result ),
						null,
						null,
						array( 'item' => $item, 'post_id' => $post_result )
					);
				}
			} catch ( Throwable $e ) {
				$failed_count++;
				$this->logger->log(
					sprintf( 'Bulk batch processor: exception for item in job %s: %s', $job_id, $e->getMessage() ),
					'error'
				);
				$history->record(
					'warning',
					$e->getMessage(),
					null,
					null,
					array( 'item' => $item, 'exception' => $e->getMessage() )
				);
			}
		}

		// Increment the processed counter atomically.
		$this->job_store->increment_processed( $job_id, count( $items_slice ) );
		$job_after_slice = $this->job_store->get( $job_id );
		$processed_total = $job_after_slice ? (int) $job_after_slice->processed : 0;
		$total_items     = $job_after_slice ? (int) $job_after_slice->total : $total_quantity;

		if ( $failed_count > 0 ) {
			$this->job_store->mark_failed( $job_id );
			$this->logger->log(
				sprintf(
					'Bulk batch processor: job %s marked failed after slice [%d, +%d]. processed=%d/%d',
					$job_id,
					$start_index,
					$batch_size,
					$processed_total,
					$total_items
				),
				'warning'
			);
		} elseif ( $processed_total >= $total_items && $total_items > 0 ) {
			$this->job_store->mark_completed( $job_id );
			$this->logger->log(
				sprintf(
					'Bulk batch processor: job %s completed after processed count reached %d/%d.',
					$job_id,
					$processed_total,
					$total_items
				),
				'info'
			);
		}

		// Complete the history container for this slice.
		if ( $failed_count > 0 ) {
			$history->complete_failure(
				/* translators: %d: failures */
				sprintf( __( 'Batch slice completed with %d failures', 'ai-post-scheduler' ), $failed_count ),
				array( 'success_count' => $success_count, 'failed_count' => $failed_count )
			);
		} else {
			$history->complete_success(
				array( 'success_count' => $success_count, 'failed_count' => 0 )
			);
		}

		$this->logger->log(
			sprintf(
				'Bulk batch processor: job %s slice [%d, +%d] done — %d succeeded, %d failed.',
				$job_id,
				$start_index,
				$batch_size,
				$success_count,
				$failed_count
			),
			'info'
		);

		if ( ! empty( $correlation_id ) ) {
			AIPS_Correlation_ID::reset();
		}
	}
}
