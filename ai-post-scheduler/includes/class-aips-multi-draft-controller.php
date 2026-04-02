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

		$post = get_post( $post_id );
		if ( ! ( $post instanceof WP_Post ) ) {
			wp_send_json_error( array( 'message' => __( 'Post not found.', 'ai-post-scheduler' ) ) );
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
		$current_components = array(
			'title' => (string) $post->post_title,
			'excerpt' => (string) $post->post_excerpt,
			'content' => (string) $post->post_content,
		);

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
			'current_components' => $current_components,
		) );
	}

	/**
	 * AJAX handler: Apply a section-level merged draft to an existing post.
	 *
	 * Accepts POST fields:
	 *   post_id    (int)
	 *   history_id (int)
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
		$history_id = isset( $_POST['history_id'] )                               ? absint( $_POST['history_id'] ) : 0;
		$components = isset( $_POST['components'] ) && is_array( $_POST['components'] ) ? $_POST['components'] : array();

		if ( ! $post_id || ! $history_id || empty( $components ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'ai-post-scheduler' ) ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this post.', 'ai-post-scheduler' ) ) );
		}

		$post = get_post( $post_id );
		if ( ! ( $post instanceof WP_Post ) ) {
			wp_send_json_error( array( 'message' => __( 'Post not found.', 'ai-post-scheduler' ) ) );
		}

		$post_data          = array( 'ID' => $post_id );
		$updated_components = array();
		$sanitized          = array();

		$current_components = array(
			'title' => (string) $post->post_title,
			'excerpt' => (string) $post->post_excerpt,
			'content' => (string) $post->post_content,
		);

		$component_fields = array(
			'title' => 'post_title',
			'excerpt' => 'post_excerpt',
			'content' => 'post_content',
		);

		$component_sanitizers = array(
			'title' => 'sanitize_text_field',
			'excerpt' => 'sanitize_textarea_field',
			'content' => 'wp_kses_post',
		);

		foreach ( array( 'title', 'excerpt', 'content' ) as $component ) {
			if ( ! array_key_exists( $component, $components ) ) {
				continue;
			}

			$raw_new_value = wp_unslash( $components[ $component ] );
			$new_value = call_user_func( $component_sanitizers[ $component ], $raw_new_value );
			$sanitized[ $component ] = $new_value;

			if ( (string) $new_value === (string) $current_components[ $component ] ) {
				continue;
			}

			$snapshot_result = $this->service->capture_component_revision(
				$post_id,
				$history_id,
				$component,
				$this->sanitize_component_revision_value( $component, $current_components[ $component ] ),
				'multi_draft',
				'pre_apply_multi_draft'
			);

			if ( is_wp_error( $snapshot_result ) ) {
				wp_send_json_error( array( 'message' => $snapshot_result->get_error_message() ) );
			}

			$post_data[ $component_fields[ $component ] ] = $new_value;
			$updated_components[] = $component;
		}

		if ( empty( $updated_components ) ) {
			wp_send_json_success( array(
				'message' => __( 'No changes were applied.', 'ai-post-scheduler' ),
				'updated_components' => array(),
			) );
		}

		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Build a sanitized copy for the action hook (mirrors AIPS_AI_Edit_Controller pattern).
		$sanitized_for_action = array();
		foreach ( $updated_components as $component ) {
			if ( isset( $sanitized[ $component ] ) ) {
				$sanitized_for_action[ $component ] = $sanitized[ $component ];
			}
		}

		do_action( 'aips_post_components_updated', $post_id, $updated_components, $sanitized_for_action );

		wp_send_json_success( array(
			'message'            => __( 'Draft applied to post successfully!', 'ai-post-scheduler' ),
			'updated_components' => $updated_components,
		) );
	}

	/**
	 * Sanitize revision snapshot values before persistence.
	 *
	 * @param string $component Component key.
	 * @param mixed  $value Component value.
	 * @return string
	 */
	private function sanitize_component_revision_value( $component, $value ) {
		switch ( $component ) {
			case 'title':
				return sanitize_text_field( (string) $value );

			case 'excerpt':
				return sanitize_textarea_field( (string) $value );

			case 'content':
				return wp_kses_post( (string) $value );

			default:
				return '';
		}
	}
}
