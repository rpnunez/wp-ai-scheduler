<?php
/**
 * Ad Frontend Integration
 *
 * Handles dynamic in-content ad injection via the_content filter,
 * shortcode handling, and public script/style asset enqueuing.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Ad_Frontend {

	/**
	 * @var AIPS_Ad_Slots_Repository
	 */
	private $slots_repo;

	/**
	 * @var AIPS_Sponsor_Campaigns_Repository
	 */
	private $campaigns_repo;

	/**
	 * @var AIPS_Ad_Injection_Service
	 */
	private $injection_service;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	public function __construct(
		?AIPS_Ad_Slots_Repository $slots_repo = null,
		?AIPS_Sponsor_Campaigns_Repository $campaigns_repo = null,
		?AIPS_Ad_Injection_Service $injection_service = null,
		?AIPS_Config $config = null
	) {
		$container               = AIPS_Container::get_instance();
		$this->slots_repo        = $slots_repo ?: ( $container->has( AIPS_Ad_Slots_Repository::class ) ? $container->make( AIPS_Ad_Slots_Repository::class ) : new AIPS_Ad_Slots_Repository() );
		$this->campaigns_repo    = $campaigns_repo ?: ( $container->has( AIPS_Sponsor_Campaigns_Repository::class ) ? $container->make( AIPS_Sponsor_Campaigns_Repository::class ) : new AIPS_Sponsor_Campaigns_Repository() );
		$this->injection_service = $injection_service ?: ( $container->has( AIPS_Ad_Injection_Service::class ) ? $container->make( AIPS_Ad_Injection_Service::class ) : new AIPS_Ad_Injection_Service() );
		$this->config            = $config ?: AIPS_Config::get_instance();

		$this->init_hooks();
	}

	/**
	 * Initialize frontend hooks.
	 */
	private function init_hooks() {
		add_filter( 'the_content', array( $this, 'filter_content' ), 15 );
		add_shortcode( 'aips_ad', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue public styles and telemetry tracking scripts.
	 */
	public function enqueue_assets() {
		if ( ! $this->config->get_option( 'aips_monetization_enabled', true ) ) {
			return;
		}

		if ( ! is_singular( 'post' ) ) {
			return;
		}

		wp_enqueue_style(
			'aips-monetization-frontend',
			AIPS_PLUGIN_URL . 'assets/css/monetization-frontend.css',
			array(),
			AIPS_VERSION
		);

		if ( $this->config->get_option( 'aips_ad_telemetry_enabled', true ) ) {
			wp_enqueue_script(
				'aips-monetization-frontend',
				AIPS_PLUGIN_URL . 'assets/js/monetization-frontend.js',
				array(),
				AIPS_VERSION,
				true
			);

			wp_localize_script(
				'aips-monetization-frontend',
				'aipsMonetizationConfig',
				array(
					'restUrl'         => esc_url_raw( rest_url( 'aips/v1/monetization/track' ) ),
					'nonce'           => wp_create_nonce( 'wp_rest' ),
					'ga4Enabled'      => (bool) $this->config->get_option( 'aips_ad_ga4_datalayer_enabled', true ),
					'telemetryEnabled'=> true,
				)
			);
		}
	}

	/**
	 * Filter post content to insert ad units dynamically.
	 *
	 * @param string $content
	 * @return string
	 */
	public function filter_content( $content ) {
		if ( ! $this->config->get_option( 'aips_monetization_enabled', true ) ) {
			return $content;
		}

		// Only filter singular post main query
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}

		// Check post override: disable all ads
		$disabled = get_post_meta( $post_id, '_aips_disable_ads', true );
		if ( ! empty( $disabled ) && '1' === (string) $disabled ) {
			return $content;
		}

		// Check suppressed slots
		$suppressed_meta = get_post_meta( $post_id, '_aips_suppressed_slots', true );
		$suppressed_ids  = is_array( $suppressed_meta ) ? array_map( 'absint', $suppressed_meta ) : array();

		// Fetch active runtime slots
		$slots = $this->slots_repo->get_active_runtime_slots();
		if ( empty( $slots ) ) {
			return $content;
		}

		// Check sponsor campaign assignment
		$campaign = null;
		$campaign_id = (int) get_post_meta( $post_id, '_aips_sponsor_campaign_id', true );
		if ( $campaign_id > 0 ) {
			$campaign = $this->campaigns_repo->get_by_id( $campaign_id );
		}

		// Auto-match sponsor campaign if not explicitly assigned
		if ( ! $campaign ) {
			$categories = wp_get_post_categories( $post_id );
			$tags       = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );
			$campaign   = $this->campaigns_repo->match_campaign( $tags, $categories );
		}

		return $this->injection_service->inject_runtime_ads( $content, $slots, $post_id, $campaign, $suppressed_ids );
	}

	/**
	 * Render [aips_ad slot="1" or slot="name"] shortcode.
	 *
	 * @param array $atts
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'slot' => 0,
				'id'   => 0,
			),
			$atts,
			'aips_ad'
		);

		$slot_id = absint( $atts['id'] ?: $atts['slot'] );
		$slot    = null;

		if ( $slot_id > 0 ) {
			$slot = $this->slots_repo->get_by_id( $slot_id );
		} else {
			// Find by name if non-numeric
			$all_slots = $this->slots_repo->get_all();
			foreach ( $all_slots as $s ) {
				if ( strtolower( $s->name ) === strtolower( (string) $atts['slot'] ) ) {
					$slot = $s;
					break;
				}
			}
		}

		if ( ! $slot || 'active' !== $slot->status ) {
			return '';
		}

		$post_id  = get_the_ID() ?: 0;
		$campaign = null;
		if ( $post_id > 0 ) {
			$campaign_id = (int) get_post_meta( $post_id, '_aips_sponsor_campaign_id', true );
			if ( $campaign_id > 0 ) {
				$campaign = $this->campaigns_repo->get_by_id( $campaign_id );
			}
		}

		return $this->injection_service->render_ad_slot( $slot, $post_id, $campaign );
	}
}
