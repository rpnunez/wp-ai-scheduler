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

	/**
	 * @inheritDoc
	 */
	public function get_snapshot(): array {
		$queue   = $this->indexer->get_queue_status();
		$pending = (int) $queue['pending_count'];
		$status  = self::STATUS_IDLE;
		$message = '';

		if ($this->indexer->is_queue_paused()) {
			$status  = 'paused';
			$message = __('Paused by an administrator.', 'ai-post-scheduler');
		} elseif ($pending > 0) {
			$cooldown = $this->limiter->get_cooldown_status();
			$stats    = $this->limiter->get_usage_stats();

			if ($cooldown['is_paused']) {
				$status  = 'cooldown';
				$message = (string) $cooldown['reason'];
			} elseif (!empty($stats['is_rate_limited'])) {
				$status  = 'waiting_quota';
				$message = __('Embeddings quota reached. The queue resumes automatically.', 'ai-post-scheduler');
			} else {
				$status = 'running';
			}
		}

		if ($message === '' && $pending > 0) {
			$message = sprintf(
				/* translators: %s: number of queued items. */
				_n('%s item queued.', '%s items queued.', $pending, 'ai-post-scheduler'),
				number_format_i18n($pending)
			);
		}

		return $this->make_snapshot(array(
			'status'  => $status,
			'total'   => $pending,
			'message' => $message,
		));
	}

	/**
	 * @inheritDoc
	 */
	public function start(array $options = array()) {
		$queue = $this->indexer->get_queue_status();

		if ((int) $queue['pending_count'] <= 0) {
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

		$queue = $this->indexer->get_queue_status();
		if ((int) $queue['pending_count'] <= 0) {
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
		$queue = $this->indexer->get_queue_status();
		if ((int) $queue['pending_count'] <= 0 && !$this->indexer->is_queue_paused()) {
			return false;
		}

		$this->indexer->clear_queue();
		return true;
	}
}
