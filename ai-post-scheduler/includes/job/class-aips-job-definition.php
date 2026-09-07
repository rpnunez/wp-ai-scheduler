<?php
/**
 * Job Definition Value Object
 *
 * Represents a single WordPress cron job to be scheduled.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Job_Definition
 *
 * Immutable value object representing a cron job configuration.
 */
class AIPS_Job_Definition {

	/**
	 * @var string Job type identifier (e.g., 'schedule_batch', 'author_slice')
	 */
	private $job_type;

	/**
	 * @var string WordPress cron hook name
	 */
	private $hook;

	/**
	 * @var array Arguments to pass to the cron hook
	 */
	private $args;

	/**
	 * @var int Unix timestamp when the job should fire
	 */
	private $fire_at;

	/**
	 * @var array Metadata for logging/tracking (not passed to hook)
	 */
	private $metadata;

	/**
	 * @var string Correlation ID for distributed tracing
	 */
	private $correlation_id;

	/**
	 * Constructor.
	 *
	 * @param string $job_type       Job type identifier.
	 * @param string $hook           WordPress cron hook.
	 * @param array  $args           Hook arguments.
	 * @param int    $fire_at        Unix timestamp.
	 * @param array  $metadata       Optional metadata for logging.
	 * @param string $correlation_id Optional correlation ID.
	 */
	public function __construct(
		string $job_type,
		string $hook,
		array $args,
		int $fire_at,
		array $metadata = array(),
		string $correlation_id = ''
	) {
		$this->job_type = $job_type;
		$this->hook = $hook;
		$this->args = $args;
		$this->fire_at = $fire_at;
		$this->metadata = $metadata;
		$this->correlation_id = $correlation_id;
	}

	/**
	 * Get job type.
	 *
	 * @return string
	 */
	public function get_job_type(): string {
		return $this->job_type;
	}

	/**
	 * Get hook name.
	 *
	 * @return string
	 */
	public function get_hook(): string {
		return $this->hook;
	}

	/**
	 * Get hook arguments.
	 *
	 * @return array
	 */
	public function get_args(): array {
		return $this->args;
	}

	/**
	 * Get fire timestamp.
	 *
	 * @return int
	 */
	public function get_fire_at(): int {
		return $this->fire_at;
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
	 * Get correlation ID.
	 *
	 * @return string
	 */
	public function get_correlation_id(): string {
		return $this->correlation_id;
	}

	/**
	 * Create a new job definition with modified fire time.
	 *
	 * @param int $fire_at New fire timestamp.
	 * @return self New instance with updated fire time.
	 */
	public function with_fire_at(int $fire_at): self {
		return new self(
			$this->job_type,
			$this->hook,
			$this->args,
			$fire_at,
			$this->metadata,
			$this->correlation_id
		);
	}

	/**
	 * Create a unique key for deduplication checks.
	 *
	 * @return string Hash representing hook + args combination.
	 */
	public function get_unique_key(): string {
		return md5($this->hook . wp_json_encode($this->args));
	}
}
