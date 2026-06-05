<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Cache_Repository
 *
 * Repository encapsulating DB persistence for cache rows.
 */
class AIPS_Cache_Repository {

	/** @var wpdb */
	private $wpdb;

	/** @var string */
	private $table;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table = $wpdb->prefix . 'aips_cache';
	}

	public function get_row($cache_key, $cache_group) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT value, expires_at FROM `{$this->table}` WHERE cache_key = %s AND cache_group = %s LIMIT 1",
				$cache_key,
				$cache_group
			)
		);
	}

	public function replace($cache_key, $cache_group, $value, $expires_at, $updated_at) {
		return $this->wpdb->replace(
			$this->table,
			array(
				'cache_key' => $cache_key,
				'cache_group' => $cache_group,
				'value' => $value,
				'expires_at' => (int) $expires_at,
				'updated_at' => (int) $updated_at,
			),
			array('%s','%s','%s','%d','%d')
		);
	}

	public function last_error() {
		return $this->wpdb->last_error;
	}

	public function delete($cache_key, $cache_group) {
		return $this->wpdb->delete($this->table, array('cache_key'=>$cache_key,'cache_group'=>$cache_group), array('%s','%s'));
	}

	public function truncate() {
		return $this->wpdb->query("TRUNCATE TABLE `{$this->table}`");
	}

	public function has_non_expired($cache_key, $cache_group, $now_ts) {
		return $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT 1 FROM `{$this->table}` WHERE cache_key = %s AND cache_group = %s AND (expires_at = 0 OR expires_at >= %d) LIMIT 1",
				$cache_key,
				$cache_group,
				$now_ts
			)
		);
	}

	public function purge_expired($now_ts) {
		return $this->wpdb->query(
			$this->wpdb->prepare("DELETE FROM `{$this->table}` WHERE expires_at > 0 AND expires_at < %d", $now_ts)
		);
	}
}
