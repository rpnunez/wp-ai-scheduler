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
	 * Render a monetization template partial.
	 *
	 * @param string $slug Partial file slug inside templates/partials/monetization/.
	 * @param array  $args Variables passed to the partial template.
	 * @return string Rendered HTML.
	 */
	public function render_partial( $slug, $args = array() ) {
		$file = AIPS_PLUGIN_DIR . 'templates/partials/monetization/' . sanitize_file_name( $slug ) . '.php';
		if ( ! file_exists( $file ) ) {
			return '';
		}

		extract( $args, EXTR_SKIP );
		ob_start();
		include $file;
		return ob_get_clean();
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

		return $this->render_partial(
			'product-card',
			array(
				'item'        => $item,
				'badge'       => $badge,
				'layout'      => $layout,
				'button_text' => $button_text,
			)
		);
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

		return $this->render_partial(
			'comparison-table',
			array(
				'items' => $items,
				'title' => sanitize_text_field( $atts['title'] ),
			)
		);
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

		return $this->render_partial(
			'pros-cons',
			array(
				'title' => sanitize_text_field( $atts['title'] ),
				'pros'  => $pros,
				'cons'  => $cons,
			)
		);
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

		return $this->render_partial(
			'faq',
			array(
				'title'     => sanitize_text_field( $atts['title'] ),
				'faq_pairs' => $faq_pairs,
			)
		);
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

		return $this->render_partial(
			'toc',
			array(
				'title' => sanitize_text_field( $atts['title'] ),
			)
		);
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

		return $this->render_partial(
			'cross-sells',
			array(
				'title' => sanitize_text_field( $atts['title'] ),
				'items' => $items,
			)
		);
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

		return $this->render_partial(
			'ftc-disclosure',
			array(
				'text' => sanitize_text_field( $atts['text'] ),
			)
		);
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
