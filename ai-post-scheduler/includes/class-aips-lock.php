<?php
/**
 * AIPS Concurrency & In-Flight Lock Helper
 *
 * Provides atomic, transient-based lock acquisition and release to prevent
 * simultaneous or duplicate executions of sensitive operations (such as
 * manual generation runs, schedule triggers, or batch tasks).
 *
 * @package AI_Post_Scheduler
 * @since   3.7.1
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Lock {

	/**
	 * Lock prefix.
	 */
	private const PREFIX = 'aips_lock_';

	/**
	 * Default lock TTL in seconds.
	 */
	private const DEFAULT_TTL = 30;

	/**
	 * Attempt to acquire an execution lock for a given key.
	 *
	 * Uses WordPress transients. If a lock already exists, returns false.
	 *
	 * @param string $key Name of the lock (e.g., 'run_now_template_12').
	 * @param int    $ttl Lock expiration in seconds (default 30).
	 * @return bool True if lock acquired, false if already locked.
	 */
	public static function acquire(string $key, int $ttl = self::DEFAULT_TTL): bool {
		$transient_key = self::PREFIX . sanitize_key($key);

		if (get_transient($transient_key) !== false) {
			return false;
		}

		return set_transient($transient_key, time(), max(5, $ttl));
	}

	/**
	 * Release an execution lock for a given key.
	 *
	 * @param string $key Name of the lock.
	 * @return bool True if lock was released.
	 */
	public static function release(string $key): bool {
		$transient_key = self::PREFIX . sanitize_key($key);

		return delete_transient($transient_key);
	}

	/**
	 * Check whether a lock is currently active.
	 *
	 * @param string $key Name of the lock.
	 * @return bool True if locked, false otherwise.
	 */
	public static function is_locked(string $key): bool {
		$transient_key = self::PREFIX . sanitize_key($key);

		return get_transient($transient_key) !== false;
	}

	/**
	 * Execute a callable safely inside an acquired lock.
	 *
	 * Automatically releases the lock when execution finishes (even if an
	 * exception occurs).
	 *
	 * @param string   $key      Lock key.
	 * @param callable $callback Operation to run under lock.
	 * @param int      $ttl      Lock TTL.
	 * @return mixed Return value of the callback, or WP_Error if locked.
	 */
	public static function with_lock(string $key, callable $callback, int $ttl = self::DEFAULT_TTL) {
		if (!self::acquire($key, $ttl)) {
			return new WP_Error('resource_locked', __('An operation is already in progress for this item. Please wait.', 'ai-post-scheduler'));
		}

		try {
			return call_user_func($callback);
		} finally {
			self::release($key);
		}
	}
}
