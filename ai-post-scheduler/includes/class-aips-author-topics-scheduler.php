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
		// Use AIPS_Date_Time instead of time() for consistent timezone-safe timestamp handling
		$now            = AIPS_DateTime::now()->timestamp();
		$correlation_id = (string) AIPS_Correlation_ID::get();

		// Stagger each author's event by 10 seconds to avoid simultaneous AI requests.
		$stagger_seconds = (int) apply_filters('aips_author_topics_slice_stagger_seconds', 10);
		$stagger_seconds = max(0, $stagger_seconds);

		$failed_authors = array();
		$successful_count = 0;

		$i = 0;
		foreach ( $due_authors as $author ) {
			$fire_at = $now + ($i * $stagger_seconds);
			$args = array( (int) $author->id, $correlation_id );

			// Avoid scheduling duplicate events
			if ( wp_next_scheduled( self::SLICE_HOOK, $args ) ) {
				$successful_count++;
				$i++;
				continue;
			}

			// Try to schedule the event with retry logic
			$scheduled = $this->schedule_slice_with_retry( self::SLICE_HOOK, $fire_at, $args, $author->id );

			if ( $scheduled ) {
				$successful_count++;
			} else {
				$failed_authors[] = $author;
			}

			$i++;
		}

		$this->logger->log(
			sprintf(
				'Dispatched %d/%d author-topics slice events (stagger: %ds each).',
				$successful_count,
				count($due_authors),
				$stagger_seconds
			),
			'info'
		);

		// Schedule a delayed retry for any failed authors
		if ( ! empty( $failed_authors ) ) {
			$this->schedule_failed_authors_retry( $failed_authors, 'topics', $correlation_id );
		}
	}

	/**
	 * Schedule a slice event with retry logic.
	 *
	 * Attempts to schedule the event up to 3 times with exponential backoff
	 * (1s, 2s delays between attempts) to handle transient WordPress cron issues.
	 *
	 * @param string $hook      The cron hook name.
	 * @param int    $fire_at   Unix timestamp when the event should fire.
	 * @param array  $args      Arguments to pass to the hook.
	 * @param int    $author_id Author ID for logging.
	 * @return bool True if successfully scheduled, false otherwise.
	 */
	private function schedule_slice_with_retry( string $hook, int $fire_at, array $args, int $author_id ): bool {
		$max_attempts = (int) apply_filters( 'aips_slice_schedule_max_attempts', 3 );
		$max_attempts = max( 1, min( 5, $max_attempts ) ); // Clamp between 1-5

		for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
			$result = wp_schedule_single_event( $fire_at, $hook, $args );

			if ( $result === true ) {
				if ( $attempt > 1 ) {
					$this->logger->log(
						sprintf( 'Successfully scheduled slice for author ID %d on attempt %d', $author_id, $attempt ),
						'info'
					);
				}
				return true;
			}

			// Log the failure
			$error_msg = is_wp_error( $result ) ? $result->get_error_message() : 'Unknown error (returned false)';
			$this->logger->log(
				sprintf(
					'Attempt %d/%d: Failed to schedule author-topics slice for author ID %d: %s',
					$attempt,
					$max_attempts,
					$author_id,
					$error_msg
				),
				$attempt < $max_attempts ? 'warning' : 'error'
			);

			// If not the last attempt, wait before retrying (exponential backoff)
			if ( $attempt < $max_attempts ) {
				$delay_seconds = pow( 2, $attempt - 1 ); // 1s, 2s, 4s
				sleep( $delay_seconds );
			}
		}

		// All attempts failed - log to history
		$history = $this->history_service->create( 'author_topic_generation', array(
			'author_id' => $author_id,
		) );

		$error_msg = is_wp_error( $result ) ? $result->get_error_message() : 'Unknown error (returned false)';
		$history->record(
			'dispatch_failed',
			sprintf( 'Failed to dispatch topics slice after %d attempts: %s', $max_attempts, $error_msg ),
			array(
				'event_type'   => 'dispatch_slice_failed',
				'event_status' => 'failed',
			),
			null,
			array(
				'author_id'     => $author_id,
				'error'         => $error_msg,
				'max_attempts'  => $max_attempts,
			)
		);

		return false;
	}

	/**
	 * Schedule a delayed retry event for failed authors.
	 *
	 * When one or more author slices fail to schedule despite retries, this method
	 * schedules a single delayed event that will attempt to re-dispatch those authors
	 * after a configurable delay (default 5 minutes).
	 *
	 * @param object[] $failed_authors Array of author objects that failed to schedule.
	 * @param string   $type           Type of generation ('topics' or 'posts').
	 * @param string   $correlation_id Correlation ID for tracing.
	 */
	private function schedule_failed_authors_retry( array $failed_authors, string $type, string $correlation_id ): void {
		$retry_delay = (int) apply_filters( 'aips_author_slice_retry_delay_seconds', 300 ); // 5 minutes
		$retry_delay = max( 60, $retry_delay ); // At least 1 minute

		$retry_hook = 'aips_retry_failed_author_slices_' . $type;
		$retry_at   = AIPS_DateTime::now()->timestamp() + $retry_delay;

		$author_ids = array_map( function( $author ) {
			return (int) $author->id;
		}, $failed_authors );

		$retry_args = array(
			wp_json_encode( $author_ids ),
			$correlation_id,
		);

		// Avoid duplicate retry events
		if ( wp_next_scheduled( $retry_hook, $retry_args ) ) {
			$this->logger->log(
				sprintf(
					'Retry event for %d failed %s slices already scheduled',
					count( $failed_authors ),
					$type
				),
				'info'
			);
			return;
		}

		$result = wp_schedule_single_event( $retry_at, $retry_hook, $retry_args );

		if ( $result === true ) {
			$this->logger->log(
				sprintf(
					'Scheduled delayed retry for %d failed %s slices in %d seconds',
					count( $failed_authors ),
					$type,
					$retry_delay
				),
				'info'
			);
		} else {
			$error_msg = is_wp_error( $result ) ? $result->get_error_message() : 'returned false';
			$this->logger->log(
				sprintf(
					'CRITICAL: Failed to schedule retry event for %d failed %s slices: %s',
					count( $failed_authors ),
					$type,
					$error_msg
				),
				'error'
			);

			// Log critical failure to history
			$history = $this->history_service->create( 'author_topic_generation', array() );
			$history->record(
				'retry_schedule_failed',
				sprintf( 'Failed to schedule retry for %d failed author slices: %s', count( $failed_authors ), $error_msg ),
				array(
					'event_type'   => 'retry_schedule_failed',
					'event_status' => 'failed',
				),
				null,
				array(
					'failed_author_ids' => $author_ids,
					'error'             => $error_msg,
				)
			);
		}
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
	 * Retry failed author topic slices.
	 *
	 * This is the callback for the `aips_retry_failed_author_slices_topics` cron hook.
	 * It re-attempts to dispatch slice events for authors that failed to schedule earlier.
	 *
	 * @param string $author_ids_json JSON-encoded array of author IDs.
	 * @param string $correlation_id  Correlation ID for tracing.
	 */
	public function retry_failed_topic_slices( string $author_ids_json, string $correlation_id = '' ): void {
		if ( ! empty( $correlation_id ) ) {
			AIPS_Correlation_ID::set( $correlation_id );
		} else {
			AIPS_Correlation_ID::generate();
		}

		try {
			$author_ids = json_decode( $author_ids_json, true );
			if ( ! is_array( $author_ids ) || empty( $author_ids ) ) {
				$this->logger->log( 'Invalid author IDs provided for retry', 'error' );
				return;
			}

			$this->logger->log(
				sprintf( 'Retrying topic generation for %d failed authors', count( $author_ids ) ),
				'info'
			);

			// Fetch the author objects
			$authors = array();
			foreach ( $author_ids as $author_id ) {
				$author = $this->authors_repository->get_by_id( $author_id );
				if ( $author ) {
					$authors[] = $author;
				} else {
					$this->logger->log(
						sprintf( 'Retry: author ID %d not found', $author_id ),
						'warning'
					);
				}
			}

			if ( empty( $authors ) ) {
				$this->logger->log( 'No valid authors found for retry', 'warning' );
				return;
			}

			// Re-dispatch these authors
			$this->dispatch_author_slices( $authors );

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
