<?php
/**
 * Content Component Run Context
 *
 * DTO for runtime component evaluation.
 *
 * @package AI_Post_Scheduler
 * @since 2.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Content_Component_Run_Context {

	/**
	 * @var array<string,mixed>
	 */
	private $data;

	/**
	 * @param array<string,mixed> $data Context data.
	 */
	public function __construct( array $data ) {
		$this->data = $data;
	}

	/**
	 * Build context from a generation context before the post is saved.
	 *
	 * @param AIPS_Generation_Context $generation_context Generation context.
	 * @param string                  $content Generated content.
	 * @param array<string,mixed>     $args Additional overrides.
	 * @return self
	 */
	public static function from_generation_context( AIPS_Generation_Context $generation_context, $content = '', array $args = array() ) {
		$author_id      = absint( $generation_context->get_post_author() );
		$locale         = isset( $args['locale'] ) ? sanitize_text_field( (string) $args['locale'] ) : get_locale();
		$author_persona = self::resolve_author_persona( $generation_context, $author_id );

		return new self(
			array(
				'post_id'           => isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0,
				'post_type'         => isset( $args['post_type'] ) ? sanitize_key( (string) $args['post_type'] ) : 'post',
				'post_status'       => sanitize_key( (string) $generation_context->get_post_status() ),
				'author_id'         => $author_id,
				'author_persona'    => $author_persona,
				'topic'             => (string) $generation_context->get_topic(),
				'category_tokens'   => self::normalize_term_tokens( $generation_context->get_post_category(), 'category' ),
				'tag_tokens'        => self::normalize_tag_tokens( $generation_context->get_post_tags() ),
				'locale'            => $locale,
				'region'            => self::resolve_region( $args['region'] ?? '', $locale ),
				'content'           => (string) $content,
				'content_length'    => mb_strlen( wp_strip_all_tags( (string) $content ) ),
				'has_headings'      => preg_match( '/<h[1-6][^>]*>/i', (string) $content ) ? true : false,
				'has_h2'            => preg_match( '/<h2[^>]*>/i', (string) $content ) ? true : false,
				'policy_tags'       => self::normalize_tag_tokens( $args['policy_tags'] ?? '' ),
				'run_timestamp'     => isset( $args['run_timestamp'] ) ? absint( $args['run_timestamp'] ) : AIPS_DateTime::now()->timestamp(),
				'site_timezone'     => wp_timezone_string(),
			)
		);
	}

	/**
	 * Build context from an existing WordPress post.
	 *
	 * @param WP_Post                $post Post object.
	 * @param array<string,mixed>    $args Additional overrides.
	 * @return self
	 */
	public static function from_post( WP_Post $post, array $args = array() ) {
		$categories = wp_get_post_terms( $post->ID, 'category' );
		$tags       = wp_get_post_terms( $post->ID, 'post_tag' );
		$locale     = isset( $args['locale'] ) ? sanitize_text_field( (string) $args['locale'] ) : get_locale();
		$content    = isset( $args['content'] ) ? (string) $args['content'] : (string) $post->post_content;

		return new self(
			array(
				'post_id'           => (int) $post->ID,
				'post_type'         => sanitize_key( (string) $post->post_type ),
				'post_status'       => sanitize_key( (string) $post->post_status ),
				'author_id'         => (int) $post->post_author,
				'author_persona'    => self::resolve_author_persona( null, (int) $post->post_author ),
				'topic'             => (string) $post->post_title,
				'category_tokens'   => self::normalize_wp_terms( $categories ),
				'tag_tokens'        => self::normalize_wp_terms( $tags ),
				'locale'            => $locale,
				'region'            => self::resolve_region( $args['region'] ?? '', $locale ),
				'content'           => $content,
				'content_length'    => mb_strlen( wp_strip_all_tags( $content ) ),
				'has_headings'      => preg_match( '/<h[1-6][^>]*>/i', $content ) ? true : false,
				'has_h2'            => preg_match( '/<h2[^>]*>/i', $content ) ? true : false,
				'policy_tags'       => self::normalize_tag_tokens( $args['policy_tags'] ?? '' ),
				'run_timestamp'     => isset( $args['run_timestamp'] ) ? absint( $args['run_timestamp'] ) : AIPS_DateTime::now()->timestamp(),
				'site_timezone'     => wp_timezone_string(),
			)
		);
	}

	/**
	 * @param string $key Context key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		return array_key_exists( $key, $this->data ) ? $this->data[ $key ] : $default;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function to_array() {
		return $this->data;
	}

	/**
	 * Resolve a best-effort author persona string from supported sources.
	 *
	 * @param AIPS_Generation_Context|null $generation_context Generation context.
	 * @param int                          $author_id WP author ID.
	 * @return string
	 */
	private static function resolve_author_persona( $generation_context = null, $author_id = 0 ) {
		$candidates = array();

		if ( $author_id > 0 ) {
			$candidates[] = get_user_meta( $author_id, 'aips_persona', true );
			$candidates[] = get_user_meta( $author_id, 'persona', true );
			$candidates[] = get_user_meta( $author_id, 'description', true );
		}

		if ( $generation_context && method_exists( $generation_context, 'get_author' ) ) {
			$author = $generation_context->get_author();
			if ( is_object( $author ) ) {
				$candidates[] = $author->field_niche ?? '';
				$candidates[] = $author->description ?? '';
				$candidates[] = $author->name ?? '';
			}
		}

		foreach ( $candidates as $candidate ) {
			$candidate = trim( wp_strip_all_tags( (string) $candidate ) );
			if ( '' !== $candidate ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Normalize category-like tokens from IDs, slugs, names, or CSV.
	 *
	 * @param mixed  $raw Raw value.
	 * @param string $taxonomy Taxonomy name.
	 * @return string[]
	 */
	private static function normalize_term_tokens( $raw, $taxonomy ) {
		$tokens = array();

		if ( is_array( $raw ) ) {
			$values = $raw;
		} elseif ( is_string( $raw ) ) {
			$values = array_map( 'trim', explode( ',', $raw ) );
		} elseif ( null !== $raw && '' !== $raw ) {
			$values = array( $raw );
		} else {
			$values = array();
		}

		foreach ( $values as $value ) {
			$value = trim( (string) $value );
			if ( '' === $value ) {
				continue;
			}

			$tokens[] = strtolower( $value );

			if ( is_numeric( $value ) && taxonomy_exists( $taxonomy ) ) {
				$term = get_term( (int) $value, $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					$tokens[] = strtolower( (string) $term->slug );
					$tokens[] = strtolower( (string) $term->name );
				}
			}
		}

		return array_values( array_unique( array_filter( $tokens ) ) );
	}

	/**
	 * Normalize tags from CSV.
	 *
	 * @param mixed $raw Raw tag value.
	 * @return string[]
	 */
	private static function normalize_tag_tokens( $raw ) {
		if ( is_array( $raw ) ) {
			$values = $raw;
		} else {
			$values = array_map( 'trim', explode( ',', (string) $raw ) );
		}

		$tokens = array();
		foreach ( $values as $value ) {
			$value = strtolower( trim( wp_strip_all_tags( (string) $value ) ) );
			if ( '' !== $value ) {
				$tokens[] = $value;
			}
		}

		return array_values( array_unique( $tokens ) );
	}

	/**
	 * Normalize WP_Term objects to ID/slug/name tokens.
	 *
	 * @param WP_Term[]|WP_Error $terms Terms list.
	 * @return string[]
	 */
	private static function normalize_wp_terms( $terms ) {
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$tokens = array();
		foreach ( $terms as $term ) {
			if ( ! is_object( $term ) ) {
				continue;
			}
			$tokens[] = strtolower( (string) $term->term_id );
			$tokens[] = strtolower( (string) $term->slug );
			$tokens[] = strtolower( (string) $term->name );
		}

		return array_values( array_unique( array_filter( $tokens ) ) );
	}

	/**
	 * Resolve region from an override or locale suffix.
	 *
	 * @param string $region_override Optional region override.
	 * @param string $locale Locale string.
	 * @return string
	 */
	private static function resolve_region( $region_override, $locale ) {
		$region_override = strtoupper( trim( sanitize_text_field( (string) $region_override ) ) );
		if ( '' !== $region_override ) {
			return $region_override;
		}

		if ( preg_match( '/[_-]([A-Za-z]{2})$/', (string) $locale, $matches ) ) {
			return strtoupper( $matches[1] );
		}

		return 'US';
	}
}
