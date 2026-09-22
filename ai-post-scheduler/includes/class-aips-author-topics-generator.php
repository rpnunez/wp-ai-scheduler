<?php
/**
 * Author Topics Generator
 *
 * Generates topic ideas using AI for an Author based on their field/niche.
 * Implements feedback loop by including summaries of approved/rejected topics.
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Author_Topics_Generator
 *
 * Generates AI-powered topic suggestions for authors.
 */
class AIPS_Author_Topics_Generator {
	
	/**
	 * @var AIPS_AI_Service_Interface AI service for making API calls
	 */
	private $ai_service;
	
	/**
	 * @var AIPS_Logger_Interface Logger instance
	 */
	private $logger;
	
	/**
	 * @var AIPS_Author_Topics_Repository Repository for topics
	 */
	private $topics_repository;
	
	/**
	 * @var AIPS_Author_Topic_Logs_Repository Repository for logs
	 */
	private $logs_repository;
	
	/**
	 * @var AIPS_Embeddings_Service Embeddings service for fuzzy duplicate checks
	 */
	private $embeddings_service;

	/**
	 * @var AIPS_Deduplication_Service Deduplication service
	 */
	private $deduplication_service;

	/**
	 * @var AIPS_Feedback_Repository Feedback repository for building quality context
	 */
	private $feedback_repository;

	/**
	 * @var AIPS_Authors_Repository Repository for authors
	 */
	private $authors_repository;
	
	/**
	 * @var AIPS_Prompt_Builder_Topic Topic prompt builder.
	 */
	private $prompt_builder;

	/**
	 * @var AIPS_Embeddings_Repository
	 */
	private $embeddings_repo;

	/**
	 * @var AIPS_Embeddings_Rate_Limiter Rate limiter instance.
	 */
	private $rate_limiter;

	/**
	 * Initialize the generator.
	 *
	 * @param AIPS_AI_Service_Interface|null $ai_service AI service instance (optional for testing).
	 * @param AIPS_Logger_Interface|null $logger Logger instance (optional for testing).
	 * @param object|null $topics_repository Topics repository (optional for testing).
	 * @param object|null $logs_repository Logs repository (optional for testing).
	 * @param object|null $embeddings_service Embeddings service (optional for testing).
	 * @param object|null $feedback_repository Feedback repository (optional for testing).
	 * @param object|null $prompt_builder Topic prompt builder (optional for testing).
	 * @param object|null $deduplication_service Deduplication service (optional for testing).
	 * @param object|null $authors_repository Authors repository (optional for testing).
	 * @param object|null $embeddings_repo Embeddings repository (optional for testing).
	 * @param AIPS_Embeddings_Rate_Limiter|null $rate_limiter Rate limiter (optional for testing).
	 */
	public function __construct(?AIPS_AI_Service_Interface $ai_service = null, ?AIPS_Logger_Interface $logger = null, $topics_repository = null, $logs_repository = null, $embeddings_service = null, $feedback_repository = null, $prompt_builder = null, $deduplication_service = null, $authors_repository = null, $embeddings_repo = null, ?AIPS_Embeddings_Rate_Limiter $rate_limiter = null) {
		$container = AIPS_Container::get_instance();
		$this->ai_service = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
		$this->logger = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
		$this->topics_repository = $topics_repository ?: new AIPS_Author_Topics_Repository();
		$this->logs_repository = $logs_repository ?: new AIPS_Author_Topic_Logs_Repository();
		$this->authors_repository = $authors_repository ?: new AIPS_Authors_Repository();
		$this->embeddings_repo = $embeddings_repo ?: ($container->has(AIPS_Embeddings_Repository::class) ? $container->make(AIPS_Embeddings_Repository::class) : new AIPS_Embeddings_Repository());
		$this->rate_limiter = $rate_limiter ?: ($container->has(AIPS_Embeddings_Rate_Limiter::class) ? $container->make(AIPS_Embeddings_Rate_Limiter::class) : new AIPS_Embeddings_Rate_Limiter());
		$this->embeddings_service = $embeddings_service ?: new AIPS_Embeddings_Service($this->ai_service, $this->logger, null, null, $this->embeddings_repo, $this->rate_limiter);
		$this->deduplication_service = $deduplication_service ?: ($container->has(AIPS_Deduplication_Service::class) ? $container->make(AIPS_Deduplication_Service::class) : new AIPS_Deduplication_Service($this->embeddings_repo, null, $this->embeddings_service, null, $this->logger));
		$this->feedback_repository = $feedback_repository ?: new AIPS_Feedback_Repository();
		$this->prompt_builder = $prompt_builder ?: new AIPS_Prompt_Builder_Topic(
			null,
			new AIPS_Prompt_Builder_Diversity_Injector(null, $this->topics_repository)
		);
	}
	
