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
class AIPS_Author_Post_Generator extends AIPS_Author_Slice_Scheduler_Base implements AIPS_Cron_Generation_Handler {

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
	 * Default number of posts to generate per author run.
	 *
	 * @var int
	 */
	const DEFAULT_POSTS_PER_RUN = 1;

	/**
	 * Maximum number of posts that can be generated per author run.
	 *
	 * Prevents runaway loops from misconfigured settings or large overrides.
	 * Can be raised via the 'aips_author_post_generation_quantity_max' filter.
	 *
	 * @var int
	 */
	const MAX_POSTS_PER_RUN = 10;

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
	 * @var AIPS_Interval_Calculator Calculator for scheduling intervals
	 */
	private $interval_calculator;

	/**
	 * @var AIPS_Topic_Expansion_Service Service for topic expansion
	 */
	private $expansion_service;

	/**
	 * Initialize the generator.
	 *
	 * Container-aware with optional constructor injection so tests can supply
	 * doubles. Shared collaborators ($runner, $history_service, $logger,
	 * $authors_repository, $job_scheduler) are declared on
	 * AIPS_Author_Slice_Scheduler_Base; do not redeclare them here.
	 *
	 * @param AIPS_Generator|null                    $generator          Post content engine.
	 * @param AIPS_Logger_Interface|null             $logger             Logger instance.
	 * @param object|null                            $history_service    History service.
	 * @param object|null                            $authors_repository Authors repository.
	 * @param object|null                            $topics_repository  Topics repository.
	 * @param object|null                            $logs_repository    Author topic logs repository.
	 * @param object|null                            $interval_calculator Interval calculator.
	 * @param object|null                            $expansion_service  Topic expansion service.
	 * @param AIPS_Generation_Execution_Runner|null  $runner             Shared execution harness.
	 * @param object|null                            $job_scheduler      Job scheduler.
	 */
	public function __construct(
		$generator = null,
		?AIPS_Logger_Interface $logger = null,
		$history_service = null,
		$authors_repository = null,
		$topics_repository = null,
		$logs_repository = null,
		$interval_calculator = null,
		$expansion_service = null,
		$runner = null,
		$job_scheduler = null
	) {
		$container = AIPS_Container::get_instance();

		$this->authors_repository = $authors_repository ?: new AIPS_Authors_Repository();
		$this->topics_repository = $topics_repository ?: new AIPS_Author_Topics_Repository();
		$this->logs_repository = $logs_repository ?: new AIPS_Author_Topic_Logs_Repository();
		$this->generator = $generator ?: ($container->has(AIPS_Generator::class) ? $container->make(AIPS_Generator::class) : new AIPS_Generator());
		$this->logger = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
		$this->interval_calculator = $interval_calculator ?: new AIPS_Interval_Calculator();
		$this->expansion_service = $expansion_service ?: new AIPS_Topic_Expansion_Service();
		$this->history_service = $history_service ?: ($container->has(AIPS_History_Service_Interface::class) ? $container->make(AIPS_History_Service_Interface::class) : new AIPS_History_Service());
		$this->runner = $runner ?: new AIPS_Generation_Execution_Runner($this->history_service, $this->logger);
		$this->job_scheduler = $job_scheduler ?: new AIPS_Job_Scheduler();
	}

	/**
	 * Get the cron hook name for this scheduler's slice processing.
	 *
	 * @return string The WordPress cron hook name.
	 */
	protected function get_slice_hook(): string {
		return self::SLICE_HOOK;
	}

	/**
	 * Get the filter name for stagger seconds configuration.
	 *
	 * @return string The WordPress filter name.
	 */
	protected function get_stagger_filter(): string {
		return 'aips_author_post_slice_stagger_seconds';
	}

	/**
	 * Get the default stagger seconds value.
	 *
	 * @return int Default number of seconds between author slices.
	 */
	protected function get_default_stagger_seconds(): int {
		return 15;
	}

	/**
	 * Get the history service type for this scheduler.
	 *
	 * @return string Type string for history service.
	 */
	protected function get_history_type(): string {
		return 'author_post_generation';
	}

	/**
	 * Get the human-readable log type for this scheduler.
	 *
	 * @return string Log type.
	 */
	protected function get_log_type(): string {
		return 'author-post';
	}

	/**
	 * Get the retry cron hook name for this scheduler.
	 *
	 * @return string The WordPress cron hook name for retries.
	 */
	protected function get_retry_hook(): string {
		return 'aips_retry_failed_author_slices_posts';
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
			$this->run_slice(
				function() use ($author) {
					$this->generate_posts_for_author($author, null, 'scheduled', true);
				},
				array('author_id' => $author->id)
			);
		}

