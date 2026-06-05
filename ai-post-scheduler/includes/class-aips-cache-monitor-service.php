<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Cache_Monitor_Service
 *
 * Business logic for the Cache Monitor subsystem.
 * All operations on cache data (summary, entries, tags, domains, operations,
 * events, driver info, maintenance) are coordinated here.
 *
 * Destructive operations are logged via AIPS_Cache_Monitor_Repository::insert_event().
 *
 * @package AI_Post_Scheduler
 * @since   2.9.0
 */
class AIPS_Cache_Monitor_Service {

	/**
	 * Sensitive field names whose values should be redacted in previews.
	 *
	 * @var array<int, string>
	 */
	private const REDACT_FIELDS = array(
		'api_key', 'api_secret', 'token', 'secret', 'password', 'pass',
		'authorization', 'auth', 'credential', 'private_key', 'access_key',
		'access_token', 'refresh_token', 'client_secret',
	);

	/**
	 * @var AIPS_Cache_Monitor_Repository
	 */
	private $repository;

	/**
	 * @var AIPS_Cache_Index
	 */
	private $cache_index;

	/**
	 * @var AIPS_Cache
	 */
	private $cache;

	/**
	 * @var AIPS_Logger_Interface|null
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Cache_Monitor_Repository $repository
	 * @param AIPS_Cache_Index              $cache_index
	 * @param AIPS_Cache|null               $cache
	 * @param AIPS_Logger_Interface|null    $logger
	 */
	public function __construct(
		AIPS_Cache_Monitor_Repository $repository,
		AIPS_Cache_Index $cache_index,
		AIPS_Cache $cache = null,
		AIPS_Logger_Interface $logger = null
	) {
		$this->repository  = $repository;
		$this->cache_index = $cache_index;
		$this->cache       = $cache ?? AIPS_Cache_Factory::instance();
		$this->logger      = $logger ?? AIPS_Logger::instance();
	}

	// -----------------------------------------------------------------------
	// Summary / Overview
	// -----------------------------------------------------------------------

	/**
	 * Return full summary data for the Overview tab.
	 *
	 * @return array<string, mixed>
	 */
	public function get_summary(): array {
		$config        = AIPS_Config::get_instance();
		$driver_name   = (string) $config->get_option( 'aips_cache_driver', 'array' );
		$cache_enabled = (bool) $config->get_option( 'aips_enable_cache_system', true );
		$driver        = $this->resolve_active_driver();
		$capabilities  = $driver instanceof AIPS_Cache_Monitorable_Driver
			? $driver->get_monitor_capabilities()
			: array();

		$index_summary = $this->repository->get_index_summary();

		// Last flush: look for the most recent flush event.
		$last_flush_events = $this->repository->get_events( array( 'event_type' => 'flush_all' ), 1, 1 );
		$last_flush_ts     = !empty( $last_flush_events[0]['created_at'] ) ? (int) $last_flush_events[0]['created_at'] : null;

		// Driver size estimate.
		$driver_size = $driver instanceof AIPS_Cache_Monitorable_Driver
			? $driver->estimate_size()
			: array( 'available' => false );

		return array(
			'cache_enabled'   => $cache_enabled,
			'driver_name'     => $driver_name,
			'driver_label'    => $this->get_driver_label( $driver_name ),
			'capabilities'    => $capabilities,
			'index'           => $index_summary,
			'driver_size'     => $driver_size,
			'last_flush_ts'   => $last_flush_ts,
		);
	}

	// -----------------------------------------------------------------------
	// Entries
	// -----------------------------------------------------------------------

