<?php
/**
 * Author Suggestions Service
 *
 * Uses AI to generate author profile suggestions based on site/blog context inputs.
 * Admins can review suggestions and import them as ready-to-use author profiles.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.2
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Author_Suggestions_Service
 *
 * Generates AI-powered author profile suggestions based on a site's niche,
 * target audience and content goals. Each suggestion is a complete author
 * profile (name, niche, description, keywords, voice_tone, writing_style,
 * topic_generation_prompt) that can be imported directly from the Authors UI.
 */
class AIPS_Author_Suggestions_Service {

	/**
	 * @var AIPS_AI_Service AI service for making API calls.
	 */
	private $ai_service;

	/**
	 * @var AIPS_Logger Logger instance.
	 */
	private $logger;

	/**
	 * Initialize the service.
	 *
	 * @param AIPS_AI_Service|null $ai_service AI service instance (optional for testing).
	 * @param AIPS_Logger|null     $logger     Logger instance (optional for testing).
	 */
	public function __construct($ai_service = null, $logger = null) {
		$this->ai_service = $ai_service ?: new AIPS_AI_Service();
		$this->logger     = $logger ?: new AIPS_Logger();
	}

	/**
	 * Generate author profile suggestions using AI.
	 *
	 * @param array $inputs {
	 *     Context inputs for suggestion generation.
	 *
	 *     @type string $site_niche      The site/blog niche or primary topic. Required.
	 *     @type string $target_audience Description of the intended readers. Optional.
	 *     @type string $content_goals   What the content aims to achieve. Optional.
	 *     @type string $site_url        URL of the site (used for context only). Optional.
	 * }
	 * @param int   $count Number of author suggestions to generate (1–10). Default 3.
	 * @return array|WP_Error Array of suggestion arrays, or WP_Error on failure.
	 */
	public function suggest_authors(array $inputs, $count = 3) {
		$count = max(1, min(10, (int) $count));

		// Fall back to site-wide content settings when a field is not provided in $inputs.
		$site_ctx = AIPS_Site_Context::get();

		$site_niche = !empty($inputs['site_niche']) ? sanitize_text_field($inputs['site_niche']) : $site_ctx['niche'];
		if (empty($site_niche)) {
			return new WP_Error('missing_niche', __('Site niche is required to generate author suggestions.', 'ai-post-scheduler'));
		}

		// Merge site defaults into inputs so build_suggestion_prompt() has a complete picture.
		$merged_inputs = array_merge(
			array(
				'target_audience' => $site_ctx['target_audience'],
				'content_goals'   => $site_ctx['content_goals'],
				'brand_voice'     => $site_ctx['brand_voice'],
				'site_url'        => '',
			),
			$inputs,
			array('site_niche' => $site_niche)
		);

		$prompt = $this->build_suggestion_prompt($merged_inputs, $count);

		$this->logger->log(
			"Generating {$count} author suggestion(s) for niche: {$site_niche}",
			'info',
			array('niche' => $site_niche, 'count' => $count)
		);

		$response = $this->ai_service->generate_json($prompt, array(
			'max_tokens' => 2000,
			'temperature' => 0.8,
		));

		if (is_wp_error($response)) {
			$this->logger->log('Author suggestions AI call failed: ' . $response->get_error_message(), 'error');
			return $response;
		}

		$suggestions = $this->parse_suggestions($response, $count);

		if (empty($suggestions)) {
			$this->logger->log('No author suggestions parsed from AI response.', 'warning');
			return new WP_Error('no_suggestions_parsed', __('Could not parse author suggestions from the AI response. Please try again.', 'ai-post-scheduler'));
		}

		$this->logger->log(
			'Generated ' . count($suggestions) . ' author suggestion(s).',
			'info',
			array('count' => count($suggestions))
		);

		return $suggestions;
	}

