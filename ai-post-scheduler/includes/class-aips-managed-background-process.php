<?php
/**
 * Managed Background Process
 *
 * Base class for processes whose runs are driven by the shared tick loop:
 * one WP-Cron single event per slice, progress persisted in
 * `aips_background_processes`, and pause/resume/stop honored between slices.
 *
 * Subclasses supply the work (process_slice) and, for AI-backed processes,
 * the quota rules (check_limits, get_allowance, count_ai_calls).
 *
 * @package AI_Post_Scheduler
 * @since 3.7.11
 */

if (!defined('ABSPATH')) {
	exit;
}

abstract class AIPS_Managed_Background_Process extends AIPS_Background_Process_Base {

	/**
	 * Cron hook that runs one slice. Args: array($process_key).
	 */
	const TICK_HOOK = 'aips_bg_process_tick';

	/**
	 * Seconds a slice may hold the per-process lock before it is considered stale.
	 */
	const LOCK_TTL = 300;

	/**
	 * Seconds until the watchdog tick fires if a slice never finishes.
	 *
	 * WP-Cron removes an event before running it, and the next tick is only scheduled
	 * once a slice returns. A slice killed by a timeout or out-of-memory fatal never
	 * gets there, so a tick is scheduled before each slice and replaced when it ends.
	 */
	const WATCHDOG_DELAY = 330;

	/**
	 * @var AIPS_Background_Process_Repository
	 */
	protected $repository;

	/**
	 * @param AIPS_Background_Process_Repository|null $repository Run repository.
	 */
	public function __construct(?AIPS_Background_Process_Repository $repository = null) {
		$this->repository = $repository ?: new AIPS_Background_Process_Repository();
	}

	// -------------------------------------------------------------------------
	// Work supplied by subclasses
	// -------------------------------------------------------------------------

	/**
	 * Items left to process right now.
	 *
	 * @return int
	 */
	abstract protected function count_remaining(): int;

	/**
	 * Process up to $limit items starting after $cursor.
	 *
	 * @param int   $cursor  Cursor stored by the previous slice (0 at the start).
	 * @param int   $limit   Maximum items for this slice.
	 * @param array $options Options the run was started with.
	 * @return array{processed:int, failed:int, cursor:int, done:bool, message?:string, fatal?:WP_Error}
	 */
	abstract protected function process_slice(int $cursor, int $limit, array $options): array;

	/**
	 * Items per slice.
	 *
	 * @return int
	 */
	protected function get_batch_size(): int {
		return 10;
	}

	/**
	 * Seconds to wait between slices.
	 *
	 * @return int
	 */
	protected function get_delay(): int {
		return 5;
	}

	/**
	 * Whether a run may start now.
	 *
	 * @return true|WP_Error
	 */
	protected function can_run() {
		return true;
	}

	/**
	 * A rate limit or cooldown that must pass before the next slice may run.
	 *
	 * @return array{status:string, retry_at:int, message:string}|null Null when clear.
	 */
	protected function check_limits(): ?array {
		return null;
	}

	/**
	 * How many AI calls the next slice may make.
	 *
	 * @return int PHP_INT_MAX when unbounded.
	 */
	protected function get_allowance(): int {
		return PHP_INT_MAX;
	}

	/**
	 * Running total of AI calls made (used to measure a slice's cost).
	 *
	 * @return int
	 */
	protected function count_ai_calls(): int {
		return 0;
	}

	// -------------------------------------------------------------------------
	// Interface
	// -------------------------------------------------------------------------

	/**
	 * @inheritDoc
	 */
	public function get_snapshot(): array {
		$run = $this->repository->get_open($this->get_key());

		if ($run) {
			return $this->make_snapshot(array(
				'status'          => $run->status,
				'processed'       => $run->processed,
				'total'           => $run->total,
				'failed'          => $run->failed,
				'ai_calls_used'   => $run->ai_calls_used,
				'ai_calls_budget' => $run->ai_calls_budget,
				'message'         => (string) $run->message,
				'next_run_at'     => $run->next_run_at,
				'started_at'      => $run->started_at,
				'updated_at'      => $run->updated_at,
				'run_id'          => $run->id,
			));
		}

		$last = $this->repository->get_latest($this->get_key());

		return $this->make_snapshot(array(
			'status'           => self::STATUS_IDLE,
			'last_status'      => $last ? $last->status : '',
			'last_finished_at' => $last ? $last->finished_at : 0,
			'processed'        => $last ? $last->processed : 0,
			'total'            => $last ? $last->total : 0,
			'failed'           => $last ? $last->failed : 0,
			'ai_calls_used'    => $last ? $last->ai_calls_used : 0,
			'message'          => $last ? (string) $last->message : '',
			'updated_at'       => $last ? $last->updated_at : 0,
		));
	}

