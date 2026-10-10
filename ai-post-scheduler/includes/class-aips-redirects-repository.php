<?php
/**
 * Redirects Repository
 *
 * Every redirect AIPS creates, whichever plugin serves it (AIPS itself,
 * Redirection, Yoast SEO Premium or Rank Math). The provider and the
 * provider's own ID are stored so redirects can be moved between providers.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.7
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Redirects_Repository
 */
class AIPS_Redirects_Repository {

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * @var string
	 */
	private $table;

	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'aips_redirects';
	}

	/**
	 * Whether the table exists (schema may not be upgraded yet).
	 *
	 * @return bool
	 */
	public function table_exists(): bool {
		static $exists = null;
		if ($exists === null) {
			$found  = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $this->table));
			$exists = ($found === $this->table);
		}
		return (bool) $exists;
	}

	/**
	 * Insert a redirect.
	 *
	 * @param array $data Column values.
	 * @return int|false New ID.
	 */
	public function insert(array $data) {
		if (!$this->table_exists()) {
			return false;
		}

		$now  = time();
		$data = array_merge(array('created_at' => $now, 'updated_at' => $now), $this->only_columns($data));

		return $this->wpdb->insert($this->table, $data) ? (int) $this->wpdb->insert_id : false;
	}

	/**
	 * Update a redirect.
	 *
	 * @param int   $id   Redirect ID.
	 * @param array $data Column values.
	 * @return bool
	 */
	public function update(int $id, array $data): bool {
		if (!$this->table_exists()) {
			return false;
		}

		$data               = $this->only_columns($data);
		$data['updated_at'] = time();

		return false !== $this->wpdb->update($this->table, $data, array('id' => $id));
	}

	/**
	 * Delete a redirect.
	 *
	 * @param int $id Redirect ID.
	 * @return bool
	 */
	public function delete(int $id): bool {
		return $this->table_exists() && (bool) $this->wpdb->delete($this->table, array('id' => $id), array('%d'));
	}

	/**
	 * One redirect.
	 *
	 * @param int $id Redirect ID.
	 * @return object|null
	 */
	public function get(int $id) {
		if (!$this->table_exists()) {
			return null;
		}

		return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id)) ?: null;
	}

	/**
	 * Redirect for a source path hash.
	 *
	 * @param string $hash md5 of the normalized source path.
	 * @return object|null
	 */
	public function get_by_hash(string $hash) {
		if (!$this->table_exists()) {
			return null;
		}

		return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table} WHERE source_hash = %s", $hash)) ?: null;
	}

	/**
	 * Redirects created for an origin (e.g. a consolidation of post N).
	 *
	 * @param string $origin     Origin type.
	 * @param int    $origin_ref Origin reference.
	 * @return object[]
	 */
	public function get_by_origin(string $origin, int $origin_ref): array {
		if (!$this->table_exists()) {
			return array();
		}

		return (array) $this->wpdb->get_results(
			$this->wpdb->prepare("SELECT * FROM {$this->table} WHERE origin = %s AND origin_ref = %d ORDER BY id ASC", $origin, $origin_ref)
		);
	}

	/**
	 * A page of redirects, newest first.
	 *
	 * @param array $args search, provider, origin, page, per_page.
	 * @return object[]
	 */
	public function get_page(array $args = array()): array {
		if (!$this->table_exists()) {
			return array();
		}

		$per_page = max(1, min(100, (int) ($args['per_page'] ?? 25)));
		$offset   = (max(1, (int) ($args['page'] ?? 1)) - 1) * $per_page;
		list($where, $params) = $this->build_where($args);

		return (array) $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d",
				array_merge($params, array($per_page, $offset))
			)
		);
	}

	/**
	 * Count redirects matching the filters of get_page().
	 *
	 * @param array $args Filters.
	 * @return int
	 */
	public function count(array $args = array()): int {
		if (!$this->table_exists()) {
			return 0;
		}

		list($where, $params) = $this->build_where($args);
		$sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$where}";

		return (int) $this->wpdb->get_var(empty($params) ? $sql : $this->wpdb->prepare($sql, $params));
	}

	/**
	 * All redirect IDs served by a provider.
	 *
	 * @param string $provider Provider key.
	 * @return int[]
	 */
	public function get_ids_by_provider(string $provider): array {
		if (!$this->table_exists()) {
			return array();
		}

		return array_map('intval', (array) $this->wpdb->get_col(
			$this->wpdb->prepare("SELECT id FROM {$this->table} WHERE provider = %s ORDER BY id ASC", $provider)
		));
	}

	/**
	 * Counts per provider.
	 *
	 * @return array<string,int>
	 */
	public function count_by_provider(): array {
		if (!$this->table_exists()) {
			return array();
		}

		$counts = array();
		foreach ((array) $this->wpdb->get_results("SELECT provider, COUNT(*) AS total FROM {$this->table} GROUP BY provider") as $row) {
			$counts[(string) $row->provider] = (int) $row->total;
		}
		return $counts;
	}

	/**
	 * Source hashes of enabled redirects AIPS serves itself (for the
	 * front-end lookup cache).
	 *
	 * @param string $provider Native provider key.
	 * @return string[]
	 */
	public function get_enabled_hashes(string $provider): array {
		if (!$this->table_exists()) {
			return array();
		}

		return (array) $this->wpdb->get_col(
			$this->wpdb->prepare("SELECT source_hash FROM {$this->table} WHERE provider = %s AND enabled = 1", $provider)
		);
	}

	/**
	 * Count a hit.
	 *
	 * @param int $id Redirect ID.
	 * @return void
	 */
	public function record_hit(int $id): void {
		if (!$this->table_exists()) {
			return;
		}

		$this->wpdb->query(
			$this->wpdb->prepare("UPDATE {$this->table} SET hits = hits + 1, last_hit_at = %d WHERE id = %d", time(), $id)
		);
	}

	/**
	 * WHERE clause for list filters.
	 *
	 * @param array $args Filters.
	 * @return array{0:string, 1:array}
	 */
	private function build_where(array $args): array {
		$where  = array('1=1');
		$params = array();

		if (!empty($args['search'])) {
			$like     = '%' . $this->wpdb->esc_like((string) $args['search']) . '%';
			$where[]  = '(source_path LIKE %s OR target_url LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		foreach (array('provider', 'origin') as $column) {
			if (!empty($args[$column])) {
				$where[]  = "{$column} = %s";
				$params[] = (string) $args[$column];
			}
		}

		return array(implode(' AND ', $where), $params);
	}

	/**
	 * Keep known columns only.
	 *
	 * @param array $data Data.
	 * @return array
	 */
	private function only_columns(array $data): array {
		$columns = array('source_path', 'source_hash', 'target_url', 'target_post_id', 'status_code', 'provider', 'provider_ref', 'provider_error', 'origin', 'origin_ref', 'enabled', 'hits', 'last_hit_at', 'created_by');
		return array_intersect_key($data, array_flip($columns));
	}
}
