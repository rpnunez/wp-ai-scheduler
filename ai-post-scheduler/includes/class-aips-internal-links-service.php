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
 * Generates ranked internal-link suggestions between posts. It never writes
 * embeddings itself: indexing belongs to AIPS_Content_Indexer_Service, which this
 * service asks (ensure_indexed) and reads status from.
 */
class AIPS_Internal_Links_Service {

	/**
	 * @var AIPS_Embeddings_Repository
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
	 * @var AIPS_Similarity_Evaluator
	 */
	private $similarity_evaluator;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * @var AIPS_Content_Indexer_Service
	 */
	private $indexer;

	/**
	 * Initialize the service.
	 *
	 * @param AIPS_Embeddings_Repository|null   $embeddings_repo      Embeddings repository.
	 * @param AIPS_Internal_Links_Repository|null $links_repo         Internal links repository.
	 * @param AIPS_Embeddings_Service|null      $embeddings_service   Embeddings service.
	 * @param AIPS_Logger|null                  $logger               Logger instance.
	 * @param AIPS_Similarity_Evaluator|null    $similarity_evaluator Similarity evaluator instance.
	 * @param AIPS_Config|null                  $config               Config instance.
	 * @param AIPS_Content_Indexer_Service|null $indexer              Content indexer (sole writer of post embeddings).
	 */
	public function __construct(
		$embeddings_repo = null,
		$links_repo = null,
		$embeddings_service = null,
		$logger = null,
		$similarity_evaluator = null,
		$config = null,
		$indexer = null
	) {
		$container                  = AIPS_Container::get_instance();
		$this->embeddings_repo      = $embeddings_repo      ?: ($container->has(AIPS_Embeddings_Repository::class) ? $container->make(AIPS_Embeddings_Repository::class) : new AIPS_Embeddings_Repository());
		$this->links_repo           = $links_repo           ?: new AIPS_Internal_Links_Repository();
		$this->embeddings_service   = $embeddings_service   ?: new AIPS_Embeddings_Service();
		$this->logger               = $logger               ?: new AIPS_Logger();
		$this->config               = $config               ?: ($container->has(AIPS_Config::class) ? $container->make(AIPS_Config::class) : AIPS_Config::get_instance());
		$this->similarity_evaluator = $similarity_evaluator ?: ($container->has(AIPS_Similarity_Evaluator::class) ? $container->make(AIPS_Similarity_Evaluator::class) : new AIPS_Similarity_Evaluator($this->config, $this->embeddings_repo, $this->embeddings_service));
		$this->indexer              = $indexer              ?: $container->make(AIPS_Content_Indexer_Service::class);
	}

	/**
	 * Get the similarity evaluator instance.
	 *
	 * @return AIPS_Similarity_Evaluator
	 */
	public function get_similarity_evaluator(): AIPS_Similarity_Evaluator {
		return $this->similarity_evaluator;
	}

	// -------------------------------------------------------------------------
	// Suggestion generation
	// -------------------------------------------------------------------------

