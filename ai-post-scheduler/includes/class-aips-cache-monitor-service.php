<?php
if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Cache_Monitor_Service {

	private $repository;
	private $cache;

	public function __construct( AIPS_Cache_Monitor_Repository $repository = null, AIPS_Cache $cache = null ) {
		$this->repository = $repository ? $repository : new AIPS_Cache_Monitor_Repository();
		$this->cache = $cache ? $cache : AIPS_Cache_Factory::instance();
	}

	public function get_summary() {
		$counts = $this->repository->get_summary_counts();
		$metrics = $this->repository->get_metrics( array(), 100 );
		$hits = 0; $misses = 0; $bypasses = 0; $stale = 0;
		foreach ($metrics as $metric) {
			$hits += (int) $metric['hit_count'];
			$misses += (int) $metric['miss_count'];
			$bypasses += (int) $metric['bypass_count'];
			$stale += (int) $metric['stale_count'];
		}
		$total_reads = $hits + $misses;
		return array(
			'enabled' => $this->is_cache_enabled(),
			'active_driver' => $this->active_driver_name(),
			'driver_capabilities' => $this->get_driver_capabilities(),
			'total_indexed_entries' => $counts['total_entries'],
			'total_estimated_size' => $counts['total_size'],
			'expired_indexed_entries' => $counts['expired'],
			'entries_by_group' => $this->repository->get_group_counts( 'cache_group' ),
			'entries_by_tier' => $this->repository->get_group_counts( 'tier' ),
			'entries_by_repository_operation' => $this->repository->get_group_counts( 'operation_id' ),
			'largest_entries' => $this->repository->get_largest_entries( 5 ),
			'hit_count' => $hits,
			'miss_count' => $misses,
			'hit_ratio' => $total_reads > 0 ? round( ( $hits / $total_reads ) * 100, 2 ) : 0,
			'bypass_count' => $bypasses,
			'stale_count' => $stale,
			'last_plugin_cache_flush' => (int) get_option( 'aips_cache_monitor_last_flush_at', 0 ),
			'most_invalidated_tags' => $this->list_tags(),
		);
	}

	public function list_entries( array $filters = array(), $limit = 50, $offset = 0, $orderby = 'updated_at', $order = 'DESC' ) {
		return array(
			'items' => $this->repository->list_entries( $filters, $limit, $offset, $orderby, $order ),
			'total' => $this->repository->count_entries( $filters ),
		);
	}

	public function inspect_entry( $key_hash, $group = '' ) {
		$entry = $this->repository->get_entry_by_hash( $key_hash, $group, true );
		if (!$entry) {
			return null;
		}
		$value = null;
		$can_read = true;
		try {
			$value = $this->cache->get( $entry['cache_key'], $entry['cache_group'], null );
			AIPS_Cache_Index::get_instance()->record_access( $entry['cache_key'], $entry['cache_group'] );
		} catch (Throwable $throwable) {
			$can_read = false;
			$this->log_event( 'inspect_failed', array( 'key_hash' => $entry['key_hash'], 'cache_group' => $entry['cache_group'], 'message' => $throwable->getMessage() ) );
		}
		unset( $entry['cache_key'] );
		$entry['safe_preview'] = $can_read ? $this->safe_preview( $value ) : __( 'The active driver could not read this value.', 'ai-post-scheduler' );
		$entry['full_value_available'] = $this->full_value_allowed();
		return $entry;
	}

	public function delete_entry( $key_hash, $group = '' ) {
		$entry = $this->repository->get_entry_by_hash( $key_hash, $group, true );
		if (!$entry) {
			return 0;
		}
		$this->cache->delete( $entry['cache_key'], $entry['cache_group'] );
		$count = $this->repository->delete_index( $entry['cache_key'], $entry['cache_group'] );
		$this->log_event( 'entry_deleted', array( 'key_hash' => $entry['key_hash'], 'cache_group' => $entry['cache_group'], 'affected_count' => $count ) );
		return $count;
	}

	public function delete_selected( array $items ) {
		$count = 0;
		foreach ($items as $item) {
			$key_hash = isset( $item['key_hash'] ) ? sanitize_text_field( $item['key_hash'] ) : '';
			$group = isset( $item['group'] ) ? sanitize_text_field( $item['group'] ) : '';
			if ($key_hash !== '') {
				$count += $this->delete_entry( $key_hash, $group );
			}
		}
		$this->log_event( 'selected_entries_deleted', array( 'affected_count' => $count ) );
		return $count;
	}

	public function flush_group( $group ) {
		$group = sanitize_text_field( $group );
		$entries = $this->repository->list_entries( array( 'group' => $group ), 500, 0 );
		$count = 0;
		foreach ($entries as $entry) {
			$count += $this->delete_entry( $entry['key_hash'], $group );
		}
		if (!$entries && $this->cache->get_driver() instanceof AIPS_Cache_Monitorable_Driver) {
			$this->cache->get_driver()->delete_group( $group );
		}
		$this->log_event( 'group_flushed', array( 'cache_group' => $group, 'affected_count' => $count ) );
		return $count;
	}

	public function invalidate_tag( $tag ) {
		$tag = sanitize_text_field( $tag );
		$version_key = 'aips_cache_tag_version_' . sanitize_key( $tag );
		$version = (int) get_option( $version_key, 1 ) + 1;
		update_option( $version_key, $version, false );
		$count = $this->repository->delete_entries_with_tag( $tag );
		$this->repository->record_metric_event( 'tag:' . $tag, array( 'event' => 'invalidation', 'tags' => array( $tag ) ) );
		$this->log_event( 'tag_invalidated', array( 'tags' => array( $tag ), 'affected_count' => $count ) );
		return array( 'version' => $version, 'affected_count' => $count );
	}

	public function invalidate_domain( $domain ) {
		$domain = sanitize_key( $domain );
		$details = $this->get_domain_details( $domain );
		$count = 0;
		foreach ($details['tags'] as $tag) {
			$result = $this->invalidate_tag( $tag );
			$count += (int) $result['affected_count'];
		}
		$this->log_event( 'domain_invalidated', array( 'domain' => $domain, 'affected_count' => $count, 'context' => $details ) );
		return array( 'affected_count' => $count, 'tags' => $details['tags'] );
	}

	public function flush_expired() {
		$index = $this->repository->prune_expired_index();
		$db = $this->repository->prune_expired_db_cache();
		$this->log_event( 'expired_flushed', array( 'affected_count' => $index + $db, 'context' => array( 'index' => $index, 'db_cache' => $db ) ) );
		return array( 'index' => $index, 'db_cache' => $db );
	}

	public function flush_all_plugin_cache() {
		$result = $this->cache->flush();
		$count = $this->repository->reset_index();
		update_option( 'aips_cache_monitor_last_flush_at', $this->now(), false );
		$this->log_event( 'all_plugin_cache_flushed', array( 'affected_count' => $count, 'context' => array( 'driver_success' => (bool) $result ) ) );
		return $count;
	}

	public function reset_index() {
		$count = $this->repository->reset_index();
		$this->log_event( 'cache_index_reset', array( 'affected_count' => $count ) );
		return $count;
	}

	public function list_tags( array $filters = array() ) {
		$tags = $this->repository->list_tags();
		if (!empty( $filters['search'] )) {
			$search = strtolower( sanitize_text_field( $filters['search'] ) );
			$tags = array_filter( $tags, function( $tag ) use ( $search ) { return strpos( strtolower( $tag['tag'] ), $search ) !== false; } );
		}
		return array_values( $tags );
	}

	public function get_tag_details( $tag ) {
		$tag = sanitize_text_field( $tag );
		foreach ($this->list_tags() as $row) {
			if ($row['tag'] === $tag) {
				return $row;
			}
		}
		return array( 'tag' => $tag, 'version' => (int) get_option( 'aips_cache_tag_version_' . sanitize_key( $tag ), 1 ), 'entry_count' => 0, 'operation_ids' => array() );
	}

	public function list_domains() {
		$domains = array(
			'authors' => array( 'authors', 'author_topics', 'author_posts' ),
			'templates' => array( 'templates', 'prompt_sections', 'article_structures' ),
			'sources' => array( 'sources', 'source_groups', 'research' ),
			'taxonomy' => array( 'taxonomy', 'terms' ),
			'internal_links' => array( 'internal_links', 'embeddings', 'posts' ),
			'settings' => array( 'settings', 'config' ),
		);
		$rows = array();
		foreach ($domains as $domain => $tags) {
			$details = $this->get_domain_details( $domain );
			$rows[] = array_merge( array( 'domain' => $domain, 'tags' => $tags ), $details );
		}
		return $rows;
	}

	public function get_domain_details( $domain ) {
		$domain = sanitize_key( $domain );
		$map = array(
			'authors' => array( 'authors', 'author_topics', 'author_posts' ),
			'templates' => array( 'templates', 'prompt_sections', 'article_structures' ),
			'sources' => array( 'sources', 'source_groups', 'research' ),
			'taxonomy' => array( 'taxonomy', 'terms' ),
			'internal_links' => array( 'internal_links', 'embeddings', 'posts' ),
			'settings' => array( 'settings', 'config' ),
		);
		$tags = isset( $map[ $domain ] ) ? $map[ $domain ] : array( $domain );
		$operations = array();
		foreach ($tags as $tag) {
			$tag_details = $this->get_tag_details( $tag );
			foreach ((array) $tag_details['operation_ids'] as $operation_id) {
				$operations[ $operation_id ] = $operation_id;
			}
		}
		return array( 'domain' => $domain, 'tags' => $tags, 'operation_ids' => array_values( $operations ) );
	}

	public function list_operations( array $filters = array() ) {
		$metrics = $this->repository->get_metrics( $filters, 200 );
		$ops = $this->repository->get_group_counts( 'operation_id' );
		$indexed = array();
		foreach ($ops as $op) {
			$indexed[ $op['item_key'] ] = $op;
		}
		foreach ($metrics as &$metric) {
			$key = $metric['operation_id'];
			$metric['indexed_entry_count'] = isset( $indexed[ $key ] ) ? (int) $indexed[ $key ]['total'] : 0;
			$metric['estimated_size'] = isset( $indexed[ $key ] ) ? (int) $indexed[ $key ]['size_bytes'] : 0;
		}
		return $metrics;
	}

	public function list_events( array $filters = array(), $limit = 50, $offset = 0 ) {
		return $this->repository->list_events( $filters, $limit, $offset );
	}

	public function get_driver_status() {
		$driver = $this->cache->get_driver();
		$name = $this->active_driver_name();
		$capabilities = $this->get_driver_capabilities();
		$status = array(
			'name' => $name,
			'class' => get_class( $driver ),
			'capabilities' => $capabilities,
			'health' => 'ok',
			'limitations' => array(),
			'storage_stats' => array(),
		);
		if ($name === 'array') {
			$status['limitations'][] = __( 'Array cache is request-local; entries disappear after the current request.', 'ai-post-scheduler' );
		}
		if ($name === 'wp-object-cache') {
			$status['limitations'][] = __( 'WP Object Cache cannot safely list arbitrary keys; Cache Monitor uses the plugin-owned index.', 'ai-post-scheduler' );
			$status['storage_stats']['persistent_object_cache'] = function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache();
		}
		if ($name === 'redis') {
			$status['limitations'][] = __( 'Redis key listing is intentionally disabled unless the driver can do so safely. Credentials are never displayed.', 'ai-post-scheduler' );
			$status['storage_stats']['host'] = get_option( 'aips_cache_redis_host', '127.0.0.1' );
			$status['storage_stats']['port'] = (int) get_option( 'aips_cache_redis_port', 6379 );
			$status['storage_stats']['db'] = (int) get_option( 'aips_cache_redis_db', 0 );
		}
		if ($name === 'db') {
			$status['storage_stats']['indexed_entries'] = $this->repository->get_summary_counts()['total_entries'];
		}
		if ($name === 'session') {
			$status['limitations'][] = __( 'Session cache is per-user and depends on PHP session availability.', 'ai-post-scheduler' );
		}
		return $status;
	}

	public function run_maintenance() {
		$retention = (int) get_option( 'aips_cache_monitor_event_retention_days', 30 );
		$result = array(
			'expired_index_entries' => $this->repository->prune_expired_index(),
			'old_events' => $this->repository->prune_old_events( $retention ),
			'expired_db_cache_rows' => $this->repository->prune_expired_db_cache(),
		);
		$this->log_event( 'maintenance_run', array( 'affected_count' => array_sum( $result ), 'context' => $result ) );
		return $result;
	}

	public function run_maintenance_action( $action ) {
		$action = sanitize_key( $action );
		if ($action === 'prune_expired') {
			return $this->flush_expired();
		}
		if ($action === 'rebuild_index') {
			$count = $this->repository->rebuild_index_from_db_cache( $this->active_driver_name() );
			$this->log_event( 'cache_index_rebuilt', array( 'affected_count' => $count ) );
			return array( 'rebuilt' => $count );
		}
		if ($action === 'reset_index') {
			return array( 'reset' => $this->reset_index() );
		}
		if ($action === 'validate_policies') {
			return array( 'valid' => true, 'message' => __( 'No repository cache policy registry is available; indexed operations were checked instead.', 'ai-post-scheduler' ) );
		}
		if ($action === 'compact_metrics') {
			$this->log_event( 'cache_metrics_compacted', array( 'message' => 'Metrics compaction completed.' ) );
			return array( 'compacted' => true );
		}
		return array( 'message' => __( 'Unknown maintenance action.', 'ai-post-scheduler' ) );
	}

	public function diagnostics_bundle() {
		return array(
			'generated_at' => $this->now(),
			'summary' => $this->get_summary(),
			'driver' => $this->get_driver_status(),
			'tags' => $this->list_tags(),
			'domains' => $this->list_domains(),
			'operations' => $this->list_operations(),
		);
	}

	public function log_event( $event_type, array $data = array() ) {
		$data['event_type'] = $event_type;
		$this->repository->add_event( $data );
		if (class_exists( 'AIPS_Logger' )) {
			AIPS_Logger::instance()->log( 'Cache Monitor: ' . $event_type, 'info', $data );
		}
	}

	private function get_driver_capabilities() {
		$driver = $this->cache->get_driver();
		if ($driver instanceof AIPS_Cache_Monitorable_Driver) {
			return $driver->get_monitor_capabilities();
		}
		return array(
			'list_keys' => false,
			'inspect_entry' => false,
			'delete_key' => true,
			'delete_group' => false,
			'flush_plugin' => true,
			'size_bytes' => false,
			'ttl_remaining' => false,
			'tag_versions' => false,
			'live_metrics' => false,
		);
	}

	private function active_driver_name() {
		$driver = $this->cache->get_driver();
		$name = strtolower( str_replace( array( 'AIPS_Cache_', '_Driver', '_' ), array( '', '', '-' ), get_class( $driver ) ) );
		return $name;
	}

	private function is_cache_enabled() {
		$value = get_option( 'aips_enable_cache_system', '1' );
		return $value !== '0' && $value !== 0 && $value !== false;
	}

	private function safe_preview( $value ) {
		$limit = max( 100, (int) get_option( 'aips_cache_monitor_preview_length', 1200 ) );
		return $this->preview_value( $value, $limit );
	}

	private function preview_value( $value, $limit, $depth = 0, $name = '' ) {
		if ($this->is_sensitive_name( $name )) {
			return '[redacted]';
		}
		if ($depth > 2) {
			return '[depth limit]';
		}
		if (is_array( $value )) {
			$out = array( '_type' => 'array', '_count' => count( $value ), 'items' => array() );
			$i = 0;
			foreach ($value as $k => $v) {
				if ($i++ >= 12) { break; }
				$out['items'][ (string) $k ] = $this->preview_value( $v, $limit, $depth + 1, (string) $k );
			}
			return $out;
		}
		if (is_object( $value )) {
			$vars = get_object_vars( $value );
			return array( '_type' => 'object', 'class' => get_class( $value ), 'properties' => $this->preview_value( $vars, $limit, $depth + 1 ) );
		}
		if (is_string( $value )) {
			$value = $this->redact_string( $value );
			return strlen( $value ) > $limit ? substr( $value, 0, $limit ) . '…' : $value;
		}
		if (is_bool( $value ) || is_int( $value ) || is_float( $value ) || $value === null) {
			return $value;
		}
		return '[unpreviewable ' . gettype( $value ) . ']';
	}

	private function is_sensitive_name( $name ) {
		return (bool) preg_match( '/api[_-]?key|token|secret|password|authorization/i', (string) $name );
	}

	private function redact_string( $value ) {
		return preg_replace( '/(api[_-]?key|token|secret|password|authorization)(["\'\s:=]+)([^"\'\s,}\]]+)/i', '$1$2[redacted]', $value );
	}

	private function full_value_allowed() {
		$debug_only = get_option( 'aips_cache_monitor_full_value_debug_only', '1' );
		return ( $debug_only === '0' ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || (bool) get_option( 'aips_developer_mode', false );
	}

	private function now() {
		return class_exists( 'AIPS_DateTime' ) ? AIPS_DateTime::now()->timestamp() : time();
	}
}
