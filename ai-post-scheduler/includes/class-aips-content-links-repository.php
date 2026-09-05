<?php
/**
 * Content Links Repository
 *
 * Manages CRUD operations and edge querying for the aips_content_links table.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Content_Links_Repository
 */
class AIPS_Content_Links_Repository {

	/**
	 * Database table name.
	 *
	 * @var string
	 */
	protected $table;

	/**
	 * Initialize the repository.
	 */
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'aips_content_links';
	}

	/**
	 * Get database table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		return $this->table;
	}

	/**
	 * Atomically synchronize outgoing links for a specific source post.
	 *
	 * Purges links that no longer exist and inserts/updates detected links.
	 *
	 * @param int   $source_id Source post ID.
	 * @param array $links     Array of link items: array(array('target_id' => int, 'anchor_text' => string, 'link_url' => string, 'post_type' => string)).
	 * @return bool True on success.
	 */
	public function sync_post_links($source_id, array $links) {
		global $wpdb;
		$source_id = absint($source_id);
		if ($source_id <= 0) {
			return false;
		}

		// Wrap the delete + inserts in a transaction so a mid-loop failure
		// (deadlock, PHP fatal, request timeout) does not leave the source with
		// a partial outbound set. On InnoDB this rolls back cleanly; on storage
		// engines without transaction support the calls are effectively no-ops
		// and behavior degrades gracefully to the prior non-atomic path.
		$wpdb->query('START TRANSACTION');

		// Delete existing outbound links for this source
		$wpdb->delete($this->table, array('source_id' => $source_id), array('%d'));

		if (empty($links)) {
			$wpdb->query('COMMIT');
			return true;
		}

		$now           = current_time('mysql');
		$insert_failed = false;
		foreach ($links as $link) {
			$target_id   = absint($link['target_id'] ?? 0);
			$link_url    = esc_url_raw($link['link_url'] ?? '');
			$anchor_text = sanitize_text_field(substr($link['anchor_text'] ?? '', 0, 255));
			$post_type   = sanitize_key($link['post_type'] ?? 'post');

			if ($target_id <= 0 || empty($link_url)) {
				continue;
			}

			// Do not link to self
			if ($target_id === $source_id) {
				continue;
			}

			$inserted = $wpdb->insert(
				$this->table,
				array(
					'source_id'   => $source_id,
					'target_id'   => $target_id,
					'anchor_text' => $anchor_text,
					'link_url'    => $link_url,
					'post_type'   => $post_type,
					'created_at'  => $now,
					'updated_at'  => $now,
				),
				array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
			);
			if (false === $inserted) {
				$insert_failed = true;
				break;
			}
		}

		if ($insert_failed) {
			$wpdb->query('ROLLBACK');
			return false;
		}

		$wpdb->query('COMMIT');
		return true;
	}

	/**
	 * Retrieve all outbound links originating from a source post.
	 *
	 * @param int $source_id Source post ID.
	 * @return array Array of link rows.
	 */
	public function get_outbound_links($source_id) {
		global $wpdb;
		$source_id = absint($source_id);

		$sql = $wpdb->prepare(
			"SELECT * FROM {$this->table} WHERE source_id = %d ORDER BY id ASC",
			$source_id
		);

		$results = $wpdb->get_results($sql);
		return is_array($results) ? $results : array();
	}

	/**
	 * Retrieve all inbound links pointing to a target post.
	 *
	 * @param int $target_id Target post ID.
	 * @return array Array of link rows.
	 */
	public function get_inbound_links($target_id) {
		global $wpdb;
		$target_id = absint($target_id);

		$sql = $wpdb->prepare(
			"SELECT * FROM {$this->table} WHERE target_id = %d ORDER BY id ASC",
			$target_id
		);

		$results = $wpdb->get_results($sql);
		return is_array($results) ? $results : array();
	}

	/**
	 * Count outbound links for a post.
	 *
	 * @param int $source_id Post ID.
	 * @return int Number of outbound internal links.
	 */
	public function get_outbound_count($source_id) {
		global $wpdb;
		$source_id = absint($source_id);

		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table} WHERE source_id = %d",
			$source_id
		);

		return (int) $wpdb->get_var($sql);
	}

	/**
	 * Count inbound links for a post.
	 *
	 * @param int $target_id Post ID.
	 * @return int Number of inbound internal links.
	 */
	public function get_inbound_count($target_id) {
		global $wpdb;
		$target_id = absint($target_id);

		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table} WHERE target_id = %d",
			$target_id
		);

		return (int) $wpdb->get_var($sql);
	}

	/**
	 * Batch lookup inbound link counts for multiple posts.
	 *
	 * @param array $post_ids Array of post IDs.
	 * @return array Map of post_id => count.
	 */
	public function get_inbound_counts(array $post_ids) {
		global $wpdb;
		$clean_ids = array_filter(array_map('absint', $post_ids));
		if (empty($clean_ids)) {
			return array();
		}

		$placeholders = implode(',', array_fill(0, count($clean_ids), '%d'));
		$sql          = $wpdb->prepare(
			"SELECT target_id, COUNT(*) as cnt FROM {$this->table} WHERE target_id IN ($placeholders) GROUP BY target_id",
			$clean_ids
		);

		$rows    = $wpdb->get_results($sql);
		$results = array_fill_keys($clean_ids, 0);

		if (!empty($rows)) {
			foreach ($rows as $row) {
				$results[(int) $row->target_id] = (int) $row->cnt;
			}
		}

		return $results;
	}

	/**
	 * Batch lookup outbound link counts for multiple posts.
	 *
	 * @param array $post_ids Array of post IDs.
	 * @return array Map of post_id => count.
	 */
	public function get_outbound_counts(array $post_ids) {
		global $wpdb;
		$clean_ids = array_filter(array_map('absint', $post_ids));
		if (empty($clean_ids)) {
			return array();
		}

		$placeholders = implode(',', array_fill(0, count($clean_ids), '%d'));
		$sql          = $wpdb->prepare(
			"SELECT source_id, COUNT(*) as cnt FROM {$this->table} WHERE source_id IN ($placeholders) GROUP BY source_id",
			$clean_ids
		);

		$rows    = $wpdb->get_results($sql);
		$results = array_fill_keys($clean_ids, 0);

		if (!empty($rows)) {
			foreach ($rows as $row) {
				$results[(int) $row->source_id] = (int) $row->cnt;
			}
		}

		return $results;
	}

	/**
	 * Find orphan published posts (posts having 0 inbound internal links).
	 *
	 * @param array $post_types Post types to check.
	 * @param int   $limit      Maximum records to return.
	 * @return array Array of orphan post IDs.
	 */
	public function get_orphan_post_ids(array $post_types = array('post', 'page'), $limit = 100) {
		global $wpdb;
		$limit      = max(1, min(500, absint($limit)));
		$post_types = array_values(array_unique(array_filter(array_map('sanitize_key', $post_types))));
		if (empty($post_types)) {
			$post_types = array('post', 'page');
		}

		$placeholders = implode(',', array_fill(0, count($post_types), '%s'));
		$params       = array_merge($post_types, array($limit));

		$sql = $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			LEFT JOIN {$this->table} l ON p.ID = l.target_id
			WHERE p.post_status = 'publish'
			AND p.post_type IN ($placeholders)
			AND l.id IS NULL
			ORDER BY p.post_date DESC
			LIMIT %d",
			$params
		);

		$ids = $wpdb->get_col($sql);
		return is_array($ids) ? array_map('intval', $ids) : array();
	}

	/**
	 * Retrieve all directed edges where both source and target belong to the given node list.
	 *
	 * Eliminates N+1 queries when building micro-graph modal topologies.
	 *
	 * @param array $node_ids Array of post IDs.
	 * @return array Array of array('source' => int, 'target' => int).
	 */
	public function get_edges_between_nodes(array $node_ids) {
		global $wpdb;
		$clean_ids = array_values(array_unique(array_filter(array_map('absint', $node_ids))));
		if (count($clean_ids) < 2) {
			return array();
		}

		$placeholders = implode(',', array_fill(0, count($clean_ids), '%d'));
		$query_params = array_merge($clean_ids, $clean_ids);

		$sql = $wpdb->prepare(
			"SELECT DISTINCT source_id, target_id FROM {$this->table} WHERE source_id IN ($placeholders) AND target_id IN ($placeholders)",
			$query_params
		);

		$results = $wpdb->get_results($sql, ARRAY_A);
		if (!is_array($results)) {
			return array();
		}

		return array_map(function ($row) {
			return array(
				'source' => (int) $row['source_id'],
				'target' => (int) $row['target_id'],
			);
		}, $results);
	}

	/**
	 * Retrieve all directed edges (source_id -> target_id) for whole graph traversal.
	 *
	 * @return array Array of array('source_id' => int, 'target_id' => int).
	 */
	public function get_all_directed_edges() {
		global $wpdb;

		$sql     = "SELECT DISTINCT source_id, target_id FROM {$this->table}";
		$results = $wpdb->get_results($sql, ARRAY_A);

		if (!is_array($results)) {
			return array();
		}

		return array_map(function ($row) {
			return array(
				'source_id' => (int) $row['source_id'],
				'target_id' => (int) $row['target_id'],
			);
		}, $results);
	}

	/**
	 * Delete all records associated with a post ID (either as source or target).
	 *
	 * @param int $post_id Post ID.
	 * @return int Number of rows deleted.
	 */
	public function delete_by_post($post_id) {
		global $wpdb;
		$post_id = absint($post_id);
		if ($post_id <= 0) {
			return 0;
		}

		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table} WHERE source_id = %d OR target_id = %d",
				$post_id,
				$post_id
			)
		);
	}

	/**
	 * Count total directed internal links in repository.
	 *
	 * @return int Total edges.
	 */
	public function get_total_links_count() {
		global $wpdb;
		return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
	}

	/**
	 * Count orphan published posts for a given post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return int Number of orphan posts.
	 */
	public function count_orphan_posts($post_type = 'post') {
		global $wpdb;
		$post_type = sanitize_key($post_type);
		$sql = $wpdb->prepare(
			"SELECT COUNT(p.ID) FROM {$wpdb->posts} p
			LEFT JOIN {$this->table} l ON p.ID = l.target_id
			WHERE p.post_status = 'publish'
			AND p.post_type = %s
			AND l.id IS NULL",
			$post_type
		);
		return (int) $wpdb->get_var($sql);
	}
}
