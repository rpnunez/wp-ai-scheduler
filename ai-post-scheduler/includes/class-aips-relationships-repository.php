<?php
/**
 * Relationships Repository
 *
 * Handles persistence for precomputed semantic relationships between posts,
 * topics, and other content entities.
 *
 * @package AI_Post_Scheduler
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Relationships_Repository
 *
 * Manages CRUD operations for the aips_relationships table.
 */
class AIPS_Relationships_Repository {

	/**
	 * @var wpdb WordPress database object.
	 */
	private $wpdb;

	/**
	 * @var string Table name with prefix.
	 */
	private $table;

	/**
	 * Initialize repository.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'aips_relationships';
	}

	/**
	 * Upsert a relationship record.
	 *
	 * @param string $source_type   Source entity type ('post', 'topic').
	 * @param int    $source_id     Source entity ID.
	 * @param string $target_type   Target entity type ('post', 'topic').
	 * @param int    $target_id     Target entity ID.
	 * @param float  $similarity    Cosine similarity score (0.0000 - 1.0000).
	 * @param string $relation_type Relation classification ('related_post', 'duplicate_candidate').
	 * @return int|false
	 */
	public function upsert($source_type, $source_id, $target_type, $target_id, $similarity, $relation_type = 'related_post') {
		$source_type   = sanitize_key($source_type);
		$source_id     = absint($source_id);
		$target_type   = sanitize_key($target_type);
		$target_id     = absint($target_id);
		$similarity    = max(0.0, min(1.0, (float) $similarity));
		$relation_type = sanitize_key($relation_type);
		$now           = AIPS_DateTime::now()->timestamp();

		$existing = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT id FROM {$this->table}
				WHERE source_type = %s AND source_id = %d AND target_type = %s AND target_id = %d AND relation_type = %s
				LIMIT 1",
				$source_type,
				$source_id,
				$target_type,
				$target_id,
				$relation_type
			)
		);

		if ($existing) {
			return $this->wpdb->update(
				$this->table,
				array(
					'similarity' => $similarity,
					'updated_at' => $now,
				),
				array('id' => (int) $existing->id),
				array('%f', '%d'),
				array('%d')
			);
		}

		return $this->wpdb->insert(
			$this->table,
			array(
				'source_type'   => $source_type,
				'source_id'     => $source_id,
				'target_type'   => $target_type,
				'target_id'     => $target_id,
				'similarity'    => $similarity,
				'relation_type' => $relation_type,
				'updated_at'    => $now,
			),
			array('%s', '%d', '%s', '%d', '%f', '%s', '%d')
		);
	}

	/**
	 * Sync (replace) all precomputed relationships for a source item atomically.
	 *
	 * @param string $source_type   Source entity type.
	 * @param int    $source_id     Source ID.
	 * @param array  $targets       Array of ['target_type' => string, 'target_id' => int, 'similarity' => float].
	 * @param string $relation_type Relation type.
	 * @return void
	 */
	public function sync_for_source($source_type, $source_id, array $targets, $relation_type = 'related_post') {
		$source_type   = sanitize_key($source_type);
		$source_id     = absint($source_id);
		$relation_type = sanitize_key($relation_type);

		// Delete existing relationships for this source and relation_type
		$this->delete_for_source($source_type, $source_id, $relation_type);

		if (empty($targets)) {
			return;
		}

		$now = AIPS_DateTime::now()->timestamp();
		foreach ($targets as $target) {
			$t_type = isset($target['target_type']) ? sanitize_key($target['target_type']) : 'post';
			$t_id   = isset($target['target_id']) ? absint($target['target_id']) : 0;
			$sim    = isset($target['similarity']) ? max(0.0, min(1.0, (float) $target['similarity'])) : 0.0;

			if ($t_id <= 0 || ($source_type === $t_type && $source_id === $t_id)) {
				continue;
			}

			$this->wpdb->insert(
				$this->table,
				array(
					'source_type'   => $source_type,
					'source_id'     => $source_id,
					'target_type'   => $t_type,
					'target_id'     => $t_id,
					'similarity'    => $sim,
					'relation_type' => $relation_type,
					'updated_at'    => $now,
				),
				array('%s', '%d', '%s', '%d', '%f', '%s', '%d')
			);
		}
	}

	/**
	 * Get top related items for a source entity.
	 *
	 * @param string $source_type    Source type.
	 * @param int    $source_id      Source ID.
	 * @param int    $limit          Max results to return.
	 * @param float  $min_similarity Minimum similarity score threshold.
	 * @param string $relation_type  Relation filter.
	 * @return object[] Array of relationship rows.
	 */
	public function get_related($source_type, $source_id, $limit = 5, $min_similarity = 0.60, $relation_type = 'related_post') {
		$source_type    = sanitize_key($source_type);
		$source_id      = absint($source_id);
		$limit          = absint($limit);
		$min_similarity = (float) $min_similarity;
		$relation_type  = sanitize_key($relation_type);

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT r.*, p.post_title, p.post_status, p.post_type
				FROM {$this->table} r
				LEFT JOIN {$this->wpdb->posts} p ON r.target_id = p.ID AND r.target_type = 'post'
				WHERE r.source_type = %s
				AND r.source_id = %d
				AND r.relation_type = %s
				AND r.similarity >= %f
				AND (r.target_type != 'post' OR (p.ID IS NOT NULL AND p.post_status = 'publish'))
				ORDER BY r.similarity DESC
				LIMIT %d",
				$source_type,
				$source_id,
				$relation_type,
				$min_similarity,
				$limit
			)
		);
	}

	/**
	 * Fetch full graph network data around an entity for visualization.
	 *
	 * @param string $source_type    Central entity type.
	 * @param int    $source_id      Central entity ID.
	 * @param int    $limit          Max 1st-degree neighbors.
	 * @param float  $min_similarity Minimum similarity threshold.
	 * @return array{nodes: array, edges: array} Graph payload.
	 */
	public function get_graph_data($source_type, $source_id, $limit = 15, $min_similarity = 0.50) {
		$source_type    = sanitize_key($source_type);
		$source_id      = absint($source_id);
		$limit          = absint($limit);
		$min_similarity = (float) $min_similarity;

		$neighbors = $this->get_related($source_type, $source_id, $limit, $min_similarity, 'related_post');

		$nodes = array();
		$edges = array();

		// Central source node
		$central_title = "Item #{$source_id}";
		$central_type  = $source_type;
		if ('post' === $source_type) {
			$post = get_post($source_id);
			if ($post) {
				$central_title = $post->post_title;
				$central_type  = $post->post_type;
			}
		}

		$nodes[] = array(
			'id'        => "{$source_type}_{$source_id}",
			'label'     => $central_title,
			'type'      => $central_type,
			'is_center' => true,
			'raw_id'    => $source_id,
			'url'       => 'post' === $source_type ? get_edit_post_link($source_id, '') : '',
		);

		$neighbor_ids = array();
		foreach ($neighbors as $n) {
			$n_id   = (int) $n->target_id;
			$n_type = $n->target_type;
			$n_key  = "{$n_type}_{$n_id}";

			$neighbor_ids[] = $n_id;

			$nodes[] = array(
				'id'         => $n_key,
				'label'      => !empty($n->post_title) ? $n->post_title : "{$n_type} #{$n_id}",
				'type'       => !empty($n->post_type) ? $n->post_type : $n_type,
				'is_center'  => false,
				'raw_id'     => $n_id,
				'similarity' => (float) $n->similarity,
				'url'        => 'post' === $n_type ? get_edit_post_link($n_id, '') : '',
				'view_url'   => 'post' === $n_type ? get_permalink($n_id) : '',
			);

			$edges[] = array(
				'source'     => "{$source_type}_{$source_id}",
				'target'     => $n_key,
				'weight'     => (float) $n->similarity,
				'label'      => round(((float) $n->similarity) * 100, 1) . '%',
			);
		}

		// Also fetch secondary interconnections between neighbors to form a rich cluster graph
		if (count($neighbor_ids) > 1) {
			$placeholders = implode(',', array_fill(0, count($neighbor_ids), '%d'));
			$inter_rows   = $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SELECT source_id, target_id, similarity
					FROM {$this->table}
					WHERE source_type = 'post'
					AND target_type = 'post'
					AND source_id IN ($placeholders)
					AND target_id IN ($placeholders)
					AND similarity >= %f",
					...array_merge($neighbor_ids, $neighbor_ids, array($min_similarity))
				)
			);

			$seen_edges = array();
			foreach ($edges as $e) {
				$seen_edges[$e['source'] . '->' . $e['target']] = true;
			}

			foreach ($inter_rows as $row) {
				$src = "post_{$row->source_id}";
				$tgt = "post_{$row->target_id}";
				if ($src !== $tgt && !isset($seen_edges["{$src}->{$tgt}"]) && !isset($seen_edges["{$tgt}->{$src}"])) {
					$edges[] = array(
						'source' => $src,
						'target' => $tgt,
						'weight' => (float) $row->similarity,
						'label'  => round(((float) $row->similarity) * 100, 1) . '%',
					);
					$seen_edges["{$src}->{$tgt}"] = true;
				}
			}
		}

		return array(
			'nodes' => $nodes,
			'edges' => $edges,
		);
	}

	/**
	 * Get duplicate/cannibalization clusters across the site.
	 *
	 * @param float $min_similarity High similarity threshold (e.g. 0.85).
	 * @param int   $limit          Max pairs to return.
	 * @return array Cluster pairings.
	 */
	public function get_top_duplicate_pairs($min_similarity = 0.85, $limit = 50) {
		$min_similarity = (float) $min_similarity;
		$limit          = absint($limit);

		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT r.source_id, r.target_id, r.similarity,
					p1.post_title as source_title, p1.post_type as source_post_type, p1.post_date as source_date,
					p2.post_title as target_title, p2.post_type as target_post_type, p2.post_date as target_date
				FROM {$this->table} r
				INNER JOIN {$this->wpdb->posts} p1 ON r.source_id = p1.ID
				INNER JOIN {$this->wpdb->posts} p2 ON r.target_id = p2.ID
				WHERE r.source_type = 'post'
				AND r.target_type = 'post'
				AND r.source_id < r.target_id
				AND r.similarity >= %f
				AND p1.post_status = 'publish'
				AND p2.post_status = 'publish'
				ORDER BY r.similarity DESC
				LIMIT %d",
				$min_similarity,
				$limit
			)
		);

		return (array) $rows;
	}

	/**
	 * Delete all relationships for a source item.
	 *
	 * @param string $source_type   Source type.
	 * @param int    $source_id     Source ID.
	 * @param string $relation_type Optional relation type filter.
	 * @return int|false
	 */
	public function delete_for_source($source_type, $source_id, $relation_type = '') {
		$where = array(
			'source_type' => sanitize_key($source_type),
			'source_id'   => absint($source_id),
		);
		$formats = array('%s', '%d');

		if (!empty($relation_type)) {
			$where['relation_type'] = sanitize_key($relation_type);
			$formats[]              = '%s';
		}

		return $this->wpdb->delete($this->table, $where, $formats);
	}

	/**
	 * Delete all relationships referencing an object (as either source or target).
	 *
	 * @param string $type Entity type.
	 * @param int    $id   Entity ID.
	 * @return int|false
	 */
	public function delete_for_object($type, $id) {
		$type = sanitize_key($type);
		$id   = absint($id);

		return $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table} 
				WHERE (source_type = %s AND source_id = %d) 
				   OR (target_type = %s AND target_id = %d)",
				$type,
				$id,
				$type,
				$id
			)
		);
	}

	/**
	 * Clear all relationships.
	 *
	 * @param string $relation_type Optional relation type filter.
	 * @return int|false
	 */
	public function clear_all($relation_type = '') {
		if (!empty($relation_type)) {
			return $this->wpdb->delete(
				$this->table,
				array('relation_type' => sanitize_key($relation_type)),
				array('%s')
			);
		}

		return $this->wpdb->query("TRUNCATE TABLE {$this->table}");
	}

	/**
	 * Count total relationship records.
	 *
	 * @param string $relation_type Optional filter.
	 * @return int
	 */
	public function count($relation_type = '') {
		if (!empty($relation_type)) {
			return (int) $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table} WHERE relation_type = %s",
					sanitize_key($relation_type)
				)
			);
		}

		return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
	}
}
