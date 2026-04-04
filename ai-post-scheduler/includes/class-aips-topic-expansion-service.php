<?php
/**
 * Topic Expansion Service
 *
 * Uses embeddings to expand approved topics and suggest related topics.
 * Provides semantic understanding of topic relationships.
 *
 * @package AI_Post_Scheduler
 * @since 1.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Topic_Expansion_Service
 *
 * Service for expanding and suggesting topics based on semantic similarity.
 */
class AIPS_Topic_Expansion_Service {
	
	/**
	 * @var AIPS_Embeddings_Service Embeddings service
	 */
	private $embeddings_service;
	
	/**
	 * @var AIPS_Author_Topics_Repository Topics repository
	 */
	private $topics_repository;
	
	/**
	 * @var AIPS_Authors_Repository Authors repository
	 */
	private $authors_repository;
	
	/**
	 * @var AIPS_Logger Logger instance
	 */
	private $logger;

	/**
	 * @var AIPS_History_Service History service for logging
	 */
	private $history_service;

	/**
	 * Initialize the topic expansion service.
	 */
	public function __construct($embeddings_service = null, $topics_repository = null, $logger = null, $authors_repository = null, $history_service = null) {
		$this->embeddings_service = $embeddings_service ?: new AIPS_Embeddings_Service();
		$this->topics_repository = $topics_repository ?: new AIPS_Author_Topics_Repository();
		$this->logger = $logger ?: new AIPS_Logger();
		$this->authors_repository = $authors_repository ?: new AIPS_Authors_Repository();
		$this->history_service = $history_service ?: new AIPS_History_Service();
	}
	
	/**
	 * Compute and store embedding for a topic.
	 *
	 * @param int $topic_id Topic ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function compute_topic_embedding($topic_id) {
		$topic = $this->topics_repository->get_by_id($topic_id);
		
		if (!$topic) {
			return new WP_Error('topic_not_found', __('Topic not found.', 'ai-post-scheduler'));
		}
		
		// Generate embedding for topic title (could also include topic_prompt if available)
		$text = $topic->topic_title;
		if (!empty($topic->topic_prompt)) {
			$text .= ' ' . $topic->topic_prompt;
		}
		
		$embedding = $this->embeddings_service->generate_embedding($text);
		
		if (is_wp_error($embedding)) {
			$this->logger->log('Failed to generate embedding for topic ' . $topic_id . ': ' . $embedding->get_error_message(), 'error');
			return $embedding;
		}
		
		// Store embedding in metadata
		$metadata = !empty($topic->metadata) ? json_decode($topic->metadata, true) : array();
		if (!is_array($metadata)) {
			$metadata = array();
		}
		
		$metadata['embedding'] = $embedding;
		
		$result = $this->topics_repository->update($topic_id, array(
			'metadata' => wp_json_encode($metadata)
		));
		
		if ($result !== false) {
			$this->logger->log('Computed embedding for topic ' . $topic_id, 'debug');
			return true;
		}
		
		return new WP_Error('update_failed', __('Failed to store embedding.', 'ai-post-scheduler'));
	}
	
	/**
	 * Get embedding for a topic.
	 *
	 * @param int $topic_id Topic ID.
	 * @return array|null Embedding vector or null if not found.
	 */
	public function get_topic_embedding($topic_id) {
		$topic = $this->topics_repository->get_by_id($topic_id);
		
		if (!$topic || empty($topic->metadata)) {
			return null;
		}
		
		$metadata = json_decode($topic->metadata, true);
		
		if (!is_array($metadata) || !isset($metadata['embedding'])) {
			return null;
		}
		
		return $metadata['embedding'];
	}
	
	/**
	 * Find similar topics to a given topic.
	 *
	 * Results are filtered by the `aips_topic_similarity_threshold` setting (default 0.8),
	 * so the returned count may be less than `$limit` when few neighbors meet the threshold.
	 *
	 * @param int    $topic_id  The topic ID to find similar topics for.
	 * @param int    $author_id Author ID to limit search to.
	 * @param int    $limit     Maximum number of similar topics to return.
	 * @param string $status    Optional. Filter by status (pending, approved, rejected).
	 * @return array Array of similar topics with similarity scores.
	 */
	public function find_similar_topics($topic_id, $author_id, $limit = 5, $status = null) {
		// Get target topic embedding
		$target_embedding = $this->get_topic_embedding($topic_id);
		
		if (!$target_embedding) {
			// If no embedding exists, compute it
			$result = $this->compute_topic_embedding($topic_id);
			if (is_wp_error($result)) {
				return array();
			}
			$target_embedding = $this->get_topic_embedding($topic_id);
		}
		
		if (!$target_embedding) {
			return array();
		}
		
		// Get all topics for the author
		$all_topics = $this->topics_repository->get_by_author($author_id, $status);
		
		// Prepare candidate embeddings
		$candidates = array();
		foreach ($all_topics as $candidate_topic) {
			// Skip the target topic itself
			if ($candidate_topic->id == $topic_id) {
				continue;
			}
			
			// Get or compute embedding
			$embedding = $this->get_topic_embedding($candidate_topic->id);
			
			if (!$embedding) {
				// Try to compute it
				$this->compute_topic_embedding($candidate_topic->id);
				$embedding = $this->get_topic_embedding($candidate_topic->id);
			}
			
			if ($embedding) {
				$candidates[] = array(
					'id' => $candidate_topic->id,
					'embedding' => $embedding,
					'data' => array(
						'topic_title' => $candidate_topic->topic_title,
						'status' => $candidate_topic->status
					)
				);
			}
		}
		
		// Find nearest neighbors and filter by configured similarity threshold
		$raw_threshold = get_option('aips_topic_similarity_threshold', 0.8);
		$threshold = is_numeric($raw_threshold) ? min(1.0, max(0.1, (float) $raw_threshold)) : 0.8;
		$neighbors = $this->embeddings_service->find_nearest_neighbors($target_embedding, $candidates, $limit);

		return array_values(array_filter($neighbors, function($neighbor) use ($threshold) {
			return isset($neighbor['similarity']) && $neighbor['similarity'] >= $threshold;
		}));
	}
	
