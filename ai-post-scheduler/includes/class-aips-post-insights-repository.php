<?php
/**
 * Post Insights Repository
 *
 * Encapsulates all database interactions for post insights, vector embedding statuses,
 * semantic duplicate relationships, and generation context details.
 *
 * @package AI_Post_Scheduler
 * @since 3.6.7
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Post_Insights_Repository
 *
 * Repository layer for post AI insights and Posts list table prefetching.
 */
class AIPS_Post_Insights_Repository {

	/**
	 * @var wpdb WordPress database object.
	 */
	private $wpdb;

	/**
	 * @var string Table names with prefixes.
	 */
	private $embeddings_table;
	private $relationships_table;
	private $history_table;
	private $authors_table;
	private $templates_table;
	private $topics_table;

	/**
	 * @var AIPS_Config Config instance.
	 */
	private $config;

	/**
	 * Initialize repository.
	 *
	 * @param wpdb|null        $wpdb   WordPress database object.
	 * @param AIPS_Config|null $config Config instance.
	 */
	public function __construct($wpdb = null, ?AIPS_Config $config = null) {
		$this->wpdb                = ($wpdb instanceof wpdb) ? $wpdb : $GLOBALS['wpdb'];
		$prefix                    = isset($this->wpdb->prefix) ? $this->wpdb->prefix : 'wp_';
		$this->embeddings_table    = $prefix . 'aips_embeddings';
		$this->relationships_table = $prefix . 'aips_relationships';
		$this->history_table       = $prefix . 'aips_history';
		$this->authors_table       = $prefix . 'aips_authors';
		$this->templates_table     = $prefix . 'aips_templates';
		$this->topics_table        = $prefix . 'aips_author_topics';

		$container    = AIPS_Container::get_instance();
		$this->config = $config ?: ($container->has(AIPS_Config::class) ? $container->make(AIPS_Config::class) : AIPS_Config::get_instance());
	}

