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
	 * @var AIPS_Topic_Expansion_Service Service for topic expansion
	 */
	private $expansion_service;
	
	/**
	 * @var AIPS_History_Service Service for history logging
	 */
	private $history_service;
	
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
		$this->expansion_service = new AIPS_Topic_Expansion_Service();
		$this->history_service = new AIPS_History_Service();
		
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
		
		// Process each author, scoping a unique correlation ID to each author's
		// post generation run so the full chain is traceable end-to-end.
		foreach ($due_authors as $author) {
			AIPS_Correlation_ID::generate();
			$this->generate_post_for_author($author);
			AIPS_Correlation_ID::reset();
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
		
		// Get the next approved topic for this author
		$topics = $this->topics_repository->get_approved_for_generation($author->id, 1);
		
		if (empty($topics)) {
			$this->logger->log("No approved topics available for author {$author->id}", 'warning');
			
			// Still update the schedule to avoid getting stuck
			$this->update_author_schedule($author);
			return new WP_Error('no_topics', 'No approved topics available');
		}
		
		$topic = $topics[0];
		
		// Generate the post using the topic (scheduled generation)
		$result = $this->generate_post_from_topic($topic, $author, 'scheduled');
		
		// Update the author's next run time
		$this->update_author_schedule($author);
		
		return $result;
	}
	
	/**
	 * Generate a post from a specific topic.
	 *
	 * @param object $topic Topic object from database.
	 * @param object $author Author object from database.
	 * @param string $creation_method Optional creation method ('manual' or 'scheduled'). Defaults to 'manual'.
	 * @return int|WP_Error Post ID on success, WP_Error on failure.
	 */
	public function generate_post_from_topic($topic, $author, $creation_method = 'manual') {
		$this->logger->log("Generating post from topic: {$topic->topic_title} (ID: {$topic->id})", 'info');
		
		// Get expanded context from similar approved topics
		$expanded_context = $this->expansion_service->get_expanded_context($author->id, $topic->id, 5);
		
		if (!empty($expanded_context)) {
			$this->logger->log("Added expanded context to prompt for topic {$topic->id}", 'debug');
		}
		
		// Build a context object for the generator with the creation method
		$context = new AIPS_Topic_Context($author, $topic, $expanded_context, $creation_method);
		
		// Generate the post using the context
		// Note: The Generator internally creates its own history container
		try {
			$post_id = $this->generator->generate_post($context);
			
			if (is_wp_error($post_id)) {
				$this->logger->log("Failed to generate post for topic {$topic->id}: " . $post_id->get_error_message(), 'error');
				
				// The Generator now handles all logging internally via History Container
				// We can optionally log additional high-level activity here
				$history = $this->history_service->create('topic_post_generation', array(
					'topic_id' => $topic->id,
					'author_id' => $author->id,
				));
				
				$history->record(
					'activity',
					sprintf(
						__('Failed to generate post from topic "%s": %s', 'ai-post-scheduler'),
						$topic->topic_title,
						$post_id->get_error_message()
					),
					array(
						'event_type' => 'topic_post_generation',
						'event_status' => 'failed',
						'topic_id' => $topic->id,
						'topic_title' => $topic->topic_title,
					),
					null,
					array(
						'author_id' => $author->id,
						'author_name' => $author->name,
						'error' => $post_id->get_error_message(),
					)
				);
				
				return $post_id;
			}
			
			// Log the post generation
			$this->logs_repository->log_post_generation(
				$topic->id,
				$post_id,
				wp_json_encode(array(
					'author_id' => $author->id
				))
			);
			
			// Get post status for activity log
			$post = get_post($post_id);
			$post_status = $post ? $post->post_status : 'unknown';
			$post_title = $post ? $post->post_title : $topic->topic_title;
			
			// Log successful post generation using new History API
			$history = $this->history_service->create('topic_post_generation', array(
				'post_id' => $post_id,
				'topic_id' => $topic->id,
				'author_id' => $author->id,
			));
			
			$history->record(
				'activity',
				sprintf(
					__('Generated %s from topic "%s" for author "%s"', 'ai-post-scheduler'),
					$post_status === 'publish' ? __('post', 'ai-post-scheduler') : __('draft', 'ai-post-scheduler'),
					$topic->topic_title,
					$author->name
				),
				array(
					'event_type' => 'topic_post_generation',
					'event_status' => 'success',
					'topic_id' => $topic->id,
					'topic_title' => $topic->topic_title,
				),
				array(
					'post_id' => $post_id,
					'post_title' => $post_title,
					'post_status' => $post_status,
				),
				array(
					'author_id' => $author->id,
					'author_name' => $author->name,
				)
			);
			
			$this->logger->log("Successfully generated post {$post_id} from topic {$topic->id}", 'info');
			
			return $post_id;
			
		} catch (Exception $e) {
			$this->logger->log("Exception generating post for topic {$topic->id}: " . $e->getMessage(), 'error');

			$payload = array(
				'resource_label'  => sprintf(__('author topic "%s"', 'ai-post-scheduler'), $topic->topic_title),
				'schedule_name'   => sprintf(__('Author post generation for %s', 'ai-post-scheduler'), $author->name),
				'error_code'      => 'generation_failed',
				'error_message'   => $e->getMessage(),
				'topic_id'        => $topic->id,
				'topic_title'     => $topic->topic_title,
				'author_id'       => $author->id,
				'author_name'     => $author->name,
				'creation_method' => $creation_method,
				'correlation_id'  => AIPS_Correlation_ID::get(),
				'url'             => 'scheduled' === $creation_method ? AIPS_Admin_Menu_Helper::get_page_url('schedule') : AIPS_Admin_Menu_Helper::get_page_url('history'),
				'dedupe_key'      => sanitize_key($creation_method . '_author_topic_' . $topic->id . '_exception'),
				'dedupe_window'   => 900,
			);

			if ('scheduled' === $creation_method) {
				do_action('aips_scheduler_error', $payload);
			} else {
				do_action('aips_generation_failed', $payload);
			}
			
			// Log exception using new History API
			$history = $this->history_service->create('topic_post_generation', array(
				'topic_id' => $topic->id,
				'author_id' => $author->id,
			));
			
			$history->record(
				'activity',
				sprintf(
					__('Exception while generating post from topic "%s": %s', 'ai-post-scheduler'),
					$topic->topic_title,
					$e->getMessage()
				),
				array(
					'event_type' => 'topic_post_generation',
					'event_status' => 'failed',
					'topic_id' => $topic->id,
					'topic_title' => $topic->topic_title,
				),
				null,
				array(
					'author_id' => $author->id,
					'author_name' => $author->name,
					'error' => $e->getMessage(),
				)
			);
			
			return new WP_Error('generation_failed', $e->getMessage());
		}
	}
	
	
	/**
	 * Update the author's post generation schedule.
	 *
	 * @param object $author Author object from database.
	 */
	private function update_author_schedule($author) {
		// Calculate next run time based on frequency, preserving original phase
		$next_run = $this->interval_calculator->calculate_next_run($author->post_generation_frequency, $author->post_generation_next_run);
		
		$this->authors_repository->update_post_generation_schedule($author->id, $next_run);
		
		$this->logger->log("Updated post generation schedule for author {$author->id}. Next run: {$next_run}", 'info');
	}
	
	/**
	 * Manually trigger post generation for a specific topic (e.g., from admin UI).
	 *
	 * Records the total wall-clock time for the generation attempt (including any
	 * AI retries and resilience delays) as `_aips_post_generation_total_time` post
	 * meta so that the bulk-generation progress bar can build increasingly accurate
	 * time estimates over time.
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
		
		// Track wall-clock start time so we can record the total generation
		// duration (including resilience/retry delays) for future estimates.
		$start_time = microtime(true);
		
		// Manual generation
		$result = $this->generate_post_from_topic($topic, $author, 'manual');
		
		// Store elapsed time in post meta for future progress-bar estimation.
		if (!is_wp_error($result) && $result > 0) {
			$elapsed = round(microtime(true) - $start_time, 2);
			update_post_meta($result, '_aips_post_generation_total_time', $elapsed);
		}
		
		return $result;
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
