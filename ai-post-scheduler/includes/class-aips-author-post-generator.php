<?php
/**
 * Author Post Generator
 *
 * Generates blog posts from approved author topics.
 * Integrates with the existing AIPS_Generator for content generation.
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Author_Post_Generator
 *
 * Generates posts from approved author topics.
 */
class AIPS_Author_Post_Generator {
	
	/**
	 * @var AIPS_Authors_Repository Repository for authors
	 */
	private $authors_repository;
	
	/**
	 * @var AIPS_Author_Topics_Repository Repository for topics
	 */
	private $topics_repository;
	
	/**
	 * @var AIPS_Author_Topic_Logs_Repository Repository for logs
	 */
	private $logs_repository;
	
	/**
	 * @var AIPS_Generator Generator for posts
	 */
	private $generator;
	
	/**
	 * @var AIPS_Logger Logger instance
	 */
	private $logger;
	
	/**
	 * @var AIPS_Interval_Calculator Calculator for scheduling intervals
	 */
	private $interval_calculator;
	
	/**
	 * @var AIPS_History_Repository Repository for history
	 */
	private $history_repository;
	
	/**
	 * @var AIPS_Topic_Expansion_Service Service for topic expansion
	 */
	private $expansion_service;
	
	/**
	 * Initialize the generator.
	 */
	public function __construct() {
		$this->authors_repository = new AIPS_Authors_Repository();
		$this->topics_repository = new AIPS_Author_Topics_Repository();
		$this->logs_repository = new AIPS_Author_Topic_Logs_Repository();
		$this->generator = new AIPS_Generator();
		$this->logger = new AIPS_Logger();
		$this->interval_calculator = new AIPS_Interval_Calculator();
		$this->history_repository = new AIPS_History_Repository();
		$this->expansion_service = new AIPS_Topic_Expansion_Service();
		
		// Hook into WordPress cron
		add_action('aips_generate_author_posts', array($this, 'process_post_generation'));
	}
	
	/**
	 * Process post generation for all due authors.
	 *
	 * This is called by WordPress cron on the scheduled interval.
	 */
	public function process_post_generation() {
		$this->logger->log('Starting scheduled author post generation', 'info');
		
		// Get all authors due for post generation
		$due_authors = $this->authors_repository->get_due_for_post_generation();
		
		if (empty($due_authors)) {
			$this->logger->log('No authors due for post generation', 'info');
			return;
		}
		
		$this->logger->log('Found ' . count($due_authors) . ' authors due for post generation', 'info');
		
		// Process each author
		foreach ($due_authors as $author) {
			$this->generate_post_for_author($author);
		}
		
		$this->logger->log('Completed scheduled author post generation', 'info');
	}
	
	/**
	 * Generate a post for a specific author from their approved topics.
	 *
	 * @param object $author Author object from database.
	 * @return int|WP_Error Post ID on success, WP_Error on failure.
	 */
	public function generate_post_for_author($author) {
		$this->logger->log("Generating post for author: {$author->name} (ID: {$author->id})", 'info');
		
		// Get an approved topic using weighted probabilistic sampling
		$topics = $this->topics_repository->get_approved_for_generation_weighted($author->id, 1);
		
		if (empty($topics)) {
			$this->logger->log("No approved topics available for author {$author->id}", 'warning');
			
			// Still update the schedule to avoid getting stuck
			$this->update_author_schedule($author);
			return new WP_Error('no_topics', 'No approved topics available');
		}
		
		$topic = $topics[0];
		
		// Generate the post using the topic
		$result = $this->generate_post_from_topic($topic, $author);
		
		// Update the author's next run time
		$this->update_author_schedule($author);
		
		return $result;
	}
	
	/**
	 * Generate a post from a specific topic.
	 *
	 * @param object $topic Topic object from database.
	 * @param object $author Author object from database.
	 * @return int|WP_Error Post ID on success, WP_Error on failure.
	 */
	public function generate_post_from_topic($topic, $author) {
		$this->logger->log("Generating post from topic: {$topic->topic_title} (ID: {$topic->id})", 'info');
		
		// Build a pseudo-template object for the generator
		$template = $this->build_template_from_author($author, $topic);
		
		// Create a history entry
		$history_id = $this->history_repository->create(array(
			'template_id' => null, // No template, this is from an author
			'status' => 'pending',
			'prompt' => $topic->topic_title,
			'generated_title' => null,
			'generated_content' => null
		));
		
		// Generate the post
		try {
			$post_id = $this->generator->generate_post($template, $history_id, array(
				'topic' => $topic->topic_title
			));
			
			if (is_wp_error($post_id)) {
				$this->logger->log("Failed to generate post for topic {$topic->id}: " . $post_id->get_error_message(), 'error');
				return $post_id;
			}
			
			// Apply scheduling priority bump for approved topics
			$this->apply_scheduling_priority_bump($post_id, $topic);
			
			// Log the post generation
			$this->logs_repository->log_post_generation(
				$topic->id,
				$post_id,
				wp_json_encode(array(
					'history_id' => $history_id,
					'author_id' => $author->id,
					'topic_score' => isset($topic->computed_score) ? $topic->computed_score : null
				))
			);
			
			$this->logger->log("Successfully generated post {$post_id} from topic {$topic->id}", 'info');
			
			return $post_id;
			
		} catch (Exception $e) {
			$this->logger->log("Exception generating post for topic {$topic->id}: " . $e->getMessage(), 'error');
			return new WP_Error('generation_failed', $e->getMessage());
		}
	}
	
