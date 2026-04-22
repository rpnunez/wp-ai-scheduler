<?php
/**
 * Bulk Generator Service
 *
 * Centralises the "generate a batch of posts" pattern that was previously
 * copy-pasted across multiple AJAX handlers:
 *   - AIPS_Author_Topics_Controller::ajax_bulk_generate_topics
 *   - AIPS_Author_Topics_Controller::ajax_bulk_generate_from_queue
 *   - AIPS_Planner::ajax_bulk_generate_now
 *   - AIPS_Post_Review::ajax_bulk_regenerate_posts
 *   - AIPS_Research_Controller::ajax_generate_trending_topics_bulk
 *
 * Each caller passes a $generate_fn callable — the only thing that truly
 * differs per call site.  Everything else (limit enforcement, generation
 * loop, per-item error accumulation, history container lifecycle) lives
 * here once.
 *
 * @package AI_Post_Scheduler
 * @since   1.8.0
 */

if (!defined('ABSPATH')) {
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
// Service
// ---------------------------------------------------------------------------

/**
 * Class AIPS_Bulk_Generator_Service
 *
 * Shared harness for all synchronous bulk post-generation AJAX handlers.
 *
 * Usage
 * -----
 * ```php
 * $result = $this->bulk_generator_service->run(
 *     $items,
 *     function( $item ) { return $generator->generate_post( $template, null, $item ); },
 *     array(
 *         'history_type'   => 'bulk_generate_now',
 *         'trigger_name'   => 'ajax_bulk_generate_now',
 *         'queue_job_type' => 'planner_post',   // enables async queuing
 *     )
 * );
 * ```
 *
 * Supported $options keys
 * -----------------------
 *   limit_filter   string   Filter name for the max-items limit.
 *                           Default: 'aips_bulk_run_now_limit'.
 *   limit_default  int      Fallback limit when filter returns 0.
 *                           Default: AIPS_Bulk_Generator_Service::DEFAULT_LIMIT (5).
 *   limit_mode     string   'hard' (reject request) or 'soft' (truncate items).
 *                           Default: 'hard'.
 *                           Note: when queue_job_type is provided and the batch is
 *                           large enough, the request is queued async regardless of
 *                           limit_mode, so no items are rejected or truncated.
 *   queue_job_type string   When set, large batches (≥ aips_large_batch_threshold)
 *                           are persisted to AIPS_Bulk_Batch_Job_Store and dispatched
 *                           as a series of cron events instead of running synchronously.
 *                           The strategy for this job_type must be registered in
 *                           AIPS_Bulk_Batch_Processor before the cron fires.
 *   history_type   string   History container type string.
 *   history_meta   array    Extra metadata merged into the history container.
 *   trigger_name   string   Identifies the calling handler in history.
 *   user_action    string   Action label for record_user_action().
 *   user_message   string   Message for record_user_action().
 *   error_formatter callable($item, $error_message): string
 *                           Formats per-item failure strings.
 *                           Default: JSON-encodes non-scalar items to avoid
 *                           "Array to string conversion" warnings.
 *                           **Required** when $items contains non-scalar values
 *                           (arrays or objects); omitting it triggers
 *                           _doing_it_wrong() and falls back to wp_json_encode().
 */
class AIPS_Bulk_Generator_Service {

	/**
	 * Default item limit when no limit_default option and no filter override are given.
	 *
	 * Exposed as a class constant so callers can reference it without magic numbers.
	 * Use the 'aips_bulk_run_now_limit' filter to override at runtime.
	 *
	 * @var int
	 */
	const DEFAULT_LIMIT = 5;

	/**
	 * @var AIPS_History_Service_Interface
	 */
	private $history_service;

	/**
	 * @var AIPS_Bulk_Batch_Job_Store|null Lazy-loaded job store.
	 */
	private $job_store;

	/**
	 * @var AIPS_Batch_Queue_Service|null Lazy-loaded batch queue service.
	 */
	private $batch_queue_service;

	/**
	 * Constructor.
	 *
	 * @param AIPS_History_Service_Interface|null $history_service Injectable for testing.
	 * @param AIPS_Bulk_Batch_Job_Store|null      $job_store       Injectable for testing.
	 * @param AIPS_Batch_Queue_Service|null       $batch_queue_service Injectable for testing.
	 */
	public function __construct(
		?AIPS_History_Service_Interface $history_service     = null,
		?AIPS_Bulk_Batch_Job_Store      $job_store           = null,
		?AIPS_Batch_Queue_Service       $batch_queue_service = null
	) {
		$container = AIPS_Container::get_instance();
		$this->history_service     = $history_service     ?: ($container->has(AIPS_History_Service_Interface::class) ? $container->make(AIPS_History_Service_Interface::class) : new AIPS_History_Service());
		$this->job_store           = $job_store;
		$this->batch_queue_service = $batch_queue_service;
	}

	/**
	 * Lazy-load the job store.
	 *
	 * @return AIPS_Bulk_Batch_Job_Store
	 */
	private function get_job_store(): AIPS_Bulk_Batch_Job_Store {
		if ( $this->job_store === null ) {
			$this->job_store = new AIPS_Bulk_Batch_Job_Store();
		}
		return $this->job_store;
	}

	/**
	 * Lazy-load the batch queue service.
	 *
	 * @return AIPS_Batch_Queue_Service
	 */
	private function get_batch_queue_service(): AIPS_Batch_Queue_Service {
		if ( $this->batch_queue_service === null ) {
			$this->batch_queue_service = new AIPS_Batch_Queue_Service();
		}
		return $this->batch_queue_service;
	}

	/**
	 * Run a bulk generation batch.
	 *
	 * @param array    $items       Sanitized items to process (topic IDs, topic strings, or
	 *                              associative arrays — whatever $generate_fn expects).
	 * @param callable $generate_fn fn( $item ): int|WP_Error
	 *                              Must return a new post ID on success or a WP_Error on failure.
	 * @param array    $options     See class-level docblock.
	 * @return AIPS_Bulk_Generation_Result
	 */
	public function run( array $items, callable $generate_fn, array $options = array() ): AIPS_Bulk_Generation_Result {

		// ------------------------------------------------------------------
		// 1. Resolve and apply the batch limit
		// ------------------------------------------------------------------
		$limit_filter  = isset( $options['limit_filter'] )  ? (string) $options['limit_filter']  : 'aips_bulk_run_now_limit';
		$limit_default = isset( $options['limit_default'] ) ? (int)    $options['limit_default'] : self::DEFAULT_LIMIT;
		$limit_mode    = isset( $options['limit_mode'] )    ? (string) $options['limit_mode']    : 'hard';
		$queue_job_type = isset( $options['queue_job_type'] ) ? (string) $options['queue_job_type'] : '';

		$max_bulk = absint( apply_filters( $limit_filter, $limit_default ) );
		if ( $max_bulk < 1 ) {
			$max_bulk = $limit_default;
		}

		$was_limited = false;

		// ------------------------------------------------------------------
		// 1a. Large-batch async queuing (when queue_job_type is provided)
		//
		// When the caller has opted in to async queuing (by providing a
		// queue_job_type) and the item count meets the large-batch threshold,
		// persist the job and dispatch cron events instead of running
		// synchronously.  This bypasses the hard/soft limit entirely —
		// the whole point is to generate *all* requested items eventually.
		// ------------------------------------------------------------------
		if ( $queue_job_type !== '' ) {
			$batch_service = $this->get_batch_queue_service();
			if ( $batch_service->needs_batch_queue( count( $items ) ) ) {
				return $this->dispatch_async( $items, $options, $queue_job_type, $max_bulk, $batch_service );
			}
		}

		if ( count( $items ) > $max_bulk ) {
			if ( $limit_mode === 'soft' ) {
				$was_limited = true;
				$items       = array_slice( $items, 0, $max_bulk );
			} else {
				// Hard mode: reject immediately without creating a history container.
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

		// ------------------------------------------------------------------
		// 2. Create the history container for this bulk run
		// ------------------------------------------------------------------
		$history_type   = isset( $options['history_type'] )  ? (string) $options['history_type']  : 'bulk_generation';
		$history_meta   = isset( $options['history_meta'] )  ? (array)  $options['history_meta']  : array();
		$trigger_name   = isset( $options['trigger_name'] )  ? (string) $options['trigger_name']  : 'bulk_generator_service';
		$user_action    = isset( $options['user_action'] )   ? (string) $options['user_action']   : 'bulk_generate';
		$user_message   = isset( $options['user_message'] )  ? (string) $options['user_message']
			/* translators: %d: number of items */
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

		$history = $this->history_service->create( $history_type, $meta );
		$history->record_user_action( $user_action, $user_message, array( 'item_count' => count( $items ) ) );

		if ( $was_limited ) {
			$history->record(
				'activity',
				/* translators: %d: item count after truncation */
				sprintf( __( 'Batch was limited to %d items to avoid timeouts.', 'ai-post-scheduler' ), count( $items ) ),
				null,
				null,
				array( 'truncated_to' => count( $items ) )
			);
		}

		// ------------------------------------------------------------------
		// 3. Execute the generation loop
		// ------------------------------------------------------------------
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
					// Non-scalar item without a formatter: warn the developer and fall back
					// to a JSON representation to avoid "Array to string conversion" notices.
					_doing_it_wrong(
						__METHOD__,
						'AIPS_Bulk_Generator_Service::run() received a non-scalar item without an error_formatter option. Provide an error_formatter to generate meaningful error messages.',
						'1.8.0'
					);
					$error_msg = sprintf( '%s: %s', wp_json_encode( $item ), $result->get_error_message() );
				} else {
					$error_msg = sprintf( '%s: %s', (string) $item, $result->get_error_message() );
				}
				$errors[]  = $error_msg;
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
					/* translators: %s: post ID */
					sprintf( __( 'Post %s generated successfully', 'ai-post-scheduler' ), is_array( $post_id_or_ids ) ? implode( ',', $post_id_or_ids ) : $post_id_or_ids ),
					null,
					null,
					array( 'item' => $item, 'post_id' => $post_id_or_ids )
				);
			}
		}

		// ------------------------------------------------------------------
		// 4. Complete the history container
		// ------------------------------------------------------------------
		if ( $failed_count > 0 ) {
			$history->complete_failure(
				/* translators: %d: number of failures */
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

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	/**
	 * Persist a large-batch job and dispatch async cron events.
	 *
	 * Called from run() when queue_job_type is set and the item count meets
	 * the large-batch threshold.
	 *
	 * @param array                  $items          Items to queue.
	 * @param array                  $options        Raw options from run() caller.
	 * @param string                 $queue_job_type Strategy key.
	 * @param int                    $max_bulk       Effective per-request limit.
	 * @param AIPS_Batch_Queue_Service $batch_service Batch queue service instance.
	 * @return AIPS_Bulk_Generation_Result Result with was_queued = true.
	 */
	private function dispatch_async(
		array $items,
		array $options,
		string $queue_job_type,
		int $max_bulk,
		AIPS_Batch_Queue_Service $batch_service
	): AIPS_Bulk_Generation_Result {

		$job_store = $this->get_job_store();

		// Persist serialisable options (strip closures automatically).
		$job_id = $job_store->create( $queue_job_type, $items, $options );

		if ( is_wp_error( $job_id ) ) {
			// Job store failure: cannot dispatch async — return an all-failed result so
			// the caller knows the items were not processed and can surface the error.
			// A synchronous fallback is not possible here because dispatch_async() does
			// not hold a reference to the per-item $generate_fn closure passed to run().
			return new AIPS_Bulk_Generation_Result(
				0,
				count( $items ),
				array( $job_id->get_error_message() ),
				array(),
				false,
				$max_bulk
			);
		}

		$correlation_id = (string) AIPS_Correlation_ID::get();
		$now            = time();

		$dispatch_summary = $batch_service->dispatch_generic(
			AIPS_Bulk_Batch_Processor::HOOK,
			count( $items ),
			$now,
			array( $job_id ),
			$correlation_id
		);

		// Log the dispatch to history.
		$history_type = isset( $options['history_type'] ) ? (string) $options['history_type'] : 'bulk_generation';
		$history_meta = isset( $options['history_meta'] ) ? (array)  $options['history_meta'] : array();
		$trigger_name = isset( $options['trigger_name'] ) ? (string) $options['trigger_name'] : 'bulk_generator_service';
		$user_action  = isset( $options['user_action'] )  ? (string) $options['user_action']  : 'bulk_generate';
		$user_message = isset( $options['user_message'] ) ? (string) $options['user_message']
			/* translators: %d: number of items */
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

		$history = $this->history_service->create( $history_type, $meta );
		$history->record_user_action( $user_action, $user_message, array( 'item_count' => count( $items ) ) );
		$history->record(
			'activity',
			sprintf(
				/* translators: 1: number of batch jobs, 2: total items, 3: spread window in seconds */
				__( 'Large batch detected (%2$d items): queued %1$d batch jobs spread across %3$d seconds.', 'ai-post-scheduler' ),
				$dispatch_summary['num_batches'],
				count( $items ),
				$dispatch_summary['window_seconds']
			),
			null,
			null,
			array(
				'job_id'          => $job_id,
				'job_type'        => $queue_job_type,
				'num_batches'     => $dispatch_summary['num_batches'],
				'posts_per_batch' => $dispatch_summary['posts_per_batch'],
				'window_seconds'  => $dispatch_summary['window_seconds'],
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
}
