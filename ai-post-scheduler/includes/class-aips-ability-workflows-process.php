<?php
/**
 * Ability Workflows Process
 *
 * Background process that executes Ability Workflow runs. Each slice walks the
 * open runs (queued or running) oldest first and gives each one a bounded
 * slice of work through AIPS_Ability_Workflow_Executor::process_run(). The
 * shared tick loop supplies the cron event, the lock, the watchdog for a slice
 * that dies, pause/stop controls and the Diagnostics > Background Processes
 * row; this class only decides which runs to advance and when to look again.
 *
 * Stopping the process leaves queued runs queued: they continue when it is
 * started again. Cancelling a single run is done through the workflow UI.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Ability_Workflows_Process extends AIPS_Ability_Background_Process {

	const KEY = 'ability_workflows';

	/**
	 * Seconds one slice may spend advancing runs.
	 */
	const SLICE_TIME_BUDGET = 25.0;

	/**
	 * Most open runs one slice looks at.
	 */
	const MAX_RUNS_PER_SLICE = 50;

	/**
	 * Seconds to wait before the next slice when nothing says otherwise.
	 */
	const DEFAULT_DELAY = 2;

	/**
	 * Longest wait between slices while every open run is backing off.
	 */
	const MAX_DELAY = 60;

	/**
	 * @var AIPS_Ability_Workflow_Repository
	 */
	private $workflows;

	/**
	 * @var AIPS_Ability_Workflow_Executor|null
	 */
	private $executor;

	/**
	 * @var int Delay before the next slice, set by the last slice.
	 */
	private $next_delay = self::DEFAULT_DELAY;

	/**
	 * @param AIPS_Background_Process_Repository|null $repository Run repository of the tick loop.
	 * @param AIPS_Ability_Workflow_Repository|null   $workflows  Workflow repository.
	 * @param AIPS_Ability_Workflow_Executor|null     $executor   Executor (built on first use when omitted).
	 */
	public function __construct(
		?AIPS_Background_Process_Repository $repository = null,
		?AIPS_Ability_Workflow_Repository $workflows = null,
		?AIPS_Ability_Workflow_Executor $executor = null
	) {
		parent::__construct($repository);

		$this->workflows = $workflows ?: AIPS_Ability_Workflow_Repository::instance();
		$this->executor  = $executor;
	}

	public function get_key(): string {
		return self::KEY;
	}

	public function get_label(): string {
		return __('Ability Workflows', 'ai-post-scheduler');
	}

	public function get_description(): string {
		return __('Runs queued Ability Workflow runs step by step. Stopping it leaves queued runs waiting until it is started again.', 'ai-post-scheduler');
	}

	/**
	 * @return true|WP_Error
	 */
	protected function can_run() {
		if (!AIPS_Ability_Workflows_Feature::is_enabled()) {
			return new WP_Error('ability_workflows_disabled', __('Ability Workflows are disabled.', 'ai-post-scheduler'));
		}

		return true;
	}

	protected function count_remaining(): int {
		return $this->workflows->count_open_runs();
	}

	protected function get_batch_size(): int {
		return 3;
	}

	protected function get_delay(): int {
		return $this->next_delay;
	}

	/**
	 * Advance the open runs.
	 *
	 * @param int   $cursor  Unused: finished runs leave the open set, so every slice starts from the oldest.
	 * @param int   $limit   Most runs that may execute steps in this slice.
	 * @param array $options Unused.
	 * @return array
	 */
	protected function process_slice(int $cursor, int $limit, array $options): array {
		if (!AIPS_Ability_Workflows_Feature::is_enabled()) {
			return array(
				'processed' => 0,
				'failed'    => 0,
				'cursor'    => 0,
				'done'      => false,
				'fatal'     => new WP_Error('ability_workflows_disabled', __('Ability Workflows were disabled.', 'ai-post-scheduler')),
			);
		}

		$executor = $this->get_executor();
		$deadline = microtime(true) + self::SLICE_TIME_BUDGET;
		$finished = 0;
		$failed   = 0;
		$worked   = 0;
		$earliest = 0;

		foreach ($this->workflows->get_open_run_ids(self::MAX_RUNS_PER_SLICE) as $run_id) {
			// Runs that are only waiting out a backoff cost almost nothing, so they do not
			// count against the limit; runs that did real work do.
			if ($worked >= $limit || microtime(true) >= $deadline) {
				break;
			}

			$result = $executor->process_run($run_id);

			if (!empty($result['worked'])) {
				$worked++;
			}

			if ($result['state'] === AIPS_Ability_Workflow_Executor::RESULT_FINISHED) {
				$run = $this->workflows->get_run($run_id);

				if ($run && $run->status === AIPS_Ability_Workflow_Repository::RUN_STATUS_FAILED) {
					$failed++;
				} elseif ($run && $run->status === AIPS_Ability_Workflow_Repository::RUN_STATUS_COMPLETED) {
					$finished++;
				}

				continue;
			}

			$retry_at = (int) $result['retry_at'];
			if ($retry_at > 0) {
				$earliest = $earliest === 0 ? $retry_at : min($earliest, $retry_at);
			}
		}

		$remaining = $this->workflows->count_open_runs();

		$this->next_delay = self::DEFAULT_DELAY;
		if ($remaining > 0 && $earliest > 0 && $worked === 0) {
			// Everything open is backing off: sleep until the first retry is due.
			$this->next_delay = max(1, min(self::MAX_DELAY, $earliest - time()));
		}

		return array(
			'processed' => $finished,
			'failed'    => $failed,
			'cursor'    => 0,
			'done'      => $remaining === 0,
			'message'   => $remaining > 0
				/* translators: %d: number of workflow runs still open */
				? sprintf(_n('%d run still open.', '%d runs still open.', $remaining, 'ai-post-scheduler'), $remaining)
				: '',
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get_estimate(array $options = array()): array {
		$items = $this->count_remaining();

		return array(
			'items'      => $items,
			'ai_calls'   => 0,
			'days'       => 0,
			'daily_rate' => 0,
			'message'    => $items <= 0
				? __('There are no queued workflow runs.', 'ai-post-scheduler')
				/* translators: %d: number of workflow runs */
				: sprintf(_n('%d workflow run is waiting or in progress.', '%d workflow runs are waiting or in progress.', $items, 'ai-post-scheduler'), $items),
		);
	}

	/**
	 * @return AIPS_Ability_Workflow_Executor
	 */
	private function get_executor(): AIPS_Ability_Workflow_Executor {
		if (!$this->executor) {
			$this->executor = new AIPS_Ability_Workflow_Executor();
		}

		return $this->executor;
	}
}
