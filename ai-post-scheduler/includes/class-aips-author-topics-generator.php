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
	 * @var AIPS_Similarity_Evaluator Similarity evaluator.
	 */
	private $similarity_evaluator;

	/**
	 * @var AIPS_Content_Indexer_Service|null Content indexer service.
	 */
	private $indexer_service;

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
	 * @param AIPS_Similarity_Evaluator|null $similarity_evaluator Similarity evaluator (optional for testing).
	 * @param AIPS_Content_Indexer_Service|null $indexer_service Indexer service (optional for testing).
	 */
	public function __construct(
		?AIPS_AI_Service_Interface $ai_service = null,
		?AIPS_Logger_Interface $logger = null,
		$topics_repository = null,
		$logs_repository = null,
		$embeddings_service = null,
		$feedback_repository = null,
		$prompt_builder = null,
		$deduplication_service = null,
		$authors_repository = null,
		$embeddings_repo = null,
		?AIPS_Embeddings_Rate_Limiter $rate_limiter = null,
		?AIPS_Similarity_Evaluator $similarity_evaluator = null,
		?AIPS_Content_Indexer_Service $indexer_service = null
	) {
		$container = AIPS_Container::get_instance();
		$this->ai_service = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
		$this->logger = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
		$this->topics_repository = $topics_repository ?: new AIPS_Author_Topics_Repository();
		$this->logs_repository = $logs_repository ?: new AIPS_Author_Topic_Logs_Repository();
		$this->authors_repository = $authors_repository ?: new AIPS_Authors_Repository();
		$this->embeddings_repo = $embeddings_repo ?: ($container->has(AIPS_Embeddings_Repository::class) ? $container->make(AIPS_Embeddings_Repository::class) : new AIPS_Embeddings_Repository());
		$this->rate_limiter = $rate_limiter ?: ($container->has(AIPS_Embeddings_Rate_Limiter::class) ? $container->make(AIPS_Embeddings_Rate_Limiter::class) : new AIPS_Embeddings_Rate_Limiter());
		$this->embeddings_service = $embeddings_service ?: ($container->has(AIPS_Embeddings_Service::class) ? $container->make(AIPS_Embeddings_Service::class) : new AIPS_Embeddings_Service($this->ai_service, $this->logger, null, $this->rate_limiter, $this->embeddings_repo));
		// When a caller explicitly injects its own embeddings_service (e.g. tests), the
		// container's shared AIPS_Deduplication_Service singleton (built with the
		// production embeddings_service) must not silently override it.
		$this->deduplication_service = $deduplication_service ?: ($embeddings_service === null && $container->has(AIPS_Deduplication_Service::class) ? $container->make(AIPS_Deduplication_Service::class) : new AIPS_Deduplication_Service($this->embeddings_repo, null, $this->embeddings_service, null, $this->logger));
		$this->similarity_evaluator = $similarity_evaluator ?: ($container->has(AIPS_Similarity_Evaluator::class) ? $container->make(AIPS_Similarity_Evaluator::class) : new AIPS_Similarity_Evaluator(null, $this->embeddings_repo, $this->embeddings_service));
		$this->indexer_service = $indexer_service ?: ($container->has(AIPS_Content_Indexer_Service::class) ? $container->make(AIPS_Content_Indexer_Service::class) : null);
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
		
		// Flag semantically similar candidates against existing posts/topics (sets
		// metadata['potential_duplicate'] / ['duplicate_similarity'], which
		// apply_auto_approval_rules() below reads), then apply auto-approval rules.
		// This used to call AIPS_Similarity_Evaluator::evaluate_generated_author_topics(),
		// a method that doesn't exist, fataling on every real topic-generation run.
		$topics = $this->apply_fuzzy_duplicate_flags($author, $topics);
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

			// Queue new topic IDs for background continuous vector indexing
			$config = AIPS_Config::get_instance();
			$sync_topics = (bool) $config->get_option('aips_indexer_topics_continuous_sync', true);
			if ($sync_topics && $this->embeddings_service->is_enabled() && $this->indexer_service) {
				foreach ($saved_topics as $saved_topic) {
					$t_status = isset($saved_topic['status']) ? $saved_topic['status'] : 'pending';
					if ($t_status !== 'rejected' && !empty($saved_topic['id'])) {
						$this->indexer_service->enqueue_topic_for_indexing((int) $saved_topic['id']);
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
						$pvec = $this->embeddings_repo->decode_embedding($row->embedding, 'post', (int) $pid, !empty($row->content_hash) ? $row->content_hash : '');
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
				$paused_until  = !empty($cooldown_info['paused_until']) ? (int) $cooldown_info['paused_until'] : 0;
				$paused_str    = $paused_until > 0 ? AIPS_DateTime::fromTimestamp($paused_until)->toMysql() : 'unknown';
				$this->logger->log(
					sprintf('Author topic auto-approval skipped: Embeddings API in cooldown until %s. Reason: %s',
						$paused_str,
						$cooldown_info['reason']
					),
					'warning'
				);
				return $topics; // Graceful fallback: topics remain pending
			}

			$limit_check = $this->rate_limiter->check_limits();
			if (is_wp_error($limit_check)) {
				$this->logger->log(
					sprintf('Author topic auto-approval skipped: Embeddings rate limit quota reached (%s).',
						$limit_check->get_error_message()
					),
					'warning'
				);
				return $topics; // Graceful fallback: topics remain pending
			}

			$author_baseline_vec = $this->get_author_composite_embedding($author);
		}

		foreach ($topics as &$topic) {
			$eval_result = $this->similarity_evaluator->evaluate_author_topic_auto_approval($topic, $author, $author_baseline_vec);

			$topic['status']   = $eval_result['decision'];
			$topic['metadata'] = wp_json_encode($eval_result['meta']);

			if ('approved' === $eval_result['decision'] || 'rejected' === $eval_result['decision']) {
				$topic['reviewed_at'] = $now;
				$topic['reviewed_by'] = 0;
			} else {
				$topic['reviewed_at'] = 0;
				$topic['reviewed_by'] = null;
			}
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