	/**
	 * Return paginated cache entries from the index.
	 *
	 * @param array  $filters  Filter map.
	 * @param string $orderby  Sort column.
	 * @param string $order    ASC|DESC.
	 * @param int    $per_page Rows per page.
	 * @param int    $page     Page number.
	 * @return array{rows: array, total: int, total_pages: int, page: int}
	 */
	public function get_entries( array $filters = array(), string $orderby = 'updated_at', string $order = 'DESC', int $per_page = 50, int $page = 1 ): array {
		$total       = $this->repository->count_index_entries( $filters );
		$total_pages = max( 1, (int) ceil( $total / max( 1, $per_page ) ) );
		$page        = min( $page, $total_pages );
		$rows        = $this->repository->get_index_entries( $filters, $orderby, $order, $per_page, $page );
		$now         = AIPS_DateTime::now()->timestamp();

		foreach ($rows as &$row) {
			$expires               = (int) ($row['expires_at'] ?? 0);
			$row['ttl_remaining']  = $expires > 0 ? max( 0, $expires - $now ) : null;
			$row['is_expired']     = $expires > 0 && $expires < $now;
			$row['tags_array']     = !empty( $row['tags'] ) ? array_filter( explode( ',', $row['tags'] ) ) : array();
		}
		unset( $row );

		return compact( 'rows', 'total', 'total_pages', 'page' );
	}

	/**
	 * Return a safe value preview for a cached entry.
	 *
	 * The value is fetched from the cache (not the index) using the raw key.
	 * Sensitive fields are redacted. The full value is only returned when
	 * debug/dev mode is active.
	 *
	 * @param string $key_hash  SHA-256 hash identifying the index entry.
	 * @param bool   $full_view Whether full raw value is permitted.
	 * @return array<string, mixed>
	 */
	public function inspect_entry( string $key_hash, bool $full_view = false ): array {
		$index_row = $this->repository->get_index_entry_by_hash( $key_hash );

		if (!$index_row) {
			return array( 'error' => __( 'Entry not found in cache index.', 'ai-post-scheduler' ) );
		}

		$key   = $index_row['cache_key'];
		$group = $index_row['cache_group'];
		$value = $this->cache->get( $key, $group );
		$now   = AIPS_DateTime::now()->timestamp();

		$metadata = array(
			'key_hash'      => $index_row['key_hash'],
			'cache_key'     => $key,
			'cache_group'   => $group,
			'driver'        => $index_row['driver'],
			'tier'          => $index_row['tier'],
			'operation_id'  => $index_row['operation_id'],
			'tags'          => $index_row['tags'],
			'domain'        => $index_row['domain'],
			'ttl'           => $index_row['ttl'],
			'created_at'    => $index_row['created_at'],
			'expires_at'    => $index_row['expires_at'],
			'ttl_remaining' => (int) $index_row['expires_at'] > 0
				? max( 0, (int) $index_row['expires_at'] - $now )
				: null,
			'value_size'    => $index_row['value_size'],
			'value_type'    => $index_row['value_type'],
		);

		if ($value === null) {
			$metadata['preview']       = null;
			$metadata['cache_hit']     = false;
			$metadata['preview_note']  = __( 'Value not found in active cache (may have expired or been evicted).', 'ai-post-scheduler' );

			return $metadata;
		}

		$metadata['cache_hit'] = true;
		$metadata['preview']   = $full_view
			? $this->redact_value( $value )
			: $this->safe_preview( $value );

		$metadata['preview_note'] = $full_view
			? __( 'Full value (sensitive fields redacted).', 'ai-post-scheduler' )
			: __( 'Safe preview (truncated). Enable dev mode for full value.', 'ai-post-scheduler' );

		return $metadata;
	}

	// -----------------------------------------------------------------------
	// Tags / Domains
	// -----------------------------------------------------------------------

	/**
	 * Return all distinct tags from the index with entry counts.
	 *
	 * @param array $filters Filter map (currently unused – reserved).
	 * @return array<int, array<string, mixed>>
	 */
	public function list_tags( array $filters = array() ): array {
		$tags    = $this->repository->list_tags();
		$cache   = $this->cache;
		$result  = array();

		foreach ($tags as $tag_row) {
			$tag     = $tag_row['tag'];
			$version = $cache->get_tag_version( $tag, 'default' );

			$result[] = array(
				'tag'         => $tag,
				'entry_count' => (int) $tag_row['entry_count'],
				'version'     => $version,
			);
		}

		return $result;
	}

