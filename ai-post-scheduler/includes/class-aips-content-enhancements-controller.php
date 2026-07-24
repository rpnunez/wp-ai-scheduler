<?php
/**
 * Content Enhancements Controller
 *
 * Handles AJAX management of content enhancements.
 *
 * @package AI_Post_Scheduler
 * @since 2.9.2
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Content_Enhancements_Controller
 */
class AIPS_Content_Enhancements_Controller {

	/**
	 * @var AIPS_Content_Enhancement_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Content_Enhancement_Repository|null $repository Optional repository override.
	 */
	public function __construct( ?AIPS_Content_Enhancement_Repository $repository = null ) {
		$this->repository = $repository ?: new AIPS_Content_Enhancement_Repository();

		add_action( 'wp_ajax_aips_save_content_enhancement', array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_aips_delete_content_enhancement', array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_aips_get_content_enhancement', array( $this, 'ajax_get' ) );
		add_action( 'wp_ajax_aips_toggle_content_enhancement', array( $this, 'ajax_toggle' ) );
	}

	/** Save enhancement. */
	public function ajax_save() {
		if ( ! $this->authorize() ) {
			return;
		}

		$name     = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$provider = sanitize_key( wp_unslash( $_POST['provider'] ?? 'custom' ) );
		$type     = sanitize_key( wp_unslash( $_POST['type'] ?? 'embed' ) );

		if ( empty( $name ) ) {
			AIPS_Ajax_Response::invalid_request( __( 'Content enhancement name is required.', 'ai-post-scheduler' ) );
			return;
		}

		$allowlist = AIPS_Config::get_instance()->get_option( 'aips_content_enhancement_provider_allowlist', array() );
		$allowlist = is_array( $allowlist ) ? $allowlist : array();
		if ( ! empty( $allowlist ) && ! in_array( $provider, $allowlist, true ) ) {
			AIPS_Ajax_Response::invalid_request( __( 'Provider is not allowed.', 'ai-post-scheduler' ) );
			return;
		}

		$slug = sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) );
		if ( empty( $slug ) ) {
			$slug = sanitize_title( $name );
		}

		$id       = sanitize_key( wp_unslash( $_POST['enhancement_id'] ?? '' ) );
		$existing = $this->repository->find_by_slug( $slug );
		if ( $existing && ( empty( $id ) || $existing['id'] !== $id ) ) {
			AIPS_Ajax_Response::invalid_request( __( 'An enhancement with this slug already exists.', 'ai-post-scheduler' ) );
			return;
		}

		$record = $this->repository->save( array(
			'id'              => $id,
			'name'            => $name,
			'provider'        => $provider,
			'type'            => $type,
			'slug'            => $slug,
			'use_case'        => sanitize_textarea_field( wp_unslash( $_POST['use_case'] ?? '' ) ),
			'disclosure_text' => sanitize_textarea_field( wp_unslash( $_POST['disclosure_text'] ?? '' ) ),
			'cta_text'        => sanitize_text_field( wp_unslash( $_POST['cta_text'] ?? $_POST['cta_label'] ?? '' ) ),
			'cta_label'       => sanitize_text_field( wp_unslash( $_POST['cta_label'] ?? $_POST['cta_text'] ?? '' ) ),
			'endpoint_url'    => esc_url_raw( wp_unslash( $_POST['endpoint_url'] ?? '' ) ),
			'referral_url'    => esc_url_raw( wp_unslash( $_POST['referral_url'] ?? '' ) ),
			'utm_campaign'    => sanitize_text_field( wp_unslash( $_POST['utm_campaign'] ?? '' ) ),
			'utm_source'      => sanitize_text_field( wp_unslash( $_POST['utm_source'] ?? '' ) ),
			'utm_medium'      => sanitize_text_field( wp_unslash( $_POST['utm_medium'] ?? '' ) ),
			'rel_attributes'  => AIPS_Referral_Link_Builder::sanitize_rel( sanitize_text_field( wp_unslash( $_POST['rel_attributes'] ?? '' ) ) ),
			'is_active'       => ! empty( $_POST['is_active'] ),
		) );

		AIPS_Ajax_Response::success( array( 'enhancement' => $record, 'enhancements' => $this->repository->all() ), __( 'Content enhancement saved.', 'ai-post-scheduler' ) );
	}

	/** Delete enhancement. */
	public function ajax_delete() {
		if ( ! $this->authorize() ) {
			return;
		}

		$id = sanitize_key( wp_unslash( $_POST['enhancement_id'] ?? '' ) );
		if ( empty( $id ) || ! $this->repository->delete( $id ) ) {
			AIPS_Ajax_Response::not_found( __( 'Content Enhancement', 'ai-post-scheduler' ) );
			return;
		}

		AIPS_Ajax_Response::success( array( 'enhancements' => $this->repository->all() ), __( 'Content enhancement deleted.', 'ai-post-scheduler' ) );
	}

	/** Get enhancement. */
	public function ajax_get() {
		if ( ! $this->authorize() ) {
			return;
		}

		$id     = sanitize_key( wp_unslash( $_POST['enhancement_id'] ?? '' ) );
		$record = $this->repository->find( $id );
		if ( ! $record ) {
			AIPS_Ajax_Response::not_found( __( 'Content Enhancement', 'ai-post-scheduler' ) );
			return;
		}

		AIPS_Ajax_Response::success( array( 'enhancement' => $record ) );
	}

	/** Toggle enhancement. */
	public function ajax_toggle() {
		if ( ! $this->authorize() ) {
			return;
		}

		$id      = sanitize_key( wp_unslash( $_POST['enhancement_id'] ?? '' ) );
		$enabled = ! empty( $_POST['is_active'] );
		$record  = $this->repository->toggle( $id, $enabled );
		if ( ! $record ) {
			AIPS_Ajax_Response::not_found( __( 'Content Enhancement', 'ai-post-scheduler' ) );
			return;
		}

		AIPS_Ajax_Response::success( array( 'enhancement' => $record, 'enhancements' => $this->repository->all() ), __( 'Content enhancement updated.', 'ai-post-scheduler' ) );
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
