<?php
/**
 * AIPS Job Slice Result — value object returned by job slice handlers.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Job_Slice_Result {

	/** @var int */
	private $processed_count;

	/** @var int */
	private $failed_count;

	/** @var int */
	private $next_cursor;

	/** @var bool */
	private $is_complete;

	/** @var array */
	private $result_data;

	/**
	 * @param int   $processed_count Number of items successfully processed in this slice.
	 * @param int   $failed_count    Number of items that failed in this slice.
	 * @param int   $next_cursor     Next cursor offset or ID.
	 * @param bool  $is_complete     Whether all work for the job is finished.
	 * @param array $result_data     Optional partial or cumulative result data.
	 */
	public function __construct(
		int $processed_count = 0,
		int $failed_count = 0,
		int $next_cursor = 0,
		bool $is_complete = true,
		array $result_data = array()
	) {
		$this->processed_count = $processed_count;
		$this->failed_count    = $failed_count;
		$this->next_cursor     = $next_cursor;
		$this->is_complete     = $is_complete;
		$this->result_data     = $result_data;
	}

	public function get_processed_count(): int {
		return $this->processed_count;
	}

	public function get_failed_count(): int {
		return $this->failed_count;
	}

	public function get_next_cursor(): int {
		return $this->next_cursor;
	}

	public function is_complete(): bool {
		return $this->is_complete;
	}

	public function get_result_data(): array {
		return $this->result_data;
	}

	public static function complete( int $processed = 0, int $failed = 0, array $result = array() ): self {
		return new self( $processed, $failed, 0, true, $result );
	}

	public static function continue( int $processed = 0, int $next_cursor = 0, int $failed = 0, array $result = array() ): self {
		return new self( $processed, $failed, $next_cursor, false, $result );
	}
}
