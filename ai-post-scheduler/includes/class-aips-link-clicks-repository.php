<?php
/**
 * Link Clicks Repository
 *
 * Daily click counts for internal links (one row per source post, target
 * post and UTC day). No visitor data is stored.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.6
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Link_Clicks_Repository
 */
class AIPS_Link_Clicks_Repository {

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
		$this->table = $wpdb->prefix . 'aips_link_clicks';
	}

	/**
	 * Whether the clicks table exists (schema may not be upgraded yet).
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
	 * Count one click on a link from $source_id to $target_id.
	 *
	 * @param int $source_id Post containing the link.
	 * @param int $target_id Post the link points to.
	 * @param int $day_start UTC midnight timestamp of the click's day.
	 * @return bool
	 */
	public function record(int $source_id, int $target_id, int $day_start): bool {
		if (!$this->table_exists()) {
			return false;
		}

		return false !== $this->wpdb->query(
			$this->wpdb->prepare(
				"INSERT INTO {$this->table} (source_post_id, target_post_id, day_start, clicks)
				VALUES (%d, %d, %d, 1)
				ON DUPLICATE KEY UPDATE clicks = clicks + 1",
				$source_id,
				$target_id,
				$day_start
			)
		);
	}

	/**
	 * Clicks into each of the given posts since a timestamp.
	 *
	 * @param int[] $post_ids Target post IDs.
	 * @param int   $since    Earliest day_start to include.
	 * @return array<int,int> post ID => clicks (posts without clicks omitted).
	 */
	public function get_inbound_clicks_for_posts(array $post_ids, int $since): array {
		$post_ids = array_values(array_filter(array_map('absint', $post_ids)));
		if (empty($post_ids) || !$this->table_exists()) {
			return array();
		}

		$placeholders = implode(', ', array_fill(0, count($post_ids), '%d'));
		$rows         = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT target_post_id, SUM(clicks) AS clicks
				FROM {$this->table}
				WHERE target_post_id IN ($placeholders) AND day_start >= %d
				GROUP BY target_post_id",
				array_merge($post_ids, array($since))
			)
		);

		$counts = array();
		foreach ((array) $rows as $row) {
			$counts[(int) $row->target_post_id] = (int) $row->clicks;
		}
		return $counts;
	}

	/**
	 * Clicks into a post broken down by the post the click came from.
	 *
	 * @param int $target_id Target post ID.
	 * @param int $since     Earliest day_start to include.
	 * @return array<int,int> source post ID => clicks.
	 */
	public function get_clicks_by_source(int $target_id, int $since): array {
		return $this->get_breakdown('target_post_id', 'source_post_id', $target_id, $since);
	}

	/**
	 * Clicks on a post's outbound internal links, by destination post.
	 *
	 * @param int $source_id Source post ID.
	 * @param int $since     Earliest day_start to include.
	 * @return array<int,int> target post ID => clicks.
	 */
	public function get_clicks_by_target(int $source_id, int $since): array {
		return $this->get_breakdown('source_post_id', 'target_post_id', $source_id, $since);
	}

	/**
	 * Total internal link clicks since a timestamp.
	 *
	 * @param int $since Earliest day_start to include.
	 * @return int
	 */
	public function get_total(int $since): int {
		if (!$this->table_exists()) {
			return 0;
		}

		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare("SELECT COALESCE(SUM(clicks), 0) FROM {$this->table} WHERE day_start >= %d", $since)
		);
	}

	/**
	 * Most-clicked links since a timestamp.
	 *
	 * @param int $since Earliest day_start to include.
	 * @param int $limit Maximum rows.
	 * @return object[] Rows with source_post_id, target_post_id, clicks.
	 */
	public function get_top_links(int $since, int $limit = 10): array {
		if (!$this->table_exists()) {
			return array();
		}

		return (array) $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT source_post_id, target_post_id, SUM(clicks) AS clicks
				FROM {$this->table}
				WHERE day_start >= %d
				GROUP BY source_post_id, target_post_id
				ORDER BY clicks DESC
				LIMIT %d",
				$since,
				max(1, $limit)
			)
		);
	}

	/**
	 * Delete click rows older than a timestamp.
	 *
	 * @param int $before Rows with day_start before this are removed.
	 * @return int Rows deleted.
	 */
	public function prune(int $before): int {
		if (!$this->table_exists()) {
			return 0;
		}

		return (int) $this->wpdb->query(
			$this->wpdb->prepare("DELETE FROM {$this->table} WHERE day_start < %d", $before)
		);
	}

	/**
	 * Delete all click rows involving a post (as source or target).
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function delete_for_post(int $post_id): void {
		if (!$this->table_exists()) {
			return;
		}

		$this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table} WHERE source_post_id = %d OR target_post_id = %d",
				$post_id,
				$post_id
			)
		);
	}

	/**
	 * Clicks grouped by one column, filtered by the other.
	 *
	 * @param string $filter_column Column to filter on (whitelisted).
	 * @param string $group_column  Column to group by (whitelisted).
	 * @param int    $post_id       Filter value.
	 * @param int    $since         Earliest day_start to include.
	 * @return array<int,int>
	 */
	private function get_breakdown(string $filter_column, string $group_column, int $post_id, int $since): array {
		$columns = array('source_post_id', 'target_post_id');
		if (!in_array($filter_column, $columns, true) || !in_array($group_column, $columns, true) || !$this->table_exists()) {
			return array();
		}

		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT {$group_column} AS post_id, SUM(clicks) AS clicks
				FROM {$this->table}
				WHERE {$filter_column} = %d AND day_start >= %d
				GROUP BY {$group_column}
				ORDER BY clicks DESC",
				$post_id,
				$since
			)
		);

		$counts = array();
		foreach ((array) $rows as $row) {
			$counts[(int) $row->post_id] = (int) $row->clicks;
		}
		return $counts;
	}
}
