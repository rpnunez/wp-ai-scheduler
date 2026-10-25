<?php
/**
 * Content Indexer Service
 *
 * Centralized semantic indexing engine for posts, custom post types, and topics.
 * Manages dual-engine batch processing (interactive AJAX & WP-Cron), vectorization,
 * relationship precomputation, and History API telemetry.
 *
 * @package AI_Post_Scheduler
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Content_Indexer_Service
 *
 * Orchestrates indexing WordPress content into the unified vector store.
 */
class AIPS_Content_Indexer_Service {

	/**
	 * Option flag set while an administrator has paused the background queue.
	 */
	const QUEUE_PAUSED_OPTION = 'aips_indexer_queue_paused';

	/**
	 * @var AIPS_Embeddings_Repository
	 */
	private $embeddings_repo;

	/**
	 * @var AIPS_Relationships_Repository
	 */
	private $relationships_repo;

	/**
	 * @var AIPS_Embeddings_Service
	 */
	private $embeddings_service;

	/**
	 * @var AIPS_History_Service_Interface
	 */
	private $history_service;

	/**
	 * @var AIPS_Logger_Interface
	 */
	private $logger;

	/**
	 * @var AIPS_Author_Topics_Repository
	 */
	private $topics_repo;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * @var AIPS_Similarity_Evaluator
	 */
	private $similarity_evaluator;

	/**
	 * @var AIPS_Relationship_Builder|null
	 */
	private $relationship_builder = null;

	/**
	 * Initialize the content indexer service.
	 */
	public function __construct(
		?AIPS_Embeddings_Repository $embeddings_repo = null,
		?AIPS_Relationships_Repository $relationships_repo = null,
		?AIPS_Embeddings_Service $embeddings_service = null,
		?AIPS_History_Service_Interface $history_service = null,
		?AIPS_Logger_Interface $logger = null,
		?AIPS_Config $config = null,
		?AIPS_Author_Topics_Repository $topics_repo = null,
		?AIPS_Similarity_Evaluator $similarity_evaluator = null
	) {
		$container = AIPS_Container::get_instance();

		$this->embeddings_repo      = $embeddings_repo ?: ($container->has(AIPS_Embeddings_Repository::class) ? $container->make(AIPS_Embeddings_Repository::class) : new AIPS_Embeddings_Repository());
		$this->relationships_repo   = $relationships_repo ?: ($container->has(AIPS_Relationships_Repository::class) ? $container->make(AIPS_Relationships_Repository::class) : new AIPS_Relationships_Repository());
		$this->embeddings_service   = $embeddings_service ?: ($container->has(AIPS_Embeddings_Service::class) ? $container->make(AIPS_Embeddings_Service::class) : new AIPS_Embeddings_Service());
		$this->history_service      = $history_service ?: ($container->has(AIPS_History_Service_Interface::class) ? $container->make(AIPS_History_Service_Interface::class) : new AIPS_History_Service());
		$this->logger               = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
		$this->config               = $config ?: AIPS_Config::get_instance();
		$this->topics_repo          = $topics_repo ?: ($container->has(AIPS_Author_Topics_Repository::class) ? $container->make(AIPS_Author_Topics_Repository::class) : new AIPS_Author_Topics_Repository());
		$this->similarity_evaluator = $similarity_evaluator ?: ($container->has(AIPS_Similarity_Evaluator::class) ? $container->make(AIPS_Similarity_Evaluator::class) : new AIPS_Similarity_Evaluator($this->config, $this->embeddings_repo, $this->embeddings_service));
	}