	/**
	 * Apply scheduling priority bump to a post from an approved topic.
	 *
	 * Adjusts the post date to schedule it earlier based on topic score.
	 * Only affects posts with 'future' status (scheduled posts). Posts with
	 * 'publish' or 'draft' status are not affected as they either publish 
	 * immediately or don't have a specific publish date.
	 *
	 * @param int    $post_id Post ID.
	 * @param object $topic   Topic object.
	 */
	private function apply_scheduling_priority_bump($post_id, $topic) {
		// Only apply bump if topic has a computed score (from weighted sampling)
		if (!isset($topic->computed_score)) {
			return;
		}
		
		// Get the scheduling priority bump configuration
		$config = AIPS_Config::get_instance();
		$scoring_config = $config->get_topic_scoring_config();
		$priority_bump_seconds = isset($scoring_config['scheduling_priority_bump']) ? (int) $scoring_config['scheduling_priority_bump'] : 3600;
		
		// Get current post
		$post = get_post($post_id);
		if (!$post || $post->post_status !== 'future') {
			// Only apply to scheduled posts
			return;
		}
		
		// Calculate bump based on score (higher score = earlier scheduling)
		// Score above base gets a bump, below base gets delayed
		$base_score = isset($scoring_config['base']) ? (float) $scoring_config['base'] : 50;

		// Avoid division by zero if base score is misconfigured as 0.
		if (0.0 === $base_score) {
			return;
		}
		$score_diff = $topic->computed_score - $base_score;
		$bump_multiplier = $score_diff / $base_score; // Normalize to percentage
		$bump_seconds = (int) ($priority_bump_seconds * $bump_multiplier);
		
		// Apply the bump to post date
		$current_date = strtotime($post->post_date);
		$new_date = $current_date - $bump_seconds;
		
		// Don't schedule in the past; ensure a safe buffer into the future
		$new_date = max($new_date, time() + 300); // At least 5 minutes in the future
		
		$new_date_string = date('Y-m-d H:i:s', $new_date);
		
		wp_update_post(array(
			'ID' => $post_id,
			'post_date' => $new_date_string,
			'post_date_gmt' => get_gmt_from_date($new_date_string)
		));
		
		$this->logger->log("Applied scheduling priority bump to post {$post_id}: {$bump_seconds} seconds (score: {$topic->computed_score})", 'info');
	}
	
	/**
	 * Build a template-like object from an author for the generator.
	 *
	 * @param object $author Author object.
	 * @param object $topic Topic object.
	 * @return object Template-like object.
	 */
	private function build_template_from_author($author, $topic) {
		// Build base prompt
		$base_prompt = "Write a comprehensive blog post about: {$topic->topic_title}\n\nField/Niche: {$author->field_niche}";
		
		// Get expanded context from similar approved topics
		$expanded_context = $this->expansion_service->get_expanded_context($author->id, $topic->id, 5);
		
		// Append expanded context if available
		if (!empty($expanded_context)) {
			$base_prompt .= "\n\n" . $expanded_context;
			$this->logger->log("Added expanded context to prompt for topic {$topic->id}", 'debug');
		}
		
		return (object) array(
			'id' => null,
			'name' => "Author: {$author->name}",
			'prompt_template' => $base_prompt,
			'title_prompt' => $topic->topic_title,
			'voice_id' => null,
			'post_quantity' => 1,
			'image_prompt' => $topic->topic_title,
			'generate_featured_image' => $author->generate_featured_image,
			'featured_image_source' => $author->featured_image_source ?: 'ai_prompt',
			'featured_image_unsplash_keywords' => '',
			'featured_image_media_ids' => '',
			'post_status' => $author->post_status ?: 'draft',
			'post_category' => $author->post_category,
			'post_tags' => $author->post_tags ?: '',
			'post_author' => $author->post_author ?: get_current_user_id(),
			'article_structure_id' => $author->article_structure_id,
			'is_active' => $author->is_active
		);
	}
	
	/**
	 * Update the author's post generation schedule.
	 *
	 * @param object $author Author object from database.
	 */
	private function update_author_schedule($author) {
		// Calculate next run time based on frequency
		$next_run = $this->interval_calculator->calculate_next_run($author->post_generation_frequency);
		
		$this->authors_repository->update_post_generation_schedule($author->id, $next_run);
		
		$this->logger->log("Updated post generation schedule for author {$author->id}. Next run: {$next_run}", 'info');
	}
	
	/**
	 * Manually trigger post generation for a specific topic (e.g., from admin UI).
	 *
	 * @param int $topic_id Topic ID.
	 * @return int|WP_Error Post ID on success, WP_Error on failure.
	 */
	public function generate_now($topic_id) {
		$topic = $this->topics_repository->get_by_id($topic_id);
		
		if (!$topic) {
			return new WP_Error('invalid_topic', 'Topic not found');
		}
		
		$author = $this->authors_repository->get_by_id($topic->author_id);
		
		if (!$author) {
			return new WP_Error('invalid_author', 'Author not found');
		}
		
		return $this->generate_post_from_topic($topic, $author);
	}
	
	/**
	 * Regenerate a post from a topic.
	 *
	 * Sets the existing post to draft and generates a new one.
	 *
	 * @param int $post_id WordPress post ID.
	 * @param int $topic_id Topic ID.
	 * @return int|WP_Error New post ID on success, WP_Error on failure.
	 */
	public function regenerate_post($post_id, $topic_id) {
		// Preserve the original post status before setting it to draft
		$original_post = get_post($post_id);
		if ($original_post && isset($original_post->post_status)) {
			update_post_meta($post_id, '_aips_original_post_status', $original_post->post_status);
		}

		// Set the old post to draft
		wp_update_post(array(
			'ID' => $post_id,
			'post_status' => 'draft',
		));
		
		// Generate a new post
		return $this->generate_now($topic_id);
	}
}
