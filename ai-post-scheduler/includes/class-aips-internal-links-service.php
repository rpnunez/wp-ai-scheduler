<?php
/**
 * Internal Links Service
 *
 * Business logic for generating internal link suggestions between posts
 * using semantic embeddings and cosine similarity.
 *
 * @package AI_Post_Scheduler
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Internal_Links_Service
 *
 * Orchestrates indexing published posts (generating and storing their embeddings)
 * and generating ranked internal-link suggestions between them.
 */
class AIPS_Internal_Links_Service {

	/**
	 * @var AIPS_Post_Embeddings_Repository
	 */
	private $embeddings_repo;

	/**
	 * @var AIPS_Internal_Links_Repository
	 */
	private $links_repo;

	/**
	 * @var AIPS_Embeddings_Service
	 */
	private $embeddings_service;

	/**
	 * @var AIPS_Logger
	 */
	private $logger;

	/**
	 * Default minimum similarity score to include a suggestion.
	 *
	 * @var float
	 */
	const DEFAULT_SIMILARITY_THRESHOLD = 0.70;

	/**
	 * Default maximum number of link suggestions per source post.
	 *
	 * @var int
	 */
	const DEFAULT_MAX_SUGGESTIONS = 5;

	/**
	 * Initialize the service.
	 *
	 * @param AIPS_Post_Embeddings_Repository|null $embeddings_repo    Embeddings repository.
	 * @param AIPS_Internal_Links_Repository|null  $links_repo         Internal links repository.
	 * @param AIPS_Embeddings_Service|null         $embeddings_service Embeddings service.
	 * @param AIPS_Logger|null                     $logger             Logger instance.
	 */
	public function __construct(
		$embeddings_repo = null,
		$links_repo = null,
		$embeddings_service = null,
		$logger = null
	) {
		$this->embeddings_repo    = $embeddings_repo    ?: new AIPS_Post_Embeddings_Repository();
		$this->links_repo         = $links_repo         ?: new AIPS_Internal_Links_Repository();
		$this->embeddings_service = $embeddings_service ?: new AIPS_Embeddings_Service();
		$this->logger             = $logger             ?: new AIPS_Logger();
	}

	// -------------------------------------------------------------------------
	// Indexing
	// -------------------------------------------------------------------------

	/**
	 * Index a single post — generate its embedding and store it.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return true|WP_Error True on success or WP_Error on failure.
	 */
	public function index_post($post_id) {
		$post_id = absint($post_id);
		$post    = get_post($post_id);

		if (!$post) {
			return new WP_Error('post_not_found', __('Post not found.', 'ai-post-scheduler'));
		}

		$text = $this->get_post_text($post);

		if (empty($text)) {
			return new WP_Error('empty_content', __('Post has no indexable content.', 'ai-post-scheduler'));
		}

		$embedding = $this->embeddings_service->generate_embedding($text);

		if (is_wp_error($embedding)) {
			return $embedding;
		}

		$this->embeddings_repo->upsert($post_id, $embedding);

		$this->logger->log(
			sprintf('Indexed post %d for internal links.', $post_id),
			'debug'
		);

		return true;
	}

	/**
	 * Process a batch of unindexed posts.
	 *
	 * @param int    $batch_size    Number of posts to process per call.
	 * @param int    $last_post_id  Resume cursor: only posts with ID > this value.
	 * @param string $post_type     Post type to index.
	 * @param string $post_status   Post status to index.
	 * @return array{success: int, failed: int, last_post_id: int, done: bool}
	 */
	public function process_indexing_batch(
		$batch_size = 10,
		$last_post_id = 0,
		$post_type = 'post',
		$post_status = 'publish'
	) {
		$post_ids = $this->embeddings_repo->get_unindexed_post_ids(
			$batch_size,
			$last_post_id,
			$post_type,
			$post_status
		);

		$success        = 0;
		$failed         = 0;
		$new_last_id    = $last_post_id;

		foreach ($post_ids as $post_id) {
			$result = $this->index_post($post_id);

			if (is_wp_error($result)) {
				$this->logger->log(
					sprintf(
						'Failed to index post %d: %s',
						$post_id,
						$result->get_error_message()
					),
					'error'
				);
				$failed++;
			} else {
				$success++;
			}

			$new_last_id = max($new_last_id, $post_id);
		}

		$done = count($post_ids) < $batch_size;

		return array(
			'success'      => $success,
			'failed'       => $failed,
			'last_post_id' => $new_last_id,
			'done'         => $done,
		);
	}

	// -------------------------------------------------------------------------
	// Suggestion generation
	// -------------------------------------------------------------------------