		$this->logger->log('Completed scheduled author post generation', 'info');
	}

	/**
	 * Process post generation for a single author slice.
	 *
	 * This is the callback for the `aips_process_author_post_slice` cron hook.
	 * It loads the author by ID and calls generate_posts_for_author().
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

		$this->run_slice(
			function() use ($author) {
				$this->generate_posts_for_author($author, null, 'scheduled', true);
			},
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
		$this->retry_failed_slices( $author_ids_json, $correlation_id );
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
		$results = $this->generate_posts_for_author($author, 1, 'scheduled', true);

		if (is_wp_error($results)) {
			return $results;
		}

		return !empty($results) ? (int) $results[0] : new WP_Error('generation_failed', __('No posts were generated', 'ai-post-scheduler'));
	}

	/**
	 * Generate one or more posts for a specific author from approved topics.
	 *
	 * @param object      $author          Author object from database.
	 * @param int|null    $count           Optional explicit post count. When null,
	 *                                     the author-level setting for the creation
	 *                                     method is used.
	 * @param string      $creation_method Optional creation method.
	 * @param bool        $advance_schedule Whether to update the author schedule.
	 * @return int[]|WP_Error Generated post IDs on success, WP_Error when no posts were generated.
	 */
	public function generate_posts_for_author($author, ?int $count = null, string $creation_method = 'scheduled', bool $advance_schedule = true) {
		$this->logger->log("Generating post for author: {$author->name} (ID: {$author->id})", 'info');

		$post_count = $this->resolve_post_generation_quantity($author, $creation_method, $count);

		// Get the next approved topics for this author.
		$topics = $this->topics_repository->get_approved_for_generation($author->id, $post_count);
		
		if (empty($topics)) {
			$this->logger->log("No approved topics available for author {$author->id}", 'warning');
			
			// Still update the schedule to avoid getting stuck
			if ($advance_schedule) {
				$this->update_author_schedule($author);
			}
			return new WP_Error('no_topics', __('No approved topics available', 'ai-post-scheduler'));
		}

		$post_ids = array();
		$last_error = null;

		foreach ($topics as $topic) {
			$result = $this->generate_post_from_topic($topic, $author, $creation_method);

			if (is_wp_error($result)) {
				$last_error = $result;
				continue;
			}

			$post_ids[] = (int) $result;
		}

		if ($advance_schedule) {
			$this->update_author_schedule($author);
		}

		if (!empty($post_ids)) {
			return $post_ids;
		}

		return $last_error instanceof WP_Error
			? $last_error
			: new WP_Error('generation_failed', __('No posts were generated', 'ai-post-scheduler'));
	}

	/**
	 * Resolve how many posts an author run should generate.
	 *
	 * @param object   $author          Author object from database.
	 * @param string   $creation_method Creation method ('manual' or 'scheduled').
	 * @param int|null $count           Optional explicit override.
	 * @return int
	 */
	private function resolve_post_generation_quantity($author, string $creation_method, ?int $count = null): int {
		if (null !== $count) {
			$resolved_count = $count;
		} elseif ('manual' === $creation_method) {
			$resolved_count = isset($author->manual_post_generation_quantity) ? (int) $author->manual_post_generation_quantity : self::DEFAULT_POSTS_PER_RUN;
		} else {
			$resolved_count = isset($author->scheduled_post_generation_quantity) ? (int) $author->scheduled_post_generation_quantity : self::DEFAULT_POSTS_PER_RUN;
		}

		$resolved_count = max(1, $resolved_count);

		/**
		 * Filters the maximum number of posts allowed per single author run.
		 * Raise this to allow more than the default cap without code changes.
		 *
		 * @since 2.5.1
		 *
		 * @param int $max Maximum post count per run.
		 */
		$max_count = (int) max(1, apply_filters('aips_author_post_generation_quantity_max', self::MAX_POSTS_PER_RUN));

		/**
		 * Filters the number of posts generated for a single author run.
		 *
		 * @since 2.5.1
		 *
		 * @param int    $resolved_count  Resolved post count for this author run.
		 * @param object $author          Author object from database.
		 * @param string $creation_method Creation method ('manual' or 'scheduled').
		 */
		$filtered_count = (int) apply_filters('aips_author_post_generation_quantity', $resolved_count, $author, $creation_method);

		return max(1, min($max_count, $filtered_count));
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

				// History is recorded by AIPS_Generator on its own
				// post_generation container, including the canonical
				// author_post_generation activity row the schedule feed reads.
				return $post_id;
			}

			// Record the domain post-generation log row (author topic logs table).
			$this->logs_repository->log_post_generation(
				$topic->id,
				$post_id,
				wp_json_encode(array(
					'author_id' => $author->id
				))
			);

			// Always record author's last run timestamp on successful generation.
			$this->authors_repository->update_post_generation_last_run($author->id, AIPS_DateTime::now()->timestamp());

			// The activity/history record (including the canonical
			// author_post_generation row the schedule feed reads) is emitted by
			// AIPS_Generator on its post_generation container.
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
			
			// A thrown Throwable unwinds past AIPS_Generator's own terminal
			// records, so its post_generation container never gets the canonical
			// activity row. Emit a compensating record here on a canonical
			// post_generation container (carrying author_id) with the
			// author_post_generation event_type so the schedule feed still
			// surfaces the failure. This is not the double-logging removed from
			// the success/WP_Error paths — those are recorded by the generator.
			$history = $this->history_service->create('post_generation', array(
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
					'event_type' => 'author_post_generation',
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
		$base_run = !empty($author->post_generation_next_run) ? (int) $author->post_generation_next_run : AIPS_DateTime::now()->timestamp();

		// Advance from the scheduled slot to preserve phase and time-of-day.
		$next_run = $this->interval_calculator->calculate_next_run($author->post_generation_frequency, $base_run);
		
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
			update_post_meta($result, AIPS_Post_Manager::META_POST_GENERATION_TOTAL_TIME, $elapsed);
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
			update_post_meta($post_id, AIPS_Post_Manager::META_ORIGINAL_POST_STATUS, $original_post->post_status);
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
