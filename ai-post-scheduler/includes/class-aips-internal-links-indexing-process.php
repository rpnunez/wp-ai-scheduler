<?php
/**
 * Internal Links Indexing Process
 *
 * The "Index Posts" job on the Internal Links page: embeds every unindexed post
 * in the configured indexing scope, one slice per cron tick, within the
 * embeddings rate limits and cooldown (see AIPS_Embeddings_Background_Process).
 *
 * @package AI_Post_Scheduler
 * @since 3.7.11
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Internal_Links_Indexing_Process extends AIPS_Embeddings_Background_Process {

	const KEY = 'internal_links_indexing';

	/**
	 * @var AIPS_Internal_Links_Service
	 */
	private $service;

	/**
	 * @param AIPS_Background_Process_Repository|null $repository         Run repository.
	 * @param AIPS_Internal_Links_Service|null        $service            Internal links service.
	 * @param AIPS_Embeddings_Service|null            $embeddings_service Embeddings service.
	 * @param AIPS_Config|null                        $config             Config.
	 */
	public function __construct(
		?AIPS_Background_Process_Repository $repository = null,
		?AIPS_Internal_Links_Service $service = null,
		?AIPS_Embeddings_Service $embeddings_service = null,
		?AIPS_Config $config = null
	) {
		parent::__construct($repository, $embeddings_service, $config);

		$this->service = $service ?: new AIPS_Internal_Links_Service(null, null, $this->embeddings_service);
	}

	public function get_key(): string {
		return self::KEY;
	}

	public function get_label(): string {
		return __('Internal Links Indexing', 'ai-post-scheduler');
	}

	public function get_description(): string {
		return __('Generates embeddings for unindexed posts in the indexing scope so internal link suggestions can be found. Uses AI embedding calls and honors the embeddings rate limits.', 'ai-post-scheduler');
	}

	// -------------------------------------------------------------------------
	// Managed process hooks
	// -------------------------------------------------------------------------

	protected function can_run() {
		if (!$this->embeddings_service->is_enabled()) {
			return new WP_Error('aips_bg_embeddings_disabled', __('The vector embeddings system is disabled in settings.', 'ai-post-scheduler'));
		}

		if (!$this->embeddings_service->is_embeddings_supported()) {
			return new WP_Error('aips_bg_embeddings_unavailable', __('Embeddings are not available. Please configure AI Engine.', 'ai-post-scheduler'));
		}

		return true;
	}

	protected function count_remaining(): int {
		$status = $this->service->get_indexing_status();
		return isset($status['unindexed']) ? (int) $status['unindexed'] : 0;
	}

	protected function process_slice(int $cursor, int $limit, array $options): array {
		$result = $this->service->process_indexing_batch($limit, $cursor);

		$success    = isset($result['success']) ? (int) $result['success'] : 0;
		$failed     = isset($result['failed']) ? (int) $result['failed'] : 0;
		$new_cursor = isset($result['last_post_id']) ? (int) $result['last_post_id'] : $cursor;

		// The batch stopped on its first post without moving the cursor: a rate limit
		// or cooldown tripped mid-run. Nothing was processed; the next tick re-checks
		// the limits and retries the same post.
		if ($new_cursor === $cursor && $success === 0) {
			return array(
				'processed' => 0,
				'failed'    => 0,
				'cursor'    => $cursor,
				'done'      => ($failed === 0 && empty($result['cooldown'])),
				'message'   => $failed > 0 ? __('Waiting for the embeddings rate limit.', 'ai-post-scheduler') : '',
			);
		}

		// A slice that fetched nothing means every remaining post has been visited.
		return array(
			'processed' => $success,
			'failed'    => $failed,
			'cursor'    => $new_cursor,
			'done'      => ($success + $failed) === 0,
		);
	}

	// -------------------------------------------------------------------------
	// Estimate
	// -------------------------------------------------------------------------

	/**
	 * @inheritDoc
	 */
	public function get_estimate(array $options = array()): array {
		$items    = $this->count_remaining();
		$duration = $this->estimate_duration($items);

		return array(
			'items'      => $items,
			'ai_calls'   => $items,
			'days'       => $duration['days'],
			'daily_rate' => $duration['daily_rate'],
			'message'    => $this->build_estimate_message($items, $duration['daily_rate'], $duration['days']),
		);
	}

	/**
	 * @param int $items      Posts to index.
	 * @param int $daily_rate Sustainable embeddings per day (0 = unlimited).
	 * @param int $days       Days needed at that rate.
	 * @return string
	 */
	private function build_estimate_message(int $items, int $daily_rate, int $days): string {
		if ($items <= 0) {
			return __('Every post in the indexing scope is already indexed.', 'ai-post-scheduler');
		}

		if ($daily_rate <= 0) {
			return sprintf(
				/* translators: %s: number of posts. */
				_n(
					'%s post will be indexed using one embedding call. No embeddings rate limit is set. It runs in the background and can be paused or stopped at any time.',
					'%s posts will be indexed using one embedding call each. No embeddings rate limit is set. It runs in the background and can be paused or stopped at any time.',
					$items,
					'ai-post-scheduler'
				),
				number_format_i18n($items)
			);
		}

		return sprintf(
			/* translators: 1: number of posts, 2: embeddings allowed per day, 3: days needed. */
			_n(
				'%1$s post will be indexed using one embedding call. Your limits allow about %2$s per day, so this takes about %3$s day(s). It pauses itself when a quota is reached, resumes automatically, and can be paused or stopped at any time.',
				'%1$s posts will be indexed using one embedding call each. Your limits allow about %2$s per day, so this takes about %3$s day(s). It pauses itself when a quota is reached, resumes automatically, and can be paused or stopped at any time.',
				$items,
				'ai-post-scheduler'
			),
			number_format_i18n($items),
			number_format_i18n($daily_rate),
			number_format_i18n(max(1, $days))
		);
	}
}
