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
	 * @var AIPS_Embeddings_Repository|null Embeddings repository
	 */
	private $embeddings_repo;

	/**
	 * @var AIPS_Author_Topics_Repository|null Author topics repository
	 */
	private $topics_repo;

	/**
	 * @var AIPS_Similarity_Evaluator|null Similarity evaluator
	 */
	private $similarity_evaluator;

	/**
	 * @var array Cache for embeddings to avoid redundant API calls
	 */
	private $embedding_cache;
	
	/**
	 * Initialize the embeddings service.
	 *
	 * @param AIPS_AI_Service_Interface|null     $ai_service AI Service instance.
	 * @param AIPS_Logger_Interface|null         $logger Logger instance.
	 * @param AIPS_Config|null                   $config Config instance.
	 * @param AIPS_Embeddings_Rate_Limiter|null  $rate_limiter Rate limiter instance.
	 * @param AIPS_Embeddings_Repository|null    $embeddings_repo Embeddings repository.
	 * @param AIPS_Author_Topics_Repository|null $topics_repo Topics repository.
	 * @param AIPS_Similarity_Evaluator|null     $similarity_evaluator Similarity evaluator.
	 */
	public function __construct(
		?AIPS_AI_Service_Interface $ai_service = null,
		?AIPS_Logger_Interface $logger = null,
		?AIPS_Config $config = null,
		?AIPS_Embeddings_Rate_Limiter $rate_limiter = null,
		?AIPS_Embeddings_Repository $embeddings_repo = null,
		?AIPS_Author_Topics_Repository $topics_repo = null,
		?AIPS_Similarity_Evaluator $similarity_evaluator = null
	) {
		$container = AIPS_Container::get_instance();
		$this->ai_service           = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
		$this->logger               = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
		$this->config               = $config ?: ($container->has(AIPS_Config::class) ? $container->make(AIPS_Config::class) : AIPS_Config::get_instance());
		$this->rate_limiter         = $rate_limiter ?: ($container->has(AIPS_Embeddings_Rate_Limiter::class) ? $container->make(AIPS_Embeddings_Rate_Limiter::class) : new AIPS_Embeddings_Rate_Limiter($this->config, $this->logger));
		$this->embeddings_repo      = $embeddings_repo ?: ($container->has(AIPS_Embeddings_Repository::class) ? $container->make(AIPS_Embeddings_Repository::class) : null);
		$this->topics_repo          = $topics_repo ?: ($container->has(AIPS_Author_Topics_Repository::class) ? $container->make(AIPS_Author_Topics_Repository::class) : null);
		$this->similarity_evaluator = $similarity_evaluator ?: ($container->has(AIPS_Similarity_Evaluator::class) ? $container->make(AIPS_Similarity_Evaluator::class) : null);
		$this->embedding_cache      = array();
	}

	/**
	 * Lazy getter for embeddings repository.
	 *
	 * @return AIPS_Embeddings_Repository
	 */
	public function get_embeddings_repository(): AIPS_Embeddings_Repository {
		if ($this->embeddings_repo === null) {
			$container = AIPS_Container::get_instance();
			$this->embeddings_repo = $container->has(AIPS_Embeddings_Repository::class)
				? $container->make(AIPS_Embeddings_Repository::class)
				: new AIPS_Embeddings_Repository();
		}
		return $this->embeddings_repo;
	}

	/**
	 * Lazy getter for topics repository.
	 *
	 * @return AIPS_Author_Topics_Repository
	 */
	public function get_topics_repository(): AIPS_Author_Topics_Repository {
		if ($this->topics_repo === null) {
			$container = AIPS_Container::get_instance();
			$this->topics_repo = $container->has(AIPS_Author_Topics_Repository::class)
				? $container->make(AIPS_Author_Topics_Repository::class)
				: new AIPS_Author_Topics_Repository();
		}
		return $this->topics_repo;
	}

	/**
	 * Lazy getter for similarity evaluator.
	 *
	 * @return AIPS_Similarity_Evaluator
	 */
	public function get_similarity_evaluator(): AIPS_Similarity_Evaluator {
		if ($this->similarity_evaluator === null) {
			$container = AIPS_Container::get_instance();
			$this->similarity_evaluator = $container->has(AIPS_Similarity_Evaluator::class)
				? $container->make(AIPS_Similarity_Evaluator::class)
				: new AIPS_Similarity_Evaluator($this->config, $this->get_embeddings_repository(), $this);
		}
		return $this->similarity_evaluator;
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
	 * Get the active embedding model slug.
	 *
	 * @return string Model name.
	 */
	public function get_active_model(): string {
		$ai_config = $this->config->get_ai_config();
		$model     = !empty($ai_config['embeddings_model']) ? $ai_config['embeddings_model'] : $this->config->get_option('aips_embeddings_model', 'text-embedding-3-small');
		return !empty($model) ? (string) $model : 'text-embedding-3-small';
	}
	
	/**
	 * Generate an embedding for a text string via the active AI provider.
	 *
	 * Automatically guarded by the rate limiter execution harness, enforcing
	 * auto-cooldown detection, rolling quotas, and error recording.
	 *
	 * @param string $text The text to generate an embedding for.
	 * @param array  $options Optional. Additional options for embedding generation.
	 * @return array|WP_Error The embedding vector (float[]) or WP_Error on failure.
	 */
	public function generate_embedding($text, $options = array()) {
		if (!$this->is_enabled()) {
			return new WP_Error('embeddings_disabled', __('The vector embeddings system is disabled in settings.', 'ai-post-scheduler'));
		}

		if (empty($text)) {
			return new WP_Error('empty_text', __('Cannot generate embedding for empty text.', 'ai-post-scheduler'));
		}

		// Check in-memory cache (cached embeddings do not count towards quota)
		$mem_key = md5($text);
		if (isset($this->embedding_cache[$mem_key])) {
			return $this->embedding_cache[$mem_key];
		}

		$default_env_id = (string) $this->config->get_option('aips_embeddings_env_id');
		$default_model  = (string) $this->config->get_option('aips_embeddings_model');

		if (!empty($default_env_id) && !isset($options['embeddings_env_id'])) {
			$options['embeddings_env_id'] = $default_env_id;
		}

		if (!empty($default_model) && !isset($options['model'])) {
			$options['model'] = $default_model;
		}

		$model = !empty($options['model']) ? (string) $options['model'] : $this->get_active_model();

		// Check persistent vector cache (transients / object cache)
		$persistent_cache_enabled = (bool) $this->config->get_option('aips_embeddings_persistent_cache_enabled', true);
		$transient_key            = 'aips_raw_emb_' . md5($text . '_' . $model);

		if ($persistent_cache_enabled) {
			$cached_transient = get_transient($transient_key);
			if (is_array($cached_transient) && !empty($cached_transient)) {
				$this->embedding_cache[$mem_key] = $cached_transient;
				return $cached_transient;
			}
		}

		// Execute through the rate limiter's resilience harness
		$embedding = $this->rate_limiter->execute(
			function () use ($text, $options) {
				return $this->ai_service->generate_embedding($text, $options);
			},
			function ($result) use ($mem_key, $transient_key, $persistent_cache_enabled, $text) {
				$this->embedding_cache[$mem_key] = $result;
				if ($persistent_cache_enabled && is_array($result) && !empty($result)) {
					set_transient($transient_key, $result, 7 * DAY_IN_SECONDS);
				}
				$this->logger->log('Generated embedding for text: ' . substr($text, 0, 50) . '...', 'debug');
			},
			function ($error) {
				$this->logger->log('Embedding generation failed: ' . $error->get_error_message(), 'error');
			},
			1
		);

		return $embedding;
	}

	/**
	 * Compute and persist vector embedding for an Author Topic.
	 *
	 * Centralized method handling topic retrieval, text extraction (title + prompt),
	 * vector generation via rate-limited AI provider, persistence to central aips_embeddings
	 * table, and metadata synchronization for backwards compatibility.
	 *
	 * @param int $topic_id Topic ID.
	 * @return array|WP_Error Embedding vector on success, WP_Error on failure.
	 */
	public function compute_topic_embedding(int $topic_id) {
		if (!$this->is_enabled()) {
			return new WP_Error('embeddings_disabled', __('The vector embeddings system is disabled in settings.', 'ai-post-scheduler'));
		}

		$topics_repo = $this->get_topics_repository();
		$topic = $topics_repo ? $topics_repo->get_by_id($topic_id) : null;
		if (!$topic) {
			return new WP_Error('topic_not_found', __('Topic not found.', 'ai-post-scheduler'));
		}

		$text = trim($topic->topic_title);
		if (!empty($topic->topic_prompt)) {
			$text .= ' ' . trim($topic->topic_prompt);
		}

		if (empty($text)) {
			return new WP_Error('empty_topic', __('Topic has no text to embed.', 'ai-post-scheduler'));
		}

		$content_hash    = md5($text);
		$embeddings_repo = $this->get_embeddings_repository();

		// Check if embedding with identical content hash already exists
		if ($embeddings_repo) {
			$existing = $embeddings_repo->get_by_source('topic', $topic_id);
			if ($existing && !empty($existing->content_hash) && $existing->content_hash === $content_hash) {
				$decoded = $embeddings_repo->decode_embedding($existing->embedding, 'topic', $topic_id, $content_hash);
				if (!empty($decoded)) {
					return $decoded;
				}
			}
		}

		// Generate embedding via rate-limited service
		$embedding = $this->generate_embedding($text);
		if (is_wp_error($embedding) || !is_array($embedding)) {
			return $embedding;
		}

		$model      = $this->get_active_model();
		$dimensions = count($embedding);

		// Upsert into central aips_embeddings repository
		if ($embeddings_repo) {
			$embeddings_repo->upsert(
				'topic',
				$topic_id,
				$embedding,
				$model,
				$dimensions,
				$content_hash
			);
		}

		// Sync topic metadata for backwards compatibility
		if ($topics_repo) {
			$metadata = !empty($topic->metadata) ? (is_array($topic->metadata) ? $topic->metadata : json_decode($topic->metadata, true)) : array();
			if (!is_array($metadata)) {
				$metadata = array();
			}
			$metadata['embedding'] = $embedding;
			$topics_repo->update($topic_id, array(
				'metadata' => wp_json_encode($metadata),
			));
		}

		return $embedding;
	}

	/**
	 * Retrieve vector embedding for an Author Topic.
	 *
	 * Checks the primary aips_embeddings repository first (utilizing multi-tier
	 * memory/object/transient caching), and gracefully falls back to legacy
	 * topic metadata if not yet indexed into the central table.
	 *
	 * @param int $topic_id Topic ID.
	 * @return array|null Vector array (float[]) or null if not found.
	 */
	public function get_topic_embedding(int $topic_id): ?array {
		$embeddings_repo = $this->get_embeddings_repository();
		if ($embeddings_repo) {
			$record = $embeddings_repo->get_by_source('topic', (int) $topic_id);
			if ($record && !empty($record->embedding)) {
				$vec = $embeddings_repo->decode_embedding(
					$record->embedding,
					'topic',
					(int) $topic_id,
					!empty($record->content_hash) ? $record->content_hash : ''
				);
				if (is_array($vec) && !empty($vec)) {
					return $vec;
				}
			}
		}

		// Fallback to legacy metadata
		$topics_repo = $this->get_topics_repository();
		if ($topics_repo) {
			$topic = $topics_repo->get_by_id((int) $topic_id);
			if ($topic && !empty($topic->metadata)) {
				$meta = is_array($topic->metadata) ? $topic->metadata : json_decode($topic->metadata, true);
				if (is_array($meta) && !empty($meta['embedding']) && is_array($meta['embedding'])) {
					return $meta['embedding'];
				}
			}
		}

		return null;
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