	/**
	 * Return details for a specific tag.
	 *
	 * @param string $tag Tag name.
	 * @return array<string, mixed>
	 */
	public function get_tag_details( string $tag ): array {
		$version = $this->cache->get_tag_version( $tag, 'default' );
		$entries = $this->repository->get_index_entries( array( 'tag' => $tag ), 'updated_at', 'DESC', 50, 1 );

		return array(
			'tag'     => $tag,
			'version' => $version,
			'entries' => $entries,
		);
	}

	/**
	 * Return all known cache invalidation domains.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function list_domains(): array {
		// Domains are derived from AIPS_Repository_Cache_Dependencies static map.
		$known_domains = array(
			'author', 'author_topic', 'author_topic_log', 'post_generation', 'dashboard', 'unified_schedule',
		);

		$result = array();
		foreach ($known_domains as $domain) {
			$tags     = AIPS_Repository_Cache_Dependencies::tags_for_invalidation( $domain );
			$result[] = array(
				'domain' => $domain,
				'tags'   => $tags,
			);
		}

		return $result;
	}

	/**
	 * Return details for a specific domain.
	 *
	 * @param string $domain Domain name.
	 * @return array<string, mixed>
	 */
	public function get_domain_details( string $domain ): array {
		$tags            = AIPS_Repository_Cache_Dependencies::tags_for_invalidation( $domain );
		$affected_count  = 0;
		$affected_ops    = array();

		foreach ($tags as $tag) {
			$entries         = $this->repository->get_index_entries( array( 'tag' => $tag ), 'updated_at', 'DESC', 50, 1 );
			$affected_count += count( $entries );
			foreach ($entries as $entry) {
				if (!empty( $entry['operation_id'] )) {
					$affected_ops[] = $entry['operation_id'];
				}
			}
		}

		return array(
			'domain'          => $domain,
			'tags'            => $tags,
			'affected_count'  => $affected_count,
			'affected_ops'    => array_values( array_unique( $affected_ops ) ),
		);
	}

	// -----------------------------------------------------------------------
	// Operations analytics
	// -----------------------------------------------------------------------

	/**
	 * Return operation-level analytics from the index.
	 *
	 * @param array $filters Optional filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_operations( array $filters = array() ): array {
		return $this->repository->list_operations( $filters );
	}

	// -----------------------------------------------------------------------
	// Events
	// -----------------------------------------------------------------------

	/**
	 * Return paginated cache events log.
	 *
	 * @param array $filters  Filter map.
	 * @param int   $per_page Rows per page.
	 * @param int   $page     Page number.
	 * @return array{rows: array, total: int, total_pages: int, page: int}
	 */
	public function get_events( array $filters = array(), int $per_page = 50, int $page = 1 ): array {
		$total       = $this->repository->count_events( $filters );
		$total_pages = max( 1, (int) ceil( $total / max( 1, $per_page ) ) );
		$page        = min( $page, $total_pages );
		$rows        = $this->repository->get_events( $filters, $per_page, $page );

		return compact( 'rows', 'total', 'total_pages', 'page' );
	}

	// -----------------------------------------------------------------------
	// Invalidation actions
	// -----------------------------------------------------------------------

	/**
	 * Delete a single cache entry by key_hash and return affected count.
	 *
	 * @param string $key_hash SHA-256 key hash.
	 * @return int 1 on success, 0 on failure.
	 */
	public function delete_entry( string $key_hash ): int {
		$index_row = $this->repository->get_index_entry_by_hash( $key_hash );

		if (!$index_row) {
			return 0;
		}

		$key   = $index_row['cache_key'];
		$group = $index_row['cache_group'];

		$this->cache->delete( $key, $group );
		$this->cache_index->record_delete( $key, $group );

		$this->log_event( 'entry_deleted', array(
			'key_hash'    => $key_hash,
			'cache_group' => $group,
			'message'     => sprintf( 'Cache entry deleted: group=%s hash=%s', $group, $key_hash ),
		) );

		return 1;
	}

	/**
	 * Delete multiple cache entries by key_hash list.
	 *
	 * @param array $key_hashes Array of SHA-256 hashes.
	 * @return int Number of entries deleted.
	 */
	public function delete_entries_bulk( array $key_hashes ): int {
		$deleted = 0;

		foreach ($key_hashes as $hash) {
			$deleted += $this->delete_entry( sanitize_text_field( $hash ) );
		}

		return $deleted;
	}

