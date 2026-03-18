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
	 * @var AIPS_History_Service Service for history logging
	 */
	private $history_service;
	
	/**
	 * Initialize the scheduler.
	 */
	public function __construct() {
		$this->authors_repository = new AIPS_Authors_Repository();
		$this->topics_generator = new AIPS_Author_Topics_Generator();
		$this->logger = new AIPS_Logger();
		$this->interval_calculator = new AIPS_Interval_Calculator();
		$this->history_service = new AIPS_History_Service();
		
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
		// Create history container at the start to track the entire process
		$history = $this->history_service->create('author_topic_generation', array(
			'author_id' => $author->id,
		), AIPS_History_Container_Type::AUTHOR_TOPIC);

		$this->logger->log("Generating topics for author: {$author->name} (ID: {$author->id})", 'info');
		$history->record('info', "Generating topics for author: {$author->name}");
		
		// Generate topics using the generator
		$result = $this->topics_generator->generate_topics($author, $history);
		
		if (is_wp_error($result)) {
			$this->logger->log("Failed to generate topics for author {$author->id}: " . $result->get_error_message(), 'error');
			$history->record_error($result->get_error_message());
			
			// Log using History Container
			$history->record(
				'activity',
				sprintf(
					__('Failed to generate topics for author "%s": %s', 'ai-post-scheduler'),
					$author->name,
					$result->get_error_message()
				),
				array(
					'event_type' => 'author_topic_generation',
					'event_status' => 'failed',
				),
				null,
				array(
					'author_id' => $author->id,
					'author_name' => $author->name,
					'field_niche' => $author->field_niche,
					'requested_quantity' => $author->topic_generation_quantity,
					'error' => $result->get_error_message(),
				)
			);
			$history->complete_failure($result->get_error_message());
			
			// Still update the schedule to avoid getting stuck
			$this->update_author_schedule($author);
			return false;
		}
		
		// Update the author's next run time
		$this->update_author_schedule($author);
		
		// Log successful topic generation using History Container
		// $result is an array of topic data on success
		$topic_count = is_array($result) ? count($result) : 0;
		
		$history->record(
			'activity',
			sprintf(
				__('Generated %d topics for author "%s"', 'ai-post-scheduler'),
				$topic_count,
				$author->name
			),
			array(
				'event_type' => 'author_topic_generation',
				'event_status' => 'success',
			),
			null,
			array(
				'author_id' => $author->id,
				'author_name' => $author->name,
				'field_niche' => $author->field_niche,
				'topics_generated' => $topic_count,
				'requested_quantity' => $author->topic_generation_quantity,
			)
		);
		$history->complete_success(array('topics_generated' => $topic_count));
		
		$this->logger->log("Successfully generated topics for author {$author->id}", 'info');

		// Create admin bar notification
		AIPS_Admin_Bar::notify_author_topics_generated($author->name, $topic_count, $author->id);

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
		
		// Create history container for manual generation
		$history = $this->history_service->create('author_topic_generation', array(
			'author_id' => $author->id,
			'source' => 'manual_ui'
		), AIPS_History_Container_Type::AUTHOR_TOPIC);
		$history->record_user_action('manual_topic_generation', "User manually triggered topic generation for author: {$author->name}");
		
		$result = $this->topics_generator->generate_topics($author, $history);

		if (is_wp_error($result)) {
			$history->complete_failure($result->get_error_message());
		} else {
			$history->complete_success(array('topics_generated' => count($result)));
		}

		return $result;
	}
}
