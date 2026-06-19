<?php
/**
 * Post Improvement Service.
 *
 * Business logic layer for post improvement scanning, AI analysis,
 * suggestion generation, and suggestion application. Orchestrates the scan workflow,
 * coordinates with the AI service for content analysis, and manages the suggestion
 * lifecycle from creation through application or dismissal.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Post_Improvement_Service
 *
 * Core service for post improvement workflow execution.
 */
class AIPS_Post_Improvement_Service {

	/**
	 * Repository for data persistence.
	 *
	 * @var AIPS_Post_Improvement_Repository
	 * @since 2.10.0
	 */
	private $repository;

	/**
	 * AI service interface for content analysis.
	 *
	 * @var AIPS_AI_Service_Interface
	 * @since 2.10.0
	 */
	private $ai_service;

	/**
	 * Logger for error and diagnostic logging.
	 *
	 * @var AIPS_Logger_Interface
	 * @since 2.10.0
	 */
	private $logger;

	/**
	 * Prompt builder for AI requests.
	 *
	 * @var AIPS_Prompt_Builder_Post_Improvement
	 * @since 2.10.0
	 */
	private $prompts;

	/**
	 * Initialize service with dependencies.
	 *
	 * @param AIPS_Post_Improvement_Repository|null $repository Optional repository instance.
	 * @param AIPS_AI_Service_Interface|null        $ai_service Optional AI service instance.
	 * @param AIPS_Logger_Interface|null            $logger     Optional logger instance.
	 * @param AIPS_Prompt_Builder_Post_Improvement|null     $prompts    Optional prompts instance.
	 *
	 * @since 2.10.0
	 */
	public function __construct(
		?AIPS_Post_Improvement_Repository $repository = null,
		?AIPS_AI_Service_Interface $ai_service = null,
		?AIPS_Logger_Interface $logger = null,
		?AIPS_Prompt_Builder_Post_Improvement $prompts = null
	) {
		$container = AIPS_Container::get_instance();

		$this->repository = $repository ?: new AIPS_Post_Improvement_Repository();
		$this->ai_service = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
		$this->logger = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
		$this->prompts = $prompts ?: new AIPS_Prompt_Builder_Post_Improvement();
	}

	/**
	 * Process all due schedules (called by WP-Cron).
	 *
	 * @param int $limit Maximum number of schedules to process in one run.
	 *
	 * @return void
	 * @since 2.10.0
	 */
	public function process_due_schedules($limit = 5) {
		$schedules = $this->repository->get_due_schedules(time(), $limit);

		foreach ($schedules as $schedule) {
			$this->run_schedule((int) $schedule->id, 'cron');
		}
	}

