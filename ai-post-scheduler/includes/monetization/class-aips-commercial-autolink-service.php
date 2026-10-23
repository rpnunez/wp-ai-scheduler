<?php
/**
 * Commercial Autolink Service
 *
 * Automatically finds high-intent commercial keywords and product titles
 * within generated post content and turns them into contextual hyperlinks
 * pointing to WooCommerce products or affiliate offer landing pages.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Commercial_Autolink_Service {

	/**
	 * @var AIPS_WooCommerce_Adapter
	 */
	private $wc_adapter;

	/**
	 * Constructor.
	 *
	 * @param AIPS_WooCommerce_Adapter $wc_adapter
	 */
	public function __construct( $wc_adapter = null ) {
		$this->wc_adapter = $wc_adapter ?: new AIPS_WooCommerce_Adapter();
	}

	/**
	 * Automatically insert commercial product links into post content.
	 *
	 * @param string $content HTML or markdown content.
	 * @param array  $product_ids Explicit product IDs to autolink, or empty to auto-detect.
	 * @param int    $max_links Maximum commercial autolinks to insert (default 3).
	 * @return string
	 */
	public function autolink_products( $content, $product_ids = array(), $max_links = 3 ) {
		if ( empty( $content ) || 0 === $max_links ) {
			return $content;
		}

		$products = array();

		// Fetch products
		if ( ! empty( $product_ids ) ) {
			foreach ( $product_ids as $pid ) {
				$p = $this->wc_adapter->get_product( $pid );
				if ( $p ) {
					$products[] = $p;
				}
			}
		} elseif ( $this->wc_adapter->is_active() ) {
			// Pull top 20 popular products as potential keyword targets
			$products = $this->wc_adapter->get_products(
				array(
					'limit'   => 20,
					'orderby' => 'popularity',
				)
			);
		}

		if ( empty( $products ) ) {
			return $content;
		}

		$links_added = 0;

		foreach ( $products as $product ) {
			if ( $links_added >= $max_links ) {
				break;
			}

			$title = trim( $product['title'] );
			if ( strlen( $title ) < 4 ) {
				continue;
			}

			$url    = esc_url( $product['add_to_cart_url'] ?: $product['permalink'] );
			$rel    = $product['is_external'] ? 'rel="nofollow sponsored"' : '';
			$target = 'target="_blank"';

			// Build replacement link
			$replacement = sprintf(
				'<a href="%s" %s %s class="aips-commercial-autolink" data-product-id="%d">%s</a>',
				$url,
				$rel,
				$target,
				$product['id'],
				'$0'
			);

			// Regex that matches whole phrase outside existing <a> tags or HTML attributes
			$escaped_title = preg_quote( $title, '/' );
			$pattern       = '/(?!(?:[^<]+>|[^>]+<\/a>))\b(' . $escaped_title . ')\b/iu';

			$new_content = preg_replace( $pattern, $replacement, $content, 1, $count );
			if ( $count > 0 && null !== $new_content ) {
				$content = $new_content;
				$links_added++;
			}
		}

		return $content;
	}
}
