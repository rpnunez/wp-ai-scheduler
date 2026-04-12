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
	 * @var AIPS_Feedback_Repository Feedback repository for building quality context
	 */
	private $feedback_repository;
	
	/**
	 * @var AIPS_Prompt_Builder_Topic Topic prompt builder.
	 */
	private $prompt_builder;

	/**
	 * Initialize the generator.
	 *
	 * Dependencies are resolved from the container when available to ensure
	 * consistent singleton usage across the plugin. Optional parameters are
	 * retained for testing purposes only.
	 *
	 * @param AIPS_AI_Service_Interface|null $ai_service AI service instance (optional for testing).
	 * @param AIPS_Logger_Interface|null $logger Logger instance (optional for testing).
	 * @param object|null $topics_repository Topics repository (optional for testing).
	 * @param object|null $logs_repository Logs repository (optional for testing).
	 * @param object|null $embeddings_service Embeddings service (optional for testing).
	 * @param object|null $feedback_repository Feedback repository (optional for testing).
	 * @param object|null $prompt_builder Topic prompt builder (optional for testing).
	 */
	public function __construct(?AIPS_AI_Service_Interface $ai_service = null, ?AIPS_Logger_Interface $logger = null, $topics_repository = null, $logs_repository = null, $embeddings_service = null, $feedback_repository = null, $prompt_builder = null) {
		$container = AIPS_Container::get_instance();
		$this->ai_service = $ai_service ?: $container->make(AIPS_AI_Service_Interface::class);
		$this->logger = $logger ?: $container->make(AIPS_Logger_Interface::class);
		$this->topics_repository   = $topics_repository   ?: $container->make(AIPS_Author_Topics_Repository::class);
		$this->logs_repository     = $logs_repository     ?: $container->make(AIPS_Author_Topic_Logs_Repository::class);
		$this->embeddings_service  = $embeddings_service  ?: new AIPS_Embeddings_Service($this->ai_service, $this->logger);
		$this->feedback_repository = $feedback_repository ?: $container->make(AIPS_Feedback_Repository::class);
		$this->prompt_builder      = $prompt_builder      ?: new AIPS_Prompt_Builder_Topic();
	}
	
	/**
	 * Generate topics for an author.
	 *
	 * @param object $author Author object from database.
	 * @return array|WP_Error Array of generated topics or WP_Error on failure.
	 */
	public function generate_topics($author) {
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

				$this->logger->log("Created topic: {$topic_arr['topic_title']}", 'info', array(
					'topic_id' => $topic_arr['id'],
					'author_id' => $author->id
				));
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
					'generation_date' => current_time('mysql'),
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
					'generation_date' => current_time('mysql')
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
	 * @param object $author Author object.
	 * @param array  $topics Generated topic arrays.
	 * @return array Topics with updated metadata and score adjustments.
	 */
	private function apply_fuzzy_duplicate_flags($author, $topics) {
		if (empty($topics) || !$this->embeddings_service->is_embeddings_supported()) {
			return $topics;
		}

		$existing_topics = $this->topics_repository->get_by_author($author->id);
		if (empty($existing_topics)) {
			return $topics;
		}

		$candidate_existing = array();
		foreach ($existing_topics as $existing_topic) {
			$metadata = !empty($existing_topic->metadata) ? json_decode($existing_topic->metadata, true) : array();
			if (!is_array($metadata) || empty($metadata['embedding']) || !is_array($metadata['embedding'])) {
				continue;
			}

			$candidate_existing[] = array(
				'topic_title' => $existing_topic->topic_title,
				'embedding' => $metadata['embedding'],
			);
		}

		if (empty($candidate_existing)) {
			return $topics;
		}

		$threshold = (float) AIPS_Config::get_instance()->get_option('aips_topic_similarity_threshold');
		foreach ($topics as &$topic) {
			$text = isset($topic['topic_title']) ? (string) $topic['topic_title'] : '';
			if (empty($text)) {
				continue;
			}

			$embedding = $this->embeddings_service->generate_embedding($text);
			if (is_wp_error($embedding) || !is_array($embedding)) {
				continue;
			}

			$best_similarity = 0;
			$best_match = '';
			foreach ($candidate_existing as $existing) {
				$similarity = $this->embeddings_service->calculate_similarity($embedding, $existing['embedding']);
				if (!is_wp_error($similarity) && $similarity > $best_similarity) {
					$best_similarity = $similarity;
					$best_match = (string) $existing['topic_title'];
				}
			}

			$metadata = isset($topic['metadata']) ? json_decode($topic['metadata'], true) : array();
			if (!is_array($metadata)) {
				$metadata = array();
			}
			$metadata['embedding'] = $embedding;

			if ($best_similarity >= $threshold) {
				$metadata['potential_duplicate'] = true;
				$metadata['duplicate_similarity'] = round($best_similarity, 4);
				$metadata['duplicate_match'] = $best_match;
				$topic['score'] = max(0, ((int) (isset($topic['score']) ? $topic['score'] : 50)) - 15);
			} else {
				$metadata['potential_duplicate'] = false;
			}

			$topic['metadata'] = wp_json_encode($metadata);
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
