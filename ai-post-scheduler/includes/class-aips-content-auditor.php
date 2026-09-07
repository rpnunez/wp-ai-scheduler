<?php
/**
 * Content Auditor Service
 *
 * Analyzes existing content to identify gaps, internal link silos, decay, and opportunities.
 *
 * @package AI_Post_Scheduler
 * @since 1.9.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Content_Auditor
 *
 * Scans local content, builds internal link & entity graphs, and interfaces with AI to find content gaps.
 */
class AIPS_Content_Auditor {

	/**
	 * @var AIPS_AI_Service_Interface AI service for making API calls
	 */
	private $ai_service;

	/**
	 * @var AIPS_Logger_Interface Logger instance
	 */
	private $logger;

	/**
	 * @var AIPS_Content_Auditor_Scanner Scanner instance
	 */
	private $scanner;

	/**
	 * @var AIPS_Content_Auditor_Engine Engine instance
	 */
	private $engine;

	/**
	 * Initialize the auditor.
	 *
	 * @param AIPS_AI_Service_Interface|null    $ai_service AI service instance.
	 * @param AIPS_Logger_Interface|null        $logger     Logger instance.
	 * @param AIPS_Content_Auditor_Scanner|null $scanner    Scanner instance.
	 * @param AIPS_Content_Auditor_Engine|null  $engine     Engine instance.
	 */
	public function __construct(
		?AIPS_AI_Service_Interface $ai_service = null,
		?AIPS_Logger_Interface $logger = null,
		?AIPS_Content_Auditor_Scanner $scanner = null,
		?AIPS_Content_Auditor_Engine $engine = null
	) {
		$container = AIPS_Container::get_instance();
		$this->ai_service = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
		$this->logger = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
		$this->scanner = $scanner ?: new AIPS_Content_Auditor_Scanner();
		$this->engine = $engine ?: new AIPS_Content_Auditor_Engine($this->ai_service, $this->logger);
	}

	/**
	 * Get the scanner instance.
	 *
	 * @return AIPS_Content_Auditor_Scanner
	 */
	public function get_scanner() {
		if (null === $this->scanner) {
			$container = AIPS_Container::get_instance();
			$this->scanner = $container->has(AIPS_Content_Auditor_Scanner::class) ? $container->make(AIPS_Content_Auditor_Scanner::class) : new AIPS_Content_Auditor_Scanner();
		}
		return $this->scanner;
	}

	/**
	 * Get the engine instance.
	 *
	 * @return AIPS_Content_Auditor_Engine
	 */
	public function get_engine() {
		if (null === $this->engine) {
			$container = AIPS_Container::get_instance();
			$this->engine = $container->has(AIPS_Content_Auditor_Engine::class) ? $container->make(AIPS_Content_Auditor_Engine::class) : new AIPS_Content_Auditor_Engine($this->ai_service, $this->logger);
		}
		return $this->engine;
	}

	/**
	 * Scan site library and return structured fingerprints.
	 *
	 * @param int $limit Maximum posts to scan.
	 * @return array
	 */
	public function scan_site_library($limit = 200) {
		return $this->get_scanner()->scan_library($limit);
	}

	/**
	 * Build and return the internal link graph across library fingerprints.
	 *
	 * @param array|null $fingerprints Optional pre-scanned fingerprints.
	 * @param int        $limit Default limit when scanning on-demand.
	 * @return array
	 */
	public function get_link_graph($fingerprints = null, $limit = 200) {
		if ($fingerprints === null) {
			$fingerprints = $this->scan_site_library($limit);
		}
		return $this->get_scanner()->build_link_graph($fingerprints);
	}

	/**
	 * Build and return entity clusters, decay, and cannibalization insights.
	 *
	 * @param array|null $fingerprints Optional pre-scanned fingerprints.
	 * @param int        $limit Default limit when scanning on-demand.
	 * @return array
	 */
	public function get_entity_clusters($fingerprints = null, $limit = 200) {
		if ($fingerprints === null) {
			$fingerprints = $this->scan_site_library($limit);
		}
		return $this->get_scanner()->build_entity_clusters($fingerprints);
	}

	/**
	 * Run a full multi-dimensional content audit (5-Pillars + Health Scorecard).
	 *
	 * @param string $niche Target niche.
	 * @param array  $options Audit options.
	 * @return array Full audit report array.
	 */
	public function run_full_audit($niche, array $options = array()) {
		$limit = isset($options['limit']) ? max(10, (int) $options['limit']) : 200;

		$this->logger->log("Executing full 5-pillar content audit for niche: {$niche}", 'info');

		$fingerprints    = $this->scan_site_library($limit);
		$link_graph      = $this->get_link_graph($fingerprints);
		$entity_clusters = $this->get_entity_clusters($fingerprints);

		return $this->get_engine()->run_full_audit($niche, $fingerprints, $link_graph, $entity_clusters, $options);
	}

	/**
	 * Get a summary of existing site content.
	 *
	 * Fetches recent published posts to provide context for the AI.
	 *
	 * @param int $limit Number of posts to fetch.
	 * @return array Array of post summaries (title, categories).
	 */
	public function get_site_content_summary($limit = 100) {
		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids', // Fetch IDs first to be lighter
		);

