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
	 * @var AIPS_AI_Service AI service for making API calls
	 */
	private $ai_service;
	
	/**
	 * @var AIPS_Logger Logger instance
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
	 * Initialize the generator.
	 *
	 * @param object|null $ai_service AI service instance (optional for testing).
	 * @param object|null $logger Logger instance (optional for testing).
	 * @param object|null $topics_repository Topics repository (optional for testing).
	 * @param object|null $logs_repository Logs repository (optional for testing).
	 */
	public function __construct($ai_service = null, $logger = null, $topics_repository = null, $logs_repository = null) {
		$this->ai_service = $ai_service ?: new AIPS_AI_Service();
		$this->logger = $logger ?: new AIPS_Logger();
		$this->topics_repository = $topics_repository ?: new AIPS_Author_Topics_Repository();
		$this->logs_repository = $logs_repository ?: new AIPS_Author_Topic_Logs_Repository();
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
		
		// Build the prompt with feedback loop context
		$prompt = $this->build_topic_generation_prompt($author);
		
		// Use generate_json for structured topic data
		$response = $this->ai_service->generate_json($prompt, array(
			'max_tokens' => 2000,
			'temperature' => 0.7
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
	 * Build the prompt for topic generation with feedback loop context.
	 *
	 * @param object $author Author object from database.
	 * @return string The complete prompt.
	 */
	private function build_topic_generation_prompt($author) {
		$quantity = (int) $author->topic_generation_quantity;
		if ($quantity < 1) {
			$quantity = 5;
		}
		
		$prompt = "Generate {$quantity} unique and engaging blog post topic ideas about: {$author->field_niche}\n\n";
		
		// Add keywords if provided
		if (!empty($author->keywords)) {
			$prompt .= "Keywords/Focus Areas: {$author->keywords}\n\n";
		}
		
		// Add details/context if provided
		if (!empty($author->details)) {
			$prompt .= "Additional Context:\n{$author->details}\n\n";
		}
		
		// Add custom prompt if provided
		if (!empty($author->topic_generation_prompt)) {
			$prompt .= "{$author->topic_generation_prompt}\n\n";
		}
		
		// Add feedback loop context from approved topics
		$approved_topics = $this->topics_repository->get_approved_summary($author->id, 10);
		if (!empty($approved_topics)) {
			$prompt .= "Previously approved topics (for diversity - avoid duplicating these concepts):\n";
			foreach ($approved_topics as $topic) {
				$prompt .= "- {$topic}\n";
			}
			$prompt .= "\n";
		}
		
		// Add feedback loop context from rejected topics
		$rejected_topics = $this->topics_repository->get_rejected_summary($author->id, 10);
		if (!empty($rejected_topics)) {
			$prompt .= "Previously rejected topics (avoid similar ideas):\n";
			foreach ($rejected_topics as $topic) {
				$prompt .= "- {$topic}\n";
			}
			$prompt .= "\n";
		}
		
		$prompt .= "Requirements:\n";
		$prompt .= "- Each topic should be specific and actionable\n";
		$prompt .= "- Topics should be diverse and cover different aspects of {$author->field_niche}\n";
		$prompt .= "- Avoid duplicating previously approved or rejected topics\n";
		$prompt .= "- Format each topic as a clear, engaging blog post title\n\n";
		
		$prompt .= "Return a JSON array of objects. Each object must have:\n";
		$prompt .= "- \"title\": The blog post topic/title (string)\n";
		$prompt .= "- \"score\": Estimated engagement score 1-100 (integer)\n";
		$prompt .= "- \"keywords\": 3-5 relevant keywords (array of strings)\n\n";
		
		$prompt .= "Example format:\n";
		$prompt .= "[\n";
		$prompt .= "  {\n";
		$prompt .= "    \"title\": \"10 Best Practices for WordPress SEO in 2025\",\n";
		$prompt .= "    \"score\": 85,\n";
		$prompt .= "    \"keywords\": [\"WordPress\", \"SEO\", \"best practices\", \"2025\", \"optimization\"]\n";
		$prompt .= "  }\n";
		$prompt .= "]";
		
		return $prompt;
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
				? array_map('sanitize_text_field', $item['keywords']) 
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
		
		return $context;
	}
}
