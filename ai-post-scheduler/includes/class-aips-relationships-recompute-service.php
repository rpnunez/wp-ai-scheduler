<?php
/**
 * Relationships Recompute Service
 *
 * Recomputes related posts from stored vector embeddings.
 * Integrates with AIPS_Job_Manager as a slice handler.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Relationships_Recompute_Service {

	const KEY = 'relationships_recompute';

	const MODE_MISSING = 'missing';

	const MODE_ALL = 'all';

	const SLICE_TIME_BUDGET = 12.0;

	/**
	 * @var AIPS_Relationship_Builder
	 */
	private $builder;

	/**
	 * @var AIPS_Relationships_Repository
	 */
	private $relationships_repo;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Relationship_Builder|null     $builder
	 * @param AIPS_Relationships_Repository|null $relationships_repo
	 */
	public function __construct(
		?AIPS_Relationship_Builder $builder = null,
		?AIPS_Relationships_Repository $relationships_repo = null
	) {
		$this->relationships_repo = $relationships_repo ?: new AIPS_Relationships_Repository();
		$this->builder            = $builder ?: new AIPS_Relationship_Builder( null, $this->relationships_repo );
	}

	public function get_key(): string {
		return self::KEY;
	}

	public function get_label(): string {
		return __( 'Related Posts Recompute', 'ai-post-scheduler' );
	}

	public function get_description(): string {
		return __( 'Builds each indexed post\'s list of related posts from the stored vectors. CPU only: no AI calls.', 'ai-post-scheduler' );
	}

	public function uses_ai(): bool {
		return false;
	}

	/**
	 * Count remaining posts to compute.
	 *
	 * @param string $mode 'missing' or 'all'.
	 * @return int
	 */
	public function count_remaining( string $mode = self::MODE_MISSING ): int {
		return $this->relationships_repo->count_source_post_ids(
			$this->builder->get_post_types(),
			'publish',
			self::MODE_MISSING === $mode
		);
	}

	/**
	 * Handle a background job slice for relationships recompute.
	 *
	 * @param AIPS_Job_Context $context
	 * @return AIPS_Job_Slice_Result
	 */
	public function handle_job_slice( AIPS_Job_Context $context ): AIPS_Job_Slice_Result {
		$record       = $context->get_record();
		$payload      = $record->get_payload();
		$cursor       = (int) $record->get_cursor();
		$mode         = isset( $payload['mode'] ) ? (string) $payload['mode'] : self::MODE_MISSING;
		$only_missing = ( self::MODE_ALL !== $mode );
		$limit        = max( 1, $context->get_batch_size() );

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,Squiz.PHP.DiscouragedFunctions.Discouraged
		}

		$ids = $this->relationships_repo->get_source_post_ids(
			$this->builder->get_post_types(),
			'publish',
			$cursor,
			$limit,
			$only_missing
		);

		if ( empty( $ids ) ) {
			return new AIPS_Job_Slice_Result( 0, 0, $cursor, true );
		}

		$saved_count = 0;
		$failed      = 0;
		$handled     = 0;
		$new_cursor  = $cursor;

		foreach ( $ids as $post_id ) {
			if ( $handled > 0 && $context->should_yield() ) {
				break;
			}

			$saved = $this->builder->compute_for_posts( array( $post_id ) );
			if ( empty( $saved ) ) {
				$failed++;
			} else {
				$saved_count += count( $saved );
			}

			$new_cursor = (int) $post_id;
			$handled++;
		}

		$is_complete = ( $handled === count( $ids ) ) && ( count( $ids ) < $limit );

		return new AIPS_Job_Slice_Result(
			$saved_count,
			$failed,
			$new_cursor,
			$is_complete
		);
	}

	/**
	 * Get estimate for relationships recompute.
	 *
	 * @param array $options
	 * @return array
	 */
	public function get_estimate( array $options = array() ): array {
		$mode    = ( isset( $options['mode'] ) && self::MODE_ALL === $options['mode'] ) ? self::MODE_ALL : self::MODE_MISSING;
		$items   = $this->count_remaining( $mode );
		$seconds = $this->builder->estimate_seconds( $items );
		$minutes = (int) max( 1, ceil( $seconds / 60 ) );

		return array(
			'items'      => $items,
			'ai_calls'   => 0,
			'days'       => 0,
			'daily_rate' => 0,
			'message'    => $items <= 0
				? __( 'Every indexed post already has its related posts.', 'ai-post-scheduler' )
				: sprintf(
					/* translators: 1: number of posts, 2: estimated minutes. */
					_n(
						'Related posts will be computed for %1$s post, taking roughly %2$s minute(s).',
						'Related posts will be computed for %1$s posts, taking roughly %2$s minute(s).',
						$items,
						'ai-post-scheduler'
					),
					number_format_i18n( $items ),
					number_format_i18n( $minutes )
				),
		);
	}
}
