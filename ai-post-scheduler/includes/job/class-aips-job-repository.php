<?php
/**
 * AIPS Job Repository — database repository for wp_aips_jobs.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Job_Repository {

	/** @var wpdb */
	private $wpdb;

	/** @var string */
	private $table_name;

	public function __construct( $wpdb = null ) {
		global $wpdb;
		$this->wpdb       = $wpdb ?: $GLOBALS['wpdb'];
		$this->table_name = $this->wpdb->prefix . 'aips_jobs';
	}

	public function get_table_name(): string {
		return $this->table_name;
	}

	/**
	 * Find a job record by UUID.
	 *
	 * @param string $job_id
	 * @return AIPS_Job_Record|null
	 */
	public function find( string $job_id ): ?AIPS_Job_Record {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE job_id = %s LIMIT 1",
				$job_id
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		return new AIPS_Job_Record( $row );
	}

	/**
	 * Insert a new job record.
	 *
	 * @param array $data
	 * @return bool
	 */
	public function insert( array $data ): bool {
		$now = AIPS_DateTime::now()->timestamp();

		$record = array(
			'job_id'        => isset( $data['job_id'] ) ? (string) $data['job_id'] : wp_generate_uuid4(),
			'job_type'      => isset( $data['job_type'] ) ? (string) $data['job_type'] : '',
			'group_name'    => isset( $data['group_name'] ) ? (string) $data['group_name'] : 'aips-default',
			'status'        => isset( $data['status'] ) ? (string) $data['status'] : AIPS_Job_Record::STATUS_PENDING,
			'total'         => isset( $data['total'] ) ? (int) $data['total'] : 0,
			'processed'     => isset( $data['processed'] ) ? (int) $data['processed'] : 0,
			'failed'        => isset( $data['failed'] ) ? (int) $data['failed'] : 0,
			'cursor_id'     => isset( $data['cursor_id'] ) ? (int) $data['cursor_id'] : 0,
			'payload_json'  => isset( $data['payload_json'] ) ? (string) $data['payload_json'] : ( isset( $data['payload'] ) ? wp_json_encode( $data['payload'] ) : '{}' ),
			'result_json'   => isset( $data['result_json'] ) ? (string) $data['result_json'] : ( isset( $data['result'] ) ? wp_json_encode( $data['result'] ) : '{}' ),
			'error_message' => isset( $data['error_message'] ) ? (string) $data['error_message'] : null,
			'attempts'      => isset( $data['attempts'] ) ? (int) $data['attempts'] : 0,
			'max_retries'   => isset( $data['max_retries'] ) ? (int) $data['max_retries'] : 3,
			'next_run_at'   => isset( $data['next_run_at'] ) ? (int) $data['next_run_at'] : 0,
			'last_tick_at'  => isset( $data['last_tick_at'] ) ? (int) $data['last_tick_at'] : 0,
			'started_at'    => isset( $data['started_at'] ) ? (int) $data['started_at'] : 0,
			'finished_at'   => isset( $data['finished_at'] ) ? (int) $data['finished_at'] : 0,
			'created_at'    => isset( $data['created_at'] ) ? (int) $data['created_at'] : $now,
			'updated_at'    => isset( $data['updated_at'] ) ? (int) $data['updated_at'] : $now,
		);

		$result = $this->wpdb->insert( $this->table_name, $record );
		return false !== $result;
	}

	/**
	 * Update an existing job record.
	 *
	 * @param string $job_id
	 * @param array  $fields
	 * @return bool
	 */
	public function update( string $job_id, array $fields ): bool {
		if ( empty( $fields ) ) {
			return true;
		}

		if ( ! isset( $fields['updated_at'] ) ) {
			$fields['updated_at'] = AIPS_DateTime::now()->timestamp();
		}

		if ( isset( $fields['payload'] ) && ! isset( $fields['payload_json'] ) ) {
			$fields['payload_json'] = wp_json_encode( $fields['payload'] );
			unset( $fields['payload'] );
		}

		if ( isset( $fields['result'] ) && ! isset( $fields['result_json'] ) ) {
			$fields['result_json'] = wp_json_encode( $fields['result'] );
			unset( $fields['result'] );
		}

		$result = $this->wpdb->update(
			$this->table_name,
			$fields,
			array( 'job_id' => $job_id )
		);

		return false !== $result;
	}

	/**
	 * Atomically increment progress counters.
	 *
	 * @param string $job_id
	 * @param int    $processed_inc
	 * @param int    $failed_inc
	 * @param int    $cursor_id
	 * @return bool
	 */
	public function increment_progress( string $job_id, int $processed_inc, int $failed_inc = 0, int $cursor_id = 0 ): bool {
		$now = AIPS_DateTime::now()->timestamp();
		$sql = "UPDATE {$this->table_name} SET
			processed = processed + %d,
			failed = failed + %d,
			cursor_id = %d,
			last_tick_at = %d,
			updated_at = %d
			WHERE job_id = %s";

		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				$sql,
				$processed_inc,
				$failed_inc,
				$cursor_id,
				$now,
				$now,
				$job_id
			)
		);

		return false !== $result;
	}

	/**
	 * Delete a job record.
	 *
	 * @param string $job_id
	 * @return bool
	 */
	public function delete( string $job_id ): bool {
		$result = $this->wpdb->delete(
			$this->table_name,
			array( 'job_id' => $job_id )
		);

		return false !== $result;
	}

	/**
	 * Query job records with filtering and pagination.
	 *
	 * @param array $filters (job_type, status, group_name, search)
	 * @param int   $limit
	 * @param int   $offset
	 * @return AIPS_Job_Record[]
	 */
	public function query( array $filters = array(), int $limit = 50, int $offset = 0 ): array {
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $filters['job_type'] ) ) {
			$where[]  = 'job_type = %s';
			$params[] = (string) $filters['job_type'];
		}

		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = (string) $filters['status'];
		}

		if ( ! empty( $filters['group_name'] ) ) {
			$where[]  = 'group_name = %s';
			$params[] = (string) $filters['group_name'];
		}

		$where_clause = implode( ' AND ', $where );
		$query        = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$params[]     = $limit;
		$params[]     = $offset;

		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare( $query, $params ),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$records = array();
		foreach ( $rows as $row ) {
			$records[] = new AIPS_Job_Record( $row );
		}

		return $records;
	}

	/**
	 * Retrieve all active jobs (pending or running).
	 *
	 * @param string|null $job_type Optional job type filter.
	 * @return AIPS_Job_Record[]
	 */
	public function get_active_jobs( ?string $job_type = null ): array {
		$sql    = "SELECT * FROM {$this->table_name} WHERE status IN ('pending', 'running')";
		$params = array();

		if ( null !== $job_type && '' !== $job_type ) {
			$sql     .= ' AND job_type = %s';
			$params[] = $job_type;
		}

		$sql .= ' ORDER BY created_at ASC';

		if ( ! empty( $params ) ) {
			$sql = $this->wpdb->prepare( $sql, $params );
		}

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$records = array();
		foreach ( $rows as $row ) {
			$records[] = new AIPS_Job_Record( $row );
		}

		return $records;
	}

	/**
	 * Count jobs matching filters.
	 *
	 * @param array $filters
	 * @return int
	 */
	public function count( array $filters = array() ): int {
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $filters['job_type'] ) ) {
			$where[]  = 'job_type = %s';
			$params[] = (string) $filters['job_type'];
		}

		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = (string) $filters['status'];
		}

		if ( ! empty( $filters['group_name'] ) ) {
			$where[]  = 'group_name = %s';
			$params[] = (string) $filters['group_name'];
		}

		$where_clause = implode( ' AND ', $where );
		$query        = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";

		if ( ! empty( $params ) ) {
			$query = $this->wpdb->prepare( $query, $params );
		}

		return (int) $this->wpdb->get_var( $query );
	}

	/**
	 * Delete finished jobs (completed, failed, cancelled) older than a cutoff timestamp.
	 *
	 * @param int $cutoff_timestamp
	 * @return int Number of rows deleted.
	 */
	public function prune_finished( int $cutoff_timestamp ): int {
		$sql = "DELETE FROM {$this->table_name}
			WHERE status IN ('completed', 'failed', 'cancelled')
			AND updated_at < %d";

		$deleted = $this->wpdb->query(
			$this->wpdb->prepare( $sql, $cutoff_timestamp )
		);

		return false !== $deleted ? (int) $deleted : 0;
	}
}
