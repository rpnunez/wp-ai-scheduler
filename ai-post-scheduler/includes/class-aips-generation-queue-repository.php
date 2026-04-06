<?php
/**
 * Generation Queue Repository
 *
 * Persistence layer for the generation job queue.  Provides atomic enqueue
 * (with idempotency), bounded batch claiming, and job status transitions.
 *
 * @package AI_Post_Scheduler
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Generation_Queue_Repository
 *
 * Manages the aips_generation_queue table.  Key guarantees:
 *
 *  - enqueue() is idempotent: only one pending/processing job may exist for a
 *    given idempotency_key at a time.  Concurrent callers use a SELECT…FOR UPDATE
 *    transaction to prevent races.
 *  - claim_batch() uses an UPDATE-then-SELECT pattern so that two overlapping
 *    cron workers each only receive the rows they themselves marked.
 *  - mark_failed() implements exponential back-off and dead-lettering once
 *    attempt_count reaches the configured maximum.
 */
class AIPS_Generation_Queue_Repository {

	/**
	 * Queue table name (with WP prefix).
	 *
	 * @var string
	 */
	private $table;

	/**
	 * WordPress database object.
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Allowed job status values.
	 *
	 * Lifecycle: pending → processing → done (success) or dead (exhausted retries).
	 * Between retries a processing job is reset to pending with exponential back-off.
	 *
	 * @var string[]
	 */
	const STATUSES = array( 'pending', 'processing', 'done', 'dead' );

	/**
	 * Statuses that represent active (in-flight) work.
	 *
	 * Used to check idempotency on enqueue.
	 *
	 * @var string[]
	 */
	const ACTIVE_STATUSES = array( 'pending', 'processing' );

