<?php
/**
 * Plugin Name: AIPS cloud-session proxy trust
 * Description: Installed by scripts/cloud-wp-site.sh as a must-use plugin. Makes
 * WordPress HTTP requests trust the Claude Code session proxy's CA bundle, so
 * outgoing HTTPS (e.g. to the Gemini API) works through the proxy that injects
 * network secrets. The proxy host/port constants are set in wp-config.php.
 */

defined( 'ABSPATH' ) || exit;

if ( is_readable( '/root/.ccr/ca-bundle.crt' ) ) {
	add_filter(
		'http_request_args',
		static function ( $args ) {
			$args['sslcertificates'] = '/root/.ccr/ca-bundle.crt';
			return $args;
		}
	);
}
