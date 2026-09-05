<?php
/**
 * Referral Delivery Service
 *
 * Handles automated in-content injection of referral promo ribbons and discount cards,
 * shortcode handling, and Gutenberg block integration.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Referral_Delivery_Service {

	/**
	 * @var AIPS_Referral_Programs_Repository
	 */
	private $repo;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	public function __construct(
		?AIPS_Referral_Programs_Repository $repo = null,
		?AIPS_Config $config = null
	) {
		$container    = AIPS_Container::get_instance();
		$this->repo   = $repo ?: ( $container->has( AIPS_Referral_Programs_Repository::class ) ? $container->make( AIPS_Referral_Programs_Repository::class ) : new AIPS_Referral_Programs_Repository() );
		$this->config = $config ?: AIPS_Config::get_instance();

		$this->init_hooks();
	}

	/**
	 * Register hooks.
	 */
	private function init_hooks() {
		add_filter( 'the_content', array( $this, 'filter_content' ), 16 );
		add_shortcode( 'aips_referral', array( $this, 'render_shortcode' ) );
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Filter content to automatically inject referral discount box if matching.
	 *
	 * @param string $content HTML content.
	 * @return string
	 */
	public function filter_content( $content ) {
		if ( ! $this->config->get_option( 'aips_monetization_enabled', true ) ) {
			return $content;
		}

		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}

		// Don't auto-inject if shortcode or block already present in content
		if ( has_shortcode( $content, 'aips_referral' ) || false !== strpos( $content, 'wp:aips/referral-card' ) || false !== strpos( $content, 'aips-referral-ribbon' ) ) {
			return $content;
		}

		// Check post override
		$disabled = get_post_meta( $post_id, '_aips_disable_referrals', true );
		if ( ! empty( $disabled ) && '1' === (string) $disabled ) {
			return $content;
		}

		$categories = wp_get_post_categories( $post_id );
		$tags       = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );

		$matched = $this->repo->match_programs( $categories, $tags, $content );
		if ( empty( $matched ) ) {
			return $content;
		}

		$program = $matched[0];
		$ribbon_html = $this->render_referral_box( $program, $post_id );

		// Inject around midpoint or after paragraph 3
		$paragraphs = preg_split( '/(<\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( count( $paragraphs ) < 4 ) {
			return $content . "\n" . $ribbon_html;
		}

		$full_paragraphs = array();
		for ( $i = 0; $i < count( $paragraphs ); $i += 2 ) {
			$p_body  = $paragraphs[ $i ];
			$p_close = $paragraphs[ $i + 1 ] ?? '';
			if ( '' !== trim( $p_body ) || '' !== trim( $p_close ) ) {
				$full_paragraphs[] = $p_body . $p_close;
			}
		}

		$total_p = count( $full_paragraphs );
		$target_p = max( 2, (int) floor( $total_p * 0.45 ) );

		$output = '';
		foreach ( $full_paragraphs as $idx => $p_html ) {
			$output .= $p_html;
			if ( ( $idx + 1 ) === $target_p ) {
				$output .= "\n" . $ribbon_html . "\n";
			}
		}

		return $output;
	}

	/**
	 * Render referral promo box / discount ribbon HTML.
	 *
	 * @param array $program Program data.
	 * @param int   $post_id Current post ID.
	 * @return string HTML output.
	 */
	public function render_referral_box( array $program, $post_id = 0 ) {
		$cloaking_pfx = $this->config->get_option( 'aips_link_cloaking_prefix', 'go' );
		$cloaked_url  = home_url( '/' . $cloaking_pfx . '/' . $program['slug'] . '/' );

		if ( $post_id > 0 ) {
			$cloaked_url = add_query_arg( 'post_id', (int) $post_id, $cloaked_url );
		}

		$partner_name = esc_html( $program['partner_name'] );
		$discount     = ! empty( $program['discount_description'] ) ? esc_html( $program['discount_description'] ) : sprintf( esc_html__( 'Special offer from %s', 'ai-post-scheduler' ), $partner_name );
		$promo_code   = ! empty( $program['promo_code'] ) ? esc_attr( $program['promo_code'] ) : '';
		$network      = esc_html( ucfirst( $program['network'] ?? 'Direct' ) );

		ob_start();
		?>
		<div class="aips-referral-ribbon" data-program-id="<?php echo esc_attr( $program['id'] ); ?>" data-post-id="<?php echo esc_attr( $post_id ); ?>">
			<div class="aips-referral-inner">
				<div class="aips-referral-badge-wrap">
					<span class="aips-referral-badge"><?php esc_html_e( 'Partner Offer', 'ai-post-scheduler' ); ?></span>
					<span class="aips-referral-network"><?php echo $network; ?></span>
				</div>
				<div class="aips-referral-content">
					<h4 class="aips-referral-title"><?php echo $partner_name; ?></h4>
					<p class="aips-referral-desc"><?php echo $discount; ?></p>
				</div>
				<div class="aips-referral-actions">
					<?php if ( ! empty( $promo_code ) ) : ?>
						<div class="aips-promo-code-wrap">
							<span class="aips-promo-code"><?php echo esc_html( $promo_code ); ?></span>
							<button type="button" class="aips-btn-copy-code" data-code="<?php echo $promo_code; ?>" title="<?php esc_attr_e( 'Copy discount code to clipboard', 'ai-post-scheduler' ); ?>">
								<span class="aips-copy-text"><?php esc_html_e( 'Copy Code', 'ai-post-scheduler' ); ?></span>
							</button>
						</div>
					<?php endif; ?>
					<a href="<?php echo esc_url( $cloaked_url ); ?>" class="aips-referral-cta" target="_blank" rel="nofollow noopener">
						<?php esc_html_e( 'Claim Offer &rarr;', 'ai-post-scheduler' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Shortcode [aips_referral id="1" or slug="nordvpn"].
	 *
	 * @param array $atts
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'   => 0,
				'slug' => '',
			),
			$atts,
			'aips_referral'
		);

		$program = null;
		if ( ! empty( $atts['id'] ) ) {
			$program = $this->repo->get_by_id( absint( $atts['id'] ) );
		} elseif ( ! empty( $atts['slug'] ) ) {
			$program = $this->repo->get_by_slug( sanitize_title( $atts['slug'] ) );
		}

		if ( ! $program || 'active' !== $program['status'] ) {
			return '';
		}

		return $this->render_referral_box( $program, get_the_ID() ?: 0 );
	}

	/**
	 * Register Gutenberg dynamic block.
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			'aips/referral-card',
			array(
				'api_version'     => 2,
				'render_callback' => array( $this, 'render_block' ),
				'attributes'      => array(
					'programId' => array(
						'type'    => 'number',
						'default' => 0,
					),
				),
			)
		);
	}

	/**
	 * Block render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string HTML.
	 */
	public function render_block( $attributes ) {
		$program_id = absint( $attributes['programId'] ?? 0 );
		if ( ! $program_id ) {
			return '';
		}

		$program = $this->repo->get_by_id( $program_id );
		if ( ! $program || 'active' !== $program['status'] ) {
			return '';
		}

		return $this->render_referral_box( $program, get_the_ID() ?: 0 );
	}
}
