<?php
/**
 * Post Lifecycle Cache Invalidator
 *
 * Bridges native WordPress post lifecycle events to repository caches whose
 * reads join wp_posts, so those reads never serve stale post state.
 *
 * @package AI_Post_Scheduler
 * @since 3.6.6
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Post_Lifecycle_Cache_Invalidator
 *
 * Some repositories cache reads that JOIN wp_posts on post_status / post_type
 * (AIPS_Embeddings_Repository::count_indexed_for_types(),
 * AIPS_Relationships_Repository::get_related(), ...). Repository writes alone
 * cannot keep those fresh: a post being trashed, published, re-typed, or
 * permanently deleted changes their results without touching the plugin
 * tables. This class listens to the WordPress hooks that fire for every such
 * change and calls invalidate_post_dependent_reads() on each target
 * repository, which bumps only its wp_posts-dependent tag — reads over the
 * plugin table alone keep their cache.
 *
 * Targets are resolved lazily on the first relevant event, because the hooks
 * are registered in every request context (admin, REST, cron, frontend) and
 * most requests never transition a post.
 */
class AIPS_Post_Lifecycle_Cache_Invalidator {

	/**
	 * Repositories invalidated by default.
	 *
	 * @var string[]
	 */
	const DEFAULT_TARGETS = array(
		'AIPS_Embeddings_Repository',
		'AIPS_Relationships_Repository',
	);

	/**
	 * Targets as supplied: repository instances and/or class names.
	 *
	 * @var array<int, object|string>
	 */
	private $targets;

	/**
	 * Resolved repository instances (null until first use).
	 *
	 * @var object[]|null
	 */
	private $resolved = null;

	/**
	 * @param object|array<int, object|string>|null $targets A repository instance, a list of
	 *        instances and/or class names, or null for DEFAULT_TARGETS. Class names are
	 *        resolved through the container on first use.
	 */
	public function __construct($targets = null) {
		if (null === $targets) {
			$targets = self::DEFAULT_TARGETS;
		} elseif (!is_array($targets)) {
			$targets = array($targets);
		}

		$this->targets = array_values($targets);
	}

	/**
	 * Register the WordPress hooks that drive invalidation.
	 *
	 * transition_post_status fires on every wp_insert_post()/wp_update_post()
	 * (even when the status is unchanged), so it also covers post_type changes.
	 * deleted_post covers permanent deletion, which never fires save_post and
	 * leaves orphaned plugin rows that the wp_posts join must now exclude.
	 *
	 * @return void
	 */
	public function register() {
		add_action('transition_post_status', array($this, 'on_transition_post_status'), 10, 3);
		add_action('deleted_post', array($this, 'on_deleted_post'), 10, 2);
	}

	/**
	 * Handle a post status transition.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Previous post status.
	 * @param WP_Post $post       Post object.
	 * @return void
	 */
	public function on_transition_post_status($new_status, $old_status, $post) {
		if (!$this->is_relevant_post($post)) {
			return;
		}

		$this->invalidate($new_status === $old_status ? 'post_saved' : 'post_status_transition');
	}

	/**
	 * Handle a permanent post deletion.
	 *
	 * @param int          $post_id Deleted post ID.
	 * @param WP_Post|null $post    Post object as it was before deletion (WP >= 5.5).
	 * @return void
	 */
	public function on_deleted_post($post_id, $post = null) {
		if ($post instanceof WP_Post && !$this->is_relevant_post($post)) {
			return;
		}

		$this->invalidate('post_deleted');
	}

	/**
	 * Invalidate every target's wp_posts-dependent reads.
	 *
	 * @param string $reason Invalidation reason for cache telemetry.
	 * @return void
	 */
	private function invalidate($reason) {
		foreach ($this->resolve_targets() as $repository) {
			$repository->invalidate_post_dependent_reads($reason);
		}
	}

	/**
	 * Resolve class-name targets to repository instances once.
	 *
	 * @return object[]
	 */
	private function resolve_targets() {
		if (null !== $this->resolved) {
			return $this->resolved;
		}

		$this->resolved = array();
		foreach ($this->targets as $target) {
			if (is_string($target)) {
				if (!class_exists($target)) {
					continue;
				}
				$target = AIPS_Container::get_instance()->makeIfExists($target, $target);
			}

			if (is_object($target) && method_exists($target, 'invalidate_post_dependent_reads')) {
				$this->resolved[] = $target;
			}
		}

		return $this->resolved;
	}

	/**
	 * Whether a post can affect reads that join wp_posts.
	 *
	 * Revisions and autosaves are excluded: they are never indexed and never
	 * appear in the joined result sets, so their churn must not evict caches.
	 *
	 * @param mixed $post Post object.
	 * @return bool
	 */
	private function is_relevant_post($post) {
		if (!($post instanceof WP_Post)) {
			return false;
		}

		if ('revision' === $post->post_type) {
			return false;
		}

		if (wp_is_post_autosave($post)) {
			return false;
		}

		return true;
	}
}
