<?php
/**
 * Job Scheduler Service
 *
 * High-level orchestrator for job dispatching, combining slicing and dispatching
 * with support for staggered, batched, and simple scheduling patterns.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Job_Scheduler
 *
 * High-level API for common job scheduling patterns. Combines AIPS_Batch_Slicer
 * and AIPS_Job_Dispatcher to provide convenient methods for various dispatch strategies.
 */
class AIPS_Job_Scheduler {

	/**
	 * @var AIPS_Batch_Slicer Batch slicer service
	 */
	private $slicer;

	/**
	 * @var AIPS_Job_Dispatcher Job dispatcher service
	 */
	private $dispatcher;

	/**
	 * @var AIPS_Logger Logger instance
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Batch_Slicer|null   $slicer     Optional slicer service.
	 * @param AIPS_Job_Dispatcher|null $dispatcher Optional dispatcher service.
	 * @param AIPS_Logger|null         $logger     Optional logger.
	 */
	public function __construct(
		?AIPS_Batch_Slicer $slicer = null,
		?AIPS_Job_Dispatcher $dispatcher = null,
		?AIPS_Logger $logger = null
	) {
		$container = AIPS_Container::get_instance();

		$this->slicer = $slicer ?: new AIPS_Batch_Slicer();
		$this->dispatcher = $dispatcher ?: new AIPS_Job_Dispatcher();
		$this->logger = $logger ?: ($container->has(AIPS_Logger_Interface::class)
			? $container->make(AIPS_Logger_Interface::class)
			: new AIPS_Logger());
	}

	/**
	 * Schedule a simple job with retry logic.
	 *
	 * @param string $hook           WordPress cron hook name.
	 * @param int    $fire_at        Unix timestamp when job should fire.
	 * @param array  $args           Arguments to pass to hook.
	 * @param array  $options        {
	 *     Optional. Job options.
	 *
	 *     @type string $job_type       Job type identifier (default: 'simple').
	 *     @type array  $metadata       Metadata for logging.
	 *     @type string $correlation_id Correlation ID.
	 *     @type array  $retry_options  Retry configuration (see AIPS_Job_Dispatcher::dispatch()).
	 * }
	 * @return bool True if successfully scheduled.
	 */
	public function schedule_simple(
		string $hook,
		int $fire_at,
		array $args = array(),
		array $options = array()
	): bool {
		$job = new AIPS_Job_Definition(
			isset($options['job_type']) ? $options['job_type'] : 'simple',
			$hook,
			$args,
			$fire_at,
			isset($options['metadata']) ? $options['metadata'] : array(),
			isset($options['correlation_id']) ? $options['correlation_id'] : ''
		);

		$retry_options = isset($options['retry_options']) ? $options['retry_options'] : array();

		return $this->dispatcher->dispatch($job, $retry_options);
	}

