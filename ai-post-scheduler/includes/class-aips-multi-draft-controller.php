<?php
/**
 * Multi-Draft Controller
 *
 * Handles generation of multiple draft variants for side-by-side comparison
 * and section-level merge before publishing.
 *
 * @package AI_Post_Scheduler
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIPS_Multi_Draft_Controller
 *
 * Generates N preview variants of a post (without saving them) so the editor
 * can compare title / excerpt / content side-by-side and apply a merged
 * selection back to the existing draft.
 */
class AIPS_Multi_Draft_Controller {

	/**
	 * @var AIPS_Component_Regeneration_Service
	 */
	private $service;

	/**
	 * @var AIPS_Generator
	 */
	private $generator;

	/**
	 * Constructor — registers AJAX hooks.
	 */
	public function __construct() {
		$this->service   = new AIPS_Component_Regeneration_Service();
		$this->generator = new AIPS_Generator();

		add_action( 'wp_ajax_aips_generate_variants',  array( $this, 'ajax_generate_variants' ) );
		add_action( 'wp_ajax_aips_apply_merged_draft', array( $this, 'ajax_apply_merged_draft' ) );
	}

	/**
	 * Return the configured maximum number of variants (2–3).
	 *
	 * Reads the option set on the General settings tab.
	 *
	 * @return int
	 */
	public static function get_max_variants() {
		return max( 2, min( 3, (int) get_option( 'aips_multi_draft_max_variants', 3 ) ) );
	}

	/**
	 * AJAX handler: Generate multiple preview variants for a post.
	 *
	 * Accepts POST fields:
	 *   post_id       (int)
	 *   history_id    (int)
	 *   variant_count (int, 2–max)
	 *
	 * Returns JSON success with:
	 *   variants      array of {index, title, excerpt, content}
	 *   variant_count int
	 */
	public function ajax_generate_variants() {
		check_ajax_referer( 'aips_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-post-scheduler' ) ) );
		}

		$post_id       = isset( $_POST['post_id'] )       ? absint( $_POST['post_id'] )       : 0;
		$history_id    = isset( $_POST['history_id'] )    ? absint( $_POST['history_id'] )    : 0;
		$variant_count = isset( $_POST['variant_count'] ) ? absint( $_POST['variant_count'] ) : 2;

		if ( ! $post_id || ! $history_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'ai-post-scheduler' ) ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this post.', 'ai-post-scheduler' ) ) );
		}

		// Clamp to the configured limit.
		$max_variants  = self::get_max_variants();
		$variant_count = max( 2, min( $variant_count, $max_variants ) );

		// Retrieve the original generation context from history.
		$context = $this->service->get_generation_context( $history_id );
		if ( is_wp_error( $context ) ) {
			wp_send_json_error( array( 'message' => $context->get_error_message() ) );
		}

		if ( ! isset( $context['generation_context'] ) || ! ( $context['generation_context'] instanceof AIPS_Generation_Context ) ) {
			wp_send_json_error( array( 'message' => __( 'Generation context not found.', 'ai-post-scheduler' ) ) );
		}

		$generation_context = $context['generation_context'];

		// Increase PHP time limit for multiple generation calls.
		if ( ! ini_get( 'safe_mode' ) ) {
			set_time_limit( 300 );
		}

		// Generate N independent previews (no WP post created).
		$variants = array();
		for ( $i = 0; $i < $variant_count; $i++ ) {
			$preview = $this->generator->generate_preview( $generation_context );

			if ( is_wp_error( $preview ) ) {
				wp_send_json_error( array(
					/* translators: 1: variant number, 2: error message */
					'message' => sprintf( __( 'Variant %1$d failed: %2$s', 'ai-post-scheduler' ), $i + 1, $preview->get_error_message() ),
				) );
			}

			$variants[] = array(
				'index'   => $i + 1,
				'title'   => isset( $preview['title'] )   ? $preview['title']   : '',
				'excerpt' => isset( $preview['excerpt'] ) ? $preview['excerpt'] : '',
				'content' => isset( $preview['content'] ) ? $preview['content'] : '',
			);
		}

		wp_send_json_success( array(
			'variants'      => $variants,
			'variant_count' => count( $variants ),
		) );
	}

	/**
	 * AJAX handler: Apply a section-level merged draft to an existing post.
	 *
	 * Accepts POST fields:
	 *   post_id    (int)
	 *   components (array: title, excerpt, content)
	 *
	 * Returns JSON success with:
	 *   message             string
	 *   updated_components  string[]
	 */
	public function ajax_apply_merged_draft() {
		check_ajax_referer( 'aips_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-post-scheduler' ) ) );
		}

		$post_id    = isset( $_POST['post_id'] )                                  ? absint( $_POST['post_id'] )   : 0;
		$components = isset( $_POST['components'] ) && is_array( $_POST['components'] ) ? $_POST['components'] : array();

		if ( ! $post_id || empty( $components ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'ai-post-scheduler' ) ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this post.', 'ai-post-scheduler' ) ) );
		}

		$post_data          = array( 'ID' => $post_id );
		$updated_components = array();

		if ( ! empty( $components['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( wp_unslash( $components['title'] ) );
			$updated_components[]    = 'title';
		}

		if ( isset( $components['excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_textarea_field( wp_unslash( $components['excerpt'] ) );
			$updated_components[]      = 'excerpt';
		}

		if ( ! empty( $components['content'] ) ) {
			$post_data['post_content'] = wp_kses_post( wp_unslash( $components['content'] ) );
			$updated_components[]      = 'content';
		}

		if ( empty( $updated_components ) ) {
			wp_send_json_error( array( 'message' => __( 'No components selected.', 'ai-post-scheduler' ) ) );
		}

		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Build a sanitized copy for the action hook (mirrors AIPS_AI_Edit_Controller pattern).
		$sanitized = array();
		if ( isset( $post_data['post_title'] ) )   { $sanitized['title']   = $post_data['post_title']; }
		if ( isset( $post_data['post_excerpt'] ) ) { $sanitized['excerpt'] = $post_data['post_excerpt']; }
		if ( isset( $post_data['post_content'] ) ) { $sanitized['content'] = $post_data['post_content']; }

		do_action( 'aips_post_components_updated', $post_id, $updated_components, $sanitized );

		wp_send_json_success( array(
			'message'            => __( 'Draft applied to post successfully!', 'ai-post-scheduler' ),
			'updated_components' => $updated_components,
		) );
	}
}
