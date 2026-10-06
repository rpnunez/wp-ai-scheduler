<?php
/**
 * Auto-link Run Service
 *
 * Bulk internal linking for posts that need inbound links. A run visits
 * every orphan (or every post below the "Suggest Links" threshold), finds
 * inbound suggestions with AIPS_Inbound_Links_Service, and lets
 * AIPS_Autolink_Policy decide per suggestion:
 *
 *  - apply  → inserted immediately (only when Bulk Auto-Linking is enabled
 *             in Settings and the run is not a dry run),
 *  - review → left pending in the review queue,
 *  - skip   → discarded.
 *
 * Runs are a chain of single cron events (TICK_HOOK), so they can be paused,
 * resumed and cancelled between batches. Every inserted link records the run
 * ID, so a whole run can be undone.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.5
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Autolink_Run_Service
 */
class AIPS_Autolink_Run_Service {

	/**
	 * Job store type for runs.
	 */
	const JOB_TYPE = 'autolink_run';

	/**
	 * Cron hook processing one run batch.
	 */
	const TICK_HOOK = 'aips_autolink_run_tick';

	/**
	 * Option holding the current (most recent) run state.
	 */
	const CURRENT_OPTION = 'aips_autolink_current_run';

	/**
	 * Option holding summaries of finished runs.
	 */
	const HISTORY_OPTION = 'aips_autolink_run_history';

	/**
	 * Finished runs kept in history.
	 */
	const HISTORY_LIMIT = 20;

	/**
	 * Target posts processed per tick.
	 */
	const TARGETS_PER_TICK = 5;

	/**
	 * Seconds of work allowed per tick.
	 */
	const TICK_TIME_BUDGET = 20;

	/**
	 * Suggestions generated per target.
	 */
	const SUGGESTIONS_PER_TARGET = 10;

	/**
	 * Scope: posts with no inbound links.
	 */
	const SCOPE_ORPHANS = 'orphans';

	/**
	 * Scope: posts below the "Suggest Links" threshold.
	 */
	const SCOPE_LOW = 'low';

	/**
	 * Scope of the small runs made when an AIPS post is published.
	 */
	const SCOPE_PUBLISH = 'publish';

	/**
	 * Scope: fix a silo (members → pillar, see AIPS_Silo_Service).
	 */
	const SCOPE_SILO = 'silo';

	/**
	 * @var AIPS_Inbound_Links_Service
	 */
	private $inbound;

	/**
	 * @var AIPS_Link_Index_Service
	 */
	private $link_index;

	/**
	 * @var AIPS_Internal_Links_Repository
	 */
	private $links_repo;

	/**
	 * @var AIPS_Autolink_Policy
	 */
	private $policy;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * @var AIPS_Bulk_Batch_Job_Store
	 */
	private $job_store;

	/**
	 * @param AIPS_Inbound_Links_Service|null     $inbound    Inbound suggestions.
	 * @param AIPS_Link_Index_Service|null        $link_index Link index.
	 * @param AIPS_Internal_Links_Repository|null $links_repo Suggestions repository.
	 * @param AIPS_Autolink_Policy|null           $policy     Auto-link policy.
	 * @param AIPS_Config|null                    $config     Config.
	 * @param AIPS_Bulk_Batch_Job_Store|null      $job_store  Job store.
	 */
	public function __construct(
		?AIPS_Inbound_Links_Service $inbound = null,
		?AIPS_Link_Index_Service $link_index = null,
		?AIPS_Internal_Links_Repository $links_repo = null,
		?AIPS_Autolink_Policy $policy = null,
		?AIPS_Config $config = null,
		?AIPS_Bulk_Batch_Job_Store $job_store = null
	) {
		$container        = AIPS_Container::get_instance();
		$this->link_index = $link_index ?: ($container->has(AIPS_Link_Index_Service::class) ? $container->make(AIPS_Link_Index_Service::class) : new AIPS_Link_Index_Service());
		$this->inbound    = $inbound ?: ($container->has(AIPS_Inbound_Links_Service::class) ? $container->make(AIPS_Inbound_Links_Service::class) : new AIPS_Inbound_Links_Service(null, $this->link_index));
		$this->links_repo = $links_repo ?: new AIPS_Internal_Links_Repository();
		$this->policy     = $policy ?: new AIPS_Autolink_Policy();
		$this->config     = $config ?: AIPS_Config::get_instance();
		$this->job_store  = $job_store ?: new AIPS_Bulk_Batch_Job_Store();
	}

