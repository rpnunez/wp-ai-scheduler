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
 *
 * Implements AIPS_Cron_Generation_Handler so it can be discovered and
 * dispatched through the standard cron handler contract.
 */
class AIPS_Author_Post_Generator implements AIPS_Cron_Generation_Handler {

	/**
	 * WordPress cron hook name for per-author post-generation slices.
	 *
	 * @var string
	 */
	const SLICE_HOOK = 'aips_process_author_post_slice';

	/**
	 * Default minimum number of due authors that triggers per-author batching.
	 *
	 * Override via the 'aips_author_post_batch_threshold' filter.
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
	 * @var AIPS_Generation_Execution_Runner Shared execution harness.
	 */
	private $runner;

	/**
	 * @var AIPS_Resilience_Service Resilience service for retry logic
	 */
	private $resilience_service;

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
		$this->runner = new AIPS_Generation_Execution_Runner($this->history_service, $this->logger);
		$this->resilience_service = new AIPS_Resilience_Service();
	}
	
	/**
	 * Process post generation for all due authors.
	 *
	 * When the number of due authors meets or exceeds the configured threshold
	 * (aips_author_post_batch_threshold), individual single cron events are
	 * dispatched for each author instead of processing all of them inline.
	 * This prevents PHP timeout issues when many authors are due simultaneously.
	 *
	 * Called by WordPress cron on the `aips_generate_author_posts` hook.
	 * Implements AIPS_Cron_Generation_Handler::process().
	 */
	public function process(): void {
		$this->logger->log('Starting scheduled author post generation', 'info');
		
		// Get all authors due for post generation
		$due_authors = $this->authors_repository->get_due_for_post_generation();
		
		if (empty($due_authors)) {
			$this->logger->log('No authors due for post generation', 'info');
			return;
		}

		$author_count = count($due_authors);
		$this->logger->log("Found {$author_count} authors due for post generation", 'info');

		// Determine whether to dispatch per-author slices.
		$threshold = max(1, (int) apply_filters('aips_author_post_batch_threshold', self::DEFAULT_BATCH_THRESHOLD));

		if ( $author_count >= $threshold ) {
			$this->dispatch_author_slices( $due_authors );
			return;
		}
		
		// Below threshold — process inline (original behaviour).
		foreach ($due_authors as $author) {
			$this->runner->run(
				function() use ($author) {
					$this->generate_post_for_author($author);
				},
				'author_post_generation',
				array('author_id' => $author->id)
			);
		}
		
		$this->logger->log('Completed scheduled author post generation', 'info');
	}

	/**
	 * Dispatch one `aips_process_author_post_slice` single event per due author.
	 *
	 * Each event fires shortly after the current time (staggered to avoid
	 * hammering the AI service simultaneously) and calls
	 * process_author_slice() for a single author.
	 *
	 * @param object[] $due_authors Array of author objects from the repository.
	 */
	private function dispatch_author_slices( array $due_authors ): void {
		// Use AIPS_Date_Time instead of time() for consistent timezone-safe timestamp handling
		$now            = AIPS_DateTime::now()->timestamp();
		$correlation_id = (string) AIPS_Correlation_ID::get();

		// Stagger each author's event by 15 seconds to avoid simultaneous AI requests.
		$stagger_seconds = (int) apply_filters('aips_author_post_slice_stagger_seconds', 15);
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
				'Dispatched %d/%d author-post slice events (stagger: %ds each).',
				$successful_count,
				count($due_authors),
				$stagger_seconds
			),
			'info'
		);

		// Schedule a delayed retry for any failed authors
		if ( ! empty( $failed_authors ) ) {
			$this->schedule_failed_authors_retry( $failed_authors, 'posts', $correlation_id );
		}
	}

	/**
	 * Schedule a slice event with retry logic using AIPS_Resilience_Service.
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
		$attempt_num = 0;
		$last_error = null;

		$success = $this->resilience_service->retry_with_backoff(
			function() use ( $hook, $fire_at, $args, $author_id, &$attempt_num, &$last_error ) {
				$attempt_num++;
				$result = wp_schedule_single_event( $fire_at, $hook, $args );

				if ( $result === true ) {
					if ( $attempt_num > 1 ) {
						$this->logger->log(
							sprintf( 'Successfully scheduled slice for author ID %d on attempt %d', $author_id, $attempt_num ),
							'info'
						);
					}
					return true;
				}

				// Log the failure
				$error_msg = is_wp_error( $result ) ? $result->get_error_message() : 'Unknown error (returned false)';
				$last_error = $error_msg;

				$this->logger->log(
					sprintf(
						'Attempt %d: Failed to schedule author-post slice for author ID %d: %s',
						$attempt_num,
						$author_id,
						$error_msg
					),
					'warning'
				);

				return false;
			},
			$max_attempts,
			1, // Initial 1-second delay
			true // Use exponential backoff
		);

		// If all attempts failed, log to history
		if ( ! $success ) {
			$history = $this->history_service->create( 'author_post_generation', array(
				'author_id' => $author_id,
			) );

			$history->record(
				'dispatch_failed',
				sprintf( 'Failed to dispatch post slice after %d attempts: %s', $max_attempts, $last_error ),
				array(
					'event_type'   => 'dispatch_slice_failed',
					'event_status' => 'failed',
				),
				null,
				array(
					'author_id'     => $author_id,
					'error'         => $last_error,
					'max_attempts'  => $max_attempts,
				)
			);
		}

		return $success;
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
			$history = $this->history_service->create( 'author_post_generation', array() );
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
	 * Process post generation for a single author slice.
	 *
	 * This is the callback for the `aips_process_author_post_slice` cron hook.
	 * It loads the author by ID and calls generate_post_for_author().
	 *
	 * @param int    $author_id      ID of the author to process.
	 * @param string $correlation_id Correlation ID for tracing.
	 */
	public function process_author_slice( int $author_id, string $correlation_id = '' ): void {
		if ( ! empty( $correlation_id ) ) {
			AIPS_Correlation_ID::set( $correlation_id );
		}

		$author = $this->authors_repository->get_by_id( $author_id );
		if ( ! $author ) {
			$this->logger->log(
				"Author post slice: author {$author_id} not found — skipping.",
				'warning'
			);
			if ( ! empty( $correlation_id ) ) {
				AIPS_Correlation_ID::reset();
			}
			return;
		}

		$this->runner->run(
			function() use ($author) {
				$this->generate_post_for_author($author);
			},
			'author_post_generation',
			array('author_id' => $author->id)
		);

		if ( ! empty( $correlation_id ) ) {
			AIPS_Correlation_ID::reset();
		}
	}

	/**
	 * Retry failed author post slices.
	 *
	 * This is the callback for the `aips_retry_failed_author_slices_posts` cron hook.
	 * It re-attempts to dispatch slice events for authors that failed to schedule earlier.
	 *
	 * @param string $author_ids_json JSON-encoded array of author IDs.
	 * @param string $correlation_id  Correlation ID for tracing.
	 */
	public function retry_failed_post_slices( string $author_ids_json, string $correlation_id = '' ): void {
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
				sprintf( 'Retrying post generation for %d failed authors', count( $author_ids ) ),
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
	 * Generate a post for a specific author from their approved topics.
	 *
	 * SCHEDULE-ADVANCEMENT STRATEGY — advance after execution:
	 * `post_generation_next_run` is updated *after* the generation attempt
	 * (success or failure) rather than before it.  This is intentional and
	 * differs from the claim-first locking used by AIPS_Schedule_Processor.
	 *
	 * Rationale: per-author post-generation frequency is coarser (typically
	 * daily or weekly), so the risk of two cron workers overlapping is low.
	 * Advancing after execution ensures the schedule timestamp reflects when
	 * work actually completed, giving a more accurate "next run" window and
	 * avoiding the edge case where a crashed pre-execution advance could
	 * silently delay the author's next post by a full interval.
	 *
	 * If concurrent-worker safety ever becomes a concern (e.g. Action
	 * Scheduler with multiple workers), consider adding a claim-first lock
	 * here as well.
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
		
		// Get expanded context from similar approved topics.
		// The limit is filterable so installations with many related topics can
		// increase it without code changes.
		/**
		 * Filters the maximum number of similar approved topics used as expanded
		 * context when generating a post from an author topic.
		 *
		 * @since 2.6.0
		 * @param int $limit Default context limit. Default 5.
		 */
		$context_limit    = max(1, (int) apply_filters('aips_topic_expansion_context_limit', 5));
		$expanded_context = $this->expansion_service->get_expanded_context($author->id, $topic->id, $context_limit);
		
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
