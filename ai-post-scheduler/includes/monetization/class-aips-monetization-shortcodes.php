<?php
/**
 * Monetization & Conversion Shortcodes
 *
 * Implements high-converting, dynamically rendered shortcodes for e-commerce,
 * affiliate marketing, and CRO (Product Cards, Comparison Tables, FAQs, Pros/Cons, Cross-Sells).
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Monetization_Shortcodes {

	/**
	 * @var AIPS_WooCommerce_Adapter
	 */
	private $wc_adapter;

	/**
	 * @var AIPS_CPT_Affiliate_Adapter
	 */
	private $cpt_adapter;

	/**
	 * Stored FAQ items for JSON-LD schema generation in head/footer.
	 *
	 * @var array
	 */
	private static $faq_schema_queue = array();

	/**
	 * Constructor.
	 *
	 * @param AIPS_WooCommerce_Adapter   $wc_adapter
	 * @param AIPS_CPT_Affiliate_Adapter $cpt_adapter
	 */
	public function __construct( $wc_adapter = null, $cpt_adapter = null ) {
		$this->wc_adapter  = $wc_adapter ?: new AIPS_WooCommerce_Adapter();
		$this->cpt_adapter = $cpt_adapter ?: new AIPS_CPT_Affiliate_Adapter();

		$this->register_shortcodes();
		$this->init_hooks();
	}

	/**
	 * Register all monetization shortcodes.
	 *
	 * @return void
	 */
	public function register_shortcodes() {
		add_shortcode( 'aips_product_card', array( $this, 'render_product_card' ) );
		add_shortcode( 'aips_comparison_table', array( $this, 'render_comparison_table' ) );
		add_shortcode( 'aips_pros_cons', array( $this, 'render_pros_cons' ) );
		add_shortcode( 'aips_faq', array( $this, 'render_faq' ) );
		add_shortcode( 'aips_toc', array( $this, 'render_toc' ) );
		add_shortcode( 'aips_cross_sells', array( $this, 'render_cross_sells' ) );
		add_shortcode( 'aips_ftc_disclosure', array( $this, 'render_ftc_disclosure' ) );
	}

	/**
	 * Register frontend hooks (scripts, styles, schema).
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_json_ld_schema' ), 20 );
	}

	/**
	 * Enqueue monetization styles and tracking scripts on the frontend.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_style(
			'aips-monetization',
			plugins_url( 'assets/css/aips-monetization.css', dirname( __DIR__ ) ),
			array(),
			AIPS_VERSION
		);

		wp_enqueue_script(
			'aips-monetization',
			plugins_url( 'assets/js/aips-monetization.js', dirname( __DIR__ ) ),
			array( 'jquery' ),
			AIPS_VERSION,
			true
		);

		wp_localize_script(
			'aips-monetization',
			'aipsMonetizationData',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'restUrl'       => rest_url( 'aips/v1/link-click' ),
				'nonce'         => wp_create_nonce( 'aips_monetization_nonce' ),
				'ga4Enabled'    => (bool) AIPS_Config::get_instance()->get_option( 'aips_ga4_tracking_enabled', true ),
				'postId'        => get_the_ID() ?: 0,
			)
		);
	}

	/**
	 * Resolve product data from either WooCommerce or CPT adapter.
	 *
	 * @param int $id
	 * @return array|null
	 */
	private function resolve_item_data( $id ) {
		$id = absint( $id );
		if ( ! $id ) {
			return null;
		}

		$post = get_post( $id );
		if ( ! $post ) {
			return null;
		}

		if ( 'product' === $post->post_type && $this->wc_adapter->is_active() ) {
			return $this->wc_adapter->get_product( $id );
		}

		return $this->cpt_adapter->get_item( $id );
	}

	/**
	 * Render [aips_product_card id="123" badge="Editor's Choice" layout="card|compact|horizontal"]
	 *
	 * @param array $atts
	 * @return string
	 */
	public function render_product_card( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'          => 0,
				'badge'       => '',
				'layout'      => 'card',
				'button_text' => '',
			),
			$atts,
			'aips_product_card'
		);

		$item = $this->resolve_item_data( $atts['id'] );
		if ( ! $item ) {
			return '';
		}

		$badge       = sanitize_text_field( $atts['badge'] );
		$layout      = in_array( $atts['layout'], array( 'compact', 'horizontal', 'card' ), true ) ? $atts['layout'] : 'card';
		$button_text = ! empty( $atts['button_text'] ) ? sanitize_text_field( $atts['button_text'] ) : $item['button_text'];

		ob_start();
		?>
		<div class="aips-product-card aips-layout-<?php echo esc_attr( $layout ); ?>" data-product-id="<?php echo esc_attr( $item['id'] ); ?>">
			<?php if ( ! empty( $badge ) ) : ?>
				<div class="aips-product-badge"><?php echo esc_html( $badge ); ?></div>
			<?php endif; ?>

			<div class="aips-card-inner">
				<?php if ( ! empty( $item['image_url'] ) ) : ?>
					<div class="aips-product-image-wrap">
						<a href="<?php echo esc_url( $item['permalink'] ); ?>" target="_blank" rel="noopener noreferrer">
							<img src="<?php echo esc_url( $item['image_url'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>" loading="lazy" class="aips-product-img" />
						</a>
					</div>
				<?php endif; ?>

				<div class="aips-product-details">
					<h3 class="aips-product-title">
						<a href="<?php echo esc_url( $item['permalink'] ); ?>" class="aips-product-link">
							<?php echo esc_html( $item['title'] ); ?>
						</a>
					</h3>

					<?php if ( ! empty( $item['rating'] ) ) : ?>
						<div class="aips-product-rating" aria-label="<?php echo esc_attr( sprintf( __( 'Rating: %s out of 5', 'ai-post-scheduler' ), $item['rating'] ) ); ?>">
							<span class="aips-stars">
								<?php
								$rating = min( 5, max( 0, (float) $item['rating'] ) );
								for ( $i = 1; $i <= 5; $i++ ) {
									echo ( $rating >= $i ) ? '★' : ( ( $rating >= $i - 0.5 ) ? '⯨' : '☆' );
								}
								?>
							</span>
							<span class="aips-rating-val"><?php echo esc_html( number_format( $rating, 1 ) ); ?></span>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $item['short_description'] ) ) : ?>
						<div class="aips-product-desc">
							<?php echo wp_kses_post( wpautop( $item['short_description'] ) ); ?>
						</div>
					<?php endif; ?>

					<div class="aips-product-action-row">
						<div class="aips-price-box">
							<?php if ( ! empty( $item['formatted_price'] ) ) : ?>
								<span class="aips-current-price"><?php echo esc_html( $item['formatted_price'] ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $item['in_stock'] ) ) : ?>
								<span class="aips-stock-badge in-stock"><?php esc_html_e( 'In Stock', 'ai-post-scheduler' ); ?></span>
							<?php endif; ?>
						</div>

						<a href="<?php echo esc_url( $item['add_to_cart_url'] ); ?>"
						   class="aips-btn aips-btn-primary aips-cta-button"
						   target="_blank"
						   rel="<?php echo $item['is_external'] ? 'nofollow sponsored noopener' : 'noopener'; ?>"
						   data-product-id="<?php echo esc_attr( $item['id'] ); ?>"
						   data-product-title="<?php echo esc_attr( $item['title'] ); ?>"
						   data-product-price="<?php echo esc_attr( $item['price'] ); ?>"
						   data-action-type="<?php echo $item['is_external'] ? 'affiliate' : 'add_to_cart'; ?>">
							<?php echo esc_html( $button_text ); ?>
						</a>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render [aips_comparison_table ids="12,34,56" badges="Best Overall,Best Budget,Best Performance"]
	 *
	 * @param array $atts
	 * @return string
	 */
	public function render_comparison_table( $atts ) {
		$atts = shortcode_atts(
			array(
				'ids'    => '',
				'badges' => '',
				'title'  => '',
			),
			$atts,
			'aips_comparison_table'
		);

		if ( empty( $atts['ids'] ) ) {
			return '';
		}

		$ids    = array_map( 'absint', explode( ',', $atts['ids'] ) );
		$badges = array_map( 'trim', explode( ',', $atts['badges'] ) );

		$items = array();
		foreach ( $ids as $index => $id ) {
			$item = $this->resolve_item_data( $id );
			if ( $item ) {
				$item['badge'] = isset( $badges[ $index ] ) ? $badges[ $index ] : '';
				$items[]       = $item;
			}
		}

		if ( empty( $items ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="aips-comparison-wrapper">
			<?php if ( ! empty( $atts['title'] ) ) : ?>
				<h3 class="aips-comparison-title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>

			<div class="aips-table-responsive">
				<table class="aips-comparison-table">
					<thead>
						<tr>
							<th class="aips-col-item"><?php esc_html_e( 'Product', 'ai-post-scheduler' ); ?></th>
							<th class="aips-col-rating"><?php esc_html_e( 'Rating', 'ai-post-scheduler' ); ?></th>
							<th class="aips-col-price"><?php esc_html_e( 'Price', 'ai-post-scheduler' ); ?></th>
							<th class="aips-col-action"><?php esc_html_e( 'Details', 'ai-post-scheduler' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $items as $item ) : ?>
							<tr class="aips-table-row <?php echo ! empty( $item['badge'] ) ? 'aips-has-badge' : ''; ?>" data-product-id="<?php echo esc_attr( $item['id'] ); ?>">
								<td class="aips-product-cell">
									<?php if ( ! empty( $item['badge'] ) ) : ?>
										<span class="aips-table-badge"><?php echo esc_html( $item['badge'] ); ?></span>
									<?php endif; ?>
									<div class="aips-cell-content">
										<?php if ( ! empty( $item['image_url'] ) ) : ?>
											<img src="<?php echo esc_url( $item['image_url'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>" class="aips-thumb-sm" loading="lazy" />
										<?php endif; ?>
										<span class="aips-item-name"><?php echo esc_html( $item['title'] ); ?></span>
									</div>
								</td>
								<td class="aips-rating-cell">
									<span class="aips-stars-sm">★</span> <?php echo esc_html( number_format( (float) $item['rating'], 1 ) ); ?>
								</td>
								<td class="aips-price-cell">
									<strong><?php echo esc_html( $item['formatted_price'] ); ?></strong>
								</td>
								<td class="aips-action-cell">
									<a href="<?php echo esc_url( $item['add_to_cart_url'] ); ?>"
									   class="aips-btn aips-btn-sm aips-cta-button"
									   target="_blank"
									   rel="<?php echo $item['is_external'] ? 'nofollow sponsored noopener' : 'noopener'; ?>"
									   data-product-id="<?php echo esc_attr( $item['id'] ); ?>"
									   data-product-title="<?php echo esc_attr( $item['title'] ); ?>"
									   data-product-price="<?php echo esc_attr( $item['price'] ); ?>"
									   data-action-type="<?php echo $item['is_external'] ? 'affiliate' : 'add_to_cart'; ?>">
										<?php echo esc_html( $item['button_text'] ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render [aips_pros_cons pros="High performance|Great battery life" cons="Expensive|Heavy" title="Our Verdict"]
	 *
	 * @param array $atts
	 * @return string
	 */
	public function render_pros_cons( $atts ) {
		$atts = shortcode_atts(
			array(
				'title' => __( 'Pros & Cons', 'ai-post-scheduler' ),
				'pros'  => '',
				'cons'  => '',
			),
			$atts,
			'aips_pros_cons'
		);

		$pros = array_filter( array_map( 'trim', explode( '|', $atts['pros'] ) ) );
		$cons = array_filter( array_map( 'trim', explode( '|', $atts['cons'] ) ) );

		if ( empty( $pros ) && empty( $cons ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="aips-pros-cons-box">
			<?php if ( ! empty( $atts['title'] ) ) : ?>
				<h4 class="aips-pros-cons-title"><?php echo esc_html( $atts['title'] ); ?></h4>
			<?php endif; ?>
			<div class="aips-pros-cons-columns">
				<?php if ( ! empty( $pros ) ) : ?>
					<div class="aips-pros-col">
						<h5 class="aips-col-header pros-header">
							<span class="aips-icon">✓</span> <?php esc_html_e( 'The Good', 'ai-post-scheduler' ); ?>
						</h5>
						<ul class="aips-list pros-list">
							<?php foreach ( $pros as $pro ) : ?>
								<li><?php echo esc_html( $pro ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $cons ) ) : ?>
					<div class="aips-cons-col">
						<h5 class="aips-col-header cons-header">
							<span class="aips-icon">✗</span> <?php esc_html_e( 'The Bad', 'ai-post-scheduler' ); ?>
						</h5>
						<ul class="aips-list cons-list">
							<?php foreach ( $cons as $con ) : ?>
								<li><?php echo esc_html( $con ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render [aips_faq title="Frequently Asked Questions" items="..." schema="true"]
	 *
	 * @param array $atts
	 * @return string
	 */
	public function render_faq( $atts ) {
		$atts = shortcode_atts(
			array(
				'title'  => __( 'Frequently Asked Questions', 'ai-post-scheduler' ),
				'items'  => '',
				'schema' => 'true',
			),
			$atts,
			'aips_faq'
		);

		$raw_items = trim( $atts['items'] );
		if ( empty( $raw_items ) ) {
			return '';
		}

		$faq_pairs = array();

		// Check if JSON encoded
		$decoded = json_decode( html_entity_decode( $raw_items, ENT_QUOTES ), true );
		if ( is_array( $decoded ) ) {
			$faq_pairs = $decoded;
		} else {
			// Semicolon/Pipe delimited pairs (Q: ... | A: ...)
			$entries = explode( '||', $raw_items );
			foreach ( $entries as $entry ) {
				$parts = explode( '|', $entry );
				if ( count( $parts ) >= 2 ) {
					$faq_pairs[] = array(
						'question' => trim( $parts[0] ),
						'answer'   => trim( $parts[1] ),
					);
				}
			}
		}

		if ( empty( $faq_pairs ) ) {
			return '';
		}

		// Queue for JSON-LD schema
		if ( 'true' === $atts['schema'] ) {
			foreach ( $faq_pairs as $pair ) {
				if ( ! empty( $pair['question'] ) && ! empty( $pair['answer'] ) ) {
					self::$faq_schema_queue[] = array(
						'@type'          => 'Question',
						'name'           => wp_strip_all_tags( $pair['question'] ),
						'acceptedAnswer' => array(
							'@type' => 'Answer',
							'text'  => wp_strip_all_tags( $pair['answer'] ),
						),
					);
				}
			}
		}

		ob_start();
		?>
		<div class="aips-faq-container">
			<?php if ( ! empty( $atts['title'] ) ) : ?>
				<h3 class="aips-faq-main-title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>
			<div class="aips-faq-accordion">
				<?php foreach ( $faq_pairs as $idx => $pair ) : ?>
					<details class="aips-faq-item" <?php echo 0 === $idx ? 'open' : ''; ?>>
						<summary class="aips-faq-question">
							<strong><?php echo esc_html( $pair['question'] ); ?></strong>
						</summary>
						<div class="aips-faq-answer">
							<p><?php echo esc_html( $pair['answer'] ); ?></p>
						</div>
					</details>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render [aips_toc title="Table of Contents"]
	 *
	 * @param array $atts
	 * @return string
	 */
	public function render_toc( $atts ) {
		$atts = shortcode_atts(
			array(
				'title' => __( 'Table of Contents', 'ai-post-scheduler' ),
			),
			$atts,
			'aips_toc'
		);

		return '<div class="aips-toc-container" data-aips-toc="true"><div class="aips-toc-header">' . esc_html( $atts['title'] ) . '</div><nav class="aips-toc-list"></nav></div>';
	}

	/**
	 * Render [aips_cross_sells ids="1,2,3" category="" limit="3"]
	 *
	 * @param array $atts
	 * @return string
	 */
	public function render_cross_sells( $atts ) {
		$atts = shortcode_atts(
			array(
				'ids'      => '',
				'category' => '',
				'limit'    => 3,
				'title'    => __( 'You May Also Like', 'ai-post-scheduler' ),
			),
			$atts,
			'aips_cross_sells'
		);

		$items = array();

		if ( ! empty( $atts['ids'] ) ) {
			$ids = array_map( 'absint', explode( ',', $atts['ids'] ) );
			foreach ( $ids as $id ) {
				$item = $this->resolve_item_data( $id );
				if ( $item ) {
					$items[] = $item;
				}
			}
		} elseif ( ! empty( $atts['category'] ) && $this->wc_adapter->is_active() ) {
			$items = $this->wc_adapter->get_products(
				array(
					'category' => $atts['category'],
					'limit'    => absint( $atts['limit'] ),
				)
			);
		}

		if ( empty( $items ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="aips-cross-sells-container">
			<?php if ( ! empty( $atts['title'] ) ) : ?>
				<h4 class="aips-cross-sells-title"><?php echo esc_html( $atts['title'] ); ?></h4>
			<?php endif; ?>
			<div class="aips-cross-sells-grid">
				<?php foreach ( $items as $item ) : ?>
					<div class="aips-cross-sell-item">
						<?php if ( ! empty( $item['image_url'] ) ) : ?>
							<img src="<?php echo esc_url( $item['image_url'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>" class="aips-thumb-md" loading="lazy" />
						<?php endif; ?>
						<h5 class="aips-item-heading"><?php echo esc_html( $item['title'] ); ?></h5>
						<span class="aips-item-cost"><?php echo esc_html( $item['formatted_price'] ); ?></span>
						<a href="<?php echo esc_url( $item['add_to_cart_url'] ); ?>"
						   class="aips-btn aips-btn-sm aips-cta-button"
						   target="_blank"
						   rel="<?php echo $item['is_external'] ? 'nofollow sponsored noopener' : 'noopener'; ?>"
						   data-product-id="<?php echo esc_attr( $item['id'] ); ?>"
						   data-product-title="<?php echo esc_attr( $item['title'] ); ?>"
						   data-product-price="<?php echo esc_attr( $item['price'] ); ?>"
						   data-action-type="<?php echo $item['is_external'] ? 'affiliate' : 'add_to_cart'; ?>">
							<?php echo esc_html( $item['button_text'] ); ?>
						</a>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render [aips_ftc_disclosure]
	 *
	 * @param array $atts
	 * @return string
	 */
	public function render_ftc_disclosure( $atts ) {
		$default_text = AIPS_Config::get_instance()->get_option(
			'aips_ftc_disclosure_text',
			__( 'This post contains affiliate links. If you purchase through these links, we may earn a small commission at no additional cost to you.', 'ai-post-scheduler' )
		);

		$atts = shortcode_atts(
			array(
				'text' => $default_text,
			),
			$atts,
			'aips_ftc_disclosure'
		);

		return '<div class="aips-affiliate-disclosure"><span class="aips-disclosure-icon">ℹ</span> ' . esc_html( $atts['text'] ) . '</div>';
	}

	/**
	 * Output JSON-LD FAQ Schema in wp_footer if any FAQs were rendered on page.
	 *
	 * @return void
	 */
	public function render_json_ld_schema() {
		if ( empty( self::$faq_schema_queue ) ) {
			return;
		}

		$schema = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => self::$faq_schema_queue,
		);

		echo "\n<!-- AI Post Scheduler FAQ Schema -->\n";
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . "</script>\n";
	}
}
