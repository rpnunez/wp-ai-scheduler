<?php
/**
 * Post Score Controller
 *
 * AJAX controller that exposes post quality-scoring and targeted-revision
 * endpoints.  All actions are registered via AIPS_Ajax_Registry.
 *
 * Actions:
 *   aips_post_score_score         – score a specific WordPress post
 *   aips_post_score_run_revision  – run a revision pass on a post
 *   aips_post_score_get_result    – retrieve a previously stored score
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_PostScore_Controller
 *
 * Thin AJAX controller; delegates all business logic to AIPS_PostScore_Service.
 */
class AIPS_PostScore_Controller {

	/**
	 * @var AIPS_PostScore_Service
	 */
	private $service;

	/**
	 * Constructor — wires dependencies and registers AJAX hooks.
	 *
	 * @param AIPS_PostScore_Service|null $service Optional post-score service override (for testing).
	 */
	public function __construct( ?AIPS_PostScore_Service $service = null ) {
		$this->service = $service ?: new AIPS_PostScore_Service();

		add_action( 'wp_ajax_aips_post_score_score',        array( $this, 'ajax_score_post' ) );
		add_action( 'wp_ajax_aips_post_score_run_revision', array( $this, 'ajax_run_revision' ) );
		add_action( 'wp_ajax_aips_post_score_get_result',   array( $this, 'ajax_get_result' ) );
	}

	// ------------------------------------------------------------------
	// AJAX handlers
	// ------------------------------------------------------------------

	/**
	 * Handle aips_post_score_score.
	 *
	 * Scores a post by ID and saves the result to post meta.
	 *
	 * Expected POST fields:
	 *   nonce   – aips_post_score_nonce
	 *   post_id – WordPress post ID (int)
	 *
	 * @return void Outputs JSON and exits.
	 */
	public function ajax_score_post(): void {
		if ( ! check_ajax_referer( 'aips_post_score_nonce', 'nonce', false ) ) {
			AIPS_Ajax_Response::error( __( 'Security check failed.', 'ai-post-scheduler' ), 'security_check_failed', 403 );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			AIPS_Ajax_Response::permission_denied();
			return;
		}

		$post_id = absint( $_POST['post_id'] ?? 0 );

		if ( ! $post_id ) {
			AIPS_Ajax_Response::invalid_request( __( 'post_id is required.', 'ai-post-scheduler' ) );
			return;
		}

		$result = $this->service->score_post( $post_id );

		if ( is_wp_error( $result ) ) {
			AIPS_Ajax_Response::error( $result->get_error_message() );
			return;
		}

		AIPS_Ajax_Response::success( $result->to_array() );
	}

	/**
	 * Handle aips_post_score_run_revision.
	 *
	 * Runs the full scoring-and-revision loop for a post and updates the post
	 * content in the database if revisions improve the score.
	 *
	 * Expected POST fields:
	 *   nonce   – aips_post_score_nonce
	 *   post_id – WordPress post ID (int)
	 *
	 * @return void Outputs JSON and exits.
	 */
	public function ajax_run_revision(): void {
		if ( ! check_ajax_referer( 'aips_post_score_nonce', 'nonce', false ) ) {
			AIPS_Ajax_Response::error( __( 'Security check failed.', 'ai-post-scheduler' ), 'security_check_failed', 403 );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			AIPS_Ajax_Response::permission_denied();
			return;
		}

		$post_id = absint( $_POST['post_id'] ?? 0 );

		if ( ! $post_id ) {
			AIPS_Ajax_Response::invalid_request( __( 'post_id is required.', 'ai-post-scheduler' ) );
			return;
		}

		$result = $this->service->score_and_revise_post( $post_id );

		if ( is_wp_error( $result ) ) {
			AIPS_Ajax_Response::error( $result->get_error_message() );
			return;
		}

		AIPS_Ajax_Response::success( $result->to_array() );
	}

	/**
	 * Handle aips_post_score_get_result.
	 *
	 * Returns the most recently stored score result for a post.
	 *
	 * Expected POST fields:
	 *   nonce   – aips_ajax_nonce  (standard read-only nonce)
	 *   post_id – WordPress post ID (int)
	 *
	 * @return void Outputs JSON and exits.
	 */
	public function ajax_get_result(): void {
		if ( ! check_ajax_referer( 'aips_ajax_nonce', 'nonce', false ) ) {
			AIPS_Ajax_Response::error( __( 'Security check failed.', 'ai-post-scheduler' ), 'security_check_failed', 403 );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			AIPS_Ajax_Response::permission_denied();
			return;
		}

		$post_id = absint( $_POST['post_id'] ?? 0 );

		if ( ! $post_id ) {
			AIPS_Ajax_Response::invalid_request( __( 'post_id is required.', 'ai-post-scheduler' ) );
			return;
		}

		$result = $this->service->get_score_from_post( $post_id );

		if ( ! $result ) {
			AIPS_Ajax_Response::error( __( 'No score result found for this post.', 'ai-post-scheduler' ), 'not_found', 404 );
			return;
		}

		AIPS_Ajax_Response::success( $result->to_array() );
	}
}
