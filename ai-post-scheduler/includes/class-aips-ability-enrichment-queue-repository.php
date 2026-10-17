<?php
/**
 * Ability Enrichment Queue Repository
 *
 * Queue of generated posts waiting for ability enrichment. A post is queued by
 * a post meta row whose value is the user who triggered the generation (0 for
 * cron), so no schema change is needed and a post can only be queued once.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Ability_Enrichment_Queue_Repository {

	const META_KEY = '_aips_ability_enrichment_pending';

	/**
	 * Queue a post. Queueing a post again keeps it queued once and records the newest user.
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id User who triggered the generation (0 for cron).
	 * @return bool
	 */
	public function enqueue(int $post_id, int $user_id): bool {
		return false !== update_post_meta($post_id, self::META_KEY, (string) max(0, $user_id));
	}

	/**
	 * Remove a post from the queue.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function dequeue(int $post_id): void {
		delete_post_meta($post_id, self::META_KEY);
	}

	/**
	 * Number of queued posts.
	 *
	 * @return int
	 */
	public function count_pending(): int {
		global $wpdb;

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", self::META_KEY)
		);
	}

	/**
	 * Oldest queued posts first.
	 *
	 * @param int $limit Maximum number of rows.
	 * @return array<int,array{post_id:int,user_id:int}>
	 */
	public function get_pending(int $limit): array {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY post_id ASC LIMIT %d",
				self::META_KEY,
				max(1, $limit)
			)
		);

		$pending = array();

		foreach ((array) $rows as $row) {
			$pending[] = array(
				'post_id' => (int) $row->post_id,
				'user_id' => (int) $row->meta_value,
			);
		}

		return $pending;
	}
}
