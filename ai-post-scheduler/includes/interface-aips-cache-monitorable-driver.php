<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Interface AIPS_Cache_Monitorable_Driver
 *
 * Contract for cache drivers that support introspection by the Cache Monitor.
 * Drivers that cannot provide some capabilities should declare those capabilities
 * as false via get_monitor_capabilities() and provide stub implementations
 * that return safe empty values.
 *
 * @package AI_Post_Scheduler
 * @since   2.9.0
 */
interface AIPS_Cache_Monitorable_Driver {

	/**
	 * Return a map of monitor capability flags for this driver.
	 *
	 * Recognised keys:
	 *   list_keys      – driver can enumerate plugin cache entries
	 *   inspect_entry  – driver can read a single entry's metadata + preview
	 *   delete_key     – driver supports deleting one entry
	 *   delete_group   – driver supports flushing an entire group
	 *   flush_plugin   – driver can flush all plugin-owned cache
	 *   size_bytes     – driver can estimate storage size
	 *   ttl_remaining  – driver can report remaining TTL per entry
	 *   tag_versions   – driver can list tag-version keys
	 *   live_metrics   – driver exposes request-level hit/miss counters
	 *
	 * @return array<string, bool>
	 */
	public function get_monitor_capabilities(): array;

	/**
	 * Return a page of indexed cache entries with optional filtering.
	 *
	 * Supported filter keys:
	 *   group    (string)  – filter by cache group
	 *   key_hash (string)  – filter by SHA-256 key hash prefix
	 *   ttl_state (string) – 'active' | 'expired' | 'no_expiration'
	 *
	 * Each returned item should contain at minimum:
	 *   cache_key, key_hash, cache_group, expires_at, value_type, value_size
	 *
	 * Returns an empty array when list_keys capability is false.
	 *
	 * @param array $filters Associative filter map. Default empty.
	 * @param int   $limit   Maximum rows to return. Default 100.
	 * @param int   $offset  Row offset for pagination. Default 0.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_entries( array $filters = array(), int $limit = 100, int $offset = 0 ): array;

	/**
	 * Return the total number of entries matching the given filters.
	 *
	 * Returns 0 when list_keys capability is false.
	 *
	 * @param array $filters Associative filter map. Default empty.
	 * @return int
	 */
	public function count_entries( array $filters = array() ): int;

	/**
	 * Return metadata for a single cache entry without unserializing its value.
	 *
	 * Returns an empty array when the entry does not exist or inspect_entry
	 * capability is false.
	 *
	 * @param string $key   Cache key (raw, before any prefix transformations).
	 * @param string $group Cache group. Default 'default'.
	 * @return array<string, mixed>
	 */
	public function get_entry_metadata( string $key, string $group = 'default' ): array;

	/**
	 * Delete a single cache entry directly via the driver.
	 *
	 * Returns false when delete_key capability is false.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group. Default 'default'.
	 * @return bool
	 */
	public function delete_entry( string $key, string $group = 'default' ): bool;

	/**
	 * Flush all entries belonging to a single cache group.
	 *
	 * Returns false when delete_group capability is false.
	 *
	 * @param string $group Cache group.
	 * @return bool
	 */
	public function delete_group( string $group ): bool;

	/**
	 * Estimate total storage size.
	 *
	 * Returns an array with at least:
	 *   total_bytes   (int)    – aggregate byte estimate
	 *   row_count     (int)    – total number of entries
	 *   expired_bytes (int)    – bytes from expired entries
	 *   expired_count (int)    – number of expired entries
	 *   available     (bool)   – false when size_bytes capability is false
	 *
	 * @param array $filters Optional filters (same format as list_entries).
	 * @return array<string, mixed>
	 */
	public function estimate_size( array $filters = array() ): array;

	/**
	 * Return driver-specific diagnostic information for the Driver tab.
	 *
	 * @return array<string, mixed>
	 */
	public function get_driver_info(): array;
}