	/**
	 * Flush all entries in a cache group.
	 *
	 * Uses the driver if it supports delete_group; otherwise falls back to
	 * iterating index entries and deleting individually.
	 *
	 * @param string $group Cache group name.
	 * @return int Approximate count of entries affected.
	 */
	public function flush_group( string $group ): int {
		$driver  = $this->resolve_active_driver();
		$affected = 0;

		if ($driver instanceof AIPS_Cache_Monitorable_Driver && $driver->get_monitor_capabilities()['delete_group']) {
			$driver->delete_group( $group );
			$affected = $this->repository->delete_index_group( $group );
		} else {
			// Fallback: iterate index entries for the group.
			$entries  = $this->repository->get_index_entries( array( 'group' => $group ), 'updated_at', 'DESC', 500, 1 );
			$affected = count( $entries );

			foreach ($entries as $entry) {
				$this->cache->delete( $entry['cache_key'], $group );
			}

			$this->repository->delete_index_group( $group );
		}

		$this->log_event( 'group_flushed', array(
			'cache_group'    => $group,
			'affected_count' => $affected,
			'message'        => sprintf( 'Cache group flushed: %s (%d entries)', $group, $affected ),
		) );

		return $affected;
	}

	/**
	 * Invalidate a cache tag by bumping its version.
	 *
	 * @param string $tag Tag name.
	 * @return int New tag version.
	 */
	public function invalidate_tag( string $tag ): int {
		$new_version = $this->cache->bump_tag_version( $tag, 'default' );

		$this->log_event( 'tag_invalidated', array(
			'tags'    => $tag,
			'message' => sprintf( 'Cache tag invalidated: %s (new version: %d)', $tag, $new_version ),
		) );

		return $new_version;
	}

	/**
	 * Invalidate a dependency domain by bumping all its tags.
	 *
	 * @param string $domain Domain name.
	 * @param array  $context Optional context (author_id, topic_id, etc.).
	 * @return array<string, int> Map of tag => new version.
	 */
	public function invalidate_domain( string $domain, array $context = array() ): array {
		$tags    = AIPS_Repository_Cache_Dependencies::tags_for_invalidation( $domain, $context );
		$bumped  = array();

		foreach ($tags as $tag) {
			$bumped[ $tag ] = $this->cache->bump_tag_version( $tag, 'default' );
		}

		$this->log_event( 'domain_invalidated', array(
			'domain'         => $domain,
			'tags'           => implode( ',', $tags ),
			'affected_count' => count( $tags ),
			'message'        => sprintf( 'Cache domain invalidated: %s (%d tags bumped)', $domain, count( $tags ) ),
		) );

		return $bumped;
	}

	/**
	 * Flush all expired entries from the index (and optionally from the DB cache table).
	 *
	 * @return int Number of index rows pruned.
	 */
	public function flush_expired(): int {
		$pruned = $this->cache_index->prune_expired();

		// Also prune the DB cache table when that driver is active.
		if (AIPS_Config::get_instance()->get_option( 'aips_cache_driver', 'array' ) === 'db') {
			$driver = $this->resolve_active_driver();
			if ($driver instanceof AIPS_Cache_Db_Driver) {
				$driver->purge_expired();
			}
		}

		$this->log_event( 'expired_flushed', array(
			'affected_count' => $pruned,
			'message'        => sprintf( 'Expired cache entries flushed: %d index rows removed', $pruned ),
		) );

		return $pruned;
	}

	/**
	 * Flush all plugin-owned cache.
	 *
	 * Delegates to AIPS_Cache::flush() which internally calls driver->flush().
	 * Never touches non-plugin cache data.
	 *
	 * @return bool
	 */
	public function flush_all_plugin_cache(): bool {
		$result = $this->cache->flush();
		$this->cache_index->record_flush();

		$this->log_event( 'flush_all', array(
			'user_id' => get_current_user_id(),
			'message' => 'All plugin-owned cache flushed by user ID ' . get_current_user_id(),
		) );

		return $result;
	}