	/**
	 * Suggest related topics for an author based on approved topics.
	 *
	 * @param int $author_id Author ID.
	 * @param int $limit     Number of suggestions to return.
	 * @return array Array of suggested topics with scores.
	 */
	public function suggest_related_topics($author_id, $limit = 10) {
		// Get approved topics
		$approved_topics = $this->topics_repository->get_by_author($author_id, 'approved');
		
		if (empty($approved_topics)) {
			return array();
		}
		
		// Get pending topics
		$pending_topics = $this->topics_repository->get_by_author($author_id, 'pending');
		
		if (empty($pending_topics)) {
			return array();
		}
		
		// For each pending topic, find its similarity to approved topics
		$suggestions = array();
		
		foreach ($pending_topics as $pending_topic) {
			$pending_embedding = $this->get_topic_embedding($pending_topic->id);
			
			if (!$pending_embedding) {
				$this->compute_topic_embedding($pending_topic->id);
				$pending_embedding = $this->get_topic_embedding($pending_topic->id);
			}
			
			if (!$pending_embedding) {
				continue;
			}
			
			$max_similarity = 0;
			
			// Calculate similarity to each approved topic
			foreach ($approved_topics as $approved_topic) {
				$approved_embedding = $this->get_topic_embedding($approved_topic->id);
				
				if (!$approved_embedding) {
					$this->compute_topic_embedding($approved_topic->id);
					$approved_embedding = $this->get_topic_embedding($approved_topic->id);
				}
				
				if ($approved_embedding) {
					$similarity = $this->embeddings_service->calculate_similarity($pending_embedding, $approved_embedding);
					
					if (!is_wp_error($similarity) && $similarity > $max_similarity) {
						$max_similarity = $similarity;
					}
				}
			}
			
			if ($max_similarity > 0) {
				$suggestions[] = array(
					'topic_id' => $pending_topic->id,
					'topic_title' => $pending_topic->topic_title,
					'similarity_score' => $max_similarity
				);
			}
		}
		
		// Sort by similarity score (descending)
		usort($suggestions, function($a, $b) {
			return $b['similarity_score'] <=> $a['similarity_score'];
		});
		
		return array_slice($suggestions, 0, $limit);
	}
	
	/**
	 * Get expanded context from approved topics for prompt enhancement.
	 *
	 * @param int $author_id     Author ID.
	 * @param int $topic_id      Current topic ID.
	 * @param int $context_limit Number of similar approved topics to include.
	 * @return string Enhanced context string for prompts.
	 */
	public function get_expanded_context($author_id, $topic_id, $context_limit = 5) {
		$similar_topics = $this->find_similar_topics($topic_id, $author_id, $context_limit, 'approved');
		
		if (empty($similar_topics)) {
			return '';
		}
		
		$context_parts = array();
		
		foreach ($similar_topics as $similar) {
			if (!empty($similar['data']['topic_title'])) {
				$context_parts[] = $similar['data']['topic_title'];
			}
		}
		
		if (empty($context_parts)) {
			return '';
		}
		
		return "Related approved topics:\n- " . implode("\n- ", $context_parts);
	}
	
	/**
	 * Batch compute embeddings for all approved topics of an author.
	 *
	 * @param int $author_id Author ID.
	 * @return array Statistics about the batch operation.
	 */
	public function batch_compute_approved_embeddings($author_id) {
		$approved_topics = $this->topics_repository->get_by_author($author_id, 'approved');
		
		$stats = array(
			'total' => count($approved_topics),
			'success' => 0,
			'failed' => 0,
			'skipped' => 0
		);
		
		foreach ($approved_topics as $topic) {
			// Check if embedding already exists
			$existing_embedding = $this->get_topic_embedding($topic->id);
			
			if ($existing_embedding) {
				$stats['skipped']++;
				continue;
			}
			
			$result = $this->compute_topic_embedding($topic->id);
			
			if (is_wp_error($result)) {
				$stats['failed']++;
			} else {
				$stats['success']++;
			}
		}
		
		return $stats;
	}

