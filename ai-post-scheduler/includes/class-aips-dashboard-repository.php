<?php
if (!defined('ABSPATH')) {
	exit;
}

if (!trait_exists('AIPS_Cacheable_Repository')) {
	require_once __DIR__ . '/trait-aips-cacheable-repository.php';
}

/**
 * AIPS_Dashboard_Repository
 *
 * Repository for dashboard-specific read queries and aggregates.
 */
class AIPS_Dashboard_Repository {
	use AIPS_Cacheable_Repository;

	/**
	 * @var wpdb WordPress database abstraction object.
	 */
	private $wpdb;

	/**
	 * @var string
	 */
	private $history_table;

	/**
	 * @var string
	 */
	private $history_log_table;

	/**
	 * @var string
	 */
	private $schedule_table;

	/**
	 * @var string
	 */
	private $templates_table;

	/**
	 * @var string
	 */
	private $author_topics_table;

	/**
	 * @var string
	 */
	private $authors_table;

	/**
	 * Initialize the repository.
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb = $wpdb;

		$this->history_table = $wpdb->prefix . 'aips_history';
		$this->history_log_table = $wpdb->prefix . 'aips_history_log';
		$this->schedule_table = $wpdb->prefix . 'aips_schedule';
		$this->templates_table = $wpdb->prefix . 'aips_templates';
		$this->author_topics_table = $wpdb->prefix . 'aips_author_topics';
		$this->authors_table = $wpdb->prefix . 'aips_authors';
	}

	/**
	 * Get summary stats for a dashboard date range.
	 *
	 * @param int $from_ts Start timestamp.
	 * @param int $to_ts End timestamp.
	 * @return array
	 */
	public function get_summary_stats($from_ts, $to_ts) {
		return $this->cache_read(
			'dashboard.get_summary_stats',
			array(
				'from_ts' => (int) $from_ts,
				'to_ts'   => (int) $to_ts,
			),
			function() use ( $from_ts, $to_ts ) {
				$methods = $this->auxiliary_creation_methods();
				$placeholders = $this->placeholders_for($methods);

				$row = $this->wpdb->get_row($this->wpdb->prepare(
					"SELECT
						COUNT(*) as total,
						SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
						SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
						SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
						SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) as partial
					 FROM {$this->history_table}
					 WHERE COALESCE(creation_method, '') NOT IN ({$placeholders})
					   AND NOT (creation_method IS NULL AND template_id IS NULL AND topic_id IS NULL AND post_id IS NULL AND author_id IS NULL)
					   AND created_at >= %d AND created_at <= %d",
					array_merge($methods, array( (int) $from_ts, (int) $to_ts ))
				));

				return array(
					'total'      => isset($row->total) ? (int) $row->total : 0,
					'completed'  => isset($row->completed) ? (int) $row->completed : 0,
					'failed'     => isset($row->failed) ? (int) $row->failed : 0,
					'processing' => isset($row->processing) ? (int) $row->processing : 0,
					'partial'    => isset($row->partial) ? (int) $row->partial : 0,
				);
			}
		);
	}

	/**
	 * Count distinct schedule executions in a dashboard date range.
	 *
	 * @param int $from_ts Start timestamp.
	 * @param int $to_ts End timestamp.
	 * @return int
	 */
	public function get_schedules_run_count($from_ts, $to_ts) {
		return (int) $this->cache_read(
			'dashboard.get_schedules_run_count',
			array(
				'from_ts' => (int) $from_ts,
				'to_ts'   => (int) $to_ts,
			),
			function() use ( $from_ts, $to_ts ) {
				return $this->wpdb->get_var($this->wpdb->prepare(
					"SELECT COUNT(DISTINCT correlation_id)
					 FROM {$this->history_table}
					 WHERE created_at >= %d AND created_at <= %d
					   AND creation_method IN ('scheduled', 'author_topic_gen', 'author_post_gen', 'batch_job')",
					(int) $from_ts,
					(int) $to_ts
				));
			}
		);
	}

	/**
	 * Get topic summary stats for a dashboard date range.
	 *
	 * @param int $from_ts Start timestamp.
	 * @param int $to_ts End timestamp.
	 * @return array
	 */
	public function get_topics_stats($from_ts, $to_ts) {
		return $this->cache_read(
			'dashboard.get_topics_stats',
			array(
				'from_ts' => (int) $from_ts,
				'to_ts'   => (int) $to_ts,
			),
			function() use ( $from_ts, $to_ts ) {
				$row = $this->wpdb->get_row($this->wpdb->prepare(
					"SELECT
						COUNT(*) as total,
						SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
						SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
						SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
					 FROM {$this->author_topics_table}
					 WHERE generated_at >= %d AND generated_at <= %d",
					(int) $from_ts,
					(int) $to_ts
				));

				return array(
					'total'    => isset($row->total) ? (int) $row->total : 0,
					'pending'  => isset($row->pending) ? (int) $row->pending : 0,
					'approved' => isset($row->approved) ? (int) $row->approved : 0,
					'rejected' => isset($row->rejected) ? (int) $row->rejected : 0,
				);
			}
		);
	}

	/**
	 * Get AI request/error stats for a dashboard date range.
	 *
	 * @param int $from_ts Start timestamp.
	 * @param int $to_ts End timestamp.
	 * @return array
	 */
	public function get_ai_stats($from_ts, $to_ts) {
		return $this->cache_read(
			'dashboard.get_ai_stats',
			array(
				'from_ts' => (int) $from_ts,
				'to_ts'   => (int) $to_ts,
			),
			function() use ( $from_ts, $to_ts ) {
				$row = $this->wpdb->get_row($this->wpdb->prepare(
					"SELECT
						SUM(CASE WHEN hl.history_type_id = %d THEN 1 ELSE 0 END) as ai_calls,
						SUM(CASE WHEN hl.history_type_id = %d AND hl.details LIKE '%%AI generation failed%%' THEN 1 ELSE 0 END) as ai_errors
					 FROM {$this->history_log_table} hl
					 INNER JOIN {$this->history_table} h ON hl.history_id = h.id
					 WHERE h.created_at >= %d AND h.created_at <= %d",
					AIPS_History_Type::AI_REQUEST,
					AIPS_History_Type::ERROR,
					(int) $from_ts,
					(int) $to_ts
				));

				return array(
					'ai_calls'  => isset($row->ai_calls) ? (int) $row->ai_calls : 0,
					'ai_errors' => isset($row->ai_errors) ? (int) $row->ai_errors : 0,
				);
			}
		);
	}

	/**
	 * Count active schedules due in a future window.
	 *
	 * @param int $from_ts Start timestamp.
	 * @param int $to_ts End timestamp.
	 * @return int
	 */
	public function get_upcoming_runs_count($from_ts, $to_ts) {
		return (int) $this->cache_read(
			'dashboard.get_upcoming_runs_count',
			array(
				'from_ts' => (int) $from_ts,
				'to_ts'   => (int) $to_ts,
			),
			function() use ( $from_ts, $to_ts ) {
				return $this->wpdb->get_var($this->wpdb->prepare(
					"SELECT COUNT(*)
					 FROM {$this->schedule_table}
					 WHERE is_active = 1 AND next_run >= %d AND next_run <= %d",
					(int) $from_ts,
					(int) $to_ts
				));
			}
		);
	}

	/**
	 * Get recent completed posts for a dashboard date range.
	 *
	 * @param int $from_ts Start timestamp.
	 * @param int $to_ts End timestamp.
	 * @param int $limit Limit.
	 * @return array
	 */
	public function get_recent_posts($from_ts, $to_ts, $limit = 10) {
		return $this->cache_read(
			'dashboard.get_recent_posts',
			array(
				'from_ts' => (int) $from_ts,
				'to_ts'   => (int) $to_ts,
				'limit'   => (int) $limit,
			),
			function() use ( $from_ts, $to_ts, $limit ) {
				$methods = $this->auxiliary_creation_methods();
				$placeholders = $this->placeholders_for($methods);

				return $this->wpdb->get_results($this->wpdb->prepare(
					"SELECT h.id, h.uuid, h.correlation_id, h.post_id, h.template_id, h.campaign_id, h.topic_id, h.status, h.generated_title, h.created_at, h.completed_at, h.creation_method,
							t.name as template_name
					 FROM {$this->history_table} h
					 LEFT JOIN {$this->templates_table} t ON h.template_id = t.id
					 WHERE h.created_at >= %d AND h.created_at <= %d
					   AND COALESCE(h.creation_method, '') NOT IN ({$placeholders})
					   AND NOT (h.creation_method IS NULL AND h.template_id IS NULL AND h.topic_id IS NULL AND h.post_id IS NULL AND h.author_id IS NULL)
					 ORDER BY h.created_at DESC
					 LIMIT %d",
					array_merge(
						array( (int) $from_ts, (int) $to_ts ),
						$methods,
						array( (int) $limit )
					)
				));
			}
		);
	}

	/**
	 * Get recent topics for a dashboard date range.
	 *
	 * @param int $from_ts Start timestamp.
	 * @param int $to_ts End timestamp.
	 * @param int $limit Limit.
	 * @return array
	 */
	public function get_recent_topics($from_ts, $to_ts, $limit = 10) {
		return $this->cache_read(
			'dashboard.get_recent_topics',
			array(
				'from_ts' => (int) $from_ts,
				'to_ts'   => (int) $to_ts,
				'limit'   => (int) $limit,
			),
			function() use ( $from_ts, $to_ts, $limit ) {
				return $this->wpdb->get_results($this->wpdb->prepare(
					"SELECT t.*, a.name as author_name
					 FROM {$this->author_topics_table} t
					 LEFT JOIN {$this->authors_table} a ON t.author_id = a.id
					 WHERE t.generated_at >= %d AND t.generated_at <= %d
					 ORDER BY t.generated_at DESC
					 LIMIT %d",
					(int) $from_ts,
					(int) $to_ts,
					(int) $limit
				));
			}
		);
	}

	/**
	 * Get completed posts generated from author topics.
	 *
	 * @param int $from_ts Start timestamp.
	 * @param int $to_ts End timestamp.
	 * @param int $limit Limit.
	 * @return array
	 */
	public function get_posts_by_topic($from_ts, $to_ts, $limit = 10) {
		return $this->cache_read(
			'dashboard.get_posts_by_topic',
			array(
				'from_ts' => (int) $from_ts,
				'to_ts'   => (int) $to_ts,
				'limit'   => (int) $limit,
			),
			function() use ( $from_ts, $to_ts, $limit ) {
				return $this->wpdb->get_results($this->wpdb->prepare(
					"SELECT h.post_id, h.generated_title, h.completed_at, t.topic_title, a.name as author_name
					 FROM {$this->history_table} h
					 INNER JOIN {$this->author_topics_table} t ON h.topic_id = t.id
					 LEFT JOIN {$this->authors_table} a ON h.author_id = a.id
					 WHERE h.status = 'completed' AND h.created_at >= %d AND h.created_at <= %d
					 ORDER BY h.completed_at DESC
					 LIMIT %d",
					(int) $from_ts,
					(int) $to_ts,
					(int) $limit
				));
			}
		);
	}

	/**
	 * Get recent executed schedules for a dashboard date range.
	 *
	 * @param int $from_ts Start timestamp.
	 * @param int $to_ts End timestamp.
	 * @param int $limit Limit.
	 * @return array
	 */
	public function get_executed_schedules($from_ts, $to_ts, $limit = 10) {
		return $this->cache_read(
			'dashboard.get_executed_schedules',
			array(
				'from_ts' => (int) $from_ts,
				'to_ts'   => (int) $to_ts,
				'limit'   => (int) $limit,
			),
			function() use ( $from_ts, $to_ts, $limit ) {
				return $this->wpdb->get_results($this->wpdb->prepare(
					"SELECT h.id, h.uuid, h.status, h.completed_at, h.created_at, h.creation_method,
							s.title as schedule_title, t.name as template_name, a.name as author_name
					 FROM {$this->history_table} h
					 LEFT JOIN {$this->schedule_table} s ON (h.template_id = s.template_id OR h.author_id = s.author_id)
					 LEFT JOIN {$this->templates_table} t ON h.template_id = t.id
					 LEFT JOIN {$this->authors_table} a ON h.author_id = a.id
					 WHERE h.created_at >= %d AND h.created_at <= %d
					   AND h.creation_method IN ('scheduled', 'author_topic_gen', 'author_post_gen', 'batch_job')
					 ORDER BY h.created_at DESC
					 LIMIT %d",
					(int) $from_ts,
					(int) $to_ts,
					(int) $limit
				));
			}
		);
	}

	/**
	 * Get daily generation stats for charting.
	 *
	 * @param int $from_ts Start timestamp.
	 * @param int $to_ts End timestamp.
	 * @return array
	 */
	public function get_daily_generation_stats($from_ts, $to_ts) {
		return $this->cache_read(
			'dashboard.get_daily_generation_stats',
			array(
				'from_ts' => (int) $from_ts,
				'to_ts'   => (int) $to_ts,
			),
			function() use ( $from_ts, $to_ts ) {
				$methods = $this->auxiliary_creation_methods();
				$placeholders = $this->placeholders_for($methods);

				$results = $this->wpdb->get_results($this->wpdb->prepare(
					"SELECT
						DATE(FROM_UNIXTIME(created_at)) AS day,
						SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
						SUM(CASE WHEN status = 'failed'    THEN 1 ELSE 0 END) AS failed,
						COUNT(*) AS total
					 FROM {$this->history_table}
					 WHERE created_at >= %d AND created_at <= %d
					   AND COALESCE(creation_method, '') NOT IN ({$placeholders})
					   AND NOT (creation_method IS NULL AND template_id IS NULL AND topic_id IS NULL AND post_id IS NULL AND author_id IS NULL)
					 GROUP BY day
					 ORDER BY day ASC",
					array_merge(
						array( (int) $from_ts, (int) $to_ts ),
						$methods
					)
				));

				$data = array();
				foreach ($results as $row) {
					$data[$row->day] = array(
						'completed' => (int) $row->completed,
						'failed'    => (int) $row->failed,
						'total'     => (int) $row->total,
					);
				}

				return $data;
			}
		);
	}

	/**
	 * Get daily topic counts for charting.
	 *
	 * @param int $from_ts Start timestamp.
	 * @param int $to_ts End timestamp.
	 * @return array
	 */
	public function get_daily_topic_totals($from_ts, $to_ts) {
		return $this->cache_read(
			'dashboard.get_daily_topic_totals',
			array(
				'from_ts' => (int) $from_ts,
				'to_ts'   => (int) $to_ts,
			),
			function() use ( $from_ts, $to_ts ) {
				$results = $this->wpdb->get_results($this->wpdb->prepare(
					"SELECT DATE(FROM_UNIXTIME(generated_at)) AS day, COUNT(*) AS total
					 FROM {$this->author_topics_table}
					 WHERE generated_at >= %d AND generated_at <= %d
					 GROUP BY day
					 ORDER BY day ASC",
					(int) $from_ts,
					(int) $to_ts
				));

				$data = array();
				foreach ($results as $row) {
					$data[$row->day] = (int) $row->total;
				}

				return $data;
			}
		);
	}

	/**
	 * Get daily AI call/error counts for charting.
	 *
	 * @param int $from_ts Start timestamp.
	 * @param int $to_ts End timestamp.
	 * @return array
	 */
	public function get_daily_ai_stats($from_ts, $to_ts) {
		return $this->cache_read(
			'dashboard.get_daily_ai_stats',
			array(
				'from_ts' => (int) $from_ts,
				'to_ts'   => (int) $to_ts,
			),
			function() use ( $from_ts, $to_ts ) {
				$results = $this->wpdb->get_results($this->wpdb->prepare(
					"SELECT DATE(FROM_UNIXTIME(hl.timestamp)) AS day,
							SUM(CASE WHEN hl.history_type_id = %d THEN 1 ELSE 0 END) AS ai_calls,
							SUM(CASE WHEN hl.history_type_id = %d AND hl.details LIKE '%%AI generation failed%%' THEN 1 ELSE 0 END) AS ai_errors
					 FROM {$this->history_log_table} hl
					 INNER JOIN {$this->history_table} h ON hl.history_id = h.id
					 WHERE h.created_at >= %d AND h.created_at <= %d
					 GROUP BY day
					 ORDER BY day ASC",
					AIPS_History_Type::AI_REQUEST,
					AIPS_History_Type::ERROR,
					(int) $from_ts,
					(int) $to_ts
				));

				$data = array();
				foreach ($results as $row) {
					$data[$row->day] = array(
						'ai_calls'  => (int) $row->ai_calls,
						'ai_errors' => (int) $row->ai_errors,
					);
				}

				return $data;
			}
		);
	}

	/**
	 * Return the repository cache group.
	 *
	 * @return string
	 */
	protected function repository_cache_group(): string {
		return 'aips_dashboard';
	}

	/**
	 * Return the repository cache policies.
	 *
	 * @return array
	 */
	protected function repository_cache_policies(): array {
		return array(
			'dashboard.get_summary_stats' => array(
				'tier'        => 'short',
				'description' => 'Cache dashboard summary aggregates.',
			),
			'dashboard.get_schedules_run_count' => array(
				'tier'        => 'short',
				'description' => 'Cache dashboard schedule execution counts.',
			),
			'dashboard.get_topics_stats' => array(
				'tier'        => 'short',
				'description' => 'Cache dashboard topic aggregates.',
			),
			'dashboard.get_ai_stats' => array(
				'tier'        => 'short',
				'description' => 'Cache dashboard AI request/error aggregates.',
			),
			'dashboard.get_upcoming_runs_count' => array(
				'tier'        => 'short',
				'description' => 'Cache upcoming dashboard run counts.',
			),
			'dashboard.get_recent_posts' => array(
				'tier'        => 'short',
				'description' => 'Cache recent generated post rows for the dashboard.',
			),
			'dashboard.get_recent_topics' => array(
				'tier'        => 'short',
				'description' => 'Cache recent author topic rows for the dashboard.',
			),
			'dashboard.get_posts_by_topic' => array(
				'tier'        => 'short',
				'description' => 'Cache topic-derived post rows for the dashboard.',
			),
			'dashboard.get_executed_schedules' => array(
				'tier'        => 'short',
				'description' => 'Cache executed schedule rows for the dashboard.',
			),
			'dashboard.get_daily_generation_stats' => array(
				'tier'        => 'short',
				'description' => 'Cache daily generation chart data.',
			),
			'dashboard.get_daily_topic_totals' => array(
				'tier'        => 'short',
				'description' => 'Cache daily topic chart data.',
			),
			'dashboard.get_daily_ai_stats' => array(
				'tier'        => 'short',
				'description' => 'Cache daily AI chart data.',
			),
		);
	}

	/**
	 * Return the shared auxiliary creation methods excluded from dashboard generation aggregates.
	 *
	 * @return array
	 */
	private function auxiliary_creation_methods() {
		return array('schedule_lifecycle', 'template_lifecycle', 'campaign_lifecycle');
	}

	/**
	 * Build a placeholder list for a set of string values.
	 *
	 * @param array $values Values to bind.
	 * @return string
	 */
	private function placeholders_for(array $values) {
		return implode(', ', array_fill(0, count($values), '%s'));
	}
}
