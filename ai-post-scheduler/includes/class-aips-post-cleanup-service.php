<?php
/**
 * Post Cleanup Service
 *
 * Coordinates deletion of AI Post Scheduler data when a WordPress post is permanently deleted.
 * Handles cleanup of:
 * - Embeddings (wp_aips_embeddings) and relationships (wp_aips_relationships)
 * - Internal link suggestions (wp_aips_internal_links)
 * - Link index (wp_aips_link_index) and link click logs (wp_aips_link_clicks)
 * - History containers (wp_aips_history) and child event logs (wp_aips_history_log)
 * - Redirect rules (wp_aips_redirects)
 * - Author topic generation logs (wp_aips_author_topic_logs)
 *
 * @package AI_Post_Scheduler
 * @since 3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Post_Cleanup_Service
 */
class AIPS_Post_Cleanup_Service {

	/**
	 * Flag to temporarily suppress history container and log deletion (e.g. during regeneration).
	 *
	 * @var bool
	 */
	private static $suppress_history_deletion = false;

	/**
	 * @var AIPS_Embeddings_Repository
	 */
	private $embeddings_repository;

	/**
	 * @var AIPS_Relationships_Repository
	 */
	private $relationships_repository;

	/**
	 * @var AIPS_Internal_Links_Repository
	 */
	private $internal_links_repository;

	/**
	 * @var AIPS_Link_Index_Service
	 */
	private $link_index_service;

	/**
	 * @var AIPS_Link_Click_Tracking_Service
	 */
	private $link_click_tracking_service;

	/**
	 * @var AIPS_History_Repository_Interface
	 */
	private $history_repository;

	/**
	 * @var AIPS_Redirects_Service
	 */
	private $redirects_service;

	/**
	 * @var AIPS_Author_Topic_Logs_Repository
	 */
	private $author_topic_logs_repository;

	/**
	 * @var AIPS_Logger_Interface|null
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Embeddings_Repository|null        $embeddings_repository
	 * @param AIPS_Relationships_Repository|null     $relationships_repository
	 * @param AIPS_Internal_Links_Repository|null    $internal_links_repository
	 * @param AIPS_Link_Index_Service|null           $link_index_service
	 * @param AIPS_Link_Click_Tracking_Service|null  $link_click_tracking_service
	 * @param AIPS_History_Repository_Interface|null $history_repository
	 * @param AIPS_Redirects_Service|null            $redirects_service
	 * @param AIPS_Author_Topic_Logs_Repository|null $author_topic_logs_repository
	 * @param AIPS_Logger_Interface|null             $logger
	 */
	public function __construct(
		?AIPS_Embeddings_Repository $embeddings_repository = null,
		?AIPS_Relationships_Repository $relationships_repository = null,
		?AIPS_Internal_Links_Repository $internal_links_repository = null,
		?AIPS_Link_Index_Service $link_index_service = null,
		?AIPS_Link_Click_Tracking_Service $link_click_tracking_service = null,
		?AIPS_History_Repository_Interface $history_repository = null,
		?AIPS_Redirects_Service $redirects_service = null,
		?AIPS_Author_Topic_Logs_Repository $author_topic_logs_repository = null,
		?AIPS_Logger_Interface $logger = null
	) {
		$container = AIPS_Container::get_instance();

		$this->embeddings_repository        = $embeddings_repository ?: $container->makeIfExists(AIPS_Embeddings_Repository::class, AIPS_Embeddings_Repository::class);
		$this->relationships_repository     = $relationships_repository ?: $container->makeIfExists(AIPS_Relationships_Repository::class, AIPS_Relationships_Repository::class);
		$this->internal_links_repository    = $internal_links_repository ?: $container->makeIfExists(AIPS_Internal_Links_Repository::class, AIPS_Internal_Links_Repository::class);
		$this->link_index_service           = $link_index_service ?: $container->makeIfExists(AIPS_Link_Index_Service::class);
		$this->link_click_tracking_service  = $link_click_tracking_service ?: $container->makeIfExists(AIPS_Link_Click_Tracking_Service::class);
		$this->history_repository           = $history_repository ?: $container->makeIfExists(AIPS_History_Repository_Interface::class, AIPS_History_Repository::class);
		$this->redirects_service            = $redirects_service ?: $container->makeIfExists(AIPS_Redirects_Service::class, AIPS_Redirects_Service::class);
		$this->author_topic_logs_repository = $author_topic_logs_repository ?: $container->makeIfExists(AIPS_Author_Topic_Logs_Repository::class, AIPS_Author_Topic_Logs_Repository::class);
		$this->logger                       = $logger ?: $container->makeIfExists(AIPS_Logger_Interface::class);
	}

