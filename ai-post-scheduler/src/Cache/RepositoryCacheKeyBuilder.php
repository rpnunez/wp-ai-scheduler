<?php
namespace AIPS\Cache;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Builds stable cache keys for repository read operations.
 */
class RepositoryCacheKeyBuilder {

	const SCHEMA_VERSION = '1';
	const EMPTY_FILTER_VALUE = '__aips_empty_filter__';
	const KEY_PREFIX = 'aips_repo';

	/**
	 * Build a repository cache key from the operation, arguments, tag versions, and context.
	 *
	 * @param string $operation_id Operation identifier for the cached repository read.
	 * @param array  $args Repository method arguments and filters.
	 * @param array  $tag_versions Cache tag versions that invalidate related keys when bumped.
	 * @param array  $context Optional caller-provided context.
	 * @return string
	 */
	public static function build_key( string $operation_id, array $args, array $tag_versions = array(), array $context = array() ): string {
		return implode(
			':',
			array(
				self::KEY_PREFIX,
				self::normalize_operation_id( $operation_id ),
				self::hash_args( $args ),
				self::hash_args( $tag_versions ),
				'ctx',
				self::hash_args( self::build_context( $context ) ),
			)
		);
	}

	/**
	 * Normalize arguments into a deterministic representation suitable for hashing.
	 *
	 * @param array $args Arguments to normalize.
	 * @return array
	 */
	public static function normalize_args( array $args ): array {
		return self::normalize_array( $args, '' );
	}

	/**
	 * Hash an argument array after normalization.
	 *
	 * @param array $args Arguments to hash.
	 * @return string
	 */
	public static function hash_args( array $args ): string {
		$normalized = self::normalize_args( $args );
		$encoded    = self::json_encode( $normalized );

		return hash( 'sha256', $encoded );
	}

	/**
	 * Build the context that must participate in every repository cache key.
	 *
	 * @param array $context Caller-provided context.
	 * @return array
	 */
	private static function build_context( array $context ): array {
		$context['repository_cache_schema_version'] = self::SCHEMA_VERSION;
		$context['plugin_version']                  = defined( 'AIPS_VERSION' ) ? AIPS_VERSION : '';

		if ( function_exists( 'get_current_blog_id' ) ) {
			$context['blog_id'] = (int) get_current_blog_id();
		}

		return $context;
	}

	/**
	 * Normalize an array recursively while preserving indexed-array ordering.
	 *
	 * @param array  $args Arguments to normalize.
	 * @param string $parent_key Parent argument key.
	 * @return array
	 */
	private static function normalize_array( array $args, string $parent_key ): array {
		if ( array() === $args ) {
			return self::is_filter_key( $parent_key ) ? array( '__empty' => 1 ) : array();
		}

		if ( self::is_associative_array( $args ) ) {
			ksort( $args, SORT_STRING );
		}

		$normalized = array();
		foreach ( $args as $key => $value ) {
			$normalized[ $key ] = self::normalize_value( $value, (string) $key, $parent_key );
		}

		return $normalized;
	}

	/**
	 * Normalize a scalar or nested array value.
	 *
	 * @param mixed  $value Value to normalize.
	 * @param string $key Current key.
	 * @param string $parent_key Parent key.
	 * @return mixed
	 */
	private static function normalize_value( $value, string $key, string $parent_key ) {
		if ( self::is_empty_filter_value( $value, $key, $parent_key ) ) {
			return self::EMPTY_FILTER_VALUE;
		}

		if ( is_array( $value ) ) {
			if ( self::is_ids_key( $key ) ) {
				return self::normalize_id_list( $value );
			}

			return self::normalize_array( $value, $key );
		}

		if ( is_bool( $value ) ) {
			return $value ? 1 : 0;
		}

		if ( self::is_id_key( $key ) && is_numeric( $value ) ) {
			return (int) $value;
		}

		return $value;
	}

	/**
	 * Normalize list values for typed ID-list arguments.
	 *
	 * @param array $values ID values.
	 * @return array
	 */
	private static function normalize_id_list( array $values ): array {
		if ( self::is_associative_array( $values ) ) {
			ksort( $values, SORT_STRING );
		}

		$normalized = array();
		foreach ( $values as $key => $value ) {
			if ( is_array( $value ) ) {
				$normalized[ $key ] = self::normalize_id_list( $value );
				continue;
			}

			$normalized[ $key ] = is_numeric( $value ) ? (int) $value : $value;
		}

		return $normalized;
	}

	/**
	 * Determine whether an array should be sorted by key.
	 *
	 * @param array $value Array to inspect.
	 * @return bool
	 */
	private static function is_associative_array( array $value ): bool {
		if ( array() === $value ) {
			return false;
		}

		return array_keys( $value ) !== range( 0, count( $value ) - 1 );
	}

	/**
	 * Determine whether an argument key contains a single typed ID.
	 *
	 * @param string $key Argument key.
	 * @return bool
	 */
	private static function is_id_key( string $key ): bool {
		$key = strtolower( $key );

		return 'id' === $key || (bool) preg_match( '/(^|_)id$/', $key );
	}

	/**
	 * Determine whether an argument key contains a list of typed IDs.
	 *
	 * @param string $key Argument key.
	 * @return bool
	 */
	private static function is_ids_key( string $key ): bool {
		$key = strtolower( $key );

		return 'ids' === $key || (bool) preg_match( '/(^|_)ids$/', $key );
	}

	/**
	 * Determine whether a key belongs to a filters collection.
	 *
	 * @param string $key Argument key.
	 * @return bool
	 */
	private static function is_filter_key( string $key ): bool {
		$key = strtolower( $key );

		return in_array( $key, array( 'filter', 'filters', 'date_filter', 'date_filters' ), true );
	}

	/**
	 * Determine whether an empty filter should be normalized to the sentinel value.
	 *
	 * @param mixed  $value Value to inspect.
	 * @param string $key Current key.
	 * @param string $parent_key Parent key.
	 * @return bool
	 */
	private static function is_empty_filter_value( $value, string $key, string $parent_key ): bool {
		if ( ! self::is_filter_key( $key ) && ! self::is_filter_key( $parent_key ) ) {
			return false;
		}

		return null === $value || '' === $value || array() === $value;
	}

	/**
	 * Normalize operation IDs for safe cache-key inclusion.
	 *
	 * @param string $operation_id Operation ID.
	 * @return string
	 */
	private static function normalize_operation_id( string $operation_id ): string {
		$operation_id = strtolower( trim( $operation_id ) );
		$operation_id = preg_replace( '/[^a-z0-9_.-]+/', '_', $operation_id );

		return $operation_id ? $operation_id : 'operation';
	}

	/**
	 * Encode data consistently across WordPress and bare PHP contexts.
	 *
	 * @param mixed $value Value to encode.
	 * @return string
	 */
	private static function json_encode( $value ): string {
		$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

		if ( function_exists( 'wp_json_encode' ) ) {
			$encoded = wp_json_encode( $value, $flags );
		} else {
			$encoded = json_encode( $value, $flags );
		}

		return false === $encoded ? serialize( $value ) : (string) $encoded;
	}
}
