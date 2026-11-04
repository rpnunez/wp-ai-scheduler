<?php
/**
 * WooCommerce Adapter
 *
 * Provides a safe abstraction layer over WooCommerce functions and objects.
 * Operates gracefully even when WooCommerce is not installed or activated.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_WooCommerce_Adapter {

	/**
	 * Check if WooCommerce is installed and active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return class_exists( 'WooCommerce' ) || function_exists( 'WC' );
	}

	/**
	 * Retrieve a normalized product payload by ID.
	 *
	 * Returns an associative array containing all relevant product metadata,
	 * pricing, images, and conversion URLs, or null if the product does not exist.
	 *
	 * @param int $product_id WordPress Post/Product ID.
	 * @return array|null
	 */
	public function get_product( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return null;
		}

		$post = get_post( $product_id );
		if ( ! $post || 'trash' === $post->post_status ) {
			return null;
		}

		// Fallback for non-WooCommerce environments or standard posts treated as products
		if ( ! $this->is_active() || 'product' !== $post->post_type ) {
			return $this->get_generic_product_payload( $post );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return null;
		}

		$is_external  = $product->is_type( 'external' );
		$external_url = $is_external ? $product->get_product_url() : '';
		$button_text  = $is_external ? ( $product->get_button_text() ?: __( 'Buy Now', 'ai-post-scheduler' ) ) : __( 'Add to Cart', 'ai-post-scheduler' );
		$add_to_cart  = $is_external ? $external_url : $product->add_to_cart_url();

		$image_id  = $product->get_image_id();
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';

		// Categories
		$categories = array();
		$cat_terms  = get_the_terms( $product_id, 'product_cat' );
		if ( ! empty( $cat_terms ) && ! is_wp_error( $cat_terms ) ) {
			foreach ( $cat_terms as $term ) {
				$categories[] = array(
					'id'   => $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
				);
			}
		}

		// Product attributes (e.g. Brand, Material, Size, etc.)
		$formatted_attributes = array();
		$attributes           = $product->get_attributes();
		if ( ! empty( $attributes ) ) {
			foreach ( $attributes as $attr_name => $attribute ) {
				$label = wc_attribute_label( $attr_name );
				if ( is_object( $attribute ) && method_exists( $attribute, 'get_options' ) ) {
					$values = array();
					if ( $attribute->is_taxonomy() ) {
						$terms = $attribute->get_terms();
						if ( ! empty( $terms ) ) {
							foreach ( $terms as $term ) {
								$values[] = $term->name;
							}
						}
					} else {
						$values = $attribute->get_options();
					}
					$formatted_attributes[ $label ] = implode( ', ', $values );
				} elseif ( is_string( $attribute ) ) {
					$formatted_attributes[ $label ] = $attribute;
				}
			}
		}

		return array(
			'id'                => $product_id,
			'title'             => $product->get_name(),
			'permalink'         => get_permalink( $product_id ),
			'type'              => $product->get_type(),
			'sku'               => $product->get_sku(),
			'price'             => $product->get_price(),
			'regular_price'     => $product->get_regular_price(),
			'sale_price'        => $product->get_sale_price(),
			'on_sale'           => $product->is_on_sale(),
			'formatted_price'   => wp_strip_all_tags( wc_price( $product->get_price() ) ),
			'currency'          => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$',
			'short_description' => $product->get_short_description(),
			'full_description'  => $post->post_content,
			'image_url'         => $image_url,
			'in_stock'          => $product->is_in_stock(),
			'stock_status'      => $product->get_stock_status(),
			'rating'            => (float) $product->get_average_rating(),
			'review_count'      => (int) $product->get_review_count(),
			'is_external'       => $is_external,
			'external_url'      => $external_url,
			'add_to_cart_url'   => $add_to_cart,
			'button_text'       => $button_text,
			'categories'        => $categories,
			'attributes'        => $formatted_attributes,
		);
	}

	/**
	 * Retrieve products for buying guide or roundup generation.
	 *
	 * @param array $args Query arguments:
	 *                    - category: category slug or ID
	 *                    - limit: number of products (default 5)
	 *                    - orderby: 'date', 'popularity', 'rating', 'price'
	 *                    - order: 'DESC' or 'ASC'
	 *                    - in_stock_only: bool
	 * @return array List of normalized product arrays.
	 */
	public function get_products( $args = array() ) {
		if ( ! $this->is_active() ) {
			return array();
		}

		$defaults = array(
			'category'      => '',
			'limit'         => 5,
			'orderby'       => 'popularity',
			'order'         => 'DESC',
			'in_stock_only' => true,
		);
		$args     = wp_parse_args( $args, $defaults );

		$query_args = array(
			'status'  => 'publish',
			'limit'   => absint( $args['limit'] ),
			'orderby' => sanitize_key( $args['orderby'] ),
			'order'   => 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC',
		);

		if ( ! empty( $args['category'] ) ) {
			$query_args['category'] = array( sanitize_text_field( $args['category'] ) );
		}

		if ( ! empty( $args['in_stock_only'] ) ) {
			$query_args['stock_status'] = 'instock';
		}

		$products = wc_get_products( $query_args );
		$results  = array();

		foreach ( $products as $product ) {
			$payload = $this->get_product( $product->get_id() );
			if ( $payload ) {
				$results[] = $payload;
			}
		}

		return $results;
	}

	/**
	 * Query products specifically for Catalog Enrichment workflows.
	 *
	 * Allows filtering by missing short description, missing full description,
	 * or category to target under-optimized products.
	 *
	 * @param array $filters Filter criteria.
	 * @return array
	 */
	public function get_products_for_enrichment( $filters = array() ) {
		global $wpdb;

		if ( ! $this->is_active() ) {
			return array(
				'items' => array(),
				'total' => 0,
			);
		}

		$defaults = array(
			'category_id' => 0,
			'status'      => 'publish',
			'filter_by'   => 'all', // 'all', 'missing_short_desc', 'missing_content', 'low_word_count'
			'search'      => '',
			'paged'       => 1,
			'per_page'    => 20,
		);
		$filters  = wp_parse_args( $filters, $defaults );

		$where = "WHERE p.post_type = 'product' AND p.post_status != 'trash'";

		if ( ! empty( $filters['status'] ) && 'any' !== $filters['status'] ) {
			$where .= $wpdb->prepare( ' AND p.post_status = %s', sanitize_key( $filters['status'] ) );
		}

		if ( ! empty( $filters['search'] ) ) {
			$like   = '%' . $wpdb->esc_like( sanitize_text_field( $filters['search'] ) ) . '%';
			$where .= $wpdb->prepare( ' AND (p.post_title LIKE %s OR p.post_content LIKE %s)', $like, $like );
		}

		if ( 'missing_short_desc' === $filters['filter_by'] ) {
			$where .= " AND (p.post_excerpt IS NULL OR TRIM(p.post_excerpt) = '')";
		} elseif ( 'missing_content' === $filters['filter_by'] ) {
			$where .= " AND (p.post_content IS NULL OR TRIM(p.post_content) = '')";
		} elseif ( 'low_word_count' === $filters['filter_by'] ) {
			$where .= " AND (CHAR_LENGTH(p.post_content) - CHAR_LENGTH(REPLACE(p.post_content, ' ', '')) + 1) < 50";
		}

		$join = '';
		if ( ! empty( $filters['category_id'] ) ) {
			$cat_id = absint( $filters['category_id'] );
			$join  .= " INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id";
			$join  .= " INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'product_cat'";
			$where .= $wpdb->prepare( ' AND tt.term_id = %d', $cat_id );
		}

		$total = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p {$join} {$where}" );

		$per_page = max( 1, absint( $filters['per_page'] ) );
		$offset   = ( max( 1, absint( $filters['paged'] ) ) - 1 ) * $per_page;

		$ids = $wpdb->get_col( "SELECT DISTINCT p.ID FROM {$wpdb->posts} p {$join} {$where} ORDER BY p.post_modified DESC LIMIT {$offset}, {$per_page}" );

		$items = array();
		foreach ( $ids as $id ) {
			$product_data = $this->get_product( $id );
			if ( $product_data ) {
				$items[] = $product_data;
			}
		}

		return array(
			'items' => $items,
			'total' => $total,
			'pages' => ceil( $total / $per_page ),
		);
	}

	/**
	 * Update targeted fields on a WooCommerce product after AI enrichment.
	 *
	 * Preserves critical e-commerce data (prices, inventory, tax, shipping) while
	 * updating the requested content elements.
	 *
	 * @param int   $product_id Product Post ID.
	 * @param array $fields     Array of fields to update:
	 *                          - short_description
	 *                          - full_description
	 *                          - title
	 *                          - meta: key => value pairs (e.g. SEO meta)
	 * @return bool|WP_Error
	 */
	public function update_product_enrichment( $product_id, $fields = array() ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return new WP_Error( 'invalid_id', __( 'Invalid product ID.', 'ai-post-scheduler' ) );
		}

		$post_update = array(
			'ID' => $product_id,
		);

		if ( isset( $fields['title'] ) && '' !== trim( $fields['title'] ) ) {
			$post_update['post_title'] = sanitize_text_field( $fields['title'] );
		}

		if ( isset( $fields['short_description'] ) ) {
			$post_update['post_excerpt'] = wp_kses_post( $fields['short_description'] );
		}

		if ( isset( $fields['full_description'] ) ) {
			$post_update['post_content'] = wp_kses_post( $fields['full_description'] );
		}

		if ( count( $post_update ) > 1 ) {
			$result = wp_update_post( $post_update, true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		// Update custom meta if provided
		if ( ! empty( $fields['meta'] ) && is_array( $fields['meta'] ) ) {
			foreach ( $fields['meta'] as $meta_key => $meta_val ) {
				update_post_meta( $product_id, sanitize_key( $meta_key ), sanitize_text_field( $meta_val ) );
			}
		}

		return true;
	}

	/**
	 * Provide a standardized product-like payload for standard posts or non-WC environments.
	 *
	 * @param WP_Post $post
	 * @return array
	 */
	private function get_generic_product_payload( $post ) {
		$image_id  = get_post_thumbnail_id( $post->ID );
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';

		$price = get_post_meta( $post->ID, '_price', true ) ?: get_post_meta( $post->ID, 'price', true );
		$url   = get_post_meta( $post->ID, '_affiliate_url', true ) ?: get_permalink( $post->ID );

		return array(
			'id'                => $post->ID,
			'title'             => $post->post_title,
			'permalink'         => get_permalink( $post->ID ),
			'type'              => 'standard',
			'sku'               => (string) get_post_meta( $post->ID, '_sku', true ),
			'price'             => $price ? (string) $price : '',
			'regular_price'     => $price ? (string) $price : '',
			'sale_price'        => '',
			'on_sale'           => false,
			'formatted_price'   => $price ? '$' . $price : '',
			'currency'          => '$',
			'short_description' => $post->post_excerpt,
			'full_description'  => $post->post_content,
			'image_url'         => $image_url,
			'in_stock'          => true,
			'stock_status'      => 'instock',
			'rating'            => (float) get_post_meta( $post->ID, '_rating', true ) ?: 4.5,
			'review_count'      => (int) get_post_meta( $post->ID, '_reviews_count', true ) ?: 1,
			'is_external'       => ! empty( get_post_meta( $post->ID, '_affiliate_url', true ) ),
			'external_url'      => $url,
			'add_to_cart_url'   => $url,
			'button_text'       => __( 'Check Price', 'ai-post-scheduler' ),
			'categories'        => array(),
			'attributes'        => array(),
		);
	}
}
