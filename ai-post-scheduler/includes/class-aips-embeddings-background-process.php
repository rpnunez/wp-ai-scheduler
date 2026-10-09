<?php
/**
 * Embeddings Background Process
 *
 * Base class for managed processes that make embedding calls. It supplies the
 * shared quota rules: wait out a provider cooldown, never start a slice the
 * daily / weekly / monthly limits cannot finish, keep a share of each quota
 * free for other callers, and measure a slice's AI calls from the limiter's
 * usage history.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.13
 */

if (!defined('ABSPATH')) {
	exit;
}

abstract class AIPS_Embeddings_Background_Process extends AIPS_Managed_Background_Process {

	/**
	 * Share of each quota left free for other callers (new-post indexing, manual
	 * actions) while a bulk job runs. Applied when the "quota pause" indexer
	 * setting is on.
	 *
	 * The queue worker pauses itself once usage reaches 90% of a quota, so a bulk job
	 * has to stop earlier than that: at 80%, the 80-90% band stays usable by the queue
	 * (new posts), and the last 10% by manual actions.
	 */
	const QUOTA_RESERVE_RATIO = 0.20;

	/**
	 * @var AIPS_Embeddings_Service
	 */
	protected $embeddings_service;

	/**
	 * @var AIPS_Config
	 */
	protected $config;

	/**
	 * @param AIPS_Background_Process_Repository|null $repository         Run repository.
	 * @param AIPS_Embeddings_Service|null            $embeddings_service Embeddings service.
	 * @param AIPS_Config|null                        $config             Config.
	 */
	public function __construct(
		?AIPS_Background_Process_Repository $repository = null,
		?AIPS_Embeddings_Service $embeddings_service = null,
		?AIPS_Config $config = null
	) {
		parent::__construct($repository);

		$this->config             = $config ?: AIPS_Config::get_instance();
		$this->embeddings_service = $embeddings_service ?: new AIPS_Embeddings_Service();
	}

	public function uses_ai(): bool {
		return true;
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
				/* translators: %s: how long until the job resumes, for example "3 hours". */
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

	/**
	 * Share of each quota held back for other callers.
	 *
	 * @return float
	 */
	protected function get_reserve_ratio(): float {
		return (bool) $this->config->get_option('aips_indexer_quota_pause_enabled', true) ? self::QUOTA_RESERVE_RATIO : 0.0;
	}

	/**
	 * Calls per day the configured limits sustain, and the days needed for $items calls.
	 *
	 * @param int $items Embedding calls to make.
	 * @return array{daily_rate:int, days:int} daily_rate 0 means no limit applies.
	 */
	protected function estimate_duration(int $items): array {
		$stats = $this->embeddings_service->get_rate_limiter()->get_usage_stats();
		$rates = array();

		if (!empty($stats['enabled'])) {
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

		return array(
			'daily_rate' => $daily_rate,
			'days'       => $daily_rate > 0 ? (int) ceil($items / $daily_rate) : 0,
		);
	}
}
