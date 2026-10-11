<?php
/**
 * Author Topic Embeddings Process
 *
 * Embeds author topics that have no vector yet (pending, approved and used
 * topics), for one author or all of them. These vectors power topic
 * de-duplication, auto-approval and related-topic suggestions.
 *
 * Replaces the per-author `aips_process_author_embeddings` cron chain for new
 * runs: it runs under the shared manager, so it can be paused, resumed and
 * stopped, waits for embeddings quota instead of failing, and supports a
 * per-run AI call budget.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.13
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Author_Embeddings_Process extends AIPS_Embeddings_Background_Process {

	const KEY = 'author_topic_embeddings';

	/**
	 * @var AIPS_Embeddings_Repository
	 */
	private $embeddings_repo;

	/**
	 * @var int Author for the run being started (0 = every author).
	 */
	private $author_id = 0;

	/**
	 * @param AIPS_Background_Process_Repository|null $repository         Run repository.
	 * @param AIPS_Embeddings_Service|null            $embeddings_service Embeddings service.
	 * @param AIPS_Config|null                        $config             Config.
	 * @param AIPS_Embeddings_Repository|null         $embeddings_repo    Embeddings repository.
	 */
	public function __construct(
		?AIPS_Background_Process_Repository $repository = null,
		?AIPS_Embeddings_Service $embeddings_service = null,
		?AIPS_Config $config = null,
		?AIPS_Embeddings_Repository $embeddings_repo = null
	) {
		parent::__construct($repository, $embeddings_service, $config);

		$this->embeddings_repo = $embeddings_repo ?: $this->embeddings_service->get_embeddings_repository();
	}

	public function get_key(): string {
		return self::KEY;
	}

	public function get_label(): string {
		return __('Author Topic Embeddings', 'ai-post-scheduler');
	}

	public function get_description(): string {
		return __('Generates embeddings for author topics that do not have one yet, used for duplicate detection and related-topic suggestions. Uses AI embedding calls and honors the embeddings rate limits.', 'ai-post-scheduler');
	}

	/**
	 * Start a run.
	 *
	 * @param array $options Optional `author_id` to restrict the run to one author, and `ai_budget`.
	 * @return array|WP_Error
	 */
	public function start(array $options = array()) {
		$this->author_id = isset($options['author_id']) ? absint($options['author_id']) : 0;

		$options['author_id'] = $this->author_id;

		return parent::start($options);
	}

	protected function can_run() {
		if (!$this->embeddings_service->is_enabled()) {
			return new WP_Error('aips_bg_embeddings_disabled', __('The vector embeddings system is disabled in settings.', 'ai-post-scheduler'));
		}

		return true;
	}

	protected function count_remaining(): int {
		return $this->embeddings_repo->get_unindexed_topic_count($this->author_id);
	}

	protected function process_slice(int $cursor, int $limit, array $options): array {
		$author_id = isset($options['author_id']) ? absint($options['author_id']) : 0;
		$ids       = $this->embeddings_repo->get_unindexed_topic_ids($limit, $cursor, $author_id);

		if (empty($ids)) {
			return array('processed' => 0, 'failed' => 0, 'cursor' => $cursor, 'done' => true);
		}

		$limiter    = $this->embeddings_service->get_rate_limiter();
		$success    = 0;
		$failed     = 0;
		$new_cursor = $cursor;
		$stalled    = false;

		foreach ($ids as $topic_id) {
			$result = $this->embeddings_service->compute_topic_embedding($topic_id);

			if (is_wp_error($result)) {
				// Embeddings were switched off mid-run: end the run instead of retrying forever.
				if ($result->get_error_code() === 'embeddings_disabled') {
					return array(
						'processed' => $success,
						'failed'    => $failed,
						'cursor'    => $new_cursor,
						'done'      => false,
						'fatal'     => $result,
					);
				}

				// The limiter has already counted provider failures; a limit or cooldown
				// stops the slice and the next tick re-checks before retrying this topic.
				if (
					$limiter->is_rate_limit_or_exhaustion_error($result)
					|| in_array($result->get_error_code(), AIPS_Embeddings_Rate_Limiter::NON_FAULT_CODES, true)
				) {
					$stalled = true;
					break;
				}

				$failed++;
			} else {
				$success++;
			}

			$new_cursor = max($new_cursor, $topic_id);
		}

		return array(
			'processed' => $success,
			'failed'    => $failed,
			'cursor'    => $new_cursor,
			'done'      => !$stalled && count($ids) < $limit,
			'message'   => $stalled ? __('Waiting for the embeddings rate limit.', 'ai-post-scheduler') : '',
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get_estimate(array $options = array()): array {
		$this->author_id = isset($options['author_id']) ? absint($options['author_id']) : 0;

		$items    = $this->count_remaining();
		$duration = $this->estimate_duration($items);

		if ($items <= 0) {
			$message = __('Every topic already has an embedding.', 'ai-post-scheduler');
		} elseif ($duration['daily_rate'] <= 0) {
			$message = sprintf(
				/* translators: %s: number of topics. */
				_n(
					'%s topic will be embedded using one embedding call. No embeddings rate limit is set. It runs in the background and can be paused or stopped at any time.',
					'%s topics will be embedded using one embedding call each. No embeddings rate limit is set. It runs in the background and can be paused or stopped at any time.',
					$items,
					'ai-post-scheduler'
				),
				number_format_i18n($items)
			);
		} else {
			$message = sprintf(
				/* translators: 1: number of topics, 2: embeddings allowed per day, 3: days needed. */
				_n(
					'%1$s topic will be embedded using one embedding call. Your limits allow about %2$s per day, so this takes about %3$s day(s). It pauses itself when a quota is reached, resumes automatically, and can be paused or stopped at any time.',
					'%1$s topics will be embedded using one embedding call each. Your limits allow about %2$s per day, so this takes about %3$s day(s). It pauses itself when a quota is reached, resumes automatically, and can be paused or stopped at any time.',
					$items,
					'ai-post-scheduler'
				),
				number_format_i18n($items),
				number_format_i18n($duration['daily_rate']),
				number_format_i18n(max(1, $duration['days']))
			);
		}

		return array(
			'items'      => $items,
			'ai_calls'   => $items,
			'days'       => $duration['days'],
			'daily_rate' => $duration['daily_rate'],
			'message'    => $message,
		);
	}
}
