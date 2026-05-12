<?php
/**
 * Content Component Injection Service
 *
 * @package AI_Post_Scheduler
 * @since 2.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Content_Component_Injection_Service {

	/**
	 * @var AIPS_Content_Component_Matcher_Service
	 */
	private $matcher;

	/**
	 * @var AIPS_Content_Component_Renderer_Service
	 */
	private $renderer;

	/**
	 * @var AIPS_Content_Component_Fingerprint_Service
	 */
	private $fingerprints;

	/**
	 * @var AIPS_Content_Component_Analytics_Repository
	 */
	private $analytics_repository;

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * @var string
	 */
	private $injections_table;

	public function __construct(
		?AIPS_Content_Component_Matcher_Service $matcher = null,
		?AIPS_Content_Component_Renderer_Service $renderer = null,
		?AIPS_Content_Component_Fingerprint_Service $fingerprints = null,
		?AIPS_Content_Component_Analytics_Repository $analytics_repository = null
	) {
		global $wpdb;
		$this->wpdb                 = $wpdb;
		$this->injections_table     = $wpdb->prefix . 'aips_content_component_injections';
		$this->matcher              = $matcher ?: new AIPS_Content_Component_Matcher_Service();
		$this->renderer             = $renderer ?: new AIPS_Content_Component_Renderer_Service();
		$this->fingerprints         = $fingerprints ?: new AIPS_Content_Component_Fingerprint_Service();
		$this->analytics_repository = $analytics_repository ?: new AIPS_Content_Component_Analytics_Repository();
	}

	/**
	 * Build the resolved plan and inject it into the content string.
	 *
	 * @param string                          $content Raw post content.
	 * @param AIPS_Content_Component_Run_Context $context Runtime context.
	 * @param array<string,mixed>             $options Options.
	 * @return array<string,mixed>
	 */
	public function prepare_content( $content, AIPS_Content_Component_Run_Context $context, array $options = array() ) {
		if ( ! AIPS_Config::get_instance()->is_feature_enabled( 'content_components_engine', true ) ) {
			return array(
				'content' => (string) $content,
				'plan'    => array(),
				'base'    => (string) $content,
			);
		}

		$started_at   = microtime( true );
		$base_content = ! empty( $options['strip_existing_markers'] )
			? $this->strip_injected_components( (string) $content )
			: (string) $content;

		$matches = $this->matcher->resolve_plan( $context );
		$plan    = array();

		foreach ( $matches as $match ) {
			$rendered = $this->renderer->render_component( $match['component'], $match['rule'], $context );
			if ( '' === trim( $rendered ) ) {
				continue;
			}

			$placement = (string) ( $match['rule']['placement'] ?? 'end_of_post' );
			$hash      = $this->fingerprints->generate( (int) $match['component']->id, $placement, $rendered );

			$plan[] = array(
				'component_id' => (int) $match['component']->id,
				'placement'    => $placement,
				'hash'         => $hash,
				'rendered'     => $this->wrap_with_markers( (int) $match['component']->id, $hash, $rendered ),
			);
		}

		$result = array(
			'content' => $this->apply_plan( $base_content, $plan ),
			'plan'    => $plan,
			'base'    => $base_content,
		);

		if ( AIPS_Telemetry::is_enabled() ) {
			AIPS_Telemetry::instance()->add_event(
				'content_components',
				array(
					'type'         => 'prepare_content',
					'plan_size'    => count( $plan ),
					'elapsed_ms'   => round( ( microtime( true ) - $started_at ) * 1000, 2 ),
					'post_id'      => (int) $context->get( 'post_id', 0 ),
					'is_dry_run'   => (bool) $context->get( 'is_dry_run', false ),
				)
			);
		}

		return $result;
	}

	/**
	 * Prepare content for a single ad-hoc component/rule pair.
	 *
	 * @param string                          $content Base content.
	 * @param object                          $component Component-like object.
	 * @param array<string,mixed>             $rule Normalized Phase 1 rule record.
	 * @param AIPS_Content_Component_Run_Context $context Runtime context.
	 * @param array<string,mixed>             $options Options.
	 * @return array<string,mixed>
	 */
	public function prepare_manual_component( $content, $component, array $rule, AIPS_Content_Component_Run_Context $context, array $options = array() ) {
		if ( ! AIPS_Config::get_instance()->is_feature_enabled( 'content_components_engine', true ) ) {
			return array(
				'content' => (string) $content,
				'plan'    => array(),
				'base'    => (string) $content,
			);
		}

		$base_content = ! empty( $options['strip_existing_markers'] )
			? $this->strip_injected_components( (string) $content )
			: (string) $content;

		$rendered = $this->renderer->render_component( $component, $rule, $context );
		if ( '' === trim( $rendered ) ) {
			return array(
				'content' => $base_content,
				'plan'    => array(),
				'base'    => $base_content,
			);
		}

		$placement = (string) ( $rule['placement'] ?? 'end_of_post' );
		$hash      = $this->fingerprints->generate( (int) $component->id, $placement, $rendered );
		$plan      = array(
			array(
				'component_id' => (int) $component->id,
				'placement'    => $placement,
				'hash'         => $hash,
				'rendered'     => $this->wrap_with_markers( (int) $component->id, $hash, $rendered ),
			),
		);

		return array(
			'content' => $this->apply_plan( $base_content, $plan ),
			'plan'    => $plan,
			'base'    => $base_content,
		);
	}

	/**
	 * Persist one injection trace row per plan item.
	 *
	 * @param int    $post_id Post ID.
	 * @param array  $plan Resolved plan.
	 * @param string $run_id Correlation or run ID.
	 * @param bool   $is_regeneration Whether this came from regeneration.
	 * @return void
	 */
	public function record_injections( $post_id, array $plan, $run_id = '', $is_regeneration = false ) {
		$post_id = absint( $post_id );
		if ( $post_id < 1 || empty( $plan ) ) {
			return;
		}

		$timestamp = AIPS_DateTime::now()->timestamp();
		$run_id    = sanitize_text_field( (string) $run_id );

		foreach ( $plan as $item ) {
			$component_id = absint( $item['component_id'] ?? 0 );
			if ( $component_id < 1 ) {
				continue;
			}

			$this->wpdb->insert(
				$this->injections_table,
				array(
					'post_id'             => $post_id,
					'component_id'        => $component_id,
					'run_id'              => $run_id,
					'placement_resolved'  => sanitize_text_field( (string) ( $item['placement'] ?? 'end_of_post' ) ),
					'hash'                => sanitize_text_field( (string) ( $item['hash'] ?? '' ) ),
					'inserted_at'         => $timestamp,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%d' )
			);

			$this->analytics_repository->record_injection( $component_id, $is_regeneration );
		}
	}

	/**
	 * Parse saved marker comments from content and store trace rows.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content Saved post content.
	 * @param string $run_id Correlation or run ID.
	 * @param bool   $is_regeneration Whether this content save is a reinjection.
	 * @return void
	 */
	public function record_injections_from_content( $post_id, $content, $run_id = '', $is_regeneration = false ) {
		$matches = array();
		preg_match_all(
			'/<!--\s*aips:component:start:(\d+):([a-f0-9]{64})\s*-->/i',
			(string) $content,
			$matches,
			PREG_SET_ORDER
		);

		if ( empty( $matches ) ) {
			return;
		}

		$plan = array();
		foreach ( $matches as $match ) {
			$plan[] = array(
				'component_id' => absint( $match[1] ),
				'placement'    => 'saved_post',
				'hash'         => sanitize_text_field( (string) $match[2] ),
			);
		}

		$this->record_injections( $post_id, $plan, $run_id, $is_regeneration );
	}

	/**
	 * Strip all previously injected component marker blocks from content.
	 *
	 * @param string $content Content string.
	 * @return string
	 */
	public function strip_injected_components( $content ) {
		return trim(
			preg_replace(
				'/<!--\s*aips:component:start:\d+:[a-f0-9]{64}\s*-->.*?<!--\s*aips:component:end:\d+:[a-f0-9]{64}\s*-->/is',
				'',
				(string) $content
			)
		);
	}

	/**
	 * Sanitize content while preserving AIPS marker comments.
	 *
	 * @param string $content Raw content.
	 * @return string
	 */
	public static function sanitize_content_preserving_markers( $content ) {
		$placeholders = array();
		$index        = 0;

		$content = preg_replace_callback(
			'/<!--\s*aips:component:(?:start|end):\d+:[a-f0-9]{64}\s*-->/i',
			static function ( $matches ) use ( &$placeholders, &$index ) {
				$key                  = 'AIPS_COMPONENT_MARKER_' . $index . '_PLACEHOLDER';
				$placeholders[ $key ] = $matches[0];
				$index++;
				return $key;
			},
			(string) $content
		);

		$content = wp_kses_post( $content );

		if ( ! empty( $placeholders ) ) {
			$content = strtr( $content, $placeholders );
		}

		return $content;
	}

	/**
	 * Apply the resolved plan to content in deterministic placement order.
	 *
	 * @param string $content Base content.
	 * @param array  $plan Resolved plan.
	 * @return string
	 */
	private function apply_plan( $content, array $plan ) {
		if ( empty( $plan ) ) {
			return $content;
		}

		$groups = array(
			'before_content'   => array(),
			'after_intro'      => array(),
			'after_nth_h2'     => array(),
			'before_conclusion'=> array(),
			'end_of_post'      => array(),
		);

		foreach ( $plan as $item ) {
			$base_placement = $this->base_placement( (string) $item['placement'] );
			if ( ! isset( $groups[ $base_placement ] ) ) {
				$base_placement = 'end_of_post';
			}
			$groups[ $base_placement ][] = $item;
		}

		if ( ! empty( $groups['before_content'] ) ) {
			$content = $this->render_group( $groups['before_content'] ) . "\n\n" . ltrim( $content );
		}

		if ( ! empty( $groups['after_intro'] ) ) {
			$content = $this->insert_after_intro( $content, $this->render_group( $groups['after_intro'] ) );
		}

		if ( ! empty( $groups['after_nth_h2'] ) ) {
			$content = $this->insert_after_nth_h2( $content, $groups['after_nth_h2'] );
		}

		if ( ! empty( $groups['before_conclusion'] ) ) {
			$content = $this->insert_before_conclusion( $content, $this->render_group( $groups['before_conclusion'] ) );
		}

		if ( ! empty( $groups['end_of_post'] ) ) {
			$content = rtrim( $content ) . "\n\n" . $this->render_group( $groups['end_of_post'] );
		}

		return trim( $content );
	}

	/**
	 * @param array<int,array<string,mixed>> $items Group items.
	 * @return string
	 */
	private function render_group( array $items ) {
		return implode(
			"\n\n",
			array_map(
				static function ( $item ) {
					return (string) $item['rendered'];
				},
				$items
			)
		);
	}

	/**
	 * @param string $placement Placement string.
	 * @return string
	 */
	private function base_placement( $placement ) {
		$parts = explode( ':', $placement );
		return sanitize_key( $parts[0] );
	}

	/**
	 * @param int    $component_id Component ID.
	 * @param string $hash Fingerprint hash.
	 * @param string $html Rendered HTML.
	 * @return string
	 */
	private function wrap_with_markers( $component_id, $hash, $html ) {
		return sprintf(
			"<!-- aips:component:start:%1\$d:%2\$s -->\n%3\$s\n<!-- aips:component:end:%1\$d:%2\$s -->",
			absint( $component_id ),
			sanitize_text_field( $hash ),
			$html
		);
	}

	/**
	 * Insert content after the intro paragraph, with a safe fallback.
	 *
	 * @param string $content Content string.
	 * @param string $insertion Insertion HTML.
	 * @return string
	 */
	private function insert_after_intro( $content, $insertion ) {
		if ( preg_match( '/<\/p>/i', $content, $match, PREG_OFFSET_CAPTURE ) ) {
			$offset = $match[0][1] + strlen( $match[0][0] );
			return substr( $content, 0, $offset ) . "\n\n" . $insertion . "\n\n" . substr( $content, $offset );
		}

		if ( preg_match( "/\n\s*\n/", $content, $match, PREG_OFFSET_CAPTURE ) ) {
			$offset = $match[0][1] + strlen( $match[0][0] );
			return substr( $content, 0, $offset ) . $insertion . "\n\n" . substr( $content, $offset );
		}

		return $insertion . "\n\n" . ltrim( $content );
	}

	/**
	 * Insert content after the configured H2 position.
	 *
	 * @param string $content Content string.
	 * @param array  $items Plan items for this placement.
	 * @return string
	 */
	private function insert_after_nth_h2( $content, array $items ) {
		foreach ( $items as $item ) {
			$placement = (string) $item['placement'];
			$parts     = explode( ':', $placement );
			$nth       = isset( $parts[1] ) ? max( 1, absint( $parts[1] ) ) : 2;
			$rendered  = (string) $item['rendered'];
			$content   = $this->insert_single_after_nth_h2( $content, $rendered, $nth );
		}

		return $content;
	}

	/**
	 * @param string $content Content string.
	 * @param string $insertion Insertion HTML.
	 * @param int    $nth Heading number.
	 * @return string
	 */
	private function insert_single_after_nth_h2( $content, $insertion, $nth ) {
		if ( preg_match_all( '/<\/h2>/i', $content, $matches, PREG_OFFSET_CAPTURE ) && isset( $matches[0][ $nth - 1 ] ) ) {
			$offset = $matches[0][ $nth - 1 ][1] + strlen( $matches[0][ $nth - 1 ][0] );
			return substr( $content, 0, $offset ) . "\n\n" . $insertion . "\n\n" . substr( $content, $offset );
		}

		$this->record_fallback_telemetry( 'after_nth_h2_fallback', array( 'nth' => $nth ) );
		return rtrim( $content ) . "\n\n" . $insertion;
	}

	/**
	 * Insert content before a conclusion-style heading.
	 *
	 * @param string $content Content string.
	 * @param string $insertion Insertion HTML.
	 * @return string
	 */
	private function insert_before_conclusion( $content, $insertion ) {
		if ( preg_match_all( '/<h[1-6][^>]*>\s*(conclusion|final thoughts|summary|wrap up|closing)[^<]*<\/h[1-6]>/i', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			$last   = end( $matches[0] );
			$offset = is_array( $last ) ? (int) $last[1] : strlen( $content );
			return substr( $content, 0, $offset ) . $insertion . "\n\n" . substr( $content, $offset );
		}

		$this->record_fallback_telemetry( 'before_conclusion_fallback' );
		return rtrim( $content ) . "\n\n" . $insertion;
	}

	/**
	 * @param string               $type Telemetry event type.
	 * @param array<string,mixed>  $data Event payload.
	 * @return void
	 */
	private function record_fallback_telemetry( $type, array $data = array() ) {
		if ( ! AIPS_Telemetry::is_enabled() ) {
			return;
		}

		$data['type'] = $type;
		AIPS_Telemetry::instance()->add_event( 'content_components', $data );
	}
}
