<?php
/**
 * Slice Configuration Value Object
 *
 * Represents the calculated configuration for splitting work into batches.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Slice_Configuration
 *
 * Immutable value object representing batch slicing configuration.
 */
class AIPS_Slice_Configuration {

	/**
	 * @var int Number of slices/batches
	 */
	private $num_slices;

	/**
	 * @var int Items per slice
	 */
	private $items_per_slice;

	/**
	 * @var int Window in seconds across which slices are spread
	 */
	private $window_seconds;

	/**
	 * @var float Interval in seconds between slice start times
	 */
	private $interval_seconds;

	/**
	 * @var int Total number of items being sliced
	 */
	private $total_items;

	/**
	 * Constructor.
	 *
	 * @param int   $num_slices       Number of slices.
	 * @param int   $items_per_slice  Items per slice.
	 * @param int   $window_seconds   Spread window in seconds.
	 * @param float $interval_seconds Interval between slices.
	 * @param int   $total_items      Total items.
	 */
	public function __construct(
		int $num_slices,
		int $items_per_slice,
		int $window_seconds,
		float $interval_seconds,
		int $total_items
	) {
		$this->num_slices = $num_slices;
		$this->items_per_slice = $items_per_slice;
		$this->window_seconds = $window_seconds;
		$this->interval_seconds = $interval_seconds;
		$this->total_items = $total_items;
	}

	/**
	 * Get number of slices.
	 *
	 * @return int
	 */
	public function get_num_slices(): int {
		return $this->num_slices;
	}

	/**
	 * Get items per slice.
	 *
	 * @return int
	 */
	public function get_items_per_slice(): int {
		return $this->items_per_slice;
	}

	/**
	 * Get window in seconds.
	 *
	 * @return int
	 */
	public function get_window_seconds(): int {
		return $this->window_seconds;
	}

	/**
	 * Get interval between slices.
	 *
	 * @return float
	 */
	public function get_interval_seconds(): float {
		return $this->interval_seconds;
	}

	/**
	 * Get total items.
	 *
	 * @return int
	 */
	public function get_total_items(): int {
		return $this->total_items;
	}

	/**
	 * Convert to array format (for backward compatibility).
	 *
	 * @return array{
	 *   num_batches: int,
	 *   posts_per_batch: int,
	 *   window_seconds: int,
	 *   interval_seconds: float
	 * }
	 */
	public function to_array(): array {
		return array(
			'num_batches'      => $this->num_slices,
			'posts_per_batch'  => $this->items_per_slice,
			'window_seconds'   => $this->window_seconds,
			'interval_seconds' => $this->interval_seconds,
		);
	}
}
