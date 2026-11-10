<?php
/**
 * Detect and manage stalled generation and background job history records.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_History_Stalled_Detector
 *
 * Encapsulates stalled run detection, idle threshold calculation, SQL conditions,
 * stalled counts, summaries, and closing abandoned runs.
 */
class AIPS_History_Stalled_Detector {

	/**
	 * Seconds without activity after which an in-progress run is shown as stalled.
	 */
	const STALLED_AFTER_SECONDS = 900;

	/**
	 * Seconds without activity after which a background chained job (topics,
	 * embeddings, batches) is shown as stalled. These jobs legitimately wait on
	 * cron ticks and long multi-call AI batches, so they need a wider window.
	 */
	const STALLED_AFTER_CHAIN_SECONDS = 21600;

	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * History table name.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * History log table name.
	 *
	 * @var string
	 */
	private $table_name_log;

	/**
	 * Constructor.
	 *
	 * @param wpdb|null   $wpdb           Database instance.
	 * @param string|null $table_name     History table name.
	 * @param string|null $table_name_log History log table name.
	 */
	public function __construct($wpdb = null, $table_name = null, $table_name_log = null) {
		global $wpdb;
		$this->wpdb           = $wpdb ?: $GLOBALS['wpdb'];
		$this->table_name     = $table_name ?: $this->wpdb->prefix . 'aips_history';
		$this->table_name_log = $table_name_log ?: $this->wpdb->prefix . 'aips_history_log';
	}

	/**
	 * Whether a creation method represents an asynchronous chain/cron job.
	 *
	 * @param string|null $creation_method Creation method string.
	 * @return bool
	 */
	public static function is_chain_method($creation_method) {
		return AIPS_History_Repository::is_chain_method($creation_method);
	}

	/**
	 * Inactivity threshold (seconds) after which a processing run counts as stalled.
	 *
	 * @param string|null $creation_method Raw creation_method.
	 * @return int
	 */
	public static function stalled_threshold_seconds($creation_method = '') {
		$default = self::is_chain_method($creation_method) ? self::STALLED_AFTER_CHAIN_SECONDS : self::STALLED_AFTER_SECONDS;

		/**
		 * Filters how long (seconds) an in-progress History run may go without
		 * logged activity before it is shown as Stalled.
		 *
		 * @param int    $seconds         Threshold in seconds.
		 * @param string $creation_method Run creation method.
		 */
		return max(60, (int) apply_filters('aips_history_stalled_after_seconds', $default, (string) $creation_method));
	}

	/**
	 * Resolve the display status of a run: 'stalled' replaces 'processing' once the
	 * run has been idle past its threshold; every other status is returned as is.
	 *
	 * @param string      $status          Stored status.
	 * @param string|null $creation_method Raw creation_method.
	 * @param int         $last_activity   Unix time of the newest log entry or the run start.
	 * @param int|null    $now             Current time (defaults to now).
	 * @return string
	 */
	public static function resolve_effective_status($status, $creation_method, $last_activity, $now = null) {
		if ((string) $status !== 'processing') {
			return (string) $status;
		}

		$now = $now === null ? AIPS_DateTime::now()->timestamp() : (int) $now;

		return ($now - (int) $last_activity) >= self::stalled_threshold_seconds($creation_method) ? 'stalled' : 'processing';
	}

	/**
	 * SQL condition that is true for a processing run that has gone idle.
	 *
	 * @param string $alias History table alias.
	 * @return array{0:string,1:array} Tuple of [sql, args].
	 */
	public function stalled_condition($alias = 'h') {
		$now           = AIPS_DateTime::now()->timestamp();
		$last_activity = "GREATEST({$alias}.created_at, COALESCE((SELECT MAX(sl.timestamp) FROM {$this->table_name_log} sl WHERE sl.history_id = {$alias}.id), 0))";

		$methods = $this->wpdb->get_col("SELECT DISTINCT COALESCE(creation_method, '') FROM {$this->table_name} WHERE status = 'processing'");

		$cases = '';
		$args  = array();
		foreach ((array) $methods as $method) {
			if ($method === '') {
				continue;
			}
			$cases .= ' WHEN %s THEN %d';
			$args[] = (string) $method;
			$args[] = $now - self::stalled_threshold_seconds($method);
		}
		$args[] = $now - self::stalled_threshold_seconds('');

		$threshold = $cases === ''
			? '%d'
			: "CASE COALESCE({$alias}.creation_method, ''){$cases} ELSE %d END";

		$sql = "({$alias}.status = 'processing' AND {$last_activity} < {$threshold})";

		return array($sql, $args);
	}

