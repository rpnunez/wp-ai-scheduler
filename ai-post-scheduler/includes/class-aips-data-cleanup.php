<?php
/**
 * Data Cleanup
 *
 * Listens for native WordPress lifecycle events and removes plugin-specific
 * data that would otherwise become orphaned after the associated WordPress
 * objects (currently Posts) are permanently deleted.
 *
 * This class is intentionally lean: it owns only the hook registrations and
 * the orchestration logic.  All actual SQL is delegated to the relevant
 * Repository classes so that the cleanup logic is kept separate from the
 * individual data-access layers and is easy to extend for future Data Types
 * (Authors, Schedules, etc.).
 *
 * @package AI_Post_Scheduler
 * @since 1.7.1
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Data_Cleanup
 *
 * Registers WordPress hooks that cascade-delete plugin data whenever a
 * native WordPress object is permanently removed.
 *
 * ## Post deletion
 * When a post is permanently deleted (`before_delete_post`), the following
 * plugin records are removed:
 *   - All `aips_history` containers linked to that post ID.
 *   - All `aips_history_log` rows belonging to those containers.
 *   - All `aips_author_topic_logs` rows that reference that post ID.
 */
class AIPS_Data_Cleanup {

	/**
	 * @var AIPS_History_Repository
	 */
	private $history_repository;

	/**
	 * @var AIPS_Author_Topic_Logs_Repository
	 */
	private $topic_logs_repository;

	/**
	 * Wire up dependencies and register WordPress hooks.
	 *
	 * @param AIPS_History_Repository|null           $history_repository    Optional override (useful in tests).
	 * @param AIPS_Author_Topic_Logs_Repository|null $topic_logs_repository Optional override (useful in tests).
	 */
	public function __construct(
		$history_repository = null,
		$topic_logs_repository = null
	) {
		$this->history_repository    = $history_repository    ?: new AIPS_History_Repository();
		$this->topic_logs_repository = $topic_logs_repository ?: new AIPS_Author_Topic_Logs_Repository();

		add_action('before_delete_post', array($this, 'on_before_delete_post'), 10, 1);
	}

	/**
	 * Cascade-delete all plugin data associated with a WordPress post that is
	 * about to be permanently removed.
	 *
	 * Hooked to: `before_delete_post`
	 *
	 * Deletion order:
	 * 1. Collect history container IDs for the post.
	 * 2. Delete all history_log rows for those containers.
	 * 3. Delete the history containers themselves.
	 * 4. Delete any author_topic_logs rows referencing the post ID.
	 *
	 * @param int $post_id The ID of the post being permanently deleted.
	 * @return void
	 */
	public function on_before_delete_post($post_id) {
		$post_id = absint($post_id);

		if (!$post_id) {
			return;
		}

		// Step 1: Collect the IDs of all history containers linked to this post.
		$history_ids = $this->history_repository->get_ids_by_post_id($post_id);

		// Step 2: Delete history log rows for those containers before removing
		// the containers themselves (preserves referential integrity).
		if (!empty($history_ids)) {
			$this->history_repository->delete_logs_by_history_ids($history_ids);
		}

		// Step 3: Delete the history containers.
		$this->history_repository->delete_by_post_id($post_id);

		// Step 4: Delete author_topic_log rows that recorded post generation
		// for this post (the post_id column on that table).
		$this->topic_logs_repository->delete_by_post_id($post_id);
	}
}
