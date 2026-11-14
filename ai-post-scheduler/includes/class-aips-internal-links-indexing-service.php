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

		$record = $context->get_record();
		$cursor = (int) $record->get_cursor();
		$limit  = max( 1, $context->get_batch_size() );

		$result     = $this->service->process_indexing_batch( $limit, $cursor );
		$success    = isset( $result['success'] ) ? (int) $result['success'] : 0;
		$failed     = isset( $result['failed'] ) ? (int) $result['failed'] : 0;
		$new_cursor = isset( $result['last_post_id'] ) ? (int) $result['last_post_id'] : $cursor;

		$is_complete = ( $new_cursor === $cursor && 0 === $success ) || ( isset( $result['remaining'] ) && 0 === (int) $result['remaining'] );

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
