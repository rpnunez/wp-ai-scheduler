<?php
/**
 * Installs and configures the cloud-session WordPress site without WP-CLI.
 * Called by scripts/cloud-wp-site.sh; not for direct use.
 *
 * Usage: php install.php <site-dir> <url> <admin-user> <admin-pass> <plugin> [<plugin> ...]
 *
 * Idempotent: an installed site is left as is, and only plugin activation and
 * options are re-applied.
 */

if ( PHP_SAPI !== 'cli' || $argc < 6 ) {
	fwrite( STDERR, "usage: php install.php <site-dir> <url> <admin-user> <admin-pass> <plugin>...\n" );
	exit( 2 );
}

list( , $site_dir, $url, $admin_user, $admin_pass ) = $argv;
$plugins = array_slice( $argv, 5 );

define( 'WP_INSTALLING', true );
$_SERVER['HTTP_HOST']   = parse_url( $url, PHP_URL_HOST ) . ( parse_url( $url, PHP_URL_PORT ) ? ':' . parse_url( $url, PHP_URL_PORT ) : '' );
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = parse_url( $url, PHP_URL_HOST );

require rtrim( $site_dir, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

if ( ! is_blog_installed() ) {
	$result = wp_install( 'AI Post Scheduler (cloud)', $admin_user, 'admin@example.com', false, '', wp_slash( $admin_pass ) );
	echo "Installed WordPress (admin user: {$admin_user}).\n";
	unset( $result );
} else {
	echo "WordPress already installed.\n";
}

update_option( 'siteurl', $url );
update_option( 'home', $url );

foreach ( $plugins as $plugin ) {
	$candidates = array_keys( get_plugins( '/' . $plugin ) );
	if ( empty( $candidates ) ) {
		echo "Plugin not found: {$plugin}\n";
		continue;
	}
	$file = $plugin . '/' . $candidates[0];
	if ( is_plugin_active( $file ) ) {
		continue;
	}
	$activated = activate_plugin( $file );
	echo is_wp_error( $activated ) ? "Could not activate {$plugin}: " . $activated->get_error_message() . "\n" : "Activated {$plugin}.\n";
}

// Same provider wiring as docker-entrypoint.sh. The key itself is a dummy:
// the session proxy replaces it with the network secret on Google requests.
update_option( 'aips_ai_provider', 'wp_ai_client' );
$google_key = getenv( 'GOOGLE_API_KEY' );
if ( $google_key ) {
	update_option( 'connectors_ai_google_api_key', $google_key );
	update_option( 'connectors_ai_provider_google_api_key', $google_key );
}
echo "Configured AI provider: wp_ai_client (Google connector).\n";
