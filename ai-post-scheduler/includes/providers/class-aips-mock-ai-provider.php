<?php
/**
 * Mock AI Provider
 *
 * Deterministic offline AI provider for automated testing, development sandboxes,
 * and dry-run pipeline simulations without incurring API costs or requiring live
 * external service connectivity.
 *
 * @package AI_Post_Scheduler
 * @since 3.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Mock_AI_Provider implements AIPS_AI_Provider_Interface {

	/**
	 * Map of substring/regex prompt matches to predefined responses.
	 *
	 * @var array<string, string|array>
	 */
	private static $custom_responses = array();

	/**
	 * Forced error scenario for resilience testing.
	 *
	 * @var string|null
	 */
	private static $forced_error = null;

	/**
	 * Set a custom mock response for testing.
	 *
	 * @param string       $pattern Substring match in prompt.
	 * @param string|array $response Predefined response.
	 * @return void
	 */
	public static function set_custom_response(string $pattern, $response): void {
		self::$custom_responses[$pattern] = $response;
	}

	/**
	 * Clear all custom responses.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$custom_responses = array();
		self::$forced_error = null;
	}

	/**
	 * Force next AI call to throw an error for resilience testing.
	 *
	 * @param string|null $error_code 'rate_limit', 'quota_exceeded', 'timeout', or custom message.
	 * @return void
	 */
	public static function force_error(?string $error_code): void {
		self::$forced_error = $error_code;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'mock';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Mock AI Simulator (Offline Sandbox)', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_available(): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_unavailable_reason(): string {
		return '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate_text(string $prompt, array $params): string {
		$this->check_forced_error();

		// Check registered custom responses.
		foreach (self::$custom_responses as $pattern => $response) {
			if (stripos($prompt, $pattern) !== false) {
				return is_array($response) ? wp_json_encode($response) : (string) $response;
			}
		}

		/**
		 * Filter prompt completion in mock provider.
		 *
		 * @param string|null $override Pre-computed response.
		 * @param string      $prompt   Prompt text.
		 * @param array       $params   Request parameters.
		 */
		$override = apply_filters('aips_mock_ai_generate_text', null, $prompt, $params);
		if ($override !== null) {
			return (string) $override;
		}

		// Outline generation detection.
		if (stripos($prompt, 'outline') !== false || stripos($prompt, 'table of contents') !== false) {
			return $this->mock_outline($prompt);
		}

		// SEO Title / Excerpt / Meta detection.
		if (stripos($prompt, 'seo title') !== false || stripos($prompt, 'meta description') !== false) {
			return $this->mock_seo($prompt);
		}

		// JSON requested in text mode.
		if (stripos($prompt, 'json') !== false || stripos($prompt, 'return only valid json') !== false) {
			return $this->mock_json_text($prompt);
		}

		// Default realistic blog article content.
		return $this->mock_article_content($prompt);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate_json(?string $prompt, array $params): ?array {
		$this->check_forced_error();

		if ($prompt !== null) {
			foreach (self::$custom_responses as $pattern => $response) {
				if (stripos($prompt, $pattern) !== false && is_array($response)) {
					return $response;
				}
			}
		}

		/**
		 * Filter structured JSON generation in mock provider.
		 *
		 * @param array|null  $override Pre-computed JSON array.
		 * @param string|null $prompt   Prompt text.
		 * @param array       $params   Request parameters.
		 */
		$override = apply_filters('aips_mock_ai_generate_json', null, $prompt, $params);
		if ($override !== null && is_array($override)) {
			return $override;
		}

		// Topic suggestions schema.
		if ($prompt !== null && (stripos($prompt, 'topic') !== false || stripos($prompt, 'ideas') !== false)) {
			return array(
				'topics' => array(
					array(
						'title'       => '10 Modern Strategies for Automated Content Scheduling',
						'summary'     => 'An in-depth guide to improving publishing velocity with AI editorial workflows.',
						'keywords'    => array('automation', 'content strategy', 'wordpress'),
						'relevance'   => 95,
					),
					array(
						'title'       => 'How to Scale Your Editorial Calendar with Smart Pipelines',
						'summary'     => 'Step-by-step tactics to streamline post drafts and maintain brand consistency.',
						'keywords'    => array('editorial calendar', 'scheduling', 'productivity'),
						'relevance'   => 88,
					),
				),
			);
		}

		// Review / Component regeneration schema.
		return array(
			'title'       => 'Mock Generated Post: ' . gmdate('Y-m-d H:i'),
			'excerpt'     => 'A synthetic mock excerpt generated by the offline sandbox pipeline.',
			'content'     => "<h2>Introduction</h2>\n<p>This is a simulated article body generated in offline test mode.</p>\n<h2>Key Takeaways</h2>\n<p>Automated pipelines enhance reproducibility and resilience.</p>",
			'categories'  => array('Automation'),
			'tags'        => array('WordPress', 'AI', 'Testing'),
			'seo_score'   => 92,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate_image(string $prompt, array $params): string {
		$this->check_forced_error();

		// Return a valid SVG data URI placeholder for testing without network requests.
		$escaped_prompt = esc_attr(substr(strip_tags($prompt), 0, 40));
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630">'
			. '<rect width="100%" height="100%" fill="#2271b1"/>'
			. '<text x="50%" y="45%" dominant-baseline="middle" text-anchor="middle" fill="#ffffff" font-size="32" font-family="sans-serif">AI Post Scheduler Mock Visual</text>'
			. '<text x="50%" y="58%" dominant-baseline="middle" text-anchor="middle" fill="#c3c4c7" font-size="20" font-family="sans-serif">' . $escaped_prompt . '...</text>'
			. '</svg>';

		return 'data:image/svg+xml;base64,' . base64_encode($svg);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate_embedding(string $text, array $params): array {
		$this->check_forced_error();

		// Generate a deterministic 64-dimensional float vector based on sha256 hash seeds.
		$vector = array();
		$hash = hash('sha256', $text);
		$len = strlen($hash);

		for ($i = 0; $i < 64; $i++) {
			$chunk = substr($hash, ($i * 2) % ($len - 2), 2);
			$val = (hexdec($chunk) - 128) / 128.0;
			$vector[] = round($val, 6);
		}

		return $vector;
	}

	/**
	 * {@inheritdoc}
	 */
	public function supports_native_json(): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function supports_embeddings(): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function supports_conversation(): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function extract_error_code(string $message): string {
		$lower = strtolower($message);
		if (strpos($lower, 'rate_limit') !== false || strpos($lower, '429') !== false) {
			return 'rate_limit';
		}
		if (strpos($lower, 'quota') !== false) {
			return 'quota_exceeded';
		}
		if (strpos($lower, 'timeout') !== false) {
			return 'timeout';
		}
		return '';
	}

	/**
	 * Check if an error should be thrown.
	 *
	 * @throws Exception
	 */
	private function check_forced_error(): void {
		if (self::$forced_error !== null) {
			$err = self::$forced_error;
			self::$forced_error = null;
			throw new Exception('Mock AI Error: ' . $err);
		}
	}

	/**
	 * Generate mock outline.
	 */
	private function mock_outline(string $prompt): string {
		return "## I. Introduction\n- Overview of the core challenge\n- Purpose of this guide\n\n## II. Core Strategies\n- Strategy 1: Systematic workflow automation\n- Strategy 2: Modular stage design\n\n## III. Implementation Best Practices\n- Guardrails and quality validation\n- Observability and error recovery\n\n## IV. Conclusion\n- Key summary points";
	}

	/**
	 * Generate mock SEO metadata.
	 */
	private function mock_seo(string $prompt): string {
		return "Title: Mastering Content Automation in WordPress\nMeta Description: Discover proven patterns and architectural strategies to automate high-quality WordPress publishing with AI Post Scheduler.";
	}

	/**
	 * Generate mock JSON text.
	 */
	private function mock_json_text(string $prompt): string {
		return wp_json_encode(array(
			'status'  => 'success',
			'title'   => 'Automated Content Automation Best Practices',
			'summary' => 'Comprehensive architectural guide to publishing workflows.',
			'score'   => 94,
		));
	}

	/**
	 * Generate mock article content.
	 */
	private function mock_article_content(string $prompt): string {
		return "<!-- wp:heading -->\n<h2>Introduction</h2>\n<!-- /wp:heading -->\n\n"
			. "<!-- wp:paragraph -->\n<p>Modern web publishing demands consistency, speed, and uncompromising editorial standards. By orchestrating generation pipelines through structured middleware stages, editorial teams can reliably scale high-value content production.</p>\n<!-- /wp:paragraph -->\n\n"
			. "<!-- wp:heading -->\n<h2>Key Architectural Pillars</h2>\n<!-- /wp:heading -->\n\n"
			. "<!-- wp:paragraph -->\n<p>Modular pipeline processing ensures that every stage — from topic research and outline structuring to SEO optimization and factual grounding — operates under defined contracts with full observability.</p>\n<!-- /wp:paragraph -->\n\n"
			. "<!-- wp:heading -->\n<h2>Conclusion</h2>\n<!-- /wp:heading -->\n\n"
			. "<!-- wp:paragraph -->\n<p>Implementing these automated patterns allows creators to focus on high-impact strategy while automated systems handle routine drafting, indexing, and scheduling.</p>\n<!-- /wp:paragraph -->";
	}
}