	/**
	 * Schedule staggered jobs for multiple items.
	 *
	 * Each item gets its own job dispatched with a configurable stagger delay between them.
	 * Useful for author slices, topic processing, etc.
	 *
	 * @param string $hook             WordPress cron hook name.
	 * @param array  $items            Array of items to process.
	 * @param array  $options          {
	 *     Required and optional settings.
	 *
	 *     @type callable $args_builder    Callable that takes an item and returns args array for that job.
	 *     @type int      $stagger_seconds Seconds between each job start time (default: 10).
	 *     @type int      $base_timestamp  Base timestamp for first job (default: now).
	 *     @type string   $job_type        Job type identifier (default: 'staggered').
	 *     @type array    $metadata        Shared metadata for all jobs.
	 *     @type string   $correlation_id  Correlation ID for batch.
	 *     @type array    $retry_options   Retry configuration for each job.
	 *     @type callable $on_failed       Callback for failed items: function(array $failed_items, string $correlation_id).
	 * }
	 * @return AIPS_Dispatch_Summary
	 */
	public function schedule_staggered(
		string $hook,
		array $items,
		array $options = array()
	): AIPS_Dispatch_Summary {
		if (empty($items)) {
			return new AIPS_Dispatch_Summary(0, 0, 0);
		}

		// Parse options
		$args_builder = isset($options['args_builder']) && is_callable($options['args_builder'])
			? $options['args_builder']
			: function($item) { return array($item); };

		$stagger_seconds = isset($options['stagger_seconds'])
			? max(0, (int) $options['stagger_seconds'])
			: 10;

		$base_timestamp = isset($options['base_timestamp'])
			? (int) $options['base_timestamp']
			: AIPS_DateTime::now()->timestamp();

		$job_type = isset($options['job_type']) ? $options['job_type'] : 'staggered';
		$metadata = isset($options['metadata']) ? $options['metadata'] : array();
		$correlation_id = isset($options['correlation_id']) ? $options['correlation_id'] : (string) AIPS_Correlation_ID::get();
		$retry_options = isset($options['retry_options']) ? $options['retry_options'] : array();

		// Dispatch jobs with staggered timing
		$scheduled_count = 0;
		$failed_count = 0;
		$failed_items = array();

		foreach ($items as $index => $item) {
			$fire_at = $base_timestamp + ($index * $stagger_seconds);
			$args = $args_builder($item);

			$job = new AIPS_Job_Definition(
				$job_type,
				$hook,
				$args,
				$fire_at,
				array_merge($metadata, array('item_index' => $index)),
				$correlation_id
			);

			if ($this->dispatcher->dispatch($job, $retry_options)) {
				$scheduled_count++;
			} else {
				$failed_count++;
				$failed_items[] = $item;
			}
		}

		$this->logger->log(
			sprintf(
				'Staggered dispatch: scheduled=%d, failed=%d, stagger=%ds',
				$scheduled_count,
				$failed_count,
				$stagger_seconds
			),
			$failed_count > 0 ? 'warning' : 'info',
			array('hook' => $hook, 'correlation_id' => $correlation_id)
		);

		// Handle failed items callback
		if (!empty($failed_items) && isset($options['on_failed']) && is_callable($options['on_failed'])) {
			$options['on_failed']($failed_items, $correlation_id);
		}

		return new AIPS_Dispatch_Summary(
			$scheduled_count,
			$failed_count,
			count($items),
			null,
			array('correlation_id' => $correlation_id)
		);
	}

	/**
	 * Schedule batched jobs with time-spread distribution.
	 *
	 * Divides item_count into slices and spreads them across a time window.
	 * Useful for large post generation batches.
	 *
	 * @param string $hook        WordPress cron hook name.
	 * @param int    $item_count  Total items to process.
	 * @param array  $options     {
	 *     Required and optional settings.
	 *
	 *     @type array    $prefix_args     Arguments prepended to each job's args array.
	 *     @type int      $base_timestamp  Base timestamp for first batch (default: now).
	 *     @type string   $context         Context for slicer filters (default: 'default').
	 *     @type array    $slice_options   Options passed to slicer (max_slices, window_seconds, etc.).
	 *     @type string   $job_type        Job type identifier (default: 'batched').
	 *     @type array    $metadata        Shared metadata for all jobs.
	 *     @type string   $correlation_id  Correlation ID for batch.
	 *     @type array    $retry_options   Retry configuration for each job.
	 * }
	 * @return AIPS_Dispatch_Summary|WP_Error
	 */
		public function schedule_batched(
		string $hook,
		int $item_count,
		array $options = array()
	) {
		if ($item_count < 1) {
			return new WP_Error(
				'invalid_item_count',
				__('Batch scheduling requires at least one item.', 'ai-post-scheduler')
			);
		}

		$parsed_options = $this->parse_batched_options($options);
		$slice_config   = $this->slicer->calculate_slices($item_count, $parsed_options['slice_options']);

		$dispatch_counts = $this->dispatch_batch_slices($hook, $item_count, $parsed_options, $slice_config);

		$this->log_batch_summary($hook, $item_count, $dispatch_counts, $slice_config, $parsed_options['correlation_id']);

		return new AIPS_Dispatch_Summary(
			$dispatch_counts['scheduled'],
			$dispatch_counts['failed'],
			$slice_config->get_num_slices(),
			$slice_config,
			array('correlation_id' => $parsed_options['correlation_id'])
		);
	}