	/**
	 * Run a callback with history deletion suppressed.
	 *
	 * Useful during post regeneration where the old draft post is deleted
	 * via wp_delete_post() while preserving the current history container.
	 *
	 * @param callable $callback Operation to perform.
	 * @return mixed Return value of the callback.
	 */
	public static function without_history_deletion(callable $callback) {
		$previous = self::$suppress_history_deletion;
		self::$suppress_history_deletion = true;
		try {
			return $callback();
		} finally {
			self::$suppress_history_deletion = $previous;
		}
	}

	/**
	 * Check whether history deletion is currently suppressed.
	 *
	 * @return bool
	 */
	public static function is_history_deletion_suppressed(): bool {
		return self::$suppress_history_deletion;
	}

	/**
	 * Hook handler for WordPress before_delete_post.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return array
	 */
	public function on_before_delete_post($post_id): array {
		$post_id = (int) $post_id;
		if ($post_id <= 0 || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
			return array();
		}

		/**
		 * Filter whether to clean up AIPS data for a deleting post.
		 *
		 * @param bool $should_cleanup Whether to proceed. Default true.
		 * @param int  $post_id        Post ID.
		 */
		if (!apply_filters('aips_should_cleanup_post', true, $post_id)) {
			return array();
		}

		/**
		 * Fires before AIPS post cleanup begins.
		 *
		 * @param int $post_id Post ID.
		 */
		do_action('aips_before_post_cleanup', $post_id);

		$summary = array(
			'post_id'       => $post_id,
			'embeddings'    => 0,
			'relationships' => 0,
			'links'         => 0,
			'history'       => 0,
			'redirects'     => array('disabled' => 0, 'deleted' => 0),
			'topic_logs'    => 0,
		);

		// 1. Purge embeddings and relationship matrix entries.
		if ($this->embeddings_repository) {
			$res = $this->embeddings_repository->delete_by_post_id($post_id);
			$summary['embeddings'] = false !== $res ? (int) $res : 0;
		}

		if ($this->relationships_repository) {
			$res = $this->relationships_repository->delete_for_object('post', $post_id);
			$summary['relationships'] = false !== $res ? (int) $res : 0;
		}

		// 2. Purge internal link suggestions.
		if ($this->internal_links_repository) {
			$summary['links'] = (int) $this->internal_links_repository->delete_all_for_post($post_id);
		}

		// 3. Drop links in content index and clear click tracking.
		if ($this->link_index_service) {
			$this->link_index_service->on_before_delete_post($post_id);
		}

		if ($this->link_click_tracking_service) {
			$this->link_click_tracking_service->on_before_delete_post($post_id);
		}

		// 4. Purge history containers and detailed event logs (unless suppressed).
		// A failed child-log delete returns false and leaves the containers in place.
		if (!self::$suppress_history_deletion && $this->history_repository) {
			$deleted = $this->history_repository->delete_by_post_id($post_id);
			if (false === $deleted) {
				$summary['history'] = 0;
				$summary['history_delete_failed'] = true;
				if ($this->logger) {
					$this->logger->log(
						sprintf(
							'Post cleanup could not delete history logs for post #%d; history containers were left in place.',
							$post_id
						),
						'error'
					);
				}
			} else {
				$summary['history'] = (int) $deleted;
			}
		}

		// 5. Handle redirects (deactivate pointing redirects, delete origin redirects).
		if ($this->redirects_service) {
			$summary['redirects'] = $this->redirects_service->on_before_delete_post($post_id);
		}

		// 6. Disassociate author topic logs.
		if ($this->author_topic_logs_repository) {
			$summary['topic_logs'] = (int) $this->author_topic_logs_repository->disassociate_post_id($post_id);
		}

		// 7. Drop generated post-image rows. Media attachments stay in the library.
		$summary['post_images'] = (new AIPS_Post_Images_Generated_Repository())->delete_by_post_id($post_id) ? 1 : 0;

		if ($this->logger) {
			$this->logger->log(
				sprintf(
					'Post cleanup executed for post #%d: %s',
					$post_id,
					wp_json_encode($summary)
				),
				'info'
			);
		}

		/**
		 * Fires after all AIPS-related data for a post has been cleaned up.
		 *
		 * @param int   $post_id Post ID that was cleaned up.
		 * @param array $summary Summary counts of affected records.
		 */
		do_action('aips_post_cleanup_executed', $post_id, $summary);

		return $summary;
	}
}
