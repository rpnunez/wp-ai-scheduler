<?php
/**
 * Developer Integrations Controller
 *
 * Handles AJAX management of developer integrations.
 *
 * @package AI_Post_Scheduler
 * @since 2.9.2
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Developer_Integrations_Controller
 */
class AIPS_Developer_Integrations_Controller {

	/**
	 * @var AIPS_Developer_Integration_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Developer_Integration_Repository|null $repository Optional repository override.
	 */
	public function __construct( ?AIPS_Developer_Integration_Repository $repository = null ) {
		$this->repository = $repository ?: new AIPS_Developer_Integration_Repository();

		add_action( 'wp_ajax_aips_save_developer_integration', array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_aips_delete_developer_integration', array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_aips_get_developer_integration', array( $this, 'ajax_get' ) );
		add_action( 'wp_ajax_aips_toggle_developer_integration', array( $this, 'ajax_toggle' ) );
	}

	/** Save integration. */
	public function ajax_save() {
		if ( ! $this->authorize() ) {
			return;
		}

		$name     = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$provider = sanitize_key( wp_unslash( $_POST['provider'] ?? '' ) );

		if ( empty( $name ) || empty( $provider ) ) {
			AIPS_Ajax_Response::invalid_request( __( 'Integration name and provider are required.', 'ai-post-scheduler' ) );
			return;
		}

		$allowlist = AIPS_Config::get_instance()->get_option( 'aips_developer_integration_provider_allowlist', array() );
		if ( ! empty( $allowlist ) && ! in_array( $provider, $allowlist, true ) ) {
			AIPS_Ajax_Response::invalid_request( __( 'Provider is not allowed.', 'ai-post-scheduler' ) );
			return;
		}

		$record = $this->repository->save( array(
			'id'              => sanitize_key( wp_unslash( $_POST['integration_id'] ?? '' ) ),
			'name'            => $name,
			'provider'        => $provider,
			'disclosure_text' => sanitize_textarea_field( wp_unslash( $_POST['disclosure_text'] ?? '' ) ),
			'cta_text'        => sanitize_text_field( wp_unslash( $_POST['cta_text'] ?? '' ) ),
			'endpoint_url'    => esc_url_raw( wp_unslash( $_POST['endpoint_url'] ?? '' ) ),
			'is_active'       => ! empty( $_POST['is_active'] ),
		) );

		AIPS_Ajax_Response::success( array( 'integration' => $record, 'integrations' => $this->repository->all() ), __( 'Integration saved.', 'ai-post-scheduler' ) );
	}

	/** Delete integration. */
	public function ajax_delete() {
		if ( ! $this->authorize() ) {
			return;
		}

		$id = sanitize_key( wp_unslash( $_POST['integration_id'] ?? '' ) );
		if ( empty( $id ) || ! $this->repository->delete( $id ) ) {
			AIPS_Ajax_Response::not_found( __( 'Integration', 'ai-post-scheduler' ) );
			return;
		}

		AIPS_Ajax_Response::success( array( 'integrations' => $this->repository->all() ), __( 'Integration deleted.', 'ai-post-scheduler' ) );
	}

	/** Get integration. */
	public function ajax_get() {
		if ( ! $this->authorize() ) {
			return;
		}

		$id     = sanitize_key( wp_unslash( $_POST['integration_id'] ?? '' ) );
		$record = $this->repository->find( $id );
		if ( ! $record ) {
			AIPS_Ajax_Response::not_found( __( 'Integration', 'ai-post-scheduler' ) );
			return;
		}

		AIPS_Ajax_Response::success( array( 'integration' => $record ) );
	}

	/** Toggle integration. */
	public function ajax_toggle() {
		if ( ! $this->authorize() ) {
			return;
		}

		$id      = sanitize_key( wp_unslash( $_POST['integration_id'] ?? '' ) );
		$enabled = ! empty( $_POST['is_active'] );
		$record  = $this->repository->toggle( $id, $enabled );
		if ( ! $record ) {
			AIPS_Ajax_Response::not_found( __( 'Integration', 'ai-post-scheduler' ) );
			return;
		}

		AIPS_Ajax_Response::success( array( 'integration' => $record, 'integrations' => $this->repository->all() ), __( 'Integration updated.', 'ai-post-scheduler' ) );
	}

	/**
	 * Validate nonce and capability.
	 *
	 * @return bool
	 */
	private function authorize(): bool {
		if ( ! check_ajax_referer( 'aips_ajax_nonce', 'nonce', false ) ) {
			AIPS_Ajax_Response::error( __( 'Security check failed.', 'ai-post-scheduler' ), 'security_check_failed', 403 );
			return false;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			AIPS_Ajax_Response::permission_denied();
			return false;
		}

		return true;
	}
}
