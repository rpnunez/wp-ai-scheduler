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

class AIPS_Affiliate_Links_Controller extends AIPS_Ajax_Controller_Base {

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

	/**
	 * @var array<string, string>
	 */
	protected array $actions = array(
		'aips_affiliate_links_list'        => 'ajax_list',
		'aips_affiliate_links_get'         => 'ajax_get',
		'aips_affiliate_links_create'      => 'ajax_create',
		'aips_affiliate_links_update'      => 'ajax_update',
		'aips_affiliate_links_delete'      => 'ajax_delete',
		'aips_affiliate_links_toggle'      => 'ajax_toggle',
		'aips_affiliate_links_inject_post' => 'ajax_inject_post',
	);

	public function __construct( $repo = null, $service = null, $logger = null ) {
		$container = AIPS_Container::get_instance();
		$this->repo    = $repo    ?: $container->makeIfExists( AIPS_Affiliate_Links_Repository::class, function() {
			return new AIPS_Affiliate_Links_Repository();
		} );
		$this->service = $service ?: $container->makeIfExists( AIPS_Affiliate_Links_Service::class, function() {
			return new AIPS_Affiliate_Links_Service( $this->repo );
		} );
		$this->logger  = $logger  ?: $container->makeIfExists( AIPS_Logger::class, function() {
			return new AIPS_Logger();
		} );

		parent::__construct();
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
		$enabled = isset( $_POST['enabled'] ) ? filter_var( wp_unslash( $_POST['enabled'] ), FILTER_VALIDATE_BOOLEAN ) : false;

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
	 * Extract and sanitize mapping data from $_POST.
	 *
	 * @return array
	 */
	private function extract_mapping_data() {
		return array(
			'tag'                => isset( $_POST['tag'] )                ? sanitize_text_field( wp_unslash( $_POST['tag'] ) )                : '',
			'label'              => isset( $_POST['label'] )              ? sanitize_text_field( wp_unslash( $_POST['label'] ) )              : '',
			'affiliate_url'      => isset( $_POST['affiliate_url'] )      ? esc_url_raw( wp_unslash( $_POST['affiliate_url'] ) )              : '',
			'enabled'            => isset( $_POST['enabled'] )            ? filter_var( wp_unslash( $_POST['enabled'] ), FILTER_VALIDATE_BOOLEAN ) : true,
			'cta_html'           => isset( $_POST['cta_html'] )           ? wp_kses_post( wp_unslash( $_POST['cta_html'] ) )                  : '',
			'cta_position'       => isset( $_POST['cta_position'] )       ? sanitize_text_field( wp_unslash( $_POST['cta_position'] ) )       : 'append',
			'cta_heading'        => isset( $_POST['cta_heading'] )        ? sanitize_text_field( wp_unslash( $_POST['cta_heading'] ) )        : '',
			'cta_match_text'     => isset( $_POST['cta_match_text'] )     ? sanitize_text_field( wp_unslash( $_POST['cta_match_text'] ) )     : '',
			'cta_max_insertions' => isset( $_POST['cta_max_insertions'] ) ? absint( wp_unslash( $_POST['cta_max_insertions'] ) )              : 1,
			'use_ai_injection'   => isset( $_POST['use_ai_injection'] )   ? filter_var( wp_unslash( $_POST['use_ai_injection'] ), FILTER_VALIDATE_BOOLEAN ) : false,
		);
	}
}
