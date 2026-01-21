<?php
/**
 * Author Topics Scheduler
 *
 * Handles scheduled generation of topics for authors.
 * Separate from post generation scheduling.
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Author_Topics_Scheduler
 *
 * Schedules and executes topic generation for authors.
 */
class AIPS_Author_Topics_Scheduler {
	
	/**
	 * @var AIPS_Authors_Repository Repository for authors
	 */
	private $authors_repository;
	
	/**
	 * @var AIPS_Author_Topics_Generator Generator for topics
	 */
	private $topics_generator;
	
	/**
	 * @var AIPS_Logger Logger instance
	 */
	private $logger;
	
	/**
	 * @var AIPS_Interval_Calculator Calculator for scheduling intervals
	 */
	private $interval_calculator;
	
	/**
	 * @var AIPS_Activity_Repository Repository for activity logging
	 */
	private $activity_repository;
	
	/**
	 * Initialize the scheduler.
	 */
	public function __construct() {
		$this->authors_repository = new AIPS_Authors_Repository();
		$this->topics_generator = new AIPS_Author_Topics_Generator();
		$this->logger = new AIPS_Logger();
		$this->interval_calculator = new AIPS_Interval_Calculator();
		$this->activity_repository = new AIPS_Activity_Repository();
		
		// Hook into WordPress cron
		add_action('aips_generate_author_topics', array($this, 'process_topic_generation'));
	}
	
	/**
	 * Process topic generation for all due authors.
	 *
	 * This is called by WordPress cron on the scheduled interval.
	 */
	public function process_topic_generation() {
		$this->logger->log('Starting scheduled topic generation', 'info');
		
		// Get all authors due for topic generation
		$due_authors = $this->authors_repository->get_due_for_topic_generation();
		
		if (empty($due_authors)) {
			$this->logger->log('No authors due for topic generation', 'info');
			return;
		}
		
		$this->logger->log('Found ' . count($due_authors) . ' authors due for topic generation', 'info');
		
		// Process each author
		foreach ($due_authors as $author) {
			$this->generate_topics_for_author($author);
		}
		
		$this->logger->log('Completed scheduled topic generation', 'info');
	}
	
	/**
	 * Generate topics for a specific author.
	 *
	 * @param object $author Author object from database.
	 * @return bool True on success, false on failure.
	 */
	public function generate_topics_for_author($author) {
		$this->logger->log("Generating topics for author: {$author->name} (ID: {$author->id})", 'info');
		
		// Generate topics using the generator
		$result = $this->topics_generator->generate_topics($author);
		
		if (is_wp_error($result)) {
			$this->logger->log("Failed to generate topics for author {$author->id}: " . $result->get_error_message(), 'error');
			
			// Log to activity feed
			$this->activity_repository->create(array(
				'event_type' => 'author_topic_generation',
				'event_status' => 'failed',
				'message' => sprintf(
					__('Failed to generate topics for author "%s": %s', 'ai-post-scheduler'),
					$author->name,
					$result->get_error_message()
				),
				'metadata' => array(
					'author_id' => $author->id,
					'author_name' => $author->name,
					'field_niche' => $author->field_niche,
					'requested_quantity' => $author->topic_generation_quantity,
					'error' => $result->get_error_message(),
				),
			));
			
			// Still update the schedule to avoid getting stuck
			$this->update_author_schedule($author);
			return false;
		}
		
		// Update the author's next run time
		$this->update_author_schedule($author);
		
		// Log successful topic generation to activity feed
		$topic_count = is_array($result) ? count($result) : 0;
		$this->activity_repository->create(array(
			'event_type' => 'author_topic_generation',
			'event_status' => 'success',
			'message' => sprintf(
				__('Generated %d topics for author "%s"', 'ai-post-scheduler'),
				$topic_count,
				$author->name
			),
			'metadata' => array(
				'author_id' => $author->id,
				'author_name' => $author->name,
				'field_niche' => $author->field_niche,
				'topics_generated' => $topic_count,
				'requested_quantity' => $author->topic_generation_quantity,
			),
		));
		
		$this->logger->log("Successfully generated topics for author {$author->id}", 'info');
		return true;
	}
	
	/**
	 * Update the author's topic generation schedule.
	 *
	 * @param object $author Author object from database.
	 */
	private function update_author_schedule($author) {
		// Calculate next run time based on frequency
		$next_run = $this->interval_calculator->calculate_next_run($author->topic_generation_frequency);
		
		$this->authors_repository->update_topic_generation_schedule($author->id, $next_run);
		
		$this->logger->log("Updated topic generation schedule for author {$author->id}. Next run: {$next_run}", 'info');
	}
	
	/**
	 * Manually trigger topic generation for an author (e.g., from admin UI).
	 *
	 * @param int $author_id Author ID.
	 * @return array|WP_Error Array of generated topics or WP_Error on failure.
	 */
	public function generate_now($author_id) {
		$author = $this->authors_repository->get_by_id($author_id);
		
		if (!$author) {
			return new WP_Error('invalid_author', 'Author not found');
		}
		
		return $this->topics_generator->generate_topics($author);
	}
}
