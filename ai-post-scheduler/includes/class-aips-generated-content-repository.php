<?php
/**
 * Generated Content Repository
 *
 * Database abstraction layer for all AI-generated content (Completed posts,
 * pending review drafts, partial generations, and summary KPIs).
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!trait_exists('AIPS_Cacheable_Repository')) {
	require_once __DIR__ . '/trait-aips-cacheable-repository.php';
}

/**
 * Class AIPS_Generated_Content_Repository
 */
class AIPS_Generated_Content_Repository implements AIPS_Generated_Content_Repository_Interface {
	use AIPS_Cacheable_Repository;

	/**
	 * @var self|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * Get shared singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @var wpdb WordPress database abstraction object.
	 */
	private $wpdb;

	/**
	 * @var string History table name.
	 */
	private $history_table;

	/**
	 * @var string Posts table name.
	 */
	private $posts_table;

	/**
	 * Initialize repository.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->history_table = $wpdb->prefix . 'aips_history';
		$this->posts_table = $wpdb->posts;
	}

	/**
	 * Get summary KPI statistics for generated content.
	 *
	 * @return array
	 */
	public function get_content_kpis() {
		$results = $this->wpdb->get_row(
			"SELECT 
				COUNT(DISTINCT h.id) AS total_content,
				SUM(CASE WHEN p.post_status = 'publish' AND (h.is_currently_incomplete IS NULL OR h.is_currently_incomplete = 0) THEN 1 ELSE 0 END) AS total_published,
				SUM(CASE WHEN p.post_status = 'future' AND (h.is_currently_incomplete IS NULL OR h.is_currently_incomplete = 0) THEN 1 ELSE 0 END) AS total_scheduled,
				SUM(CASE WHEN (p.post_status = 'draft' OR p.post_status = 'pending') AND (h.is_currently_incomplete IS NULL OR h.is_currently_incomplete = 0) THEN 1 ELSE 0 END) AS total_pending_review,
				SUM(CASE WHEN h.is_currently_incomplete = 1 OR h.status = 'failed' THEN 1 ELSE 0 END) AS total_incomplete,
				AVG(CASE WHEN h.completed_at > 0 AND h.completed_at >= h.created_at THEN (h.completed_at - h.created_at) ELSE NULL END) AS avg_duration_seconds
			FROM {$this->history_table} h
			LEFT JOIN {$this->posts_table} p ON h.post_id = p.ID
			WHERE (h.post_id IS NOT NULL AND h.post_id > 0) OR h.is_currently_incomplete = 1",
			ARRAY_A
		);

		if (!$results) {
			return array(
				'total_content'        => 0,
				'total_published'      => 0,
				'total_scheduled'      => 0,
				'total_pending_review' => 0,
				'total_incomplete'     => 0,
				'avg_duration_seconds' => 0.0,
			);
		}

		return array(
			'total_content'        => (int) (isset($results['total_content']) ? $results['total_content'] : 0),
			'total_published'      => (int) (isset($results['total_published']) ? $results['total_published'] : 0),
			'total_scheduled'      => (int) (isset($results['total_scheduled']) ? $results['total_scheduled'] : 0),
			'total_pending_review' => (int) (isset($results['total_pending_review']) ? $results['total_pending_review'] : 0),
			'total_incomplete'     => (int) (isset($results['total_incomplete']) ? $results['total_incomplete'] : 0),
			'avg_duration_seconds' => round((float) (isset($results['avg_duration_seconds']) ? $results['avg_duration_seconds'] : 0), 1),
		);
	}