	/**
	 * Reset the cache index metadata table.
	 *
	 * @return void
	 */
	public function reset_cache_index(): void {
		$this->cache_index->record_flush();

		$this->log_event( 'index_reset', array(
			'user_id' => get_current_user_id(),
			'message' => 'Cache index reset by user ID ' . get_current_user_id(),
		) );
	}

	// -----------------------------------------------------------------------
	// Driver info
	// -----------------------------------------------------------------------

	/**
	 * Return driver-specific info for the Driver tab.
	 *
	 * @return array<string, mixed>
	 */
	public function get_driver_info(): array {
		$driver = $this->resolve_active_driver();

		if (!$driver instanceof AIPS_Cache_Monitorable_Driver) {
			return array(
				'driver'   => 'unknown',
				'label'    => __( 'Unknown Driver', 'ai-post-scheduler' ),
				'error'    => __( 'Active driver does not implement AIPS_Cache_Monitorable_Driver.', 'ai-post-scheduler' ),
			);
		}

		return $driver->get_driver_info();
	}

	// -----------------------------------------------------------------------
	// Maintenance
	// -----------------------------------------------------------------------

	/**
	 * Run scheduled maintenance tasks.
	 *
	 * Called both from the maintenance cron and from the Maintenance tab action.
	 *
	 * @return array<string, int> Map of task => rows affected.
	 */
	public function run_maintenance(): array {
		$config          = AIPS_Config::get_instance();
		$retention_days  = (int) $config->get_option( 'aips_cache_monitor_event_retention_days', 30 );
		$cutoff          = AIPS_DateTime::now()->timestamp() - ($retention_days * DAY_IN_SECONDS);

		$pruned_index    = $this->cache_index->prune_expired();
		$pruned_orphans  = $this->cache_index->prune_orphans();
		$pruned_events   = $this->repository->prune_events( $cutoff );

		$this->log_event( 'maintenance_run', array(
			'affected_count' => $pruned_index + $pruned_orphans + $pruned_events,
			'message'        => sprintf(
				'Maintenance run: %d expired index rows, %d orphaned rows, %d old events pruned',
				$pruned_index,
				$pruned_orphans,
				$pruned_events
			),
		) );

		return array(
			'pruned_index'   => $pruned_index,
			'pruned_orphans' => $pruned_orphans,
			'pruned_events'  => $pruned_events,
		);
	}

	/**
	 * Rebuild the cache index from the DB cache table.
	 *
	 * @return int Rows inserted/updated.
	 */
	public function rebuild_index(): int {
		$inserted = $this->cache_index->rebuild_from_db();

		$this->log_event( 'index_rebuilt', array(
			'affected_count' => $inserted,
			'message'        => sprintf( 'Cache index rebuilt from DB: %d rows', $inserted ),
		) );

		return $inserted;
	}

	/**
	 * Export a diagnostics bundle as an array (caller serializes to JSON).
	 *
	 * @return array<string, mixed>
	 */
	public function export_diagnostics(): array {
		return array(
			'generated_at'  => gmdate( 'c' ),
			'plugin_version' => AIPS_VERSION,
			'summary'       => $this->get_summary(),
			'driver_info'   => $this->get_driver_info(),
			'tags'          => $this->list_tags(),
			'domains'       => $this->list_domains(),
			'operations'    => $this->get_operations(),
		);
	}

	// -----------------------------------------------------------------------
	// Internal helpers
	// -----------------------------------------------------------------------

	/**
	 * Resolve the active cache driver instance.
	 *
	 * @return AIPS_Cache_Driver|null
	 */
	private function resolve_active_driver(): ?AIPS_Cache_Driver {
		try {
			return AIPS_Cache_Factory::make_driver();
		} catch ( Throwable $e ) {
			return null;
		}
	}

