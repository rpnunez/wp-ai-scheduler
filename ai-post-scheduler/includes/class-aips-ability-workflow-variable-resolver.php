<?php
/**
 * Ability Workflow Variable Resolver
 *
 * Resolves `{{trigger.x}}` / `{{steps.<alias>.output.field}}` template
 * tokens against a run-scoped variable bag. Pure logic — no DB, HTTP, or
 * ability calls — reused by both the executor (runtime input resolution)
 * and the document validator (save-time token validation).
 *
 * Variable bag shape:
 *   array(
 *       'trigger' => array( ... ),
 *       'steps'   => array(
 *           '<output_alias>' => array( 'output' => array( ... ), 'status' => 'completed' ),
 *       ),
 *   )
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Ability_Workflow_Variable_Resolver
 */
class AIPS_Ability_Workflow_Variable_Resolver {

	/**
	 * Regex matching a single {{token.path}} reference anywhere in a string.
	 */
	const TOKEN_PATTERN = '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/';

	/**
	 * Regex matching a string that consists of exactly one {{token.path}} reference.
	 */
	const WHOLE_TOKEN_PATTERN = '/^\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}$/';

	/**
	 * Resolve a template string against a variable bag.
	 *
	 * When the whole string is a single token, the raw typed value is
	 * returned (e.g. an int, array, or bool). When tokens are embedded in a
	 * longer string, each token is interpolated as a string.
	 *
	 * @param string $template_value Template string, e.g. "{{steps.outline.output.count}}".
	 * @param array  $variables      Variable bag.
	 * @return mixed
	 */
	public function resolve( string $template_value, array $variables ) {
		if ( '' === $template_value || false === strpos( $template_value, '{{' ) ) {
			return $template_value;
		}

		if ( preg_match( self::WHOLE_TOKEN_PATTERN, $template_value, $matches ) ) {
			return $this->resolve_token_path( $matches[1], $variables );
		}

		return preg_replace_callback(
			self::TOKEN_PATTERN,
			function ( $matches ) use ( $variables ) {
				$value = $this->resolve_token_path( $matches[1], $variables );

				if ( is_array( $value ) ) {
					return wp_json_encode( $value );
				}

				return null === $value ? '' : (string) $value;
			},
			$template_value
		);
	}

	/**
	 * Recursively resolve every string leaf of an input map.
	 *
	 * @param array $input_map Map of destination field => template value (or nested map).
	 * @param array $variables Variable bag.
	 * @return array
	 */
	public function resolve_map( array $input_map, array $variables ): array {
		$resolved = array();

		foreach ( $input_map as $key => $value ) {
			if ( is_array( $value ) ) {
				$resolved[ $key ] = $this->resolve_map( $value, $variables );
			} elseif ( is_string( $value ) ) {
				$resolved[ $key ] = $this->resolve( $value, $variables );
			} else {
				$resolved[ $key ] = $value;
			}
		}

		return $resolved;
	}

	/**
	 * Extract raw token paths (without braces) found in a template string.
	 *
	 * @param string $template_value Template string.
	 * @return string[]
	 */
	public function extract_tokens( string $template_value ): array {
		if ( ! preg_match_all( self::TOKEN_PATTERN, $template_value, $matches ) ) {
			return array();
		}

		return $matches[1];
	}

	/**
	 * Validate a single raw token path (e.g. "trigger.topic" or
	 * "steps.outline.output.sections_count").
	 *
	 * @param string $token Raw token path, without braces.
	 * @return true|WP_Error
	 */
	public function validate_token( string $token ) {
		if ( 'trigger' === $token || 0 === strpos( $token, 'trigger.' ) ) {
			return true;
		}

		if ( preg_match( '/^steps\.[a-zA-Z0-9_]+\.output(\.[a-zA-Z0-9_]+)*$/', $token ) ) {
			return true;
		}

		return new WP_Error(
			'ability_workflow_token_invalid',
			/* translators: %s: token path */
			sprintf( __( 'Invalid variable reference "%s" — must start with trigger or steps.<alias>.output.', 'ai-post-scheduler' ), $token )
		);
	}

	/**
	 * Extract and validate every token found in a template string.
	 *
	 * @param string $value Template string, possibly containing multiple tokens.
	 * @return true|WP_Error
	 */
	public function validate_token_string( string $value ) {
		foreach ( $this->extract_tokens( $value ) as $token ) {
			$result = $this->validate_token( $token );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Walk a dot-separated token path through the variable bag.
	 *
	 * @param string $path      Dot-separated path, e.g. "steps.outline.output.count".
	 * @param array  $variables Variable bag.
	 * @return mixed Null when any segment of the path is missing.
	 */
	private function resolve_token_path( string $path, array $variables ) {
		$cursor = $variables;

		foreach ( explode( '.', $path ) as $segment ) {
			if ( is_array( $cursor ) && array_key_exists( $segment, $cursor ) ) {
				$cursor = $cursor[ $segment ];
			} else {
				return null;
			}
		}

		return $cursor;
	}
}
