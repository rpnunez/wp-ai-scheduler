<?php
/**
 * Review Workflow Service
 *
 * Hooks generation/publish events to keep the review workflow in sync and
 * performs a one-time backfill of existing draft review-queue posts.
 *
 * @package AI_Post_Scheduler
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Review_Workflow_Service {

	/**
	 * WP-Cron hook name for the incremental backfill.
	 */
	const BACKFILL_CRON_HOOK = 'aips_review_workflow_backfill_batch';

	/**
	 * Option that tracks incremental backfill progress.
	 */
	const BACKFILL_STATE_OPTION = 'aips_review_workflow_backfill_state';

	/**
	 * Option that marks the backfill as fully complete.
	 */
	const BACKFILL_DONE_OPTION = 'aips_review_workflow_backfill_done';

	/**
	 * Number of posts to process per cron batch.
	 */
	const BACKFILL_BATCH_SIZE = 50;

	/**
	 * Seconds to wait before scheduling the next backfill batch.
	 */
	const BACKFILL_SCHEDULE_DELAY = 5;

	/**
	 * @var AIPS_Review_Workflow_Repository
	 */
	private $repository;

	public function __construct($repository = null) {
		$this->repository = $repository instanceof AIPS_Review_Workflow_Repository ? $repository : new AIPS_Review_Workflow_Repository();

		add_action('aips_post_generated', array($this, 'handle_post_generated'), 10, 4);
		add_action('aips_post_review_published', array($this, 'handle_post_published'), 10, 1);
		add_action('aips_post_review_deleted', array($this, 'handle_post_deleted'), 10, 2);
		add_action('transition_post_status', array($this, 'handle_transition_post_status'), 10, 3);

		// Register the WP-Cron callback so it fires even during cron/frontend contexts.
		add_action(self::BACKFILL_CRON_HOOK, array($this, 'run_backfill_batch'));

		// On admin_init, only schedule the cron event if the backfill hasn't run yet.
		if (is_admin()) {
			add_action('admin_init', array($this, 'maybe_schedule_backfill'), 20);
		}
	}

	/**
	 * Create/update workflow item when a post is generated.
	 *
	 * @param int        $post_id
	 * @param mixed      $template_or_context
	 * @param int        $history_id
	 * @param mixed|null $context
	 * @return void
	 */
	public function handle_post_generated($post_id, $template_or_context = null, $history_id = 0, $context = null) {
		$post_id   = absint($post_id);
		$history_id = absint($history_id);

		if (!$post_id) {
			return;
		}

		$post = get_post($post_id);
		if (!$post) {
			return;
		}

		if (!in_array($post->post_status, array('draft', 'pending'), true)) {
			return;
		}

		$fields = $history_id ? $this->repository->get_context_fields_from_history($history_id) : array('template_id' => 0, 'author_id' => 0, 'topic_id' => 0);
		$this->repository->get_or_create_item_for_post($post_id, $history_id, $fields);
	}

	/**
	 * Close workflow item when published via review queue.
	 *
	 * @param int $post_id
	 * @return void
	 */
	public function handle_post_published($post_id) {
		$post_id = absint($post_id);
		if (!$post_id) {
			return;
		}

		$item = $this->repository->get_item_row_by_post_id($post_id);
		if ($item) {
			$this->repository->close_item((int) $item->id, 'published');
		}
	}

	/**
	 * Close workflow item when deleted from review queue.
	 *
	 * @param int   $post_id
	 * @param array $meta
	 * @return void
	 */
	public function handle_post_deleted($post_id, $meta = array()) {
		$post_id = absint($post_id);
		if (!$post_id) {
			return;
		}

		$item = $this->repository->get_item_row_by_post_id($post_id);
		if ($item) {
			$this->repository->close_item((int) $item->id, 'archived');
		}
	}

	/**
	 * Sync closed_state when a post is updated outside the workflow UI.
	 *
	 * @param string  $new_status
	 * @param string  $old_status
	 * @param WP_Post $post
	 * @return void
	 */
	public function handle_transition_post_status($new_status, $old_status, $post) {
		if (!($post instanceof WP_Post)) {
			return;
		}

		if ($new_status === $old_status) {
			return;
		}

		$this->repository->sync_closed_state_from_post_status($post->ID);
	}

	/**
	 * Schedule the WP-Cron backfill event if the backfill has not yet completed.
	 *
	 * Called on admin_init – performs only an option-check and a single
	 * wp_schedule_single_event() call so it never blocks the admin request.
	 *
	 * @return void
	 */
	public function maybe_schedule_backfill() {
		if (!current_user_can('manage_options')) {
			return;
		}

		if ((bool) get_option(self::BACKFILL_DONE_OPTION, false)) {
			return;
		}

		// Only schedule if not already queued.
		if (!wp_next_scheduled(self::BACKFILL_CRON_HOOK)) {
			wp_schedule_single_event(time() + self::BACKFILL_SCHEDULE_DELAY, self::BACKFILL_CRON_HOOK);
		}
	}

	/**
	 * Process a single batch of the backfill via WP-Cron.
	 *
	 * Reads the current page from the persisted state option, processes
	 * up to BACKFILL_BATCH_SIZE items, then either reschedules the next
	 * batch or marks the backfill as complete.
	 *
	 * @return void
	 */
	public function run_backfill_batch() {
		if ((bool) get_option(self::BACKFILL_DONE_OPTION, false)) {
			return;
		}

		$state = get_option(self::BACKFILL_STATE_OPTION, array());
		$state = is_array($state) ? $state : array();

		$page = !empty($state['page']) ? max(1, absint($state['page'])) : 1;
		$seen = !empty($state['seen']) ? absint($state['seen']) : 0;

		$repo   = new AIPS_Post_Review_Repository();
		$result = $repo->get_draft_posts(array(
			'page'     => $page,
			'per_page' => self::BACKFILL_BATCH_SIZE,
		));

		$items = !empty($result['items']) && is_array($result['items']) ? $result['items'] : array();

		foreach ($items as $row) {
			$post_id    = !empty($row->post_id) ? absint($row->post_id) : 0;
			$history_id = !empty($row->id) ? absint($row->id) : 0;
			if (!$post_id) {
				continue;
			}

			$this->repository->get_or_create_item_for_post($post_id, $history_id, array(
				'template_id' => !empty($row->template_id) ? absint($row->template_id) : 0,
				'author_id'   => !empty($row->author_id) ? absint($row->author_id) : 0,
				'topic_id'    => !empty($row->topic_id) ? absint($row->topic_id) : 0,
			));
			$seen++;
		}

		if (count($items) === self::BACKFILL_BATCH_SIZE) {
			// More pages may remain – persist progress and schedule the next batch.
			update_option(
				self::BACKFILL_STATE_OPTION,
				array(
					'page' => $page + 1,
					'seen' => $seen,
				),
				false
			);

			wp_schedule_single_event(time() + self::BACKFILL_SCHEDULE_DELAY, self::BACKFILL_CRON_HOOK);
			return;
		}

		// All batches processed – clean up state and mark done.
		delete_option(self::BACKFILL_STATE_OPTION);
		update_option(self::BACKFILL_DONE_OPTION, 1, false);

		/**
		 * Fires after the workflow backfill completes.
		 *
		 * @param int $count Total number of posts backfilled.
		 */
		do_action('aips_review_workflow_backfilled', $seen);
	}
}