	/**
	 * Parse and set defaults for batch scheduling options.
	 *
	 * @param array $options Raw options array.
	 * @return array Parsed options with defaults applied.
	 */
	private function parse_batched_options(array $options): array {
		$context = isset($options['context']) ? $options['context'] : 'default';
		$slice_options = isset($options['slice_options']) && is_array($options['slice_options']) ? $options['slice_options'] : array();
		$slice_options['context'] = $context;

		return array(
			'prefix_args'    => isset($options['prefix_args']) && is_array($options['prefix_args']) ? $options['prefix_args'] : array(),
			'base_timestamp' => isset($options['base_timestamp']) ? (int) $options['base_timestamp'] : AIPS_DateTime::now()->timestamp(),
			'slice_options'  => $slice_options,
			'job_type'       => isset($options['job_type']) ? $options['job_type'] : 'batched',
			'metadata'       => isset($options['metadata']) ? $options['metadata'] : array(),
			'correlation_id' => isset($options['correlation_id']) ? $options['correlation_id'] : (string) AIPS_Correlation_ID::get(),
			'retry_options'  => isset($options['retry_options']) ? $options['retry_options'] : array(),
		);
	}

	/**
	 * Dispatch individual job slices for a batch.
	 *
	 * @param string                   $hook           The cron hook.
	 * @param int                      $item_count     Total item count.
	 * @param array                    $parsed_options The parsed options.
	 * @param AIPS_Slice_Configuration $slice_config   The slice configuration.
	 * @return array Associative array with 'scheduled' and 'failed' counts.
	 */
	private function dispatch_batch_slices(string $hook, int $item_count, array $parsed_options, AIPS_Slice_Configuration $slice_config): array {
		$scheduled_count = 0;
		$failed_count = 0;

		for ($slice = 0; $slice < $slice_config->get_num_slices(); $slice++) {
			$start_index = $slice * $slice_config->get_items_per_slice();
			$this_slice_size = min($slice_config->get_items_per_slice(), $item_count - $start_index);

			$delay = (int) round($slice * $slice_config->get_interval_seconds());
			$fire_at = $parsed_options['base_timestamp'] + $delay;

			$args = array_merge(
				$parsed_options['prefix_args'],
				array($start_index, $this_slice_size, $item_count, $parsed_options['correlation_id'])
			);

			$job = new AIPS_Job_Definition(
				$parsed_options['job_type'],
				$hook,
				$args,
				$fire_at,
				array_merge($parsed_options['metadata'], array(
					'slice_index' => $slice,
					'start_index' => $start_index,
					'slice_size'  => $this_slice_size,
				)),
				$parsed_options['correlation_id']
			);

			if ($this->dispatcher->dispatch($job, $parsed_options['retry_options'])) {
				$scheduled_count++;
			} else {
				$failed_count++;
			}
		}

		return array('scheduled' => $scheduled_count, 'failed' => $failed_count);
	}

	/**
	 * Log the summary of the batched dispatch operation.
	 *
	 * @param string                   $hook            The cron hook.
	 * @param int                      $item_count      Total item count.
	 * @param array                    $dispatch_counts Result from dispatch_batch_slices.
	 * @param AIPS_Slice_Configuration $slice_config    The slice configuration.
	 * @param string                   $correlation_id  The correlation ID.
	 * @return void
	 */
	private function log_batch_summary(string $hook, int $item_count, array $dispatch_counts, AIPS_Slice_Configuration $slice_config, string $correlation_id): void {
		$this->logger->log(
			sprintf(
				'Batched dispatch: scheduled=%d/%d batches, window=%ds, correlation=%s',
				$dispatch_counts['scheduled'],
				$slice_config->get_num_slices(),
				$slice_config->get_window_seconds(),
				$correlation_id
			),
			$dispatch_counts['failed'] > 0 ? 'warning' : 'info',
			array('hook' => $hook, 'item_count' => $item_count)
		);
	}

	/**
	 * Get the slicer instance.
	 *
	 * @return AIPS_Batch_Slicer
	 */
	public function get_slicer(): AIPS_Batch_Slicer {
		return $this->slicer;
	}

	/**
	 * Get the dispatcher instance.
	 *
	 * @return AIPS_Job_Dispatcher
	 */
	public function get_dispatcher(): AIPS_Job_Dispatcher {
		return $this->dispatcher;
	}
}