	/**
	 * Build the AI prompt for author suggestion generation.
	 *
	 * @param array $inputs Context inputs (see suggest_authors()).
	 * @param int   $count  Number of suggestions to request.
	 * @return string The assembled prompt.
	 */
	private function build_suggestion_prompt(array $inputs, $count) {
		$site_niche      = isset($inputs['site_niche']) ? sanitize_text_field($inputs['site_niche']) : '';
		$target_audience = isset($inputs['target_audience']) ? sanitize_text_field($inputs['target_audience']) : '';
		$content_goals   = isset($inputs['content_goals']) ? sanitize_textarea_field($inputs['content_goals']) : '';
		$brand_voice     = isset($inputs['brand_voice']) ? sanitize_text_field($inputs['brand_voice']) : '';
		$site_url        = isset($inputs['site_url']) ? esc_url_raw($inputs['site_url']) : '';

		$prompt  = "You are an expert content strategist.\n\n";
		$prompt .= "A blog or website needs {$count} distinct AI author persona(s) to produce varied, high-quality content.\n\n";
		$prompt .= "Site niche / primary topic: {$site_niche}\n";

		if (!empty($target_audience)) {
			$prompt .= "Target audience: {$target_audience}\n";
		}

		if (!empty($content_goals)) {
			$prompt .= "Content goals: {$content_goals}\n";
		}

		if (!empty($brand_voice)) {
			$prompt .= "Overall brand voice/tone: {$brand_voice} — each author's voice should complement but remain distinct from this.\n";
		}

		if (!empty($site_url)) {
			$prompt .= "Site URL (for reference only): {$site_url}\n";
		}

		$prompt .= "\nFor each author persona, devise:\n";
		$prompt .= "- A realistic-sounding pen name\n";
		$prompt .= "- A specific sub-niche or specialisation within the primary topic\n";
		$prompt .= "- A short bio that establishes credibility and perspective\n";
		$prompt .= "- 3-6 focus keywords that define their content territory\n";
		$prompt .= "- A writing voice/tone (e.g. \"conversational\", \"authoritative\", \"empathetic\")\n";
		$prompt .= "- A writing style (e.g. \"how-to guides\", \"opinion pieces\", \"data-driven analysis\")\n";
		$prompt .= "- A one-sentence topic generation prompt that instructs the AI on what kinds of post ideas to create for this author\n\n";

		$prompt .= "Important: Make each persona clearly distinct from the others. Avoid overlapping niches.\n\n";

		$prompt .= "Return a JSON array of exactly {$count} object(s). Each object must have these keys:\n";
		$prompt .= "- \"name\": string — pen name\n";
		$prompt .= "- \"field_niche\": string — specific sub-niche / specialisation\n";
		$prompt .= "- \"description\": string — short bio (2-3 sentences)\n";
		$prompt .= "- \"keywords\": string — comma-separated focus keywords\n";
		$prompt .= "- \"voice_tone\": string — writing voice/tone\n";
		$prompt .= "- \"writing_style\": string — writing style type\n";
		$prompt .= "- \"topic_generation_prompt\": string — one-sentence instruction for topic generation\n\n";

		$prompt .= "Example format:\n";
		$prompt .= "[\n  {\n    \"name\": \"Alex Rivera\",\n    \"field_niche\": \"Personal Finance for Millennials\",\n    \"description\": \"Alex is a certified financial planner who specialises in helping millennials navigate student debt, investing and home ownership. He writes in a down-to-earth way that makes money talk accessible.\",\n    \"keywords\": \"budgeting, investing, debt payoff, savings, retirement\",\n    \"voice_tone\": \"conversational and encouraging\",\n    \"writing_style\": \"practical how-to guides with real examples\",\n    \"topic_generation_prompt\": \"Generate actionable personal finance topics aimed at millennials dealing with student loans, career changes and early investing.\"\n  }\n]";

		return $prompt;
	}

	/**
	 * Parse AI response into validated author suggestion arrays.
	 *
	 * @param array $json_data Parsed JSON data from AI.
	 * @param int   $count     Expected maximum number of suggestions.
	 * @return array Array of validated suggestion arrays.
	 */
	private function parse_suggestions(array $json_data, $count) {
		$suggestions = array();

		foreach ($json_data as $item) {
			if (!is_array($item)) {
				continue;
			}

			$name = isset($item['name']) ? sanitize_text_field($item['name']) : '';
			if (empty($name)) {
				continue;
			}

			$field_niche = isset($item['field_niche']) ? sanitize_text_field($item['field_niche']) : '';
			if (empty($field_niche)) {
				continue;
			}

			$suggestions[] = array(
				'name'                     => $name,
				'field_niche'              => $field_niche,
				'description'              => isset($item['description']) ? sanitize_textarea_field($item['description']) : '',
				'keywords'                 => isset($item['keywords']) ? sanitize_text_field($item['keywords']) : '',
				'voice_tone'               => isset($item['voice_tone']) ? sanitize_text_field($item['voice_tone']) : '',
				'writing_style'            => isset($item['writing_style']) ? sanitize_text_field($item['writing_style']) : '',
				'topic_generation_prompt'  => isset($item['topic_generation_prompt']) ? sanitize_textarea_field($item['topic_generation_prompt']) : '',
			);

			if (count($suggestions) >= $count) {
				break;
			}
		}

		return $suggestions;
	}
}