	/**
	 * Known job type discriminators.
	 *
	 * Only these values may be enqueued.  enqueue() returns false for unknown
	 * types rather than silently sanitizing them, so callers are alerted to
	 * mismatches between enqueue-site and worker-dispatcher expectations.
	 *
	 * @var string[]
	 */
	const JOB_TYPES = array( 'template_schedule' );

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'aips_generation_queue';
	}

	// =========================================================================
	// Write operations
	// =========================================================================

	/**
	 * Enqueue a job unless an active (pending or processing) job already exists
	 * for the same idempotency key.
	 *
	 * A transaction with SELECT … FOR UPDATE prevents two concurrent cron
	 * invocations from inserting duplicate jobs for the same schedule occurrence.
	 *
	 * @param string      $idempotency_key Unique key for this job occurrence.
	 *                                     Recommended format: "{job_type}:{schedule_id}:{next_run}".
	 * @param string      $job_type        Job type discriminator (e.g. 'template_schedule').
	 * @param array       $payload         Structured job parameters (will be JSON-encoded).
	 * @param string|null $available_at    MySQL datetime when the job becomes eligible for processing.
	 *                                     Defaults to the current WordPress time when null.
	 * @return int|false  New row ID on success, false if a duplicate active job exists, an unknown
	 *                    job_type is supplied, or a DB error occurs.
	 */
	public function enqueue( $idempotency_key, $job_type, $payload, $available_at = null ) {
		// Validate job_type against the known whitelist before touching the DB.
		if ( ! in_array( $job_type, self::JOB_TYPES, true ) ) {
			return false;
		}

		if ( $available_at === null ) {
			$available_at = current_time( 'mysql' );
		}

		$this->wpdb->query( 'START TRANSACTION' );

		// Lock any existing active rows for this key to close the check-then-insert race.
		$active_id = $this->wpdb->get_var(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id FROM {$this->table}
				  WHERE idempotency_key = %s
				    AND status IN ('pending','processing')
				  LIMIT 1
				  FOR UPDATE",
				$idempotency_key
			)
		);

		if ( $active_id ) {
			$this->wpdb->query( 'ROLLBACK' );
			return false;
		}

		$inserted = $this->wpdb->insert(
			$this->table,
			array(
				'idempotency_key' => $idempotency_key,
				'job_type'        => $job_type,
				'payload'         => wp_json_encode( $payload ),
				'status'          => 'pending',
				'attempt_count'   => 0,
				'available_at'    => $available_at,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( ! $inserted ) {
			$this->wpdb->query( 'ROLLBACK' );
			return false;
		}

		$new_id = $this->wpdb->insert_id;
		$this->wpdb->query( 'COMMIT' );

		return $new_id;
	}

	/**
	 * Atomically claim up to $batch_size pending, due jobs for this worker.
	 *
	 * Uses an UPDATE-then-SELECT pattern: first, rows are stamped with the
	 * caller-supplied lock token; then only those rows are fetched back.  Two
	 * concurrent workers therefore never share the same rows.
	 *
	 * @param int    $batch_size Maximum number of jobs to claim.
	 * @param string $lock_token A unique string (e.g. UUID) that identifies this
	 *                           worker invocation and is used to retrieve claimed rows.
	 * @return object[] Array of claimed queue row objects ordered by available_at ASC.
	 */
	public function claim_batch( $batch_size, $lock_token ) {
		$now        = current_time( 'mysql' );
		$batch_size = max( 1, absint( $batch_size ) );

		$this->wpdb->query(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$this->table}
				    SET status     = 'processing',
				        lock_token = %s,
				        locked_at  = %s
				  WHERE status       = 'pending'
				    AND available_at <= %s
				  ORDER BY available_at ASC, id ASC
				  LIMIT %d",
				$lock_token,
				$now,
				$now,
				$batch_size
			)
		);

		if ( $this->wpdb->rows_affected === 0 ) {
			return array();
		}

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table}
				  WHERE lock_token = %s
				    AND status     = 'processing'
				  ORDER BY available_at ASC, id ASC",
				$lock_token
			)
		);
	}

	/**
	 * Mark a job as successfully completed.
	 *
	 * @param int $id Queue row ID.
	 * @return bool True on success.
	 */
	public function mark_done( $id ) {
		return (bool) $this->wpdb->update(
			$this->table,
			array(
				'status'       => 'done',
				'completed_at' => current_time( 'mysql' ),
				'lock_token'   => null,
				'locked_at'    => null,
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Record a failed attempt with exponential back-off.
	 *
	 * When attempt_count reaches $max_attempts the job is transitioned to 'dead'
	 * (dead-letter) so it no longer competes for worker capacity.  Otherwise the
	 * job is reset to 'pending' with an available_at offset that grows with each
	 * retry (5 min, 10 min, 20 min, …).
	 *
	 * The error message is persisted inside the payload JSON under 'last_error'
	 * for operator inspection.
	 *
	 * @param int    $id           Queue row ID.
	 * @param string $error        Human-readable error description.
	 * @param int    $max_attempts Maximum allowed attempts before dead-lettering. Default 3.
	 * @return bool True on success.
	 */
	public function mark_failed( $id, $error = '', $max_attempts = 3 ) {
		$id = absint( $id );

		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT attempt_count, payload FROM {$this->table} WHERE id = %d",
				$id
			)
		);

		if ( ! $row ) {
			return false;
		}

		$attempt_count = (int) $row->attempt_count + 1;
		$max_attempts  = max( 1, absint( $max_attempts ) );
		$is_dead       = $attempt_count >= $max_attempts;
		$new_status    = $is_dead ? 'dead' : 'pending';

		// Exponential back-off: 5, 10, 20 … minutes per retry tier.
		// Cap the exponent at 5 to avoid unreasonably long delays
		// (max back-off ≈ 160 minutes).
		$exponent    = min( $attempt_count - 1, 5 );
		$backoff_secs = $is_dead
			? 0
			: ( 5 * MINUTE_IN_SECONDS * ( 2 ** $exponent ) );

		$now         = current_time( 'timestamp' );
		$available_at = $is_dead
			? date( 'Y-m-d H:i:s', $now )
			: date( 'Y-m-d H:i:s', $now + $backoff_secs );

		// Persist the error inside the payload for operator visibility.
		$payload_data              = json_decode( (string) $row->payload, true );
		$payload_data              = is_array( $payload_data ) ? $payload_data : array();
		$payload_data['last_error'] = $error;
		$payload_json              = wp_json_encode( $payload_data );

		return (bool) $this->wpdb->update(
			$this->table,
			array(
				'status'        => $new_status,
				'attempt_count' => $attempt_count,
				'lock_token'    => null,
				'locked_at'     => null,
				'available_at'  => $available_at,
				'payload'       => $payload_json,
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Release stale processing locks so the affected jobs can be retried.
	 *
	 * A lock is considered stale when locked_at is older than $timeout_seconds.
	 * Released rows are returned to 'pending' status so the next worker invocation
	 * can claim them.
	 *
	 * @param int $timeout_seconds Age threshold in seconds. Default 300.
	 * @return int Number of rows released.
	 */
	public function release_stale_locks( $timeout_seconds = 300 ) {
		$cutoff = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - max( 0, absint( $timeout_seconds ) ) );

		$this->wpdb->query(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$this->table}
				    SET status     = 'pending',
				        lock_token = NULL,
				        locked_at  = NULL
				  WHERE status    = 'processing'
				    AND locked_at <= %s",
				$cutoff
			)
		);

		return (int) $this->wpdb->rows_affected;
	}

	/**
	 * Delete completed (done / dead) rows older than a given threshold to prevent
	 * unbounded table growth.
	 *
	 * @param int $older_than_days Age in days. Default 7.
	 * @return int Number of rows deleted.
	 */
	public function prune_completed( $older_than_days = 7 ) {
		$cutoff = date(
			'Y-m-d H:i:s',
			current_time( 'timestamp' ) - ( max( 1, absint( $older_than_days ) ) * DAY_IN_SECONDS )
		);

		$this->wpdb->query(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM {$this->table}
				  WHERE status IN ('done','dead')
				    AND created_at < %s",
				$cutoff
			)
		);

		return (int) $this->wpdb->rows_affected;
	}

	// =========================================================================
	// Read operations
	// =========================================================================

	/**
	 * Return the most recent active (pending or processing) queue job for a
	 * given idempotency key, or null when none exists.
	 *
	 * @param string $idempotency_key
	 * @return object|null
	 */
	public function get_active_by_idempotency_key( $idempotency_key ) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table}
				  WHERE idempotency_key = %s
				    AND status IN ('pending','processing')
				  ORDER BY id DESC
				  LIMIT 1",
				$idempotency_key
			)
		);
	}

	/**
	 * Count queue rows by status.
	 *
	 * @param string $status One of the STATUSES constants.
	 * @return int
	 */
	public function count_by_status( $status ) {
		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$this->table} WHERE status = %s",
				$status
			)
		);
	}

	/**
	 * Retrieve a single queue row by its primary key.
	 *
	 * @param int $id Row ID.
	 * @return object|null
	 */
	public function get_by_id( $id ) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table} WHERE id = %d",
				absint( $id )
			)
		);
	}
}