	/**
	 * Execute a scan schedule to analyze post improvements and generate suggestions.
	 *
	 * Workflow:
	 * 1. Acquire schedule lock to prevent concurrent execution
	 * 2. Create run record for tracking
	 * 3. Fetch candidate posts with cursor-based pagination
	 * 4. For each post:
	 *    - Compute content fingerprint
	 *    - Skip if fingerprint matches existing suggestion (unchanged)
	 *    - Generate AI-powered suggestions
	 *    - Create suggestion record and items
	 * 5. Update schedule next_run and cursor
	 * 6. Release lock
	 *
	 * @param int    $schedule_id Schedule ID to execute.
	 * @param string $trigger     Trigger type: 'manual' or 'cron'.
	 *
	 * @return int|WP_Error Run ID on success, WP_Error on failure.
	 * @since 2.10.0
	 */
	public function run_schedule($schedule_id, $trigger = 'manual') {
		$schedule_id = absint($schedule_id);
		$schedule = $this->repository->get_schedule_by_id($schedule_id);

		if (!$schedule) {
			return new WP_Error('schedule_not_found', __('Scan schedule not found.', 'ai-post-scheduler'));
		}

		// Acquire lock to prevent concurrent execution
		$token = wp_generate_password(16, false, false);
		if (!$this->repository->acquire_schedule_lock($schedule_id, $token, 300)) {
			return new WP_Error('schedule_locked', __('Scan schedule is already running.', 'ai-post-scheduler'));
		}

		// Create run record for tracking
		$run_id = $this->repository->create_run($schedule_id, 'running', $trigger);
		$metrics = array(
			'posts_scanned'           => 0,
			'suggestions_created'     => 0,
			'posts_skipped_unchanged' => 0,
			'failures_count'          => 0,
		);

		try {
			// Parse schedule configuration
			$category_filters = json_decode((string) $schedule->category_filters, true);
			if (!is_array($category_filters)) {
				$category_filters = array();
			}

			// Get cursor for incremental scanning
			$cursor = isset($schedule->run_cursor) ? absint($schedule->run_cursor) : 0;

			// Fetch batch of candidate posts
			$posts = $this->get_candidate_posts($category_filters, !empty($schedule->include_generated_posts), $cursor, 20);

			foreach ($posts as $post) {
				$metrics['posts_scanned']++;

				// Compute content fingerprint to detect changes
				$fingerprint = $this->compute_post_fingerprint($post);
				$previous = $this->repository->get_latest_pending_suggestion_for_post($post->ID);

				// Skip if post hasn't changed since last scan
				if ($previous && !empty($previous->content_hash) && hash_equals((string) $previous->content_hash, $fingerprint)) {
					$metrics['posts_skipped_unchanged']++;
					continue;
				}

				// Create suggestion record
				$suggestion_id = $this->repository->create_suggestion(array(
					'post_id'           => $post->ID,
					'run_id'            => $run_id,
					'schedule_id'       => $schedule_id,
					'content_hash'      => $fingerprint,
					'freshness_marker'  => gmdate('Y-m-d'),
					'last_scanned_at'   => time(),
				));

				if (!$suggestion_id) {
					$metrics['failures_count']++;
					continue;
				}

				// Generate AI-powered suggestions
				$items = $this->build_suggestions_for_post($post);

				if (empty($items)) {
					// No suggestions - mark as closed
					$this->repository->mark_suggestion_as($suggestion_id, 'closed');
					continue;
				}

				$metrics['suggestions_created']++;

				// Add suggestion items
				foreach ($items as $item) {
					$item['suggestion_id'] = $suggestion_id;
					$item['run_id'] = $run_id;
					$item['post_id'] = $post->ID;
					$this->repository->add_suggestion_item($item);
				}
			}

			// Calculate next run time based on schedule frequency
			$interval = wp_get_schedules();
			$freq = isset($interval[$schedule->frequency]['interval']) ? (int) $interval[$schedule->frequency]['interval'] : DAY_IN_SECONDS;
			$next_run = time() + max(HOUR_IN_SECONDS, $freq);

			// Update run record with success
			$this->repository->update_run($run_id, array_merge($metrics, array(
				'status'       => 'completed',
				'completed_at' => time(),
			)));

			// Update schedule for next execution
			$this->repository->update_schedule($schedule_id, array(
				'last_run'    => time(),
				'next_run'    => (int) $next_run,
				'run_cursor'  => !empty($posts) ? (int) end($posts)->ID : $cursor,
				'retry_count' => 0,
				'last_error'  => '',
			));

		} catch (Exception $e) {
			$metrics['failures_count']++;

			// Update run with failure status
			$this->repository->update_run($run_id, array_merge($metrics, array(
				'status'        => 'failed',
				'error_summary' => $e->getMessage(),
				'completed_at'  => time(),
			)));

			// Update schedule retry count
			$this->repository->update_schedule($schedule_id, array(
				'retry_count' => (int) $schedule->retry_count + 1,
				'last_error'  => $e->getMessage(),
			));

			$this->logger->log('Post improvement scan failed: ' . $e->getMessage(), 'error');
		} finally {
			// Always release lock
			$this->repository->release_schedule_lock($schedule_id, $token);
		}

		return $run_id;
	}

	/**
	 * Apply approved suggestion items to a WordPress post.
	 *
	 * Updates post properties (title, excerpt, content, categories) based on
	 * suggestion item values. Marks items as 'applied' and recalculates overall
	 * suggestion status.
	 *
	 * @param int   $suggestion_id Suggestion ID.
	 * @param array $item_ids      Array of item IDs to apply.
	 * @param int   $user_id       User ID performing the action.
	 *
	 * @return array|WP_Error Array with 'applied' count on success, WP_Error on failure.
	 * @since 2.10.0
	 */
	public function apply_items($suggestion_id, $item_ids, $user_id) {
		$detail = $this->repository->get_suggestion_detail($suggestion_id);

		if (!$detail) {
			return new WP_Error('suggestion_not_found', __('Suggestion not found.', 'ai-post-scheduler'));
		}

		$items = $this->repository->get_items_by_ids($item_ids);
		$post_id = (int) $detail['suggestion']->post_id;
		$post = get_post($post_id);

		if (!$post) {
			return new WP_Error('post_not_found', __('Post not found.', 'ai-post-scheduler'));
		}

		$post_update = array('ID' => $post_id);
		$category_ids = wp_get_post_categories($post_id);
		$applied = 0;

		foreach ($items as $item) {
			if ((int) $item->suggestion_id !== (int) $suggestion_id) {
				continue;
			}

			$suggested_value = json_decode((string) $item->suggested_value, true);

			// Apply based on component type
			switch ($item->component) {
				case 'title':
					if (is_string($suggested_value) && '' !== trim($suggested_value)) {
						$post_update['post_title'] = $suggested_value;
					}
					break;

				case 'excerpt':
					if (is_string($suggested_value)) {
						$post_update['post_excerpt'] = $suggested_value;
					}
					break;

				case 'content':
					if (is_string($suggested_value)) {
						$post_update['post_content'] = $suggested_value;
					}
					break;

				case 'categories':
					if (is_array($suggested_value)) {
						$category_ids = array_values(array_filter(array_map('absint', $suggested_value)));
					}
					break;
			}

			// Mark item as applied with audit trail
			$this->repository->update_item_status((int) $item->id, 'applied', array(
				'user_id'   => absint($user_id),
				'action'    => 'apply',
				'timestamp' => time(),
			));

			$applied++;
		}

		// Update post if any changes were made
		if (count($post_update) > 1) {
			wp_update_post(wp_slash($post_update));
		}

		if (!empty($category_ids)) {
			wp_set_post_categories($post_id, $category_ids, false);
		}

		// Recalculate suggestion status based on item statuses
		$this->repository->recalculate_suggestion_status($suggestion_id);

		return array('applied' => $applied);
	}

