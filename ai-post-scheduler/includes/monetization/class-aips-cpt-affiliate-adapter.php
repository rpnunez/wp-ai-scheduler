<?php
/**
 * Generic CPT Affiliate Adapter
 *
 * Allows arbitrary Custom Post Types (reviews, deals, products) to function as
 * monetized items inside comparison tables, product cards, and autolink widgets.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_CPT_Affiliate_Adapter {

	/**
	 * Config option key for CPT field mappings.
	 */
	const OPTION_KEY = 'aips_cpt_affiliate_mappings';

	/**
	 * Get all registered mappings for CPTs.
	 *
	 * @return array
	 */
	public function get_mappings() {
		$defaults = array(
			'post' => array(
				'url_field'      => '_affiliate_url',
				'price_field'    => '_price',
				'rating_field'   => '_rating',
				'merchant_field' => '_merchant',
				'button_text'    => __( 'View Deal', 'ai-post-scheduler' ),
			),
		);

		$saved = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( (array) $saved, $defaults );
	}

	/**
	 * Save field mapping for a specific custom post type.
	 *
	 * @param string $post_type
	 * @param array  $mapping
	 * @return bool
	 */
	public function save_mapping( $post_type, $mapping ) {
		$post_type = sanitize_key( $post_type );
		$all       = $this->get_mappings();

		$all[ $post_type ] = array(
			'url_field'      => sanitize_key( $mapping['url_field'] ?? '_affiliate_url' ),
			'price_field'    => sanitize_key( $mapping['price_field'] ?? '_price' ),
			'rating_field'   => sanitize_key( $mapping['rating_field'] ?? '_rating' ),
			'merchant_field' => sanitize_key( $mapping['merchant_field'] ?? '_merchant' ),
			'button_text'    => sanitize_text_field( $mapping['button_text'] ?? __( 'View Deal', 'ai-post-scheduler' ) ),
		);

		return update_option( self::OPTION_KEY, $all );
	}

	/**
	 * Retrieve a normalized item payload from any CPT based on field mapping.
	 *
	 * @param int $post_id
	 * @return array|null
	 */
	public function get_item( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return null;
		}

		$post = get_post( $post_id );
		if ( ! $post || 'trash' === $post->post_status ) {
			return null;
		}

		$mappings = $this->get_mappings();
		$map      = $mappings[ $post->post_type ] ?? ( $mappings['post'] ?? array() );

		$url_field      = $map['url_field'] ?? '_affiliate_url';
		$price_field    = $map['price_field'] ?? '_price';
		$rating_field   = $map['rating_field'] ?? '_rating';
		$merchant_field = $map['merchant_field'] ?? '_merchant';
		$button_text    = $map['button_text'] ?? __( 'View Deal', 'ai-post-scheduler' );

		$affiliate_url = get_post_meta( $post_id, $url_field, true ) ?: get_permalink( $post_id );
		$price         = get_post_meta( $post_id, $price_field, true );
		$rating        = (float) get_post_meta( $post_id, $rating_field, true ) ?: 4.5;
		$merchant      = get_post_meta( $post_id, $merchant_field, true ) ?: get_bloginfo( 'name' );

		$image_id  = get_post_thumbnail_id( $post_id );
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';

		return array(
			'id'                => $post_id,
			'title'             => $post->post_title,
			'permalink'         => get_permalink( $post_id ),
			'type'              => $post->post_type,
			'sku'               => '',
			'price'             => (string) $price,
			'regular_price'     => (string) $price,
			'sale_price'        => '',
			'on_sale'           => false,
			'formatted_price'   => $price ? '$' . $price : '',
			'currency'          => '$',
			'short_description' => $post->post_excerpt,
			'full_description'  => $post->post_content,
			'image_url'         => $image_url,
			'in_stock'          => true,
			'stock_status'      => 'instock',
			'rating'            => $rating,
			'review_count'      => 1,
			'is_external'       => true,
			'external_url'      => $affiliate_url,
			'add_to_cart_url'   => $affiliate_url,
			'button_text'       => $button_text,
			'merchant'          => $merchant,
			'categories'        => array(),
			'attributes'        => array(),
		);
	}
}
