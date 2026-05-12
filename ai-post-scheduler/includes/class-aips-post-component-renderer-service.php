<?php
/**
 * Post Component Renderer Service
 *
 * @package AI_Post_Scheduler
 * @since 2.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Post_Component_Renderer_Service {

	/**
	 * Render a component to safe HTML.
	 *
	 * @param object                          $component Component row.
	 * @param array<string,mixed>             $rule Rule payload.
	 * @param AIPS_Post_Component_Run_Context $context Runtime context.
	 * @return string
	 */
	public function render_component( $component, array $rule, AIPS_Post_Component_Run_Context $context ) {
		$content_mode = ! empty( $component->content_mode ) ? sanitize_key( (string) $component->content_mode ) : 'html';
		$payload      = '';

		if ( ! empty( $component->content_payload ) ) {
			$payload = (string) $component->content_payload;
		} elseif ( ! empty( $component->content ) ) {
			$payload = (string) $component->content;
		}

		if ( '' === trim( $payload ) ) {
			return '';
		}

		if ( 'shortcode' === $content_mode ) {
			$payload = do_shortcode( $payload );
		} elseif ( 'template' === $content_mode ) {
			$payload = $this->replace_template_tokens( $payload, $context );
		}

		$component_type = ! empty( $component->component_type ) ? sanitize_html_class( (string) $component->component_type ) : 'custom';
		$component_id   = absint( $component->id );
		$placement      = sanitize_html_class( str_replace( ':', '-', (string) ( $rule['placement'] ?? 'end_of_post' ) ) );

		return sprintf(
			'<div class="aips-post-component aips-post-component--%1$s aips-post-component--%2$s" data-aips-component-id="%3$d">%4$s</div>',
			esc_attr( $component_type ),
			esc_attr( $placement ),
			$component_id,
			wp_kses_post( $payload )
		);
	}

	/**
	 * Replace simple template tokens from run context.
	 *
	 * @param string                          $payload Template payload.
	 * @param AIPS_Post_Component_Run_Context $context Runtime context.
	 * @return string
	 */
	private function replace_template_tokens( $payload, AIPS_Post_Component_Run_Context $context ) {
		$replacements = array(
			'{{topic}}'          => (string) $context->get( 'topic', '' ),
			'{{locale}}'         => (string) $context->get( 'locale', '' ),
			'{{region}}'         => (string) $context->get( 'region', '' ),
			'{{author_persona}}' => (string) $context->get( 'author_persona', '' ),
		);

		return strtr( $payload, $replacements );
	}
}
