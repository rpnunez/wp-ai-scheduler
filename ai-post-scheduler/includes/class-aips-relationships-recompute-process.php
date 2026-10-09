<?php
/**
 * Relationships Recompute Process
 *
 * Fills in (or refreshes) the "related posts" neighbours of already-indexed
 * posts, a batch at a time, using AIPS_Relationship_Builder. Makes no AI
 * calls: it only compares vectors that are already stored, so it is safe to
 * run on a large site and can be paused or stopped at any time.
 *
 * Typical use: after the Internal Links indexing job has embedded your posts
 * (it stores vectors only), run this to build their related-post lists.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.12
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Relationships_Recompute_Process extends AIPS_Managed_Background_Process {

	const KEY = 'relationships_recompute';

	/**
	 * Only posts that have no related-post rows yet.
	 */
	const MODE_MISSING = 'missing';

	/**
	 * Every indexed post, replacing its existing rows.
	 */
	const MODE_ALL = 'all';

	/**
	 * Seconds one slice may spend comparing vectors.
	 */
	const SLICE_TIME_BUDGET = 12.0;

	/**
	 * @var AIPS_Relationship_Builder
	 */
	private $builder;

	/**
	 * @var AIPS_Relationships_Repository
	 */
	private $relationships_repo;

	/**
	 * @var string Mode for the run being started (missing or all).
	 */
	private $mode = self::MODE_MISSING;

	/**
	 * @param AIPS_Background_Process_Repository|null $repository         Run repository.
	 * @param AIPS_Relationship_Builder|null          $builder            Relationship builder.
	 * @param AIPS_Relationships_Repository|null      $relationships_repo Relationships repository.
	 */
	public function __construct(
		?AIPS_Background_Process_Repository $repository = null,
		?AIPS_Relationship_Builder $builder = null,
		?AIPS_Relationships_Repository $relationships_repo = null
	) {
		parent::__construct($repository);

		$this->relationships_repo = $relationships_repo ?: new AIPS_Relationships_Repository();
		$this->builder            = $builder ?: new AIPS_Relationship_Builder(null, $this->relationships_repo);
	}

	public function get_key(): string {
		return self::KEY;
	}

	public function get_label(): string {
		return __('Related Posts Recompute', 'ai-post-scheduler');
	}

	public function get_description(): string {
		return __('Builds each indexed post\'s list of related posts from the stored vectors. CPU only: no AI calls.', 'ai-post-scheduler');
	}

	/**
	 * Start a run.
	 *
	 * @param array $options Optional `mode`: 'missing' (default) or 'all' to rebuild every post.
	 * @return array|WP_Error
	 */
	public function start(array $options = array()) {
		$this->mode = (isset($options['mode']) && $options['mode'] === self::MODE_ALL) ? self::MODE_ALL : self::MODE_MISSING;

		$options['mode'] = $this->mode;

		return parent::start($options);
	}

	protected function count_remaining(): int {
		return $this->relationships_repo->count_source_post_ids(
			$this->builder->get_post_types(),
			'publish',
			$this->mode === self::MODE_MISSING
		);
	}

	protected function get_batch_size(): int {
		return $this->builder->get_batch_size_for_budget(self::SLICE_TIME_BUDGET);
	}

	protected function get_delay(): int {
		return 2;
	}

	protected function process_slice(int $cursor, int $limit, array $options): array {
		$only_missing = !(isset($options['mode']) && $options['mode'] === self::MODE_ALL);

		// A pass over a large library can outlast the web server's default limit. Ask for
		// more time where the host allows it; where it does not, the watchdog tick in
		// AIPS_Managed_Background_Process recovers the run.
		if (function_exists('set_time_limit')) {
			@set_time_limit(120); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,Squiz.PHP.DiscouragedFunctions.Discouraged
		}

		$ids = $this->relationships_repo->get_source_post_ids(
			$this->builder->get_post_types(),
			'publish',
			$cursor,
			$limit,
			$only_missing
		);

		if (empty($ids)) {
			return array('processed' => 0, 'failed' => 0, 'cursor' => $cursor, 'done' => true);
		}

		$saved  = $this->builder->compute_for_posts($ids);
		$failed = count($ids) - count($saved);

		return array(
			'processed' => count($saved),
			'failed'    => $failed,
			'cursor'    => max($ids),
			'done'      => count($ids) < $limit,
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get_estimate(array $options = array()): array {
		$this->mode = (isset($options['mode']) && $options['mode'] === self::MODE_ALL) ? self::MODE_ALL : self::MODE_MISSING;
		$items      = $this->count_remaining();
		$seconds    = $this->builder->estimate_seconds($items);
		$minutes    = (int) max(1, ceil($seconds / 60));

		return array(
			'items'      => $items,
			'ai_calls'   => 0,
			'days'       => 0,
			'daily_rate' => 0,
			'message'    => $items <= 0
				? __('Every indexed post already has its related posts.', 'ai-post-scheduler')
				: sprintf(
					/* translators: 1: number of posts, 2: estimated minutes. */
					_n(
						'Related posts will be computed for %1$s post, taking roughly %2$s minute(s) of server time. No AI calls are used. It runs in the background and can be paused or stopped at any time.',
						'Related posts will be computed for %1$s posts, taking roughly %2$s minute(s) of server time. No AI calls are used. It runs in the background and can be paused or stopped at any time.',
						$items,
						'ai-post-scheduler'
					),
					number_format_i18n($items),
					number_format_i18n($minutes)
				),
		);
	}
}