	/**
	 * Dismiss suggestion items (reject without applying).
	 *
	 * Marks items as 'dismissed' and recalculates overall suggestion status.
	 *
	 * @param int   $suggestion_id Suggestion ID.
	 * @param array $item_ids      Array of item IDs to dismiss.
	 * @param int   $user_id       User ID performing the action.
	 *
	 * @return int Count of dismissed items.
	 * @since 2.10.0
	 */
	public function dismiss_items($suggestion_id, $item_ids, $user_id) {
		$items = $this->repository->get_items_by_ids($item_ids);
		$count = 0;

		foreach ($items as $item) {
			if ((int) $item->suggestion_id !== (int) $suggestion_id) {
				continue;
			}

			if ($this->repository->update_item_status((int) $item->id, 'dismissed', array(
				'user_id'   => absint($user_id),
				'action'    => 'dismiss',
				'timestamp' => time(),
			))) {
				$count++;
			}
		}

		// Recalculate suggestion status based on item statuses
		$this->repository->recalculate_suggestion_status($suggestion_id);

		return $count;
	}

	/**
	 * Get candidate posts for scanning with optional filtering.
	 *
	 * Supports cursor-based pagination and optional exclusion of plugin-generated posts.
	 *
	 * @param array $category_filters         Category IDs to filter by (empty for all).
	 * @param bool  $include_generated_posts  Whether to include plugin-generated posts.
	 * @param int   $cursor                   ID cursor for pagination (posts with ID > cursor).
	 * @param int   $limit                    Maximum posts to return.
	 *
	 * @return array Array of WP_Post objects.
	 * @since 2.10.0
	 */
	private function get_candidate_posts($category_filters, $include_generated_posts, $cursor = 0, $limit = 20) {
		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => max(1, absint($limit)),
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'fields'         => 'all',
		);

		if (!empty($category_filters)) {
			$args['category__in'] = array_values(array_filter(array_map('absint', (array) $category_filters)));
		}

		// Add cursor filter if provided
		$where_filter = null;
		if (!empty($cursor)) {
			$cursor = absint($cursor);
			$where_filter = static function($where) use ($cursor) {
				return $where . ' AND ID > ' . (int) $cursor;
			};
			add_filter('posts_where', $where_filter);
		}

		try {
			$query = new WP_Query($args);
		} finally {
			if ($where_filter) {
				remove_filter('posts_where', $where_filter);
			}
		}

		$posts = $query->have_posts() ? $query->posts : array();

		// If including all posts or no posts found, return as-is
		if ($include_generated_posts || empty($posts)) {
			return $posts;
		}

		// Filter out plugin-generated posts
		$post_ids = wp_list_pluck($posts, 'ID');
		$generated_ids = $this->repository->get_plugin_generated_post_ids($post_ids);

		if (empty($generated_ids)) {
			return $posts;
		}

		$generated_lookup = array_fill_keys(array_map('intval', $generated_ids), true);

