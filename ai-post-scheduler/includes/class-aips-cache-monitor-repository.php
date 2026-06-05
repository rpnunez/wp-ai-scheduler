<?php
if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Cache_Monitor_Repository {

	private $index_table;
	private $events_table;
	private $metrics_table;
	private $cache_table;

	public function __construct() {
		global $wpdb;
		$this->index_table   = $wpdb->prefix . 'aips_cache_index';
		$this->events_table  = $wpdb->prefix . 'aips_cache_monitor_events';
		$this->metrics_table = $wpdb->prefix . 'aips_cache_monitor_metrics';
		$this->cache_table   = $wpdb->prefix . 'aips_cache';
	}

	public function upsert_index( array $entry ) {
		global $wpdb;
		$now       = $this->now();
		$key       = isset( $entry['cache_key'] ) ? (string) $entry['cache_key'] : '';
		$group     = isset( $entry['cache_group'] ) ? (string) $entry['cache_group'] : 'default';
		$existing  = $this->get_entry_by_key( $key, $group, true );
		$created   = ( $existing && !empty( $existing['created_at'] ) ) ? (int) $existing['created_at'] : $now;
		$ttl       = isset( $entry['ttl'] ) ? max( 0, (int) $entry['ttl'] ) : 0;
		$expires   = isset( $entry['expires_at'] ) ? (int) $entry['expires_at'] : ( $ttl > 0 ? $now + $ttl : 0 );
		$tags      = isset( $entry['tags'] ) ? $this->encode_json_array( $entry['tags'] ) : '[]';
		$data      = array(
			'cache_key'        => $key,
			'key_hash'         => isset( $entry['key_hash'] ) ? (string) $entry['key_hash'] : $this->hash_key( $key ),
			'cache_group'      => $group,
			'driver'           => isset( $entry['driver'] ) ? sanitize_key( $entry['driver'] ) : '',
			'tier'             => isset( $entry['tier'] ) ? sanitize_key( $entry['tier'] ) : 'default',
			'operation_id'     => isset( $entry['operation_id'] ) ? sanitize_text_field( $entry['operation_id'] ) : '',
			'repository_class' => isset( $entry['repository_class'] ) ? sanitize_text_field( $entry['repository_class'] ) : '',
			'tags'             => $tags,
			'domain'           => isset( $entry['domain'] ) ? sanitize_key( $entry['domain'] ) : '',
			'source'           => isset( $entry['source'] ) ? sanitize_key( $entry['source'] ) : '',
			'ttl'              => $ttl,
			'created_at'       => $created,
			'updated_at'       => $now,
			'expires_at'       => $expires,
			'last_accessed_at' => isset( $entry['last_accessed_at'] ) ? (int) $entry['last_accessed_at'] : 0,
			'estimated_size'   => isset( $entry['estimated_size'] ) ? max( 0, (int) $entry['estimated_size'] ) : 0,
			'value_type'       => isset( $entry['value_type'] ) ? sanitize_key( $entry['value_type'] ) : '',
		);

		$result = $wpdb->replace(
			$this->index_table,
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s' )
		);
		return false !== $result;
	}

	public function touch_access( $key, $group = 'default' ) {
		global $wpdb;
		return false !== $wpdb->update(
			$this->index_table,
			array( 'last_accessed_at' => $this->now() ),
			array( 'cache_key' => (string) $key, 'cache_group' => (string) $group ),
			array( '%d' ),
			array( '%s', '%s' )
		);
	}

	public function delete_index( $key, $group = 'default' ) {
		global $wpdb;
		return (int) $wpdb->delete( $this->index_table, array( 'cache_key' => (string) $key, 'cache_group' => (string) $group ), array( '%s', '%s' ) );
	}

	public function delete_by_hash( $key_hash, $group = '' ) {
		$entry = $this->get_entry_by_hash( $key_hash, $group, true );
		if (!$entry) {
			return 0;
		}
		return $this->delete_index( $entry['cache_key'], $entry['cache_group'] );
	}

	public function delete_group( $group ) {
		global $wpdb;
		return (int) $wpdb->delete( $this->index_table, array( 'cache_group' => (string) $group ), array( '%s' ) );
	}

	public function reset_index() {
		global $wpdb;
		return (int) $wpdb->query( "TRUNCATE TABLE `{$this->index_table}`" );
	}

	public function list_entries( array $filters = array(), $limit = 100, $offset = 0, $orderby = 'updated_at', $order = 'DESC' ) {
		global $wpdb;
		$where = $this->build_entry_where( $filters );
		$allowed_orderby = array( 'estimated_size', 'ttl', 'created_at', 'expires_at', 'cache_group', 'operation_id', 'updated_at', 'last_accessed_at', 'driver', 'tier' );
		$orderby = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'updated_at';
		$order   = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';
		$limit   = max( 1, min( 500, (int) $limit ) );
		$offset  = max( 0, (int) $offset );
		$sql     = "SELECT * FROM `{$this->index_table}` {$where['sql']} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$args    = array_merge( $where['args'], array( $limit, $offset ) );
		$rows    = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		return array_map( array( $this, 'normalize_entry' ), is_array( $rows ) ? $rows : array() );
	}

	public function count_entries( array $filters = array() ) {
		global $wpdb;
		$where = $this->build_entry_where( $filters );
		$sql   = "SELECT COUNT(*) FROM `{$this->index_table}` {$where['sql']}";
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $where['args'] ) );
	}

	public function get_entry_by_key( $key, $group = 'default', $include_cache_key = false ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$this->index_table}` WHERE cache_key = %s AND cache_group = %s LIMIT 1", (string) $key, (string) $group ),
			ARRAY_A
		);
		return $row ? $this->normalize_entry( $row, $include_cache_key ) : null;
	}

	public function get_entry_by_hash( $key_hash, $group = '', $include_cache_key = false ) {
		global $wpdb;
		if ($group !== '') {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$this->index_table}` WHERE key_hash = %s AND cache_group = %s LIMIT 1", (string) $key_hash, (string) $group ), ARRAY_A );
		} else {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$this->index_table}` WHERE key_hash = %s LIMIT 1", (string) $key_hash ), ARRAY_A );
		}
		return $row ? $this->normalize_entry( $row, $include_cache_key ) : null;
	}

	public function get_summary_counts() {
		global $wpdb;
		$now = $this->now();
		return array(
			'total_entries' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$this->index_table}`" ),
			'total_size'    => (int) $wpdb->get_var( "SELECT COALESCE(SUM(estimated_size),0) FROM `{$this->index_table}`" ),
			'expired'       => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$this->index_table}` WHERE expires_at > 0 AND expires_at < %d", $now ) ),
		);
	}

	public function get_group_counts( $field ) {
		global $wpdb;
		$allowed = array( 'cache_group', 'tier', 'operation_id', 'repository_class', 'driver', 'domain' );
		if (!in_array( $field, $allowed, true )) {
			return array();
		}
		$rows = $wpdb->get_results( "SELECT {$field} AS item_key, COUNT(*) AS total, COALESCE(SUM(estimated_size),0) AS size_bytes FROM `{$this->index_table}` GROUP BY {$field} ORDER BY total DESC LIMIT 50", ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	public function get_largest_entries( $limit = 10 ) {
		return $this->list_entries( array(), $limit, 0, 'estimated_size', 'DESC' );
	}

	public function list_tags() {
		$entries = $this->list_entries( array(), 500, 0 );
		$tags = array();
		foreach ($entries as $entry) {
			foreach ((array) $entry['tags'] as $tag) {
				if (!isset( $tags[ $tag ] )) {
					$tags[ $tag ] = array( 'tag' => $tag, 'entry_count' => 0, 'estimated_size' => 0, 'last_invalidated' => 0, 'operation_ids' => array() );
				}
				$tags[ $tag ]['entry_count']++;
				$tags[ $tag ]['estimated_size'] += (int) $entry['estimated_size'];
				if (!empty( $entry['operation_id'] )) {
					$tags[ $tag ]['operation_ids'][ $entry['operation_id'] ] = $entry['operation_id'];
				}
			}
		}
		foreach ($tags as $tag => $data) {
			$event = $this->latest_event( 'tag_invalidated', array( 'tag' => $tag ) );
			$tags[ $tag ]['last_invalidated'] = $event ? (int) $event['created_at'] : 0;
			$tags[ $tag ]['version'] = (int) get_option( 'aips_cache_tag_version_' . sanitize_key( $tag ), 1 );
			$tags[ $tag ]['operation_ids'] = array_values( $tags[ $tag ]['operation_ids'] );
		}
		return array_values( $tags );
	}

	public function delete_entries_with_tag( $tag ) {
		global $wpdb;
		$like = '%' . $wpdb->esc_like( '"' . (string) $tag . '"' ) . '%';
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM `{$this->index_table}` WHERE tags LIKE %s", $like ) );
	}

	public function prune_expired_index() {
		global $wpdb;
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM `{$this->index_table}` WHERE expires_at > 0 AND expires_at < %d", $this->now() ) );
	}

	public function prune_old_events( $retention_days ) {
		global $wpdb;
		$cutoff = $this->now() - ( max( 1, (int) $retention_days ) * DAY_IN_SECONDS );
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM `{$this->events_table}` WHERE created_at > 0 AND created_at < %d", $cutoff ) );
	}

	public function prune_expired_db_cache() {
		global $wpdb;
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM `{$this->cache_table}` WHERE expires_at > 0 AND expires_at < %d", $this->now() ) );
	}

	public function rebuild_index_from_db_cache( $driver = 'db' ) {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT cache_key, cache_group, value, expires_at, updated_at FROM `{$this->cache_table}` LIMIT 1000", ARRAY_A );
		$count = 0;
		foreach ((array) $rows as $row) {
			$count += $this->upsert_index( array(
				'cache_key' => $row['cache_key'],
				'cache_group' => $row['cache_group'],
				'driver' => $driver,
				'ttl' => !empty( $row['expires_at'] ) ? max( 0, (int) $row['expires_at'] - $this->now() ) : 0,
				'expires_at' => (int) $row['expires_at'],
				'updated_at' => (int) $row['updated_at'],
				'estimated_size' => strlen( (string) $row['value'] ),
				'value_type' => 'serialized',
				'source' => 'db_rebuild',
			) ) ? 1 : 0;
		}
		return $count;
	}

	public function add_event( array $event ) {
		global $wpdb;
		$data = array(
			'event_type'     => isset( $event['event_type'] ) ? sanitize_key( $event['event_type'] ) : 'cache_event',
			'user_id'        => isset( $event['user_id'] ) ? (int) $event['user_id'] : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 ),
			'correlation_id' => isset( $event['correlation_id'] ) ? sanitize_text_field( $event['correlation_id'] ) : ( class_exists( 'AIPS_Correlation_ID' ) ? AIPS_Correlation_ID::get() : '' ),
			'cache_group'    => isset( $event['cache_group'] ) ? sanitize_text_field( $event['cache_group'] ) : '',
			'key_hash'       => isset( $event['key_hash'] ) ? sanitize_text_field( $event['key_hash'] ) : '',
			'operation_id'   => isset( $event['operation_id'] ) ? sanitize_text_field( $event['operation_id'] ) : '',
			'tags'           => isset( $event['tags'] ) ? $this->encode_json_array( $event['tags'] ) : '[]',
			'domain'         => isset( $event['domain'] ) ? sanitize_key( $event['domain'] ) : '',
			'affected_count' => isset( $event['affected_count'] ) ? (int) $event['affected_count'] : 0,
			'elapsed_ms'     => isset( $event['elapsed_ms'] ) ? (float) $event['elapsed_ms'] : 0,
			'message'        => isset( $event['message'] ) ? sanitize_textarea_field( $event['message'] ) : '',
			'context'        => isset( $event['context'] ) ? wp_json_encode( $event['context'] ) : '{}',
			'created_at'     => $this->now(),
		);
		$result = $wpdb->insert( $this->events_table, $data, array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%d' ) );
		return false !== $result;
	}

	public function list_events( array $filters = array(), $limit = 50, $offset = 0 ) {
		global $wpdb;
		$where = array( '1=1' );
		$args  = array();
		if (!empty( $filters['event_type'] )) {
			$where[] = 'event_type = %s';
			$args[] = sanitize_key( $filters['event_type'] );
		}
		$limit = max( 1, min( 200, (int) $limit ) );
		$offset = max( 0, (int) $offset );
		$sql = "SELECT * FROM `{$this->events_table}` WHERE " . implode( ' AND ', $where ) . ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $args, array( $limit, $offset ) ) ), ARRAY_A );
		return array_map( array( $this, 'normalize_event' ), is_array( $rows ) ? $rows : array() );
	}

	public function get_metrics( array $filters = array(), $limit = 100 ) {
		global $wpdb;
		$where = array( '1=1' );
		$args = array();
		if (!empty( $filters['repository'] )) {
			$where[] = 'repository_class = %s';
			$args[] = sanitize_text_field( $filters['repository'] );
		}
		if (!empty( $filters['tier'] )) {
			$where[] = 'tier = %s';
			$args[] = sanitize_key( $filters['tier'] );
		}
		$limit = max( 1, min( 200, (int) $limit ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$this->metrics_table}` WHERE " . implode( ' AND ', $where ) . ' ORDER BY updated_at DESC LIMIT %d', array_merge( $args, array( $limit ) ) ), ARRAY_A );
		return array_map( array( $this, 'normalize_metric' ), is_array( $rows ) ? $rows : array() );
	}

	public function record_metric_event( $operation_id, array $data ) {
		global $wpdb;
		$operation_id = sanitize_text_field( $operation_id );
		if ($operation_id === '') {
			return false;
		}
		$metric = $this->get_metric( $operation_id );
		$now = $this->now();
		$defaults = array(
			'operation_id' => $operation_id,
			'repository_class' => isset( $data['repository_class'] ) ? sanitize_text_field( $data['repository_class'] ) : '',
			'cache_policy' => isset( $data['cache_policy'] ) ? sanitize_key( $data['cache_policy'] ) : '',
			'tier' => isset( $data['tier'] ) ? sanitize_key( $data['tier'] ) : 'default',
			'ttl' => isset( $data['ttl'] ) ? (int) $data['ttl'] : 0,
			'tags' => isset( $data['tags'] ) ? $this->encode_json_array( $data['tags'] ) : '[]',
			'hit_count' => 0,
			'miss_count' => 0,
			'bypass_count' => 0,
			'stale_count' => 0,
			'invalidation_count' => 0,
			'rebuild_count' => 0,
			'total_rebuild_ms' => 0,
			'max_rebuild_ms' => 0,
			'last_read_at' => 0,
			'last_invalidated_at' => 0,
			'updated_at' => $now,
		);
		$metric = $metric ? array_merge( $defaults, $metric ) : $defaults;
		$type = isset( $data['event'] ) ? sanitize_key( $data['event'] ) : '';
		if ($type === 'hit') { $metric['hit_count']++; $metric['last_read_at'] = $now; }
		if ($type === 'miss') { $metric['miss_count']++; $metric['last_read_at'] = $now; }
		if ($type === 'bypass') { $metric['bypass_count']++; }
		if ($type === 'stale') { $metric['stale_count']++; $metric['last_read_at'] = $now; }
		if ($type === 'invalidation') { $metric['invalidation_count']++; $metric['last_invalidated_at'] = $now; }
		if ($type === 'rebuild') {
			$elapsed = isset( $data['elapsed_ms'] ) ? (float) $data['elapsed_ms'] : 0;
			$metric['rebuild_count']++;
			$metric['total_rebuild_ms'] += $elapsed;
			$metric['max_rebuild_ms'] = max( (float) $metric['max_rebuild_ms'], $elapsed );
		}
		$metric['updated_at'] = $now;
		$result = $wpdb->replace( $this->metrics_table, $metric, array( '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%f', '%f', '%d', '%d', '%d' ) );
		return false !== $result;
	}

	private function get_metric( $operation_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$this->metrics_table}` WHERE operation_id = %s LIMIT 1", $operation_id ), ARRAY_A );
		return $row ? $this->normalize_metric( $row ) : null;
	}

	private function build_entry_where( array $filters ) {
		global $wpdb;
		$where = array( '1=1' );
		$args  = array();
		$equals = array( 'cache_group' => 'group', 'tier' => 'tier', 'driver' => 'driver', 'operation_id' => 'operation_id', 'repository_class' => 'repository', 'domain' => 'domain' );
		foreach ($equals as $column => $key) {
			if (!empty( $filters[ $key ] )) {
				$where[] = "{$column} = %s";
				$args[] = sanitize_text_field( $filters[ $key ] );
			}
		}
		if (!empty( $filters['tag'] )) {
			$where[] = 'tags LIKE %s';
			$args[] = '%' . $wpdb->esc_like( '"' . sanitize_text_field( $filters['tag'] ) . '"' ) . '%';
		}
		if (!empty( $filters['search'] )) {
			$search = '%' . $wpdb->esc_like( sanitize_text_field( $filters['search'] ) ) . '%';
			$where[] = '(key_hash LIKE %s OR cache_group LIKE %s OR operation_id LIKE %s OR tags LIKE %s)';
			$args = array_merge( $args, array( $search, $search, $search, $search ) );
		}
		if (!empty( $filters['ttl_state'] )) {
			$now = $this->now();
			if ($filters['ttl_state'] === 'expired') {
				$where[] = 'expires_at > 0 AND expires_at < %d';
				$args[] = $now;
			} elseif ($filters['ttl_state'] === 'active') {
				$where[] = 'expires_at > %d';
				$args[] = $now;
			} elseif ($filters['ttl_state'] === 'none') {
				$where[] = 'expires_at = 0';
			}
		}
		if (isset( $filters['min_size'] ) && $filters['min_size'] !== '') {
			$where[] = 'estimated_size >= %d';
			$args[] = (int) $filters['min_size'];
		}
		if (isset( $filters['max_size'] ) && $filters['max_size'] !== '') {
			$where[] = 'estimated_size <= %d';
			$args[] = (int) $filters['max_size'];
		}
		return array( 'sql' => 'WHERE ' . implode( ' AND ', $where ), 'args' => $args );
	}

	private function latest_event( $type, array $context = array() ) {
		global $wpdb;
		if (isset( $context['tag'] )) {
			$like = '%' . $wpdb->esc_like( '"' . $context['tag'] . '"' ) . '%';
			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$this->events_table}` WHERE event_type = %s AND tags LIKE %s ORDER BY created_at DESC LIMIT 1", $type, $like ), ARRAY_A );
		}
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$this->events_table}` WHERE event_type = %s ORDER BY created_at DESC LIMIT 1", $type ), ARRAY_A );
	}

	private function normalize_entry( $row, $include_cache_key = false ) {
		$row['tags'] = $this->decode_json_array( isset( $row['tags'] ) ? $row['tags'] : '[]' );
		$row['ttl_remaining'] = $this->ttl_remaining( isset( $row['expires_at'] ) ? (int) $row['expires_at'] : 0 );
		if (!$include_cache_key) {
			unset( $row['cache_key'] );
		}
		return $row;
	}

	private function normalize_event( $row ) {
		$row['tags'] = $this->decode_json_array( isset( $row['tags'] ) ? $row['tags'] : '[]' );
		$row['context'] = json_decode( isset( $row['context'] ) ? $row['context'] : '{}', true );
		if (!is_array( $row['context'] )) {
			$row['context'] = array();
		}
		return $row;
	}

	private function normalize_metric( $row ) {
		$row['tags'] = $this->decode_json_array( isset( $row['tags'] ) ? $row['tags'] : '[]' );
		$total = (int) $row['hit_count'] + (int) $row['miss_count'];
		$row['hit_ratio'] = $total > 0 ? round( ( (int) $row['hit_count'] / $total ) * 100, 2 ) : 0;
		$row['average_rebuild_ms'] = !empty( $row['rebuild_count'] ) ? round( (float) $row['total_rebuild_ms'] / (int) $row['rebuild_count'], 2 ) : 0;
		return $row;
	}

	private function ttl_remaining( $expires_at ) {
		if ($expires_at <= 0) {
			return null;
		}
		return max( 0, $expires_at - $this->now() );
	}

	private function encode_json_array( $value ) {
		if (is_string( $value )) {
			$value = array_filter( array_map( 'trim', explode( ',', $value ) ) );
		}
		if (!is_array( $value )) {
			$value = array();
		}
		$value = array_values( array_unique( array_map( 'sanitize_text_field', $value ) ) );
		return wp_json_encode( $value );
	}

	private function decode_json_array( $value ) {
		$decoded = json_decode( (string) $value, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	private function hash_key( $key ) {
		return hash( 'sha256', (string) $key );
	}

	private function now() {
		return class_exists( 'AIPS_DateTime' ) ? AIPS_DateTime::now()->timestamp() : time();
	}
}
