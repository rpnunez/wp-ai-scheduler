<?php
/**
 * Link Cloaking Service
 *
 * Handles custom URL rewrites, safe HTTP 307 redirects,
 * nofollow/noindex headers, and real-time outbound conversion telemetry.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Link_Cloaking_Service {

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * @var AIPS_Affiliate_Links_Repository
	 */
	private $affiliate_repo;

	/**
	 * @var AIPS_Sponsor_Campaigns_Repository
	 */
	private $sponsor_repo;

	/**
	 * @var AIPS_Monetization_Telemetry_Repository
	 */
	private $telemetry_repo;

	/**
	 * Query variable name used for cloaked links.
	 */
	const QUERY_VAR = 'aips_cloak_slug';

	public function __construct(
		?AIPS_Config $config = null,
		?AIPS_Affiliate_Links_Repository $affiliate_repo = null,
		?AIPS_Sponsor_Campaigns_Repository $sponsor_repo = null,
		?AIPS_Monetization_Telemetry_Repository $telemetry_repo = null
	) {
		$container            = AIPS_Container::get_instance();
		$this->config         = $config ?: AIPS_Config::get_instance();
		$this->affiliate_repo = $affiliate_repo ?: ( $container->has( AIPS_Affiliate_Links_Repository::class ) ? $container->make( AIPS_Affiliate_Links_Repository::class ) : new AIPS_Affiliate_Links_Repository() );
		$this->sponsor_repo   = $sponsor_repo ?: ( $container->has( AIPS_Sponsor_Campaigns_Repository::class ) ? $container->make( AIPS_Sponsor_Campaigns_Repository::class ) : new AIPS_Sponsor_Campaigns_Repository() );
		$this->telemetry_repo = $telemetry_repo ?: ( $container->has( AIPS_Monetization_Telemetry_Repository::class ) ? $container->make( AIPS_Monetization_Telemetry_Repository::class ) : new AIPS_Monetization_Telemetry_Repository() );

		$this->init_hooks();
	}

	/**
	 * Initialize rewrite and redirection hooks.
	 */
	public function init_hooks() {
		add_action( 'init', array( $this, 'register_rewrites' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_redirect' ) );
	}

	/**
	 * Register cloaking rewrite rule based on configured prefix.
	 */
	public function register_rewrites() {
		if ( ! $this->config->get_option( 'aips_link_cloaking_enabled', true ) ) {
			return;
		}

		$prefix = sanitize_title( $this->config->get_option( 'aips_link_cloaking_prefix', 'go' ) );
		if ( empty( $prefix ) ) {
			$prefix = 'go';
		}

		add_rewrite_rule(
			'^' . preg_quote( $prefix, '/' ) . '/([^/]+)/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * Register custom query var with WordPress.
	 *
	 * @param string[] $vars
	 * @return string[]
	 */
	public function register_query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Handle incoming cloaked link request during template_redirect.
	 */
	public function handle_redirect() {
		$slug = get_query_var( self::QUERY_VAR );
		if ( empty( $slug ) ) {
			return;
		}

		if ( ! $this->config->get_option( 'aips_link_cloaking_enabled', true ) ) {
			return;
		}

		$slug       = sanitize_title( $slug );
		$target_url = '';
		$label      = $slug;

		// 1. Check Affiliate Links
		$aff_link = $this->affiliate_repo->get_by_slug( $slug );
		if ( $aff_link && ! empty( $aff_link->affiliate_url ) ) {
			$target_url = $aff_link->affiliate_url;
			$label      = $aff_link->label ?: $aff_link->tag;
		}

		// 2. Check Sponsor Campaigns if not matched
		if ( empty( $target_url ) ) {
			$campaigns = $this->sponsor_repo->get_all( false );
			foreach ( $campaigns as $camp ) {
				if ( sanitize_title( $camp->brand_name ) === $slug || sanitize_title( $camp->name ?? '' ) === $slug ) {
					$target_url = $camp->target_url;
					$label      = $camp->brand_name;
					break;
				}
			}
		}

		if ( empty( $target_url ) ) {
			// If not found, let WordPress handle normal 404
			return;
		}

		// Record outbound conversion telemetry
		$this->telemetry_repo->record_event(
			0,
			0,
			0,
			'click',
			wp_is_mobile() ? 'mobile' : 'desktop',
			1
		);

		// Prevent search engine indexation of redirects
		if ( ! headers_sent() ) {
			header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		}

		// Issue HTTP 307 Temporary Redirect (prevents aggressive browser caching of tracking cookies)
		wp_redirect( esc_url_raw( $target_url ), 307 );
		exit;
	}

	/**
	 * Build a cloaked URL for a given slug or identifier.
	 *
	 * @param string $slug_or_tag
	 * @return string
	 */
	public function get_cloaked_url( $slug_or_tag ) {
		if ( ! $this->config->get_option( 'aips_link_cloaking_enabled', true ) ) {
			return '';
		}

		$prefix = sanitize_title( $this->config->get_option( 'aips_link_cloaking_prefix', 'go' ) );
		if ( empty( $prefix ) ) {
			$prefix = 'go';
		}

		$slug = sanitize_title( $slug_or_tag );
		return home_url( '/' . $prefix . '/' . $slug . '/' );
	}
}
