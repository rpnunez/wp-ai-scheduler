<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Interface AIPS_Cache_Driver
 *
 * Contract for all cache driver implementations.
 * Every driver must be able to get, set, delete, flush, and check the
 * existence of cached values, with optional TTL and group/namespace support.
 *
 * @package AI_Post_Scheduler
 * @since   2.3.0
 */
interface AIPS_Cache_Driver {

	/**
	 * Retrieve a value from the cache.
	 *
	 * Returns null when the key does not exist or has expired.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Optional cache group/namespace. Default 'default'.
	 * @return mixed|null The cached value, or null on a miss.
	 */
	public function get( $key, $group = 'default' );

	/**
	 * Store a value in the cache.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to cache.
	 * @param int    $ttl   Time-to-live in seconds. 0 means no expiration. Default 0.
	 * @param string $group Optional cache group/namespace. Default 'default'.
	 * @return bool True on success, false on failure.
	 */
	public function set( $key, $value, $ttl = 0, $group = 'default' );

	/**
	 * Remove a value from the cache.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Optional cache group/namespace. Default 'default'.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $key, $group = 'default' );

	/**
	 * Flush all values from the cache.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function flush();

	/**
	 * Check whether a key exists in the cache and has not expired.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Optional cache group/namespace. Default 'default'.
	 * @return bool True if the key exists and is valid.
	 */
	public function has( $key, $group = 'default' );
}
