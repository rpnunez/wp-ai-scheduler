<?php
/**
 * Content Indexer Queue Process
 *
 * Exposes the debounced "index newly published posts" queue (see
 * AIPS_Content_Indexer_Service::process_pending_indexer_queue) so it can be
 * observed, paused, resumed and cleared like any other background process.
 * The queue schedules itself; this adapter only reads its state and flips the
 * pause flag.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.11
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Content_Indexer_Queue_Process extends AIPS_Background_Process_Base {

	const KEY = 'content_indexer_queue';

	/**
	 * @var AIPS_Content_Indexer_Service
	 */
	private $indexer;

	/**
	 * @var AIPS_Embeddings_Rate_Limiter
	 */
	private $limiter;

	/**
	 * @param AIPS_Content_Indexer_Service|null $indexer Indexer service.
	 * @param AIPS_Embeddings_Rate_Limiter|null $limiter Rate limiter.
	 */
	public function __construct(?AIPS_Content_Indexer_Service $indexer = null, ?AIPS_Embeddings_Rate_Limiter $limiter = null) {
		$container     = AIPS_Container::get_instance();
		$this->indexer = $indexer ?: $container->make(AIPS_Content_Indexer_Service::class);
		$this->limiter = $limiter ?: new AIPS_Embeddings_Rate_Limiter();
	}

	public function get_key(): string {
		return self::KEY;
	}

	public function get_label(): string {
		return __('Content Indexer Queue', 'ai-post-scheduler');
	}

	public function get_description(): string {
		return __('Indexes newly published and updated posts and topics in small slices. Pausing it keeps the queued items for later.', 'ai-post-scheduler');
	}

	public function uses_ai(): bool {
		return true;
	}

	public function tracks_ai_calls(): bool {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function get_snapshot(): array {
		$queue       = $this->indexer->get_queue_status();
		$pending     = (int) $queue['pending_count'];
		$in_flight   = isset($queue['in_flight_count']) ? (int) $queue['in_flight_count'] : 0;
		$outstanding = isset($queue['outstanding_count']) ? (int) $queue['outstanding_count'] : ($pending + $in_flight);
		$open_run    = isset($queue['open_run']) ? $queue['open_run'] : null;
		$latest_run  = isset($queue['latest_run']) ? $queue['latest_run'] : null;
		$enabled     = (bool) AIPS_Config::get_instance()->get_option('aips_embeddings_enabled', true);

		$status      = self::STATUS_IDLE;
		$message     = '';
		$next_run_at = 0;

		if ($this->indexer->is_queue_paused()) {
			$status  = 'paused';
			$message = __('Paused by an administrator.', 'ai-post-scheduler');
		} elseif (!$enabled && ($outstanding > 0 || $open_run)) {
			$status  = 'paused';
			$message = __('The vector embeddings system is disabled in settings.', 'ai-post-scheduler');
		} elseif ($outstanding > 0 || $open_run) {
			$cooldown    = $this->limiter->get_cooldown_status();
			$stats       = $this->limiter->get_usage_stats();
			$quota_pause = (bool) AIPS_Config::get_instance()->get_option('aips_indexer_quota_pause_enabled', true);

			if ($cooldown['is_paused']) {
				$status      = 'cooldown';
				$message     = (string) $cooldown['reason'];
				$next_run_at = (int) $cooldown['paused_until'] + 5;
			} elseif (
				!empty($stats['is_rate_limited'])
				|| ($quota_pause && $this->limiter->is_approaching_quota(0.90))
				|| ($open_run && $open_run->status === AIPS_Background_Process_Repository::STATUS_WAITING_QUOTA && (int) $open_run->next_run_at > time())
			) {
				$status      = 'waiting_quota';
				$message     = __('Embeddings quota reached. The queue resumes automatically.', 'ai-post-scheduler');
				$next_run_at = $this->limiter->get_next_allowance_timestamp($quota_pause ? 0.10 : 0.0);
				if ($next_run_at <= 0 && $open_run && (int) $open_run->next_run_at > time()) {
					$next_run_at = (int) $open_run->next_run_at;
				}
			} else {
				$status = 'running';
			}
		}

		if ($open_run) {
			$total         = max((int) $open_run->total, (int) $open_run->processed + $outstanding);
			$processed     = (int) $open_run->processed;
			$failed        = (int) $open_run->failed;
			$ai_calls_used = (int) $open_run->ai_calls_used;
			$started_at    = (int) $open_run->started_at;
			$updated_at    = (int) $open_run->updated_at;
			$run_id        = (int) $open_run->id;

			if ($message === '') {
				if (!empty($open_run->message)) {
					$message = (string) $open_run->message;
				} elseif ($outstanding > 0) {
					$message = sprintf(
						/* translators: %s: number of queued items. */
						_n('%s item queued.', '%s items queued.', $outstanding, 'ai-post-scheduler'),
						number_format_i18n($outstanding)
					);
				}
			}

			return $this->make_snapshot(array(
				'status'          => $status,
				'processed'       => $processed,
				'total'           => $total,
				'failed'          => $failed,
				'pending'         => $pending,
				'in_flight'       => $in_flight,
				'ai_calls_used'   => $ai_calls_used,
				'ai_calls_budget' => (int) $open_run->ai_calls_budget,
				'message'         => $message,
				'next_run_at'     => $next_run_at > 0 ? $next_run_at : (int) $open_run->next_run_at,
				'started_at'      => $started_at,
				'updated_at'      => $updated_at,
				'run_id'          => $run_id,
			));
		}

		// No open session: if a finished session exists in history, keep it visible until another session starts.
		if ($latest_run && $outstanding === 0 && $status === self::STATUS_IDLE) {
			return $this->make_snapshot(array(
				'status'           => self::STATUS_IDLE,
				'last_status'      => (string) $latest_run->status,
				'last_finished_at' => (int) $latest_run->finished_at,
				'processed'        => (int) $latest_run->processed,
				'total'            => (int) $latest_run->total,
				'failed'           => (int) $latest_run->failed,
				'pending'          => 0,
				'in_flight'        => 0,
				'ai_calls_used'    => (int) $latest_run->ai_calls_used,
				'message'          => (string) $latest_run->message,
				'started_at'       => (int) $latest_run->started_at,
				'updated_at'       => (int) $latest_run->updated_at,
				'run_id'           => (int) $latest_run->id,
			));
		}

		if ($message === '' && $outstanding > 0) {
			$message = sprintf(
				/* translators: %s: number of queued items. */
				_n('%s item queued.', '%s items queued.', $outstanding, 'ai-post-scheduler'),
				number_format_i18n($outstanding)
			);
		}

		return $this->make_snapshot(array(
			'status'      => $status,
			'total'       => $outstanding,
			'pending'     => $pending,
			'in_flight'   => $in_flight,
			'message'     => $message,
			'next_run_at' => $next_run_at,
		));
	}

	/**
	 * @inheritDoc
	 */
	public function start(array $options = array()) {
		if (!AIPS_Config::get_instance()->get_option('aips_embeddings_enabled', true)) {
			return new WP_Error('aips_bg_embeddings_disabled', __('The vector embeddings system is disabled in settings.', 'ai-post-scheduler'));
		}

		$queue       = $this->indexer->get_queue_status();
		$outstanding = isset($queue['outstanding_count']) ? (int) $queue['outstanding_count'] : (int) $queue['pending_count'];

		if ($outstanding <= 0) {
			return new WP_Error('aips_bg_nothing_to_do', __('There is nothing left to process.', 'ai-post-scheduler'));
		}

		$this->indexer->set_queue_paused(false);
		$this->indexer->schedule_queue_worker(time() + 1);

		return $this->get_snapshot();
	}

	public function pause(): bool {
		if ($this->indexer->is_queue_paused()) {
			return false;
		}

		$queue       = $this->indexer->get_queue_status();
		$outstanding = isset($queue['outstanding_count']) ? (int) $queue['outstanding_count'] : (int) $queue['pending_count'];
		if ($outstanding <= 0 && empty($queue['open_run'])) {
			return false;
		}

		$this->indexer->set_queue_paused(true);
		return true;
	}

	public function resume(): bool {
		if (!$this->indexer->is_queue_paused()) {
			return false;
		}

		$this->indexer->set_queue_paused(false);
		$this->indexer->schedule_queue_worker(time() + 1);
		return true;
	}

	public function cancel(): bool {
		$queue       = $this->indexer->get_queue_status();
		$outstanding = isset($queue['outstanding_count']) ? (int) $queue['outstanding_count'] : (int) $queue['pending_count'];
		if ($outstanding <= 0 && !$this->indexer->is_queue_paused() && empty($queue['open_run'])) {
			return false;
		}

		$this->indexer->clear_queue();
		return true;
	}

	/**
	 * Ensure the background worker is scheduled if items remain queued and the queue is not paused.
	 *
	 * @return void
	 */
	public function ensure_scheduled(): void {
		if ($this->indexer->is_queue_paused() || !AIPS_Config::get_instance()->get_option('aips_embeddings_enabled', true)) {
			return;
		}

		$queue       = $this->indexer->get_queue_status();
		$outstanding = isset($queue['outstanding_count']) ? (int) $queue['outstanding_count'] : (int) $queue['pending_count'];
		if ($outstanding > 0 && !$this->indexer->is_queue_worker_scheduled()) {
			$this->indexer->schedule_queue_worker(time() + 5);
		}
	}
}
