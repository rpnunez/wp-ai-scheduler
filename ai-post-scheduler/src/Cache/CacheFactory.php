<?php
namespace AIPS\Cache;

if (!defined('ABSPATH')) {
	exit;
}

use AIPS\Cache\Cache;
use AIPS\Cache\CacheDriverInterface;
use AIPS\Cache\Drivers\ArrayDriver;
use AIPS\Cache\Drivers\DBDriver;
use AIPS\Cache\Drivers\WPObjectCacheDriver;

/**
 * Class CacheFactory
 *
 * Builds a ready-to-use Cache instance based on the admin-configured
 * cache driver.
 *
 * Usage:
 *   $cache = CacheFactory::instance(); // shared singleton
 *   $cache = CacheFactory::make();     // new Cache each call
 *
 * @package AI_Post_Scheduler
 * @since   2.3.0
 */
class CacheFactory {

	/**
	 * Singleton Cache instance (re-used across the request).
	 *
	 * @var Cache|null
	 */
	private static $instance = null;

	/**
	 * Registry of named Cache instances.
	 *
	 * @var array<string, Cache>
	 */
	private static $named = array();

	// -----------------------------------------------------------------------
	// Public factory methods
	// -----------------------------------------------------------------------

	/**
	 * Return the shared Cache singleton for this request.
	 *
	 * @return Cache
	 */
	public static function instance() {
		if (self::$instance === null) {
			self::$instance = self::make();
		}
		return self::$instance;
	}

	/**
	 * Create and return a new Cache instance configured from settings.
	 *
	 * @param string|null $driver_name Override driver name. Null = read from settings.
	 * @param string      $namespace   Optional namespace prepended to DB cache keys.
	 * @return Cache
	 */
	public static function make( $driver_name = null, $namespace = '' ) {
		$driver = self::make_driver( $driver_name, $namespace );
		return new Cache( $driver );
	}

	/**
	 * Resolve and instantiate the configured driver.
	 *
	 * Falls back to ArrayDriver when the selected value is unsupported.
	 *
	 * When the 'aips_enable_cache_system' option is falsy the Array driver is
	 * returned immediately.
	 *
	 * Note: This method intentionally uses direct get_option() calls instead of
	 * Config::get_instance()->get_option(). Config relies on
	 * CacheFactory::named() to create its own per-request cache, so using
	 * Config here while the singleton is still being constructed would create
	 * a bootstrapping circular dependency. See Config::get_cache_config() for
	 * the equivalent typed accessor intended for all other callers.
	 *
	 * @param string|null $driver_name Optional override. Null = read from settings.
	 * @param string      $namespace   Optional namespace prepended to DB cache keys.
	 * @return CacheDriverInterface
	 */
	public static function make_driver( $driver_name = null, $namespace = '' ) {
		// When the cache system is disabled, skip expensive driver initialisation
		// (Redis connection, DB queries, etc.) and return the lightest driver.
		$system_enabled_raw = get_option( 'aips_enable_cache_system', '1' );
		$system_enabled     = ($system_enabled_raw !== '0' && $system_enabled_raw !== 0 && $system_enabled_raw !== false);
		if (!$system_enabled) {
			return new ArrayDriver();
		}

		if ($driver_name === null) {
			$driver_name = get_option( 'aips_cache_driver', 'array' );
		}

		$raw_driver_name = (string) $driver_name;
		$driver_name     = self::normalize_driver_name( $raw_driver_name );

		if ($driver_name !== $raw_driver_name) {
			if ($driver_name !== get_option( 'aips_cache_driver', 'array' )) {
				update_option( 'aips_cache_driver', $driver_name );
			}

			self::schedule_admin_notice(
				__( 'AI Post Scheduler: The selected cache driver is no longer supported and was migrated to WP Object Cache.', 'ai-post-scheduler' )
			);
		}

		switch ( $driver_name ) {
			case 'db':
				$prefix = (string) get_option( 'aips_cache_db_prefix', '' );
				if (!empty($namespace)) {
					$prefix = $prefix !== '' ? $prefix . ':' . $namespace : $namespace;
				}
				return new DBDriver( $prefix );

			case 'wp_object_cache':
				return new WPObjectCacheDriver();

			case 'array':
			default:
				return new ArrayDriver();
		}
	}

	/**
	 * Reset the shared singleton and all named instances (useful for testing
	 * or forced re-init).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$instance = null;
		self::$named    = array();
	}

	// -----------------------------------------------------------------------
	// Named instance API
	// -----------------------------------------------------------------------

	/**
	 * Get (or lazily create) a named Cache instance.
	 *
	 * Named instances allow different parts of the plugin to use separate
	 * cache drivers independently. For example:
	 *
	 *   // Request-scoped caching for compiled templates.
	 *   $templates     = CacheFactory::named( 'templates', 'array' );
	 *
	 *   // Persistent caching for notifications (5-minute TTL).
	 *   $notifications = CacheFactory::named( 'notifications', 'db' );
	 *
	 * If a named instance already exists it is returned as-is, regardless of
	 * the $driver_name parameter. To force a new driver, call register() first.
	 *
	 * @param string      $name        Identifier for this named cache instance.
	 * @param string|null $driver_name Driver to use when creating the instance.
	 *                                 Null = read from admin settings (same default
	 *                                 as instance()).
	 * @return Cache
	 */
	public static function named( $name, $driver_name = null ) {
		if (!isset( self::$named[ $name ] )) {
			self::$named[ $name ] = self::make( $driver_name, $name );
		}
		return self::$named[ $name ];
	}

	/**
	 * Pre-register a named Cache instance.
	 *
	 * Typically called during plugin bootstrap to pre-wire a specific driver
	 * for a named cache channel. If an instance with the same name already
	 * exists it is replaced.
	 *
	 * Example:
	 *   CacheFactory::register(
	 *       'templates',
	 *       new Cache( new ArrayDriver() )
	 *   );
	 *   CacheFactory::register(
	 *       'notifications',
	 *       new Cache( new DBDriver() )
	 *   );
	 *
	 * @param string $name  Instance name.
	 * @param Cache  $cache Ready-to-use Cache instance.
	 * @return void
	 */
	public static function register( $name, Cache $cache ) {
		self::$named[ $name ] = $cache;
	}

	// -----------------------------------------------------------------------
	// Internal helpers
	// -----------------------------------------------------------------------

	/**
	 * Normalize any persisted driver value to the currently supported set.
	 *
	 * @param string $driver_name Raw driver name.
	 * @return string One of: array, db, wp_object_cache.
	 */
	private static function normalize_driver_name( $driver_name ) {
		switch ( (string) $driver_name ) {
			case 'db':
			case 'wp_object_cache':
			case 'array':
				return (string) $driver_name;

			case 'redis':
			case 'session':
				return 'wp_object_cache';

			default:
				return 'array';
		}
	}

	/**
	 * Register a one-time admin notice for a failed driver initialisation.
	 *
	 * Uses a static flag so the notice is registered at most once per request,
	 * regardless of how many times the factory is called.
	 *
	 * @param string $message HTML-safe notice message.
	 * @return void
	 */
	private static function schedule_admin_notice( $message ) {
		if (!is_admin()) {
			return;
		}

		static $registered = false;
		if ($registered) {
			return;
		}
		$registered = true;

		add_action( 'admin_notices', static function() use ( $message ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . wp_kses_post( $message ) . '</p></div>';
		});
	}
}
