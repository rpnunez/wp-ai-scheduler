<?php
/**
 * Editor Directive Service
 *
 * Provides targeted AI rewriting directives (punchier, expand, simplify, tone)
 * and multi-variant candidate generation for the WordPress post editor.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Editor_Directive_Service {

	/**
	 * Preset directive keys.
	 */
	const PRESET_PUNCHY     = 'punchy';
	const PRESET_EXPAND     = 'expand';
	const PRESET_SIMPLIFY   = 'simplify';
	const PRESET_TONE_SHIFT = 'tone_shift';

	/**
	 * @var AIPS_AI_Service_Interface
	 */
	private $ai_service;

	/**
	 * Constructor.
	 *
	 * @param AIPS_AI_Service_Interface|null $ai_service AI Service.
	 */
	public function __construct(?AIPS_AI_Service_Interface $ai_service = null) {
		$container        = AIPS_Container::get_instance();
		$this->ai_service = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
	}

	/**
	 * Get the list of built-in standard presets.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function get_standard_presets(): array {
		return array(
			self::PRESET_PUNCHY     => array(
				'label'       => __('Make Punchier', 'ai-post-scheduler'),
				'description' => __('Crisp, dynamic phrasing with active verbs and zero fluff.', 'ai-post-scheduler'),
				'instruction' => 'Rewrite the following text to make it punchier, more dynamic, and highly engaging. Use strong active verbs, crisp phrasing, and eliminate fluff while preserving the core meaning.',
			),
			self::PRESET_EXPAND     => array(
				'label'       => __('Expand with Examples', 'ai-post-scheduler'),
				'description' => __('Elaborate with practical details, analogies, and depth.', 'ai-post-scheduler'),
				'instruction' => 'Expand the following text by providing practical depth, clear explanations, and relevant real-world examples while maintaining a natural reading flow.',
			),
			self::PRESET_SIMPLIFY   => array(
				'label'       => __('Simplify & Clarify', 'ai-post-scheduler'),
				'description' => __('Accessible vocabulary, short sentences, and direct clarity.', 'ai-post-scheduler'),
				'instruction' => 'Simplify the following text. Use clear, accessible language, shorter sentences, and direct explanations. Eliminate passive voice and technical jargon.',
			),
			self::PRESET_TONE_SHIFT => array(
				'label'       => __('Tone Polish', 'ai-post-scheduler'),
				'description' => __('Authoritative, polished, and professional tone.', 'ai-post-scheduler'),
				'instruction' => 'Refine the tone of the following text to be authoritative, polished, and professional while retaining clarity and natural engagement.',
			),
		);
	}

	/**
	 * Apply a directive to content, generating 1 or multiple candidate variants.
	 *
	 * @param string $content           Content to rewrite.
	 * @param string $directive_preset  Optional preset key.
	 * @param string $custom_directive  Optional freeform directive.
	 * @param int    $variants_count    Number of variants requested (1 or 3).
	 * @param array  $context           Optional context (e.g., post_title, post_id).
	 * @return array<string>|WP_Error Array of candidate variation strings or WP_Error.
	 */
	public function apply_directive(string $content, string $directive_preset = '', string $custom_directive = '', int $variants_count = 1, array $context = array()) {
		if (!$this->ai_service || !$this->ai_service->is_available()) {
			return new WP_Error('ai_service_unavailable', __('AI service is currently unavailable. Please check your AI Engine configuration.', 'ai-post-scheduler'));
		}

		$content = trim($content);
		if ('' === $content) {
			return new WP_Error('empty_content', __('No content provided for rewriting.', 'ai-post-scheduler'));
		}

		$instruction = '';
		$presets     = $this->get_standard_presets();

		if ($directive_preset && isset($presets[$directive_preset])) {
			$instruction = $presets[$directive_preset]['instruction'];
		}

		if ($custom_directive) {
			$instruction = $instruction ? $instruction . ' Also follow this instruction: ' . $custom_directive : $custom_directive;
		}

		if (!$instruction) {
			$instruction = 'Rewrite and improve the following text for clarity, readability, and engagement.';
		}

		$variants_count = ($variants_count >= 3) ? 3 : 1;

		if ($variants_count === 3) {
			return $this->generate_multiple_variants($content, $instruction, $context);
		}

		return $this->generate_single_variant($content, $instruction, $context);
	}

	/**
	 * Generate a single rewritten variant.
	 *
	 * @param string $content     Original content.
	 * @param string $instruction Directive instruction.
	 * @param array  $context     Optional context.
	 * @return array<string>|WP_Error
	 */
	private function generate_single_variant(string $content, string $instruction, array $context = array()) {
		$prompt  = "You are an expert editorial assistant for WordPress.\n\n";
		$prompt .= "Instruction:\n" . $instruction . "\n\n";

		if (!empty($context['post_title'])) {
			$prompt .= "Article Context: Post Title is \"" . esc_attr($context['post_title']) . "\".\n\n";
		}

		$prompt .= "Original Text to Rewrite:\n\"\"\"\n" . $content . "\n\"\"\"\n\n";
		$prompt .= "Return ONLY the rewritten text without conversational introductions, explanations, or quotes around the output.";

		$response = $this->ai_service->generate_text($prompt);
		if (is_wp_error($response)) {
			return $response;
		}

		$cleaned = trim((string) $response);
		// Strip outer markdown quotes if LLM added them
		$cleaned = preg_replace('/^["\']|["\']$/u', '', $cleaned);

		return array($cleaned);
	}

	/**
	 * Generate 3 distinct creative variants.
	 *
	 * @param string $content     Original content.
	 * @param string $instruction Directive instruction.
	 * @param array  $context     Optional context.
	 * @return array<string>|WP_Error
	 */
	private function generate_multiple_variants(string $content, string $instruction, array $context = array()) {
		$prompt  = "You are an expert editorial assistant for WordPress.\n\n";
		$prompt .= "Instruction:\n" . $instruction . "\n\n";

		if (!empty($context['post_title'])) {
			$prompt .= "Article Context: Post Title is \"" . esc_attr($context['post_title']) . "\".\n\n";
		}

		$prompt .= "Original Text to Rewrite:\n\"\"\"\n" . $content . "\n\"\"\"\n\n";
		$prompt .= "Provide exactly 3 distinctly different creative variations of the rewritten text.\n";
		$prompt .= "Output MUST be a valid JSON array of 3 strings, with no additional text or formatting outside the JSON array.\n";
		$prompt .= "Example format:\n[\"Variation 1 text here\", \"Variation 2 text here\", \"Variation 3 text here\"]";

		$response = $this->ai_service->generate_text($prompt);
		if (is_wp_error($response)) {
			return $response;
		}

		$raw = trim((string) $response);

		// Extract JSON array if surrounded by markdown code blocks
		if (preg_match('/\[[\s\S]*\]/', $raw, $matches)) {
			$decoded = json_decode($matches[0], true);
			if (is_array($decoded) && count($decoded) > 0) {
				return array_slice(array_map('strval', array_map('trim', $decoded)), 0, 3);
			}
		}

		// Fallback: split by line breaks or return raw as single variant
		$lines = array_filter(array_map('trim', explode("\n", $raw)));
		if (count($lines) >= 3) {
			return array_slice(array_values($lines), 0, 3);
		}

		return array($raw);
	}

	/**
	 * Generate 3 creative candidates for post title or excerpt.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $component 'title' or 'excerpt'.
	 * @return array<string>|WP_Error
	 */
	public function generate_component_variants(int $post_id, string $component = 'title') {
		if (!$this->ai_service || !$this->ai_service->is_available()) {
			return new WP_Error('ai_service_unavailable', __('AI service is currently unavailable.', 'ai-post-scheduler'));
		}

		$post = get_post($post_id);
		if (!$post) {
			return new WP_Error('post_not_found', __('Post not found.', 'ai-post-scheduler'));
		}

		$content_sample = wp_strip_all_tags($post->post_content);
		if (strlen($content_sample) > 2000) {
			$content_sample = substr($content_sample, 0, 2000) . '...';
		}

		if ('title' === $component) {
			$prompt  = "You are an expert SEO copywriter for WordPress.\n\n";
			$prompt .= "Current Post Title: \"" . esc_attr($post->post_title) . "\"\n";
			$prompt .= "Post Excerpt: \"" . esc_attr($post->post_excerpt) . "\"\n";
			$prompt .= "Post Content Sample:\n" . $content_sample . "\n\n";
			$prompt .= "Generate 3 highly engaging, click-worthy, SEO-optimized title variations for this post.\n";
			$prompt .= "Return ONLY a valid JSON array of 3 strings: [\"Title 1\", \"Title 2\", \"Title 3\"]. No extra text.";
		} else {
			$prompt  = "You are an expert editorial writer for WordPress.\n\n";
			$prompt .= "Post Title: \"" . esc_attr($post->post_title) . "\"\n";
			$prompt .= "Post Content Sample:\n" . $content_sample . "\n\n";
			$prompt .= "Generate 3 compelling 40-to-60-word excerpt variations summarizing this post.\n";
			$prompt .= "Return ONLY a valid JSON array of 3 strings: [\"Excerpt 1\", \"Excerpt 2\", \"Excerpt 3\"]. No extra text.";
		}

		$response = $this->ai_service->generate_text($prompt);
		if (is_wp_error($response)) {
			return $response;
		}

		$raw = trim((string) $response);
		if (preg_match('/\[[\s\S]*\]/', $raw, $matches)) {
			$decoded = json_decode($matches[0], true);
			if (is_array($decoded) && count($decoded) > 0) {
				return array_slice(array_map('strval', array_map('trim', $decoded)), 0, 3);
			}
		}

		return array($raw);
	}
}
