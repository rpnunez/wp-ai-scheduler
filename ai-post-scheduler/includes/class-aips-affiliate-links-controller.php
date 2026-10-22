<?php
/**
 * Affiliate Links Controller
 *
 * Admin page rendering and AJAX endpoints for the Affiliate Links feature.
 *
 * @package AI_Post_Scheduler
 * @since 3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Affiliate_Links_Controller {

	/**
	 * @var AIPS_Affiliate_Links_Repository
	 */
	private $repo;

	/**
	 * @var AIPS_Affiliate_Links_Service
	 */
	private $service;

	/**
	 * @var AIPS_Logger
	 */
	private $logger;

	public function __construct( $repo = null, $service = null, $logger = null ) {
		$this->repo    = $repo    ?: new AIPS_Affiliate_Links_Repository();
		$this->service = $service ?: new AIPS_Affiliate_Links_Service( $this->repo );
		$this->logger  = $logger  ?: new AIPS_Logger();

		add_action( 'wp_ajax_aips_affiliate_links_list',          array( $this, 'ajax_list' ) );
		add_action( 'wp_ajax_aips_affiliate_links_get',           array( $this, 'ajax_get' ) );
		add_action( 'wp_ajax_aips_affiliate_links_create',        array( $this, 'ajax_create' ) );
		add_action( 'wp_ajax_aips_affiliate_links_update',        array( $this, 'ajax_update' ) );
		add_action( 'wp_ajax_aips_affiliate_links_delete',        array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_aips_affiliate_links_toggle',        array( $this, 'ajax_toggle' ) );
		add_action( 'wp_ajax_aips_affiliate_links_inject_post',   array( $this, 'ajax_inject_post' ) );
	}

	/**
	 * Render the Affiliate Links admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		include AIPS_PLUGIN_DIR . 'templates/admin/affiliate-links.php';
	}

	// -------------------------------------------------------------------------
	// AJAX Handlers
	// -------------------------------------------------------------------------

	/**
	 * AJAX: List mappings (paginated).
	 */
	public function ajax_list() {
		$this->verify_request();

		$page     = max( 1, absint( isset( $_POST['page'] ) ? wp_unslash( $_POST['page'] ) : 1 ) );
		$per_page = max( 1, min( 100, absint( isset( $_POST['per_page'] ) ? wp_unslash( $_POST['per_page'] ) : 20 ) ) );
		$search   = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		$items = $this->repo->get_paginated( $per_page, $page, $search );
		$total = $this->repo->get_paginated_count( $search );

		AIPS_Ajax_Response::success( array(
			'items'       => $items,
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
			'page'        => $page,
		) );
	}

	/**
	 * AJAX: Get a single mapping by ID.
	 */
	public function ajax_get() {
		$this->verify_request();

		$id   = absint( isset( $_POST['id'] ) ? wp_unslash( $_POST['id'] ) : 0 );
		$item = $id ? $this->repo->get_by_id( $id ) : null;

		if ( ! $item ) {
			AIPS_Ajax_Response::error( array( 'message' => __( 'Mapping not found.', 'ai-post-scheduler' ) ) );
		}

		AIPS_Ajax_Response::success( array( 'item' => $item ) );
	}

	/**
	 * AJAX: Create a new mapping.
	 */
	public function ajax_create() {
		$this->verify_request();

		$data = $this->extract_mapping_data();

		if ( empty( $data['tag'] ) ) {
			AIPS_Ajax_Response::error( array( 'message' => __( 'Tag is required.', 'ai-post-scheduler' ) ) );
		}

		if ( empty( $data['affiliate_url'] ) ) {
			AIPS_Ajax_Response::error( array( 'message' => __( 'Affiliate URL is required.', 'ai-post-scheduler' ) ) );
		}

		$id = $this->repo->insert( $data );

		if ( ! $id ) {
			AIPS_Ajax_Response::error( array( 'message' => __( 'Failed to create mapping.', 'ai-post-scheduler' ) ) );
		}

		AIPS_Ajax_Response::success( array(
			'id'      => $id,
			'message' => __( 'Affiliate link mapping created.', 'ai-post-scheduler' ),
		) );
	}

	/**
	 * AJAX: Update an existing mapping.
	 */
	public function ajax_update() {
		$this->verify_request();

		$id = absint( isset( $_POST['id'] ) ? wp_unslash( $_POST['id'] ) : 0 );

		if ( ! $id ) {
			AIPS_Ajax_Response::error( array( 'message' => __( 'Invalid ID.', 'ai-post-scheduler' ) ) );
		}

		$data   = $this->extract_mapping_data();
		$result = $this->repo->update( $id, $data );

		if ( false === $result ) {
			AIPS_Ajax_Response::error( array( 'message' => __( 'Failed to update mapping.', 'ai-post-scheduler' ) ) );
		}

		AIPS_Ajax_Response::success( array( 'message' => __( 'Affiliate link mapping updated.', 'ai-post-scheduler' ) ) );
	}

	/**
	 * AJAX: Delete a mapping.
	 */
	public function ajax_delete() {
		$this->verify_request();

		$id = absint( isset( $_POST['id'] ) ? wp_unslash( $_POST['id'] ) : 0 );

		if ( ! $id ) {
			AIPS_Ajax_Response::error( array( 'message' => __( 'Invalid ID.', 'ai-post-scheduler' ) ) );
		}

		$result = $this->repo->delete( $id );

		if ( false === $result ) {
			AIPS_Ajax_Response::error( array( 'message' => __( 'Failed to delete mapping.', 'ai-post-scheduler' ) ) );
		}

		AIPS_Ajax_Response::success( array( 'message' => __( 'Mapping deleted.', 'ai-post-scheduler' ) ) );
	}

	/**
	 * AJAX: Toggle enabled state of a mapping.
	 */
	public function ajax_toggle() {
		$this->verify_request();

		$id      = absint( isset( $_POST['id'] ) ? wp_unslash( $_POST['id'] ) : 0 );
		$enabled = isset( $_POST['enabled'] ) ? (bool) wp_unslash( $_POST['enabled'] ) : false;

		if ( ! $id ) {
			AIPS_Ajax_Response::error( array( 'message' => __( 'Invalid ID.', 'ai-post-scheduler' ) ) );
		}

		$result = $this->repo->set_enabled( $id, $enabled );

		if ( false === $result ) {
			AIPS_Ajax_Response::error( array( 'message' => __( 'Failed to update mapping.', 'ai-post-scheduler' ) ) );
		}

		AIPS_Ajax_Response::success( array( 'message' => __( 'Mapping updated.', 'ai-post-scheduler' ) ) );
	}

	/**
	 * AJAX: Manually inject affiliate links into a specific post.
	 */
	public function ajax_inject_post() {
		$this->verify_request();

		$post_id = absint( isset( $_POST['post_id'] ) ? wp_unslash( $_POST['post_id'] ) : 0 );

		if ( ! $post_id ) {
			AIPS_Ajax_Response::error( array( 'message' => __( 'Invalid post ID.', 'ai-post-scheduler' ) ) );
		}

		$this->service->inject_for_post( $post_id );

		AIPS_Ajax_Response::success( array( 'message' => __( 'Affiliate links injected.', 'ai-post-scheduler' ) ) );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Verify nonce and capability. Dies on failure.
	 */
	private function verify_request() {
		if ( ! check_ajax_referer( 'aips_ajax_nonce', 'nonce', false ) ) {
			AIPS_Ajax_Response::error( __( 'Invalid nonce.', 'ai-post-scheduler' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			AIPS_Ajax_Response::permission_denied();
		}
	}

	/**
	 * Extract and sanitize mapping data from $_POST.
	 *
	 * @return array
	 */
	private function extract_mapping_data() {
		return array(
			'tag'                => isset( $_POST['tag'] )                ? sanitize_text_field( wp_unslash( $_POST['tag'] ) )                : '',
			'label'              => isset( $_POST['label'] )              ? sanitize_text_field( wp_unslash( $_POST['label'] ) )              : '',
			'affiliate_url'      => isset( $_POST['affiliate_url'] )      ? esc_url_raw( wp_unslash( $_POST['affiliate_url'] ) )              : '',
			'enabled'            => isset( $_POST['enabled'] )            ? (bool) wp_unslash( $_POST['enabled'] )                           : true,
			'cta_html'           => isset( $_POST['cta_html'] )           ? wp_kses_post( wp_unslash( $_POST['cta_html'] ) )                  : '',
			'cta_position'       => isset( $_POST['cta_position'] )       ? sanitize_text_field( wp_unslash( $_POST['cta_position'] ) )       : 'append',
			'cta_heading'        => isset( $_POST['cta_heading'] )        ? sanitize_text_field( wp_unslash( $_POST['cta_heading'] ) )        : '',
			'cta_match_text'     => isset( $_POST['cta_match_text'] )     ? sanitize_text_field( wp_unslash( $_POST['cta_match_text'] ) )     : '',
			'cta_max_insertions' => isset( $_POST['cta_max_insertions'] ) ? absint( wp_unslash( $_POST['cta_max_insertions'] ) )              : 1,
			'use_ai_injection'   => isset( $_POST['use_ai_injection'] )   ? (bool) wp_unslash( $_POST['use_ai_injection'] )                  : false,
		);
	}
}
