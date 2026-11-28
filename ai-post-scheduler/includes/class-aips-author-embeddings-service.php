<?php
/**
 * Author Topic Embeddings Service
 *
 * Generates embeddings for author topics that do not have one yet.
 * Used for topic duplicate detection, auto-approval, and related-topic suggestions.
 * Integrates with AIPS_Job_Manager as a slice handler.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Author_Embeddings_Service {

	const KEY = 'author_topic_embeddings';

	const QUOTA_RESERVE_RATIO = 0.20;

	/**
	 * @var AIPS_Embeddings_Service
	 */
	private $embeddings_service;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * @var AIPS_Embeddings_Repository
	 */
	private $embeddings_repo;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Embeddings_Service|null    $embeddings_service
	 * @param AIPS_Config|null                $config
	 * @param AIPS_Embeddings_Repository|null $embeddings_repo
	 */
	public function __construct(
		?AIPS_Embeddings_Service $embeddings_service = null,
		?AIPS_Config $config = null,
		?AIPS_Embeddings_Repository $embeddings_repo = null
	) {
		$this->config             = $config ?: AIPS_Config::get_instance();
		$this->embeddings_service = $embeddings_service ?: new AIPS_Embeddings_Service();
		$this->embeddings_repo    = $embeddings_repo ?: $this->embeddings_service->get_embeddings_repository();
	}

	public function get_key(): string {
		return self::KEY;
	}

	public function get_label(): string {
		return __( 'Author Topic Embeddings', 'ai-post-scheduler' );
	}

	public function get_description(): string {
		return __( 'Generates embeddings for author topics that do not have one yet. Uses AI embedding calls and honors the embeddings rate limits.', 'ai-post-scheduler' );
	}

	public function uses_ai(): bool {
		return true;
	}

	/**
	 * Check if embeddings can run.
	 *
	 * @return true|WP_Error
	 */
	public function can_run() {
		if ( ! $this->embeddings_service->is_enabled() ) {
			return new WP_Error( 'aips_bg_embeddings_disabled', __( 'The vector embeddings system is disabled in settings.', 'ai-post-scheduler' ) );
		}
		return true;
	}

	/**
	 * Count unindexed topics for an author (or all authors).
	 *
	 * @param int $author_id Author ID (0 = all authors).
	 * @return int
	 */
	public function count_remaining( int $author_id = 0 ): int {
		return $this->embeddings_repo->get_unindexed_topic_count( $author_id );
	}

	/**
	 * Handle a background job slice for author topic embeddings.
	 *
	 * @param AIPS_Job_Context $context
	 * @return AIPS_Job_Slice_Result
	 */
	public function handle_job_slice( AIPS_Job_Context $context ): AIPS_Job_Slice_Result {
		$can = $this->can_run();
		if ( is_wp_error( $can ) ) {
			return AIPS_Job_Slice_Result::failure( array( $can->get_error_message() ) );
		}

		$record    = $context->get_record();
		$payload   = $record->get_payload();
		$cursor    = (int) $record->get_cursor();
		$author_id = isset( $payload['author_id'] ) ? absint( $payload['author_id'] ) : 0;
		$budget    = isset( $payload['ai_budget'] ) ? max( 0, (int) $payload['ai_budget'] ) : 0;
		$limit     = max( 1, $context->get_batch_size() );

		// Enforce per-run AI call budget before making any embedding calls.
		if ( $budget > 0 ) {
			$budget_remaining = max( 0, $budget - $record->get_processed() );
			if ( $budget_remaining <= 0 ) {
				if ( $this->count_remaining( $author_id ) <= 0 ) {
					return new AIPS_Job_Slice_Result( 0, 0, $cursor, true );
				}
				AIPS_Container::get_instance()->get_job_manager()->pause( $record->get_job_id() );
				return new AIPS_Job_Slice_Result( 0, 0, $cursor, false );
			}
			$limit = min( $limit, $budget_remaining );
		}

		$limiter  = $this->embeddings_service->get_rate_limiter();
		$cooldown = $limiter->get_cooldown_status();

		if ( ! empty( $cooldown['is_paused'] ) ) {
			$delay = max( 5, (int) $cooldown['remaining_seconds'] );
			return ( new AIPS_Job_Slice_Result( 0, 0, $cursor, false ) )->with_retry_delay( $delay );
		}

		$ids = $this->embeddings_repo->get_unindexed_topic_ids( $limit, $cursor, $author_id );

		if ( empty( $ids ) ) {
			return new AIPS_Job_Slice_Result( 0, 0, $cursor, true );
		}

		$success    = 0;
		$failed     = 0;
		$new_cursor = $cursor;
		$stalled    = false;
		$handled    = 0;

		foreach ( $ids as $topic_id ) {
			if ( $context->should_yield() && ( $success + $failed ) > 0 ) {
				break;
			}

			if ( $limiter->get_remaining_allowance( self::QUOTA_RESERVE_RATIO ) <= 0 ) {
				$next  = $limiter->get_next_allowance_timestamp( self::QUOTA_RESERVE_RATIO );
				$now   = AIPS_DateTime::now()->timestamp();
				$delay = $next > $now ? ( $next - $now ) : 60;
				return ( new AIPS_Job_Slice_Result( $success, $failed, $new_cursor, false ) )->with_retry_delay( $delay );
			}

			$result = $this->embeddings_service->compute_topic_embedding( (int) $topic_id );

			if ( is_wp_error( $result ) ) {
				if ( 'embeddings_disabled' === $result->get_error_code() ) {
					return AIPS_Job_Slice_Result::failure( array( $result->get_error_message() ) );
				}

				if (
					$limiter->is_rate_limit_or_exhaustion_error( $result )
					|| in_array( $result->get_error_code(), AIPS_Embeddings_Rate_Limiter::NON_FAULT_CODES, true )
				) {
					$stalled = true;
					break;
				}

				$failed++;
			} else {
				$success++;
			}

			$new_cursor = max( $new_cursor, (int) $topic_id );
			$handled++;
		}

		// Only complete when every fetched id was processed (no early yield/break) AND the
		// page was short — otherwise a yielded final slice would abandon unembedded topics.
		$is_complete = ! $stalled && ( $handled === count( $ids ) ) && ( count( $ids ) < $limit );

		// If the run reached its AI call budget, complete when no topics remain; otherwise pause.
		if ( ! $stalled && $budget > 0 && ( $record->get_processed() + $success ) >= $budget ) {
			if ( $this->count_remaining( $author_id ) <= 0 ) {
				$is_complete = true;
			} else {
				AIPS_Container::get_instance()->get_job_manager()->pause( $record->get_job_id() );
				return new AIPS_Job_Slice_Result( $success, $failed, $new_cursor, false );
			}
		}

		return new AIPS_Job_Slice_Result(
			$success,
			$failed,
			$new_cursor,
			$is_complete
		);
	}

	/**
	 * Get estimate for topic embeddings.
	 *
	 * @param array $options
	 * @return array
	 */
	public function get_estimate( array $options = array() ): array {
		$author_id = isset( $options['author_id'] ) ? absint( $options['author_id'] ) : 0;
		$items     = $this->count_remaining( $author_id );

		$limiter    = $this->embeddings_service->get_rate_limiter();
		$daily_rate = $limiter->get_daily_limit();
		$days       = ( $daily_rate > 0 ) ? (int) ceil( $items / $daily_rate ) : 0;

		if ( $items <= 0 ) {
			$message = __( 'Every topic already has an embedding.', 'ai-post-scheduler' );
		} else {
			$message = sprintf(
				/* translators: %s: number of topics. */
				_n(
					'%s topic will be embedded using one embedding call.',
					'%s topics will be embedded using one embedding call each.',
					$items,
					'ai-post-scheduler'
				),
				number_format_i18n( $items )
			);
		}

		return array(
			'items'      => $items,
			'ai_calls'   => $items,
			'days'       => $days,
			'daily_rate' => $daily_rate,
			'message'    => $message,
		);
	}
}
