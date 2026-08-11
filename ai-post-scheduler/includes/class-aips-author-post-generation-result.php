<?php
/**
 * Author Post Generation Result
 *
 * Structured result object returned by AIPS_Author_Post_Generator when
 * generating one or more posts for an author. Captures every success, failure
 * and skip so multi-post runs can be reported accurately instead of collapsing
 * to the final error or a bare list of IDs.
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Author_Post_Generation_Result
 */
class AIPS_Author_Post_Generation_Result implements AIPS_Generation_Result_Interface {

	const STATUS_SUCCESS         = 'success';
	const STATUS_PARTIAL         = 'partial';
	const STATUS_FAILED          = 'failed';
	const STATUS_NO_WORK         = 'no_work';
	const STATUS_ALREADY_RUNNING = 'already_running';

	/**
	 * @var int Number of posts requested for this run.
	 */
	private $requested_count = 0;

	/**
	 * @var int Author ID this run belongs to.
	 */
	private $author_id = 0;

	/**
	 * @var int[] Topic IDs selected/attempted during this run.
	 */
	private $attempted_topic_ids = array();

	/**
	 * @var int[] WordPress post IDs successfully generated.
	 */
	private $post_ids = array();

	/**
	 * @var array<int, array{topic_id:int, topic_title:string, error_code:string, error_message:string}>
	 */
	private $failures = array();

	/**
	 * @var array<int, array{topic_id:int, topic_title:string, reason:string}>
	 */
	private $skipped = array();

	/**
	 * @var string Overall run status. Defaults to no_work until items are recorded.
	 */
	private $status = self::STATUS_NO_WORK;

	/**
	 * @var string Correlation ID for tracing.
	 */
	private $correlation_id = '';

	/**
	 * @var int Unix timestamp when the run started.
	 */
	private $started_at = 0;

	/**
	 * @var int Unix timestamp when the run completed.
	 */
	private $completed_at = 0;

	/**
	 * Constructor.
	 *
	 * @param int    $author_id       Author ID.
	 * @param int    $requested_count Number of posts requested.
	 * @param string $correlation_id  Correlation ID for tracing.
	 */
	public function __construct(int $author_id = 0, int $requested_count = 0, string $correlation_id = '') {
		$this->author_id       = $author_id;
		$this->requested_count = max(0, $requested_count);
		$this->correlation_id  = $correlation_id;
		$this->started_at      = AIPS_DateTime::now()->timestamp();
	}

	/**
	 * Record the full set of topic IDs selected for this run.
	 *
	 * @param int[] $topic_ids Topic IDs.
	 * @return void
	 */
	public function set_attempted_topic_ids(array $topic_ids): void {
		$this->attempted_topic_ids = array_values(array_map('intval', $topic_ids));
	}

	/**
	 * Record a successfully generated post.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return void
	 */
	public function add_success(int $post_id): void {
		if ($post_id > 0) {
			$this->post_ids[] = $post_id;
		}
	}

	/**
	 * Record a per-topic failure.
	 *
	 * @param int    $topic_id      Topic ID.
	 * @param string $topic_title   Topic title.
	 * @param string $error_code    Machine error code.
	 * @param string $error_message Human-readable error message.
	 * @return void
	 */
	public function add_failure(int $topic_id, string $topic_title, string $error_code, string $error_message): void {
		$this->failures[] = array(
			'topic_id'      => $topic_id,
			'topic_title'   => $topic_title,
			'error_code'    => $error_code,
			'error_message' => $error_message,
		);
	}

	/**
	 * Record a skipped topic and the reason it was skipped.
	 *
	 * @param int    $topic_id    Topic ID.
	 * @param string $topic_title Topic title.
	 * @param string $reason      Skip reason.
	 * @return void
	 */
	public function add_skipped(int $topic_id, string $topic_title, string $reason): void {
		$this->skipped[] = array(
			'topic_id'    => $topic_id,
			'topic_title' => $topic_title,
			'reason'      => $reason,
		);
	}

