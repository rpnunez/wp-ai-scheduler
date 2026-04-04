<?php
/**
 * Internal Links Service
 *
 * Core business logic for Semantic Internal Linking:
 * - Generating and storing post embeddings in Pinecone.
 * - Finding related posts via vector similarity search.
 * - AI-assisted injection of anchor links into post content.
 *
 * @package AI_Post_Scheduler
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Internal_Links_Service
 */
class AIPS_Internal_Links_Service {

	/**
	 * @var AIPS_Embeddings_Service
	 */
	private $embeddings_service;

	/**
	 * @var AIPS_Pinecone_Client
	 */
	private $pinecone_client;

	/**
	 * @var AIPS_Embeddings_Repository
	 */
	private $embeddings_repository;

	/**
	 * @var AIPS_AI_Service
	 */
	private $ai_service;

	/**
	 * @var AIPS_Logger
	 */
	private $logger;

	/**
	 * Initialize the service with optional dependency injection.
	 *
	 * @param AIPS_Embeddings_Service|null    $embeddings_service
	 * @param AIPS_Pinecone_Client|null       $pinecone_client
	 * @param AIPS_Embeddings_Repository|null $embeddings_repository
	 * @param AIPS_AI_Service|null            $ai_service
	 * @param AIPS_Logger|null                $logger
	 */
	public function __construct(
		$embeddings_service    = null,
		$pinecone_client       = null,
		$embeddings_repository = null,
		$ai_service            = null,
		$logger                = null
	) {
		$this->embeddings_service    = $embeddings_service    ?: new AIPS_Embeddings_Service();
		$this->pinecone_client       = $pinecone_client       ?: new AIPS_Pinecone_Client();
		$this->embeddings_repository = $embeddings_repository ?: new AIPS_Embeddings_Repository();
		$this->ai_service            = $ai_service            ?: new AIPS_AI_Service();
		$this->logger                = $logger                ?: new AIPS_Logger();
	}

	// -----------------------------------------------------------------------
	// Indexing
	// -----------------------------------------------------------------------

	/**
	 * Generate and store the embedding for a single post.
	 *
	 * Concatenates title + excerpt + stripped content, generates a vector via
	 * Meow AI Engine, then upserts it into Pinecone and records the outcome in
	 * the embeddings repository.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return true|WP_Error
	 */
	public function index_post($post_id) {
		$post_id = absint($post_id);
		$post    = get_post($post_id);

		if (!$post) {
			return new WP_Error('post_not_found', __('Post not found.', 'ai-post-scheduler'));
		}

		if (!$this->pinecone_client->is_configured()) {
			return new WP_Error('pinecone_not_configured', __('Pinecone is not configured.', 'ai-post-scheduler'));
		}

		// Build the text to embed
		$text_parts = array($post->post_title);

		if (!empty($post->post_excerpt)) {
			$text_parts[] = $post->post_excerpt;
		}

		if (!empty($post->post_content)) {
			$text_parts[] = wp_strip_all_tags($post->post_content);
		}

		$text = implode(' ', $text_parts);
		$text = wp_trim_words($text, 500, ''); // Cap at ~500 words to stay within token limits

		// Generate embedding
		$embedding = $this->embeddings_service->generate_embedding($text);

		if (is_wp_error($embedding)) {
			$this->embeddings_repository->upsert_status($post_id, AIPS_Embeddings_Repository::STATUS_ERROR, $embedding->get_error_message());
			return $embedding;
		}

		// Upsert into Pinecone
		$vector = array(
			'id'       => 'post-' . $post_id,
			'values'   => $embedding,
			'metadata' => array(
				'post_id'     => $post_id,
				'title'       => $post->post_title,
				'permalink'   => get_permalink($post_id),
				'post_type'   => $post->post_type,
				'post_status' => $post->post_status,
			),
		);

		$result = $this->pinecone_client->upsert(array($vector));

		if (is_wp_error($result)) {
			$this->embeddings_repository->upsert_status($post_id, AIPS_Embeddings_Repository::STATUS_ERROR, $result->get_error_message());
			return $result;
		}

		$this->embeddings_repository->upsert_status($post_id, AIPS_Embeddings_Repository::STATUS_INDEXED);
		$this->logger->log('Indexed post ' . $post_id . ' in Pinecone.', 'info');

		return true;
	}