	/**
	 * Start a run.
	 *
	 * @param string $scope   SCOPE_ORPHANS or SCOPE_LOW.
	 * @param bool   $dry_run Only create suggestions for review, even when auto-linking is enabled.
	 * @return array|WP_Error Current run state.
	 */
	public function start(string $scope = self::SCOPE_ORPHANS, bool $dry_run = false) {
		$current = $this->get_current();
		if ($current && in_array($current['status'], array(AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING, AIPS_Link_Index_Service::STATUS_PAUSED), true)) {
			return new WP_Error('aips_autolink_run_in_progress', __('An auto-link run is already running or paused. Resume or cancel it first.', 'ai-post-scheduler'));
		}

		if (!$this->link_index->is_built()) {
			return new WP_Error('aips_autolink_no_index', __('Build the link index first so auto-linking knows which posts need links.', 'ai-post-scheduler'));
		}

		$scope   = ($scope === self::SCOPE_LOW) ? self::SCOPE_LOW : self::SCOPE_ORPHANS;
		$below   = ($scope === self::SCOPE_LOW) ? (int) apply_filters('aips_inbound_suggest_below', AIPS_Inbound_Links_Service::SUGGEST_BELOW_INBOUND) : 1;
		$targets = $this->link_index->get_repository()->get_post_ids_with_inbound_below($this->link_index->get_post_types(), $below);

		if (empty($targets)) {
			return new WP_Error('aips_autolink_nothing_to_do', __('No posts need inbound links right now.', 'ai-post-scheduler'));
		}

		$job_id = $this->job_store->create(self::JOB_TYPE, $targets, array('scope' => $scope));
		if (is_wp_error($job_id)) {
			return $job_id;
		}
		$this->job_store->update_status($job_id, AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING);

		$state = array(
			'job_id'      => $job_id,
			'scope'       => $scope,
			'apply'       => !$dry_run && $this->policy->is_enabled(),
			'dry_run'     => $dry_run,
			'user_id'     => get_current_user_id(),
			'started_at'  => time(),
			'finished_at' => 0,
			'status'      => AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING,
			'processed'   => 0,
			'total'       => count($targets),
			'applied'     => 0,
			'review'      => 0,
			'skipped'     => 0,
			'errors'      => 0,
			'reverted'    => false,
			'source_new'  => array(),
		);
		$this->save_current($state);
		$this->schedule_tick($job_id, 0);

		return $this->public_state($state);
	}

	/**
	 * Cron handler: process the next batch of target posts.
	 *
	 * @param string $job_id Run ID.
	 * @return void
	 */
	public function process_tick($job_id): void {
		$state = $this->get_raw_current();
		if (!$state || (string) $job_id !== $state['job_id']) {
			return;
		}

		$job = $this->job_store->get($state['job_id']);
		if (!$job || $job->status !== AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING) {
			return;
		}

		// Act as the admin who started the run: content saved from cron is
		// otherwise filtered by kses as an anonymous user, stripping embeds.
		$previous_user = get_current_user_id();
		if (!empty($state['user_id'])) {
			wp_set_current_user((int) $state['user_id']);
		}
		add_filter('aips_content_indexer_skip_post_save', '__return_true');

		$offset  = (int) $job->processed;
		$batch   = array_slice((array) $job->items, $offset, self::TARGETS_PER_TICK);
		$started = microtime(true);
		$done    = 0;

		try {
			foreach ($batch as $target_id) {
				$this->process_target((int) $target_id, $state);
				$done++;

				if ((microtime(true) - $started) > self::TICK_TIME_BUDGET) {
					break;
				}
			}
		} finally {
			remove_filter('aips_content_indexer_skip_post_save', '__return_true');
			wp_set_current_user($previous_user);
		}

		$processed          = $offset + $done;
		$state['processed'] = $processed;

		$latest = $this->job_store->get($state['job_id']);
		$status = $latest ? $latest->status : AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING;

		if ($processed >= (int) $job->total) {
			$this->job_store->update_status($state['job_id'], AIPS_Bulk_Batch_Job_Store::STATUS_COMPLETED, $processed);
			$state['status']      = AIPS_Bulk_Batch_Job_Store::STATUS_COMPLETED;
			$state['finished_at'] = time();
			$this->save_current($state);
			$this->archive($state);
			return;
		}

		$this->job_store->update_status($state['job_id'], $status, $processed);
		$state['status'] = $status;
		$this->save_current($state);

		if ($status === AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING) {
			$this->schedule_tick($state['job_id'], min(600, max(0, (int) $this->config->get_option('aips_link_index_batch_delay', 20))));
		}
	}

