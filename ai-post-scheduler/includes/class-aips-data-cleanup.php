<?php
/**
 * Data Cleanup
 *
 * Listens for native WordPress lifecycle events and removes plugin-specific
 * data that would otherwise become orphaned after the associated WordPress
 * objects (Posts) or plugin objects (Authors) are permanently deleted.
 *
 * This class is intentionally lean: it owns only the hook registrations and
 * the orchestration logic.  All actual SQL is delegated to the relevant
 * Repository classes so that the cleanup logic is kept separate from the
 * individual data-access layers and is easy to extend for future Data Types
 * (Schedules, etc.).
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
 * WordPress object or plugin object is permanently removed.
 *
 * ## Post deletion
 * When a post is permanently deleted (`before_delete_post`), the following
 * plugin records are removed:
 *   - All `aips_history` containers linked to that post ID.
 *   - All `aips_history_log` rows belonging to those containers.
 *   - All `aips_author_topic_logs` rows that reference that post ID.
 *
 * ## Author deletion
 * When an AIPS author is deleted (`aips_before_delete_author`), the following
 * records are removed:
 *   - All `aips_topic_feedback` rows for the author's topics.
 *   - All `aips_author_topic_logs` rows for the author's topics.
 *   - All `aips_author_topics` rows for the author.
 *   - All `aips_history` containers for the author that are NOT associated
 *     with a WordPress post that still exists in `wp_posts`.  Containers
 *     tied to live posts are preserved so that post-generation history
 *     survives the author deletion.
 *   - All `aips_history_log` rows belonging to those deleted containers.
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
	 * @var AIPS_Author_Topics_Repository
	 */
	private $topics_repository;

	/**
	 * @var AIPS_Feedback_Repository
	 */
	private $feedback_repository;

	/**
	 * Wire up dependencies and register WordPress hooks.
	 *
	 * @param AIPS_History_Repository|null           $history_repository    Optional override (useful in tests).
	 * @param AIPS_Author_Topic_Logs_Repository|null $topic_logs_repository Optional override (useful in tests).
	 * @param AIPS_Author_Topics_Repository|null     $topics_repository     Optional override (useful in tests).
	 * @param AIPS_Feedback_Repository|null          $feedback_repository   Optional override (useful in tests).
	 */
	public function __construct(
		$history_repository    = null,
		$topic_logs_repository = null,
		$topics_repository     = null,
		$feedback_repository   = null
	) {
		$this->history_repository    = $history_repository    ?: new AIPS_History_Repository();
		$this->topic_logs_repository = $topic_logs_repository ?: new AIPS_Author_Topic_Logs_Repository();
		$this->topics_repository     = $topics_repository     ?: new AIPS_Author_Topics_Repository();
		$this->feedback_repository   = $feedback_repository   ?: new AIPS_Feedback_Repository();

		add_action('before_delete_post',       array($this, 'on_before_delete_post'),   10, 1);
		add_action('aips_before_delete_author', array($this, 'on_before_delete_author'), 10, 1);
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

	/**
	 * Cascade-delete all plugin data associated with an AIPS author that is
	 * about to be permanently removed.
	 *
	 * Hooked to: `aips_before_delete_author`
	 *
	 * Deletion order:
	 * 1. Collect topic IDs for the author.
	 * 2. Delete topic feedback for those topics.
	 * 3. Delete topic logs for those topics.
	 * 4. Delete the topics themselves.
	 * 5. Collect "deletable" history container IDs: author's containers
	 *    whose associated WP post does NOT exist in wp_posts. Containers
	 *    linked to still-existing posts are intentionally preserved.
	 * 6. Delete history_log rows for those containers.
	 * 7. Delete those history containers.
	 *
	 * @param int $author_id The AIPS author ID being deleted.
	 * @return void
	 */
	public function on_before_delete_author($author_id) {
		$author_id = absint($author_id);

		if (!$author_id) {
			return;
		}

		// Step 1: Collect IDs of all topics belonging to this author.
		$topics    = $this->topics_repository->get_by_author($author_id);
		$topic_ids = array_map(fn($t) => (int) $t->id, $topics ?: array());

		if (!empty($topic_ids)) {
			// Step 2: Delete feedback for those topics.
			$this->feedback_repository->delete_by_topic_ids($topic_ids);

			// Step 3: Delete topic logs for those topics.
			$this->topic_logs_repository->delete_by_topic_ids($topic_ids);
		}

		// Step 4: Delete all topics for this author.
		$this->topics_repository->delete_by_author($author_id);

		// Step 5: Collect history container IDs that are safe to delete —
		// i.e. containers for this author that are NOT tied to a still-existing
		// WordPress post.  Containers with a live post_id are left untouched so
		// that post-generation history survives the author deletion.
		$history_ids = $this->history_repository->get_deletable_ids_by_author_id($author_id);

		if (!empty($history_ids)) {
			// Step 6: Delete history log rows before removing the containers.
			$this->history_repository->delete_logs_by_history_ids($history_ids);

			// Step 7: Delete the history containers.
			$this->history_repository->delete_bulk($history_ids);
		}
	}
}
