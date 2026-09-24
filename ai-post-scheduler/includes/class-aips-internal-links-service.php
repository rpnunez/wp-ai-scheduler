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
	 * Initialize the service.
	 *
	 * @param AIPS_Embeddings_Repository|null   $embeddings_repo      Embeddings repository.
	 * @param AIPS_Internal_Links_Repository|null $links_repo         Internal links repository.
	 * @param AIPS_Embeddings_Service|null      $embeddings_service   Embeddings service.
	 * @param AIPS_Logger|null                  $logger               Logger instance.
	 * @param AIPS_Similarity_Evaluator|null    $similarity_evaluator Similarity evaluator instance.
	 * @param AIPS_Config|null                  $config               Config instance.
	 */
	public function __construct(
		$embeddings_repo = null,
		$links_repo = null,
		$embeddings_service = null,
		$logger = null,
		$similarity_evaluator = null,
		$config = null
	) {
		$container                  = AIPS_Container::get_instance();
		$this->embeddings_repo      = $embeddings_repo      ?: ($container->has(AIPS_Embeddings_Repository::class) ? $container->make(AIPS_Embeddings_Repository::class) : new AIPS_Embeddings_Repository());
		$this->links_repo           = $links_repo           ?: new AIPS_Internal_Links_Repository();
		$this->embeddings_service   = $embeddings_service   ?: new AIPS_Embeddings_Service();
		$this->logger               = $logger               ?: new AIPS_Logger();
		$this->config               = $config               ?: ($container->has(AIPS_Config::class) ? $container->make(AIPS_Config::class) : AIPS_Config::get_instance());
		$this->similarity_evaluator = $similarity_evaluator ?: ($container->has(AIPS_Similarity_Evaluator::class) ? $container->make(AIPS_Similarity_Evaluator::class) : new AIPS_Similarity_Evaluator($this->config, $this->embeddings_repo, $this->embeddings_service));
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
	// Indexing
	// -------------------------------------------------------------------------

	/**
	 * Index a single post — generate its embedding and store it.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return true|WP_Error True on success or WP_Error on failure.
	 */
	public function index_post($post_id) {
		if (!$this->embeddings_service->is_enabled()) {
			return new WP_Error('embeddings_disabled', __('The vector embeddings system is disabled in settings.', 'ai-post-scheduler'));
		}

		$rate_limiter = $this->embeddings_service->get_rate_limiter();
		$cooldown     = $rate_limiter->get_cooldown_status();
		if ($cooldown['is_paused']) {
			return new WP_Error(
				'embeddings_cooldown_active',
				sprintf(
					/* translators: %s: formatted date/time when cooldown expires */
					__('Embeddings rate limit cooldown active. Paused until %s.', 'ai-post-scheduler'),
					date_i18n('Y-m-d H:i:s', $cooldown['paused_until'])
				)
			);
		}

		$post_id = absint($post_id);
		$post    = get_post($post_id);

		if (!$post) {
			return new WP_Error('post_not_found', __('Post not found.', 'ai-post-scheduler'));
		}

		$text = $this->get_post_text($post);

		if (empty($text)) {
			return new WP_Error('empty_content', __('Post has no indexable content.', 'ai-post-scheduler'));
		}

		$content_hash = md5($text);
		$existing     = $this->embeddings_repo->get_by_post_id($post_id);

		// Skip if text hasn't changed (0 AI calls required)
		if ($existing && !empty($existing->content_hash) && $existing->content_hash === $content_hash) {
			$this->logger->log(
				sprintf('Post %d content unchanged (hash: %s), skipping internal links embedding generation.', $post_id, $content_hash),
				'debug'
			);
			return true;
		}

		$embedding = $this->embeddings_service->generate_embedding($text);

		if (is_wp_error($embedding) || !is_array($embedding)) {
			return $embedding;
		}

		$model      = $this->embeddings_service->get_active_model();
		$dimensions = count($embedding);

		$this->embeddings_repo->upsert(
			'post',
			$post_id,
			$embedding,
			$model,
			$dimensions,
			$content_hash,
			$post->post_type
		);

		$this->logger->log(
			sprintf('Indexed post %d for internal links.', $post_id),
			'debug'
		);

		return true;
	}

	/**
	 * Process a batch of unindexed posts.
	 *
	 * Respects sliding-window API quotas, auto-cooldown detection, and error thresholds.
	 *
	 * @param int    $batch_size    Number of posts to process per call.
	 * @param int    $last_post_id  Resume cursor: only posts with ID > this value.
	 * @param string $post_type     Post type to index.
	 * @param string $post_status   Post status to index.
	 * @return array{success: int, failed: int, last_post_id: int, done: bool, cooldown?: bool, paused_until?: int}
	 */
	public function process_indexing_batch(
		$batch_size = 10,
		$last_post_id = 0,
		$post_type = 'post',
		$post_status = 'publish'
	) {
		if (!$this->embeddings_service->is_enabled()) {
			return array(
				'success'      => 0,
				'failed'       => 0,
				'last_post_id' => $last_post_id,
				'done'         => true,
			);
		}

		$rate_limiter = $this->embeddings_service->get_rate_limiter();
		$cooldown     = $rate_limiter->get_cooldown_status();

		if ($cooldown['is_paused']) {
			return array(
				'success'      => 0,
				'failed'       => 0,
				'last_post_id' => $last_post_id,
				'done'         => true,
				'cooldown'     => true,
				'paused_until' => $cooldown['paused_until'],
			);
		}

		$post_ids = $this->embeddings_repo->get_unindexed_post_ids(
			$batch_size,
			$last_post_id,
			$post_type,
			$post_status
		);

		$success     = 0;
		$failed      = 0;
		$new_last_id = $last_post_id;

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
				$rate_limiter->record_failure($result);

				if ($rate_limiter->is_rate_limit_or_exhaustion_error($result) || $result->get_error_code() === 'rate_limit_exceeded' || $result->get_error_code() === 'embeddings_cooldown_active') {
					break;
				}
			} else {
				$rate_limiter->record_success();
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
	 * @param string $post_type   Post type to check.
	 * @param string $post_status Post status to check.
	 * @return array{total_posts: int, indexed: int, unindexed: int, percent: int}
	 */
	public function get_indexing_status($post_type = 'post', $post_status = 'publish') {
		$counts      = wp_count_posts($post_type);
		$total_posts = isset($counts->$post_status) ? (int) $counts->$post_status : 0;
		$indexed     = $this->embeddings_repo->count_indexed_for_types((array) $post_type, $post_status);
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
