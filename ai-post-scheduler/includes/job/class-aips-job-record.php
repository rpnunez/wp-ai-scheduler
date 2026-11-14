<?php
/**
 * AIPS Job Record — strongly typed, immutable representation of a background job row.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Job_Record {

	const STATUS_PENDING   = 'pending';
	const STATUS_RUNNING   = 'running';
	const STATUS_PAUSED    = 'paused';
	const STATUS_COMPLETED = 'completed';
	const STATUS_FAILED    = 'failed';
	const STATUS_CANCELLED = 'cancelled';

	/** @var string */
	private $job_id;

	/** @var string */
	private $job_type;

	/** @var string */
	private $group_name;

	/** @var string */
	private $status;

	/** @var int */
	private $total;

	/** @var int */
	private $processed;

	/** @var int */
	private $failed;

	/** @var int */
	private $cursor_id;

	/** @var array */
	private $payload;

	/** @var array */
	private $result;

	/** @var string|null */
	private $error_message;

	/** @var int */
	private $attempts;

	/** @var int */
	private $max_retries;

	/** @var int */
	private $next_run_at;

	/** @var int */
	private $last_tick_at;

	/** @var int */
	private $started_at;

	/** @var int */
	private $finished_at;

	/** @var int */
	private $created_at;

	/** @var int */
	private $updated_at;

	/**
	 * @param array $data Row data from database or factory array.
	 */
	public function __construct( array $data = array() ) {
		$this->job_id        = isset( $data['job_id'] ) ? (string) $data['job_id'] : '';
		$this->job_type      = isset( $data['job_type'] ) ? (string) $data['job_type'] : '';
		$this->group_name    = isset( $data['group_name'] ) ? (string) $data['group_name'] : 'aips-default';
		$this->status        = isset( $data['status'] ) ? (string) $data['status'] : self::STATUS_PENDING;
		$this->total         = isset( $data['total'] ) ? (int) $data['total'] : 0;
		$this->processed     = isset( $data['processed'] ) ? (int) $data['processed'] : 0;
		$this->failed        = isset( $data['failed'] ) ? (int) $data['failed'] : 0;
		$this->cursor_id     = isset( $data['cursor_id'] ) ? (int) $data['cursor_id'] : 0;
		$this->attempts      = isset( $data['attempts'] ) ? (int) $data['attempts'] : 0;
		$this->max_retries   = isset( $data['max_retries'] ) ? (int) $data['max_retries'] : 3;
		$this->next_run_at   = isset( $data['next_run_at'] ) ? (int) $data['next_run_at'] : 0;
		$this->last_tick_at  = isset( $data['last_tick_at'] ) ? (int) $data['last_tick_at'] : 0;
		$this->started_at    = isset( $data['started_at'] ) ? (int) $data['started_at'] : 0;
		$this->finished_at   = isset( $data['finished_at'] ) ? (int) $data['finished_at'] : 0;
		$this->created_at    = isset( $data['created_at'] ) ? (int) $data['created_at'] : 0;
		$this->updated_at    = isset( $data['updated_at'] ) ? (int) $data['updated_at'] : 0;
		$this->error_message = isset( $data['error_message'] ) ? $data['error_message'] : null;

		$payload_raw = isset( $data['payload_json'] ) ? $data['payload_json'] : ( isset( $data['payload'] ) ? $data['payload'] : array() );
		if ( is_string( $payload_raw ) ) {
			$decoded       = json_decode( $payload_raw, true );
			$this->payload = is_array( $decoded ) ? $decoded : array();
		} else {
			$this->payload = is_array( $payload_raw ) ? $payload_raw : array();
		}

		$result_raw = isset( $data['result_json'] ) ? $data['result_json'] : ( isset( $data['result'] ) ? $data['result'] : array() );
		if ( is_string( $result_raw ) ) {
			$decoded      = json_decode( $result_raw, true );
			$this->result = is_array( $decoded ) ? $decoded : array();
		} else {
			$this->result = is_array( $result_raw ) ? $result_raw : array();
		}
	}

	public function get_job_id(): string {
		return $this->job_id;
	}

	public function get_job_type(): string {
		return $this->job_type;
	}

	public function get_group_name(): string {
		return $this->group_name;
	}

	public function get_status(): string {
		return $this->status;
	}

	public function get_total(): int {
		return $this->total;
	}

	public function get_processed(): int {
		return $this->processed;
	}

	public function get_failed(): int {
		return $this->failed;
	}

	public function get_cursor_id(): int {
		return $this->cursor_id;
	}

	public function get_payload(): array {
		return $this->payload;
	}

	public function get_result(): array {
		return $this->result;
	}

	public function get_error_message(): ?string {
		return $this->error_message;
	}

	public function get_attempts(): int {
		return $this->attempts;
	}

	public function get_max_retries(): int {
		return $this->max_retries;
	}

	public function get_next_run_at(): int {
		return $this->next_run_at;
	}

	public function get_last_tick_at(): int {
		return $this->last_tick_at;
	}

	public function get_started_at(): int {
		return $this->started_at;
	}

	public function get_finished_at(): int {
		return $this->finished_at;
	}

	public function get_created_at(): int {
		return $this->created_at;
	}

	public function get_updated_at(): int {
		return $this->updated_at;
	}

	public function is_active(): bool {
		return in_array( $this->status, array( self::STATUS_PENDING, self::STATUS_RUNNING ), true );
	}

	public function is_paused(): bool {
		return self::STATUS_PAUSED === $this->status;
	}

	public function is_completed(): bool {
		return self::STATUS_COMPLETED === $this->status;
	}

	public function is_failed(): bool {
		return self::STATUS_FAILED === $this->status;
	}

	public function is_cancelled(): bool {
		return self::STATUS_CANCELLED === $this->status;
	}

	public function get_progress_percentage(): float {
		if ( $this->total <= 0 ) {
			return $this->is_completed() ? 100.0 : 0.0;
		}

		return round( min( 100.0, ( ( $this->processed + $this->failed ) / $this->total ) * 100 ), 1 );
	}

	/**
	 * Convert record to associative array suitable for API responses or serialization.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'job_id'              => $this->job_id,
			'job_type'            => $this->job_type,
			'group_name'          => $this->group_name,
			'status'              => $this->status,
			'total'               => $this->total,
			'processed'           => $this->processed,
			'failed'              => $this->failed,
			'progress_percentage' => $this->get_progress_percentage(),
			'cursor_id'           => $this->cursor_id,
			'payload'             => $this->payload,
			'result'              => $this->result,
			'error_message'       => $this->error_message,
			'attempts'            => $this->attempts,
			'max_retries'         => $this->max_retries,
			'next_run_at'         => $this->next_run_at,
			'last_tick_at'        => $this->last_tick_at,
			'started_at'          => $this->started_at,
			'finished_at'         => $this->finished_at,
			'created_at'          => $this->created_at,
			'updated_at'          => $this->updated_at,
		);
	}
}