	/**
	 * Generate topics for an author.
	 *
	 * @param object $author               Author object from database.
	 * @param bool   $apply_auto_approval  Optional. Whether to apply author auto-approval rules. Default true.
	 * @return array|WP_Error Array of generated topics or WP_Error on failure.
	 */
	public function generate_topics($author, $apply_auto_approval = true) {
		if (!$author || !isset($author->id)) {
			return new WP_Error('invalid_author', 'Invalid author object provided');
		}
		
		$this->logger->log("Starting topic generation for author: {$author->name} (ID: {$author->id})", 'info', array(
			'author_id' => $author->id,
			'quantity' => $author->topic_generation_quantity
		));
		
		// Build the prompt via the dedicated prompt builder
		$approved_topics   = $this->topics_repository->get_approved_summary($author->id, 10);
		$rejected_topics   = $this->topics_repository->get_rejected_summary($author->id, 10);
		$feedback_guidance = $this->build_feedback_guidance_section($author);

		$prompt = $this->prompt_builder->build($author, $approved_topics, $rejected_topics, $feedback_guidance);
		
		// Use generate_json for structured topic data
		$response = $this->ai_service->generate_json($prompt, array(
			'temperature' => 0.7,
			'json_schema' => $this->get_topic_json_schema(),
		));
		
		if (is_wp_error($response)) {
			$this->logger->log("Failed to generate topics for author {$author->id}: " . $response->get_error_message(), 'error');
			return $response;
		}
		
		// Parse the JSON response into database-ready topics
		$topics = $this->parse_json_topics($response, $author);
		
		if (empty($topics)) {
			$this->logger->log("No topics parsed from AI response for author {$author->id}", 'warning');
			return new WP_Error('no_topics_parsed', 'Failed to parse topics from AI response');
		}
		
		// Flag semantically similar candidates before they reach editorial review.
		$topics = $this->apply_fuzzy_duplicate_flags($author, $topics);

		// Apply author auto-approval rules if enabled
		if ($apply_auto_approval) {
			$topics = $this->apply_auto_approval_rules($author, $topics);
		}
		
		// Save topics to database
		$saved_topics = array();

		// Bolt Optimization: Use bulk insert to reduce database round-trips
		if ($this->topics_repository->create_bulk($topics)) {
			// Retrieve created topics to get IDs (fetch latest N topics for author)
			$created_topics = $this->topics_repository->get_latest_by_author($author->id, count($topics));

			// Reverse to match original order (oldest to newest ID)
			$created_topics = array_reverse($created_topics);

			foreach ($created_topics as $topic_obj) {
				$topic_arr = (array) $topic_obj;
				$saved_topics[] = $topic_arr;

				$meta = !empty($topic_arr['metadata']) ? json_decode($topic_arr['metadata'], true) : array();
				$status = isset($topic_arr['status']) ? $topic_arr['status'] : 'pending';

				if ($status === 'approved' && !empty($meta['auto_approved'])) {
					$this->logs_repository->create(array(
						'author_topic_id' => $topic_arr['id'],
						'action'          => 'approved',
						'user_id'         => null,
						'notes'           => isset($meta['auto_approval_note']) ? $meta['auto_approval_note'] : sprintf(__('Topic auto-approved via %s policy.', 'ai-post-scheduler'), isset($meta['auto_approval_rule']) ? $meta['auto_approval_rule'] : 'auto'),
						'metadata'        => wp_json_encode(array(
							'source'   => 'auto_rule',
							'rule'     => isset($meta['auto_approval_rule']) ? $meta['auto_approval_rule'] : 'auto',
							'reason'   => isset($meta['auto_approval_reason']) ? $meta['auto_approval_reason'] : '',
						)),
					));
				} elseif ($status === 'rejected' && !empty($meta['auto_rejected'])) {
					$this->logs_repository->create(array(
						'author_topic_id' => $topic_arr['id'],
						'action'          => 'rejected',
						'user_id'         => null,
						'notes'           => isset($meta['auto_rejection_note']) ? $meta['auto_rejection_note'] : sprintf(__('Topic auto-rejected via %s policy fallback.', 'ai-post-scheduler'), isset($meta['auto_rejection_rule']) ? $meta['auto_rejection_rule'] : 'auto'),
						'metadata'        => wp_json_encode(array(
							'source'   => 'auto_rule',
							'rule'     => isset($meta['auto_rejection_rule']) ? $meta['auto_rejection_rule'] : 'auto',
							'reason'   => isset($meta['auto_rejection_reason']) ? $meta['auto_rejection_reason'] : '',
						)),
					));
				} else {
					$this->logger->log("Created topic: {$topic_arr['topic_title']}", 'info', array(
						'topic_id' => $topic_arr['id'],
						'author_id' => $author->id
					));
				}
			}

			// Always record author's topic generation last run timestamp.
			$this->authors_repository->update_topic_generation_last_run($author->id, AIPS_DateTime::now()->timestamp());

			// Continuous Topic Vector Indexing: persist embeddings if enabled
			$config = AIPS_Config::get_instance();
			$sync_topics = (bool) $config->get_option('aips_indexer_topics_continuous_sync', true);
			if ($sync_topics && $this->embeddings_service->is_enabled() && !$this->rate_limiter->is_in_cooldown()) {
				foreach ($saved_topics as $saved_topic) {
					$t_status = isset($saved_topic['status']) ? $saved_topic['status'] : 'pending';
					if ($t_status !== 'rejected' && !empty($saved_topic['topic_title'])) {
						$t_id  = (int) $saved_topic['id'];
						$t_vec = $this->embeddings_service->generate_embedding($saved_topic['topic_title']);
						if (is_wp_error($t_vec)) {
							if ($this->rate_limiter->is_rate_limit_or_exhaustion_error($t_vec)) {
								break;
							}
						} elseif (is_array($t_vec) && !empty($t_vec)) {
							$model = $this->embeddings_service->get_active_model();
							$dims  = count($t_vec);
							$this->embeddings_repo->upsert('topic', $t_id, $t_vec, $model, $dims, md5($saved_topic['topic_title']));
						}
					}
				}
			}
		} else {
			$this->logger->log("Failed to bulk create topics for author {$author->id}", 'error');
			return new WP_Error('db_insert_error', 'Failed to save generated topics to database');
		}
		
		$count = count($saved_topics);
		$this->logger->log("Successfully generated {$count} topics for author {$author->id}", 'info', array(
			'author_id' => $author->id,
			'topic_count' => $count
		));
		
		return $saved_topics;
	}
	
