<?php
/**
 * Embeddings Service
 *
 * Handles embedding generation and similarity calculations using Meow AI Engine.
 * Provides semantic similarity features for topic expansion and recommendation.
 *
 * @package AI_Post_Scheduler
 * @since 1.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Embeddings_Service
 *
 * Service for generating and working with text embeddings.
 */
class AIPS_Embeddings_Service {
	
	/**
	 * @var AIPS_AI_Service_Interface AI Service instance
	 */
	private $ai_service;
	
	/**
	 * @var AIPS_Logger_Interface Logger instance
	 */
	private $logger;
	
	/**
	 * @var AIPS_Post_Embeddings_Repository Post embeddings repository instance
	 */
	private $embeddings_repo;
	
	/**
	 * Initialize the embeddings service.
	 */
	public function __construct(?AIPS_AI_Service_Interface $ai_service = null, ?AIPS_Logger_Interface $logger = null, ?AIPS_Post_Embeddings_Repository $embeddings_repo = null) {
		$container = AIPS_Container::get_instance();
		$this->ai_service = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
		$this->logger = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
		$this->embeddings_repo = $embeddings_repo ?: new AIPS_Post_Embeddings_Repository();
		$this->embedding_cache = array();
	}
	
	/**
	 * Generate an embedding for a text string using Meow AI.
	 *
	 * @param string $text The text to generate an embedding for.
	 * @param array  $options Optional. Additional options for embedding generation.
	 * @return array|WP_Error The embedding vector or WP_Error on failure.
	 */
	public function generate_embedding($text, $options = array()) {
		if (empty($text)) {
			return new WP_Error('empty_text', __('Cannot generate embedding for empty text.', 'ai-post-scheduler'));
		}
		
		// Check cache
		$cache_key = md5($text);
		if (isset($this->embedding_cache[$cache_key])) {
			return $this->embedding_cache[$cache_key];
		}
		
		if (!$this->ai_service->is_available()) {
			return new WP_Error('ai_unavailable', __('AI Engine plugin is not available.', 'ai-post-scheduler'));
		}
		
		// Get AI engine through global
		$ai = null;
		if (class_exists('Meow_MWAI_Core')) {
			global $mwai_core;
			$ai = $mwai_core;
		}
		
		if (!$ai) {
			return new WP_Error('ai_unavailable', __('AI Engine plugin is not available.', 'ai-post-scheduler'));
		}
		
		try {
			// Use Meow AI's embeddings functionality
			// Note: This assumes Meow AI supports embeddings through a Query_Embed or similar class
			if (class_exists('Meow_MWAI_Query_Embed')) {
				$query = new Meow_MWAI_Query_Embed($text);
				
				// Set embeddings environment if specified
				if (!empty($options['embeddings_env_id'])) {
					if (method_exists($query, 'set_embeddings_env_id')) {
						$query->set_embeddings_env_id($options['embeddings_env_id']);
					}
				}
				
				$response = $ai->run_query($query);
				
				if ($response && !empty($response->result)) {
					$embedding = $response->result;
					
					// Cache the result
					$this->embedding_cache[$cache_key] = $embedding;
					
					$this->logger->log('Generated embedding for text: ' . substr($text, 0, 50) . '...', 'debug');
					return $embedding;
				}
			}
			
			// Fallback: If Meow AI doesn't support embeddings directly, return an error
			return new WP_Error('embeddings_not_supported', __('Embeddings are not supported by the current AI Engine configuration.', 'ai-post-scheduler'));
			
		} catch (Exception $e) {
			$this->logger->log('Embedding generation failed: ' . $e->getMessage(), 'error');
			return new WP_Error('embedding_failed', $e->getMessage());
		}
	}
	
	/**
	 * Calculate cosine similarity between two embedding vectors.
	 *
	 * @param array $embedding1 First embedding vector.
	 * @param array $embedding2 Second embedding vector.
	 * @return float|WP_Error Similarity score (0-1) or WP_Error on failure.
	 */
	public function calculate_similarity($embedding1, $embedding2) {
		if (!is_array($embedding1) || !is_array($embedding2)) {
			return new WP_Error('invalid_embeddings', __('Invalid embedding vectors provided.', 'ai-post-scheduler'));
		}
		
		if (count($embedding1) !== count($embedding2)) {
			return new WP_Error('dimension_mismatch', __('Embedding vectors must have the same dimensions.', 'ai-post-scheduler'));
		}
		
		// Calculate cosine similarity
		$dot_product = 0;
		$magnitude1 = 0;
		$magnitude2 = 0;
		
		for ($i = 0; $i < count($embedding1); $i++) {
			$dot_product += $embedding1[$i] * $embedding2[$i];
			$magnitude1 += $embedding1[$i] * $embedding1[$i];
			$magnitude2 += $embedding2[$i] * $embedding2[$i];
		}
		
		$magnitude1 = sqrt($magnitude1);
		$magnitude2 = sqrt($magnitude2);
		
		if ($magnitude1 == 0 || $magnitude2 == 0) {
			return new WP_Error('zero_magnitude', __('Cannot calculate similarity with zero magnitude vectors.', 'ai-post-scheduler'));
		}
		
		$similarity = $dot_product / ($magnitude1 * $magnitude2);
		
		// Ensure result is in [0, 1] range (sometimes floating point errors can cause slight exceedance)
		return max(0, min(1, $similarity));
	}
	
