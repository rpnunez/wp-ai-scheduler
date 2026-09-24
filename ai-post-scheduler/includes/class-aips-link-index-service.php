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
	 * @var AIPS_Batch_Queue_Service|null
	 */
	private $batch_queue_service;

	/**
	 * @param AIPS_Link_Index_Repository|null $repository          Link index repository.
	 * @param AIPS_Link_Extractor|null        $extractor           HTML link extractor.
	 * @param AIPS_Link_Url_Resolver|null     $resolver            URL resolver.
	 * @param AIPS_Config|null                $config              Config.
	 * @param AIPS_Bulk_Batch_Job_Store|null  $job_store           Job store (backfill).
	 * @param AIPS_Batch_Queue_Service|null   $batch_queue_service Batch dispatcher (backfill).
	 */
	public function __construct(
		?AIPS_Link_Index_Repository $repository = null,
		?AIPS_Link_Extractor $extractor = null,
		?AIPS_Link_Url_Resolver $resolver = null,
		?AIPS_Config $config = null,
		?AIPS_Bulk_Batch_Job_Store $job_store = null,
		?AIPS_Batch_Queue_Service $batch_queue_service = null
	) {
		$this->repository          = $repository ?: new AIPS_Link_Index_Repository();
		$this->extractor           = $extractor ?: new AIPS_Link_Extractor();
		$this->resolver            = $resolver ?: new AIPS_Link_Url_Resolver();
		$this->config              = $config ?: AIPS_Config::get_instance();
		$this->job_store           = $job_store;
		$this->batch_queue_service = $batch_queue_service;
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
	 * IDs of every published post in scope, for the backfill.
	 *
	 * @return int[]
	 */
	public function get_backfill_post_ids(): array {
		$ids = get_posts(array(
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
		));

		return array_map('intval', (array) $ids);
	}

	/**
	 * Queue a background job that (re)indexes every published post in scope.
	 *
	 * @return array{job_id:string, total:int}|WP_Error
	 */
	public function start_backfill() {
		$post_ids = $this->get_backfill_post_ids();
		if (empty($post_ids)) {
			return new WP_Error('aips_link_index_nothing_to_index', __('There are no published posts to index.', 'ai-post-scheduler'));
		}

		$job_store = $this->get_job_store();
		$job_id    = $job_store->create(self::BACKFILL_JOB_TYPE, $post_ids, array('history_type' => self::BACKFILL_JOB_TYPE));
		if (is_wp_error($job_id)) {
			return $job_id;
		}

		$dispatch = $this->get_batch_queue_service()->dispatch_generic(
			AIPS_Bulk_Batch_Processor::HOOK,
			count($post_ids),
			time(),
			array($job_id),
			(string) AIPS_Correlation_ID::get()
		);

		if (is_wp_error($dispatch)) {
			$job_store->mark_failed($job_id);
			return $dispatch;
		}

		$this->config->set_option(self::BACKFILL_JOB_OPTION, $job_id);

		return array(
			'job_id' => $job_id,
			'total'  => count($post_ids),
		);
	}

	/**
	 * Progress of the most recent backfill job.
	 *
	 * @return array{job_id:string, status:string, processed:int, total:int}|null Null when no backfill has run.
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
		);
	}

	/**
	 * Bulk batch strategy for BACKFILL_JOB_TYPE.
	 *
	 * Returns the post ID for every handled item (indexed, unchanged or
	 * removed) so skips never fail the job; only a missing post is an error.
	 *
	 * @param mixed $post_id Item from the job (a post ID).
	 * @return int|WP_Error
	 */
	public function process_backfill_item($post_id) {
		$result = $this->index_post((int) $post_id, true);

		return is_wp_error($result) ? $result : (int) $post_id;
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

	/**
	 * @return AIPS_Batch_Queue_Service
	 */
	private function get_batch_queue_service(): AIPS_Batch_Queue_Service {
		if ($this->batch_queue_service === null) {
			$this->batch_queue_service = new AIPS_Batch_Queue_Service();
		}
		return $this->batch_queue_service;
	}
}
