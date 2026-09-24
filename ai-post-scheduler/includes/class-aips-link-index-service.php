<?php
/**
 * Link Index Service
 *
 * Keeps the aips_link_index table in sync with post content: extracts the
 * <a href> links of a post, resolves them to internal/external targets and
 * stores them via AIPS_Link_Index_Repository. Runs on save_post, cleans up
 * on post deletion, and provides a backfill job for existing content.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.4
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Link_Index_Service
 */
class AIPS_Link_Index_Service {

	/**
	 * Bulk batch job type for the backfill.
	 */
	const BACKFILL_JOB_TYPE = 'link_index_backfill';

	/**
	 * Post meta holding the content hash the index was built from.
	 */
	const HASH_META_KEY = '_aips_link_index_hash';

	/**
	 * Option holding the most recent backfill job ID.
	 */
	const BACKFILL_JOB_OPTION = 'aips_link_index_backfill_job';

	/**
	 * Single cron event that processes one scan batch and schedules the next.
	 */
	const SCAN_TICK_HOOK = 'aips_link_index_scan_tick';

	/**
	 * Scan only published posts that have never been indexed.
	 */
	const MODE_MISSING = 'missing';

	/**
	 * Scan published posts modified in the last N days.
	 */
	const MODE_RECENT = 'recent';

	/**
	 * Re-scan every published post.
	 */
	const MODE_ALL = 'all';

	/**
	 * Scan paused by the user; resumable.
	 */
	const STATUS_PAUSED = 'paused';

	/**
	 * Scan cancelled by the user.
	 */
	const STATUS_CANCELLED = 'cancelled';

	/**
	 * Seconds of work allowed per batch before deferring the rest to the next tick.
	 */
	const TICK_TIME_BUDGET = 20;

	/**
	 * @var AIPS_Link_Index_Repository
	 */
	private $repository;

	/**
	 * @var AIPS_Link_Extractor
	 */
	private $extractor;

	/**
	 * @var AIPS_Link_Url_Resolver
	 */
	private $resolver;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * @var AIPS_Bulk_Batch_Job_Store|null
	 */
	private $job_store;

	/**
	 * @param AIPS_Link_Index_Repository|null $repository          Link index repository.
	 * @param AIPS_Link_Extractor|null        $extractor           HTML link extractor.
	 * @param AIPS_Link_Url_Resolver|null     $resolver            URL resolver.
	 * @param AIPS_Config|null                $config              Config.
	 * @param AIPS_Bulk_Batch_Job_Store|null  $job_store           Job store (scans).
	 */
	public function __construct(
		?AIPS_Link_Index_Repository $repository = null,
		?AIPS_Link_Extractor $extractor = null,
		?AIPS_Link_Url_Resolver $resolver = null,
		?AIPS_Config $config = null,
		?AIPS_Bulk_Batch_Job_Store $job_store = null
	) {
		$this->repository          = $repository ?: new AIPS_Link_Index_Repository();
		$this->extractor           = $extractor ?: new AIPS_Link_Extractor();
		$this->resolver            = $resolver ?: new AIPS_Link_Url_Resolver();
		$this->config              = $config ?: AIPS_Config::get_instance();
		$this->job_store           = $job_store;
	}

	/**
	 * @return AIPS_Link_Index_Repository
	 */
	public function get_repository(): AIPS_Link_Index_Repository {
		return $this->repository;
	}

	/**
	 * Whether automatic link indexing is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return (bool) $this->config->get_option('aips_link_index_enabled', true);
	}

	/**
	 * Post types whose links are indexed (registered types only).
	 *
	 * @return string[]
	 */
	public function get_post_types(): array {
		$types = (array) $this->config->get_option('aips_link_index_post_types', array('post', 'page'));
		$types = array_values(array_filter(array_map('sanitize_key', $types), 'post_type_exists'));

		return !empty($types) ? $types : array('post');
	}

	/**
	 * Whether the link index has been built (at least one post scanned).
	 *
	 * Uses the scan marker rather than stored links, so a site whose posts
	 * contain no links yet still counts as indexed.
	 *
	 * @return bool
	 */
	public function is_built(): bool {
		$ids = get_posts(array(
			'post_type'              => $this->get_post_types(),
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'meta_key'               => self::HASH_META_KEY,
			'no_found_rows'          => true,
			'suppress_filters'       => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		));

		return !empty($ids);
	}

