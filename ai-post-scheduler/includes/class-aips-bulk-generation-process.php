<?php
/**
 * Bulk Generation Process
 *
 * Lets the large bulk post-generation queue (Author Topics, Planner and
 * Research "generate all" actions) be watched, paused, resumed and stopped from
 * the Background Processes tab, the admin bar and the Heartbeat updates.
 *
 * Bridges onto AIPS_Job_Manager and wp_aips_jobs.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.13
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Bulk_Generation_Process extends AIPS_Background_Process_Base {

	const KEY = 'bulk_generation';

	const PAUSED_SLICES_OPTION = 'paused_slices';

	const RESUME_DELAY = 5;

	/**
	 * @var AIPS_Job_Manager
	 */
	private $job_manager;

	/**
	 * @var AIPS_Job_Repository
	 */
	private $job_repo;

	/**
	 * @param AIPS_Job_Manager|object|null $job_manager Job manager.
	 */
	public function __construct( $job_manager = null ) {
		$container         = AIPS_Container::get_instance();
		$this->job_manager = ( $job_manager instanceof AIPS_Job_Manager ) ? $job_manager : $container->get_job_manager();
		$this->job_repo    = $container->get_job_repository();
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
			array('bulk_post_generation', 'bulk_post_publish', 'author_topic_post', 'planner_post', 'trending_topic_post', 'review_publish', 'bulk_batch_slice')
		))));
	}

	/**
	 * Bulk jobs that still have work: running, pending, paused.
	 *
	 * @return AIPS_Job_Record[]
	 */
	private function get_open_jobs(): array {
		$types   = $this->get_job_types();
		$open    = array();
		$records = $this->job_repo->query( array(), 100 );

		foreach ( $records as $job ) {
			if ( in_array( $job->get_job_type(), $types, true ) && ( $job->is_active() || $job->is_paused() ) ) {
				$open[] = $job;
			}
		}

		return $open;
	}

	/**
	 * @inheritDoc
	 */
	public function get_snapshot(): array {
		$jobs = $this->get_open_jobs();

		if (empty($jobs)) {
			return $this->make_snapshot(array('status' => self::STATUS_IDLE, 'can_start' => false));
		}

		$processed = 0;
		$total     = 0;
		$running   = false;
		$started   = 0;
		$updated   = 0;

		foreach ($jobs as $job) {
			$processed += $job->get_processed();
			$total     += $job->get_total();
			$running    = $running || $job->is_active();
			$started    = $started === 0 ? (int) $job->get_created_at() : min($started, (int) $job->get_created_at());
			$updated    = max($updated, (int) $job->get_updated_at());
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
		$jobs   = $this->get_open_jobs();
		$paused = false;

		foreach ($jobs as $job) {
			if ($job->is_paused()) {
				continue;
			}
			if ($this->job_manager->pause($job->get_job_id())) {
				$paused = true;
			}
		}

		return $paused;
	}

	/**
	 * @inheritDoc
	 */
	public function resume(): bool {
		$jobs    = $this->get_open_jobs();
		$resumed = false;

		foreach ($jobs as $job) {
			if (!$job->is_paused()) {
				continue;
			}
			if ($this->job_manager->resume($job->get_job_id())) {
				$resumed = true;
			}
		}

		return $resumed;
	}

	/**
	 * @inheritDoc
	 */
	public function cancel(): bool {
		$jobs      = $this->get_open_jobs();
		$cancelled = false;

		foreach ($jobs as $job) {
			if ($this->job_manager->cancel($job->get_job_id())) {
				$cancelled = true;
			}
		}

		return $cancelled;
	}
}
