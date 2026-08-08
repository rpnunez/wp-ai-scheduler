<?php
/**
 * Query Monitor component-mapping integration.
 *
 * Tells Query Monitor that files under the real (symlink-resolved) plugin
 * directory belong to this plugin, not WordPress Core.
 *
 * When the plugin directory is a symlink, PHP's debug_backtrace() returns the
 * real path (e.g. C:/Projects/.../ai-post-scheduler) which does not start with
 * WP_PLUGIN_DIR, so QM falls back to "WordPress Core".
 *
 * The three companion filters together register the resolved path and map the
 * custom key back to TYPE_PLUGIN with the correct plugin-slug context so that
 * QM shows "Plugin: ai-post-scheduler" in the Component column.
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Query_Monitor_Integration {

    /**
     * Register the Query Monitor component-mapping filters.
     *
     * @return void
     */
    public static function register() {
        add_filter('qm/component_dirs', function( array $dirs ) {
            $real = realpath( AIPS_PLUGIN_DIR );
            if ( false === $real ) {
                return $dirs;
            }

            $real = rtrim( str_replace( '\\', '/', $real ), '/' );

            // Compare against the canonical WordPress plugins path, not AIPS_PLUGIN_DIR.
            // In symlinked installs plugin_dir_path(__FILE__) can already be resolved.
            $expected = rtrim( str_replace( '\\', '/', WP_PLUGIN_DIR . '/ai-post-scheduler' ), '/' );

            // Only add when the real path differs from the canonical WP plugin path
            // (i.e. a symlink is in use). Using a namespaced key avoids overwriting
            // QM's built-in 'plugin' entry which is appended after this filter runs.
            if ( $real !== $expected ) {
                $dirs['plugin:ai-post-scheduler'] = $real;
            }

            return $dirs;
        });

        // Map the custom dir-key type back to TYPE_PLUGIN so QM shows the correct
        // component class and name rather than falling into the "unknown" branch.
        add_filter('qm/component_type/plugin:ai-post-scheduler', function() {
            return 'plugin';
        });

        // Supply the plugin slug as the context so the component name becomes
        // "Plugin: ai-post-scheduler" instead of "Plugin: plugin:ai-post-scheduler".
        add_filter('qm/component_context/plugin', function( $context, $file ) {
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
        }, 10, 2);
    }
}
