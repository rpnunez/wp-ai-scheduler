<?php
/**
 * Bulk Generation Process
 *
 * Lets the large bulk post-generation queue (Author Topics, Planner and
 * Research "generate all" actions) be watched, paused, resumed and stopped from
 * the Background Processes tab, the admin bar and the Heartbeat updates.
 *
 * A large bulk run is a stored job (AIPS_Bulk_Batch_Job_Store) plus one cron
 * event per slice, all scheduled up front. So:
 *
 *   - pause   records and unschedules the job's pending slices;
 *   - resume  schedules them again, keeping their spacing;
 *   - stop    unschedules them and marks the job cancelled.
 *
 * This process aggregates every open bulk job. It cannot start one: bulk runs
 * are started from the screen that picks the items.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.13
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Bulk_Generation_Process extends AIPS_Background_Process_Base {

	const KEY = 'bulk_generation';

	/**
	 * Job option holding the slices removed from cron while the job is paused.
	 */
	const PAUSED_SLICES_OPTION = 'paused_slices';

	/**
	 * Seconds before the first resumed slice runs.
	 */
	const RESUME_DELAY = 5;

	/**
	 * @var AIPS_Bulk_Batch_Job_Store
	 */
	private $job_store;

	/**
	 * @param AIPS_Bulk_Batch_Job_Store|null $job_store Job store.
	 */
	public function __construct(?AIPS_Bulk_Batch_Job_Store $job_store = null) {
		$this->job_store = $job_store ?: new AIPS_Bulk_Batch_Job_Store();
	}

	public function get_key(): string {
		return self::KEY;
	}

	public function get_label(): string {
		return __('Bulk Post Generation', 'ai-post-scheduler');
	}

	public function get_description(): string {
		return __('Large "generate all" runs from Author Topics, the Planner and Research, spread over many background slices. Each post generated uses AI. Start them from the screen that picks the topics.', 'ai-post-scheduler');
	}

	public function uses_ai(): bool {
		return true;
	}

	/**
	 * Job types that count as bulk post generation.
	 *
	 * @return string[]
	 */
	private function get_job_types(): array {
		return array_values(array_filter(array_map('sanitize_key', (array) apply_filters(
			'aips_bulk_generation_job_types',
			array('author_topic_post', 'planner_post', 'trending_topic_post')
		))));
	}

	/**
	 * Cron events still queued for each bulk job.
	 *
	 * @return array<string,array<int,array{timestamp:int,args:array}>> Job ID => slices.
	 */
	private function get_scheduled_slices(): array {
		$slices = array();
		$cron   = function_exists('_get_cron_array') ? _get_cron_array() : array();

		if (!is_array($cron)) {
			return $slices;
		}

		foreach ($cron as $timestamp => $hooks) {
			if (!isset($hooks[AIPS_Bulk_Batch_Processor::HOOK])) {
				continue;
			}

			foreach ($hooks[AIPS_Bulk_Batch_Processor::HOOK] as $event) {
				$args   = isset($event['args']) ? (array) $event['args'] : array();
				$job_id = isset($args[0]) ? (string) $args[0] : '';

				if ($job_id !== '') {
					$slices[$job_id][] = array('timestamp' => (int) $timestamp, 'args' => $args);
				}
			}
		}

		return $slices;
	}

	/**
	 * Bulk jobs that still have work: running, pending, paused, or marked failed
	 * after a bad slice but with slices still queued.
	 *
	 * @param array $slices Result of get_scheduled_slices().
	 * @return object[]
	 */
	private function get_open_jobs(array $slices): array {
		$jobs = $this->job_store->get_jobs_by_status($this->get_job_types(), array(
			AIPS_Bulk_Batch_Job_Store::STATUS_PENDING,
			AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING,
			AIPS_Bulk_Batch_Job_Store::STATUS_PAUSED,
			AIPS_Bulk_Batch_Job_Store::STATUS_FAILED,
		));

		return array_values(array_filter($jobs, function ($job) use ($slices) {
			if ($job->status === AIPS_Bulk_Batch_Job_Store::STATUS_FAILED) {
				return !empty($slices[$job->job_id]);
			}
			return true;
		}));
	}

	/**
	 * @inheritDoc
	 */
	public function get_snapshot(): array {
		$jobs = $this->get_open_jobs($this->get_scheduled_slices());

		if (empty($jobs)) {
			return $this->make_snapshot(array('status' => self::STATUS_IDLE, 'can_start' => false));
		}

		$processed = 0;
		$total     = 0;
		$running   = false;
		$started   = 0;
		$updated   = 0;

		foreach ($jobs as $job) {
			$processed += $job->processed;
			$total     += $job->total;
			$running    = $running || $job->status !== AIPS_Bulk_Batch_Job_Store::STATUS_PAUSED;
			$started    = $started === 0 ? (int) $job->created_at : min($started, (int) $job->created_at);
			$updated    = max($updated, (int) $job->updated_at);
		}

		return $this->make_snapshot(array(
			'status'     => $running ? 'running' : 'paused',
			'processed'  => $processed,
			'total'      => $total,
			'started_at' => $started,
			'updated_at' => $updated,
			'can_start'  => false,
			'message'    => sprintf(
				/* translators: %d: number of bulk generation jobs. */
				_n('%d bulk job open.', '%d bulk jobs open.', count($jobs), 'ai-post-scheduler'),
				count($jobs)
			),
		));
	}

	/**
	 * @inheritDoc
	 */
	public function start(array $options = array()) {
		return new WP_Error(
			'aips_bg_start_elsewhere',
			__('Bulk generation is started from Author Topics, the Planner or Research, where you choose the topics.', 'ai-post-scheduler')
		);
	}

	/**
	 * @inheritDoc
	 */
	public function pause(): bool {
		$slices = $this->get_scheduled_slices();
		$paused = false;

		foreach ($this->get_open_jobs($slices) as $job) {
			if ($job->status === AIPS_Bulk_Batch_Job_Store::STATUS_PAUSED) {
				continue;
			}

			$queued  = isset($slices[$job->job_id]) ? $slices[$job->job_id] : array();
			$options = $job->options;

			$options[self::PAUSED_SLICES_OPTION] = $queued;
			$this->job_store->update_options($job->job_id, $options);
			$this->job_store->update_status($job->job_id, AIPS_Bulk_Batch_Job_Store::STATUS_PAUSED);

			foreach ($queued as $slice) {
				wp_unschedule_event($slice['timestamp'], AIPS_Bulk_Batch_Processor::HOOK, $slice['args']);
			}

			$paused = true;
		}

		return $paused;
	}

	/**
	 * @inheritDoc
	 */
	public function resume(): bool {
		$resumed = false;

		foreach ($this->get_open_jobs(array()) as $job) {
			if ($job->status !== AIPS_Bulk_Batch_Job_Store::STATUS_PAUSED) {
				continue;
			}

			$options = $job->options;
			$queued  = isset($options[self::PAUSED_SLICES_OPTION]) && is_array($options[self::PAUSED_SLICES_OPTION])
				? $options[self::PAUSED_SLICES_OPTION]
				: array();
			unset($options[self::PAUSED_SLICES_OPTION]);

			$this->job_store->update_options($job->job_id, $options);
			$this->job_store->update_status($job->job_id, AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING);

			$this->reschedule_slices($queued);

			$resumed = true;
		}

		return $resumed;
	}

	/**
	 * @inheritDoc
	 */
	public function cancel(): bool {
		$slices    = $this->get_scheduled_slices();
		$cancelled = false;

		foreach ($this->get_open_jobs($slices) as $job) {
			$options = $job->options;
			unset($options[self::PAUSED_SLICES_OPTION]);

			$this->job_store->update_options($job->job_id, $options);
			$this->job_store->update_status($job->job_id, AIPS_Bulk_Batch_Job_Store::STATUS_CANCELLED);

			if (!empty($slices[$job->job_id])) {
				foreach ($slices[$job->job_id] as $slice) {
					wp_unschedule_event($slice['timestamp'], AIPS_Bulk_Batch_Processor::HOOK, $slice['args']);
				}
			}

			$cancelled = true;
		}

		return $cancelled;
	}

	/**
	 * Schedule slices again, keeping their original spacing but starting now.
	 *
	 * @param array $queued Slices as recorded by pause().
	 * @return void
	 */
	private function reschedule_slices(array $queued): void {
		$timestamps = array();
		foreach ($queued as $slice) {
			if (isset($slice['timestamp'], $slice['args'])) {
				$timestamps[] = (int) $slice['timestamp'];
			}
		}

		if (empty($timestamps)) {
			return;
		}

		$first = min($timestamps);
		$base  = time() + self::RESUME_DELAY;

		foreach ($queued as $slice) {
			if (!isset($slice['timestamp'], $slice['args'])) {
				continue;
			}

			wp_schedule_single_event(
				$base + ((int) $slice['timestamp'] - $first),
				AIPS_Bulk_Batch_Processor::HOOK,
				(array) $slice['args']
			);
		}
	}
}
