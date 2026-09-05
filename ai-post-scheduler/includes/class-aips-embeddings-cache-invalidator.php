<?php
/**
 * Embeddings Cache Invalidator
 *
 * Bridges native WordPress post lifecycle events to the embeddings repository
 * cache so reads that join wp_posts never serve stale post state.
 *
 * @package AI_Post_Scheduler
 * @since 3.6.6
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Embeddings_Cache_Invalidator
 *
 * AIPS_Embeddings_Repository caches `count_indexed_for_types()` (and any future
 * read tagged AIPS_Embeddings_Repository::CACHE_TAG_POSTS) under a tag that
 * repository writes alone cannot keep fresh: a post being trashed, published,
 * re-typed, or permanently deleted changes those results without touching the
 * embeddings table. This class listens to the WordPress hooks that fire for
 * every such change and bumps that tag.
 *
 * Only `embeddings_posts` is bumped — reads over the embeddings table alone are
 * unaffected by post transitions and keep their cache.
 */
class AIPS_Embeddings_Cache_Invalidator {

	/**
	 * @var AIPS_Embeddings_Repository
	 */
	private $repository;

	/**
	 * @param AIPS_Embeddings_Repository|null $repository Embeddings repository.
	 */
	public function __construct(?AIPS_Embeddings_Repository $repository = null) {
		if (null === $repository) {
			$container  = AIPS_Container::get_instance();
			$repository = $container->has(AIPS_Embeddings_Repository::class)
				? $container->make(AIPS_Embeddings_Repository::class)
				: new AIPS_Embeddings_Repository();
		}

		$this->repository = $repository;
	}

	/**
	 * Register the WordPress hooks that drive invalidation.
	 *
	 * transition_post_status fires on every wp_insert_post()/wp_update_post()
	 * (even when the status is unchanged), so it also covers post_type changes.
	 * deleted_post covers permanent deletion, which never fires save_post and
	 * leaves an orphaned embedding row that the wp_posts join must now exclude.
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

		$this->repository->invalidate_post_dependent_reads(
			$new_status === $old_status ? 'post_saved' : 'post_status_transition'
		);
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

		$this->repository->invalidate_post_dependent_reads('post_deleted');
	}

	/**
	 * Whether a post can affect embeddings reads that join wp_posts.
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