	/**
	 * Find the most similar items to a target embedding.
	 *
	 * @param array $target_embedding The target embedding vector.
	 * @param array $candidate_embeddings Array of candidate embeddings with their IDs.
	 * @param int   $top_k Number of top results to return.
	 * @return array Array of results with IDs and similarity scores, sorted by similarity.
	 */
	public function find_nearest_neighbors($target_embedding, $candidate_embeddings, $top_k = 5) {
		$similarities = array();
		
		foreach ($candidate_embeddings as $candidate) {
			if (!isset($candidate['id']) || !isset($candidate['embedding'])) {
				continue;
			}
			
			$similarity = $this->calculate_similarity($target_embedding, $candidate['embedding']);
			
			if (!is_wp_error($similarity)) {
				$similarities[] = array(
					'id' => $candidate['id'],
					'similarity' => $similarity,
					'data' => isset($candidate['data']) ? $candidate['data'] : array()
				);
			}
		}
		
		// Sort by similarity (descending)
		usort($similarities, function($a, $b) {
			return $b['similarity'] <=> $a['similarity'];
		});
		
		// Return top K results
		return array_slice($similarities, 0, $top_k);
	}
	
	/**
	 * Batch generate embeddings for multiple texts.
	 *
	 * @param array $texts Array of text strings to generate embeddings for.
	 * @param array $options Optional. Additional options for embedding generation.
	 * @return array Array of embeddings (or WP_Error objects for failures).
	 */
	public function batch_generate_embeddings($texts, $options = array()) {
		$embeddings = array();
		
		foreach ($texts as $index => $text) {
			$embedding = $this->generate_embedding($text, $options);
			$embeddings[$index] = $embedding;
		}
		
		return $embeddings;
	}
	
	/**
	 * Clear the embedding cache.
	 */
	public function clear_cache() {
		$this->embedding_cache = array();
	}
	
	/**
	 * Check if embeddings are supported by the current AI Engine configuration.
	 *
	 * @return bool True if embeddings are supported, false otherwise.
	 */
	public function is_embeddings_supported() {
		return class_exists('Meow_MWAI_Query_Embed') && $this->ai_service->is_available();
	}

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

		$embedding = $this->generate_embedding($text);

		if (is_wp_error($embedding)) {
			return $embedding;
		}

		$this->embeddings_repo->upsert($post_id, $embedding);

		$this->logger->log(
			sprintf('Indexed post %d.', $post_id),
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
	public function process_post_indexing_batch(
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

	/**
	 * Find similar posts to a given post using embeddings.
	 *
	 * @param int   $post_id   Target post ID.
	 * @param int   $limit     Max number of similar posts to return.
	 * @param float $threshold Minimum similarity score.
	 * @return array Array of matching posts with ID and similarity score.
	 */
	public function find_similar_posts($post_id, $limit = 3, $threshold = 0.70) {
		$post_id = absint($post_id);
		$post    = get_post($post_id);

		if (!$post) {
			return array();
		}

		// Ensure the post is indexed first
		$source_row = $this->embeddings_repo->get_by_post_id($post_id);

		if (!$source_row) {
			$result = $this->index_post($post_id);

			if (is_wp_error($result)) {
				return array();
			}

			$source_row = $this->embeddings_repo->get_by_post_id($post_id);
		}

		if (!$source_row) {
			return array();
		}

		$target_embedding = json_decode($source_row->embedding, true);

		if (empty($target_embedding)) {
			return array();
		}

		$post_type   = $post->post_type;
		$post_status = $post->post_status;
		$all_rows    = $this->embeddings_repo->get_all_for_similarity_by_type($post_type, $post_status);

		$candidates = array();
		foreach ($all_rows as $row) {
			if ((int) $row->post_id === $post_id) {
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

		$neighbors = $this->find_nearest_neighbors(
			$target_embedding,
			$candidates,
			$limit
		);

		// Filter by similarity threshold
		return array_values(array_filter(
			$neighbors,
			function ($n) use ($threshold) {
				return isset($n['similarity']) && $n['similarity'] >= $threshold;
			}
		));
	}

	/**
	 * Hook filter method: Appends related posts list to frontend content if enabled.
	 *
	 * @param string $content Post content.
	 * @return string Modified content.
	 */
	public function append_related_posts_to_content($content) {
		if (!is_singular('post')) {
			return $content;
		}

		$post_id = get_the_ID();
		$enable  = get_post_meta($post_id, '_aips_enable_related_posts', true);

		if ('1' !== $enable) {
			return $content;
		}

		$limit = get_post_meta($post_id, '_aips_related_posts_limit', true);
		$limit = $limit ? absint($limit) : 3;

		$threshold = get_post_meta($post_id, '_aips_related_posts_threshold', true);
		$threshold = $threshold !== '' ? (float) $threshold : 0.70;

		$related = $this->find_similar_posts($post_id, $limit, $threshold);

		if (empty($related)) {
			return $content;
		}

		$html = '<div class="aips-related-posts">';
		$html .= '<h3>' . esc_html__('Related Posts', 'ai-post-scheduler') . '</h3>';
		$html .= '<ul>';
		foreach ($related as $item) {
			$related_post_id = $item['id'];
			$html .= '<li>';
			$html .= '<a href="' . esc_url(get_permalink($related_post_id)) . '">';
			$html .= esc_html(get_the_title($related_post_id));
			$html .= '</a>';
			$html .= '</li>';
		}
		$html .= '</ul>';
		$html .= '</div>';

		return $content . $html;
	}

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