	/**
	 * Run inbound linking for a few targets right away (no cron ticks) and
	 * record it in the run history, so it can be undone like any other run.
	 *
	 * Used for generation-time linking. Does not touch the current run slot,
	 * so it can happen while a bulk run is going.
	 *
	 * @param int[]  $target_ids Posts that should receive links.
	 * @param bool   $apply      Insert links the policy approves (false = suggestions only).
	 * @param int    $user_id    User to act as (kses: must be able to post unfiltered HTML).
	 * @param string $scope      Scope label for the history.
	 * @param array  $extra      Extra fields stored with the run (e.g. post_id, post_title).
	 *                           Two keys change the run and are not stored:
	 *                           'only_sources' (source ID => similarity) limits
	 *                           link sources; 'ignore_target_cap' lifts the
	 *                           per-target inbound cap.
	 * @return array Public run state.
	 */
	public function run_now(array $target_ids, bool $apply, int $user_id, string $scope = self::SCOPE_PUBLISH, array $extra = array()): array {
		$target_ids = array_values(array_filter(array_map('absint', $target_ids)));

		$state = array_merge($extra, array(
			'job_id'      => wp_generate_uuid4(), // Fits aips_internal_links.batch_id (varchar 36).
			'scope'       => $scope,
			'apply'       => $apply,
			'dry_run'     => !$apply,
			'user_id'     => $user_id,
			'started_at'  => time(),
			'finished_at' => 0,
			'status'      => AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING,
			'processed'   => 0,
			'total'       => count($target_ids),
			'applied'     => 0,
			'review'      => 0,
			'skipped'     => 0,
			'errors'      => 0,
			'reverted'    => false,
			'source_new'  => array(),
		));

		$previous_user = get_current_user_id();
		if ($user_id > 0) {
			wp_set_current_user($user_id);
		}
		add_filter('aips_content_indexer_skip_post_save', '__return_true');

		try {
			foreach ($target_ids as $target_id) {
				$this->process_target($target_id, $state);
				$state['processed']++;
			}
		} finally {
			remove_filter('aips_content_indexer_skip_post_save', '__return_true');
			wp_set_current_user($previous_user);
		}

		unset($state['only_sources'], $state['ignore_target_cap']);
		$state['status']      = AIPS_Bulk_Batch_Job_Store::STATUS_COMPLETED;
		$state['finished_at'] = time();
		$this->archive($state);

		return $this->public_state($state);
	}

	/**
	 * Pause the running run after its current batch.
	 *
	 * @return bool
	 */
	public function pause(): bool {
		return $this->transition(array(AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING), AIPS_Link_Index_Service::STATUS_PAUSED);
	}

	/**
	 * Resume a paused run.
	 *
	 * @return bool
	 */
	public function resume(): bool {
		if (!$this->transition(array(AIPS_Link_Index_Service::STATUS_PAUSED), AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING)) {
			return false;
		}
		$state = $this->get_raw_current();
		$this->schedule_tick($state['job_id'], 0);
		return true;
	}

	/**
	 * Cancel the running or paused run. Links already inserted stay (use undo).
	 *
	 * @return bool
	 */
	public function cancel(): bool {
		if (!$this->transition(array(AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING, AIPS_Link_Index_Service::STATUS_PAUSED), AIPS_Link_Index_Service::STATUS_CANCELLED)) {
			return false;
		}
		$state                = $this->get_raw_current();
		$state['finished_at'] = time();
		$this->save_current($state);
		$this->archive($state);
		return true;
	}