	/**
	 * @inheritDoc
	 */
	public function start(array $options = array()) {
		if ($this->repository->get_open($this->get_key())) {
			return new WP_Error(
				'aips_bg_already_open',
				__('This process is already running or paused. Resume or stop it first.', 'ai-post-scheduler')
			);
		}

		$can_run = $this->can_run();
		if (is_wp_error($can_run)) {
			return $can_run;
		}

		$remaining = $this->count_remaining();
		if ($remaining <= 0) {
			return new WP_Error(
				'aips_bg_nothing_to_do',
				__('There is nothing left to process.', 'ai-post-scheduler')
			);
		}

		$budget = isset($options['ai_budget']) ? max(0, (int) $options['ai_budget']) : 0;
		unset($options['ai_budget']);

		$run_id = $this->repository->create(
			$this->get_key(),
			$remaining,
			$this->sanitize_options($options),
			$budget,
			get_current_user_id()
		);

		if ($run_id <= 0) {
			return new WP_Error(
				'aips_bg_create_failed',
				__('Could not record the run. Please try again.', 'ai-post-scheduler')
			);
		}

		$this->schedule_tick(0);

		return $this->get_snapshot();
	}

	/**
	 * @inheritDoc
	 */
	public function pause(): bool {
		$run = $this->repository->get_open($this->get_key());
		if (!$run) {
			return false;
		}

		$changed = $this->repository->transition(
			$run->id,
			AIPS_Background_Process_Repository::STATUS_PAUSED,
			AIPS_Background_Process_Repository::active_statuses(),
			__('Paused by an administrator.', 'ai-post-scheduler')
		);

		if ($changed) {
			wp_clear_scheduled_hook(self::TICK_HOOK, array($this->get_key()));
		}

		return $changed;
	}

	/**
	 * @inheritDoc
	 */
	public function resume(): bool {
		$run = $this->repository->get_open($this->get_key());
		if (!$run) {
			return false;
		}

		// A run paused by its own AI budget would pause again at once: resuming it
		// is an explicit decision to carry on, so the budget no longer applies.
		if ($run->status === AIPS_Background_Process_Repository::STATUS_PAUSED && $run->ai_calls_budget > 0 && $run->ai_calls_used >= $run->ai_calls_budget) {
			$this->repository->clear_ai_budget($run->id);
		}

		$changed = $this->repository->transition(
			$run->id,
			AIPS_Background_Process_Repository::STATUS_RUNNING,
			array(AIPS_Background_Process_Repository::STATUS_PAUSED),
			'',
			time()
		);

		if ($changed) {
			$this->schedule_tick(0);
		}

		return $changed;
	}

	/**
	 * @inheritDoc
	 */
	public function cancel(): bool {
		$run = $this->repository->get_open($this->get_key());
		if (!$run) {
			return false;
		}

		$changed = $this->repository->transition(
			$run->id,
			AIPS_Background_Process_Repository::STATUS_CANCELLED,
			AIPS_Background_Process_Repository::open_statuses(),
			__('Stopped by an administrator.', 'ai-post-scheduler')
		);

		if ($changed) {
			wp_clear_scheduled_hook(self::TICK_HOOK, array($this->get_key()));
		}

		return $changed;
	}

	/**
	 * Re-queue a running process whose tick event went missing (for example
	 * after cron events were flushed). Safe to call on every status poll.
	 *
	 * @return void
	 */
	public function ensure_scheduled(): void {
		$run = $this->repository->get_open($this->get_key());
		if (!$run || !in_array($run->status, AIPS_Background_Process_Repository::active_statuses(), true)) {
			return;
		}

		if (!wp_next_scheduled(self::TICK_HOOK, array($this->get_key()))) {
			$this->schedule_tick(max(0, $run->next_run_at - time()));
		}
	}

	// -------------------------------------------------------------------------
	// Tick loop
	// -------------------------------------------------------------------------

	/**
	 * Run one slice of the current run, then schedule the next tick.
	 *
	 * Entry point for the TICK_HOOK cron event.
	 *
	 * @return void
	 */
	public function tick(): void {
		$run = $this->repository->get_open($this->get_key());
		if (!$run || !in_array($run->status, AIPS_Background_Process_Repository::active_statuses(), true)) {
			return;
		}

		// Overlapping ticks (a spawned cron plus a manual run) would double-process a slice.
		$lock_key = 'aips_bg_lock_' . $this->get_key();
		if (get_transient($lock_key)) {
			$this->schedule_tick(30);
			return;
		}
		set_transient($lock_key, 1, self::LOCK_TTL);

		// Arm the watchdog: replaced by the real next tick when the slice returns.
		$this->replace_tick(self::WATCHDOG_DELAY);

		try {
			$this->run_tick($run);
		} catch (Throwable $e) {
			(new AIPS_Logger())->log(
				sprintf('Background process %s tick failed: %s', $this->get_key(), $e->getMessage()),
				'error'
			);
			$this->repository->transition(
				$run->id,
				AIPS_Background_Process_Repository::STATUS_FAILED,
				AIPS_Background_Process_Repository::active_statuses(),
				$e->getMessage()
			);
		} finally {
			delete_transient($lock_key);

			// A run that is no longer active (finished, failed, paused or stopped) has no
			// next tick: drop the watchdog. An active run already has its real tick.
			$latest = $this->repository->get($run->id);
			if (!$latest || !in_array($latest->status, AIPS_Background_Process_Repository::active_statuses(), true)) {
				wp_clear_scheduled_hook(self::TICK_HOOK, array($this->get_key()));
			}
		}
	}