	/**
	 * Get vector embedding status for a single post.
	 *
	 * @param int $post_id Post ID.
	 * @return object|null Embedding status row or null if not indexed.
	 */
	public function get_post_embedding_status(int $post_id): ?object {
		$row = $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT id, dimensions, model, indexed_at 
			 FROM {$this->embeddings_table} 
			 WHERE object_type = 'post' AND object_id = %d 
			 LIMIT 1",
			$post_id
		));

		return $row ?: null;
	}

	/**
	 * Bulk fetch embedding status for multiple post IDs.
	 *
	 * @param int[] $post_ids Array of post IDs.
	 * @return array Keyed by post_id => object.
	 */
	public function get_bulk_embeddings_status(array $post_ids): array {
		if (empty($post_ids)) {
			return array();
		}

		$post_ids = array_unique(array_map('absint', $post_ids));
		$placeholders = implode(',', array_fill(0, count($post_ids), '%d'));

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT object_id AS post_id, dimensions, model, indexed_at 
			 FROM {$this->embeddings_table} 
			 WHERE object_type = 'post' AND object_id IN ({$placeholders})",
			...$post_ids
		));

		$map = array();
		if (!empty($rows)) {
			foreach ($rows as $row) {
				$map[(int) $row->post_id] = $row;
			}
		}

		return $map;
	}

	/**
	 * Get top semantic duplicate candidates for a single post.
	 *
	 * @param int $post_id Post ID.
	 * @param int $limit   Maximum results to return. Default 5.
	 * @return array List of matched objects containing matched_id and similarity.
	 */
	public function get_top_duplicates(int $post_id, int $limit = 5): array {
		$limit = max(1, min(50, absint($limit)));

		return $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT 
				CASE WHEN source_id = %d THEN target_id ELSE source_id END AS matched_id,
				similarity
			 FROM {$this->relationships_table}
			 WHERE (source_id = %d OR target_id = %d)
			   AND relation_type = 'similar'
			 ORDER BY similarity DESC
			 LIMIT %d",
			$post_id,
			$post_id,
			$post_id,
			$limit
		)) ?: array();
	}

	/**
	 * Bulk fetch the top duplicate relationship for each post in a list.
	 *
	 * @param int[] $post_ids Array of post IDs.
	 * @return array Keyed by post_id => array('matched_id' => int, 'similarity' => float).
	 */
	public function get_bulk_top_duplicates(array $post_ids): array {
		if (empty($post_ids)) {
			return array();
		}

		$post_ids = array_unique(array_map('absint', $post_ids));
		$placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
		$all_ids = array_merge($post_ids, $post_ids);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT source_id, target_id, similarity 
			 FROM {$this->relationships_table} 
			 WHERE relation_type = 'similar' 
			   AND (source_id IN ({$placeholders}) OR target_id IN ({$placeholders}))
			 ORDER BY similarity DESC",
			...$all_ids
		));

		$map = array();
		if (!empty($rows)) {
			foreach ($rows as $r) {
				$s_id = (int) $r->source_id;
				$t_id = (int) $r->target_id;
				$sim  = (float) $r->similarity;

				if (in_array($s_id, $post_ids, true) && !isset($map[$s_id])) {
					$map[$s_id] = array('matched_id' => $t_id, 'similarity' => $sim);
				}
				if (in_array($t_id, $post_ids, true) && !isset($map[$t_id])) {
					$map[$t_id] = array('matched_id' => $s_id, 'similarity' => $sim);
				}
			}
		}

		return $map;
	}

	/**
	 * Get generation history and resolved metadata (author, template, topic) for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null Array of generation data or null if not generated by AIPS.
	 */
	public function get_post_generation_details(int $post_id): ?array {
		$row = $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT h.*, 
					a.name AS author_name, 
					t.name AS template_title, 
					top.topic_title AS topic_title
			 FROM {$this->history_table} h
			 LEFT JOIN {$this->authors_table} a ON h.author_id = a.id
			 LEFT JOIN {$this->templates_table} t ON h.template_id = t.id
			 LEFT JOIN {$this->topics_table} top ON h.topic_id = top.id
			 WHERE h.post_id = %d
			 ORDER BY h.id DESC
			 LIMIT 1",
			$post_id
		));

		return $row ? (array) $row : null;
	}

	/**
	 * Bulk fetch generation history and resolved metadata for multiple posts.
	 *
	 * @param int[] $post_ids Array of post IDs.
	 * @return array Keyed by post_id => array of generation data.
	 */
	public function get_bulk_generation_details(array $post_ids): array {
		if (empty($post_ids)) {
			return array();
		}

		$post_ids = array_unique(array_map('absint', $post_ids));
		$placeholders = implode(',', array_fill(0, count($post_ids), '%d'));

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT h.*, 
					a.name AS author_name, 
					t.name AS template_title, 
					top.topic_title AS topic_title
			 FROM {$this->history_table} h
			 LEFT JOIN {$this->authors_table} a ON h.author_id = a.id
			 LEFT JOIN {$this->templates_table} t ON h.template_id = t.id
			 LEFT JOIN {$this->topics_table} top ON h.topic_id = top.id
			 WHERE h.post_id IN ({$placeholders})
			 ORDER BY h.id DESC",
			...$post_ids
		));

		$map = array();
		if (!empty($rows)) {
			foreach ($rows as $row) {
				$pid = (int) $row->post_id;
				if (!isset($map[$pid])) {
					$map[$pid] = (array) $row;
				}
			}
		}

		return $map;
	}

	/**
	 * Get pillar post relationship for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return object|null
	 */
	public function get_pillar_relationship(int $post_id): ?object {
		return $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT * FROM {$this->relationships_table} 
			 WHERE relation_type = 'pillar_spoke' 
			   AND (source_id = %d OR target_id = %d) 
			 LIMIT 1",
			$post_id,
			$post_id
		));
	}

	/**
	 * Set or remove pillar post relationship in relationships table.
	 *
	 * @param int  $post_id    Post ID.
	 * @param int  $cluster_id Cluster ID / representative post ID.
	 * @param bool $is_pillar  True to designate as pillar, false to remove.
	 * @return bool
	 */
	public function set_pillar_relationship(int $post_id, int $cluster_id, bool $is_pillar): bool {
		if ($is_pillar) {
			return (bool) $this->wpdb->replace(
				$this->relationships_table,
				array(
					'source_type'   => 'post',
					'source_id'     => $post_id,
					'target_type'   => 'post',
					'target_id'     => $cluster_id,
					'similarity'    => 1.0000,
					'relation_type' => 'pillar_spoke',
					'updated_at'    => AIPS_DateTime::now()->timestamp(),
				),
				array('%s', '%d', '%s', '%d', '%f', '%s', '%d')
			);
		}

		return (bool) $this->wpdb->delete(
			$this->relationships_table,
			array(
				'source_id'     => $post_id,
				'relation_type' => 'pillar_spoke',
			),
			array('%d', '%s')
		);
	}
}