	/**
	 * Index a single WordPress post or Custom Post Type.
	 *
	 * Generates embedding vector, stores it in aips_embeddings, precomputes relationships,
	 * and logs the event to History.
	 *
	 * @param int  $post_id WordPress post ID.
	 * @param bool $compute_relationships Whether to automatically compute related posts.
	 * @return true|WP_Error
	 */
	public function index_post($post_id, $compute_relationships = true) {
		if (!$this->embeddings_service->is_enabled()) {
			return new WP_Error('embeddings_disabled', __('The vector embeddings system is disabled in settings.', 'ai-post-scheduler'));
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

		// Skip if text hasn't changed
		if ($existing && !empty($existing->content_hash) && $existing->content_hash === $content_hash) {
			if ($compute_relationships) {
				$this->recompute_relationships_for_post($post_id);
			}
			return true;
		}

		$start_time = microtime(true);
		$ai_config  = $this->config->get_ai_config();
		$configured_model = (string) $this->config->get_option('aips_embeddings_model');
		if ($configured_model === '') {
			$configured_model = !empty($ai_config['model']) ? (string) $ai_config['model'] : 'text-embedding-3-small';
		}
		$model    = $configured_model;
		$provider = (string) $this->config->get_option('aips_embeddings_provider');
		if ($provider === '') {
			$provider = 'default';
		}

		// Rich per-step logging (ai_request/ai_response/intermediate activities) can
		// balloon the history log for large reindex runs, so it is opt-in via setting.
		$verbose = (bool) $this->config->get_option('aips_indexer_verbose_history', false);

		// Create History container at the start of indexing. Only columns actually
		// persisted by AIPS_History_Repository::create() are passed; anything else
		// belongs in individual log entries below.
		$container = $this->history_service->create('content_indexing', array(
			'post_id'         => $post_id,
			'post_type'       => $post->post_type,
			'generated_title' => $post->post_title,
			'creation_method' => 'content_indexing',
			'correlation_id'  => AIPS_Correlation_ID::get(),
		));

		// Trigger entries add two log rows per indexed post, so (like the other
		// per-step logging) they are only written when verbose history is enabled.
		if ($verbose) {
			AIPS_Generation_Trigger::record(
				$container,
				array(
					'event'   => __('Content indexing', 'ai-post-scheduler'),
					'post_id' => $post_id,
				),
				AIPS_Generation_Trigger::detect_creation_method()
			);
		}

		$container->record(
			'activity',
			sprintf(__('Started content indexing for post #%d: "%s"', 'ai-post-scheduler'), $post_id, $post->post_title),
			array(
				'post_id'        => $post_id,
				'post_type'      => $post->post_type,
				'title'          => $post->post_title,
				'content_length' => mb_strlen($text),
			)
		);

		if ($verbose) {
			$text_sample = mb_strlen($text) > 400 ? mb_substr($text, 0, 400) . '...' : $text;

			$container->record(
				'ai_request',
				sprintf(__('AI request: Sent post #%d content for embedding generation', 'ai-post-scheduler'), $post_id),
				array(
					'component'   => 'embeddings',
					'model'       => $model,
					'provider'    => $provider,
					'text_sample' => $text_sample,
					'text_length' => mb_strlen($text),
					'post_id'     => $post_id,
				),
				null,
				array('component' => 'embeddings')
			);
		}

		$embedding = $this->embeddings_service->generate_embedding($text);

		if (is_wp_error($embedding)) {
			$duration      = round(microtime(true) - $start_time, 3);
			$error_message = $embedding->get_error_message();
			$error_details = array(
				'post_id'    => $post_id,
				'error_code' => $embedding->get_error_code(),
				'duration'   => $duration,
			);

			$container->complete_failure($error_message, $error_details);

			$this->logger->log(
				sprintf('Failed to generate embedding for post %d (%s): %s', $post_id, $post->post_type, $error_message),
				'error'
			);
			return $embedding;
		}

		$dimensions = count($embedding);
		$duration   = round(microtime(true) - $start_time, 3);

		if ($verbose) {
			$sample_preview = array();
			for ($i = 0; $i < min(5, $dimensions); $i++) {
				$sample_preview[] = round($embedding[$i], 6);
			}

			$container->record(
				'ai_response',
				sprintf(
					/* translators: 1: vector dimensions count, 2: duration seconds. */
					__('AI response: Received %1$d-dimensional embedding vector in %2$ss', 'ai-post-scheduler'),
					$dimensions,
					$duration
				),
				null,
				array(
					'component'      => 'embeddings',
					'dimensions'     => $dimensions,
					'model'          => $model,
					'provider'       => $provider,
					'duration'       => $duration,
					'sample_vector'  => $sample_preview,
					'vector_summary' => sprintf(
						/* translators: 1: total dimensions, 2: comma-separated preview values. */
						__('5 of %1$d dimensions preview: [%2$s, ...]', 'ai-post-scheduler'),
						$dimensions,
						implode(', ', $sample_preview)
					),
				),
				array('component' => 'embeddings')
			);
		}

		$persisted = $this->embeddings_repo->upsert(
			'post',
			$post_id,
			$embedding,
			$model,
			$dimensions,
			$content_hash,
			$post->post_type
		);

		if ($persisted === false) {
			$error_message = sprintf(
				/* translators: %d: WordPress post ID. */
				__('Could not save the embedding vector for post #%d.', 'ai-post-scheduler'),
				$post_id
			);
			$error = new WP_Error('embedding_persistence_failed', $error_message);

			$container->complete_failure(
				$error_message,
				array(
					'post_id'    => $post_id,
					'dimensions' => $dimensions,
					'model'      => $model,
					'provider'   => $provider,
				)
			);
			$this->logger->log(
				sprintf('Failed to persist embedding for post %d (%s).', $post_id, $post->post_type),
				'error'
			);

			return $error;
		}

		if ($verbose) {
			$container->record(
				'activity',
				sprintf(
					/* translators: 1: dimensions, 2: post id. */
					__('Saved %1$d-dimension vector to embeddings database for post #%2$d', 'ai-post-scheduler'),
					$dimensions,
					$post_id
				),
				array(
					'post_id'    => $post_id,
					'dimensions' => $dimensions,
					'model'      => $model,
				)
			);
		}

		$rel_count = 0;
		if ($compute_relationships) {
			$rel_count = (int) $this->recompute_relationships_for_post($post_id);
			if ($verbose) {
				$container->record(
					'info',
					sprintf(__('Recomputed top related post relationships (%d matches)', 'ai-post-scheduler'), $rel_count),
					array(
						'post_id'             => $post_id,
						'relationships_saved' => $rel_count,
					)
				);
			}
		}

		$total_duration = round(microtime(true) - $start_time, 3);
		$completion_details = array(
			'dimensions' => $dimensions,
			'duration'   => $total_duration,
			'model'      => $model,
			'provider'   => $provider,
		);
		if ($compute_relationships) {
			$completion_details['relationships_saved'] = $rel_count;
		}

		// complete_success only merges columns whitelisted by the repository update;
		// dimensions/duration/relationships live in the final activity log entry.
		$completion_message = $compute_relationships
			? sprintf(
				/* translators: 1: dimensions, 2: total duration seconds, 3: related-post count. */
				__('Indexed post: %1$d dims, %2$ss, %3$d related', 'ai-post-scheduler'),
				$dimensions,
				$total_duration,
				$rel_count
			)
			: sprintf(
				/* translators: 1: dimensions, 2: total duration seconds. */
				__('Indexed post: %1$d dims, %2$ss', 'ai-post-scheduler'),
				$dimensions,
				$total_duration
			);
		$container->record(
			'activity',
			$completion_message,
			$completion_details
		);

		$container->complete_success(array(
			'status'          => 'completed',
			'generated_title' => $post->post_title,
		));

		$this->logger->log(
			sprintf('Indexed post %d (%s, %d dims) in %ss.', $post_id, $post->post_type, $dimensions, $total_duration),
			'debug'
		);

		return true;
	}

	/**
	 * Index a single Author Topic.
	 *
	 * Computes the embedding vector via AIPS_Embeddings_Service, persists it,
	 * and precomputes cross-entity similarity against published posts to flag
	 * potential keyword cannibalization.
	 *
	 * @param int    $topic_id Topic ID.
	 * @param string $title    Optional topic title (kept for signature compatibility).
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function index_topic($topic_id, $title = '') {
		$topic_id = absint($topic_id);
		if ($topic_id <= 0) {
			return new WP_Error('invalid_topic_id', __('Invalid topic ID.', 'ai-post-scheduler'));
		}

		$embedding = $this->embeddings_service->compute_topic_embedding($topic_id);
		if (is_wp_error($embedding)) {
			return $embedding;
		}

		if (!is_array($embedding) || empty($embedding)) {
			return new WP_Error('empty_embedding', __('Failed to compute topic embedding.', 'ai-post-scheduler'));
		}

		$dimensions = count($embedding);

		// Precompute cross-entity similarity against published posts to flag potential cannibalization
		$post_vectors = $this->embeddings_repo->get_all_for_similarity_by_type('post', 'publish');
		if (!empty($post_vectors)) {
			$min_sim = (float) $this->config->get_option('aips_indexer_similarity_threshold', 0.65);
			$matches = array();
			foreach ($post_vectors as $pv) {
				$p_vec = $this->embeddings_repo->decode_embedding($pv->embedding);
				if (!empty($p_vec) && count($p_vec) === $dimensions) {
					$sim = $this->similarity_evaluator->cosine_similarity($embedding, $p_vec);
					if ($sim >= $min_sim) {
						$matches[] = array(
							'target_type' => 'post',
							'target_id'   => (int) $pv->post_id,
							'similarity'  => (float) $sim,
						);
					}
				}
			}
			if (!empty($matches)) {
				$this->relationships_repo->sync_for_source('topic', $topic_id, $matches, 'similar');
			}
		}

		return true;
	}

	/**
	 * Process a batch of unindexed items (posts or topics).
	 *
	 * @param int             $batch_size   Number of items to vectorize in this slice.
	 * @param int             $last_post_id Cursor pagination for posts: process IDs > this.
	 * @param string[]|string $post_types   Post types to index.
	 * @param string          $post_status  Post status to index.
	 * @param string          $entity_scope Entity scope ('all', 'posts', 'topics'). Default 'all'.
	 * @return array
	 */
	public function process_indexing_batch(
		$batch_size = 10,
		$last_post_id = 0,
		$post_types = array('post'),
		$post_status = 'publish',
		$entity_scope = 'all'
	) {
		$post_types = (array) $post_types;
		if (empty($post_types)) {
			$post_types = (array) $this->config->get_option('aips_indexer_post_types', array('post'));
		}

		$rate_limiter = $this->embeddings_service->get_rate_limiter();
		$cooldown     = $rate_limiter->get_cooldown_status();

		if ($cooldown['is_paused']) {
			$status = $this->get_indexing_status($post_types, $post_status);
			return array(
				'success'             => 0,
				'failed'              => 0,
				'last_post_id'        => $last_post_id,
				'done'                => true,
				'total_indexed'       => $status['indexed'],
				'total_posts'         => $status['total_posts'],
				'percent'             => $status['percent'],
				'status'              => $status,
				'rate_limit_exceeded' => true,
				'rate_limit_error'    => array(
					'message' => $cooldown['reason'],
					'data'    => $cooldown,
				),
				'rate_limits'         => $status['rate_limits'],
			);
		}

		// Entity Scope: Topics only
		if ($entity_scope === 'topics') {
			$topic_ids = $this->embeddings_repo->get_unindexed_topic_ids($batch_size);
			$success   = 0;
			$failed    = 0;
			$rate_err  = null;

			foreach ($topic_ids as $tid) {
				$res = $this->index_topic($tid);
				if (is_wp_error($res)) {
					$failed++;
					$rate_limiter->record_failure($res);
					if ($rate_limiter->is_rate_limit_or_exhaustion_error($res)) {
						$rate_err = array('message' => $res->get_error_message());
						break;
					}
				} else {
					$success++;
					$rate_limiter->record_success();
				}
			}

			$status = $this->get_indexing_status($post_types, $post_status);
			return array(
				'success'             => $success,
				'failed'              => $failed,
				'last_post_id'        => 0,
				'done'                => empty($topic_ids) || count($topic_ids) < $batch_size || !empty($rate_err),
				'total_indexed'       => $status['indexed_topics'],
				'total_posts'         => $status['total_topics'],
				'percent'             => $status['topics_percent'],
				'status'              => $status,
				'rate_limit_exceeded' => !empty($rate_err),
				'rate_limit_error'    => $rate_err,
				'rate_limits'         => $status['rate_limits'],
				'entity_scope'        => 'topics',
			);
		}

		$post_ids = $this->embeddings_repo->get_unindexed_post_ids(
			$batch_size,
			$last_post_id,
			$post_types,
			$post_status
		);

		$success     = 0;
		$failed      = 0;
		$new_last_id = $last_post_id;

		$correlation_id        = AIPS_Correlation_ID::get();
		$generated_correlation = false;
		if (empty($correlation_id)) {
			$correlation_id        = AIPS_Correlation_ID::generate();
			$generated_correlation = true;
		}

		$rate_limit_error = null;
		$indexed_ids      = array();

		try {
			foreach ($post_ids as $post_id) {
				// Relationships are computed once for the whole batch below, not per post.
				$result = $this->index_post($post_id, false);

				if (is_wp_error($result)) {
					$failed++;
					$cooldown_res = $rate_limiter->record_failure($result);

					if ($rate_limiter->is_rate_limit_or_exhaustion_error($result) || $result->get_error_code() === 'rate_limit_exceeded' || $result->get_error_code() === 'embeddings_cooldown_active') {
						$rate_limit_error = array(
							'message' => $result->get_error_message(),
							'data'    => $result->get_error_data(),
						);
						break;
					}
				} else {
					$rate_limiter->record_success();
					$success++;
					$indexed_ids[] = $post_id;
				}

				$new_last_id = max($new_last_id, $post_id);
			}

			$this->recompute_relationships_for_posts($indexed_ids);
		} finally {
			if ($generated_correlation) {
				AIPS_Correlation_ID::reset();
			}
		}

		$status = $this->get_indexing_status($post_types, $post_status);

		if ($entity_scope === 'all' && empty($rate_limit_error)) {
			if (empty($post_ids) || count($post_ids) < $batch_size || $status['unindexed'] === 0) {
				$topic_batch = $batch_size - $success;
				if ($topic_batch > 0 && $status['unindexed_topics'] > 0) {
					$topic_ids = $this->embeddings_repo->get_unindexed_topic_ids($topic_batch);
					foreach ($topic_ids as $tid) {
						$res = $this->index_topic($tid);
						if (is_wp_error($res)) {
							$failed++;
							$rate_limiter->record_failure($res);
							if ($rate_limiter->is_rate_limit_or_exhaustion_error($res)) {
								$rate_limit_error = array('message' => $res->get_error_message());
								break;
							}
						} else {
							$success++;
							$rate_limiter->record_success();
						}
					}
					$status = $this->get_indexing_status($post_types, $post_status);
				}
			}
			$done = ($status['unindexed'] === 0 && $status['unindexed_topics'] === 0) || !empty($rate_limit_error);
		} else {
			$done = empty($post_ids) || count($post_ids) < $batch_size || $status['unindexed'] === 0 || !empty($rate_limit_error);
		}

		return array(
			'success'             => $success,
			'failed'              => $failed,
			'last_post_id'        => $new_last_id,
			'done'                => $done,
			'total_indexed'       => $status['indexed'],
			'total_posts'         => $status['total_posts'],
			'percent'             => $status['percent'],
			'status'              => $status,
			'rate_limit_exceeded' => !empty($rate_limit_error),
			'rate_limit_error'    => $rate_limit_error,
			'rate_limits'         => $status['rate_limits'],
			'entity_scope'        => $entity_scope,
		);
	}

	/**
	 * Recompute and persist top-K related post relationships for a given post.
	 *
	 * @param int   $post_id WordPress post ID.
	 * @param int   $top_k   Number of top neighbors to precompute.
	 * @param float $min_sim Minimum similarity threshold.
	 * @return int Number of relationships saved.
	 */
	public function recompute_relationships_for_post($post_id, $top_k = 15, $min_sim = 0.50) {
		$saved = $this->recompute_relationships_for_posts(array(absint($post_id)), $top_k, $min_sim);

		return isset($saved[absint($post_id)]) ? (int) $saved[absint($post_id)] : 0;
	}

	/**
	 * Recompute related-post relationships for several posts in one pass over the
	 * stored vectors. Prefer this over calling recompute_relationships_for_post()
	 * in a loop: the cost of reading and decoding every vector is shared by the
	 * whole batch (see AIPS_Relationship_Builder).
	 *
	 * @param int[] $post_ids Source post IDs.
	 * @param int   $top_k    Number of top neighbors to precompute.
	 * @param float $min_sim  Minimum similarity threshold.
	 * @return array<int,int> Source post ID => relationships saved.
	 */
	public function recompute_relationships_for_posts(array $post_ids, $top_k = 15, $min_sim = 0.50) {
		if (empty($post_ids)) {
			return array();
		}

		return $this->get_relationship_builder()->compute_for_posts($post_ids, (int) $top_k, (float) $min_sim);
	}

	/**
	 * @return AIPS_Relationship_Builder
	 */
	private function get_relationship_builder() {
		if ($this->relationship_builder === null) {
			$this->relationship_builder = new AIPS_Relationship_Builder($this->embeddings_repo, $this->relationships_repo, $this->config);
		}

		return $this->relationship_builder;
	}

	/**
	 * Check if a post is within the configured indexing scope.
	 *
	 * @param int|WP_Post $post Post ID or WP_Post object.
	 * @return bool True if within scope, false otherwise.
	 */
	public function is_post_in_scope($post): bool {
		$post = get_post($post);
		if (!$post) {
			return false;
		}

		$scope = (string) $this->config->get_option('aips_embeddings_scope', 'aips_only');

		if ('aips_only' === $scope) {
			$meta = get_post_meta($post->ID, '_aips_generated_post', true);
			return !empty($meta);
		}

		if ('date_range' === $scope) {
			$date_after = (string) $this->config->get_option('aips_embeddings_date_after', '');
			$date_days  = (int) $this->config->get_option('aips_embeddings_date_days', 30);

			$raw_date  = $post->post_date_gmt && '0000-00-00 00:00:00' !== $post->post_date_gmt ? $post->post_date_gmt : $post->post_date;
			$dt        = AIPS_DateTime::fromMysqlOrNull($raw_date);
			$post_time = $dt ? $dt->timestamp() : 0;

			if (!empty($date_after)) {
				try {
					$after_time = AIPS_DateTime::fromDate($date_after)->timestamp();
					return $post_time >= $after_time;
				} catch (\Exception $e) {
					// Invalid date fallback
				}
			}

			if ($date_days > 0) {
				$cutoff = AIPS_DateTime::now()->timestamp() - ($date_days * DAY_IN_SECONDS);
				return $post_time >= $cutoff;
			}
		}

		return true; // 'all' scope
	}

	/**
	 * Get indexing status across configured post types and scope.
	 *
	 * @param string[]|string $post_types  Post types to check.
	 * @param string          $post_status Status to filter.
	 * @return array
	 */
	public function get_indexing_status($post_types = array('post'), $post_status = 'publish') {
		$post_types  = (array) $post_types;
		$post_status = sanitize_key($post_status);

		if (empty($post_types)) {
			$post_types = (array) $this->config->get_option('aips_indexer_post_types', array('post'));
		}

		$scope       = (string) $this->config->get_option('aips_embeddings_scope', 'aips_only');
		$total_posts = $this->embeddings_repo->count_total_posts_for_scope($post_types, $post_status, $scope);
		$indexed     = $this->embeddings_repo->count_indexed_for_types($post_types, $post_status, $scope);
		$unindexed   = max(0, $total_posts - $indexed);
		$percent     = $total_posts > 0 ? min(100, (int) round(($indexed / $total_posts) * 100)) : 0;

		$total_topics     = $this->embeddings_repo->get_total_topic_count();
		$unindexed_topics = $this->embeddings_repo->get_unindexed_topic_count();
		$indexed_topics   = max(0, $total_topics - $unindexed_topics);
		$topics_percent   = $total_topics > 0 ? min(100, (int) round(($indexed_topics / $total_topics) * 100)) : 0;

		$rate_limiter = $this->embeddings_service->get_rate_limiter();
		$usage_stats  = $rate_limiter->get_usage_stats();

		return array(
			'total_posts'        => $total_posts,
			'indexed'            => $indexed,
			'unindexed'          => $unindexed,
			'percent'            => $percent,
			'total_topics'       => $total_topics,
			'indexed_topics'     => $indexed_topics,
			'unindexed_topics'   => $unindexed_topics,
			'topics_percent'     => $topics_percent,
			'posts'              => array(
				'total'     => $total_posts,
				'indexed'   => $indexed,
				'unindexed' => $unindexed,
				'percent'   => $percent,
			),
			'topics'             => array(
				'total'     => $total_topics,
				'indexed'   => $indexed_topics,
				'unindexed' => $unindexed_topics,
				'percent'   => $topics_percent,
			),
			'combined'           => array(
				'total'     => $total_posts + $total_topics,
				'indexed'   => $indexed + $indexed_topics,
				'unindexed' => $unindexed + $unindexed_topics,
				'percent'   => ($total_posts + $total_topics) > 0 ? min(100, (int) round((($indexed + $indexed_topics) / ($total_posts + $total_topics)) * 100)) : 0,
			),
			'post_types'         => $post_types,
			'scope'              => $scope,
			'embeddings_enabled' => $this->embeddings_service->is_enabled(),
			'rate_limits'        => $usage_stats,
		);
	}

	/**
	 * Clear all embeddings and precomputed relationships.
	 *
	 * @param string $object_type Optional entity filter.
	 * @return void
	 */
	public function clear_index($object_type = '') {
		$this->embeddings_repo->clear_all($object_type);
		$this->relationships_repo->clear_all();
		$this->embeddings_service->clear_cache();

		if (empty($object_type) || 'post' === $object_type) {
			delete_option('aips_pending_index_queue');
		}
		if (empty($object_type) || 'topic' === $object_type) {
			delete_option('aips_pending_topic_index_queue');
		}

		if (empty($object_type)) {
			if (function_exists('as_unschedule_all_actions')) {
				as_unschedule_all_actions('aips_process_pending_indexer_queue', array(), 'aips-indexer');
			}
			wp_clear_scheduled_hook('aips_process_pending_indexer_queue');
		}
	}

	/**
	 * Hook callback for save_post / transition_post_status to auto-index published posts.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function on_post_save($post_id, $post) {
		if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
			return;
		}

		/**
		 * Skip semantic re-indexing for this save.
		 *
		 * Bulk link insertion returns true here: adding an <a> tag does not
		 * change a post's text, and recomputing relationships on every edit
		 * of a bulk run would rescan every stored vector each time.
		 *
		 * @param bool $skip    Default false.
		 * @param int  $post_id Post being saved.
		 */
		if (apply_filters('aips_content_indexer_skip_post_save', false, $post_id)) {
			return;
		}

		if (!$this->embeddings_service->is_enabled()) {
			return;
		}

		if (!$this->config->get_option('aips_auto_index_on_publish', true)) {
			return;
		}

		$post_types = (array) $this->config->get_option('aips_indexer_post_types', array('post'));
		if (!in_array($post->post_type, $post_types, true)) {
			return;
		}

		// Enforce scope check on real-time continuous indexing
		if (!$this->is_post_in_scope($post)) {
			return;
		}

		if ('publish' === $post->post_status) {
			$timing = (string) $this->config->get_option('aips_indexer_publish_execution_timing', 'queued');
			if ('immediate' === $timing) {
				$this->index_post($post_id, true);
			} else {
				$this->enqueue_post_for_indexing($post_id);
			}
		} else {
			// If post moved to draft/trash, delete its relationships and embedding
			$this->embeddings_repo->delete_by_post_id($post_id);
			$this->relationships_repo->delete_for_object('post', $post_id);
		}
	}