	/**
	 * Generate internal link suggestions for a single post.
	 *
	 * Finds the most similar already-indexed posts and persists new suggestions.
	 * Existing pending suggestions for the source post are replaced, while
	 * non-pending suggestions (accepted, rejected, or inserted) are preserved.
	 *
	 * @param int        $source_post_id       WordPress post ID.
	 * @param int|null   $max_suggestions      Maximum suggestions to create. Null for evaluator default.
	 * @param float|null $similarity_threshold Minimum similarity score. Null for evaluator default.
	 * @return int[]|WP_Error Array of created suggestion IDs or WP_Error.
	 */
	public function generate_suggestions_for_post(
		$source_post_id,
		$max_suggestions = null,
		$similarity_threshold = null
	) {
		$source_post_id       = absint($source_post_id);
		$max_suggestions      = ($max_suggestions !== null && (int) $max_suggestions > 0) ? (int) $max_suggestions : $this->similarity_evaluator->get_default_max_suggestions();
		$similarity_threshold = ($similarity_threshold !== null && is_numeric($similarity_threshold)) ? (float) $similarity_threshold : $this->similarity_evaluator->get_default_threshold('internal_links');

		// Make sure the source post is indexed with the current text and model. A
		// current row costs no embedding call. If re-embedding is not possible right
		// now (cooldown, lock), a previously stored vector is still good enough.
		$ensured    = $this->indexer->ensure_indexed($source_post_id);
		$source_row = $this->embeddings_repo->get_by_post_id($source_post_id);

		if (is_wp_error($ensured)) {
			if (!$source_row) {
				return $ensured;
			}

			$this->logger->log(
				sprintf('Using the stored embedding for post %d because re-indexing failed: %s', $source_post_id, $ensured->get_error_message()),
				'warning'
			);
		}

		if (!$source_row) {
			return new WP_Error('index_failed', __('Could not index the source post.', 'ai-post-scheduler'));
		}

		$source_embedding = $this->embeddings_repo->decode_embedding($source_row->embedding);

		if (empty($source_embedding)) {
			return new WP_Error('invalid_embedding', __('Source post has an invalid embedding.', 'ai-post-scheduler'));
		}

		// Fetch embeddings constrained to the same post type and status as the source post.
		// This excludes deleted posts and posts of a different type from the candidate set,
		// reducing memory usage and improving relevance on sites with many post types.
		$source_post = get_post($source_post_id);
		if (!$source_post) {
			return new WP_Error('post_not_found', __('Source post no longer exists.', 'ai-post-scheduler'));
		}
		$post_type   = $source_post->post_type;
		$post_status = $source_post->post_status;
		$all_rows    = $this->embeddings_repo->get_all_for_similarity('post', array($post_type), $post_status);

		// Collect target IDs that already have a non-pending suggestion from this source.
		// These must be excluded so regeneration does not produce fewer than max_suggestions
		// when accepted/rejected/inserted rows exist for some top neighbors.
		$existing_rows       = $this->links_repo->get_by_source_post($source_post_id);
		// Posts the source already links to (per the link index) never need a suggestion.
		$link_index          = new AIPS_Link_Index_Repository();
		$excluded_target_ids = array_values(array_filter(array_map('intval', wp_list_pluck($link_index->get_outbound($source_post_id, AIPS_Link_Index_Repository::TYPE_INTERNAL), 'target_post_id'))));
		foreach ($existing_rows as $row) {
			if ('pending' !== $row->status) {
				$excluded_target_ids[] = (int) $row->target_post_id;
			}
		}

		$candidates = array();
		foreach ($all_rows as $row) {
			$cand_id = (int) $row->object_id;
			if ($cand_id === $source_post_id || in_array($cand_id, $excluded_target_ids, true)) {
				continue;
			}
			$candidates[] = $row;
		}

		if (empty($candidates)) {
			return array();
		}

		// Find top matches via Similarity Evaluator
		$neighbors = $this->similarity_evaluator->find_top_matches(
			$source_embedding,
			$candidates,
			$similarity_threshold,
			$max_suggestions,
			'post'
		);

		// Delete only existing PENDING suggestions before reinserting.
		// Accepted, rejected, and inserted suggestions are preserved so that
		// editorial decisions and insertion tracking are not lost during regeneration.
		$this->links_repo->delete_pending_by_source_post($source_post_id, 'outbound');

		$created_ids = array();

		if (!empty($neighbors) && function_exists('_prime_post_caches')) {
			$post_ids = array_column($neighbors, 'id');
			_prime_post_caches(array_unique($post_ids), false, true);
		}

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
	 * Reads the Content Indexer's status so every page shows the same numbers.
	 *
	 * @param string|string[]|null $post_type   Post type(s) to check, or null for the indexer setting.
	 * @param string               $post_status Post status to check.
	 * @return array{total_posts: int, indexed: int, unindexed: int, stale: int, percent: int}
	 */
	public function get_indexing_status($post_type = null, $post_status = 'publish') {
		$status = $this->indexer->get_indexing_status($this->resolve_post_types($post_type), $post_status);

		return array(
			'total_posts' => (int) $status['total_posts'],
			'indexed'     => (int) $status['indexed'],
			'unindexed'   => (int) $status['unindexed'],
			'stale'       => (int) $status['stale'],
			'percent'     => (int) $status['percent'],
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
	 * Post types to index: the explicit argument, else the configured indexer post types.
	 *
	 * @param string|string[]|null $post_type Explicit post type(s) or null for the setting.
	 * @return string[]
	 */
	private function resolve_post_types($post_type = null) {
		$types = $post_type === null || $post_type === ''
			? (array) $this->config->get_option('aips_indexer_post_types', array('post'))
			: (array) $post_type;

		$types = array_values(array_filter(array_map('sanitize_key', $types)));

		return !empty($types) ? $types : array('post');
	}
}