		return array_values(array_filter($posts, static function($post) use ($generated_lookup) {
			return !isset($generated_lookup[(int) $post->ID]);
		}));
	}

	/**
	 * Compute a SHA-256 fingerprint of post content for change detection.
	 *
	 * @param WP_Post $post Post object.
	 *
	 * @return string SHA-256 hash of title, excerpt, and content.
	 * @since 2.10.0
	 */
	private function compute_post_fingerprint($post) {
		return hash('sha256', implode('|', array(
			(string) $post->post_title,
			(string) $post->post_excerpt,
			(string) $post->post_content,
		)));
	}

	/**
	 * Build suggestion items for a post using AI analysis.
	 *
	 * Sends post data to AI service and parses JSON response into suggestion items.
	 * Falls back to basic suggestions if AI analysis fails.
	 *
	 * @param WP_Post $post Post to analyze.
	 *
	 * @return array Array of suggestion items.
	 * @since 2.10.0
	 */
	private function build_suggestions_for_post($post) {
		$categories = get_the_category((int) $post->ID);
		$prompt = $this->prompts->build_post_scan_prompt($post, $categories);
		$response = $this->ai_service->generate_json($prompt, array('temperature' => 0.2));

		$items = array();
		$decoded = array();

		// Parse AI response
		if (!is_wp_error($response) && is_array($response) && isset($response['suggestions']) && is_array($response['suggestions'])) {
			$decoded = $response['suggestions'];
		}

		// Fallback if AI analysis failed
		if (empty($decoded)) {
			$decoded = $this->fallback_suggestions($post);
		}

		// Transform AI suggestions into item records
		foreach ($decoded as $entry) {
			$component = isset($entry['component']) ? sanitize_key($entry['component']) : '';
			$item_type = isset($entry['item_type']) ? sanitize_key($entry['item_type']) : 'recommendation';

			if (empty($component)) {
				continue;
			}

			$original = $this->extract_original_value_for_component($post, $component);
			$suggested = isset($entry['suggested_value']) ? $entry['suggested_value'] : '';

			$items[] = array(
				'component'       => $component,
				'item_type'       => $item_type,
				'status'          => 'pending',
				'original_value'  => $original,
				'suggested_value' => $suggested,
				'rationale'       => isset($entry['rationale']) ? sanitize_textarea_field($entry['rationale']) : '',
				'confidence'      => isset($entry['confidence']) ? (float) $entry['confidence'] : 0,
				'diff_payload'    => $this->build_diff_payload($original, $suggested),
				'audit_meta'      => array(
					'priority' => isset($entry['priority']) ? sanitize_key($entry['priority']) : 'medium',
					'severity' => isset($entry['severity']) ? sanitize_key($entry['severity']) : 'medium',
				),
			);
		}

		return $items;
	}

	/**
	 * Generate basic fallback suggestions when AI analysis fails.
	 *
	 * @param WP_Post $post Post object.
	 *
	 * @return array Array of fallback suggestion entries.
	 * @since 2.10.0
	 */
	private function fallback_suggestions($post) {
		$suggestions = array();

		// Short title suggestion
		$title = (string) $post->post_title;
		if (strlen($title) < 30) {
			$suggestions[] = array(
				'component'       => 'title',
				'item_type'       => 'expand',
				'suggested_value' => $title . ' – Updated Guide',
				'rationale'       => __('Title appears short and may benefit from stronger context.', 'ai-post-scheduler'),
				'confidence'      => 0.45,
				'priority'        => 'medium',
				'severity'        => 'low',
			);
		}

		// Missing excerpt suggestion
		$excerpt = (string) $post->post_excerpt;
		if (empty($excerpt)) {
			$suggestions[] = array(
				'component'       => 'excerpt',
				'item_type'       => 'rewrite',
				'suggested_value' => wp_trim_words(wp_strip_all_tags((string) $post->post_content), 30, '...'),
				'rationale'       => __('Post has no excerpt and could benefit from one.', 'ai-post-scheduler'),
				'confidence'      => 0.6,
				'priority'        => 'high',
				'severity'        => 'medium',
			);
		}

		return $suggestions;
	}

	/**
	 * Extract the original value for a specific post component.
	 *
	 * @param WP_Post $post      Post object.
	 * @param string  $component Component name (title, excerpt, content, categories).
	 *
	 * @return mixed Original value for the component.
	 * @since 2.10.0
	 */
	private function extract_original_value_for_component($post, $component) {
		switch ($component) {
			case 'title':
				return (string) $post->post_title;

			case 'excerpt':
				return (string) $post->post_excerpt;

			case 'content':
				return (string) $post->post_content;

			case 'categories':
				return wp_get_post_categories((int) $post->ID);

			default:
				return '';
		}
	}

	/**
	 * Build a diff payload structure for UI display.
	 *
	 * @param mixed $original  Original value.
	 * @param mixed $suggested Suggested value.
	 *
	 * @return array Diff payload with original, suggested, and preview fields.
	 * @since 2.10.0
	 */
	private function build_diff_payload($original, $suggested) {
		return array(
			'original'          => $original,
			'suggested'         => $suggested,
			'original_preview'  => is_scalar($original) ? wp_trim_words((string) $original, 24, '...') : wp_json_encode($original),
			'suggested_preview' => is_scalar($suggested) ? wp_trim_words((string) $suggested, 24, '...') : wp_json_encode($suggested),
		);
	}
}