	/**
	 * Buffer multiple WordPress post IDs into the debounced background indexing queue in a single operation.
	 *
	 * @param array $post_ids Array of post IDs.
	 * @return void
	 */
	public function enqueue_posts_for_indexing(array $post_ids): void {
		$clean_ids = array();
		foreach ($post_ids as $id) {
			$id = absint($id);
			if ($id > 0) {
				$clean_ids[] = $id;
			}
		}

		if (empty($clean_ids)) {
			return;
		}

		$queue  = (array) get_option('aips_pending_index_queue', array());
		$merged = array_values(array_unique(array_merge($queue, $clean_ids)));

		if ($merged !== $queue) {
			update_option('aips_pending_index_queue', $merged, false);
		}

		// Schedule debounced worker if not already queued
		$debounce = max(5, (int) $this->config->get_option('aips_indexer_queue_debounce_seconds', 15));
		$this->schedule_queue_worker(time() + $debounce);
	}

	/**
	 * Buffer a WordPress post ID into the debounced background indexing queue.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return void
	 */
	public function enqueue_post_for_indexing(int $post_id): void {
		$this->enqueue_posts_for_indexing(array($post_id));
	}

	/**
	 * Buffer multiple Author Topic IDs into the debounced background indexing queue in a single operation.
	 *
	 * @param array $topic_ids Array of Author Topic IDs.
	 * @return void
	 */
	public function enqueue_topics_for_indexing(array $topic_ids): void {
		$clean_ids = array();
		foreach ($topic_ids as $id) {
			$id = absint($id);
			if ($id > 0) {
				$clean_ids[] = $id;
			}
		}

		if (empty($clean_ids)) {
			return;
		}

		$queue  = (array) get_option('aips_pending_topic_index_queue', array());
		$merged = array_values(array_unique(array_merge($queue, $clean_ids)));

		if ($merged !== $queue) {
			update_option('aips_pending_topic_index_queue', $merged, false);
		}

		// Schedule debounced worker if not already queued
		$debounce = max(5, (int) $this->config->get_option('aips_indexer_queue_debounce_seconds', 15));
		$this->schedule_queue_worker(time() + $debounce);
	}

