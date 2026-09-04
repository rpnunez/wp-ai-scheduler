<?php
/**
 * JSON Extractor Utility
 *
 * Provides utility methods to extract and sanitize JSON fragments from raw AI text responses.
 *
 * @package AI_Post_Scheduler
 * @since 3.6.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIPS_JSON_Extractor
 *
 * Utility class for extracting and sanitizing JSON fragments.
 */
class AIPS_JSON_Extractor {

    /**
     * Extract the first balanced JSON object/array from text.
     *
     * @param string $text Raw AI text response.
     * @return string|WP_Error Balanced JSON fragment or WP_Error.
     */
	public static function extract_json_fragment( $text ) {
		$text = trim( (string) $text );

		// Remove common markdown wrappers.
		$text = preg_replace( '/^```(?:json)?\s*/i', '', $text );
		$text = preg_replace( '/```\s*$/', '', $text );
		$text = trim( (string) $text );

		$search_offset = 0;
		$text_length   = strlen( $text );
		$found_token   = false;

		while ( $search_offset < $text_length ) {
			$start_pos_obj = strpos( $text, '{', $search_offset );
			$start_pos_arr = strpos( $text, '[', $search_offset );

			if ( false === $start_pos_obj && false === $start_pos_arr ) {
				break;
			}

			$found_token = true;

			if ( false === $start_pos_obj ) {
				$start_pos = $start_pos_arr;
			} elseif ( false === $start_pos_arr ) {
				$start_pos = $start_pos_obj;
			} else {
				$start_pos = min( $start_pos_obj, $start_pos_arr );
			}

			$in_string = false;
			$escape    = false;
			$stack     = array();

			for ( $i = $start_pos; $i < $text_length; $i++ ) {
				$ch = $text[ $i ];

				if ( $in_string ) {
					if ( $escape ) {
						$escape = false;
					} elseif ( '\\' === $ch ) {
						$escape = true;
					} elseif ( '"' === $ch ) {
						$in_string = false;
					}

					continue;
				}

				if ( '"' === $ch ) {
					$in_string = true;
					continue;
				}

				if ( '{' === $ch || '[' === $ch ) {
					$stack[] = $ch;
					continue;
				}

				if ( '}' !== $ch && ']' !== $ch ) {
					continue;
				}

				if ( empty( $stack ) ) {
					return new WP_Error( 'json_extract_failed', __( 'JSON appears malformed (unexpected closing token).', 'ai-post-scheduler' ) );
				}

				$open = array_pop( $stack );
				if ( ( '{' === $open && '}' !== $ch ) || ( '[' === $open && ']' !== $ch ) ) {
					return new WP_Error( 'json_extract_failed', __( 'JSON appears malformed (mismatched tokens).', 'ai-post-scheduler' ) );
				}

				if ( ! empty( $stack ) ) {
					continue;
				}

				$candidate = substr( $text, $start_pos, $i - $start_pos + 1 );
				$candidate = self::sanitize_json_candidate( $candidate );
				$decoded   = json_decode( $candidate, true );

				if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
					return $candidate;
				}

				$search_offset = $i + 1;
				continue 2;
			}

			return new WP_Error( 'json_extract_failed', __( 'JSON appears truncated before closing token.', 'ai-post-scheduler' ) );
		}

		$message = $found_token
			? __( 'No valid JSON object or array found in AI response.', 'ai-post-scheduler' )
			: __( 'No JSON start token found in AI response.', 'ai-post-scheduler' );

		return new WP_Error( 'json_extract_failed', $message );
	}

	/**
	 * Extract and decode the first valid JSON object or array from text.
	 *
	 * @param string $text Raw AI text response.
	 * @return array|WP_Error Decoded JSON data or WP_Error.
	 */
	public static function decode_json_response( $text ) {
		$candidate = self::extract_json_fragment( $text );

		if ( is_wp_error( $candidate ) ) {
			return $candidate;
		}

		$decoded = json_decode( $candidate, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return new WP_Error(
				'json_decode_failed',
				sprintf(
					__( 'Failed to parse JSON: %s', 'ai-post-scheduler' ),
					json_last_error_msg()
				)
			);
		}

		return $decoded;
	}

	/**
     * Normalize control characters in a candidate JSON fragment.
     *
     * @param string $candidate Candidate JSON fragment.
     * @return string
     */
	private static function sanitize_json_candidate( $candidate ) {
		return preg_replace_callback(
			'/"((?:[^"\\\\]|\\\\.)*)"/',
			function ( $matches ) {
				$inner = $matches[1];
				$inner = str_replace( "\r", '\\r', $inner );
				$inner = str_replace( "\n", '\\n', $inner );
				$inner = str_replace( "\t", '\\t', $inner );
				$inner = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $inner );

				return '"' . $inner . '"';
			},
			(string) $candidate
		);
	}
}
