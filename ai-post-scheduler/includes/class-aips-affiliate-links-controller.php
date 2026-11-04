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

	/**
	 * @var AIPS_Affiliate_Link_Suggestions_Repository
	 */
	private $suggestions_repo;

	public function __construct( $repo = null, $service = null, $logger = null, $suggestions_repo = null ) {
		$this->repo             = $repo             ?: new AIPS_Affiliate_Links_Repository();
		$this->service          = $service          ?: new AIPS_Affiliate_Links_Service( $this->repo );
		$this->logger           = $logger           ?: new AIPS_Logger();
		$this->suggestions_repo = $suggestions_repo ?: new AIPS_Affiliate_Link_Suggestions_Repository();

		add_action( 'wp_ajax_aips_affiliate_links_list',                 array( $this, 'ajax_list' ) );
		add_action( 'wp_ajax_aips_affiliate_links_get',                  array( $this, 'ajax_get' ) );
		add_action( 'wp_ajax_aips_affiliate_links_create',               array( $this, 'ajax_create' ) );
		add_action( 'wp_ajax_aips_affiliate_links_update',               array( $this, 'ajax_update' ) );
		add_action( 'wp_ajax_aips_affiliate_links_delete',               array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_aips_affiliate_links_toggle',               array( $this, 'ajax_toggle' ) );
		add_action( 'wp_ajax_aips_affiliate_links_inject_post',          array( $this, 'ajax_inject_post' ) );
		add_action( 'wp_ajax_aips_affiliate_links_count_affected_posts',array( $this, 'ajax_count_affected_posts' ) );
		add_action( 'wp_ajax_aips_affiliate_suggestions_list',          array( $this, 'ajax_suggestions_list' ) );
		add_action( 'wp_ajax_aips_affiliate_suggestions_apply',         array( $this, 'ajax_suggestions_apply' ) );
		add_action( 'wp_ajax_aips_affiliate_suggestions_reject',        array( $this, 'ajax_suggestions_reject' ) );
		add_action( 'wp_ajax_aips_affiliate_suggestions_bulk',          array( $this, 'ajax_suggestions_bulk' ) );
		add_action( 'wp_ajax_aips_affiliate_scan_start',                array( $this, 'ajax_scan_start' ) );
		add_action( 'wp_ajax_aips_affiliate_url_replace_start',         array( $this, 'ajax_url_replace_start' ) );
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

	/**
	 * AJAX: Count posts affected by an affiliate URL.
	 */
	public function ajax_count_affected_posts() {
		$this->verify_request();

		$url   = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		$count = $this->repo->count_posts_with_url( $url );

		AIPS_Ajax_Response::success( array(
			'count' => $count,
			'url'   => $url,
		) );
	}

	/**
	 * AJAX: List retroactive suggestions (paginated).
	 */
	public function ajax_suggestions_list() {
		$this->verify_request();

		$page     = max( 1, absint( isset( $_POST['page'] ) ? wp_unslash( $_POST['page'] ) : 1 ) );
		$per_page = max( 1, min( 100, absint( isset( $_POST['per_page'] ) ? wp_unslash( $_POST['per_page'] ) : 20 ) ) );
		$status   = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'pending';
		$offer_id = absint( isset( $_POST['offer_id'] ) ? wp_unslash( $_POST['offer_id'] ) : 0 );

		$items = $this->suggestions_repo->get_paginated( $per_page, $page, $status, $offer_id );
		$total = $this->suggestions_repo->count( $status, $offer_id );

		AIPS_Ajax_Response::success( array(
			'items'       => $items,
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
			'page'        => $page,
		) );
	}

	/**
	 * AJAX: Apply a single suggestion.
	 */
	public function ajax_suggestions_apply() {
		$this->verify_request();

		$id         = absint( isset( $_POST['id'] ) ? wp_unslash( $_POST['id'] ) : ( isset( $_POST['suggestion_id'] ) ? wp_unslash( $_POST['suggestion_id'] ) : 0 ) );
		$suggestion = $id ? $this->suggestions_repo->get_by_id( $id ) : null;

		if ( ! $suggestion ) {
			AIPS_Ajax_Response::error( array( 'message' => __( 'Suggestion not found.', 'ai-post-scheduler' ) ) );
		}

		$post = get_post( $suggestion->post_id );
		if ( ! $post ) {
			AIPS_Ajax_Response::error( array( 'message' => __( 'Post not found.', 'ai-post-scheduler' ) ) );
		}

		$content = $post->post_content;
		if ( ! empty( $suggestion->before_snippet ) && ! empty( $suggestion->after_snippet ) ) {
			if ( strpos( $content, $suggestion->before_snippet ) !== false ) {
				$content = str_replace( $suggestion->before_snippet, $suggestion->after_snippet, $content );
			} else {
				// Fallback append if snippet not found
				$content .= "\n" . $suggestion->after_snippet;
			}
		}

		$updated = wp_update_post( array(
			'ID'           => $post->ID,
			'post_content' => $content,
		) );

		if ( is_wp_error( $updated ) || ! $updated ) {
			AIPS_Ajax_Response::error( array( 'message' => __( 'Failed to update post.', 'ai-post-scheduler' ) ) );
		}

		AIPS_Link_Index_Service::get_instance()->index_post( (int) $post->ID, true );
		$this->suggestions_repo->update_status( $id, 'applied' );

		AIPS_Ajax_Response::success( array( 'message' => __( 'Affiliate link applied to post.', 'ai-post-scheduler' ) ) );
	}

	/**
	 * AJAX: Reject a single suggestion.
	 */
	public function ajax_suggestions_reject() {
		$this->verify_request();

		$id = absint( isset( $_POST['id'] ) ? wp_unslash( $_POST['id'] ) : ( isset( $_POST['suggestion_id'] ) ? wp_unslash( $_POST['suggestion_id'] ) : 0 ) );
		if ( ! $id ) {
			AIPS_Ajax_Response::error( array( 'message' => __( 'Invalid ID.', 'ai-post-scheduler' ) ) );
		}

		$this->suggestions_repo->update_status( $id, 'rejected' );
		AIPS_Ajax_Response::success( array( 'message' => __( 'Suggestion rejected.', 'ai-post-scheduler' ) ) );
	}

	/**
	 * AJAX: Bulk accept or reject suggestions.
	 */
	public function ajax_suggestions_bulk() {
		$this->verify_request();

		$action  = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$raw_ids = isset( $_POST['ids'] ) ? $_POST['ids'] : ( isset( $_POST['suggestion_ids'] ) ? $_POST['suggestion_ids'] : array() );
		$ids     = array_map( 'absint', (array) wp_unslash( $raw_ids ) );

		if ( empty( $ids ) ) {
			AIPS_Ajax_Response::error( array( 'message' => __( 'No suggestions selected.', 'ai-post-scheduler' ) ) );
		}

		$processed = 0;
		foreach ( $ids as $id ) {
			if ( 'accept' === $action ) {
				$suggestion = $this->suggestions_repo->get_by_id( $id );
				if ( $suggestion && 'pending' === $suggestion->status ) {
					$post = get_post( $suggestion->post_id );
					if ( $post ) {
						$content = $post->post_content;
						if ( ! empty( $suggestion->before_snippet ) && strpos( $content, $suggestion->before_snippet ) !== false ) {
							$content = str_replace( $suggestion->before_snippet, $suggestion->after_snippet, $content );
						} else {
							$content .= "\n" . $suggestion->after_snippet;
						}
						wp_update_post( array(
							'ID'           => $post->ID,
							'post_content' => $content,
						) );
						AIPS_Link_Index_Service::get_instance()->index_post( (int) $post->ID, true );
						$this->suggestions_repo->update_status( $id, 'applied' );
						$processed++;
					}
				}
			} elseif ( 'reject' === $action ) {
				$this->suggestions_repo->update_status( $id, 'rejected' );
				$processed++;
			}
		}

		AIPS_Ajax_Response::success( array(
			'message'   => sprintf( __( 'Processed %d suggestions.', 'ai-post-scheduler' ), $processed ),
			'processed' => $processed,
		) );
	}

	/**
	 * AJAX: Start background retroactive affiliate scan.
	 */
	public function ajax_scan_start() {
		$this->verify_request();

		$manager = AIPS_Background_Process_Manager::get_instance();
		$started = $manager->start_process( 'affiliate_links_scan' );
		if ( is_wp_error( $started ) ) {
			AIPS_Ajax_Response::error( array( 'message' => $started->get_error_message() ) );
		}
		AIPS_Ajax_Response::success( array( 'message' => __( 'Retroactive affiliate scan started in the background.', 'ai-post-scheduler' ) ) );
	}

	/**
	 * AJAX: Start background affiliate URL replacement.
	 */
	public function ajax_url_replace_start() {
		$this->verify_request();

		$old_url = isset( $_POST['old_url'] ) ? esc_url_raw( wp_unslash( $_POST['old_url'] ) ) : '';
		$new_url = isset( $_POST['new_url'] ) ? esc_url_raw( wp_unslash( $_POST['new_url'] ) ) : '';

		if ( empty( $old_url ) || empty( $new_url ) ) {
			AIPS_Ajax_Response::error( array( 'message' => __( 'Both Old URL and New URL are required.', 'ai-post-scheduler' ) ) );
		}

		$manager = AIPS_Background_Process_Manager::get_instance();
		$started = $manager->start_process( 'affiliate_url_replace', array(
			'old_url' => $old_url,
			'new_url' => $new_url,
		) );

		if ( is_wp_error( $started ) ) {
			AIPS_Ajax_Response::error( array( 'message' => $started->get_error_message() ) );
		}

		AIPS_Ajax_Response::success( array( 'message' => __( 'Affiliate URL mass-update job started in the background.', 'ai-post-scheduler' ) ) );
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
		$raw_categories = isset( $_POST['category_ids'] ) ? wp_unslash( $_POST['category_ids'] ) : array();
		if ( is_string( $raw_categories ) ) {
			$raw_categories = array_filter( array_map( 'trim', explode( ',', $raw_categories ) ) );
		}
		$category_ids = is_array( $raw_categories ) ? array_map( 'absint', $raw_categories ) : array();

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
			'keywords'           => isset( $_POST['keywords'] )           ? sanitize_text_field( wp_unslash( $_POST['keywords'] ) )           : '',
			'category_ids'       => $category_ids,
			'prompt_guidance'    => isset( $_POST['prompt_guidance'] )    ? sanitize_textarea_field( wp_unslash( $_POST['prompt_guidance'] ) ): '',
			'is_global_fallback' => isset( $_POST['is_global_fallback'] ) ? (bool) wp_unslash( $_POST['is_global_fallback'] )               : false,
		);
	}
}