	/**
	 * Buffer an Author Topic ID into the debounced background indexing queue.
	 *
	 * @param int $topic_id Author Topic ID.
	 * @return void
	 */
	public function enqueue_topic_for_indexing(int $topic_id): void {
		$this->enqueue_topics_for_indexing(array($topic_id));
	}

	/**
	 * Check if the background indexer queue worker is already scheduled.
	 *
	 * Checks Action Scheduler if available, falling back to WP-Cron.
	 *
	 * @return bool
	 */
	public function is_queue_worker_scheduled(): bool {
		if (function_exists('as_has_scheduled_action') && as_has_scheduled_action('aips_process_pending_indexer_queue', array(), 'aips-indexer')) {
			return true;
		}

		return (bool) wp_next_scheduled('aips_process_pending_indexer_queue');
	}

	/**
	 * Whether an administrator has paused the background queue.
	 *
	 * @return bool
	 */
	public function is_queue_paused(): bool {
		return (bool) get_option(self::QUEUE_PAUSED_OPTION, false);
	}

	/**
	 * Pause or unpause the background queue.
	 *
	 * Pausing unschedules the worker; queued items are kept. Unpausing does not
	 * reschedule it: call schedule_queue_worker() afterwards.
	 *
	 * @param bool $paused True to pause.
	 * @return void
	 */
	public function set_queue_paused(bool $paused): void {
		if ($paused) {
			update_option(self::QUEUE_PAUSED_OPTION, 1, false);
			$this->unschedule_queue_worker();
		} else {
			delete_option(self::QUEUE_PAUSED_OPTION);
		}
	}