	/**
	 * Undo every link a run inserted.
	 *
	 * @param string $job_id Run ID.
	 * @return array{reverted:int, conflicts:int}|WP_Error
	 */
	public function undo_run(string $job_id) {
		$current = $this->get_raw_current();
		if ($current && $current['job_id'] === $job_id && in_array($current['status'], array(AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING, AIPS_Link_Index_Service::STATUS_PAUSED), true)) {
			return new WP_Error('aips_autolink_run_active', __('Cancel the run before undoing it.', 'ai-post-scheduler'));
		}

		$ids = $this->links_repo->get_ids_by_batch($job_id, 'inserted');
		if (empty($ids)) {
			return new WP_Error('aips_autolink_nothing_to_undo', __('This run has no inserted links left to undo.', 'ai-post-scheduler'));
		}

		add_filter('aips_content_indexer_skip_post_save', '__return_true');
		$reverted  = 0;
		$conflicts = 0;

		try {
			foreach ($ids as $id) {
				if (is_wp_error($this->inbound->revert($id))) {
					$conflicts++;
				} else {
					$reverted++;
				}
			}
		} finally {
			remove_filter('aips_content_indexer_skip_post_save', '__return_true');
		}

		$this->mark_reverted($job_id);

		/**
		 * Fires after a run was undone, so features that key off a run's scope
		 * (e.g. AIPS_Publish_Linking_Service, whose one-time "already linked"
		 * flag would otherwise never clear) can react.
		 *
		 * @param array $run Archived run state (job_id, scope, post_id when set, ...).
		 */
		do_action('aips_autolink_run_undone', $this->find_history($job_id));

		return array(
			'reverted'  => $reverted,
			'conflicts' => $conflicts,
		);
	}

	/**
	 * A run's archived record by job ID, or an empty array.
	 *
	 * @param string $job_id Run ID.
	 * @return array
	 */
	private function find_history(string $job_id): array {
		foreach ((array) $this->config->get_option(self::HISTORY_OPTION, array()) as $run) {
			if (isset($run['job_id']) && $run['job_id'] === $job_id) {
				return $run;
			}
		}

		return array();
	}

	/**
	 * Re-queue a running run whose next tick went missing.
	 *
	 * @return void
	 */
	public function ensure_scheduled(): void {
		$state = $this->get_raw_current();
		if ($state && $state['status'] === AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING && !wp_next_scheduled(self::TICK_HOOK, array($state['job_id']))) {
			$this->schedule_tick($state['job_id'], 0);
		}
	}

	/**
	 * Current (most recent) run for the UI, or null.
	 *
	 * @return array|null
	 */
	public function get_current(): ?array {
		$state = $this->get_raw_current();
		return $state ? $this->public_state($state) : null;
	}

	/**
	 * Finished runs, newest first.
	 *
	 * @return array[]
	 */
	public function get_history(): array {
		return array_values(array_map(array($this, 'public_state'), (array) $this->config->get_option(self::HISTORY_OPTION, array())));
	}

	/**
	 * Generate suggestions for one target and act on the policy decision.
	 *
	 * @param int   $target_id Target post ID.
	 * @param array $state     Run state (updated in place).
	 * @return void
	 */
	private function process_target(int $target_id, array &$state): void {
		$only   = isset($state['only_sources']) ? (array) $state['only_sources'] : array();
		$result = $this->inbound->generate_for_target($target_id, $only ? count($only) : self::SUGGESTIONS_PER_TARGET, $only);
		if (is_wp_error($result)) {
			$state['skipped']++;
			return;
		}

		$index_repo = $this->link_index->get_repository();
		$added      = 0;

		// One eligible suggestion may have several rows in the same list (rare,
		// but cheaper to guard than to assume); dedupe before the batch query.
		$eligible_ids = array();
		foreach ($result['suggestions'] as $suggestion) {
			$source_id = (int) $suggestion['source_id'];
			if ($suggestion['status'] === 'pending' && (!$only || isset($only[$source_id]))) {
				$eligible_ids[$source_id] = true;
			}
		}
		$counts = $eligible_ids ? $index_repo->get_counts_for_posts(array_keys($eligible_ids)) : array();

		foreach ($result['suggestions'] as $suggestion) {
			if ($suggestion['status'] !== 'pending') {
				continue;
			}

			$source_id = (int) $suggestion['source_id'];
			if ($only && !isset($only[$source_id])) {
				continue;
			}

			$decision = $this->policy->evaluate(
				array(
					'source_post_id' => $source_id,
					'target_post_id' => $target_id,
					'confidence'     => $suggestion['confidence'] / 100,
					'has_anchor'     => $suggestion['anchor'] !== '',
				),
				array(
					'source_internal_links' => isset($counts[$source_id]) ? $counts[$source_id]['outbound'] : 0,
					'source_new_links'      => isset($state['source_new'][$source_id]) ? (int) $state['source_new'][$source_id] : 0,
					'target_new_inbound'    => empty($state['ignore_target_cap']) ? $added : 0,
				)
			);

			if ($decision['decision'] === AIPS_Autolink_Policy::DECISION_SKIP) {
				$this->links_repo->delete($suggestion['id']);
				$state['skipped']++;
				continue;
			}

			if ($decision['decision'] === AIPS_Autolink_Policy::DECISION_APPLY && $state['apply']) {
				$applied = $this->inbound->apply($suggestion['id'], $state['job_id']);
				if (is_wp_error($applied)) {
					$state['errors']++;
					continue;
				}
				$state['applied']++;
				$state['source_new'][$source_id] = (isset($state['source_new'][$source_id]) ? (int) $state['source_new'][$source_id] : 0) + 1;
				$added++;
				continue;
			}

			$state['review']++;
		}
	}