	/**
	 * Count stalled rows matching a given additional condition.
	 *
	 * @param string $row_condition SQL condition snippet.
	 * @param string $alias         Table alias.
	 * @return int Stalled row count.
	 */
	public function count_stalled_rows($row_condition, $alias = 'h') {
		list($stalled_sql, $stalled_args) = $this->stalled_condition($alias);

		return (int) $this->wpdb->get_var($this->wpdb->prepare(
			"SELECT COUNT(*)
			 FROM {$this->table_name} {$alias}
			 WHERE {$stalled_sql}
			   AND {$row_condition}",
			$stalled_args
		));
	}

	/**
	 * Aggregate count and oldest-idle duration for stalled processing runs.
	 *
	 * @param string|string[] $kinds Record kinds to inspect. Default 'generation'.
	 * @return array{count:int,oldest_idle_seconds:int|null}
	 */
	public function get_stalled_summary($kinds = AIPS_History_Record_Kind::GENERATION) {
		list($stalled_sql, $stalled_args) = $this->stalled_condition('h');
		$kind_sql                          = AIPS_History_Repository::kind_condition($kinds, 'h');

		$row = $this->wpdb->get_row($this->wpdb->prepare(
			"SELECT COUNT(*) AS total,
					MIN(GREATEST(h.created_at, COALESCE((SELECT MAX(sl.timestamp) FROM {$this->table_name_log} sl WHERE sl.history_id = h.id), 0))) AS oldest_activity
			 FROM {$this->table_name} h
			 WHERE {$stalled_sql}
			   AND {$kind_sql}",
			$stalled_args
		));

		$count = $row ? (int) $row->total : 0;

		return array(
			'count'               => $count,
			'oldest_idle_seconds' => ($count > 0 && $row->oldest_activity !== null)
				? max(0, AIPS_DateTime::now()->timestamp() - (int) $row->oldest_activity)
				: null,
		);
	}

	/**
	 * Close every processing run that has exceeded its stalled threshold.
	 *
	 * @param string|string[] $kinds           Record kinds to close. Default 'generation'.
	 * @param string[]        $exclude_methods Optional creation_method values that must never be closed.
	 * @return int Number of rows closed.
	 */
	public function close_stalled_processing_rows($kinds = AIPS_History_Record_Kind::GENERATION, array $exclude_methods = array()) {
		if (is_array($kinds) && !empty($kinds) && !AIPS_History_Record_Kind::is_valid(reset($kinds))) {
			$exclude_methods = $kinds;
			$kinds           = AIPS_History_Record_Kind::GENERATION;
		}

		list($stalled_sql, $stalled_args) = $this->stalled_condition('h');
		$kind_sql                          = AIPS_History_Repository::kind_condition($kinds, 'h');

		$args            = array(__('Closed automatically: no activity was recorded and the run did not finish.', 'ai-post-scheduler'));
		$args            = array_merge($args, $stalled_args);
		$where_exclude   = '';
		$exclude_methods = array_values(array_filter(array_map('strval', $exclude_methods)));
		if (!empty($exclude_methods)) {
			$where_exclude = " AND COALESCE(h.creation_method, '') NOT IN (" . implode(', ', array_fill(0, count($exclude_methods), '%s')) . ')';
			$args          = array_merge($args, $exclude_methods);
		}

		$updated = $this->wpdb->query($this->wpdb->prepare(
			"UPDATE {$this->table_name} h
			 SET h.completed_at = GREATEST(h.created_at, COALESCE((SELECT MAX(sl.timestamp) FROM {$this->table_name_log} sl WHERE sl.history_id = h.id), 0)),
				 h.error_message = CASE WHEN h.error_message IS NULL OR h.error_message = '' THEN %s ELSE h.error_message END,
				 h.status = 'terminated'
			 WHERE {$stalled_sql}
			   AND {$kind_sql}{$where_exclude}",
			$args
		));

		if ($updated === false || $updated < 1) {
			return 0;
		}

		AIPS_Config::get_instance()->get_runtime_cache('aips_history_stats', 'array')->flush();

		return (int) $updated;
	}
}
