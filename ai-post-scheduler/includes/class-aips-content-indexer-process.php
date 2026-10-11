<?php
/**
 * Content Indexer Process
 *
 * The managed "Index content" job on the Content Indexer page. It embeds every
 * unindexed post and topic in the configured scope (or, with the 'stale'
 * mode, re-embeds posts made with another model or dimension count), one
 * slice per cron tick, within the embeddings rate limits and cooldown (see
 * AIPS_Embeddings_Background_Process).
 *
 * @package AI_Post_Scheduler
 * @since 3.7.12
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Content_Indexer_Process extends AIPS_Embeddings_Background_Process {

	const KEY = 'content_indexing';

	/**
	 * @var AIPS_Content_Indexer_Service
	 */
	private $indexer;

	/**
	 * @param AIPS_Background_Process_Repository|null $repository         Run repository.
	 * @param AIPS_Content_Indexer_Service|null       $indexer            Content indexer service.
	 * @param AIPS_Embeddings_Service|null            $embeddings_service Embeddings service.
	 * @param AIPS_Config|null                        $config             Config.
	 */
	public function __construct(
		?AIPS_Background_Process_Repository $repository = null,
		?AIPS_Content_Indexer_Service $indexer = null,
		?AIPS_Embeddings_Service $embeddings_service = null,
		?AIPS_Config $config = null
	) {
		parent::__construct($repository, $embeddings_service, $config);

		$this->indexer = $indexer ?: AIPS_Container::get_instance()->make(AIPS_Content_Indexer_Service::class);
	}

	public function get_key(): string {
		return self::KEY;
	}

	public function get_label(): string {
		return __('Content Indexing', 'ai-post-scheduler');
	}

	public function get_description(): string {
		return __('Generates embeddings for unindexed posts and topics in the indexing scope, or re-embeds stale ones. Uses AI embedding calls and honors the embeddings rate limits.', 'ai-post-scheduler');
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
		return $this->count_for(array());
	}

	protected function process_slice(int $cursor, int $limit, array $options): array {
		$stale_only = $this->is_stale_mode($options);
		$result     = $this->indexer->process_indexing_batch(
			$limit,
			$cursor,
			$this->get_post_types(),
			'publish',
			$this->get_entity_scope(),
			$stale_only
		);

		$success    = isset($result['success']) ? (int) $result['success'] : 0;
		$failed     = isset($result['failed']) ? (int) $result['failed'] : 0;
		$new_cursor = isset($result['last_post_id']) ? (int) $result['last_post_id'] : $cursor;

		// A rate limit or cooldown stopped the slice (the service reports done=true for it).
		// The run is not finished: keep what was embedded and let the next tick re-check the
		// limits and retry from the item that tripped them.
		if (!empty($result['rate_limit_exceeded'])) {
			return array(
				'processed' => $success,
				'failed'    => $success === 0 ? 0 : $failed,
				'cursor'    => $new_cursor,
				'done'      => false,
				'message'   => __('Waiting for the embeddings rate limit.', 'ai-post-scheduler'),
			);
		}

		// A slice that embedded nothing and moved the cursor nowhere (only topics that
		// keep failing, or an empty scope) would repeat forever: treat it as finished.
		return array(
			'processed' => $success,
			'failed'    => $failed,
			'cursor'    => $new_cursor,
			'done'      => !empty($result['done']) || ($success === 0 && $new_cursor === $cursor),
		);
	}

	// -------------------------------------------------------------------------
	// Estimate
	// -------------------------------------------------------------------------

	/**
	 * @inheritDoc
	 */
	public function get_estimate(array $options = array()): array {
		$items    = $this->count_for($options);
		$duration = $this->estimate_duration($items);

		return array(
			'items'      => $items,
			'ai_calls'   => $items,
			'days'       => $duration['days'],
			'daily_rate' => $duration['daily_rate'],
			'message'    => $this->build_estimate_message($items, $duration['daily_rate'], $duration['days'], $this->is_stale_mode($options)),
		);
	}

	/**
	 * Items a run with these options would embed.
	 *
	 * @param array $options Run options (mode: 'stale' re-embeds only stale posts).
	 * @return int
	 */
	private function count_for(array $options): int {
		$status = $this->indexer->get_indexing_status($this->get_post_types());

		if ($this->is_stale_mode($options)) {
			return isset($status['stale']) ? (int) $status['stale'] : 0;
		}

		$scope  = $this->get_entity_scope();
		$posts  = isset($status['unindexed']) ? (int) $status['unindexed'] : 0;
		$topics = isset($status['unindexed_topics']) ? (int) $status['unindexed_topics'] : 0;

		if ('posts' === $scope) {
			return $posts;
		}
		if ('topics' === $scope) {
			return $topics;
		}

		return $posts + $topics;
	}

	/**
	 * @param array $options Run options.
	 * @return bool Whether the run only re-embeds stale posts.
	 */
	private function is_stale_mode(array $options): bool {
		return isset($options['mode']) && 'stale' === $options['mode'];
	}

	/**
	 * @return string[]
	 */
	private function get_post_types(): array {
		return (array) $this->config->get_option('aips_indexer_post_types', array('post'));
	}

	/**
	 * @return string 'all', 'posts' or 'topics'.
	 */
	private function get_entity_scope(): string {
		$scope = (string) $this->config->get_option('aips_indexer_scan_entity_scope', 'all');

		return in_array($scope, array('all', 'posts', 'topics'), true) ? $scope : 'all';
	}

	/**
	 * @param int  $items      Items to embed.
	 * @param int  $daily_rate Sustainable embeddings per day (0 = unlimited).
	 * @param int  $days       Days needed at that rate.
	 * @param bool $stale_only Whether the run only re-embeds stale posts.
	 * @return string
	 */
	private function build_estimate_message(int $items, int $daily_rate, int $days, bool $stale_only): string {
		if ($items <= 0) {
			return $stale_only
				? __('No stale embeddings: every indexed post uses the active model.', 'ai-post-scheduler')
				: __('Everything in the indexing scope is already indexed.', 'ai-post-scheduler');
		}

		if ($daily_rate <= 0) {
			return sprintf(
				/* translators: %s: number of items. */
				_n(
					'%s item will be indexed using one embedding call. No embeddings rate limit is set. It runs in the background and can be paused or stopped at any time.',
					'%s items will be indexed using one embedding call each. No embeddings rate limit is set. It runs in the background and can be paused or stopped at any time.',
					$items,
					'ai-post-scheduler'
				),
				number_format_i18n($items)
			);
		}

		return sprintf(
			/* translators: 1: number of items, 2: embeddings allowed per day, 3: days needed. */
			_n(
				'%1$s item will be indexed using one embedding call. Your limits allow about %2$s per day, so this takes about %3$s day(s). It pauses itself when a quota is reached, resumes automatically, and can be paused or stopped at any time.',
				'%1$s items will be indexed using one embedding call each. Your limits allow about %2$s per day, so this takes about %3$s day(s). It pauses itself when a quota is reached, resumes automatically, and can be paused or stopped at any time.',
				$items,
				'ai-post-scheduler'
			),
			number_format_i18n($items),
			number_format_i18n($daily_rate),
			number_format_i18n(max(1, $days))
		);
	}
}