	/**
	 * Return a human-readable label for a driver identifier.
	 *
	 * @param string $driver_name Driver identifier string.
	 * @return string
	 */
	private function get_driver_label( string $driver_name ): string {
		$labels = array(
			'array'          => __( 'In-Memory Array', 'ai-post-scheduler' ),
			'db'             => __( 'Database', 'ai-post-scheduler' ),
			'redis'          => __( 'Redis', 'ai-post-scheduler' ),
			'wp_object_cache' => __( 'WP Object Cache', 'ai-post-scheduler' ),
			'session'        => __( 'PHP Session', 'ai-post-scheduler' ),
		);

		return $labels[ $driver_name ] ?? $driver_name;
	}

	/**
	 * Log a cache monitor event through the repository.
	 *
	 * @param string $event_type Event type identifier.
	 * @param array  $context    Event context/metadata.
	 * @return void
	 */
	private function log_event( string $event_type, array $context = array() ): void {
		try {
			$context['event_type'] = $event_type;
			$context['user_id']    = $context['user_id'] ?? get_current_user_id();
			$this->repository->insert_event( $context );
		} catch ( Throwable $e ) {
			// Event logging must never block cache operations.
		}
	}

	/**
	 * Produce a safe, truncated preview of a cached value.
	 *
	 * @param mixed $value Cached value.
	 * @return mixed Safe preview.
	 */
	private function safe_preview( $value ) {
		$preview_length = (int) AIPS_Config::get_instance()->get_option( 'aips_cache_monitor_preview_length', 500 );

		if (is_array( $value )) {
			$keys    = array_keys( $value );
			$summary = array();

			foreach (array_slice( $keys, 0, 10 ) as $key ) {
				if ($this->is_sensitive_field( (string) $key )) {
					$summary[ $key ] = '[REDACTED]';
					continue;
				}

				$v = $value[ $key ];

				if (is_scalar( $v )) {
					$summary[ $key ] = is_string( $v ) ? substr( $v, 0, 80 ) : $v;
				} elseif (is_array( $v )) {
					$summary[ $key ] = '[array(' . count( $v ) . ')]';
				} elseif (is_object( $v )) {
					$summary[ $key ] = '[object:' . get_class( $v ) . ']';
				} else {
					$summary[ $key ] = '[' . gettype( $v ) . ']';
				}
			}

			if (count( $keys ) > 10) {
				$summary['...'] = sprintf( '(+%d more keys)', count( $keys ) - 10 );
			}

			return $summary;
		}

		if (is_object( $value )) {
			return array(
				'__class__' => get_class( $value ),
				'__note__'  => __( 'Object preview not available — enable dev mode for full inspection.', 'ai-post-scheduler' ),
			);
		}

		if (is_string( $value )) {
			$truncated = strlen( $value ) > $preview_length
				? substr( $value, 0, $preview_length ) . '…'
				: $value;

			return $truncated;
		}

		return $value;
	}

	/**
	 * Recursively redact sensitive fields from an array or object.
	 *
	 * @param mixed $value Value to redact.
	 * @param int   $depth Current recursion depth (max 5).
	 * @return mixed
	 */
	private function redact_value( $value, int $depth = 0 ) {
		if ($depth > 5) {
			return '…';
		}

		if (is_array( $value )) {
			$result = array();
			foreach ($value as $k => $v) {
				$result[ $k ] = $this->is_sensitive_field( (string) $k )
					? '[REDACTED]'
					: $this->redact_value( $v, $depth + 1 );
			}

			return $result;
		}

		if (is_object( $value )) {
			$clone = clone $value;
			foreach (get_object_vars( $clone ) as $k => $v) {
				$clone->$k = $this->is_sensitive_field( (string) $k )
					? '[REDACTED]'
					: $this->redact_value( $v, $depth + 1 );
			}

			return $clone;
		}

		return $value;
	}

	/**
	 * Determine whether a field name is considered sensitive.
	 *
	 * @param string $field_name Field name (case-insensitive).
	 * @return bool
	 */
	private function is_sensitive_field( string $field_name ): bool {
		$lower = strtolower( $field_name );

		foreach (self::REDACT_FIELDS as $pattern) {
			if (strpos( $lower, $pattern ) !== false) {
				return true;
			}
		}

		return false;
	}
}
