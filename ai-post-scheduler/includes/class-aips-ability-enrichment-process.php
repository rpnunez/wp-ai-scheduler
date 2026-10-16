<?php
/**
 * Ability Enrichment Process
 *
 * Background process that enriches generated posts with WordPress Abilities
 * (featured-image alt text, categories and tags). Generation only queues the
 * post (AIPS_Ability_Enrichment_Service::handle_post_generated()); this
 * process drains the queue a couple of posts at a time, so the AI calls never
 * run inside the generation request or its cron slice and an administrator can
 * pause or stop them from Diagnostics > Background Processes.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Ability_Enrichment_Process extends AIPS_Ability_Background_Process {

	const KEY = 'ability_enrichment';

	/**
	 * @var AIPS_Ability_Enrichment_Queue_Repository
	 */
	private $queue;

	/**
	 * @var AIPS_Ability_Enrichment_Service|null
	 */
	private $service;

	/**
	 * @param AIPS_Background_Process_Repository|null       $repository Run repository of the tick loop.
	 * @param AIPS_Ability_Enrichment_Queue_Repository|null $queue      Queue of posts to enrich.
	 * @param AIPS_Ability_Enrichment_Service|null          $service    Enrichment service (built on first use when omitted).
	 */
	public function __construct(
		?AIPS_Background_Process_Repository $repository = null,
		?AIPS_Ability_Enrichment_Queue_Repository $queue = null,
		?AIPS_Ability_Enrichment_Service $service = null
	) {
		parent::__construct($repository);

		$this->queue   = $queue ?: new AIPS_Ability_Enrichment_Queue_Repository();
		$this->service = $service;
	}

	public function get_key(): string {
		return self::KEY;
	}

	public function get_label(): string {
		return __('Ability Enrichment', 'ai-post-scheduler');
	}

	public function get_description(): string {
		return __('Adds alt text, categories and tags to generated posts with WordPress Abilities, a couple of posts at a time.', 'ai-post-scheduler');
	}

	/**
	 * @return true|WP_Error
	 */
	protected function can_run() {
		if (!AIPS_Ability_Enrichment_Service::is_enabled()) {
			return new WP_Error('ability_enrichment_disabled', __('Ability Enrichment is disabled.', 'ai-post-scheduler'));
		}

		return true;
	}

	protected function count_remaining(): int {
		return $this->queue->count_pending();
	}

	protected function get_batch_size(): int {
		return 2;
	}

	protected function get_delay(): int {
		return 3;
	}

	/**
	 * Enrich the oldest queued posts.
	 *
	 * @param int   $cursor  Unused: an enriched post leaves the queue, so every slice starts from the oldest.
	 * @param int   $limit   Most posts to enrich in this slice.
	 * @param array $options Unused.
	 * @return array
	 */
	protected function process_slice(int $cursor, int $limit, array $options): array {
		if (!AIPS_Ability_Enrichment_Service::is_enabled()) {
			return array(
				'processed' => 0,
				'failed'    => 0,
				'cursor'    => 0,
				'done'      => false,
				'fatal'     => new WP_Error('ability_enrichment_disabled', __('Ability Enrichment was disabled.', 'ai-post-scheduler')),
			);
		}

		$service   = $this->get_service();
		$processed = 0;
		$failed    = 0;

		foreach ($this->queue->get_pending($limit) as $item) {
			$outcomes = $service->enrich_post($item['post_id'], $item['user_id']);

			// A post is tried once. Whatever the outcome, it leaves the queue: failures are
			// logged by the service and a permanent error would otherwise block the queue.
			$this->queue->dequeue($item['post_id']);

			if ($this->has_failure($outcomes)) {
				$failed++;
			} else {
				$processed++;
			}
		}

		return array(
			'processed' => $processed,
			'failed'    => $failed,
			'cursor'    => 0,
			'done'      => $this->queue->count_pending() === 0,
		);
	}

	/**
	 * Whether any step failed or the post could not be enriched at all.
	 *
	 * @param array<string,string> $outcomes Step key => outcome from enrich_post().
	 * @return bool
	 */
	private function has_failure(array $outcomes): bool {
		foreach ($outcomes as $outcome) {
			if (strpos((string) $outcome, 'error:') === 0 || $outcome === 'skipped:no_principal') {
				return true;
			}
		}

		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function get_estimate(array $options = array()): array {
		$items = $this->count_remaining();

		return array(
			'items'      => $items,
			'ai_calls'   => $items * 3,
			'days'       => 0,
			'daily_rate' => 0,
			'message'    => $items <= 0
				? __('No generated posts are waiting for enrichment.', 'ai-post-scheduler')
				/* translators: %d: number of posts */
				: sprintf(_n('%d generated post is waiting for enrichment (up to 3 ability calls each).', '%d generated posts are waiting for enrichment (up to 3 ability calls each).', $items, 'ai-post-scheduler'), $items),
		);
	}

	/**
	 * @return AIPS_Ability_Enrichment_Service
	 */
	private function get_service(): AIPS_Ability_Enrichment_Service {
		if (!$this->service) {
			$this->service = AIPS_Container::get_instance()->make(AIPS_Ability_Enrichment_Service::class);
		}

		return $this->service;
	}
}
