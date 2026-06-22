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
	 * Render deterministic markup for an enhancement placeholder.
	 *
	 * @param array<string, mixed> $enhancement Enhancement record.
	 * @return string
	 */
	public function render( array $enhancement ): string {
		$name       = $enhancement['name'] ?? '';
		$type       = $enhancement['type'] ?? 'embed';
		$disclosure = $enhancement['disclosure_text'] ?? '';
		$cta        = $enhancement['cta_text'] ?? '';
		$url        = $enhancement['endpoint_url'] ?? '';

		$classes = 'aips-content-enhancement aips-content-enhancement--' . sanitize_html_class( $type );
		$html    = '<div class="' . esc_attr( $classes ) . '" data-enhancement-type="' . esc_attr( $type ) . '">';
		$html   .= '<strong class="aips-content-enhancement__title">' . esc_html( $name ) . '</strong>';

		if ( $disclosure ) {
			$html .= '<p class="aips-content-enhancement__disclosure">' . esc_html( $disclosure ) . '</p>';
		}

		if ( $url && $cta ) {
			$html .= '<p><a class="aips-content-enhancement__cta" href="' . esc_url( $url ) . '" rel="nofollow sponsored noopener" target="_blank">' . esc_html( $cta ) . '</a></p>';
		}

		$html .= '</div>';

		return $html;
	}
}
