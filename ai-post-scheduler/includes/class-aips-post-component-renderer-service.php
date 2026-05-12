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
	 * @var AIPS_Internal_Links_Repository
	 */
	private $internal_links_repository;

	public function __construct( ?AIPS_Internal_Links_Repository $internal_links_repository = null ) {
		$this->internal_links_repository = $internal_links_repository ?: new AIPS_Internal_Links_Repository();
	}

	/**
	 * Render a component to safe HTML.
	 *
	 * @param object                          $component Component row.
	 * @param array<string,mixed>             $rule Rule payload.
	 * @param AIPS_Post_Component_Run_Context $context Runtime context.
	 * @return string
	 */
	public function render_component( $component, array $rule, AIPS_Post_Component_Run_Context $context ) {
		if ( 'internal_link_pod' === (string) $component->component_type ) {
			$pod = $this->render_internal_link_pod( $component, $context );
			if ( '' !== $pod ) {
				return $pod;
			}
		}

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

	/**
	 * Render a dynamic internal-link pod.
	 *
	 * @param object                          $component Component row.
	 * @param AIPS_Post_Component_Run_Context $context Runtime context.
	 * @return string
	 */
	private function render_internal_link_pod( $component, AIPS_Post_Component_Run_Context $context ) {
		$post_id = absint( $context->get( 'post_id', 0 ) );
		if ( $post_id < 1 ) {
			return $this->render_component_fallback( $component );
		}

		$suggestions = $this->internal_links_repository->get_by_source_post( $post_id, 'accepted' );
		if ( empty( $suggestions ) ) {
			$suggestions = $this->internal_links_repository->get_by_source_post( $post_id, 'pending' );
		}

		$items = array();
		foreach ( array_slice( (array) $suggestions, 0, 3 ) as $suggestion ) {
			$target_post = get_post( (int) $suggestion->target_post_id );
			if ( ! $target_post ) {
				continue;
			}
			$items[] = sprintf(
				'<li><a href="%1$s">%2$s</a></li>',
				esc_url( get_permalink( $target_post->ID ) ),
				esc_html( $target_post->post_title )
			);
		}

		if ( empty( $items ) ) {
			return $this->render_component_fallback( $component );
		}

		return sprintf(
			'<div class="aips-post-component aips-post-component--internal-link-pod" data-aips-component-id="%1$d"><div class="aips-related-links-pod"><h3>%2$s</h3><ul>%3$s</ul></div></div>',
			absint( $component->id ),
			esc_html__( 'Related Reading', 'ai-post-scheduler' ),
			implode( '', $items )
		);
	}

	/**
	 * Render the saved component payload when the dynamic pod has no related links yet.
	 *
	 * @param object $component Component row.
	 * @return string
	 */
	private function render_component_fallback( $component ) {
		$payload = '';

		if ( ! empty( $component->content_payload ) ) {
			$payload = (string) $component->content_payload;
		} elseif ( ! empty( $component->content ) ) {
			$payload = (string) $component->content;
		}

		if ( '' === trim( $payload ) ) {
			return '';
		}

		return sprintf(
			'<div class="aips-post-component aips-post-component--internal-link-pod" data-aips-component-id="%1$d">%2$s</div>',
			absint( $component->id ),
			wp_kses_post( $payload )
		);
	}
}
