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
	 * WordPress cron hook name for per-author topic-generation slices.
	 *
	 * @var string
	 */
	const SLICE_HOOK = 'aips_process_author_topics_slice';

	/**
	 * Default minimum number of due authors that triggers per-author batching.
	 *
	 * When more than this many authors are due, individual single events are
	 * dispatched for each author rather than processing all of them inline.
	 * Override via the 'aips_author_topics_batch_threshold' filter.
	 *
	 * @var int
	 */
	const DEFAULT_BATCH_THRESHOLD = 3;

	/**
	 * @var self|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * Get the shared singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

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
	 * @var AIPS_Notifications Notifications service
	 */
	private $notifications;

	/**
	 * @var AIPS_Batch_Queue_Service|null Lazy-loaded batch queue service.
	 */
	private $batch_queue_service;

	/**
	 * Initialize the scheduler.
	 */
	public function __construct() {
		$this->authors_repository = new AIPS_Authors_Repository();
		$this->topics_generator = new AIPS_Author_Topics_Generator();
		$this->logger = new AIPS_Logger();
		$this->interval_calculator = new AIPS_Interval_Calculator();
		$this->history_service = new AIPS_History_Service();
		$this->notifications = new AIPS_Notifications();
	}

	/**
	 * Lazy-load the batch queue service.
	 *
	 * @return AIPS_Batch_Queue_Service
	 */
	private function get_batch_queue_service(): AIPS_Batch_Queue_Service {
		if ( $this->batch_queue_service === null ) {
			$this->batch_queue_service = new AIPS_Batch_Queue_Service();
		}
		return $this->batch_queue_service;
	}
	
	/**
	 * Process topic generation for all due authors.
	 *
	 * When the number of due authors meets or exceeds the configured threshold
	 * (aips_author_topics_batch_threshold), individual single cron events are
	 * dispatched for each author instead of processing all of them inline.
	 * This prevents PHP timeout issues when many authors are due simultaneously.
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

		$author_count = count($due_authors);
		$this->logger->log("Found {$author_count} authors due for topic generation", 'info');

		// Determine whether to dispatch per-author slices.
		$threshold = max(1, (int) apply_filters('aips_author_topics_batch_threshold', self::DEFAULT_BATCH_THRESHOLD));

		if ( $author_count >= $threshold ) {
			$this->dispatch_author_slices( $due_authors );
			return;
		}
		
		// Below threshold — process inline (original behaviour).
		foreach ($due_authors as $author) {
			AIPS_Correlation_ID::generate();
			try {
				$this->generate_topics_for_author($author);
			} finally {
				AIPS_Correlation_ID::reset();
			}
		}
		
		$this->logger->log('Completed scheduled topic generation', 'info');
	}

	/**
	 * Dispatch one `aips_process_author_topics_slice` single event per due author.
	 *
	 * Each event fires shortly after the current time (staggered by a few seconds
	 * to avoid hammering the AI service simultaneously) and calls
	 * process_author_slice() for a single author.
	 *
	 * @param object[] $due_authors Array of author objects from the repository.
	 */
	private function dispatch_author_slices( array $due_authors ): void {
		$now            = time();
		$correlation_id = (string) AIPS_Correlation_ID::get();

		// Stagger each author's event by 10 seconds to avoid simultaneous AI requests.
		$stagger_seconds = (int) apply_filters('aips_author_topics_slice_stagger_seconds', 10);
		$stagger_seconds = max(0, $stagger_seconds);

		$i = 0;
		foreach ( $due_authors as $author ) {
			$fire_at = $now + ($i * $stagger_seconds);
			wp_schedule_single_event(
				$fire_at,
				self::SLICE_HOOK,
				array( (int) $author->id, $correlation_id )
			);
			$i++;
		}

		$this->logger->log(
			sprintf(
				'Dispatched %d author-topics slice events (stagger: %ds each).',
				count($due_authors),
				$stagger_seconds
			),
			'info'
		);
	}

	/**
	 * Process topic generation for a single author slice.
	 *
	 * This is the callback for the `aips_process_author_topics_slice` cron hook.
	 * It loads the author by ID and calls generate_topics_for_author().
	 *
	 * @param int    $author_id      ID of the author to process.
	 * @param string $correlation_id Correlation ID for tracing.
	 */
	public function process_author_slice( int $author_id, string $correlation_id = '' ): void {
		if ( ! empty( $correlation_id ) ) {
			AIPS_Correlation_ID::set( $correlation_id );
		} else {
			AIPS_Correlation_ID::generate();
		}

		try {
			$author = $this->authors_repository->get_by_id( $author_id );
			if ( ! $author ) {
				$this->logger->log(
					"Author topics slice: author {$author_id} not found — skipping.",
					'warning'
				);
				return;
			}

			$this->generate_topics_for_author( $author );
		} finally {
			AIPS_Correlation_ID::reset();
		}
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
			
			// Log using History Container
			$fail_history = $this->history_service->create('author_topic_generation', array(
				'author_id' => $author->id,
			));
			$fail_history->record(
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
			
			// Still update the schedule to avoid getting stuck
			$this->update_author_schedule($author);
			return false;
		}
		
		// Update the author's next run time
		$this->update_author_schedule($author);
		
		// Log successful topic generation using History Container
		// $result is an array of topic data on success
		$topic_count = is_array($result) ? count($result) : 0;
		$success_history = $this->history_service->create('author_topic_generation', array(
			'author_id' => $author->id,
		));
		$success_history->record(
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
		
		$this->logger->log("Successfully generated topics for author {$author->id}", 'info');

		// Create admin bar notification
		$this->notifications->author_topics_generated($author->name, $topic_count, $author->id);

		return true;
	}
	
	/**
	 * Update the author's topic generation schedule.
	 *
	 * @param object $author Author object from database.
	 */
	private function update_author_schedule($author) {
		// Calculate next run time based on frequency, preserving original phase
		$next_run = $this->interval_calculator->calculate_next_run($author->topic_generation_frequency, $author->topic_generation_next_run);
		
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

		$result = $this->topics_generator->generate_topics($author);

		// Keep manual "Run Now" behavior aligned with cron runs by advancing
		// schedule timestamps regardless of success/failure to avoid re-running
		// immediately on the next cron tick.
		$this->update_author_schedule($author);

		return $result;
	}
}