	/**
	 * @param object $run Current run row.
	 * @return void
	 */
	private function run_tick(object $run): void {
		$active = AIPS_Background_Process_Repository::active_statuses();

		// Quota or cooldown first: never start a slice that cannot finish.
		$limit = $this->check_limits();
		if ($limit !== null) {
			$this->repository->transition($run->id, $limit['status'], $active, $limit['message'], $limit['retry_at']);
			$this->replace_tick(max(30, $limit['retry_at'] - time()));
			return;
		}

		// Per-run AI budget.
		if ($run->ai_calls_budget > 0 && $run->ai_calls_used >= $run->ai_calls_budget) {
			$this->repository->transition(
				$run->id,
				AIPS_Background_Process_Repository::STATUS_PAUSED,
				$active,
				__('AI call budget for this run reached. Resume to carry on without a budget.', 'ai-post-scheduler')
			);
			return;
		}

		$size = min($this->get_batch_size(), $this->get_allowance());
		if ($run->ai_calls_budget > 0) {
			$size = min($size, $run->ai_calls_budget - $run->ai_calls_used);
		}

		if ($size <= 0) {
			$this->replace_tick(60);
			return;
		}

		if ($run->status !== AIPS_Background_Process_Repository::STATUS_RUNNING) {
			$this->repository->transition($run->id, AIPS_Background_Process_Repository::STATUS_RUNNING, $active, '', time());
		}

		$calls_before = $this->count_ai_calls();
		$result       = $this->process_slice($run->cursor_id, $size, $run->options);
		$calls_made   = max(0, $this->count_ai_calls() - $calls_before);

		$processed = isset($result['processed']) ? (int) $result['processed'] : 0;
		$failed    = isset($result['failed']) ? (int) $result['failed'] : 0;
		$message   = isset($result['message']) ? (string) $result['message'] : '';

		$this->repository->add_progress($run->id, $processed + $failed, $failed, isset($result['cursor']) ? (int) $result['cursor'] : 0, $calls_made, $message);

		// Paused or stopped by an administrator while the slice ran: leave that status alone.
		$latest = $this->repository->get($run->id);
		if (!$latest || !in_array($latest->status, $active, true)) {
			return;
		}

		if (!empty($result['fatal']) && is_wp_error($result['fatal'])) {
			$this->repository->transition($run->id, AIPS_Background_Process_Repository::STATUS_FAILED, $active, $result['fatal']->get_error_message());
			return;
		}

		if (!empty($result['done'])) {
			$this->repository->transition(
				$run->id,
				AIPS_Background_Process_Repository::STATUS_COMPLETED,
				$active,
				__('Finished.', 'ai-post-scheduler')
			);
			return;
		}

		$delay = $this->get_delay();
		$this->repository->transition($run->id, AIPS_Background_Process_Repository::STATUS_RUNNING, $active, null, time() + $delay);
		$this->replace_tick($delay);
	}

	/**
	 * Schedule the next tick unless one is already queued.
	 *
	 * @param int $delay Seconds from now.
	 * @return void
	 */
	protected function schedule_tick(int $delay): void {
		$args = array($this->get_key());

		if (!wp_next_scheduled(self::TICK_HOOK, $args)) {
			wp_schedule_single_event(time() + max(0, $delay), self::TICK_HOOK, $args);
		}
	}

	/**
	 * Schedule the next tick, replacing any tick already queued (such as the watchdog).
	 *
	 * @param int $delay Seconds from now.
	 * @return void
	 */
	private function replace_tick(int $delay): void {
		$args = array($this->get_key());

		wp_clear_scheduled_hook(self::TICK_HOOK, $args);
		wp_schedule_single_event(time() + max(0, $delay), self::TICK_HOOK, $args);
	}

	/**
	 * Keep only scalar options so they survive JSON storage.
	 *
	 * @param array $options Raw options.
	 * @return array
	 */
	protected function sanitize_options(array $options): array {
		$clean = array();
		foreach ($options as $key => $value) {
			if (is_scalar($value) || $value === null) {
				$clean[sanitize_key((string) $key)] = $value;
			}
		}
		return $clean;
	}
}
