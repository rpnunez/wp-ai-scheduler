<?php
/**
 * Ability Catalog Service
 *
 * The only class the workflow UI/AJAX layer and executor should call for
 * ability discovery. Wraps AIPS_Ability_Service and normalizes its output
 * into a stable shape the workflow builder and executor can depend on.
 *
 * Never re-implements ability provider discovery — that lives entirely in
 * AIPS_Ability_Service.
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Ability_Catalog_Service
 */
class AIPS_Ability_Catalog_Service {

	/**
	 * @var AIPS_Ability_Service
	 */
	private $ability_service;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Ability_Service|null $ability_service Ability service adapter.
	 */
	public function __construct( ?AIPS_Ability_Service $ability_service = null ) {
		if ( $ability_service ) {
			$this->ability_service = $ability_service;
			return;
		}

		$container = AIPS_Container::get_instance();
		$this->ability_service = $container->has( AIPS_Ability_Service::class )
			? $container->make( AIPS_Ability_Service::class )
			: new AIPS_Ability_Service();
	}

	/**
	 * List available abilities, normalized.
	 *
	 * @param array $args {
	 *     @type string $category Optional category filter.
	 *     @type string $search   Optional label/description/name search.
	 * }
	 * @return array|WP_Error Array of normalized ability arrays keyed by name, or WP_Error.
	 */
	public function list_abilities( array $args = array() ) {
		$abilities = $this->ability_service->list_available();

		if ( is_wp_error( $abilities ) ) {
			return $abilities;
		}

		$normalized = array();

		foreach ( $abilities as $slug => $raw_ability ) {
			$normalized[ $slug ] = $this->normalize_ability( is_array( $raw_ability ) ? $raw_ability : array( 'slug' => $slug ) );
		}

		if ( ! empty( $args['category'] ) ) {
			$category   = (string) $args['category'];
			$normalized = array_filter(
				$normalized,
				function ( $ability ) use ( $category ) {
					return $ability['category'] === $category;
				}
			);
		}

		if ( ! empty( $args['search'] ) ) {
			$search     = strtolower( (string) $args['search'] );
			$normalized = array_filter(
				$normalized,
				function ( $ability ) use ( $search ) {
					$haystack = strtolower( $ability['name'] . ' ' . $ability['label'] . ' ' . $ability['description'] );
					return false !== strpos( $haystack, $search );
				}
			);
		}

		return $normalized;
	}

	/**
	 * Get a single normalized ability.
	 *
	 * @param string $ability_name Ability slug.
	 * @return array|WP_Error
	 */
	public function get_ability( string $ability_name ) {
		$abilities = $this->list_abilities();

		if ( is_wp_error( $abilities ) ) {
			return $abilities;
		}

		if ( ! isset( $abilities[ $ability_name ] ) ) {
			return new WP_Error(
				'ability_not_found',
				/* translators: %s: ability name */
				sprintf( __( 'Ability "%s" was not found.', 'ai-post-scheduler' ), $ability_name )
			);
		}

		return $abilities[ $ability_name ];
	}

	/**
	 * Normalize a raw ability array (from AIPS_Ability_Service::list_available())
	 * into the stable shape the UI/executor depend on.
	 *
	 * @param array $raw_ability Raw ability data.
	 * @return array {
	 *     @type string      $name                 Ability slug.
	 *     @type string      $label                Human-readable label.
	 *     @type string      $description          Description text.
	 *     @type string      $provider             Provider label/source.
	 *     @type string      $category             Category, defaults to 'general'.
	 *     @type array       $input_schema         Input schema, if provided by the raw ability.
	 *     @type array       $output_schema        Output schema, if provided by the raw ability.
	 *     @type string|null $permission_callback  Permission callback identifier, if any.
	 *     @type bool        $is_destructive       Whether invoking this ability mutates state destructively.
	 *     @type bool        $is_available         Whether this ability is currently invocable.
	 *     @type array       $metadata             Any remaining raw fields, for forward-compat display.
	 * }
	 */
	public function normalize_ability( array $raw_ability ): array {
		$slug = isset( $raw_ability['slug'] ) ? (string) $raw_ability['slug'] : ( isset( $raw_ability['name'] ) ? (string) $raw_ability['name'] : '' );

		$known_keys = array(
			'slug', 'name', 'label', 'description', 'provider', 'category',
			'input_schema', 'output_schema', 'permission_callback',
			'is_destructive', 'destructive', 'is_available',
		);

		return array(
			'name'                 => $slug,
			'label'                => ! empty( $raw_ability['label'] ) ? (string) $raw_ability['label'] : $this->humanize_slug( $slug ),
			'description'          => isset( $raw_ability['description'] ) ? (string) $raw_ability['description'] : '',
			'provider'             => isset( $raw_ability['provider'] ) ? (string) $raw_ability['provider'] : __( 'Unknown', 'ai-post-scheduler' ),
			'category'             => ! empty( $raw_ability['category'] ) ? (string) $raw_ability['category'] : 'general',
			'input_schema'         => isset( $raw_ability['input_schema'] ) && is_array( $raw_ability['input_schema'] ) ? $raw_ability['input_schema'] : array(),
			'output_schema'        => isset( $raw_ability['output_schema'] ) && is_array( $raw_ability['output_schema'] ) ? $raw_ability['output_schema'] : array(),
			'permission_callback'  => isset( $raw_ability['permission_callback'] ) ? $raw_ability['permission_callback'] : null,
			'is_destructive'       => ! empty( $raw_ability['is_destructive'] ) || ! empty( $raw_ability['destructive'] ),
			'is_available'         => array_key_exists( 'is_available', $raw_ability ) ? (bool) $raw_ability['is_available'] : true,
			'metadata'             => array_diff_key( $raw_ability, array_flip( $known_keys ) ),
		);
	}

	/**
	 * Validate that an ability is currently available for invocation.
	 *
	 * @param string $ability_name Ability slug.
	 * @return true|WP_Error
	 */
	public function validate_ability_available( string $ability_name ) {
		$available = $this->ability_service->is_available( $ability_name );

		if ( is_wp_error( $available ) ) {
			return $available;
		}

		if ( ! $available ) {
			return new WP_Error(
				'ability_unavailable',
				/* translators: %s: ability name */
				sprintf( __( 'Ability "%s" is not available.', 'ai-post-scheduler' ), $ability_name )
			);
		}

		return true;
	}

	/**
	 * Get the input schema for an ability.
	 *
	 * @param string $ability_name Ability slug.
	 * @return array|WP_Error
	 */
	public function get_input_schema( string $ability_name ) {
		$ability = $this->get_ability( $ability_name );

		return is_wp_error( $ability ) ? $ability : $ability['input_schema'];
	}

	/**
	 * Get the output schema for an ability.
	 *
	 * @param string $ability_name Ability slug.
	 * @return array|WP_Error
	 */
	public function get_output_schema( string $ability_name ) {
		$ability = $this->get_ability( $ability_name );

		return is_wp_error( $ability ) ? $ability : $ability['output_schema'];
	}

	/**
	 * Turn a slug like "vendor/create-outline" into a display label.
	 *
	 * @param string $slug Ability slug.
	 * @return string
	 */
	private function humanize_slug( string $slug ): string {
		$label = preg_replace( '/^[^\/]+\//', '', $slug );
		$label = str_replace( array( '-', '_' ), ' ', $label );

		return ucwords( trim( $label ) );
	}
}