	/**
	 * Generate internal link suggestions for a single post.
	 *
	 * Finds the most similar already-indexed posts and persists new suggestions.
	 * Existing suggestions for the source post are replaced.
	 *
	 * @param int   $source_post_id     WordPress post ID.
	 * @param int   $max_suggestions    Maximum suggestions to create.
	 * @param float $similarity_threshold Minimum similarity score.
	 * @return int[]|WP_Error Array of created suggestion IDs or WP_Error.
	 */
	public function generate_suggestions_for_post(
		$source_post_id,
		$max_suggestions = self::DEFAULT_MAX_SUGGESTIONS,
		$similarity_threshold = self::DEFAULT_SIMILARITY_THRESHOLD
	) {
		$source_post_id = absint($source_post_id);

		// Ensure the post is indexed first
		$source_row = $this->embeddings_repo->get_by_post_id($source_post_id);

		if (!$source_row) {
			$result = $this->index_post($source_post_id);

			if (is_wp_error($result)) {
				return $result;
			}

			$source_row = $this->embeddings_repo->get_by_post_id($source_post_id);
		}

		if (!$source_row) {
			return new WP_Error('index_failed', __('Could not index the source post.', 'ai-post-scheduler'));
		}

		$source_embedding = json_decode($source_row->embedding, true);

		if (empty($source_embedding)) {
			return new WP_Error('invalid_embedding', __('Source post has an invalid embedding.', 'ai-post-scheduler'));
		}

		// Fetch all other indexed embeddings for comparison
		$all_rows = $this->embeddings_repo->get_all_for_similarity();

		$candidates = array();
		foreach ($all_rows as $row) {
			if ((int) $row->post_id === $source_post_id) {
				continue;
			}

			$embedding = json_decode($row->embedding, true);
			if (!empty($embedding)) {
				$candidates[] = array(
					'id'        => (int) $row->post_id,
					'embedding' => $embedding,
				);
			}
		}

		if (empty($candidates)) {
			return array();
		}

		$neighbors = $this->embeddings_service->find_nearest_neighbors(
			$source_embedding,
			$candidates,
			$max_suggestions * 2 // fetch extra to apply threshold filter below
		);

		// Filter by similarity threshold
		$neighbors = array_filter(
			$neighbors,
			function ($n) use ($similarity_threshold) {
				return isset($n['similarity']) && $n['similarity'] >= $similarity_threshold;
			}
		);

		$neighbors = array_slice(array_values($neighbors), 0, $max_suggestions);

		// Delete existing pending suggestions before reinserting
		$this->links_repo->delete_by_source_post($source_post_id);

		$created_ids = array();

		foreach ($neighbors as $neighbor) {
			$target_post_id  = (int) $neighbor['id'];
			$similarity      = (float) $neighbor['similarity'];
			$target_post     = get_post($target_post_id);
			$anchor_text     = $target_post ? $target_post->post_title : '';

			$id = $this->links_repo->insert(
				$source_post_id,
				$target_post_id,
				$similarity,
				$anchor_text
			);

			if ($id) {
				$created_ids[] = $id;
			}
		}

		return $created_ids;
	}

	// -------------------------------------------------------------------------
	// Status helpers
	// -------------------------------------------------------------------------

	/**
	 * Get indexing status statistics.
	 *
	 * @param string $post_type   Post type to check.
	 * @param string $post_status Post status to check.
	 * @return array{total_posts: int, indexed: int, unindexed: int, percent: int}
	 */
	public function get_indexing_status($post_type = 'post', $post_status = 'publish') {
		$total_posts = (int) wp_count_posts($post_type)->$post_status;
		$indexed     = $this->embeddings_repo->count();
		$unindexed   = max(0, $total_posts - $indexed);
		$percent     = $total_posts > 0 ? min(100, (int) round(($indexed / $total_posts) * 100)) : 0;

		return array(
			'total_posts' => $total_posts,
			'indexed'     => $indexed,
			'unindexed'   => $unindexed,
			'percent'     => $percent,
		);
	}

	/**
	 * Get a combined dashboard summary.
	 *
	 * @return array
	 */
	public function get_dashboard_summary() {
		return array(
			'indexing'       => $this->get_indexing_status(),
			'link_counts'    => $this->links_repo->get_status_counts(),
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build the text content to embed for a post (title + excerpt + content).
	 *
	 * @param WP_Post $post Post object.
	 * @return string Plain-text representation.
	 */
	private function get_post_text($post) {
		$parts = array(
			$post->post_title,
			$post->post_excerpt,
			wp_strip_all_tags($post->post_content),
		);

		return trim(implode(' ', array_filter($parts)));
	}
}
