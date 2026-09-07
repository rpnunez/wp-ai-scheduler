<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Cache_Monitor_Repository
 *
 * All SQL queries for the Cache Monitor subsystem: cache index entries,
 * cache events log, and operation metrics aggregations.
 *
 * No SQL is permitted outside this class for cache-monitor features.
 *
 * @package AI_Post_Scheduler
 * @since   2.9.0
 */
class AIPS_Cache_Monitor_Repository {

	// -----------------------------------------------------------------------
	// Cache Index queries
	// -----------------------------------------------------------------------

	/**
	 * Return paginated + filtered rows from aips_cache_index.
	 *
	 * @param array  $filters  Assoc filter map (group, tier, driver, operation_id, tag, ttl_state, search).
	 * @param string $orderby  Column to order by. Default 'updated_at'.
	 * @param string $order    'ASC' or 'DESC'. Default 'DESC'.
	 * @param int    $per_page Rows per page. Default 50.
	 * @param int    $page     1-based page number. Default 1.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_index_entries( array $filters = array(), string $orderby = 'updated_at', string $order = 'DESC', int $per_page = 50, int $page = 1 ): array {
		global $wpdb;

		$table  = $wpdb->prefix . 'aips_cache_index';
		$now    = AIPS_DateTime::now()->timestamp();
		$offset = max( 0, ($page - 1) * $per_page );
		$limit  = max( 1, min( 500, $per_page ) );

		$allowed_orderby = array( 'updated_at', 'created_at', 'expires_at', 'value_size', 'cache_group', 'operation_id', 'tier', 'driver' );
		$orderby         = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'updated_at';
		$order           = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

		list($where_sql, $params) = $this->build_index_where( $filters, $now );

		$params[] = $limit;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				...$params
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count rows in aips_cache_index matching the given filters.
	 *
	 * @param array $filters Filter map.
	 * @return int
	 */
	public function count_index_entries( array $filters = array() ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'aips_cache_index';
		$now   = AIPS_DateTime::now()->timestamp();

		list($where_sql, $params) = $this->build_index_where( $filters, $now );

		if (empty( $params )) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}" );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}", ...$params ) );
		}

		return (int) $count;
	}

	/**
	 * Get aggregate summary stats from aips_cache_index.
	 *
	 * @return array<string, mixed>
	 */
	public function get_index_summary(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'aips_cache_index';
		$now   = AIPS_DateTime::now()->timestamp();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$totals = $wpdb->get_row(
			"SELECT COUNT(*) as total_entries, COALESCE(SUM(value_size),0) as total_size FROM `{$table}`",
			ARRAY_A
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$expired_count = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE expires_at > 0 AND expires_at < %d", $now )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$by_group = $wpdb->get_results(
			"SELECT cache_group, COUNT(*) as cnt, COALESCE(SUM(value_size),0) as sz FROM `{$table}` GROUP BY cache_group ORDER BY cnt DESC LIMIT 20",
			ARRAY_A
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$by_tier = $wpdb->get_results(
			"SELECT tier, COUNT(*) as cnt, COALESCE(SUM(value_size),0) as sz FROM `{$table}` GROUP BY tier ORDER BY cnt DESC",
			ARRAY_A
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$by_driver = $wpdb->get_results(
			"SELECT driver, COUNT(*) as cnt FROM `{$table}` GROUP BY driver ORDER BY cnt DESC",
			ARRAY_A
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$largest = $wpdb->get_results(
			"SELECT key_hash, cache_group, operation_id, value_size, value_type FROM `{$table}` ORDER BY value_size DESC LIMIT 10",
			ARRAY_A
		);

		return array(
			'total_entries' => (int) ($totals['total_entries'] ?? 0),
			'total_size'    => (int) ($totals['total_size'] ?? 0),
			'expired_count' => $expired_count,
			'by_group'      => is_array( $by_group ) ? $by_group : array(),
			'by_tier'       => is_array( $by_tier ) ? $by_tier : array(),
			'by_driver'     => is_array( $by_driver ) ? $by_driver : array(),
			'largest'       => is_array( $largest ) ? $largest : array(),
		);
	}

	/**
	 * Return a single index row by key_hash.
	 *
	 * @param string $key_hash SHA-256 hash.
	 * @return array<string, mixed>|null
	 */
	public function get_index_entry_by_hash( string $key_hash ): ?array {
		global $wpdb;

		$table = $wpdb->prefix . 'aips_cache_index';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE key_hash = %s LIMIT 1", $key_hash ),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Delete a single index entry by key_hash.
	 *
	 * @param string $key_hash SHA-256 hash.
	 * @return bool
	 */
	public function delete_index_entry( string $key_hash ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'aips_cache_index';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $table, array( 'key_hash' => $key_hash ), array( '%s' ) );

		return true;
	}

	/**
	 * Delete multiple index entries by key_hash list.
	 *
	 * @param array $key_hashes Array of SHA-256 hashes.
	 * @return int
	 */
	public function delete_index_entries_bulk( array $key_hashes ): int {
		if (empty( $key_hashes )) {
			return 0;
		}

		global $wpdb;
		$table       = $wpdb->prefix . 'aips_cache_index';
		$placeholders = implode( ',', array_fill( 0, count( $key_hashes ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query(
			$wpdb->prepare( "DELETE FROM `{$table}` WHERE key_hash IN ({$placeholders})", ...$key_hashes )
		);

		return (int) $deleted;
	}

	/**
	 * Delete all index entries for a given cache group.
	 *
	 * @param string $group Cache group name.
	 * @return int Number of rows deleted.
	 */
	public function delete_index_group( string $group ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'aips_cache_index';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = $wpdb->delete( $table, array( 'cache_group' => $group ), array( '%s' ) );

		return (int) $deleted;
	}

	/**
	 * List all distinct tags from the index with entry counts.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function list_tags(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'aips_cache_index';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT tags, COUNT(*) as entry_count FROM `{$table}` WHERE tags != '' GROUP BY tags ORDER BY entry_count DESC LIMIT 200",
			ARRAY_A
		);

		if (!is_array( $rows )) {
			return array();
		}

		// Explode comma-separated tag strings into individual tag counts.
		$tag_map = array();
		foreach ($rows as $row) {
			$entry_count = (int) $row['entry_count'];
			foreach (explode( ',', $row['tags'] ) as $tag) {
				$tag = trim( $tag );
				if ('' === $tag) {
					continue;
				}

				if (!isset( $tag_map[ $tag ] )) {
					$tag_map[ $tag ] = 0;
				}

				$tag_map[ $tag ] += $entry_count;
			}
		}

		arsort( $tag_map );

		$result = array();
		foreach ($tag_map as $tag => $count) {
			$result[] = array( 'tag' => $tag, 'entry_count' => $count );
		}

		return array_slice( $result, 0, 100 );
	}

	/**
	 * Return distinct operation IDs with aggregate stats from the index.
	 *
	 * @param array $filters Optional filters (repository_class, tier).
	 * @return array<int, array<string, mixed>>
	 */
	public function list_operations( array $filters = array() ): array {
		global $wpdb;

		$table  = $wpdb->prefix . 'aips_cache_index';
		$where  = array( "operation_id != ''" );
		$params = array();

		if (!empty( $filters['repository_class'] )) {
			$where[]  = 'repository_class = %s';
			$params[] = sanitize_text_field( $filters['repository_class'] );
		}

		if (!empty( $filters['tier'] )) {
			$where[]  = 'tier = %s';
			$params[] = sanitize_key( $filters['tier'] );
		}

		$where_sql = implode( ' AND ', $where );

		$sql = "SELECT operation_id, repository_class, tier, COUNT(*) as index_count, COALESCE(SUM(value_size),0) as total_size, MAX(updated_at) as last_updated FROM `{$table}` WHERE {$where_sql} GROUP BY operation_id, repository_class, tier ORDER BY index_count DESC LIMIT 200";

		if (empty( $params )) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $sql, ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
		}

		return is_array( $rows ) ? $rows : array();
	}

	// -----------------------------------------------------------------------
	// Cache Events queries
	// -----------------------------------------------------------------------

	/**
	 * Insert a cache event row.
	 *
	 * @param array $event Event data.
	 * @return bool
	 */
	public function insert_event( array $event ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'aips_cache_events';
		$now   = AIPS_DateTime::now()->timestamp();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$table,
			array(
				'event_type'     => sanitize_key( $event['event_type'] ?? '' ),
				'user_id'        => (int) ($event['user_id'] ?? get_current_user_id()),
				'correlation_id' => sanitize_text_field( $event['correlation_id'] ?? '' ),
				'cache_group'    => sanitize_key( $event['cache_group'] ?? '' ),
				'key_hash'       => sanitize_text_field( $event['key_hash'] ?? '' ),
				'operation_id'   => sanitize_text_field( $event['operation_id'] ?? '' ),
				'tags'           => sanitize_text_field( $event['tags'] ?? '' ),
				'domain'         => sanitize_text_field( $event['domain'] ?? '' ),
				'affected_count' => (int) ($event['affected_count'] ?? 0),
				'elapsed_ms'     => (float) ($event['elapsed_ms'] ?? 0),
				'message'        => sanitize_text_field( $event['message'] ?? '' ),
				'created_at'     => $now,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Return a paginated list of cache events.
	 *
	 * @param array $filters  Filter map (event_type, user_id).
	 * @param int   $per_page Rows per page.
	 * @param int   $page     1-based page number.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_events( array $filters = array(), int $per_page = 50, int $page = 1 ): array {
		global $wpdb;

		$table  = $wpdb->prefix . 'aips_cache_events';
		$offset = max( 0, ($page - 1) * $per_page );
		$limit  = max( 1, min( 200, $per_page ) );
		$where  = array( '1=1' );
		$params = array();

		if (!empty( $filters['event_type'] )) {
			$where[]  = 'event_type = %s';
			$params[] = sanitize_key( $filters['event_type'] );
		}

		if (!empty( $filters['user_id'] )) {
			$where[]  = 'user_id = %d';
			$params[] = (int) $filters['user_id'];
		}

		$where_sql = implode( ' AND ', $where );
		$params[]  = $limit;
		$params[]  = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d", ...$params ),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count cache events.
	 *
	 * @param array $filters Filter map.
	 * @return int
	 */
	public function count_events( array $filters = array() ): int {
		global $wpdb;

		$table  = $wpdb->prefix . 'aips_cache_events';
		$where  = array( '1=1' );
		$params = array();

		if (!empty( $filters['event_type'] )) {
			$where[]  = 'event_type = %s';
			$params[] = sanitize_key( $filters['event_type'] );
		}

		$where_sql = implode( ' AND ', $where );

		if (empty( $params )) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}" );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}", ...$params ) );
		}

		return (int) $count;
	}

	/**
	 * Delete cache events older than a given timestamp.
	 *
	 * @param int $before_timestamp Unix timestamp cutoff.
	 * @return int Rows deleted.
	 */
	public function prune_events( int $before_timestamp ): int {
		global $wpdb;

		$table   = $wpdb->prefix . 'aips_cache_events';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE created_at < %d", $before_timestamp ) );

		return (int) $deleted;
	}

	// -----------------------------------------------------------------------
	// Internal helpers
	// -----------------------------------------------------------------------

	/**
	 * Build the WHERE clause and params array for index queries.
	 *
	 * @param array $filters Filter map.
	 * @param int   $now     Current Unix timestamp.
	 * @return array{0: string, 1: array}
	 */
	private function build_index_where( array $filters, int $now ): array {
		$where  = array( '1=1' );
		$params = array();

		if (!empty( $filters['group'] )) {
			$where[]  = 'cache_group = %s';
			$params[] = sanitize_key( $filters['group'] );
		}

		if (!empty( $filters['tier'] )) {
			$where[]  = 'tier = %s';
			$params[] = sanitize_key( $filters['tier'] );
		}

		if (!empty( $filters['driver'] )) {
			$where[]  = 'driver = %s';
			$params[] = sanitize_key( $filters['driver'] );
		}

		if (!empty( $filters['operation_id'] )) {
			$where[]  = 'operation_id = %s';
			$params[] = sanitize_text_field( $filters['operation_id'] );
		}

		if (!empty( $filters['repository_class'] )) {
			$where[]  = 'repository_class = %s';
			$params[] = sanitize_text_field( $filters['repository_class'] );
		}

		if (!empty( $filters['tag'] )) {
			$where[]  = 'FIND_IN_SET(%s, tags) > 0';
			$params[] = sanitize_text_field( $filters['tag'] );
		}

		if (!empty( $filters['ttl_state'] )) {
			switch ($filters['ttl_state']) {
				case 'expired':
					$where[]  = 'expires_at > 0 AND expires_at < %d';
					$params[] = $now;
					break;
				case 'active':
					$where[]  = '(expires_at = 0 OR expires_at >= %d)';
					$params[] = $now;
					break;
				case 'no_expiration':
					$where[] = 'expires_at = 0';
					break;
			}
		}

		if (!empty( $filters['search'] )) {
			$like     = '%' . $GLOBALS['wpdb']->esc_like( sanitize_text_field( $filters['search'] ) ) . '%';
			$where[]  = '(key_hash LIKE %s OR operation_id LIKE %s OR cache_group LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		return array( implode( ' AND ', $where ), $params );
	}
}
