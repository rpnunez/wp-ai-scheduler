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

			case 'redis':
				$driver = self::try_make_redis_driver();
				if ($driver !== null) {
					return $driver;
				}
				// Could not connect — fall back and warn.
				self::schedule_admin_notice(
					__( 'AI Post Scheduler: Redis cache driver could not connect. Falling back to the in-memory Array driver. Check the Cache settings (host, port, password, db) and ensure the PHP <code>redis</code> extension is installed.', 'ai-post-scheduler' )
				);
				return new AIPS_Cache_Array_Driver();

			case 'wp_object_cache':
				return new AIPS_Cache_Wp_Object_Cache_Driver();

			case 'array':
			default:
				return new AIPS_Cache_Array_Driver();
		}
	}

	/**
	 * Reset the shared singleton (useful for testing or forced re-init).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$instance = null;
	}

	// -----------------------------------------------------------------------
	// Internal helpers
	// -----------------------------------------------------------------------

	/**
	 * Attempt to build a Redis driver from settings.
	 *
	 * @return AIPS_Cache_Redis_Driver|null Null when the extension is missing
	 *                                       or the connection fails.
	 */
	private static function try_make_redis_driver() {
		if (!extension_loaded( 'redis' )) {
			return null;
		}

		$host     = (string) get_option( 'aips_cache_redis_host', '127.0.0.1' );
		$port     = (int) get_option( 'aips_cache_redis_port', 6379 );
		$password = (string) get_option( 'aips_cache_redis_password', '' );
		$db       = (int) get_option( 'aips_cache_redis_db', 0 );
		$prefix   = (string) get_option( 'aips_cache_redis_prefix', 'aips' );

		$driver = new AIPS_Cache_Redis_Driver( $host, $port, $password, $db, $prefix );

		if (!$driver->is_connected()) {
			return null;
		}

		return $driver;
	}

	/**
	 * Register a one-time admin notice for a failed driver initialisation.
	 *
	 * @param string $message HTML-safe notice message.
	 * @return void
	 */
	private static function schedule_admin_notice( $message ) {
		if (!is_admin()) {
			return;
		}

		add_action( 'admin_notices', static function() use ( $message ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . wp_kses_post( $message ) . '</p></div>';
		});
	}
}
