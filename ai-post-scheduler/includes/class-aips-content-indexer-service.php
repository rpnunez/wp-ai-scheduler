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
	 * Initialize the content indexer service.
	 */
	public function __construct(
		?AIPS_Embeddings_Repository $embeddings_repo = null,
		?AIPS_Relationships_Repository $relationships_repo = null,
		?AIPS_Embeddings_Service $embeddings_service = null,
		?AIPS_History_Service_Interface $history_service = null,
		?AIPS_Logger_Interface $logger = null,
		?AIPS_Config $config = null,
		?AIPS_Author_Topics_Repository $topics_repo = null
	) {
		$container = AIPS_Container::get_instance();

		$this->embeddings_repo    = $embeddings_repo ?: ($container->has(AIPS_Embeddings_Repository::class) ? $container->make(AIPS_Embeddings_Repository::class) : new AIPS_Embeddings_Repository());
		$this->relationships_repo = $relationships_repo ?: ($container->has(AIPS_Relationships_Repository::class) ? $container->make(AIPS_Relationships_Repository::class) : new AIPS_Relationships_Repository());
		$this->embeddings_service = $embeddings_service ?: ($container->has(AIPS_Embeddings_Service::class) ? $container->make(AIPS_Embeddings_Service::class) : new AIPS_Embeddings_Service());
		$this->history_service    = $history_service ?: ($container->has(AIPS_History_Service_Interface::class) ? $container->make(AIPS_History_Service_Interface::class) : new AIPS_History_Service());
		$this->logger             = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
		$this->config             = $config ?: AIPS_Config::get_instance();
		$this->topics_repo        = $topics_repo ?: ($container->has(AIPS_Author_Topics_Repository::class) ? $container->make(AIPS_Author_Topics_Repository::class) : new AIPS_Author_Topics_Repository());
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
	 * @param int    $topic_id Topic ID.
	 * @param string $title    Topic title.
	 * @return true|WP_Error
	 */
	public function index_topic($topic_id, $title = '') {
		$topic_id = absint($topic_id);

		if (empty($title)) {
			$topic_obj = $this->topics_repo->get_by_id($topic_id);
			if ($topic_obj && !empty($topic_obj->topic_title)) {
				$title = $topic_obj->topic_title;
			}
		}

		if (empty($title)) {
			return new WP_Error('empty_topic', __('Topic not found or empty.', 'ai-post-scheduler'));
		}

		$content_hash = md5($title);
		$embedding    = $this->embeddings_service->generate_embedding($title);

		if (is_wp_error($embedding)) {
			return $embedding;
		}

		$dimensions = count($embedding);
		$ai_config  = $this->config->get_ai_config();
		$model      = !empty($ai_config['model']) ? $ai_config['model'] : 'default';

		$this->embeddings_repo->upsert(
			'topic',
			$topic_id,
			$embedding,
			$model,
			$dimensions,
			$content_hash,
			''
		);

		return true;
	}

	/**
	 * Process a batch of unindexed posts (progressive AJAX chunk runner & WP-Cron worker).
	 *
	 * @param int             $batch_size   Number of posts to vectorize in this slice.
	 * @param int             $last_post_id Cursor pagination: process IDs > this.
	 * @param string[]|string $post_types   Post types to index.
	 * @param string          $post_status  Post status to index.
	 * @return array{success: int, failed: int, last_post_id: int, done: bool, total_indexed: int, total_posts: int, percent: int}
	 */
	public function process_indexing_batch(
		$batch_size = 10,
		$last_post_id = 0,
		$post_types = array('post'),
		$post_status = 'publish'
	) {
		$post_types = (array) $post_types;
		if (empty($post_types)) {
			$post_types = (array) $this->config->get_option('aips_indexer_post_types', array('post'));
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

		try {
			foreach ($post_ids as $post_id) {
				$result = $this->index_post($post_id, true);

				if (is_wp_error($result)) {
					$failed++;
				} else {
					$success++;
				}

				$new_last_id = max($new_last_id, $post_id);
			}
		} finally {
			if ($generated_correlation) {
				AIPS_Correlation_ID::reset();
			}
		}

		$status = $this->get_indexing_status($post_types, $post_status);
		$done   = empty($post_ids) || count($post_ids) < $batch_size || $status['unindexed'] === 0;

		return array(
			'success'       => $success,
			'failed'        => $failed,
			'last_post_id'  => $new_last_id,
			'done'          => $done,
			'total_indexed' => $status['indexed'],
			'total_posts'   => $status['total_posts'],
			'percent'       => $status['percent'],
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
		$post_id = absint($post_id);
		$source  = $this->embeddings_repo->get_by_post_id($post_id);

		if (!$source || empty($source->embedding)) {
			return 0;
		}

		$source_vector = json_decode($source->embedding, true);
		if (!is_array($source_vector)) {
			return 0;
		}

		$post_types = (array) $this->config->get_option('aips_indexer_post_types', array('post'));
		$candidates = $this->embeddings_repo->get_all_for_similarity('post', $post_types, 'publish');

		$candidate_vectors = array();
		foreach ($candidates as $row) {
			$cid = (int) $row->object_id;
			if ($cid === $post_id) {
				continue;
			}
			$vec = json_decode($row->embedding, true);
			if (is_array($vec) && count($vec) === count($source_vector)) {
				$candidate_vectors[] = array(
					'id'        => $cid,
					'embedding' => $vec,
				);
			}
		}

		if (empty($candidate_vectors)) {
			return 0;
		}

		$neighbors = $this->embeddings_service->find_nearest_neighbors($source_vector, $candidate_vectors, $top_k);
		if (!is_array($neighbors)) {
			$neighbors = array();
		}

		$targets = array();
		foreach ($neighbors as $n) {
			if (isset($n['similarity']) && $n['similarity'] >= $min_sim) {
				$targets[] = array(
					'target_type' => 'post',
					'target_id'   => (int) $n['id'],
					'similarity'  => (float) $n['similarity'],
				);
			}
		}

		$this->relationships_repo->sync_for_source('post', $post_id, $targets, 'related_post');
		return count($targets);
	}

	/**
	 * Get indexing status across configured post types.
	 *
	 * @param string[]|string $post_types  Post types to check.
	 * @param string          $post_status Status to filter.
	 * @return array{total_posts: int, indexed: int, unindexed: int, percent: int, post_types: array}
	 */
	public function get_indexing_status($post_types = array('post'), $post_status = 'publish') {
		$post_types  = (array) $post_types;
		$post_status = sanitize_key($post_status);

		if (empty($post_types)) {
			$post_types = (array) $this->config->get_option('aips_indexer_post_types', array('post'));
		}

		$total_posts = 0;
		foreach ($post_types as $pt) {
			$counts = wp_count_posts($pt);
			if (isset($counts->$post_status)) {
				$total_posts += (int) $counts->$post_status;
			}
		}

		$indexed   = $this->embeddings_repo->count_indexed_for_types($post_types, $post_status);
		$unindexed = max(0, $total_posts - $indexed);
		$percent   = $total_posts > 0 ? min(100, (int) round(($indexed / $total_posts) * 100)) : 0;

		return array(
			'total_posts' => $total_posts,
			'indexed'     => $indexed,
			'unindexed'   => $unindexed,
			'percent'     => $percent,
			'post_types'  => $post_types,
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

		if (!$this->config->get_option('aips_auto_index_on_publish', true)) {
			return;
		}

		$post_types = (array) $this->config->get_option('aips_indexer_post_types', array('post'));
		if (!in_array($post->post_type, $post_types, true)) {
			return;
		}

		if ('publish' === $post->post_status) {
			$this->index_post($post_id, true);
		} else {
			// If post moved to draft/trash, delete its relationships and embedding
			$this->embeddings_repo->delete_by_post_id($post_id);
			$this->relationships_repo->delete_for_object('post', $post_id);
		}
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
