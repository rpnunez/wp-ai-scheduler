<?php
/**
 * Catalog Enrichment Controller
 *
 * Handles AJAX requests for the WooCommerce Catalog AI Enrichment Hub:
 * - Filtering and listing under-optimized products
 * - Generating AI preview diffs (short description, full copy, specs)
 * - Applying approved AI enhancements to products
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Catalog_Enrichment_Controller {

	/**
	 * @var AIPS_WooCommerce_Adapter
	 */
	private $wc_adapter;

	/**
	 * @var AIPS_AI_Service_Interface
	 */
	private $ai_service;

	/**
	 * @var AIPS_Prompt_Builder_Catalog_Enrichment
	 */
	private $prompt_builder;

	/**
	 * Constructor.
	 *
	 * @param AIPS_WooCommerce_Adapter                    $wc_adapter
	 * @param AIPS_AI_Service_Interface                   $ai_service
	 * @param AIPS_Prompt_Builder_Catalog_Enrichment|null $prompt_builder
	 */
	public function __construct( $wc_adapter = null, $ai_service = null, $prompt_builder = null ) {
		$this->wc_adapter     = $wc_adapter ?: new AIPS_WooCommerce_Adapter();
		$this->ai_service     = $ai_service ?: ( class_exists( 'AIPS_Container' ) && AIPS_Container::get_instance()->has( AIPS_AI_Service_Interface::class ) ? AIPS_Container::get_instance()->make( AIPS_AI_Service_Interface::class ) : null );
		$this->prompt_builder = $prompt_builder ?: new AIPS_Prompt_Builder_Catalog_Enrichment();

		$this->register_ajax_hooks();
	}

	/**
	 * Register AJAX endpoints.
	 *
	 * @return void
	 */
	public function register_ajax_hooks() {
		add_action( 'wp_ajax_aips_catalog_enrichment_list', array( $this, 'ajax_list_products' ) );
		add_action( 'wp_ajax_aips_catalog_enrichment_preview', array( $this, 'ajax_preview_enrichment' ) );
		add_action( 'wp_ajax_aips_catalog_enrichment_apply', array( $this, 'ajax_apply_enrichment' ) );
	}

	/**
	 * AJAX endpoint: List products for enrichment with filters.
	 *
	 * @return void
	 */
	public function ajax_list_products() {
		check_ajax_referer( 'aips_catalog_enrichment_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			AIPS_Ajax_Response::error( __( 'Unauthorized capability.', 'ai-post-scheduler' ), 403 );
		}

		$filters = array(
			'category_id' => isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0,
			'filter_by'   => isset( $_POST['filter_by'] ) ? sanitize_key( $_POST['filter_by'] ) : 'all',
			'search'      => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
			'paged'       => isset( $_POST['paged'] ) ? max( 1, absint( $_POST['paged'] ) ) : 1,
			'per_page'    => isset( $_POST['per_page'] ) ? min( 100, max( 5, absint( $_POST['per_page'] ) ) ) : 20,
		);

		$result = $this->wc_adapter->get_products_for_enrichment( $filters );

		AIPS_Ajax_Response::success( $result );
	}

	/**
	 * AJAX endpoint: Generate preview diff for product enrichment.
	 *
	 * @return void
	 */
	public function ajax_preview_enrichment() {
		check_ajax_referer( 'aips_catalog_enrichment_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			AIPS_Ajax_Response::error( __( 'Unauthorized capability.', 'ai-post-scheduler' ), 403 );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		if ( ! $product_id ) {
			AIPS_Ajax_Response::error( __( 'Invalid product ID.', 'ai-post-scheduler' ), 400 );
		}

		$product = $this->wc_adapter->get_product( $product_id );
		if ( ! $product ) {
			AIPS_Ajax_Response::error( __( 'Product not found.', 'ai-post-scheduler' ), 404 );
		}

		$target_field = isset( $_POST['target_field'] ) ? sanitize_key( $_POST['target_field'] ) : 'short_description';
		$tone         = isset( $_POST['tone'] ) ? sanitize_text_field( wp_unslash( $_POST['tone'] ) ) : 'persuasive and benefits-focused';

		// Construct prompt using specialized prompt builder
		$prompts = $this->prompt_builder->build( $product, $target_field, $tone );

		$generated_text = '';

		if ( $this->ai_service ) {
			try {
				$ai_result      = $this->ai_service->generate_text( $prompts['user_prompt'], array( 'system_prompt' => $prompts['system_prompt'] ) );
				$generated_text = is_string( $ai_result ) ? $ai_result : ( $ai_result['text'] ?? '' );
			} catch ( Exception $e ) {
				$generated_text = sprintf( __( 'AI generation error: %s', 'ai-post-scheduler' ), $e->getMessage() );
			}
		} else {
			// Mock fallback preview for testing or when AI is offline
			$generated_text = "<p>Discover the exceptional performance of <strong>" . esc_html( $product['title'] ) . "</strong>. Engineered for quality and designed for everyday reliability.</p>\n<ul>\n<li><strong>Premium Craftsmanship:</strong> Built to deliver lasting value and durability.</li>\n<li><strong>Optimized Performance:</strong> Carefully refined for superior everyday user experience.</li>\n<li><strong>Hassle-Free Satisfaction:</strong> Backed by dedicated customer support.</li>\n</ul>";
		}

		$current_value = ( 'short_description' === $target_field ) ? $product['short_description'] : $product['full_description'];

		AIPS_Ajax_Response::success(
			array(
				'product_id'   => $product_id,
				'target_field' => $target_field,
				'current'      => $current_value,
				'proposed'     => trim( $generated_text ),
			)
		);
	}

	/**
	 * AJAX endpoint: Apply enriched content to product.
	 *
	 * @return void
	 */
	public function ajax_apply_enrichment() {
		check_ajax_referer( 'aips_catalog_enrichment_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			AIPS_Ajax_Response::error( __( 'Unauthorized capability.', 'ai-post-scheduler' ), 403 );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		if ( ! $product_id ) {
			AIPS_Ajax_Response::error( __( 'Invalid product ID.', 'ai-post-scheduler' ), 400 );
		}

		$fields = array();
		if ( isset( $_POST['short_description'] ) ) {
			$fields['short_description'] = wp_kses_post( wp_unslash( $_POST['short_description'] ) );
		}
		if ( isset( $_POST['full_description'] ) ) {
			$fields['full_description'] = wp_kses_post( wp_unslash( $_POST['full_description'] ) );
		}
		if ( isset( $_POST['title'] ) ) {
			$fields['title'] = sanitize_text_field( wp_unslash( $_POST['title'] ) );
		}

		$result = $this->wc_adapter->update_product_enrichment( $product_id, $fields );

		if ( is_wp_error( $result ) ) {
			AIPS_Ajax_Response::error( $result->get_error_message() );
		}

		AIPS_Ajax_Response::success(
			array(
				'product_id' => $product_id,
				'updated'    => true,
				'message'    => __( 'Product enriched successfully.', 'ai-post-scheduler' ),
			)
		);
	}
}