	/**
	 * Build feedback-derived quality guidance for the topic generation prompt.
	 *
	 * Analyses the admin's approval/rejection feedback patterns and translates them
	 * into concrete instructions that the AI can act on. This helps close the loop
	 * so that repeated rejections steer future generation away from problematic
	 * patterns (tone, relevance, policy, duplicates) while boosting what works.
	 *
	 * @param object $author Author object from the database.
	 * @return string Formatted guidance block ready to be appended to the prompt, or empty string if no feedback exists.
	 */
	private function build_feedback_guidance_section($author) {
		$stats = $this->feedback_repository->get_reason_category_statistics($author->id);

		if (empty($stats)) {
			return '';
		}

		$rejection_guidance = array();
		$approval_notes = array();

		// --- Rejection patterns ---
		if (!empty($stats['duplicate']['rejected'])) {
			$rejection_guidance[] = 'Topics that are similar or duplicate to previous ones keep getting rejected — generate ideas with clearly distinct angles and fresh perspectives.';
		}

		if (!empty($stats['tone']['rejected'])) {
			$tone_hint = !empty($author->voice_tone) ? " Stick to a {$author->voice_tone} tone." : '';
			$rejection_guidance[] = "Several topics were rejected for tone mismatch.{$tone_hint} Ensure suggested topics naturally lend themselves to the author's established voice and writing style.";
		}

		if (!empty($stats['irrelevant']['rejected'])) {
			$rejection_guidance[] = "Multiple topics were rejected as off-topic. Stay strictly within the '{$author->field_niche}' space — avoid peripheral subjects that do not directly serve this niche.";
		}

		if (!empty($stats['policy']['rejected'])) {
			$rejection_guidance[] = 'Some topics were flagged for policy or content concerns. Avoid controversial, sensitive, or policy-violating subject matter entirely.';
		}

		if (!empty($stats['other']['rejected'])) {
			$rejection_guidance[] = 'Some topics were rejected for miscellaneous reasons. Prioritise well-scoped, clearly defined topics that are easy to execute.';
		}

		// --- Approval patterns (positive reinforcement) ---
		if (!empty($stats['duplicate']['approved'])) {
			$approval_notes[] = 'Topics with unique, specific angles tend to be approved.';
		}

		if (!empty($stats['tone']['approved'])) {
			$tone_hint = !empty($author->voice_tone) ? " in a {$author->voice_tone} tone" : '';
			$approval_notes[] = "Topics that align well with the author's voice{$tone_hint} are consistently approved.";
		}

		if (!empty($stats['irrelevant']['approved'])) {
			$approval_notes[] = "Topics tightly focused on '{$author->field_niche}' are well received.";
		}

		if (empty($rejection_guidance) && empty($approval_notes)) {
			return '';
		}

		$section = "Quality guidance derived from admin feedback:\n";

		if (!empty($rejection_guidance)) {
			$section .= "Patterns to avoid:\n";
			foreach ($rejection_guidance as $note) {
				$section .= "- {$note}\n";
			}
		}

		if (!empty($approval_notes)) {
			$section .= "Patterns that work well:\n";
			foreach ($approval_notes as $note) {
				$section .= "- {$note}\n";
			}
		}

		return $section . "\n";
	}

