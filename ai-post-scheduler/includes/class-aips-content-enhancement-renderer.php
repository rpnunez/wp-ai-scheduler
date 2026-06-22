<?php
/**
 * Content Enhancement Renderer
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Content_Enhancement_Renderer
 */
class AIPS_Content_Enhancement_Renderer {

	/**
	 * @var AIPS_Content_Enhancement_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Content_Enhancement_Repository|null $repository Optional repository override.
	 */
	public function __construct( ?AIPS_Content_Enhancement_Repository $repository = null ) {
		$this->repository = $repository ?: new AIPS_Content_Enhancement_Repository();
	}

	/**
	 * Register frontend rendering hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'aips_ce_tool', array( $this, 'render_shortcode' ) );
		add_filter( 'the_content', array( $this, 'replace_content_placeholders' ), 12 );
	}

	/**
	 * Render the content enhancement shortcode.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'slug' => '',
			),
			(array) $atts,
			'aips_ce_tool'
		);

		$slug = sanitize_title( $atts['slug'] ?? '' );
		if ( '' === $slug ) {
			return $this->fallback( '', __( 'Content enhancement is missing a slug.', 'ai-post-scheduler' ) );
		}

		return $this->render_by_slug( $slug );
	}

	/**
	 * Replace known placeholders in post content.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function replace_content_placeholders( string $content ): string {
		$replaced = preg_replace_callback( '/\{\{aips_enhancement:([a-z0-9_-]+)\}\}/i', function( $matches ) {
			return $this->render_by_slug( $matches[1] );
		}, $content );

		return is_string( $replaced ) ? $replaced : $content;
	}

	/**
	 * Render an enhancement by slug.
	 *
	 * @param string $slug Enhancement slug.
	 * @return string
	 */
	public function render_by_slug( string $slug ): string {
		$slug        = sanitize_title( $slug );
		$enhancement = $this->repository->find_by_slug( $slug );

		if ( ! $enhancement ) {
			return $this->fallback( $slug, __( 'Content enhancement is unavailable.', 'ai-post-scheduler' ) );
		}

		return $this->render( $enhancement );
	}

	/**
	 * Render deterministic markup for an enhancement placeholder.
	 *
	 * @param array<string, mixed> $enhancement Enhancement record.
	 * @return string
	 */
	public function render( array $enhancement ): string {
		$slug       = sanitize_title( $enhancement['slug'] ?? '' );
		$name       = sanitize_text_field( $enhancement['name'] ?? '' );
		$type       = sanitize_key( $enhancement['type'] ?? 'embed' );
		$provider   = sanitize_key( $enhancement['provider'] ?? 'custom' );
		$disclosure = sanitize_textarea_field( $enhancement['disclosure_text'] ?? '' );
		$cta        = sanitize_text_field( $enhancement['cta_text'] ?? '' );
		$url        = esc_url_raw( $enhancement['endpoint_url'] ?? '' );

		if ( ! empty( $enhancement['is_active'] ) !== true ) {
			return $this->fallback( $slug, __( 'Content enhancement is disabled.', 'ai-post-scheduler' ) );
		}

		if ( $this->is_fallback_context() ) {
			return $this->fallback( $slug, __( 'Content enhancement is not available in this format.', 'ai-post-scheduler' ) );
		}

		if ( '' === $url ) {
			return $this->fallback( $slug, __( 'Content enhancement URL is missing.', 'ai-post-scheduler' ) );
		}

		if ( ! $this->is_allowed_provider_url( $url, $provider ) ) {
			return $this->fallback( $slug, __( 'Content enhancement provider is blocked.', 'ai-post-scheduler' ) );
		}

		$classes = 'aips-content-enhancement aips-content-enhancement--' . sanitize_html_class( $type );
		$html    = '<div class="' . esc_attr( $classes ) . '" data-aips-content-enhancement="' . esc_attr( $slug ) . '" data-enhancement-type="' . esc_attr( $type ) . '">';

		if ( $name ) {
			$html .= '<strong class="aips-content-enhancement__title">' . esc_html( $name ) . '</strong>';
		}

		if ( $disclosure ) {
			$html .= '<p class="aips-content-enhancement__disclosure">' . esc_html( $disclosure ) . '</p>';
		}

		$html .= '<div class="aips-content-enhancement__embed">';
		$html .= '<iframe src="' . esc_url( $url ) . '" title="' . esc_attr( $name ? $name : __( 'Content enhancement', 'ai-post-scheduler' ) ) . '" loading="lazy" sandbox="allow-forms allow-popups allow-popups-to-escape-sandbox allow-same-origin allow-scripts"></iframe>';
		$html .= '</div>';

		if ( $cta ) {
			$html .= '<p><a class="aips-content-enhancement__cta" href="' . esc_url( $url ) . '" rel="nofollow sponsored noopener" target="_blank">' . esc_html( $cta ) . '</a></p>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Determine whether the current request should receive fallback markup.
	 *
	 * @return bool
	 */
	private function is_fallback_context(): bool {
		if ( function_exists( 'is_feed' ) && is_feed() ) {
			return true;
		}

		if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
			return true;
		}

		return false;
	}

	/**
	 * Check whether a provider URL is explicitly allowed.
	 *
	 * @param string $url      URL to validate.
	 * @param string $provider Enhancement provider.
	 * @return bool
	 */
	private function is_allowed_provider_url( string $url, string $provider ): bool {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}

		$host    = strtolower( $host );
		$domains = AIPS_Config::get_instance()->get_option( 'aips_content_enhancement_provider_domains', array() );
		$allowed = array();

		if ( is_array( $domains ) ) {
			if ( isset( $domains[ $provider ] ) && is_array( $domains[ $provider ] ) ) {
				$allowed = $domains[ $provider ];
			} else {
				$allowed = $domains;
			}
		}

		foreach ( $allowed as $domain ) {
			if ( is_array( $domain ) ) {
				continue;
			}

			$domain = strtolower( preg_replace( '/^https?:\/\//', '', trim( (string) $domain ) ) );
			$domain = strtok( $domain, '/:' );

			if ( $domain && ( $host === $domain || str_ends_with( $host, '.' . $domain ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build safe fallback markup.
	 *
	 * @param string $slug    Enhancement slug.
	 * @param string $message Fallback message.
	 * @return string
	 */
	private function fallback( string $slug, string $message ): string {
		return '<div class="aips-content-enhancement aips-content-enhancement--fallback" data-aips-content-enhancement="' . esc_attr( sanitize_title( $slug ) ) . '"><p>' . esc_html( $message ) . '</p></div>';
	}
}
