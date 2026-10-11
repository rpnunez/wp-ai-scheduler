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
	 * Tokens of the locks this request holds, keyed by option name.
	 *
	 * @var array<string,string>
	 */
	private static $held = array();

	/**
	 * @var AIPS_Lock_Repository|null
	 */
	private static $repository = null;

	/**
	 * Attempt to acquire an execution lock for a given key.
	 *
	 * The lock is a row in the options table whose value is "expiry|token". The
	 * option_name unique index makes the insert atomic, so two concurrent callers
	 * cannot both acquire it (a get-then-set transient has that race). An expired
	 * lock is taken over with a compare-and-delete, so only one caller wins.
	 *
	 * @param string $key Name of the lock (e.g., 'run_now_template_12').
	 * @param int    $ttl Lock expiration in seconds (default 30).
	 * @return bool True if lock acquired, false if already locked.
	 */
	public static function acquire(string $key, int $ttl = self::DEFAULT_TTL): bool {
		$name  = self::option_name($key);
		$token = wp_generate_password(12, false);
		$value = (time() + max(5, $ttl)) . '|' . $token;

		if (self::repository()->insert($name, $value)) {
			self::$held[$name] = $token;
			return true;
		}

		// Held by someone else: take it over only if it has expired.
		$current = self::repository()->read($name);
		if (null !== $current && self::is_expired($current) && self::repository()->delete_if_equals($name, $current) && self::repository()->insert($name, $value)) {
			self::$held[$name] = $token;
			return true;
		}

		return false;
	}

	/**
	 * Release an execution lock for a given key.
	 *
	 * A lock this request acquired is only released while it still owns it, so a
	 * slow holder whose lock expired and was taken over cannot free the new
	 * holder's lock. A lock acquired in another request is released outright.
	 *
	 * @param string $key Name of the lock.
	 * @return bool True if lock was released.
	 */
	public static function release(string $key): bool {
		$name  = self::option_name($key);
		$token = isset(self::$held[$name]) ? self::$held[$name] : null;
		unset(self::$held[$name]);

		$current = self::repository()->read($name);
		if (null === $current) {
			return false;
		}

		if (null !== $token && self::token_of($current) !== $token) {
			return false;
		}

		return self::repository()->delete_if_equals($name, $current);
	}

	/**
	 * Check whether a lock is currently active.
	 *
	 * @param string $key Name of the lock.
	 * @return bool True if locked, false otherwise.
	 */
	public static function is_locked(string $key): bool {
		$current = self::repository()->read(self::option_name($key));

		return null !== $current && !self::is_expired($current);
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

	/**
	 * Delete lock rows that expired without being released (for example after a crash).
	 *
	 * Transient locks expired on their own; option-backed locks need this sweep.
	 *
	 * @return int Rows deleted.
	 */
	public static function purge_expired(): int {
		return self::repository()->delete_expired(self::PREFIX, time());
	}

	/**
	 * @return AIPS_Lock_Repository
	 */
	private static function repository(): AIPS_Lock_Repository {
		if (null === self::$repository) {
			self::$repository = new AIPS_Lock_Repository();
		}

		return self::$repository;
	}

	/**
	 * @param string $key Lock key.
	 * @return string Option name holding the lock.
	 */
	private static function option_name(string $key): string {
		return self::PREFIX . sanitize_key($key);
	}

	/**
	 * @param string $value Lock value ("expiry|token").
	 * @return bool
	 */
	private static function is_expired(string $value): bool {
		return (int) $value <= time();
	}

	/**
	 * @param string $value Lock value ("expiry|token").
	 * @return string
	 */
	private static function token_of(string $value): string {
		$parts = explode('|', $value, 2);

		return isset($parts[1]) ? $parts[1] : '';
	}
}
