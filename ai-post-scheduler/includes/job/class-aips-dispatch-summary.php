<?php
/**
 * Dispatch Summary Value Object
 *
 * Represents the result of a batch dispatch operation.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Dispatch_Summary
 *
 * Immutable value object representing dispatch operation results.
 */
class AIPS_Dispatch_Summary {

	/**
	 * @var int Number of jobs/slices that were successfully scheduled
	 */
	private $scheduled_count;

	/**
	 * @var int Number of jobs/slices that failed to schedule
	 */
	private $failed_count;

	/**
	 * @var int Total number of jobs/slices attempted
	 */
	private $total_count;

	/**
	 * @var AIPS_Slice_Configuration|null Slice configuration if applicable
	 */
	private $slice_config;

	/**
	 * @var array Additional metadata
	 */
	private $metadata;

	/**
	 * Constructor.
	 *
	 * @param int                           $scheduled_count Successfully scheduled.
	 * @param int                           $failed_count    Failed to schedule.
	 * @param int                           $total_count     Total attempted.
	 * @param AIPS_Slice_Configuration|null $slice_config    Optional slice configuration.
	 * @param array                         $metadata        Optional metadata.
	 */
	public function __construct(
		int $scheduled_count,
		int $failed_count,
		int $total_count,
		?AIPS_Slice_Configuration $slice_config = null,
		array $metadata = array()
	) {
		$this->scheduled_count = $scheduled_count;
		$this->failed_count = $failed_count;
		$this->total_count = $total_count;
		$this->slice_config = $slice_config;
		$this->metadata = $metadata;
	}

	/**
	 * Get scheduled count.
	 *
	 * @return int
	 */
	public function get_scheduled_count(): int {
		return $this->scheduled_count;
	}

	/**
	 * Get failed count.
	 *
	 * @return int
	 */
	public function get_failed_count(): int {
		return $this->failed_count;
	}

	/**
	 * Get total count.
	 *
	 * @return int
	 */
	public function get_total_count(): int {
		return $this->total_count;
	}

	/**
	 * Get slice configuration if available.
	 *
	 * @return AIPS_Slice_Configuration|null
	 */
	public function get_slice_config(): ?AIPS_Slice_Configuration {
		return $this->slice_config;
	}

	/**
	 * Get metadata.
	 *
	 * @return array
	 */
	public function get_metadata(): array {
		return $this->metadata;
	}

	/**
	 * Check if dispatch was fully successful.
	 *
	 * @return bool
	 */
	public function is_success(): bool {
		return $this->failed_count === 0 && $this->scheduled_count > 0;
	}

	/**
	 * Check if dispatch was partial success.
	 *
	 * @return bool
	 */
	public function is_partial(): bool {
		return $this->scheduled_count > 0 && $this->failed_count > 0;
	}

	/**
	 * Check if dispatch completely failed.
	 *
	 * @return bool
	 */
	public function is_failure(): bool {
		return $this->scheduled_count === 0 && $this->failed_count > 0;
	}

	/**
	 * Convert to array format (for backward compatibility).
	 *
	 * @return array
	 */
	public function to_array(): array {
		$result = array(
			'scheduled_batches' => $this->scheduled_count,
			'failed_batches'    => $this->failed_count,
			'total_batches'     => $this->total_count,
		);

		if ($this->slice_config) {
			$result = array_merge($result, $this->slice_config->to_array());
		}

		if (!empty($this->metadata)) {
			$result = array_merge($result, $this->metadata);
		}

		return $result;
	}
}
