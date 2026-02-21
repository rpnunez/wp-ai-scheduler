<?php
namespace AIPS\Services\Research;

/**
 * Topic Penalty Service
 *
 * Applies tailored actions based on rejection reasons.
 * Implements soft penalties for duplicates and hard blocks for policy violations.
 *
 * @package AI_Post_Scheduler
 * @since 1.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class TopicPenaltyService
 *
 * Service for applying penalties based on feedback reasons.
 */
class TopicPenaltyService {
	
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
	 * Penalty weights for different reason categories.
	 */
	private $penalty_weights = array(
		'duplicate' => -10,   // Soft penalty
		'tone' => -5,         // Minimal penalty
		'irrelevant' => -15,  // Moderate penalty
		'policy' => -50,      // Hard penalty
		'other' => -5         // Minimal penalty
	);
	
	/**
	 * Initialize the penalty service.
	 */
	public function __construct($topics_repository = null, $authors_repository = null, $logger = null) {
		$this->topics_repository = $topics_repository ?: new \AIPS_Author_Topics_Repository();
		$this->authors_repository = $authors_repository ?: new \AIPS_Authors_Repository();
		$this->logger = $logger ?: new \AIPS_Logger();
	}
	
	/**
	 * Apply penalty based on rejection reason.
	 *
	 * @param int    $topic_id        Topic ID.
	 * @param string $reason_category Reason category (duplicate/tone/irrelevant/policy/other).
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function apply_penalty($topic_id, $reason_category) {
		$topic = $this->topics_repository->get_by_id($topic_id);
		
		if (!$topic) {
			return new \WP_Error('topic_not_found', __('Topic not found.', 'ai-post-scheduler'));
		}
		
		// Get penalty weight
		$penalty = isset($this->penalty_weights[$reason_category]) ? $this->penalty_weights[$reason_category] : $this->penalty_weights['other'];
		
		// Apply penalty to topic score
		$new_score = max(0, min(100, $topic->score + $penalty));
		
		$result = $this->topics_repository->update($topic_id, array('score' => $new_score));
		
		if ($result !== false) {
			$this->logger->log(
				sprintf('Applied %s penalty (%d points) to topic %d. New score: %d', 
					$reason_category, 
					$penalty, 
					$topic_id, 
					$new_score
				),
				'info'
			);
			
			// For policy violations, mark author for review
			if ($reason_category === 'policy') {
				$this->flag_author_for_policy_review($topic->author_id, $topic_id);
			}
			
			return true;
		}
		
		return new \WP_Error('update_failed', __('Failed to apply penalty.', 'ai-post-scheduler'));
	}
	
	/**
	 * Apply reward based on approval reason.
	 *
	 * @param int    $topic_id        Topic ID.
	 * @param string $reason_category Reason category.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function apply_reward($topic_id, $reason_category) {
		$topic = $this->topics_repository->get_by_id($topic_id);
		
		if (!$topic) {
			return new \WP_Error('topic_not_found', __('Topic not found.', 'ai-post-scheduler'));
		}
		
		// Apply positive reward (opposite of penalty)
		$reward = 10; // Fixed reward for approval
		
		$new_score = max(0, min(100, $topic->score + $reward));
		
		$result = $this->topics_repository->update($topic_id, array('score' => $new_score));
		
		if ($result !== false) {
			$this->logger->log(
				sprintf('Applied reward (+%d points) to topic %d. New score: %d', 
					$reward, 
					$topic_id, 
					$new_score
				),
				'info'
			);
			
			return true;
		}
		
		return new \WP_Error('update_failed', __('Failed to apply reward.', 'ai-post-scheduler'));
	}
	
	/**
	 * Flag an author for policy review.
	 *
	 * @param int $author_id Author ID.
	 * @param int $topic_id  Topic ID that triggered the flag.
	 */
	private function flag_author_for_policy_review($author_id, $topic_id) {
		$author = $this->authors_repository->get_by_id($author_id);
		
		if (!$author) {
			return;
		}
		
		// Store flag in author metadata
		$metadata = !empty($author->details) ? json_decode($author->details, true) : array();
		if (!is_array($metadata)) {
			$metadata = array();
		}
		
		if (!isset($metadata['policy_flags'])) {
			$metadata['policy_flags'] = array();
		}
		
		$metadata['policy_flags'][] = array(
			'topic_id' => $topic_id,
			'timestamp' => current_time('mysql'),
			'status' => 'pending_review'
		);
		
		// If multiple policy violations, consider deactivating the author
		if (count($metadata['policy_flags']) >= 3) {
			$this->logger->log(
				sprintf('Author %d has %d policy violations. Consider deactivation.', 
					$author_id, 
					count($metadata['policy_flags'])
				),
				'warning'
			);
			
			// Optionally, automatically deactivate
			// $this->authors_repository->update($author_id, array('is_active' => 0));
		}
		
		$this->authors_repository->update($author_id, array('details' => wp_json_encode($metadata)));
		
		$this->logger->log(
			sprintf('Flagged author %d for policy review due to topic %d', $author_id, $topic_id),
			'warning'
		);
	}
	
	/**
	 * Get penalty weight for a reason category.
	 *
	 * @param string $reason_category Reason category.
	 * @return int Penalty weight.
	 */
	public function get_penalty_weight($reason_category) {
		return isset($this->penalty_weights[$reason_category]) ? $this->penalty_weights[$reason_category] : $this->penalty_weights['other'];
	}
	
	/**
	 * Set custom penalty weights.
	 *
	 * @param array $weights Array of reason_category => penalty_weight.
	 */
	public function set_penalty_weights($weights) {
		$this->penalty_weights = array_merge($this->penalty_weights, $weights);
	}
	
	/**
	 * Get author policy flags.
	 *
	 * @param int $author_id Author ID.
	 * @return array Array of policy flags.
	 */
	public function get_author_policy_flags($author_id) {
		$author = $this->authors_repository->get_by_id($author_id);
		
		if (!$author || empty($author->details)) {
			return array();
		}
		
		$metadata = json_decode($author->details, true);
		
		if (!is_array($metadata) || !isset($metadata['policy_flags'])) {
			return array();
		}
		
		return $metadata['policy_flags'];
	}
	
	/**
	 * Clear author policy flags.
	 *
	 * @param int $author_id Author ID.
	 * @return bool True on success, false on failure.
	 */
	public function clear_author_policy_flags($author_id) {
		$author = $this->authors_repository->get_by_id($author_id);
		
		if (!$author) {
			return false;
		}
		
		$metadata = !empty($author->details) ? json_decode($author->details, true) : array();
		if (!is_array($metadata)) {
			$metadata = array();
		}
		
		$metadata['policy_flags'] = array();
		
		$result = $this->authors_repository->update($author_id, array('details' => wp_json_encode($metadata)));
		
		if ($result !== false) {
			$this->logger->log(sprintf('Cleared policy flags for author %d', $author_id), 'info');
			return true;
		}
		
		return false;
	}
}