		$query = new WP_Query($args);
		$summary = array();

		if ($query->have_posts()) {
			if (!empty($query->posts) && function_exists('_prime_post_caches')) {
				_prime_post_caches(array_unique(array_filter(array_map('intval', $query->posts))), false, true);
			}
			foreach ($query->posts as $post_id) {
				$title = get_the_title($post_id);
				$categories = get_the_category($post_id);
				$cat_names = array();

				if ($categories) {
					foreach ($categories as $cat) {
						$cat_names[] = $cat->name;
					}
				}

				$summary[] = array(
					'title' => $title,
					'categories' => implode(', ', $cat_names),
				);
			}
		}

		return $summary;
	}

	/**
	 * Perform a gap analysis for a specific niche.
	 *
	 * @param string $niche The target niche to analyze.
	 * @return array|WP_Error Array of gap opportunities or WP_Error on failure.
	 */
	public function perform_gap_analysis($niche) {
		$this->logger->log("Starting gap analysis for niche: {$niche}", 'info');

		$fingerprints    = $this->scan_site_library(100);
		$entity_clusters = $this->get_entity_clusters($fingerprints);

		$result = $this->get_engine()->analyze_topic_gaps($niche, $fingerprints, $entity_clusters);

		if (empty($result['gaps'])) {
			// Fallback legacy prompt
			return $this->perform_gap_analysis_fallback($niche);
		}

		$this->logger->log("Gap analysis completed successfully. Found " . count($result['gaps']) . " gaps.", 'info');

		return $result['gaps'];
	}

	/**
	 * Perform a gap analysis for a specific niche (fallback method).
	 *
	 * Uses generate_text and manual JSON parsing.
	 *
	 * @param string $niche The target niche to analyze.
	 * @return array|WP_Error Array of gap opportunities or WP_Error on failure.
	 */
	public function perform_gap_analysis_fallback($niche) {
		$this->logger->log("Starting gap analysis (fallback) for niche: {$niche}", 'info');

		// 1. Ingest existing content
		$existing_content = $this->get_site_content_summary(100);

		if (empty($existing_content)) {
			$this->logger->log("No existing content found for gap analysis.", 'info');
		}

		// 2. Construct the prompt
		$prompt = $this->generate_gap_analysis_prompt($niche, $existing_content);

		// 3. Call AI Service
		$options = array(
			'temperature' => 0.7,
		);

		$response = $this->ai_service->generate_text($prompt, $options);

		if (is_wp_error($response)) {
			$this->logger->log("Gap analysis AI call failed: " . $response->get_error_message(), 'error');
			return $response;
		}

		// 4. Parse JSON from text response
		$parsed_response = $this->parse_json_response($response);

		// 5. Validate and return results
		if (!is_array($parsed_response)) {
			$this->logger->log("Gap analysis returned invalid JSON format.", 'error');
			return new WP_Error('invalid_response', 'AI returned invalid data format.');
		}

		$this->logger->log("Gap analysis completed successfully. Found " . count($parsed_response) . " gaps.", 'info');

		return $parsed_response;
	}

	/**
	 * Parse JSON from AI text response.
	 * Handles markdown code blocks and raw JSON.
	 *
	 * @param string $response The raw text response from AI.
	 * @return array|null Parsed array or null on failure.
	 */
	private function parse_json_response($response) {
		$decoded = AIPS_JSON_Extractor::decode_json_response( $response );

		return is_wp_error( $decoded ) ? null : $decoded;
	}

	/**
	 * JSON schema for the gap analysis array returned by the AI.
	 *
	 * @return array<string, mixed>
	 */
	private function get_gap_analysis_json_schema(): array {
		return array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					'missing_topic' => array('type' => 'string'),
					'priority'      => array('type' => 'string', 'enum' => array('High', 'Medium')),
					'reason'        => array('type' => 'string'),
					'search_intent' => array('type' => 'string'),
				),
				'required' => array('missing_topic', 'priority', 'reason', 'search_intent'),
			),
		);
	}

	/**
	 * Generate the prompt for the AI.
	 *
	 * @param string $niche The target niche.
	 * @param array $existing_content List of existing content summaries.
	 * @return string The constructed prompt.
	 */
	private function generate_gap_analysis_prompt($niche, $existing_content) {
		$content_list = "";
		if (!empty($existing_content)) {
			foreach ($existing_content as $item) {
				$content_list .= "- {$item['title']} (Category: {$item['categories']})\n";
			}
		} else {
			$content_list = "(No existing content found)";
		}

		$prompt = "You are an SEO Content Strategist. The website's core niche is: {$niche}.\n\n";
		$prompt .= "Here is a list of the last " . count($existing_content) . " published articles on the site:\n";
		$prompt .= $content_list . "\n\n";

		$prompt .= "Task: Analyze the existing content coverage against the target niche. Identify 5-7 major sub-topics, 'pillar' pages, or content clusters that are MISSING or under-represented.\n\n";

		$prompt .= "Return a JSON array where each item has: \"missing_topic\" (string), \"priority\" (\"High\" or \"Medium\"), \"reason\" (string), \"search_intent\" (string).";

		return $prompt;
	}
}
