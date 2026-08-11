<?php
/**
 * Author Topic Generation Result
 *
 * Structured result object returned by AIPS_Author_Topics_Generator. Captures
 * exactly which topics were accepted and persisted for a generation run,
 * identified by an unambiguous generation_run_id rather than reconstructed from
 * the "latest N rows" for the author.
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Author_Topic_Generation_Result
 */
class AIPS_Author_Topic_Generation_Result implements AIPS_Generation_Result_Interface {

	const STATUS_SUCCESS         = 'success';
	const STATUS_PARTIAL         = 'partial';
	const STATUS_FAILED          = 'failed';
	const STATUS_NO_WORK         = 'no_work';
	const STATUS_ALREADY_RUNNING = 'already_running';

	/**
	 * @var int Author ID this run belongs to.
	 */
	private $author_id = 0;

	/**
	 * @var int Number of topics requested for this run.
	 */
	private $requested_count = 0;

	/**
	 * @var int Number of valid, accepted topic candidates.
	 */
	private $accepted_count = 0;

	/**
	 * @var int Number of rejected/invalid candidates.
	 */
	private $rejected_count = 0;

	/**
	 * @var int Number of duplicate candidates flagged.
	 */
	private $duplicate_count = 0;

	/**
	 * @var array<int, array> Persisted topic records (associative arrays).
	 */
	private $persisted_topics = array();

	/**
	 * @var string Unique run identifier used to select the exact inserted rows.
	 */
	private $generation_run_id = '';

	/**
	 * @var string Correlation ID for tracing.
	 */
	private $correlation_id = '';

	/**
	 * @var string Overall run status.
	 */
	private $status = self::STATUS_NO_WORK;

	/**
	 * @var WP_Error|null Terminal error, if any.
	 */
	private $error = null;

	/**
	 * Constructor.
	 *
	 * @param int    $author_id         Author ID.
	 * @param int    $requested_count   Number of topics requested.
	 * @param string $generation_run_id Unique run identifier.
	 * @param string $correlation_id    Correlation ID for tracing.
	 */
	public function __construct(int $author_id = 0, int $requested_count = 0, string $generation_run_id = '', string $correlation_id = '') {
		$this->author_id         = $author_id;
		$this->requested_count   = max(0, $requested_count);
		$this->generation_run_id = $generation_run_id;
		$this->correlation_id    = $correlation_id;
	}

	/**
	 * Record candidate-parsing counts.
	 *
	 * @param int $accepted  Accepted candidate count.
	 * @param int $rejected  Rejected/invalid candidate count.
	 * @param int $duplicate Duplicate candidate count.
	 * @return void
	 */
	public function set_candidate_counts(int $accepted, int $rejected = 0, int $duplicate = 0): void {
		$this->accepted_count  = max(0, $accepted);
		$this->rejected_count  = max(0, $rejected);
		$this->duplicate_count = max(0, $duplicate);
	}

	/**
	 * Record the exact persisted topic records.
	 *
	 * @param array<int, array> $topics Persisted topic records.
	 * @return void
	 */
	public function set_persisted_topics(array $topics): void {
		$this->persisted_topics = array_values($topics);
	}

	/**
	 * Mark the run as blocked because another run already holds the claim.
	 *
	 * @return void
	 */
	public function mark_already_running(): void {
		$this->status = self::STATUS_ALREADY_RUNNING;
		$this->error  = new WP_Error('already_running', __('A topic generation run for this author is already in progress.', 'ai-post-scheduler'));
	}

	/**
	 * Mark the run as failed with a terminal error.
	 *
	 * @param WP_Error $error Terminal error.
	 * @return void
	 */
	public function mark_failed(WP_Error $error): void {
		$this->status = self::STATUS_FAILED;
		$this->error  = $error;
	}

	/**
	 * Finalise the run, deriving status from persisted counts.
	 *
	 * @return $this
	 */
	public function finalize(): self {
		if (self::STATUS_ALREADY_RUNNING === $this->status || self::STATUS_FAILED === $this->status) {
			return $this;
		}

		$persisted = count($this->persisted_topics);

		if ($persisted <= 0) {
			$this->status = self::STATUS_NO_WORK;
		} elseif ($this->requested_count > 0 && $persisted < $this->requested_count) {
			$this->status = self::STATUS_PARTIAL;
		} else {
			$this->status = self::STATUS_SUCCESS;
		}

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_status(): string {
		return $this->status;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_success(): bool {
		return self::STATUS_SUCCESS === $this->status || self::STATUS_PARTIAL === $this->status;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_partial(): bool {
		return self::STATUS_PARTIAL === $this->status;
	}

	/**
	 * Persisted topic records.
	 *
	 * @return array<int, array>
	 */
	public function get_persisted_topics(): array {
		return $this->persisted_topics;
	}

	/**
	 * Persisted topic IDs.
	 *
	 * @return int[]
	 */
	public function get_persisted_topic_ids(): array {
		$ids = array();
		foreach ($this->persisted_topics as $topic) {
			if (isset($topic['id'])) {
				$ids[] = (int) $topic['id'];
			}
		}
		return $ids;
	}

	/**
	 * Number of persisted topics.
	 *
	 * @return int
	 */
	public function get_persisted_count(): int {
		return count($this->persisted_topics);
	}

	/**
	 * Unique generation run identifier.
	 *
	 * @return string
	 */
	public function get_generation_run_id(): string {
		return $this->generation_run_id;
	}

	/**
	 * Terminal error, if any.
	 *
	 * @return WP_Error|null
	 */
	public function get_error() {
		return $this->error;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_correlation_id(): string {
		return $this->correlation_id;
	}

	/**
	 * Convert to the legacy `array|WP_Error` shape expected by existing callers.
	 *
	 * @return array<int, array>|WP_Error Persisted topic records on success, WP_Error otherwise.
	 */
	public function to_legacy_return() {
		if ($this->error instanceof WP_Error && empty($this->persisted_topics)) {
			return $this->error;
		}

		if (!empty($this->persisted_topics)) {
			return $this->persisted_topics;
		}

		return new WP_Error('no_topics_parsed', __('Failed to parse topics from AI response', 'ai-post-scheduler'));
	}

	/**
	 * {@inheritDoc}
	 */
	public function to_array(): array {
		return array(
			'type'              => 'author_topic_generation',
			'status'            => $this->status,
			'author_id'         => $this->author_id,
			'requested_count'   => $this->requested_count,
			'accepted_count'    => $this->accepted_count,
			'rejected_count'    => $this->rejected_count,
			'duplicate_count'   => $this->duplicate_count,
			'persisted_count'   => count($this->persisted_topics),
			'persisted_ids'     => $this->get_persisted_topic_ids(),
			'generation_run_id' => $this->generation_run_id,
			'correlation_id'    => $this->correlation_id,
			'error'             => $this->error instanceof WP_Error ? $this->error->get_error_message() : null,
		);
	}
}
