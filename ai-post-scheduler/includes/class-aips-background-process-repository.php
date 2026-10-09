<?php
/**
 * Background Process Repository
 *
 * All SQL for the `aips_background_processes` table, which records one row per
 * run of a managed background process (for example the Internal Links indexer).
 *
 * @package AI_Post_Scheduler
 * @since 3.7.11
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Background_Process_Repository
 */
class AIPS_Background_Process_Repository {

	/**
	 * Run statuses.
	 */
	const STATUS_RUNNING       = 'running';
	const STATUS_WAITING_QUOTA = 'waiting_quota';
	const STATUS_COOLDOWN      = 'cooldown';
	const STATUS_PAUSED        = 'paused';
	const STATUS_COMPLETED     = 'completed';
	const STATUS_FAILED        = 'failed';
	const STATUS_CANCELLED     = 'cancelled';

	/**
	 * Days after which finished runs are removed by cleanup_old().
	 */
	const CLEANUP_DAYS = 30;

	/**
	 * Statuses in which a run is actively ticking (a cron tick is expected).
	 *
	 * @return string[]
	 */
	public static function active_statuses(): array {
		return array(self::STATUS_RUNNING, self::STATUS_WAITING_QUOTA, self::STATUS_COOLDOWN);
	}

	/**
	 * Statuses in which a run still owns its process key (active or paused).
	 *
	 * @return string[]
	 */
	public static function open_statuses(): array {
		return array_merge(self::active_statuses(), array(self::STATUS_PAUSED));
	}