	/**
	 * Whether a post's links belong in the index (published, indexed type).
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	public function is_post_in_scope(WP_Post $post): bool {
		return $post->post_status === 'publish' && in_array($post->post_type, $this->get_post_types(), true);
	}

	/**
	 * Build (or refresh) the index rows for one post.
	 *
	 * Out-of-scope posts (drafts, trashed, other types) have their rows
	 * removed. In-scope posts are skipped when their content hash matches the
	 * last indexed version unless $force is true.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $force   Re-index even when the content is unchanged.
	 * @return array{status:string, links:int}|WP_Error status is indexed, unchanged or removed.
	 */
	public function index_post(int $post_id, bool $force = false) {
		$post = get_post($post_id);
		if (!$post instanceof WP_Post) {
			return new WP_Error('aips_link_index_post_not_found', __('Post not found.', 'ai-post-scheduler'));
		}

		if (!$this->is_post_in_scope($post)) {
			$this->remove_post($post_id);
			return array('status' => 'removed', 'links' => 0);
		}

		$hash = md5((string) $post->post_content);
		if (!$force && get_post_meta($post_id, self::HASH_META_KEY, true) === $hash) {
			return array('status' => 'unchanged', 'links' => 0);
		}

		$rows    = $this->build_rows((string) $post->post_content);
		$written = $this->repository->sync_for_source($post_id, $rows);

		update_post_meta($post_id, self::HASH_META_KEY, $hash);

		return array('status' => 'indexed', 'links' => $written);
	}

	/**
	 * Resolve the links in some HTML into link index rows.
	 *
	 * @param string $html Post content.
	 * @return array[] Rows for AIPS_Link_Index_Repository::sync_for_source().
	 */
	public function build_rows(string $html): array {
		$rows = array();

		foreach ($this->extractor->extract($html) as $link) {
			$resolved = $this->resolver->resolve_link($link['href']);
			if ($resolved === null) {
				continue;
			}

			$rows[] = array_merge(
				$resolved,
				array(
					'anchor_text'      => $link['anchor_text'],
					'rel'              => $link['rel'],
					'is_nofollow'      => $link['is_nofollow'],
					'inserted_by_aips' => $link['inserted_by_aips'],
					'position'         => $link['position'],
				)
			);
		}

		return $rows;
	}

