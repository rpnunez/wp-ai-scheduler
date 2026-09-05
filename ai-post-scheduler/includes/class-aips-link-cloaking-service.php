<?php
/**
 * Link Cloaking Service
 *
 * Handles custom URL rewrites, safe HTTP 307 redirects,
 * nofollow/noindex headers, dynamic subID tracking parameter decoration,
 * and real-time outbound conversion telemetry.
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
	 * @var AIPS_Referral_Programs_Repository|null
	 */
	private $referral_repo;

	/**
	 * Query variable name used for cloaked links.
	 */
	const QUERY_VAR = 'aips_cloak_slug';

	/**
	 * Canonical subID parameter names per network provider.
	 */
	const DEFAULT_NETWORK_SUBID_PARAMS = array(
		'amazon'     => 'ascsubtag',
		'shareasale' => 'afftrack',
		'cj'         => 'sid',
		'impact'     => 'subId1',
		'awin'       => 'clickref',
		'rakuten'    => 'u1',
		'direct'     => 'subid',
	);

	/**
	 * Constructor with dependency injection.
	 *
	 * @param AIPS_Config|null                            $config
	 * @param AIPS_Affiliate_Links_Repository|null        $affiliate_repo
	 * @param AIPS_Sponsor_Campaigns_Repository|null      $sponsor_repo
	 * @param AIPS_Monetization_Telemetry_Repository|null $telemetry_repo
	 * @param AIPS_Referral_Programs_Repository|null      $referral_repo
	 */
	public function __construct(
		?AIPS_Config $config = null,
		?AIPS_Affiliate_Links_Repository $affiliate_repo = null,
		?AIPS_Sponsor_Campaigns_Repository $sponsor_repo = null,
		?AIPS_Monetization_Telemetry_Repository $telemetry_repo = null,
		?AIPS_Referral_Programs_Repository $referral_repo = null
	) {
		$container            = AIPS_Container::get_instance();
		$this->config         = $config ?: AIPS_Config::get_instance();
		$this->affiliate_repo = $affiliate_repo ?: ( $container->has( AIPS_Affiliate_Links_Repository::class ) ? $container->make( AIPS_Affiliate_Links_Repository::class ) : new AIPS_Affiliate_Links_Repository() );
		$this->sponsor_repo   = $sponsor_repo ?: ( $container->has( AIPS_Sponsor_Campaigns_Repository::class ) ? $container->make( AIPS_Sponsor_Campaigns_Repository::class ) : new AIPS_Sponsor_Campaigns_Repository() );
		$this->telemetry_repo = $telemetry_repo ?: ( $container->has( AIPS_Monetization_Telemetry_Repository::class ) ? $container->make( AIPS_Monetization_Telemetry_Repository::class ) : new AIPS_Monetization_Telemetry_Repository() );

		if ( null !== $referral_repo ) {
			$this->referral_repo = $referral_repo;
		} elseif ( $container->has( AIPS_Referral_Programs_Repository::class ) ) {
			$this->referral_repo = $container->make( AIPS_Referral_Programs_Repository::class );
		} elseif ( class_exists( 'AIPS_Referral_Programs_Repository' ) ) {
			$this->referral_repo = new AIPS_Referral_Programs_Repository();
		} else {
			$this->referral_repo = null;
		}

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

		$slug = sanitize_title( $slug );

		// Resolve post context for telemetry & token replacement
		$post_id = 0;
		if ( ! empty( $_GET['post_id'] ) ) {
			$post_id = absint( $_GET['post_id'] );
		} elseif ( ! empty( $_GET['pid'] ) ) {
			$post_id = absint( $_GET['pid'] );
		} else {
			$referer = wp_get_referer();
			if ( ! empty( $referer ) ) {
				$resolved_id = url_to_postid( $referer );
				if ( $resolved_id > 0 ) {
					$post_id = $resolved_id;
				}
			}
		}

		$post = ( $post_id > 0 ) ? get_post( $post_id ) : null;

		// 1. Resolve Partner Referral Programs
		if ( $this->referral_repo ) {
			$program = $this->referral_repo->get_by_slug( $slug );
			if ( ! empty( $program ) ) {
				$this->handle_referral_redirect( $program, $slug, $post_id, $post );
				return;
			}
		}

		// 2. Resolve Affiliate Links (Legacy / Affiliate Links Inserter)
		$aff_link = $this->affiliate_repo->get_by_slug( $slug );
		if ( $aff_link && ! empty( $aff_link->affiliate_url ) ) {
			$target_url = $this->validate_url( $aff_link->affiliate_url );
			if ( empty( $target_url ) ) {
				$this->handle_malformed_url( $aff_link->affiliate_url, $slug );
				return;
			}

			// Record telemetry (slot_id = 0, campaign_id = 0 for affiliate links)
			$device_type = wp_is_mobile() ? 'mobile' : 'desktop';
			$this->telemetry_repo->record_event(
				0,
				$post_id,
				0,
				'click',
				$device_type,
				1
			);

			$this->emit_headers_and_redirect( $target_url, 307 );
			return;
		}

		// 3. Resolve Sponsor Campaigns if not matched
		$campaigns = $this->sponsor_repo->get_all( false );
		foreach ( $campaigns as $camp ) {
			if ( sanitize_title( $camp->brand_name ) === $slug || sanitize_title( $camp->name ?? '' ) === $slug ) {
				$target_url = $this->validate_url( $camp->target_url );
				if ( empty( $target_url ) ) {
					$this->handle_malformed_url( $camp->target_url, $slug );
					return;
				}

				// Record telemetry (campaign_id = sponsor campaign ID)
				$device_type = wp_is_mobile() ? 'mobile' : 'desktop';
				$this->telemetry_repo->record_event(
					0,
					$post_id,
					(int) $camp->id,
					'click',
					$device_type,
					1
				);

				$this->emit_headers_and_redirect( $target_url, 307 );
				return;
			}
		}

		// 4. If not found in any repository, allow WordPress to handle 404
	}

	/**
	 * Process redirection and telemetry for a matched referral program.
	 *
	 * @param array|object $program Referral program entity.
	 * @param string       $slug    Cloaked slug.
	 * @param int          $post_id Originating post ID.
	 * @param WP_Post|null $post    Post object if found.
	 * @return void
	 */
	private function handle_referral_redirect( $program, $slug, $post_id, $post ) {
		$program_id  = is_array( $program ) ? (int) ( $program['id'] ?? 0 ) : (int) ( $program->id ?? 0 );
		$status      = is_array( $program ) ? (string) ( $program['status'] ?? 'active' ) : (string) ( $program->status ?? 'active' );
		$expiry_date = is_array( $program ) ? (string) ( $program['expiry_date'] ?? '' ) : (string) ( $program->expiry_date ?? '' );
		$raw_url     = is_array( $program ) ? (string) ( $program['referral_url'] ?? '' ) : (string) ( $program->referral_url ?? '' );
		$network     = is_array( $program ) ? (string) ( $program['network_provider'] ?? 'direct' ) : (string) ( $program->network_provider ?? 'direct' );
		$network     = strtolower( trim( $network ) );

		// Edge Case 1: Paused program
		if ( 'active' !== $status ) {
			$this->handle_paused_program( $program, $slug );
			return;
		}

		// Edge Case 2: Expired program
		$today = AIPS_DateTime::now()->format( 'Y-m-d' );
		if ( ! empty( $expiry_date ) && '0000-00-00' !== $expiry_date && $expiry_date < $today ) {
			$this->handle_expired_program( $program, $slug, $expiry_date );
			return;
		}

		// Edge Case 3: Malformed target URL
		$clean_url = $this->validate_url( $raw_url );
		if ( empty( $clean_url ) ) {
			$this->handle_malformed_url( $raw_url, $slug );
			return;
		}

		// Decorate destination URL with network subIDs and tracking tokens
		$destination_url = $this->decorate_referral_url( $clean_url, $network, $slug, $post_id, $post );

		// Final safety check on decorated destination URL
		if ( ! wp_http_validate_url( $destination_url ) ) {
			$this->handle_malformed_url( $destination_url, $slug );
			return;
		}

		// Record outbound conversion telemetry (slot_id = 0, campaign_id = program_id)
		$device_type = wp_is_mobile() ? 'mobile' : 'desktop';
		$this->telemetry_repo->record_event(
			0,
			$post_id,
			$program_id,
			'click',
			$device_type,
			1
		);

		// Emit X-Robots-Tag and issue HTTP 307 temporary redirect
		$this->emit_headers_and_redirect( $destination_url, 307 );
	}

	/**
	 * Decorate referral destination URL with network tracking parameters and token replacement.
	 *
	 * @param string       $url     Validated base URL.
	 * @param string       $network Network provider slug (e.g. 'amazon', 'shareasale', 'cj').
	 * @param string       $slug    Program slug.
	 * @param int          $post_id Originating post ID.
	 * @param WP_Post|null $post    Post object.
	 * @return string Decorated URL.
	 */
	public function decorate_referral_url( $url, $network, $slug, $post_id = 0, $post = null ) {
		$profiles = $this->config->get_option( 'aips_affiliate_network_profiles', array() );
		$profile  = isset( $profiles[ $network ] ) && is_array( $profiles[ $network ] ) ? $profiles[ $network ] : array();

		$tokens = $this->resolve_tokens( $post_id, $post, $slug );

		$query_args = array();

		// 1. Network Profile Affiliate ID / Tag (if configured and not already present)
		$affiliate_id = trim( (string) ( $profile['affiliate_id'] ?? $profile['tag'] ?? $profile['publisher_id'] ?? $profile['media_partner_id'] ?? '' ) );
		if ( ! empty( $affiliate_id ) ) {
			if ( 'amazon' === $network && false === strpos( $url, 'tag=' ) ) {
				$query_args['tag'] = $affiliate_id;
			} elseif ( 'shareasale' === $network && false === strpos( $url, 'u=' ) ) {
				$query_args['u'] = $affiliate_id;
			} elseif ( 'awin' === $network && false === strpos( $url, 'awinaffid=' ) ) {
				$query_args['awinaffid'] = $affiliate_id;
			}
		}

		// 2. Network-specific subID tracking parameter
		$subid_param = ! empty( $profile['subid_param'] ) ? $profile['subid_param'] : ( $profile['param_name'] ?? '' );
		if ( empty( $subid_param ) ) {
			$subid_param = self::DEFAULT_NETWORK_SUBID_PARAMS[ $network ] ?? 'subid';
		}

		$subid_template = ! empty( $profile['subid_template'] ) ? $profile['subid_template'] : '{post_id}';
		$subid_value    = sanitize_text_field( strtr( $subid_template, $tokens ) );

		if ( ! empty( $subid_param ) && '' !== $subid_value ) {
			$query_args[ $subid_param ] = $subid_value;
		}

		// 3. Custom parameters from profile (e.g. UTM tracking tags)
		$custom_params = trim( (string) ( $profile['custom_params'] ?? '' ) );
		if ( ! empty( $custom_params ) ) {
			$interpolated_params = strtr( $custom_params, $tokens );
			wp_parse_str( ltrim( $interpolated_params, '?' ), $parsed_custom );
			if ( is_array( $parsed_custom ) ) {
				foreach ( $parsed_custom as $k => $v ) {
					if ( is_string( $v ) ) {
						$query_args[ sanitize_key( $k ) ] = sanitize_text_field( $v );
					}
				}
			}
		}

		// Apply parameters cleanly preserving anchors and existing parameters
		if ( ! empty( $query_args ) ) {
			$url = add_query_arg( $query_args, $url );
		}

		return $url;
	}

	/**
	 * Build dynamic token replacements dictionary.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post object.
	 * @param string       $slug    Program slug.
	 * @return array
	 */
	public function resolve_tokens( $post_id, $post, $slug ) {
		$post_id_str   = ( $post_id > 0 ) ? (string) $post_id : '0';
		$slug_str      = sanitize_title( $slug );
		$date_compact  = AIPS_DateTime::now()->format( 'Ymd' );
		$date_iso      = AIPS_DateTime::now()->format( 'Y-m-d' );
		$author_id_str = ( $post && ! empty( $post->post_author ) ) ? (string) $post->post_author : '0';

		$category_str = 'general';
		if ( $post_id > 0 ) {
			$categories = get_the_category( $post_id );
			if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
				$category_str = sanitize_title( $categories[0]->slug );
			}
		}

		return array(
			'{post_id}'      => $post_id_str,
			'{slug}'         => $slug_str,
			'{date}'         => $date_compact,
			'{author_id}'    => $author_id_str,
			'{category}'     => $category_str,
			// Aliases
			'{program_slug}' => $slug_str,
			'{post_slug}'    => ( $post && ! empty( $post->post_name ) ) ? sanitize_title( $post->post_name ) : '',
			'{date_iso}'     => $date_iso,
		);
	}

	/**
	 * Validate URL and auto-correct missing scheme if applicable.
	 *
	 * @param string $url Raw URL.
	 * @return string Validated URL or empty string.
	 */
	public function validate_url( $url ) {
		$url = trim( (string) $url );
		if ( empty( $url ) ) {
			return '';
		}

		// If protocol is missing, attempt prepending https://
		if ( ! preg_match( '#^[a-z]+://#i', $url ) ) {
			$url = 'https://' . ltrim( $url, '/' );
		}

		// Validate HTTP/HTTPS protocol and syntax
		$validated = wp_http_validate_url( $url );
		return $validated ? esc_url_raw( $validated ) : '';
	}

	/**
	 * Emit security/SEO headers and perform safe HTTP 307 temporary redirect.
	 *
	 * @param string $url         Destination URL.
	 * @param int    $status_code HTTP status code (307).
	 * @return void
	 */
	public function emit_headers_and_redirect( $url, $status_code = 307 ) {
		if ( ! headers_sent() ) {
			header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		}

		wp_redirect( esc_url_raw( $url ), $status_code );
		exit;
	}

	/**
	 * Handle paused referral program edge case.
	 *
	 * @param array|object $program Referral program.
	 * @param string       $slug    Program slug.
	 * @return void
	 */
	private function handle_paused_program( $program, $slug ) {
		if ( class_exists( 'AIPS_Logger' ) ) {
			AIPS_Logger::get_instance()->warning(
				sprintf( 'Link cloaking: Referral program "%s" is paused.', $slug )
			);
		}

		/**
		 * Filter fallback URL for paused referral programs.
		 * Default is site homepage. Return empty or false to trigger 404.
		 *
		 * @param string       $fallback_url Fallback URL.
		 * @param array|object $program      Program entity.
		 * @param string       $slug         Program slug.
		 */
		$fallback_url = apply_filters( 'aips_referral_paused_fallback_url', home_url( '/' ), $program, $slug );

		if ( ! empty( $fallback_url ) && wp_http_validate_url( $fallback_url ) ) {
			$this->emit_headers_and_redirect( $fallback_url, 307 );
		}
	}

	/**
	 * Handle expired referral program edge case.
	 *
	 * @param array|object $program     Referral program.
	 * @param string       $slug        Program slug.
	 * @param string       $expiry_date Expiration date string.
	 * @return void
	 */
	private function handle_expired_program( $program, $slug, $expiry_date ) {
		if ( class_exists( 'AIPS_Logger' ) ) {
			AIPS_Logger::get_instance()->warning(
				sprintf( 'Link cloaking: Referral program "%s" expired on %s.', $slug, $expiry_date )
			);
		}

		/**
		 * Filter fallback URL for expired referral programs.
		 * Default is site homepage. Return empty or false to trigger 404.
		 *
		 * @param string       $fallback_url Fallback URL.
		 * @param array|object $program      Program entity.
		 * @param string       $slug         Program slug.
		 */
		$fallback_url = apply_filters( 'aips_referral_expired_fallback_url', home_url( '/' ), $program, $slug );

		if ( ! empty( $fallback_url ) && wp_http_validate_url( $fallback_url ) ) {
			$this->emit_headers_and_redirect( $fallback_url, 307 );
		}
	}

	/**
	 * Handle malformed target URL edge case.
	 *
	 * @param string $raw_url Malformed URL.
	 * @param string $slug    Program slug.
	 * @return void
	 */
	private function handle_malformed_url( $raw_url, $slug ) {
		if ( class_exists( 'AIPS_Logger' ) ) {
			AIPS_Logger::get_instance()->error(
				sprintf( 'Link cloaking: Malformed referral target URL "%s" for slug "%s".', $raw_url, $slug )
			);
		}

		/**
		 * Filter fallback URL for malformed referral links.
		 * Default is site homepage.
		 *
		 * @param string $fallback_url Fallback URL.
		 * @param string $raw_url      Raw invalid URL.
		 * @param string $slug         Program slug.
		 */
		$fallback_url = apply_filters( 'aips_referral_malformed_url_fallback', home_url( '/' ), $raw_url, $slug );

		if ( ! empty( $fallback_url ) && wp_http_validate_url( $fallback_url ) ) {
			$this->emit_headers_and_redirect( $fallback_url, 307 );
		}
	}

	/**
	 * Build a cloaked URL for a given slug or identifier.
	 *
	 * @param string    $slug_or_tag     Cloaked slug.
	 * @param int|array $post_id_or_args Optional. Originating Post ID or associative array of query args.
	 * @return string Cloaked URL.
	 */
	public function get_cloaked_url( $slug_or_tag, $post_id_or_args = 0 ) {
		if ( ! $this->config->get_option( 'aips_link_cloaking_enabled', true ) ) {
			return '';
		}

		$prefix = sanitize_title( $this->config->get_option( 'aips_link_cloaking_prefix', 'go' ) );
		if ( empty( $prefix ) ) {
			$prefix = 'go';
		}

		$slug = sanitize_title( $slug_or_tag );
		$url  = home_url( '/' . $prefix . '/' . $slug . '/' );

		if ( is_numeric( $post_id_or_args ) && (int) $post_id_or_args > 0 ) {
			$url = add_query_arg( 'post_id', (int) $post_id_or_args, $url );
		} elseif ( is_array( $post_id_or_args ) && ! empty( $post_id_or_args ) ) {
			$url = add_query_arg( $post_id_or_args, $url );
		}

		return $url;
	}
}
