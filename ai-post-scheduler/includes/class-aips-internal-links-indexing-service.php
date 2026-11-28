<?php
/**
 * Internal Links Indexing Service
 *
 * Embeds unindexed posts in the configured indexing scope.
 * Integrates with AIPS_Job_Manager as a slice handler.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Internal_Links_Indexing_Service {

	const KEY = 'internal_links_indexing';

	/**
	 * @var AIPS_Internal_Links_Service
	 */
	private $service;

	/**
	 * @var AIPS_Embeddings_Service
	 */
	private $embeddings_service;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Internal_Links_Service|null $service
	 * @param AIPS_Embeddings_Service|null     $embeddings_service
	 * @param AIPS_Config|null                 $config
	 */
	public function __construct(
		?AIPS_Internal_Links_Service $service = null,
		?AIPS_Embeddings_Service $embeddings_service = null,
		?AIPS_Config $config = null
	) {
		$this->config             = $config ?: AIPS_Config::get_instance();
		$this->embeddings_service = $embeddings_service ?: new AIPS_Embeddings_Service();
		$this->service            = $service ?: new AIPS_Internal_Links_Service( null, null, $this->embeddings_service );
	}

	public function get_key(): string {
		return self::KEY;
	}

	public function get_label(): string {
		return __( 'Internal Links Indexing', 'ai-post-scheduler' );
	}

	public function get_description(): string {
		return __( 'Generates embeddings for unindexed posts in the indexing scope so internal link suggestions can be found.', 'ai-post-scheduler' );
	}

	public function uses_ai(): bool {
		return true;
	}

	/**
	 * Check if indexing can run.
	 *
	 * @return true|WP_Error
	 */
	public function can_run() {
		if ( ! $this->embeddings_service->is_enabled() ) {
			return new WP_Error( 'aips_bg_embeddings_disabled', __( 'The vector embeddings system is disabled in settings.', 'ai-post-scheduler' ) );
		}

		if ( ! $this->embeddings_service->is_embeddings_supported() ) {
			return new WP_Error( 'aips_bg_embeddings_unavailable', __( 'Embeddings are not available. Please configure AI Engine.', 'ai-post-scheduler' ) );
		}

		return true;
	}

	/**
	 * Count remaining unindexed posts.
	 *
	 * @return int
	 */
	public function count_remaining(): int {
		$status = $this->service->get_indexing_status();
		return isset( $status['unindexed'] ) ? (int) $status['unindexed'] : 0;
	}

	/**
	 * Handle a background job slice for post indexing.
	 *
	 * @param AIPS_Job_Context $context
	 * @return AIPS_Job_Slice_Result
	 */
	public function handle_job_slice( AIPS_Job_Context $context ): AIPS_Job_Slice_Result {
		$can = $this->can_run();
		if ( is_wp_error( $can ) ) {
			return AIPS_Job_Slice_Result::failure( array( $can->get_error_message() ) );
		}

		$record  = $context->get_record();
		$payload = $record->get_payload();
		$cursor  = (int) $record->get_cursor();
		$budget  = isset( $payload['ai_budget'] ) ? max( 0, (int) $payload['ai_budget'] ) : 0;
		$limit   = max( 1, $context->get_batch_size() );

		// Enforce per-run AI call budget before starting an indexing slice.
		if ( $budget > 0 ) {
			$budget_remaining = max( 0, $budget - $record->get_processed() );
			if ( $budget_remaining <= 0 ) {
				if ( $this->count_remaining() <= 0 ) {
					return new AIPS_Job_Slice_Result( 0, 0, $cursor, true );
				}
				AIPS_Container::get_instance()->get_job_manager()->pause( $record->get_job_id() );
				return new AIPS_Job_Slice_Result( 0, 0, $cursor, false );
			}
			$limit = min( $limit, $budget_remaining );
		}

		$result     = $this->service->process_indexing_batch( $limit, $cursor );
		$success    = isset( $result['success'] ) ? (int) $result['success'] : 0;
		$failed     = isset( $result['failed'] ) ? (int) $result['failed'] : 0;
		$new_cursor = isset( $result['last_post_id'] ) ? (int) $result['last_post_id'] : $cursor;

		$limiter       = $this->embeddings_service->get_rate_limiter();
		$cooldown      = $limiter->get_cooldown_status();
		$stats         = $limiter->get_usage_stats();
		$stalled_first = ( $new_cursor === $cursor && 0 === $success && $failed > 0 );

		if ( ! empty( $result['cooldown'] ) || ! empty( $cooldown['is_paused'] ) || ! empty( $stats['is_rate_limited'] ) || $stalled_first ) {
			$paused_until = isset( $result['paused_until'] )
				? (int) $result['paused_until']
				: ( isset( $cooldown['paused_until'] ) ? (int) $cooldown['paused_until'] : 0 );
			$now          = AIPS_DateTime::now()->timestamp();
			$delay        = $paused_until > $now ? ( $paused_until - $now ) : 60;

			// Do not count a rate-limit / quota stall on the current cursor item as a permanent post failure.
			if ( $stalled_first ) {
				$failed = 0;
			}

			return ( new AIPS_Job_Slice_Result( $success, $failed, $new_cursor, false ) )->with_retry_delay( $delay );
		}

		$is_complete = ! empty( $result['done'] ) && ( ( $success + $failed ) === 0 || 0 === $this->count_remaining() );

		// Pause the job when the configured AI call budget is reached while unindexed posts remain.
		if ( $budget > 0 && ( $record->get_processed() + $success ) >= $budget ) {
			if ( $this->count_remaining() <= 0 ) {
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
	 * Get estimate for internal links indexing.
	 *
	 * @param array $options
	 * @return array
	 */
	public function get_estimate( array $options = array() ): array {
		$items = $this->count_remaining();

		return array(
			'items'      => $items,
			'ai_calls'   => $items,
			'days'       => 0,
			'daily_rate' => 0,
			'message'    => sprintf( __( '%d posts to index.', 'ai-post-scheduler' ), $items ),
		);
	}
}
