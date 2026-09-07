<?php
/**
 * Content Auditor Repository
 *
 * Database persistence layer for historical content audit snapshots and health scores.
 *
 * @package AI_Post_Scheduler
 * @since 3.4.2
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Content_Auditor_Repository {

	/**
	 * @var wpdb WordPress database abstraction object.
	 */
	private $wpdb;

	/**
	 * @var string Full table name with prefix.
	 */
	private $table_name;

	/**
	 * Constructor.
	 *
	 * @param wpdb|null $wpdb Optional wpdb instance for testing.
	 */
	public function __construct($wpdb = null) {
		global $wpdb;
		$this->wpdb       = $wpdb ?: $GLOBALS['wpdb'];
		$this->table_name = $this->wpdb->prefix . 'aips_content_audits';
	}

	/**
	 * Save an audit report snapshot.
	 *
	 * @param array $report Full audit report from AIPS_Content_Auditor_Engine.
	 * @return int|false Inserted record ID or false on failure.
	 */
	public function save(array $report) {
		$niche = isset($report['niche']) ? sanitize_text_field($report['niche']) : 'General';
		$scorecard = isset($report['health_scorecard']) ? (array) $report['health_scorecard'] : array();

		$overall_score         = isset($scorecard['overall_score']) ? (int) $scorecard['overall_score'] : 0;
		$freshness_score       = isset($scorecard['freshness_score']) ? (int) $scorecard['freshness_score'] : 0;
		$link_score            = isset($scorecard['link_score']) ? (int) $scorecard['link_score'] : 0;
		$cannibalization_score = isset($scorecard['cannibalization_score']) ? (int) $scorecard['cannibalization_score'] : 0;
		$gap_score             = isset($scorecard['gap_score']) ? (int) $scorecard['gap_score'] : 0;

		$total_posts    = isset($report['total_posts']) ? (int) $report['total_posts'] : 0;
		$orphan_count   = isset($report['modules']['links']['orphan_count']) ? (int) $report['modules']['links']['orphan_count'] : 0;
		$decay_count    = isset($report['modules']['decay']['decay_count']) ? (int) $report['modules']['decay']['decay_count'] : 0;
		$conflict_count = isset($report['modules']['cannibalization']['conflict_count']) ? (int) $report['modules']['cannibalization']['conflict_count'] : 0;
		$gap_count      = isset($report['modules']['gaps']['gap_count']) ? (int) $report['modules']['gaps']['gap_count'] : 0;

		$report_json = wp_json_encode($report);
		$now_ts      = AIPS_DateTime::now_ts();

		$data = array(
			'niche'                 => $niche,
			'overall_score'         => $overall_score,
			'freshness_score'       => $freshness_score,
			'link_score'            => $link_score,
			'cannibalization_score' => $cannibalization_score,
			'gap_score'             => $gap_score,
			'total_posts'           => $total_posts,
			'orphan_count'          => $orphan_count,
			'decay_count'           => $decay_count,
			'conflict_count'        => $conflict_count,
			'gap_count'             => $gap_count,
			'audit_report'          => $report_json,
			'created_at'            => $now_ts,
			'updated_at'            => $now_ts,
		);

		$format = array(
			'%s', // niche
			'%d', // overall_score
			'%d', // freshness_score
			'%d', // link_score
			'%d', // cannibalization_score
			'%d', // gap_score
			'%d', // total_posts
			'%d', // orphan_count
			'%d', // decay_count
			'%d', // conflict_count
			'%d', // gap_count
			'%s', // audit_report
			'%d', // created_at
			'%d', // updated_at
		);

		$result = $this->wpdb->insert($this->table_name, $data, $format);

		return $result !== false ? (int) $this->wpdb->insert_id : false;
	}

	/**
	 * Retrieve a single audit snapshot by ID.
	 *
	 * @param int $id Record ID.
	 * @return array|null
	 */
	public function get_by_id($id) {
		$id = absint($id);
		if ($id <= 0) {
			return null;
		}

		$query = $this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id);
		$row   = $this->wpdb->get_row($query, ARRAY_A);

		return $row ? $this->hydrate_row($row) : null;
	}

	/**
	 * Retrieve the most recent audit report.
	 *
	 * @param string|null $niche Optional niche filter.
	 * @return array|null
	 */
	public function get_latest($niche = null) {
		if (!empty($niche)) {
			$query = $this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE niche = %s ORDER BY created_at DESC, id DESC LIMIT 1",
				sanitize_text_field($niche)
			);
		} else {
			$query = "SELECT * FROM {$this->table_name} ORDER BY created_at DESC, id DESC LIMIT 1";
		}

		$row = $this->wpdb->get_row($query, ARRAY_A);
		return $row ? $this->hydrate_row($row) : null;
	}

	/**
	 * Retrieve paginated audit history list (lightweight summary without large report JSON).
	 *
	 * @param int         $limit Number of records to fetch.
	 * @param int         $offset Offset.
	 * @param string|null $niche Optional niche filter.
	 * @return array Array of audit summary objects.
	 */
	public function get_history($limit = 20, $offset = 0, $niche = null) {
		$limit  = max(1, min(100, (int) $limit));
		$offset = max(0, (int) $offset);

		if (!empty($niche)) {
			$query = $this->wpdb->prepare(
				"SELECT id, niche, overall_score, freshness_score, link_score, cannibalization_score, gap_score, total_posts, orphan_count, decay_count, conflict_count, gap_count, created_at, updated_at
				 FROM {$this->table_name}
				 WHERE niche = %s
				 ORDER BY created_at DESC, id DESC
				 LIMIT %d OFFSET %d",
				sanitize_text_field($niche),
				$limit,
				$offset
			);
		} else {
			$query = $this->wpdb->prepare(
				"SELECT id, niche, overall_score, freshness_score, link_score, cannibalization_score, gap_score, total_posts, orphan_count, decay_count, conflict_count, gap_count, created_at, updated_at
				 FROM {$this->table_name}
				 ORDER BY created_at DESC, id DESC
				 LIMIT %d OFFSET %d",
				$limit,
				$offset
			);
		}

		$rows = $this->wpdb->get_results($query, ARRAY_A);
		return is_array($rows) ? $rows : array();
	}

	/**
	 * Delete an audit record by ID.
	 *
	 * @param int $id Record ID.
	 * @return bool
	 */
	public function delete($id) {
		$id = absint($id);
		if ($id <= 0) {
			return false;
		}

		$result = $this->wpdb->delete($this->table_name, array('id' => $id), array('%d'));
		return $result !== false && $result > 0;
	}

	/**
	 * Count total audit records.
	 *
	 * @param string|null $niche Optional niche filter.
	 * @return int
	 */
	public function count($niche = null) {
		if (!empty($niche)) {
			$query = $this->wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE niche = %s", sanitize_text_field($niche));
		} else {
			$query = "SELECT COUNT(*) FROM {$this->table_name}";
		}

		return (int) $this->wpdb->get_var($query);
	}

	/**
	 * Hydrate and decode report JSON for a database row.
	 *
	 * @param array $row Raw database row.
	 * @return array
	 */
	private function hydrate_row(array $row) {
		if (!empty($row['audit_report']) && is_string($row['audit_report'])) {
			$decoded = json_decode($row['audit_report'], true);
			$row['report'] = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : array();
		} else {
			$row['report'] = array();
		}

		$row['id']                    = (int) $row['id'];
		$row['overall_score']         = (int) $row['overall_score'];
		$row['freshness_score']       = (int) $row['freshness_score'];
		$row['link_score']            = (int) $row['link_score'];
		$row['cannibalization_score'] = (int) $row['cannibalization_score'];
		$row['gap_score']             = (int) $row['gap_score'];
		$row['total_posts']           = (int) $row['total_posts'];
		$row['orphan_count']          = (int) $row['orphan_count'];
		$row['decay_count']           = (int) $row['decay_count'];
		$row['conflict_count']        = (int) $row['conflict_count'];
		$row['gap_count']             = (int) $row['gap_count'];
		$row['created_at']            = (int) $row['created_at'];
		$row['updated_at']            = (int) $row['updated_at'];

		return $row;
	}
}
