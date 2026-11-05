<?php
/**
 * Query Monitor Integration.
 *
 * Ensures Query Monitor correctly attributes symlinked plugin files to
 * AI Post Scheduler instead of falling back to WordPress Core.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

final class AIPS_Query_Monitor {

	/**
	 * Register Query Monitor filters.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter('qm/component_dirs', array(__CLASS__, 'filter_component_dirs'));
		add_filter('qm/component_type/plugin:ai-post-scheduler', array(__CLASS__, 'filter_component_type'));
		add_filter('qm/component_context/plugin', array(__CLASS__, 'filter_component_context'), 10, 2);
	}

	/**
	 * Register the real (symlink-resolved) plugin path with Query Monitor.
	 *
	 * @param array $dirs Existing directory mappings.
	 * @return array
	 */
	public static function filter_component_dirs( array $dirs ) {
		$real = realpath( AIPS_PLUGIN_DIR );
		if ( false === $real ) {
			return $dirs;
		}

		$real = rtrim( str_replace( '\\', '/', $real ), '/' );
		$expected = rtrim( str_replace( '\\', '/', WP_PLUGIN_DIR . '/ai-post-scheduler' ), '/' );

		if ( $real !== $expected ) {
			$dirs['plugin:ai-post-scheduler'] = $real;
		}

		return $dirs;
	}

	/**
	 * Map custom dir-key type back to TYPE_PLUGIN.
	 *
	 * @return string
	 */
	public static function filter_component_type() {
		return 'plugin';
	}

	/**
	 * Supply the plugin slug as context for matching files.
	 *
	 * @param string|mixed $context Existing context.
	 * @param string|mixed $file    File path.
	 * @return string
	 */
	public static function filter_component_context( $context, $file ) {
		$real = realpath( AIPS_PLUGIN_DIR );

		if ( false === $real || ! is_string( $file ) ) {
			return $context;
		}

		$real = rtrim( str_replace( '\\', '/', $real ), '/' );
		$file = str_replace( '\\', '/', $file );

		if ( 0 === strpos( $file, $real . '/' ) || $file === $real ) {
			return 'ai-post-scheduler';
		}

		return $context;
	}
}