	/**
	 * Remove a post's own rows and forget its content hash.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function remove_post(int $post_id): void {
		$this->repository->delete_for_source($post_id);
		delete_post_meta($post_id, self::HASH_META_KEY);
	}

	/**
	 * save_post handler.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function on_post_save($post_id, $post): void {
		if (!$post instanceof WP_Post || !$this->is_enabled()) {
			return;
		}

		if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || $post->post_type === 'revision') {
			return;
		}

		$this->index_post((int) $post_id);
	}

	/**
	 * before_delete_post handler: drop the post's links and turn links that
	 * point at it into broken internal links.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function on_before_delete_post($post_id): void {
		$post_id = (int) $post_id;
		if ($post_id <= 0 || wp_is_post_revision($post_id)) {
			return;
		}

		$this->repository->delete_for_source($post_id);
		$this->repository->clear_target($post_id);
	}

	/**
	 * Published in-scope post IDs a scan should visit.
	 *
	 * @param string $mode One of the MODE_* constants.
	 * @param int    $days Look-back window for MODE_RECENT.
	 * @return int[]
	 */
	public function get_scan_post_ids(string $mode = self::MODE_ALL, int $days = 30): array {
		$query = array(
			'post_type'              => $this->get_post_types(),
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'suppress_filters'       => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		if ($mode === self::MODE_MISSING) {
			$query['meta_query'] = array(
				array(
					'key'     => self::HASH_META_KEY,
					'compare' => 'NOT EXISTS',
				),
			);
		} elseif ($mode === self::MODE_RECENT) {
			$query['date_query'] = array(
				array(
					'column' => 'post_modified_gmt',
					'after'  => gmdate('Y-m-d H:i:s', time() - max(1, $days) * DAY_IN_SECONDS),
				),
			);
		}

		return array_map('intval', (array) get_posts($query));
	}

	/**
	 * Start a background link scan.
	 *
	 * Scans run as a chain of single cron events (SCAN_TICK_HOOK): each tick
	 * indexes one batch, saves its position in the job store and schedules
	 * the next tick after the configured pause, so a scan can be paused,
	 * resumed or cancelled at any batch boundary. Only MODE_ALL forces a
	 * re-parse of unchanged posts; other modes skip content that has not
	 * changed since it was last indexed.
	 *
	 * @param string $mode MODE_MISSING, MODE_RECENT or MODE_ALL.
	 * @param int    $days Look-back window for MODE_RECENT (1 - 3650).
	 * @return array{job_id:string, total:int, mode:string}|WP_Error
	 */
	public function start_backfill(string $mode = self::MODE_MISSING, int $days = 30) {
		if (!in_array($mode, array(self::MODE_MISSING, self::MODE_RECENT, self::MODE_ALL), true)) {
			$mode = self::MODE_MISSING;
		}
		$days = min(3650, max(1, $days));

		$current = $this->get_backfill_status();
		if ($current && in_array($current['status'], array(AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING, self::STATUS_PAUSED), true)) {
			return new WP_Error('aips_link_scan_in_progress', __('A link scan is already running or paused. Resume or cancel it first.', 'ai-post-scheduler'));
		}

		$post_ids = $this->get_scan_post_ids($mode, $days);
		if (empty($post_ids)) {
			$message = ($mode === self::MODE_MISSING)
				? __('Every published post is already in the link index.', 'ai-post-scheduler')
				: __('No published posts match this scan.', 'ai-post-scheduler');
			return new WP_Error('aips_link_index_nothing_to_index', $message);
		}

		$job_store = $this->get_job_store();
		$job_id    = $job_store->create(
			self::BACKFILL_JOB_TYPE,
			$post_ids,
			array(
				'mode' => $mode,
				'days' => $days,
			)
		);
		if (is_wp_error($job_id)) {
			return $job_id;
		}

		$job_store->update_status($job_id, AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING);
		$this->config->set_option(self::BACKFILL_JOB_OPTION, $job_id);
		$this->schedule_tick($job_id, 0);

		return array(
			'job_id' => $job_id,
			'total'  => count($post_ids),
			'mode'   => $mode,
		);
	}

	/**
	 * Cron handler: index the next batch of a running scan.
	 *
	 * @param string $job_id Scan job ID.
	 * @return void
	 */
	public function process_scan_tick($job_id): void {
		$job_id = (string) $job_id;
		if ($job_id === '' || $job_id !== (string) $this->config->get_option(self::BACKFILL_JOB_OPTION, '')) {
			return;
		}

		$job_store = $this->get_job_store();
		$job       = $job_store->get($job_id);
		if (!$job || $job->status !== AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING) {
			return;
		}

		$force   = (isset($job->options['mode']) ? $job->options['mode'] : '') === self::MODE_ALL;
		$offset  = (int) $job->processed;
		$batch   = array_slice((array) $job->items, $offset, $this->get_batch_size());
		$started = microtime(true);
		$done    = 0;

		foreach ($batch as $post_id) {
			try {
				$this->index_post((int) $post_id, $force);
			} catch (Throwable $e) {
				(new AIPS_Logger())->log(sprintf('Link index scan: post %d failed: %s', (int) $post_id, $e->getMessage()), 'warning');
			}
			$done++;

			if ((microtime(true) - $started) > self::TICK_TIME_BUDGET) {
				break;
			}
		}

		$processed = $offset + $done;

		// Re-read the status: the user may have paused or cancelled mid-batch.
		$latest = $job_store->get($job_id);
		$status = $latest ? $latest->status : AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING;

		if ($processed >= (int) $job->total) {
			$job_store->update_status($job_id, AIPS_Bulk_Batch_Job_Store::STATUS_COMPLETED, $processed);
			return;
		}

		$job_store->update_status($job_id, $status, $processed);

		if ($status === AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING) {
			$this->schedule_tick($job_id, $this->get_batch_delay());
		}
	}

	/**
	 * Pause the running scan after its current batch.
	 *
	 * @return bool True when a running scan was paused.
	 */
	public function pause_backfill(): bool {
		return $this->transition(array(AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING), self::STATUS_PAUSED);
	}

	/**
	 * Resume a paused scan from where it stopped.
	 *
	 * @return bool True when a paused scan was resumed.
	 */
	public function resume_backfill(): bool {
		if (!$this->transition(array(self::STATUS_PAUSED), AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING)) {
			return false;
		}

		$this->schedule_tick((string) $this->config->get_option(self::BACKFILL_JOB_OPTION, ''), 0);
		return true;
	}

	/**
	 * Cancel the running or paused scan. Posts already scanned stay indexed.
	 *
	 * @return bool True when a scan was cancelled.
	 */
	public function cancel_backfill(): bool {
		return $this->transition(
			array(AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING, self::STATUS_PAUSED),
			self::STATUS_CANCELLED
		);
	}

	/**
	 * Re-queue a running scan whose next tick went missing (e.g. cron events
	 * were flushed). Safe to call on every status poll.
	 *
	 * @return void
	 */
	public function ensure_scan_scheduled(): void {
		$status = $this->get_backfill_status();
		if (!$status || $status['status'] !== AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING) {
			return;
		}

		if (!wp_next_scheduled(self::SCAN_TICK_HOOK, array($status['job_id']))) {
			$this->schedule_tick($status['job_id'], 0);
		}
	}

	/**
	 * Progress of the most recent scan.
	 *
	 * @return array{job_id:string, status:string, processed:int, total:int, mode:string, days:int}|null Null when no scan has run.
	 */
	public function get_backfill_status(): ?array {
		$job_id = (string) $this->config->get_option(self::BACKFILL_JOB_OPTION, '');
		if ($job_id === '') {
			return null;
		}

		$job = $this->get_job_store()->get($job_id);
		if (!$job) {
			return null;
		}

		return array(
			'job_id'    => $job_id,
			'status'    => (string) $job->status,
			'processed' => (int) $job->processed,
			'total'     => (int) $job->total,
			'mode'      => isset($job->options['mode']) ? (string) $job->options['mode'] : self::MODE_ALL,
			'days'      => isset($job->options['days']) ? (int) $job->options['days'] : 0,
		);
	}

	/**
	 * Posts indexed per scan batch (setting, 10 - 500).
	 *
	 * @return int
	 */
	private function get_batch_size(): int {
		return min(500, max(10, (int) $this->config->get_option('aips_link_index_batch_size', 50)));
	}

	/**
	 * Seconds to wait between scan batches (setting, 0 - 600).
	 *
	 * @return int
	 */
	private function get_batch_delay(): int {
		return min(600, max(0, (int) $this->config->get_option('aips_link_index_batch_delay', 20)));
	}

	/**
	 * Schedule the next scan tick.
	 *
	 * @param string $job_id Scan job ID.
	 * @param int    $delay  Seconds from now.
	 * @return void
	 */
	private function schedule_tick(string $job_id, int $delay): void {
		if ($job_id === '') {
			return;
		}

		$args = array($job_id);
		if (!wp_next_scheduled(self::SCAN_TICK_HOOK, $args)) {
			wp_schedule_single_event(time() + max(0, $delay), self::SCAN_TICK_HOOK, $args);
		}
	}

	/**
	 * Move the current scan between statuses.
	 *
	 * @param string[] $from Allowed current statuses.
	 * @param string   $to   New status.
	 * @return bool
	 */
	private function transition(array $from, string $to): bool {
		$status = $this->get_backfill_status();
		if (!$status || !in_array($status['status'], $from, true)) {
			return false;
		}

		$this->get_job_store()->update_status($status['job_id'], $to);

		if ($to !== AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING) {
			wp_clear_scheduled_hook(self::SCAN_TICK_HOOK, array($status['job_id']));
		}

		return true;
	}

	/**
	 * @return AIPS_Bulk_Batch_Job_Store
	 */
	private function get_job_store(): AIPS_Bulk_Batch_Job_Store {
		if ($this->job_store === null) {
			$this->job_store = new AIPS_Bulk_Batch_Job_Store();
		}
		return $this->job_store;
	}
}