	/**
	 * Mark all published posts as pending so the batch worker will index them.
	 *
	 * @param int $batch_size Number of posts to query per WP_Query pass.
	 * @return array { queued: int, skipped: int }
	 */
	public function index_all_published($batch_size = 200) {
		$queued  = 0;
		$skipped = 0;
		$paged   = 1;

		do {
			$query = new WP_Query(array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => $batch_size,
				'paged'          => $paged,
				'fields'         => 'ids',
				'no_found_rows'  => false,
			));

			if (empty($query->posts)) {
				break;
			}

			$affected = $this->embeddings_repository->mark_pending_bulk($query->posts);
			$queued  += $affected;
			$skipped += count($query->posts) - $affected;
			$paged++;

		} while ($paged <= $query->max_num_pages);

		return array(
			'queued'  => $queued,
			'skipped' => $skipped,
		);
	}

	/**
	 * Process up to $limit pending embedding jobs.
	 *
	 * Called by the WP Cron batch worker.
	 *
	 * @param int $limit Maximum number of posts to index in this run.
	 * @return array { indexed: int, errors: int }
	 */
	public function process_pending_batch($limit = 10) {
		$pending = $this->embeddings_repository->get_by_status(AIPS_Embeddings_Repository::STATUS_PENDING, $limit);

		$indexed = 0;
		$errors  = 0;

		foreach ($pending as $row) {
			$result = $this->index_post((int) $row->post_id);

			if (is_wp_error($result)) {
				$errors++;
				$this->logger->log('Batch indexing failed for post ' . $row->post_id . ': ' . $result->get_error_message(), 'error');
			} else {
				$indexed++;
			}
		}

		return array(
			'indexed' => $indexed,
			'errors'  => $errors,
		);
	}

	// -----------------------------------------------------------------------
	// Similarity search
	// -----------------------------------------------------------------------

	/**
	 * Find posts that are semantically related to a source post.
	 *
	 * Generates an embedding for the source post, queries Pinecone for nearest
	 * neighbours, strips the source post from results, filters by min_score,
	 * and confirms each candidate still exists and is published.
	 *
	 * @param int        $source_post_id Post ID to find related posts for.
	 * @param int|null   $top_n          Number of results (default from setting).
	 * @param float|null $min_score      Minimum similarity score 0–1 (default from setting).
	 * @return array|WP_Error Array of [ post_id, title, permalink, score ] on success.
	 */
	public function find_related_posts($source_post_id, $top_n = null, $min_score = null) {
		$source_post_id = absint($source_post_id);

		if (!$this->pinecone_client->is_configured()) {
			return new WP_Error('pinecone_not_configured', __('Pinecone is not configured.', 'ai-post-scheduler'));
		}

		if (is_null($top_n)) {
			$top_n = (int) get_option('aips_internal_links_top_n', 10);
		}

		if (is_null($min_score)) {
			$min_score = (float) get_option('aips_internal_links_min_score', 0.75);
		}

		$top_n     = max(1, (int) $top_n);
		$min_score = max(0.0, min(1.0, (float) $min_score));

		$post = get_post($source_post_id);

		if (!$post) {
			return new WP_Error('post_not_found', __('Source post not found.', 'ai-post-scheduler'));
		}

		// Build embedding text for the source post
		$text_parts = array($post->post_title);

		if (!empty($post->post_excerpt)) {
			$text_parts[] = $post->post_excerpt;
		}

		if (!empty($post->post_content)) {
			$text_parts[] = wp_strip_all_tags($post->post_content);
		}

		$text = wp_trim_words(implode(' ', $text_parts), 500, '');

		$embedding = $this->embeddings_service->generate_embedding($text);

		if (is_wp_error($embedding)) {
			return $embedding;
		}

		// Request one extra result to account for the source post appearing in results
		$matches = $this->pinecone_client->query($embedding, $top_n + 1);

		if (is_wp_error($matches)) {
			return $matches;
		}

		$source_vector_id = 'post-' . $source_post_id;
		$related          = array();

		foreach ($matches as $match) {
			// Skip the source post itself
			if (isset($match['id']) && $match['id'] === $source_vector_id) {
				continue;
			}

			$score = isset($match['score']) ? (float) $match['score'] : 0.0;

			if ($score < $min_score) {
				continue;
			}

			// Extract post ID from vector ID (format: "post-123")
			$vector_id = isset($match['id']) ? (string) $match['id'] : '';
			$related_post_id = 0;

			if (preg_match('/^post-(\d+)$/', $vector_id, $id_match)) {
				$related_post_id = (int) $id_match[1];
			} elseif (isset($match['metadata']['post_id'])) {
				$related_post_id = (int) $match['metadata']['post_id'];
			}

			if (!$related_post_id) {
				continue;
			}

			// Verify post still exists and is published
			$related_post = get_post($related_post_id);

			if (!$related_post || $related_post->post_status !== 'publish') {
				continue;
			}

			$related[] = array(
				'post_id'   => $related_post_id,
				'title'     => $related_post->post_title,
				'permalink' => get_permalink($related_post_id),
				'score'     => $score,
			);

			if (count($related) >= $top_n) {
				break;
			}
		}

		return $related;
	}

	// -----------------------------------------------------------------------
	// Link injection
	// -----------------------------------------------------------------------

	/**
	 * Use AI to rewrite post content with internal links injected.
	 *
	 * Builds a prompt instructing the AI to embed natural anchor-tag links to
	 * related posts. Returns the new HTML string WITHOUT saving it — the caller
	 * (controller) is responsible for confirming and persisting the result.
	 *
	 * @param int   $target_post_id Post whose content will be rewritten.
	 * @param array $related_posts  Array of [ post_id, title, permalink ] to link to.
	 * @return string|WP_Error New post content (HTML) or WP_Error on failure.
	 */
	public function inject_links($target_post_id, array $related_posts) {
		$target_post_id = absint($target_post_id);
		$post           = get_post($target_post_id);

		if (!$post) {
			return new WP_Error('post_not_found', __('Target post not found.', 'ai-post-scheduler'));
		}

		if (empty($related_posts)) {
			return new WP_Error('no_related_posts', __('No related posts provided.', 'ai-post-scheduler'));
		}

		// Build link list for the prompt
		$link_lines = array();
		foreach ($related_posts as $rp) {
			$link_lines[] = sprintf('- Title: "%s" | URL: %s', $rp['title'], $rp['permalink']);
		}
		$links_text = implode("\n", $link_lines);

		$prompt = sprintf(
			"You are an SEO expert tasked with adding internal links to a blog post.\n\n" .
			"TASK: Rewrite the following post content by naturally embedding HTML anchor-tag links to the related posts listed below. " .
			"Follow these strict rules:\n" .
			"1. Each related post may appear AT MOST once as a link.\n" .
			"2. Only add a link where it fits naturally and improves the reader's experience.\n" .
			"3. Do NOT add links that disrupt sentence flow or feel forced.\n" .
			"4. Preserve all existing HTML structure, headings, and formatting exactly.\n" .
			"5. Return ONLY the rewritten HTML content — no explanation, no preamble.\n\n" .
			"RELATED POSTS TO LINK TO:\n%s\n\n" .
			"POST CONTENT:\n%s",
			$links_text,
			$post->post_content
		);

		$result = $this->ai_service->generate_text($prompt);

		if (is_wp_error($result)) {
			return $result;
		}

		if (empty(trim($result))) {
			return new WP_Error('empty_response', __('AI returned an empty response.', 'ai-post-scheduler'));
		}

		return $result;
	}

	/**
	 * Persist AI-rewritten content to a post and log the operation.
	 *
	 * The content is run through wp_kses_post before saving to strip any
	 * potentially dangerous HTML that the AI may have introduced.
	 *
	 * @param int    $post_id     WordPress post ID.
	 * @param string $new_content New post content (HTML).
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function save_links($post_id, $new_content) {
		$post_id = absint($post_id);
		$post    = get_post($post_id);

		if (!$post) {
			return new WP_Error('post_not_found', __('Post not found.', 'ai-post-scheduler'));
		}

		// Sanitize — preserve anchor tags but strip dangerous elements
		$safe_content = wp_kses_post($new_content);

		$update_result = wp_update_post(array(
			'ID'           => $post_id,
			'post_content' => $safe_content,
		), true);

		if (is_wp_error($update_result)) {
			return $update_result;
		}

		// Log via history service
		$history_service = new AIPS_History_Service();
		$container       = $history_service->create(
			'internal_links',
			array(
				'post_id'    => $post_id,
				'created_by' => get_current_user_id(),
			)
		);
		$container->record(
			AIPS_History_Type::LOG,
			__('Internal links injected and saved.', 'ai-post-scheduler'),
			'internal_links_saved',
			array('post_id' => $post_id),
			array()
		);
		$container->complete_success(array('post_id' => $post_id));

		$this->logger->log('Internal links saved for post ' . $post_id, 'info');

		return true;
	}
}
