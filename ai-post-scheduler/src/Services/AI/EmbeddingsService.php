<?php
namespace AIPS\Services\AI;

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
 * Class EmbeddingsService
 *
 * Service for generating and working with text embeddings.
 */
class EmbeddingsService {
	
	/**
	 * @var AIPS_AI_Service AI Service instance
	 */
	private $ai_service;
	
	/**
	 * @var AIPS_Logger Logger instance
	 */
	private $logger;
	
	/**
	 * @var array Cache for embeddings to avoid redundant API calls
	 */
	private $embedding_cache;
	
	/**
	 * Initialize the embeddings service.
	 */
	public function __construct($ai_service = null, $logger = null) {
		$this->ai_service = $ai_service ?: new \AIPS_AI_Service();
		$this->logger = $logger ?: new \AIPS_Logger();
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
			return new \WP_Error('empty_text', __('Cannot generate embedding for empty text.', 'ai-post-scheduler'));
		}
		
		// Check cache
		$cache_key = md5($text);
		if (isset($this->embedding_cache[$cache_key])) {
			return $this->embedding_cache[$cache_key];
		}
		
		if (!$this->ai_service->is_available()) {
			return new \WP_Error('ai_unavailable', __('AI Engine plugin is not available.', 'ai-post-scheduler'));
		}
		
		// Get AI engine through global
		$ai = null;
		if (class_exists('Meow_MWAI_Core')) {
			global $mwai_core;
			$ai = $mwai_core;
		}
		
		if (!$ai) {
			return new \WP_Error('ai_unavailable', __('AI Engine plugin is not available.', 'ai-post-scheduler'));
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
			return new \WP_Error('embeddings_not_supported', __('Embeddings are not supported by the current AI Engine configuration.', 'ai-post-scheduler'));
			
		} catch (Exception $e) {
			$this->logger->log('Embedding generation failed: ' . $e->getMessage(), 'error');
			return new \WP_Error('embedding_failed', $e->getMessage());
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
			return new \WP_Error('invalid_embeddings', __('Invalid embedding vectors provided.', 'ai-post-scheduler'));
		}
		
		if (count($embedding1) !== count($embedding2)) {
			return new \WP_Error('dimension_mismatch', __('Embedding vectors must have the same dimensions.', 'ai-post-scheduler'));
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
			return new \WP_Error('zero_magnitude', __('Cannot calculate similarity with zero magnitude vectors.', 'ai-post-scheduler'));
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
}