	/**
	 * Move the current run between statuses.
	 *
	 * @param string[] $from Allowed current statuses.
	 * @param string   $to   New status.
	 * @return bool
	 */
	private function transition(array $from, string $to): bool {
		$state = $this->get_raw_current();
		if (!$state) {
			return false;
		}

		$job    = $this->job_store->get($state['job_id']);
		$status = $job ? $job->status : $state['status'];
		if (!in_array($status, $from, true)) {
			return false;
		}

		$this->job_store->update_status($state['job_id'], $to);
		$state['status'] = $to;
		$this->save_current($state);

		if ($to !== AIPS_Bulk_Batch_Job_Store::STATUS_PROCESSING) {
			wp_clear_scheduled_hook(self::TICK_HOOK, array($state['job_id']));
		}

		return true;
	}

	/**
	 * Add a finished run to history (newest first, capped).
	 *
	 * @param array $state Run state.
	 * @return void
	 */
	private function archive(array $state): void {
		unset($state['source_new']);
		$history = (array) $this->config->get_option(self::HISTORY_OPTION, array());
		$history = array_filter($history, function ($run) use ($state) {
			return isset($run['job_id']) && $run['job_id'] !== $state['job_id'];
		});
		array_unshift($history, $state);
		$this->config->set_option(self::HISTORY_OPTION, array_slice(array_values($history), 0, self::HISTORY_LIMIT), false);
	}

	/**
	 * Flag a run as undone in the current state and history.
	 *
	 * @param string $job_id Run ID.
	 * @return void
	 */
	private function mark_reverted(string $job_id): void {
		$history = (array) $this->config->get_option(self::HISTORY_OPTION, array());
		foreach ($history as &$run) {
			if (isset($run['job_id']) && $run['job_id'] === $job_id) {
				$run['reverted'] = true;
			}
		}
		unset($run);
		$this->config->set_option(self::HISTORY_OPTION, $history, false);

		$state = $this->get_raw_current();
		if ($state && $state['job_id'] === $job_id) {
			$state['reverted'] = true;
			$this->save_current($state);
		}
	}

	/**
	 * @return array|null
	 */
	private function get_raw_current(): ?array {
		$state = $this->config->get_option(self::CURRENT_OPTION, array());
		return (is_array($state) && !empty($state['job_id'])) ? $state : null;
	}

	/**
	 * @param array $state Run state.
	 * @return void
	 */
	private function save_current(array $state): void {
		$this->config->set_option(self::CURRENT_OPTION, $state, false);
	}

	/**
	 * Run state without internal bookkeeping, for the UI.
	 *
	 * @param array $state Raw state.
	 * @return array
	 */
	private function public_state(array $state): array {
		unset($state['source_new'], $state['user_id']);
		return $state;
	}

	/**
	 * @param string $job_id Run ID.
	 * @param int    $delay  Seconds from now.
	 * @return void
	 */
	private function schedule_tick(string $job_id, int $delay): void {
		if ($job_id !== '' && !wp_next_scheduled(self::TICK_HOOK, array($job_id))) {
			wp_schedule_single_event(time() + max(0, $delay), self::TICK_HOOK, array($job_id));
		}
	}
}
