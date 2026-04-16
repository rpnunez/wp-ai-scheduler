<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Cache_Factory
 *
 * Builds a ready-to-use AIPS_Cache instance based on the admin-configured
 * cache driver, with automatic fallback to the ArrayDriver when the selected
 * driver cannot initialise.
 *
 * Usage:
 *   $cache = AIPS_Cache_Factory::instance(); // shared singleton
 *   $cache = AIPS_Cache_Factory::make();     // new AIPS_Cache each call
 *
 * @package AI_Post_Scheduler
 * @since   2.3.0
 */
class AIPS_Cache_Factory {

	/**
	 * Singleton AIPS_Cache instance (re-used across the request).
	 *
	 * @var AIPS_Cache|null
	 */
	private static $instance = null;

	/**
	 * Registry of named AIPS_Cache instances.
	 *
	 * Named instances let different plugin subsystems each use a different
	 * driver independently. For example, one subsystem can use the Array
	 * driver for request-scoped caching while another uses the Session driver
	 * for cross-request persistence.
	 *
	 * @var array<string, AIPS_Cache>
	 */
	private static $named = array();

	// -----------------------------------------------------------------------
	// Public factory methods
	// -----------------------------------------------------------------------

	/**
	 * Return the shared AIPS_Cache singleton for this request.
	 *
	 * @return AIPS_Cache
	 */
	public static function instance() {
		if (self::$instance === null) {
			self::$instance = self::make();
		}
		return self::$instance;
	}

	/**
	 * Create and return a new AIPS_Cache instance configured from settings.
	 *
	 * @param string|null $driver_name Override driver name. Null = read from settings.
	 * @return AIPS_Cache
	 */
	public static function make( $driver_name = null ) {
		$driver = self::make_driver( $driver_name );
		return new AIPS_Cache( $driver );
	}

	/**
	 * Resolve and instantiate the configured driver.
	 *
	 * Falls back to ArrayDriver if the chosen driver cannot be initialised
	 * (e.g. Redis extension missing) and optionally schedules an admin notice.
	 *
	 * Note: This method intentionally uses direct get_option() calls instead of
	 * AIPS_Config::get_instance()->get_option(). AIPS_Config relies on
	 * AIPS_Cache_Factory::named() to create its own per-request cache, so using
	 * AIPS_Config here while the singleton is still being constructed would create
	 * a bootstrapping circular dependency. See AIPS_Config::get_cache_config() for
	 * the equivalent typed accessor intended for all other callers.
	 *
	 * @param string|null $driver_name Optional override. Null = read from settings.
	 * @return AIPS_Cache_Driver
	 */
	public static function make_driver( $driver_name = null ) {
		if ($driver_name === null) {
			$driver_name = get_option( 'aips_cache_driver', 'array' );
		}

		switch ( (string) $driver_name ) {
			case 'db':
				$prefix = (string) get_option( 'aips_cache_db_prefix', '' );
				return new AIPS_Cache_Db_Driver( $prefix );

			case 'session':
				return new AIPS_Cache_Session_Driver();

			case 'redis':
				$notice = '';
				$driver = self::try_make_redis_driver( $notice );
				if ($driver !== null) {
					return $driver;
				}
				// Could not connect — fall back and warn.
				self::schedule_admin_notice( $notice );
				return new AIPS_Cache_Array_Driver();

			case 'wp_object_cache':
				return new AIPS_Cache_Wp_Object_Cache_Driver();

			case 'array':
			default:
				return new AIPS_Cache_Array_Driver();
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
	 * Get (or lazily create) a named AIPS_Cache instance.
	 *
	 * Named instances allow different parts of the plugin to use separate
	 * cache drivers independently. For example:
	 *
	 *   // Request-scoped caching for compiled templates.
	 *   $templates     = AIPS_Cache_Factory::named( 'templates', 'array' );
	 *
	 *   // Cross-request caching for notifications (5-minute TTL).
	 *   $notifications = AIPS_Cache_Factory::named( 'notifications', 'session' );
	 *
	 * If a named instance already exists it is returned as-is, regardless of
	 * the $driver_name parameter. To force a new driver, call register() first.
	 *
	 * @param string      $name        Identifier for this named cache instance.
	 * @param string|null $driver_name Driver to use when creating the instance.
	 *                                 Null = read from admin settings (same default
	 *                                 as instance()).
	 * @return AIPS_Cache
	 */
	public static function named( $name, $driver_name = null ) {
		if (!isset( self::$named[ $name ] )) {
			self::$named[ $name ] = self::make( $driver_name );
		}
		return self::$named[ $name ];
	}

	/**
	 * Pre-register a named AIPS_Cache instance.
	 *
	 * Typically called during plugin bootstrap to pre-wire a specific driver
	 * for a named cache channel. If an instance with the same name already
	 * exists it is replaced.
	 *
	 * Example:
	 *   AIPS_Cache_Factory::register(
	 *       'templates',
	 *       new AIPS_Cache( new AIPS_Cache_Array_Driver() )
	 *   );
	 *   AIPS_Cache_Factory::register(
	 *       'notifications',
	 *       new AIPS_Cache( new AIPS_Cache_Session_Driver() )
	 *   );
	 *
	 * @param string     $name  Instance name.
	 * @param AIPS_Cache $cache Ready-to-use AIPS_Cache instance.
	 * @return void
	 */
	public static function register( $name, AIPS_Cache $cache ) {
		self::$named[ $name ] = $cache;
	}

	// -----------------------------------------------------------------------
	// Internal helpers
	// -----------------------------------------------------------------------

	/**
	 * Attempt to build a Redis driver from settings.
	 *
	 * @param string &$notice Populated with a human-readable notice when the
	 *                        driver cannot be initialised, otherwise empty.
	 * @return AIPS_Cache_Redis_Driver|null Null when the extension is missing
	 *                                       or the connection fails.
	 */
	private static function try_make_redis_driver( &$notice = '' ) {
		if (!extension_loaded( 'redis' )) {
			$notice = __( 'AI Post Scheduler: Redis cache driver could not load — the PHP <code>redis</code> extension is not installed. Falling back to the in-memory Array driver. Install the extension via PECL (<code>pecl install redis</code>) and restart your web server.', 'ai-post-scheduler' );
			return null;
		}

		$host     = (string) get_option( 'aips_cache_redis_host', '127.0.0.1' );
		$port     = (int) get_option( 'aips_cache_redis_port', 6379 );
		$password = (string) get_option( 'aips_cache_redis_password', '' );
		$db       = (int) get_option( 'aips_cache_redis_db', 0 );
		$prefix   = (string) get_option( 'aips_cache_redis_prefix', 'aips' );
		$timeout  = (float) get_option( 'aips_cache_redis_timeout', 2.0 );

		$driver = new AIPS_Cache_Redis_Driver( $host, $port, $password, $db, $prefix, $timeout );

		if (!$driver->is_connected()) {
			$error_detail = $driver->get_last_error();
			/* translators: %s: connection error message from the Redis extension. */
			$notice = sprintf(
				__( 'AI Post Scheduler: Redis cache driver could not connect to <code>%1$s:%2$d</code>. Falling back to the in-memory Array driver. Error: %3$s', 'ai-post-scheduler' ),
				esc_html( $host ),
				$port,
				esc_html( $error_detail ?: __( 'unknown error', 'ai-post-scheduler' ) )
			);
			return null;
		}

		return $driver;
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
