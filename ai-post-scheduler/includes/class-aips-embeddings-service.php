<?php
/**
 * Embeddings Service
 *
 * Handles embedding generation and similarity calculations via the active AI
 * provider (AIPS_AI_Service). Provides semantic similarity features for topic
 * expansion and recommendation.
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
	 * @var AIPS_Config Config instance
	 */
	private $config;
	
	/**
	 * @var AIPS_Embeddings_Rate_Limiter Rate limiter instance
	 */
	private $rate_limiter;

	/**
	 * @var array Cache for embeddings to avoid redundant API calls
	 */
	private $embedding_cache;
	
	/**
	 * Initialize the embeddings service.
	 */
	public function __construct(?AIPS_AI_Service_Interface $ai_service = null, ?AIPS_Logger_Interface $logger = null, ?AIPS_Config $config = null, ?AIPS_Embeddings_Rate_Limiter $rate_limiter = null) {
		$container = AIPS_Container::get_instance();
		$this->ai_service = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
		$this->logger = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
		$this->config = $config ?: ($container->has(AIPS_Config::class) ? $container->make(AIPS_Config::class) : AIPS_Config::get_instance());
		$this->rate_limiter = $rate_limiter ?: ($container->has(AIPS_Embeddings_Rate_Limiter::class) ? $container->make(AIPS_Embeddings_Rate_Limiter::class) : new AIPS_Embeddings_Rate_Limiter($this->config, $this->logger));
		$this->embedding_cache = array();
	}
	
	/**
	 * Get the rate limiter instance.
	 *
	 * @return AIPS_Embeddings_Rate_Limiter
	 */
	public function get_rate_limiter(): AIPS_Embeddings_Rate_Limiter {
		return $this->rate_limiter;
	}

	/**
	 * Check if the embeddings system is enabled in configuration.
	 *
	 * @return bool True if embeddings are enabled, false otherwise.
	 */
	public function is_enabled(): bool {
		return (bool) $this->config->get_option('aips_embeddings_enabled', true);
	}
	
	/**
	 * Generate an embedding for a text string via the active AI provider.
	 *
	 * Embeddings currently require the Meow AI Engine provider; other providers
	 * report embeddings_not_supported.
	 *
	 * @param string $text The text to generate an embedding for.
	 * @param array  $options Optional. Additional options for embedding generation.
	 * @return array|WP_Error The embedding vector or WP_Error on failure.
	 */
	public function generate_embedding($text, $options = array()) {
		if (!$this->is_enabled()) {
			return new WP_Error('embeddings_disabled', __('The vector embeddings system is disabled in settings.', 'ai-post-scheduler'));
		}

		if (empty($text)) {
			return new WP_Error('empty_text', __('Cannot generate embedding for empty text.', 'ai-post-scheduler'));
		}

		// Check cache (cached embeddings do not count towards quota)
		$cache_key = md5($text);
		if (isset($this->embedding_cache[$cache_key])) {
			return $this->embedding_cache[$cache_key];
		}

		// Enforce rate limits before dispatching AI provider request
		$limit_check = $this->rate_limiter->check_limits(1);
		if (is_wp_error($limit_check)) {
			return $limit_check;
		}

		$default_env_id = (string) $this->config->get_option('aips_embeddings_env_id');
		$default_model  = (string) $this->config->get_option('aips_embeddings_model');

		if (!empty($default_env_id) && !isset($options['embeddings_env_id'])) {
			$options['embeddings_env_id'] = $default_env_id;
		}

		if (!empty($default_model) && !isset($options['model'])) {
			$options['model'] = $default_model;
		}

		// Delegate the raw call to the active provider via the AI service, which
		// applies resilience and logging. The provider abstracts away whether the
		// backend is Meow AI Engine, the WordPress AI Client, or another adapter.
		$embedding = $this->ai_service->generate_embedding($text, $options);

		// Record quota consumption immediately upon making the outgoing AI provider request
		$this->rate_limiter->record_usage(1);

		if (is_wp_error($embedding)) {
			$this->rate_limiter->record_failure($embedding);
			$this->logger->log('Embedding generation failed: ' . $embedding->get_error_message(), 'error');
			return $embedding;
		}

		$this->rate_limiter->record_success();

		// Cache the result
		$this->embedding_cache[$cache_key] = $embedding;

		$this->logger->log('Generated embedding for text: ' . substr($text, 0, 50) . '...', 'debug');

		return $embedding;
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
		return $this->is_enabled() && $this->ai_service->is_available() && $this->ai_service->supports_embeddings();
	}
}
