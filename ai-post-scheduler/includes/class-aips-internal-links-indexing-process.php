<?php
/**
 * Internal Links Indexing Process
 *
 * The "Index Posts" job on the Internal Links page: embeds every unindexed post
 * in the configured indexing scope, one slice per cron tick, within the
 * embeddings rate limits and cooldown.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.11
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Internal_Links_Indexing_Process extends AIPS_Managed_Background_Process {

	const KEY = 'internal_links_indexing';

	/**
	 * Share of each quota left free for other callers (new-post indexing, manual
	 * actions) while this bulk job runs. Applied when the "quota pause"
	 * indexer setting is on.
	 */
	const QUOTA_RESERVE_RATIO = 0.10;

	/**
	 * @var AIPS_Internal_Links_Service
	 */
	private $service;

	/**
	 * @var AIPS_Embeddings_Service
	 */
	private $embeddings_service;

	/**
	 * @var AIPS_Config
	 */
	private $config;

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
		parent::__construct($repository);

		$this->config             = $config ?: AIPS_Config::get_instance();
		$this->embeddings_service = $embeddings_service ?: new AIPS_Embeddings_Service();
		$this->service            = $service ?: new AIPS_Internal_Links_Service(null, null, $this->embeddings_service);
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

	public function uses_ai(): bool {
		return true;
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

	protected function get_batch_size(): int {
		return max(1, min(50, (int) $this->config->get_option('aips_indexer_batch_size', 10)));
	}

	protected function check_limits(): ?array {
		$limiter  = $this->embeddings_service->get_rate_limiter();
		$cooldown = $limiter->get_cooldown_status();

		if ($cooldown['is_paused']) {
			return array(
				'status'   => AIPS_Background_Process_Repository::STATUS_COOLDOWN,
				'retry_at' => (int) $cooldown['paused_until'] + 5,
				'message'  => (string) $cooldown['reason'],
			);
		}

		$ratio = $this->get_reserve_ratio();
		if ($limiter->get_remaining_allowance($ratio) > 0) {
			return null;
		}

		$retry_at = $limiter->get_next_allowance_timestamp($ratio);
		if ($retry_at <= time()) {
			$retry_at = time() + HOUR_IN_SECONDS;
		}

		return array(
			'status'   => AIPS_Background_Process_Repository::STATUS_WAITING_QUOTA,
			'retry_at' => $retry_at,
			'message'  => sprintf(
				/* translators: %s: how long until indexing resumes, for example "3 hours". */
				__('Embeddings quota reached. Resumes automatically in about %s.', 'ai-post-scheduler'),
				human_time_diff(time(), $retry_at)
			),
		);
	}

	protected function get_allowance(): int {
		return $this->embeddings_service->get_rate_limiter()->get_remaining_allowance($this->get_reserve_ratio());
	}

	protected function count_ai_calls(): int {
		return count($this->embeddings_service->get_rate_limiter()->get_usage_history());
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
	public function get_estimate(): array {
		$items   = $this->count_remaining();
		$limiter = $this->embeddings_service->get_rate_limiter();
		$stats   = $limiter->get_usage_stats();

		$rates = array();
		if ($stats['enabled']) {
			if ($stats['daily_limit'] > 0) {
				$rates[] = $stats['daily_limit'];
			}
			if ($stats['weekly_limit'] > 0) {
				$rates[] = $stats['weekly_limit'] / 7;
			}
			if ($stats['monthly_limit'] > 0) {
				$rates[] = $stats['monthly_limit'] / 30;
			}
		}

		$daily_rate = !empty($rates) ? max(1, (int) floor(min($rates))) : 0;
		$days       = $daily_rate > 0 ? (int) ceil($items / $daily_rate) : 0;

		return array(
			'items'      => $items,
			'ai_calls'   => $items,
			'days'       => $days,
			'daily_rate' => $daily_rate,
			'message'    => $this->build_estimate_message($items, $daily_rate, $days),
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

	/**
	 * @return float Share of each quota held back for other callers.
	 */
	private function get_reserve_ratio(): float {
		return (bool) $this->config->get_option('aips_indexer_quota_pause_enabled', true) ? self::QUOTA_RESERVE_RATIO : 0.0;
	}
}