	/**
	 * Remove the scheduled queue worker from Action Scheduler and WP-Cron.
	 *
	 * @return void
	 */
	public function unschedule_queue_worker(): void {
		if (function_exists('as_unschedule_all_actions')) {
			as_unschedule_all_actions('aips_process_pending_indexer_queue', array(), 'aips-indexer');
		}
		wp_clear_scheduled_hook('aips_process_pending_indexer_queue');
	}

	/**
	 * Drop every queued post and topic (they are re-queued when next saved).
	 *
	 * @return void
	 */
	public function clear_queue(): void {
		delete_option('aips_pending_index_queue');
		delete_option('aips_pending_topic_index_queue');
		$this->unschedule_queue_worker();
		delete_option(self::QUEUE_PAUSED_OPTION);
	}

	/**
	 * Get the state of the background indexer queue.
	 *
	 * @return array{is_running: bool, pending_count: int, pending_posts: int, pending_topics: int}
	 */
	public function get_queue_status(): array {
		$pending_posts  = count((array) get_option('aips_pending_index_queue', array()));
		$pending_topics = count((array) get_option('aips_pending_topic_index_queue', array()));

		return array(
			'is_running'     => $this->is_queue_worker_scheduled(),
			'pending_count'  => $pending_posts + $pending_topics,
			'pending_posts'  => $pending_posts,
			'pending_topics' => $pending_topics,
		);
	}

