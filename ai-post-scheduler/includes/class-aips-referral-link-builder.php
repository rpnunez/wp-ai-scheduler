<?php
/**
 * Referral Link Builder
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Referral_Link_Builder
 */
class AIPS_Referral_Link_Builder {

	const DEFAULT_REL = 'sponsored nofollow noopener noreferrer';

	/**
	 * Build a validated referral URL with configured UTM parameters.
	 *
	 * @param array<string, mixed> $enhancement Enhancement record.
	 * @return string
	 */
	public function build( array $enhancement ): string {
		$url = esc_url_raw( $enhancement['referral_url'] ?? $enhancement['endpoint_url'] ?? '' );

		if ( '' === $url || ! $this->is_safe_url( $url ) ) {
			return '';
		}

		$params = array(
			'utm_campaign' => sanitize_key( $enhancement['utm_campaign'] ?? '' ),
			'utm_source'   => sanitize_key( $enhancement['utm_source'] ?? '' ),
			'utm_medium'   => sanitize_key( $enhancement['utm_medium'] ?? '' ),
		);

		foreach ( $params as $key => $value ) {
			if ( '' !== $value ) {
				$url = add_query_arg( $key, rawurlencode( $value ), $url );
			}
		}

		return esc_url_raw( $url );
	}

	/**
	 * Sanitize rel attributes while preserving safe defaults.
	 *
	 * @param string $rel Raw rel attributes.
	 * @return string
	 */
	public static function sanitize_rel( string $rel ): string {
		$tokens = preg_split( '/\\s+/', strtolower( $rel ) );
		$tokens = is_array( $tokens ) ? $tokens : array();
		$tokens = array_filter( array_map( 'sanitize_key', $tokens ) );
		$tokens = array_unique( array_merge( explode( ' ', self::DEFAULT_REL ), $tokens ) );

		return implode( ' ', $tokens );
	}

	/**
	 * Validate outbound URL scheme and host.
	 *
	 * @param string $url URL to validate.
	 * @return bool
	 */
	public function is_safe_url( string $url ): bool {
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$host   = wp_parse_url( $url, PHP_URL_HOST );

		return in_array( strtolower( (string) $scheme ), array( 'http', 'https' ), true ) && is_string( $host ) && '' !== $host;
	}
}