	/**
	 * JSON schema for the topic array returned by the AI.
	 *
	 * @return array<string, mixed>
	 */
	private function get_topic_json_schema(): array {
		return array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					'title'    => array('type' => 'string'),
					'score'    => array('type' => 'integer'),
					'keywords' => array('type' => 'array', 'items' => array('type' => 'string')),
				),
				'required' => array('title', 'score', 'keywords'),
			),
		);
	}

	/**
	 * Parse topics from JSON response.
	 *
	 * Converts structured JSON data into database-ready topic arrays.
	 *
	 * @param array  $json_data Parsed JSON data from AI.
	 * @param object $author    Author object.
	 * @return array Array of topic data arrays ready for database insertion.
	 */
	private function parse_json_topics($json_data, $author) {
		$topics = array();
		
		if (!is_array($json_data)) {
			$this->logger->log("JSON data is not an array for author {$author->id}", 'warning');
			return array();
		}
		
		foreach ($json_data as $item) {
			// Validate required fields
			if (!isset($item['title']) || empty($item['title'])) {
				continue;
			}
			
			// Extract and sanitize data
			$title = sanitize_text_field($item['title']);
			$score = isset($item['score']) ? absint($item['score']) : 50;
			$keywords = isset($item['keywords']) && is_array($item['keywords']) 
				? AIPS_Utilities::sanitize_string_array($item['keywords'])
				: array();
			
			// Skip if title is too short
			if (strlen($title) < 10) {
				continue;
			}
			
			// Create topic data
			$topics[] = array(
				'author_id' => $author->id,
				'topic_title' => $title,
				'topic_prompt' => '', // Will be built when generating post
				'status' => 'pending',
				'score' => $score,
				'metadata' => wp_json_encode(array(
					'generated_via' => 'ai_json',
					'generation_date' => AIPS_DateTime::now()->timestamp(),
					'keywords' => $keywords
				))
			);
			
			// Stop if we have enough topics
			if (count($topics) >= $author->topic_generation_quantity) {
				break;
			}
		}
		
		return $topics;
	}
	
	/**
	 * Parse topics from AI response (legacy text-based method).
	 *
	 * This method is kept for backward compatibility and as fallback.
	 *
	 * @param string $response AI response text.
	 * @param object $author Author object.
	 * @return array Array of topic data arrays ready for database insertion.
	 */
	private function parse_topics_from_response($response, $author) {
		$topics = array();
		
		// Split response into lines and clean
		$lines = explode("\n", $response);
		foreach ($lines as $line) {
			$line = trim($line);
			
			// Skip empty lines
			if (empty($line)) {
				continue;
			}
			
			// Remove common prefixes (numbered lists, bullets, etc.)
			$line = preg_replace('/^[\d]+[\.\)]\s*/', '', $line); // Remove "1. " or "1) "
			$line = preg_replace('/^[-\*•]\s*/', '', $line);      // Remove "- " or "* " or "• "
			$line = trim($line);
			
			// Skip if still empty or too short
			if (strlen($line) < 10) {
				continue;
			}
			
			// Remove any quotes
			$line = trim($line, '"\'');
			
			// Create topic data
			$topics[] = array(
				'author_id' => $author->id,
				'topic_title' => $line,
				'topic_prompt' => '', // Will be built when generating post
				'status' => 'pending',
				'score' => 50,
				'metadata' => wp_json_encode(array(
					'generated_via' => 'ai',
					'generation_date' => AIPS_DateTime::now()->timestamp()
				))
			);
			
			// Stop if we have enough topics
			if (count($topics) >= $author->topic_generation_quantity) {
				break;
			}
		}
		
		return $topics;
	}
	
	/**
	 * Flag semantically similar generated topics as potential duplicates.
	 *
	 * Uses AIPS_Deduplication_Service to check candidates against both existing
	 * topics and existing published WordPress articles.
	 *
	 * @param object $author Author object.
	 * @param array  $topics Generated topic arrays.
	 * @return array Topics with updated metadata and score adjustments.
	 */
	private function apply_fuzzy_duplicate_flags($author, $topics) {
		return $this->deduplication_service->evaluate_topics_for_duplicates($topics, $author->id);
	}

	/**
	 * Compute or retrieve cached composite reference embedding vector for an Author.
	 *
	 * Aggregates the author's persona, bio, focus topics/niche, and the centroid of
	 * all published posts written by this author to form an accurate baseline profile vector.
	 *
	 * @param object $author Author object.
	 * @return array|null Composite vector array or null if unavailable.
	 */
	public function get_author_composite_embedding($author): ?array {
		if (!$this->embeddings_service->is_enabled()) {
			return null;
		}

		$author_id = isset($author->id) ? (int) $author->id : 0;
		$cache_key = 'aips_author_composite_vec_' . $author_id;
		$cached    = get_transient($cache_key);
		if (is_array($cached) && !empty($cached)) {
			return $cached;
		}

		// 1. Build composite profile text from persona, bio, style, goals, and focus topics
		$profile_parts = array(
			!empty($author->name) ? $author->name : '',
			!empty($author->author_persona) ? $author->author_persona : (!empty($author->persona) ? $author->persona : ''),
			!empty($author->author_bio) ? $author->author_bio : (!empty($author->bio) ? $author->bio : ''),
			!empty($author->writing_style) ? $author->writing_style : '',
			!empty($author->content_goals) ? $author->content_goals : '',
			!empty($author->target_audience) ? $author->target_audience : '',
			!empty($author->niche) ? $author->niche : '',
		);
		$profile_text = trim(implode(' ', array_filter($profile_parts)));
		if (empty($profile_text) || !$this->embeddings_service->is_enabled() || $this->rate_limiter->is_in_cooldown()) {
			return null;
		}

		$profile_vec = $this->embeddings_service->generate_embedding($profile_text);
		if (is_wp_error($profile_vec) || !is_array($profile_vec)) {
			return null;
		}

		$vectors = array($profile_vec);

		// 2. Aggregate embeddings of published posts by this author
		if (!empty($author->post_author) || !empty($author->wp_user_id)) {
			$user_id  = !empty($author->post_author) ? (int) $author->post_author : (int) $author->wp_user_id;
			$post_ids = get_posts(array(
				'author'         => $user_id,
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'fields'         => 'ids',
			));

			if (!empty($post_ids)) {
				foreach ($post_ids as $pid) {
					$row = $this->embeddings_repo->get_by_post_id((int) $pid);
					if ($row && !empty($row->embedding)) {
						$pvec = $this->embeddings_repo->decode_embedding($row->embedding);
						if (!empty($pvec) && count($pvec) === count($profile_vec)) {
							$vectors[] = $pvec;
						}
					}
				}
			}
		}

		// 3. Compute centroid across profile vector and author's published post vectors
		$dimensions = count($profile_vec);
		$count      = count($vectors);
		$composite  = array_fill(0, $dimensions, 0.0);

		foreach ($vectors as $v) {
			for ($i = 0; $i < $dimensions; $i++) {
				$composite[$i] += (float) $v[$i];
			}
		}

		for ($i = 0; $i < $dimensions; $i++) {
			$composite[$i] /= $count;
		}

		set_transient($cache_key, $composite, 12 * HOUR_IN_SECONDS);
		return $composite;
	}

	/**
	 * Apply author auto-approval rules to generated topics.
	 *
	 * Supports the Dual-Boundary Semantic Gate (Minimum Niche Relevance + Maximum Duplicate Guard),
	 * quality score thresholding, and global Settings > Authors policy inheritance with Smart Split rejection.
	 *
	 * @param object $author Author object.
	 * @param array  $topics List of topic arrays.
	 * @return array Processed topic arrays.
	 */
	public function apply_auto_approval_rules($author, array $topics): array {
		$config = AIPS_Config::get_instance();

		// Determine effective policy: inherit from global settings or use author custom settings
		$author_mode = !empty($author->topic_auto_approval_mode) ? $author->topic_auto_approval_mode : 'inherit';

		if ($author_mode === 'inherit') {
			$global_enabled = (bool) $config->get_option('aips_author_topic_auto_approval_enabled', false);
			if (!$global_enabled) {
				return $topics; // Manual review by default
			}
			$mode           = (string) $config->get_option('aips_author_topic_approval_mode', 'embeddings');
			$min_score      = 70;
			$min_relevance  = (float) $config->get_option('aips_author_topic_min_relevance', 0.65);
			$max_similarity = (float) $config->get_option('aips_author_topic_max_duplicate', 0.80);
			$fallback       = (string) $config->get_option('aips_author_topic_fallback_action', 'smart_split');
		} else {
			$mode           = $author_mode;
			$min_score      = isset($author->topic_auto_approval_min_score) ? (int) $author->topic_auto_approval_min_score : 70;
			// In similarity/embeddings mode, topic_auto_approval_min_score stores relevance percentage (1-100)
			$min_relevance  = isset($author->topic_auto_approval_min_score) ? max(0.01, min(1.0, (float) ($author->topic_auto_approval_min_score / 100))) : 0.65;
			$max_similarity = isset($author->topic_auto_approval_max_similarity) ? (float) $author->topic_auto_approval_max_similarity : 0.80;
			$fallback       = !empty($author->topic_auto_approval_fallback) ? $author->topic_auto_approval_fallback : 'smart_split';
		}

		if ($mode === 'manual') {
			return $topics;
		}

		$now                 = AIPS_DateTime::now()->timestamp();
		$author_baseline_vec = null;

		if (in_array($mode, array('similarity', 'embeddings'), true)) {
			// Check rate limits and auto-cooldown before attempting remote embedding calls
			if ($this->rate_limiter->is_in_cooldown()) {
				$cooldown_info = $this->rate_limiter->get_cooldown_status();
				$this->logger->log(
					sprintf('Author topic auto-approval skipped: Embeddings API in cooldown until %s. Reason: %s',
						gmdate('Y-m-d H:i:s', (int) $cooldown_info['until']),
						$cooldown_info['reason']
					),
					'warning'
				);
				return $topics; // Graceful fallback: topics remain pending
			}

			$limits = $this->rate_limiter->check_limits();
			if (!$limits['allowed']) {
				$this->logger->log(
					sprintf('Author topic auto-approval skipped: Embeddings rate limit quota (%s) reached.',
						$limits['exceeded_limit']
					),
					'warning'
				);
				return $topics; // Graceful fallback: topics remain pending
			}

			$author_baseline_vec = $this->get_author_composite_embedding($author);
		}

		foreach ($topics as &$topic) {
			$meta = isset($topic['metadata']) ? json_decode($topic['metadata'], true) : array();
			if (!is_array($meta)) {
				$meta = array();
			}

			$qualifies        = false;
			$is_dup_rejection = false;
			$reason           = '';
			$note             = '';

			switch ($mode) {
				case 'all':
					$qualifies = true;
					$reason    = 'auto_approve_all';
					$note      = __('Auto-approved: Policy is set to auto-approve all topics.', 'ai-post-scheduler');
					break;

				case 'score':
					$score = isset($topic['score']) ? (int) $topic['score'] : 50;
					if ($score >= $min_score) {
						$qualifies = true;
						$reason    = 'quality_score_threshold_met';
						$note      = sprintf(__('Auto-approved: Quality score %d met or exceeded minimum threshold of %d.', 'ai-post-scheduler'), $score, $min_score);
					} else {
						$qualifies = false;
						$reason    = 'quality_score_below_threshold';
						$note      = sprintf(__('Did not qualify: Quality score %d is below minimum threshold of %d.', 'ai-post-scheduler'), $score, $min_score);
					}
					$meta['auto_approval_score']     = $score;
					$meta['auto_approval_min_score'] = $min_score;
					break;

				case 'similarity':
				case 'embeddings':
					$topic_title = isset($topic['topic_title']) ? (string) $topic['topic_title'] : '';
					$dup_sim     = isset($meta['duplicate_similarity']) ? (float) $meta['duplicate_similarity'] : (!empty($meta['potential_duplicate']) ? 1.0 : 0.0);

					// Compute topic relevance to author composite baseline vector
					$rel_sim = 0.70;
					if (!empty($author_baseline_vec) && !empty($topic_title) && $this->embeddings_service->is_enabled() && !$this->rate_limiter->is_in_cooldown()) {
						$tvec = $this->embeddings_service->generate_embedding($topic_title);
						if (!is_wp_error($tvec) && is_array($tvec) && count($tvec) === count($author_baseline_vec)) {
							$sim_calc = $this->embeddings_service->calculate_similarity($tvec, $author_baseline_vec);
							if (!is_wp_error($sim_calc)) {
								$rel_sim = (float) $sim_calc;
							}
						}
					}

					$meta['auto_approval_relevance']      = round($rel_sim, 4);
					$meta['auto_approval_duplicate_sim']  = round($dup_sim, 4);
					$meta['auto_approval_min_relevance']  = round($min_relevance, 4);
					$meta['auto_approval_max_similarity'] = round($max_similarity, 4);

					// Dual-Boundary Semantic Evaluation:
					if ($dup_sim >= $max_similarity) {
						// Upper bound breach: Duplicate / cannibalization hazard
						$qualifies        = false;
						$is_dup_rejection = true;
						$reason           = 'rejected_duplicate_cannibalization';
						$note             = sprintf(
							__('Auto-rejected: Duplicate similarity %.1f%% met or exceeded maximum threshold of %.1f%%.', 'ai-post-scheduler'),
							$dup_sim * 100,
							$max_similarity * 100
						);
					} elseif ($rel_sim < $min_relevance) {
						// Lower bound breach: Off-topic / low niche relevance
						$qualifies        = false;
						$is_dup_rejection = false;
						$reason           = 'low_niche_relevance';
						$note             = sprintf(
							__('Did not qualify: Niche relevance %.1f%% is below minimum required %.1f%%.', 'ai-post-scheduler'),
							$rel_sim * 100,
							$min_relevance * 100
						);
					} else {
						// Passed both gates!
						$qualifies = true;
						$reason    = 'semantic_dual_gate_passed';
						$note      = sprintf(
							__('Auto-approved: Passed semantic gate (Relevance: %.1f%%, Duplicate: %.1f%%).', 'ai-post-scheduler'),
							$rel_sim * 100,
							$dup_sim * 100
						);
					}
					break;

				default:
					$qualifies = false;
					break;
			}

			if ($qualifies) {
				$topic['status']              = 'approved';
				$topic['reviewed_at']         = $now;
				$topic['reviewed_by']         = 0;
				$meta['auto_approved']        = true;
				$meta['auto_approval_rule']   = $mode;
				$meta['auto_approval_reason'] = $reason;
				$meta['auto_approval_note']   = $note;
			} else {
				// Rejection & Fallback resolution:
				// Smart Split rejects duplicates immediately and holds low-relevance in pending for review
				$should_reject = ($fallback === 'rejected') || ($fallback === 'smart_split' && $is_dup_rejection);

				if ($should_reject) {
					$topic['status']              = 'rejected';
					$topic['reviewed_at']         = $now;
					$topic['reviewed_by']         = 0;
					$meta['auto_rejected']        = true;
					$meta['auto_rejection_rule']   = $mode;
					$meta['auto_rejection_reason'] = $reason;
					$meta['auto_rejection_note']   = $note;
				} else {
					$topic['status']                 = 'pending';
					$topic['reviewed_at']            = 0;
					$topic['reviewed_by']            = null;
					$meta['auto_approval_evaluated'] = true;
					$meta['auto_approval_rule']      = $mode;
					$meta['auto_approval_reason']    = $reason;
					$meta['auto_approval_note']      = $note;
				}
			}

			$topic['metadata'] = wp_json_encode($meta);
		}
		unset($topic);

		return $topics;
	}
	
	/**
	 * Get feedback context summary for an author.
	 *
	 * This is used to provide context for topic generation.
	 *
	 * @param int $author_id Author ID.
	 * @return string Summary text for inclusion in prompts.
	 */
	public function get_feedback_context($author_id) {
		$context = '';
		
		$approved = $this->topics_repository->get_approved_summary($author_id, 10);
		$rejected = $this->topics_repository->get_rejected_summary($author_id, 10);
		
		if (!empty($approved)) {
			$context .= "Approved topics:\n" . implode("\n", $approved) . "\n\n";
		}
		
		if (!empty($rejected)) {
			$context .= "Rejected topics:\n" . implode("\n", $rejected) . "\n\n";
		}

		$stats = $this->feedback_repository->get_reason_category_statistics($author_id);
		if (!empty($stats)) {
			$context .= "Feedback patterns:\n";
			foreach ($stats as $category => $counts) {
				$approved_count = isset($counts['approved']) ? (int) $counts['approved'] : 0;
				$rejected_count = isset($counts['rejected']) ? (int) $counts['rejected'] : 0;
				if ($rejected_count > 0 || $approved_count > 0) {
					$context .= "- {$category}: {$approved_count} approved, {$rejected_count} rejected\n";
				}
			}
		}
		
		return $context;
	}
}