	/**
	 * @return string Table name with the WordPress prefix.
	 */
	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'aips_background_processes';
	}

	/**
	 * Create a run in the running state.
	 *
	 * @param string $process_key Process key.
	 * @param int    $total       Items to process.
	 * @param array  $options     Scalar options for the run.
	 * @param int    $ai_budget   Maximum AI calls for the run (0 = unlimited).
	 * @param int    $user_id     User who started the run.
	 * @return int Run ID, or 0 on failure.
	 */
	public function create(string $process_key, int $total, array $options = array(), int $ai_budget = 0, int $user_id = 0): int {
		global $wpdb;

		$now = time();

		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$this->table(),
			array(
				'process_key'     => sanitize_key($process_key),
				'status'          => self::STATUS_RUNNING,
				'total'           => max(0, $total),
				'options_json'    => wp_json_encode($options),
				'ai_calls_budget' => max(0, $ai_budget),
				'next_run_at'     => $now,
				'started_by'      => max(0, $user_id),
				'started_at'      => $now,
				'updated_at'      => $now,
			),
			array('%s', '%s', '%d', '%s', '%d', '%d', '%d', '%d', '%d')
		);

		return $result === false ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * @param int $id Run ID.
	 * @return object|null
	 */
	public function get(int $id): ?object {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d LIMIT 1", $id) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return $row ? $this->hydrate($row) : null;
	}

	/**
	 * The run that currently owns a process key (active or paused), if any.
	 *
	 * @param string $process_key Process key.
	 * @return object|null
	 */
	public function get_open(string $process_key): ?object {
		global $wpdb;

		$statuses     = self::open_statuses();
		$placeholders = implode(',', array_fill(0, count($statuses), '%s'));

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE process_key = %s AND status IN ({$placeholders}) ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...array_merge(array(sanitize_key($process_key)), $statuses)
			)
		);

		return $row ? $this->hydrate($row) : null;
	}

	/**
	 * The most recent run of a process key, whatever its status.
	 *
	 * @param string $process_key Process key.
	 * @return object|null
	 */
	public function get_latest(string $process_key): ?object {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE process_key = %s ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				sanitize_key($process_key)
			)
		);

		return $row ? $this->hydrate($row) : null;
	}

	/**
	 * Recent runs, newest first.
	 *
	 * @param int         $limit       Maximum rows.
	 * @param string|null $process_key Restrict to one process key.
	 * @return object[]
	 */
	public function get_recent(int $limit = 20, ?string $process_key = null): array {
		global $wpdb;

		$limit = max(1, min(200, $limit));

		if ($process_key !== null && $process_key !== '') {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE process_key = %s ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				sanitize_key($process_key),
				$limit
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$this->table()} ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			);
		}

		$rows = $wpdb->get_results($sql); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return array_map(array($this, 'hydrate'), (array) $rows);
	}

	/**
	 * Add progress to a run without touching its status.
	 *
	 * Deltas are applied atomically so a pause or cancel written by another
	 * request while a tick is running is never overwritten.
	 *
	 * @param int         $id              Run ID.
	 * @param int         $processed_delta Items processed in this slice.
	 * @param int         $failed_delta    Items that failed in this slice.
	 * @param int         $cursor_id       New cursor value (kept when lower than the stored one).
	 * @param int         $ai_calls_delta  AI calls made in this slice.
	 * @param string|null $message         Optional status message.
	 * @return bool
	 */
	public function add_progress(int $id, int $processed_delta, int $failed_delta, int $cursor_id, int $ai_calls_delta, ?string $message = null): bool {
		global $wpdb;

		$now = time();

		$result = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				"UPDATE {$this->table()} SET processed = processed + %d, failed = failed + %d, cursor_id = GREATEST(cursor_id, %d), ai_calls_used = ai_calls_used + %d, message = %s, last_tick_at = %d, updated_at = %d WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				max(0, $processed_delta),
				max(0, $failed_delta),
				max(0, $cursor_id),
				max(0, $ai_calls_delta),
				(string) $message,
				$now,
				$now,
				$id
			)
		);

		return $result !== false;
	}

	/**
	 * Remove a run's AI call budget (it then runs without a limit).
	 *
	 * @param int $id Run ID.
	 * @return bool
	 */
	public function clear_ai_budget(int $id): bool {
		global $wpdb;

		$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$this->table(),
			array('ai_calls_budget' => 0, 'updated_at' => time()),
			array('id' => $id),
			array('%d', '%d'),
			array('%d')
		);

		return $result !== false;
	}

	/**
	 * Move a run to a new status, only when it is currently in one of $from.
	 *
	 * @param int         $id          Run ID.
	 * @param string      $status      New status.
	 * @param string[]    $from        Statuses the run must currently be in.
	 * @param string|null $message     Optional status message.
	 * @param int|null    $next_run_at Optional next tick timestamp.
	 * @return bool True when the row changed.
	 */
	public function transition(int $id, string $status, array $from, ?string $message = null, ?int $next_run_at = null): bool {
		global $wpdb;

		$from = array_values(array_filter(array_map('sanitize_key', $from)));
		if (empty($from)) {
			return false;
		}

		$now      = time();
		$finished = in_array($status, array(self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED), true) ? $now : 0;
		$sets     = array('status = %s', 'updated_at = %d', 'finished_at = %d');
		$args     = array(sanitize_key($status), $now, $finished);

		if ($message !== null) {
			$sets[] = 'message = %s';
			$args[] = $message;
		}

		if ($next_run_at !== null) {
			$sets[] = 'next_run_at = %d';
			$args[] = max(0, $next_run_at);
		}

		$placeholders = implode(',', array_fill(0, count($from), '%s'));

		$result = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				"UPDATE {$this->table()} SET " . implode(', ', $sets) . " WHERE id = %d AND status IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...array_merge($args, array($id), $from)
			)
		);

		return $result !== false && $result > 0;
	}

	/**
	 * Delete finished runs older than CLEANUP_DAYS.
	 *
	 * @return int Rows deleted.
	 */
	public function cleanup_old(): int {
		global $wpdb;

		$cutoff = time() - (self::CLEANUP_DAYS * DAY_IN_SECONDS);

		$deleted = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				"DELETE FROM {$this->table()} WHERE status IN ('completed','failed','cancelled') AND updated_at < %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cutoff
			)
		);

		return (int) $deleted;
	}

	/**
	 * Decode the JSON options column.
	 *
	 * @param object $row Raw row.
	 * @return object
	 */
	private function hydrate(object $row): object {
		$row->options = json_decode((string) $row->options_json, true);
		if (!is_array($row->options)) {
			$row->options = array();
		}

		foreach (array('id', 'total', 'processed', 'failed', 'cursor_id', 'ai_calls_used', 'ai_calls_budget', 'next_run_at', 'last_tick_at', 'started_by', 'started_at', 'finished_at', 'updated_at') as $field) {
			$row->$field = (int) $row->$field;
		}

		return $row;
	}
}