	/**
	 * Schedule background indexer queue worker via Action Scheduler (if available) or WP-Cron.
	 *
	 * @param int $timestamp Unix timestamp to run.
	 * @return void
	 */
	public function schedule_queue_worker(int $timestamp): void {
		if ($this->is_queue_paused() || $this->is_queue_worker_scheduled()) {
			return;
		}

		if (function_exists('as_schedule_single_action')) {
			as_schedule_single_action($timestamp, 'aips_process_pending_indexer_queue', array(), 'aips-indexer');
		} else {
			wp_schedule_single_event($timestamp, 'aips_process_pending_indexer_queue');
		}
	}

	/**
	 * Process pending post and topic IDs in the background indexing queue in slices.
	 *
	 * Respects quota auto-pause, cooldown status, post types, scope, and rate limits.
	 *
	 * @return array Processed summary results.
	 */
	public function process_pending_indexer_queue(): array {
		if (!$this->embeddings_service->is_enabled()) {
			return array('status' => 'disabled', 'processed' => 0);
		}

		if ($this->is_queue_paused()) {
			return array('status' => 'paused', 'processed' => 0);
		}

		$rate_limiter = $this->embeddings_service->get_rate_limiter();
		$cooldown     = $rate_limiter->get_cooldown_status();

		// If currently in cooldown, reschedule worker for cooldown expiry and yield
		if ($cooldown['is_paused']) {
			$this->schedule_queue_worker($cooldown['paused_until'] + 5);
			return array('status' => 'cooldown_active', 'paused_until' => $cooldown['paused_until']);
		}

		// If approaching hard quota (>=90%), pause queue until daily reset
		$quota_pause = (bool) $this->config->get_option('aips_indexer_quota_pause_enabled', true);
		if ($quota_pause && $rate_limiter->is_approaching_quota(0.90)) {
			$stats = $rate_limiter->get_usage_stats();
			$delay = max(3600, (int) $stats['daily_reset_in'] + 60);
			$this->schedule_queue_worker(time() + $delay);
			$this->logger->info('Embeddings queue auto-paused: approaching API quota limit.');
			return array('status' => 'quota_paused', 'reschedule_in' => $delay);
		}

		$post_queue  = (array) get_option('aips_pending_index_queue', array());
		$topic_queue = (array) get_option('aips_pending_topic_index_queue', array());

		if (empty($post_queue) && empty($topic_queue)) {
			return array('status' => 'empty', 'processed' => 0);
		}

		$batch_size = max(1, min(50, (int) $this->config->get_option('aips_indexer_batch_size', 10)));
		$post_slice = array_slice($post_queue, 0, $batch_size);
		$post_rem   = array_slice($post_queue, count($post_slice));

		$success     = 0;
		$failed      = 0;
		$broke_early = false;
		$indexed_ids = array();

		foreach ($post_slice as $idx => $post_id) {
			$post = get_post($post_id);
			if (!$post || 'publish' !== $post->post_status || !$this->is_post_in_scope($post)) {
				continue;
			}

			// Relationships are computed once for the whole slice below, not per post.
			$result = $this->index_post($post_id, false);

			if (is_wp_error($result)) {
				$failed++;
				$rate_limiter->record_failure($result);

				if ($rate_limiter->is_rate_limit_or_exhaustion_error($result) || $result->get_error_code() === 'rate_limit_exceeded' || $result->get_error_code() === 'embeddings_cooldown_active') {
					$broke_early = true;
					$unprocessed_post_slice = array_slice($post_slice, $idx);
					$post_rem = array_merge($unprocessed_post_slice, $post_rem);
					break;
				}
			} else {
				$rate_limiter->record_success();
				$success++;
				$indexed_ids[] = $post_id;
			}
		}

		$this->recompute_relationships_for_posts($indexed_ids);

		update_option('aips_pending_index_queue', array_values($post_rem), false);

		$topic_rem = $topic_queue;
		if (!$broke_early && !empty($topic_queue)) {
			$remaining_capacity = max(0, $batch_size - count($post_slice));
			if ($remaining_capacity > 0) {
				$topic_slice = array_slice($topic_queue, 0, $remaining_capacity);
				$topic_rem   = array_slice($topic_queue, count($topic_slice));

				foreach ($topic_slice as $idx => $topic_id) {
					$result = $this->index_topic((int) $topic_id);

					if (is_wp_error($result)) {
						$failed++;
						$rate_limiter->record_failure($result);

						if ($rate_limiter->is_rate_limit_or_exhaustion_error($result) || $result->get_error_code() === 'rate_limit_exceeded' || $result->get_error_code() === 'embeddings_cooldown_active') {
							$unprocessed_topic_slice = array_slice($topic_slice, $idx);
							$topic_rem = array_merge($unprocessed_topic_slice, $topic_rem);
							break;
						}
					} else {
						$rate_limiter->record_success();
						$success++;
					}
				}

				update_option('aips_pending_topic_index_queue', array_values($topic_rem), false);
			}
		}

		$total_remaining = count($post_rem) + count($topic_rem);

		// If more items remain and not in cooldown, schedule next batch
		$cooldown = $rate_limiter->get_cooldown_status();
		if ($total_remaining > 0) {
			$next_time = $cooldown['is_paused'] ? ($cooldown['paused_until'] + 5) : (AIPS_DateTime::now()->timestamp() + 5);
			$this->schedule_queue_worker($next_time);
		}

		if ((bool) $this->config->get_option('aips_indexer_queue_notifications_enabled', true)) {
			$this->logger->log(
				sprintf('Processed background indexing queue slice: %d succeeded, %d failed, %d remaining.', $success, $failed, $total_remaining),
				'info'
			);
		}

		return array(
			'status'    => 'processed',
			'success'   => $success,
			'failed'    => $failed,
			'remaining' => $total_remaining,
		);
	}

	/**
	 * Extract plain indexable text from a post.
	 *
	 * @param WP_Post $post Post object.
	 * @return string Clean plain text representation.
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
