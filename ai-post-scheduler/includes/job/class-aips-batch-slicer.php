<?php
/**
 * Batch Slicer Service
 *
 * Calculates batch/slice configuration for dividing work into manageable chunks.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Batch_Slicer
 *
 * Responsible for determining if batching is needed and calculating optimal
 * slice configuration based on item count and context-specific filters.
 */
class AIPS_Batch_Slicer {

	/**
	 * Default minimum item count that triggers batching.
	 *
	 * @var int
	 */
	const DEFAULT_THRESHOLD = 5;

	/**
	 * Default maximum number of slices.
	 *
	 * @var int
	 */
	const DEFAULT_MAX_SLICES = 10;

	/**
	 * Default time window (seconds) across which slices are spread.
	 *
	 * @var int
	 */
	const DEFAULT_WINDOW_SECONDS = 600; // 10 minutes

	/**
	 * Determine whether batching is needed based on item count and context.
	 *
	 * @param int    $item_count Total items to process.
	 * @param string $context    Context identifier for filter (e.g., 'schedule', 'author').
	 * @return bool True if batching should be used.
	 */
	public function needs_batching(int $item_count, string $context = 'default'): bool {
		$threshold = $this->get_threshold($context);
		return $item_count >= $threshold;
	}

	/**
	 * Get the batching threshold for a specific context.
	 *
	 * @param string $context Context identifier.
	 * @return int Threshold (minimum 2).
	 */
	public function get_threshold(string $context = 'default'): int {
		$filter_name = 'aips_batch_threshold';
		if ($context !== 'default') {
			$filter_name .= '_' . $context;
		}

		$threshold = (int) apply_filters($filter_name, self::DEFAULT_THRESHOLD);
		return max(2, $threshold);
	}

	/**
	 * Calculate slice configuration for the given item count.
	 *
	 * @param int   $item_count Total items to slice.
	 * @param array $options    {
	 *     Optional. Slicing options.
	 *
	 *     @type string $context        Context identifier for filters (default: 'default').
	 *     @type int    $max_slices     Maximum number of slices (default: filter value).
	 *     @type int    $window_seconds Window in seconds (default: filter value).
	 *     @type int    $items_per_slice Preferred items per slice (overrides calculation).
	 * }
	 * @return AIPS_Slice_Configuration
	 */
	public function calculate_slices(int $item_count, array $options = array()): AIPS_Slice_Configuration {
		$context = isset($options['context']) ? (string) $options['context'] : 'default';

		// Get configuration from options or filters
		$max_slices = isset($options['max_slices'])
			? max(1, (int) $options['max_slices'])
			: $this->get_max_slices($context);

		$window_seconds = isset($options['window_seconds'])
			? max(0, (int) $options['window_seconds'])
			: $this->get_window_seconds($context);

		// Calculate number of slices and items per slice
		if (isset($options['items_per_slice'])) {
			// Explicit items_per_slice provided
			$items_per_slice = max(1, (int) $options['items_per_slice']);
			$num_slices = (int) ceil($item_count / $items_per_slice);
			$num_slices = max(1, min($max_slices, $num_slices));
		} else {
			// Auto-calculate: aim for ~2 items per slice, but don't exceed max_slices
			$num_slices = min($max_slices, (int) ceil($item_count / 2));
			$num_slices = max(1, $num_slices);
			$items_per_slice = (int) ceil($item_count / $num_slices);
		}

		// Calculate interval between slice start times
		// If only 1 slice, no spread is needed
		$interval_seconds = ($num_slices > 1)
			? (float) ($window_seconds / ($num_slices - 1))
			: 0.0;

		return new AIPS_Slice_Configuration(
			$num_slices,
			$items_per_slice,
			$window_seconds,
			$interval_seconds,
			$item_count
		);
	}

	/**
	 * Get maximum number of slices for a context.
	 *
	 * @param string $context Context identifier.
	 * @return int Maximum slices (minimum 1).
	 */
	private function get_max_slices(string $context): int {
		$filter_name = 'aips_batch_max_slices';
		if ($context !== 'default') {
			$filter_name .= '_' . $context;
		}

		$max = (int) apply_filters($filter_name, self::DEFAULT_MAX_SLICES);
		return max(1, $max);
	}

	/**
	 * Get window seconds for a context.
	 *
	 * @param string $context Context identifier.
	 * @return int Window in seconds (minimum 0).
	 */
	private function get_window_seconds(string $context): int {
		$filter_name = 'aips_batch_window_seconds';
		if ($context !== 'default') {
			$filter_name .= '_' . $context;
		}

		$window = (int) apply_filters($filter_name, self::DEFAULT_WINDOW_SECONDS);
		return max(0, $window);
	}
}