	/**
	 * Mark the run as blocked because another run already holds the claim.
	 *
	 * @return void
	 */
	public function mark_already_running(): void {
		$this->status = self::STATUS_ALREADY_RUNNING;
		$this->finish();
	}

	/**
	 * Mark the run as having no work to do (no eligible topics).
	 *
	 * @return void
	 */
	public function mark_no_work(): void {
		$this->status = self::STATUS_NO_WORK;
		$this->finish();
	}

	/**
	 * Finalise the run: stamp completion time and derive the overall status
	 * from the recorded successes and failures (unless an explicit terminal
	 * status such as already_running/no_work was already set).
	 *
	 * @return $this
	 */
	public function finalize(): self {
		if (self::STATUS_ALREADY_RUNNING === $this->status) {
			$this->finish();
			return $this;
		}

		$success_count = count($this->post_ids);
		$failure_count = count($this->failures);

		if ($success_count > 0 && ($failure_count > 0 || ($this->requested_count > 0 && $success_count < $this->requested_count))) {
			$this->status = self::STATUS_PARTIAL;
		} elseif ($success_count > 0) {
			$this->status = self::STATUS_SUCCESS;
		} elseif ($failure_count > 0) {
			$this->status = self::STATUS_FAILED;
		} else {
			$this->status = self::STATUS_NO_WORK;
		}

		$this->finish();
		return $this;
	}

	/**
	 * Stamp the completion timestamp once.
	 *
	 * @return void
	 */
	private function finish(): void {
		if (0 === $this->completed_at) {
			$this->completed_at = AIPS_DateTime::now()->timestamp();
		}
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
	 * Successful WordPress post IDs.
	 *
	 * @return int[]
	 */
	public function get_post_ids(): array {
		return $this->post_ids;
	}

	/**
	 * Recorded per-topic failures.
	 *
	 * @return array<int, array>
	 */
	public function get_failures(): array {
		return $this->failures;
	}

	/**
	 * Recorded skipped topics.
	 *
	 * @return array<int, array>
	 */
	public function get_skipped(): array {
		return $this->skipped;
	}

	/**
	 * Requested post count for this run.
	 *
	 * @return int
	 */
	public function get_requested_count(): int {
		return $this->requested_count;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_correlation_id(): string {
		return $this->correlation_id;
	}

	/**
	 * Convert this result to the legacy `int[]|WP_Error` shape expected by
	 * callers that have not yet adopted the result object.
	 *
	 * @return int[]|WP_Error Post IDs on any success, WP_Error otherwise.
	 */
	public function to_legacy_return() {
		if (!empty($this->post_ids)) {
			return $this->post_ids;
		}

		if (self::STATUS_ALREADY_RUNNING === $this->status) {
			return new WP_Error('already_running', __('A generation run for this author is already in progress.', 'ai-post-scheduler'));
		}

		if (!empty($this->failures)) {
			$last = end($this->failures);
			return new WP_Error(
				!empty($last['error_code']) ? $last['error_code'] : 'generation_failed',
				!empty($last['error_message']) ? $last['error_message'] : __('No posts were generated', 'ai-post-scheduler')
			);
		}

		if (self::STATUS_NO_WORK === $this->status) {
			return new WP_Error('no_topics', __('No approved topics available', 'ai-post-scheduler'));
		}

		return new WP_Error('generation_failed', __('No posts were generated', 'ai-post-scheduler'));
	}

	/**
	 * {@inheritDoc}
	 */
	public function to_array(): array {
		return array(
			'type'                => 'author_post_generation',
			'status'              => $this->status,
			'author_id'           => $this->author_id,
			'requested_count'     => $this->requested_count,
			'attempted_topic_ids' => $this->attempted_topic_ids,
			'post_ids'            => $this->post_ids,
			'success_count'       => count($this->post_ids),
			'failures'            => $this->failures,
			'failure_count'       => count($this->failures),
			'skipped'             => $this->skipped,
			'skipped_count'       => count($this->skipped),
			'correlation_id'      => $this->correlation_id,
			'started_at'          => $this->started_at,
			'completed_at'        => $this->completed_at,
		);
	}
}
