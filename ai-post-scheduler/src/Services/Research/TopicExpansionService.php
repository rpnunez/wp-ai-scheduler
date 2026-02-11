<?php
namespace AIPS\Services\Research;

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
 * Class TopicExpansionService
 *
 * Service for expanding and suggesting topics based on semantic similarity.
 */
class TopicExpansionService {
	
	/**
	 * @var AIPS_Embeddings_Service Embeddings service
	 */
	private $embeddings_service;
	
	/**
	 * @var AIPS_Author_Topics_Repository Topics repository
	 */
	private $topics_repository;
	
	/**
	 * @var AIPS_Logger Logger instance
	 */
	private $logger;
	
	/**
	 * Initialize the topic expansion service.
	 */
	public function __construct($embeddings_service = null, $topics_repository = null, $logger = null) {
		$this->embeddings_service = $embeddings_service ?: new AIPS_Embeddings_Service();
		$this->topics_repository = $topics_repository ?: new AIPS_Author_Topics_Repository();
		$this->logger = $logger ?: new AIPS_Logger();
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
	 * @param int $topic_id      The topic ID to find similar topics for.
	 * @param int $author_id     Author ID to limit search to.
	 * @param int $limit         Number of similar topics to return.
	 * @param string $status     Optional. Filter by status (pending, approved, rejected).
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
		
		// Find nearest neighbors
		return $this->embeddings_service->find_nearest_neighbors($target_embedding, $candidates, $limit);
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
}
