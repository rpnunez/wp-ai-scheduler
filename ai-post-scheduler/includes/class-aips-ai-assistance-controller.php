<?php
/**
 * AI Assistance Controller
 *
 * Handles AJAX requests for AI field suggestion features.
 *
 * @package AI_Post_Scheduler
 * @since 2.4.2
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_AI_Assistance_Controller
 *
 * AJAX controller for AI field assistance actions.
 * Registered via AIPS_Ajax_Registry; instantiated only for AJAX requests.
 */
class AIPS_AI_Assistance_Controller {

	/**
	 * @var AIPS_AI_Assistance_Service Service instance.
	 */
	private $service;

	/**
	 * @var AIPS_AI_Assistance_Repository Repository instance.
	 */
	private $repository;

	/**
	 * Constructor — wires up service/repository and registers AJAX hooks.
	 */
	public function __construct() {
		$this->repository = new AIPS_AI_Assistance_Repository();
		$this->service    = new AIPS_AI_Assistance_Service(
			new AIPS_AI_Service(),
			$this->repository
		);

		add_action( 'wp_ajax_aips_ai_field_assist',          array( $this, 'ajax_field_assist' ) );
		add_action( 'wp_ajax_aips_get_field_assist_history', array( $this, 'ajax_get_field_assist_history' ) );
	}

	/**
	 * Handle the aips_ai_field_assist AJAX action.
	 *
	 * Validates the request, delegates to the service, and returns the suggestion.
	 *
	 * @return void Outputs JSON and exits.
	 */
	public function ajax_field_assist() {
		if ( ! check_ajax_referer( 'aips_ajax_nonce', 'nonce', false ) ) {
			AIPS_Ajax_Response::error( __( 'Security check failed.', 'ai-post-scheduler' ), 'security_check_failed', 403 );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			AIPS_Ajax_Response::permission_denied();
			return;
		}

		$field_config = array(
			'field_name'        => sanitize_text_field( wp_unslash( $_POST['field_name'] ?? '' ) ),
			'form_field_id'     => sanitize_text_field( wp_unslash( $_POST['field_key'] ?? '' ) ),
			'form_context'      => sanitize_text_field( wp_unslash( $_POST['form_context'] ?? '' ) ),
			'description'       => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
			'influence'         => sanitize_textarea_field( wp_unslash( $_POST['influence'] ?? '' ) ),
			'expected_response' => sanitize_text_field( wp_unslash( $_POST['expected_response'] ?? '' ) ),
			'current_value'     => sanitize_textarea_field( wp_unslash( $_POST['current_value'] ?? '' ) ),
			'author_name'       => sanitize_text_field( wp_unslash( $_POST['author_name'] ?? '' ) ),
			'field_niche'       => sanitize_text_field( wp_unslash( $_POST['field_niche'] ?? '' ) ),
		);

		$session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );
		$user_id    = get_current_user_id();

		if ( empty( $field_config['form_field_id'] ) || empty( $field_config['form_context'] ) || empty( $field_config['field_name'] ) ) {
			AIPS_Ajax_Response::invalid_request( __( 'Required field parameters are missing.', 'ai-post-scheduler' ) );
			return;
		}

		$result = $this->service->get_field_suggestion( $field_config, $session_id, $user_id );

		if ( is_wp_error( $result ) ) {
			AIPS_Ajax_Response::error( $result->get_error_message() );
			return;
		}

		AIPS_Ajax_Response::success( array(
			'response'  => $result['response'],
			'record_id' => $result['record_id'],
		) );
	}

	/**
	 * Handle the aips_get_field_assist_history AJAX action.
	 *
	 * Returns session-scoped and all-time suggestion history for a field.
	 *
	 * @return void Outputs JSON and exits.
	 */
	public function ajax_get_field_assist_history() {
		if ( ! check_ajax_referer( 'aips_ajax_nonce', 'nonce', false ) ) {
			AIPS_Ajax_Response::error( __( 'Security check failed.', 'ai-post-scheduler' ), 'security_check_failed', 403 );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			AIPS_Ajax_Response::permission_denied();
			return;
		}

		$form_context = sanitize_text_field( wp_unslash( $_POST['form_context'] ?? '' ) );
		$field_key    = sanitize_text_field( wp_unslash( $_POST['field_key'] ?? '' ) );
		$session_id   = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );

		if ( empty( $form_context ) || empty( $field_key ) || empty( $session_id ) ) {
			AIPS_Ajax_Response::invalid_request( __( 'Required parameters are missing.', 'ai-post-scheduler' ) );
			return;
		}

		$session_records = $this->repository->get_by_session_and_field( $session_id, $form_context, $field_key );
		$alltime_records = $this->repository->get_by_field( $form_context, $field_key, 15 );

		AIPS_Ajax_Response::success( array(
			'session' => $session_records,
			'alltime' => $alltime_records,
		) );
	}
}
