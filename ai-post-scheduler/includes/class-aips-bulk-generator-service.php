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
 * Controllers inspect the fields and build their own JSON response.
 */
class AIPS_Bulk_Generation_Result {

	/**
	 * Number of items that were generated successfully.
	 *
	 * @var int
	 */
	public $success_count;

	/**
	 * Number of items that failed (validation failures count here too).
	 *
	 * For a hard-limit overflow this equals the total number of items that
	 * were submitted; no generation was attempted in that case.
	 *
	 * @var int
	 */
	public $failed_count;

	/**
	 * Human-readable error strings, one entry per failed item.
	 *
	 * @var string[]
	 */
	public $errors;

	/**
	 * IDs of every post that was created/regenerated in this run.
	 *
	 * @var int[]
	 */
	public $post_ids;

	/**
	 * True when the batch was larger than the configured limit.
	 *
	 * For hard-mode this means the entire request was rejected.
	 * For soft-mode this means items were silently truncated.
	 *
	 * @var bool
	 */
	public $was_limited;

	/**
	 * The effective bulk limit that was applied.
	 *
	 * Useful so the controller can embed the limit value in its error message
	 * without re-calling apply_filters().
	 *
	 * @var int
	 */
	public $max_bulk;

	/**
	 * Constructor.
	 *
	 * @param int      $success_count Number of successful generations.
	 * @param int      $failed_count  Number of failures.
	 * @param string[] $errors        Per-item error strings.
	 * @param int[]    $post_ids      IDs of generated posts.
	 * @param bool     $was_limited   Whether the batch limit was hit.
	 * @param int      $max_bulk      Effective limit that was applied.
	 */
	public function __construct(
		int $success_count,
		int $failed_count,
		array $errors,
		array $post_ids,
		bool $was_limited,
		int $max_bulk
	) {
		$this->success_count = $success_count;
		$this->failed_count  = $failed_count;
		$this->errors        = $errors;
		$this->post_ids      = $post_ids;
		$this->was_limited   = $was_limited;
		$this->max_bulk      = $max_bulk;
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
 *         'history_type' => 'bulk_generate_now',
 *         'trigger_name' => 'ajax_bulk_generate_now',
 *     )
 * );
 * ```
 *
 * Supported $options keys
 * -----------------------
 *   limit_filter   string   Filter name for the max-items limit.
 *                           Default: 'aips_bulk_run_now_limit'.
 *   limit_default  int      Fallback limit when filter returns 0.
 *                           Default: 5.
 *   limit_mode     string   'hard' (reject request) or 'soft' (truncate items).
 *                           Default: 'hard'.
 *   history_type   string   History container type string.
 *   history_meta   array    Extra metadata merged into the history container.
 *   trigger_name   string   Identifies the calling handler in history.
 *   user_action    string   Action label for record_user_action().
 *   user_message   string   Message for record_user_action().
 *   error_formatter callable($item, $error_message): string
 *                           Formats per-item failure strings.
 *                           Default: "{$item}: {$error_message}".
 */
class AIPS_Bulk_Generator_Service {

	/**
	 * @var AIPS_History_Service
	 */
	private $history_service;

	/**
	 * Constructor.
	 *
	 * @param AIPS_History_Service|null $history_service Injectable for testing.
	 */
	public function __construct( $history_service = null ) {
		$this->history_service = $history_service ?: new AIPS_History_Service();
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
		$limit_default = isset( $options['limit_default'] ) ? (int)    $options['limit_default'] : 5;
		$limit_mode    = isset( $options['limit_mode'] )    ? (string) $options['limit_mode']    : 'hard';

		$max_bulk = absint( apply_filters( $limit_filter, $limit_default ) );
		if ( $max_bulk < 1 ) {
			$max_bulk = $limit_default;
		}

		$was_limited = false;

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
				$error_msg = $error_formatter
					? call_user_func( $error_formatter, $item, $result->get_error_message() )
					: sprintf( '%s: %s', strval( $item ), $result->get_error_message() );
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
}