	/**
	 * Batch compute embeddings for all approved topics across all authors.
	 *
	 * @return array Statistics about the batch operation.
	 */
	public function batch_compute_all_approved_embeddings() {
		$authors = $this->authors_repository->get_all();

		$stats = array(
			'total' => 0,
			'success' => 0,
			'failed' => 0,
			'skipped' => 0
		);

		foreach ($authors as $author) {
			$author_stats = $this->batch_compute_approved_embeddings((int) $author->id);
			$stats['total'] += (int) $author_stats['total'];
			$stats['success'] += (int) $author_stats['success'];
			$stats['failed'] += (int) $author_stats['failed'];
			$stats['skipped'] += (int) $author_stats['skipped'];
		}

		return $stats;
	}

	/**
	 * Process a batch of approved topics for embeddings generation.
	 *
	 * Uses ID-based pagination to avoid slow OFFSET queries. Processes topics
	 * incrementally and returns progress information for re-scheduling.
	 *
	 * @param int $author_id         Author ID.
	 * @param int $batch_size        Number of topics to process in this batch. Default 20.
	 * @param int $last_processed_id Last processed topic ID for pagination. Default 0.
	 * @return array|WP_Error Array with keys: success, failed, skipped, last_processed_id, done, processed_count.
	 */
	public function process_approved_embeddings_batch($author_id, $batch_size = 20, $last_processed_id = 0) {
		// Validate batch size
		$batch_size = max(1, min(100, $batch_size));

		// Get or create history container for this author's embeddings processing
		$history = $this->get_or_create_embeddings_history($author_id);

		// Get the next batch of approved topics using ID-based pagination
		$topics = $this->topics_repository->get_approved_for_generation($author_id, $batch_size, $last_processed_id);

		$stats = array(
			'success'           => 0,
			'failed'            => 0,
			'skipped'           => 0,
			'last_processed_id' => $last_processed_id,
			'done'              => empty($topics),
			'processed_count'   => 0,
		);

		// If no topics returned, we're done
		if (empty($topics)) {
			$history->record(
				'activity',
				__('No topics found to process in this batch', 'ai-post-scheduler'),
				array(
					'event_type' => 'embeddings_batch_empty',
					'event_status' => 'complete',
				),
				null,
				array(
					'author_id'         => $author_id,
					'last_processed_id' => $last_processed_id,
				)
			);
			return $stats;
		}

		// Process each topic in the batch
		foreach ($topics as $topic) {
			$stats['last_processed_id'] = $topic->id;
			$stats['processed_count']++;

			// Check if embedding already exists in metadata
			$existing_embedding = $this->get_topic_embedding($topic->id);

			if ($existing_embedding) {
				$stats['skipped']++;
				$history->record(
					'activity',
					sprintf(
						__('Skipped topic ID %d (embedding already exists)', 'ai-post-scheduler'),
						$topic->id
					),
					array(
						'event_type' => 'embedding_skipped',
						'event_status' => 'skipped',
					),
					null,
					array(
						'topic_id' => $topic->id,
						'topic_title' => $topic->topic_title,
					)
				);
				continue;
			}

			// Compute embedding for this topic
			$result = $this->compute_topic_embedding($topic->id);

			if (is_wp_error($result)) {
				$stats['failed']++;
				$history->record(
					'warning',
					sprintf(
						__('Failed to compute embedding for topic ID %d: %s', 'ai-post-scheduler'),
						$topic->id,
						$result->get_error_message()
					),
					array(
						'event_type' => 'embedding_failed',
						'event_status' => 'failed',
					),
					null,
					array(
						'topic_id' => $topic->id,
						'topic_title' => $topic->topic_title,
						'error' => $result->get_error_message(),
					)
				);
			} else {
				$stats['success']++;
				$history->record(
					'activity',
					sprintf(
						__('Computed embedding for topic ID %d', 'ai-post-scheduler'),
						$topic->id
					),
					array(
						'event_type' => 'embedding_computed',
						'event_status' => 'success',
					),
					null,
					array(
						'topic_id' => $topic->id,
						'topic_title' => $topic->topic_title,
					)
				);
			}
		}

		// Check if there are more topics to process
		// We're done if we got fewer topics than requested
		$stats['done'] = (count($topics) < $batch_size);

		return $stats;
	}

	/**
	 * Get or create a history container for author embeddings processing.
	 *
	 * Looks for an existing incomplete container for this author, or creates a new one.
	 *
	 * @param int $author_id Author ID.
	 * @return AIPS_History_Container History container instance.
	 */
	private function get_or_create_embeddings_history($author_id) {
		// Try to find existing incomplete container for this author
		$existing = $this->history_service->find_incomplete('author_embeddings', array(
			'author_id' => $author_id,
		));

		if ($existing) {
			return $existing;
		}

		// Create new history container
		return $this->history_service->create('author_embeddings', array(
			'author_id' => $author_id,
		));
	}
}