	/**
	 * Get paginated unified content records across all states.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_unified_content(array $args = array()) {
		$defaults = array(
			'per_page'    => 20,
			'page'        => 1,
			'search'      => '',
			'author_id'   => 0,
			'template_id' => 0,
			'campaign_id' => 0,
			'status'      => '',
			'orderby'     => 'h.created_at',
			'order'       => 'DESC',
		);

		$params = wp_parse_args($args, $defaults);
		$per_page = max(1, (int) $params['per_page']);
		$page = max(1, (int) $params['page']);
		$offset = ($page - 1) * $per_page;

		$where = array();
		$values = array();

		// Base condition: linked to a valid post or flagged as incomplete
		$where[] = '((h.post_id IS NOT NULL AND h.post_id > 0) OR h.is_currently_incomplete = 1)';

		// Search query in post title or generated title
		if (!empty($params['search'])) {
			$search_like = '%' . $this->wpdb->esc_like($params['search']) . '%';
			$where[] = '(p.post_title LIKE %s OR h.generated_title LIKE %s)';
			$values[] = $search_like;
			$values[] = $search_like;
		}

		// Author filter
		if (!empty($params['author_id'])) {
			$where[] = '(h.author_id = %d OR p.post_author = %d)';
			$values[] = (int) $params['author_id'];
			$values[] = (int) $params['author_id'];
		}

		// Template filter
		if (!empty($params['template_id'])) {
			$where[] = 'h.template_id = %d';
			$values[] = (int) $params['template_id'];
		}

		// Campaign filter
		if (!empty($params['campaign_id'])) {
			$where[] = 'h.campaign_id = %d';
			$values[] = (int) $params['campaign_id'];
		}

		// Status / State filter
		$status = sanitize_key($params['status']);
		if ($status === 'incomplete') {
			$where[] = '(h.is_currently_incomplete = 1 OR h.status = %s)';
			$values[] = 'failed';
		} elseif ($status === 'draft' || $status === 'review') {
			$where[] = "((p.post_status = 'draft' OR p.post_status = 'pending') AND (h.is_currently_incomplete IS NULL OR h.is_currently_incomplete = 0))";
		} elseif ($status === 'publish') {
			$where[] = "(p.post_status = 'publish' AND (h.is_currently_incomplete IS NULL OR h.is_currently_incomplete = 0))";
		} elseif ($status === 'future') {
			$where[] = "(p.post_status = 'future' AND (h.is_currently_incomplete IS NULL OR h.is_currently_incomplete = 0))";
		} elseif ($status === 'trash') {
			$where[] = "p.post_status = 'trash'";
		}

		$where_clause = implode(' AND ', $where);

		// Count total records
		$count_sql = "SELECT COUNT(DISTINCT h.id) FROM {$this->history_table} h LEFT JOIN {$this->posts_table} p ON h.post_id = p.ID WHERE {$where_clause}";
		$total = (int) (!empty($values) ? $this->wpdb->get_var($this->wpdb->prepare($count_sql, $values)) : $this->wpdb->get_var($count_sql));

		if ($total === 0) {
			return array(
				'items'        => array(),
				'total'        => 0,
				'pages'        => 0,
				'current_page' => $page,
			);
		}

		// Order & Direction
		$order = strtoupper($params['order']) === 'ASC' ? 'ASC' : 'DESC';
		$orderby = 'h.created_at';

		$query_sql = "SELECT 
				h.id AS history_id,
				h.uuid,
				h.correlation_id,
				h.post_id,
				h.template_id,
				h.campaign_id,
				h.author_id,
				h.topic_id,
				h.status AS history_status,
				h.creation_method,
				h.generated_title,
				h.created_at,
				h.completed_at,
				h.component_statuses,
				h.is_currently_incomplete,
				p.ID AS wp_post_id,
				p.post_title,
				p.post_content,
				p.post_status,
				p.post_author,
				p.post_date,
				p.post_modified
			FROM {$this->history_table} h
			LEFT JOIN {$this->posts_table} p ON h.post_id = p.ID
			WHERE {$where_clause}
			ORDER BY {$orderby} {$order}
			LIMIT %d OFFSET %d";

		$query_values = $values;
		$query_values[] = $per_page;
		$query_values[] = $offset;

		$items = $this->wpdb->get_results($this->wpdb->prepare($query_sql, $query_values));

		return array(
			'items'        => is_array($items) ? $items : array(),
			'total'        => $total,
			'pages'        => (int) ceil($total / $per_page),
			'current_page' => $page,
		);
	}

	/**
	 * Repository cache group name.
	 *
	 * @return string
	 */
	protected function repository_cache_group(): string {
		return 'aips_generated_content';
	}

	/**
	 * Explicit repository cache policies.
	 *
	 * @return array
	 */
	protected function repository_cache_policies(): array {
		return array(
			'generated_content.get_kpis' => array(
				'tier'        => 'short',
				'tags'        => array('history', 'content_kpis'),
				'description' => 'Cache for generated content aggregate statistics.',
			),
		);
	}
}
