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

	/** @var bool */
	private $update_cursor = true;

	/** @var bool */
	private $is_failed = false;

	/** @var int */
	private $retry_delay = 0;

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

	/**
	 * Alias used by domain tests and older call sites.
	 *
	 * @return bool
	 */
	public function is_completed(): bool {
		return $this->is_complete;
	}

	public function get_result_data(): array {
		return $this->result_data;
	}

	/**
	 * Whether the runner should persist get_next_cursor().
	 *
	 * Terminal complete() and success() results leave the cursor alone so a
	 * finished job does not have its cursor wiped back to 0.
	 *
	 * @return bool
	 */
	public function should_update_cursor(): bool {
		return $this->update_cursor;
	}

	/**
	 * Terminal failure. The runner records the error and does not retry.
	 *
	 * @return bool
	 */
	public function is_failed(): bool {
		return $this->is_failed;
	}

	/**
	 * Seconds to wait before the next slice. 0 means the runner default.
	 *
	 * @return int
	 */
	public function get_retry_delay(): int {
		return $this->retry_delay;
	}

	/**
	 * @param int $seconds
	 * @return self
	 */
	public function with_retry_delay( int $seconds ): self {
		$this->retry_delay = max( 0, $seconds );
		return $this;
	}

	public static function complete( int $processed = 0, int $failed = 0, array $result = array() ): self {
		$result_obj                = new self( $processed, $failed, 0, true, $result );
		$result_obj->update_cursor = false;
		return $result_obj;
	}

	/**
	 * One-shot success used by schedule and other single-item handlers.
	 *
	 * @param int   $processed Items finished by this slice.
	 * @param array $result    Optional result payload.
	 * @return self
	 */
	public static function success( int $processed = 1, array $result = array() ): self {
		return self::complete( $processed, 0, $result );
	}

	/**
	 * Terminal failure. $errors are stored on the job and the slice is not retried.
	 *
	 * @param array $errors Error messages.
	 * @param int   $cursor Cursor to keep. Not written unless with a continue result.
	 * @return self
	 */
	public static function failure( array $errors, int $cursor = 0 ): self {
		$messages                  = array_values( array_map( 'strval', $errors ) );
		$result_obj                = new self( 0, 0, $cursor, true, array( 'errors' => $messages ) );
		$result_obj->is_failed     = true;
		$result_obj->update_cursor = false;
		return $result_obj;
	}

	public static function continue( int $processed = 0, int $next_cursor = 0, int $failed = 0, array $result = array() ): self {
		return new self( $processed, $failed, $next_cursor, false, $result );
	}
}
