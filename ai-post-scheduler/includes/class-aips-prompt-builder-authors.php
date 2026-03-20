<?php
/**
 * Author Suggestions Prompt Builder
 *
 * Responsible for assembling AI prompts that are used exclusively for
 * generating author profile suggestions. Extracted from
 * AIPS_Author_Suggestions_Service to keep prompt construction in the
 * prompt-builder layer.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.2
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Builder_Authors
 *
 * Builds AI prompts for author persona suggestion generation.
 */
class AIPS_Prompt_Builder_Authors {

	/**
	 * @var AIPS_Prompt_Builder Base prompt builder for shared helpers.
	 */
	private $base_builder;

	/**
	 * @param AIPS_Prompt_Builder|null $base_builder Optional; instantiated automatically when null.
	 */
	public function __construct($base_builder = null) {
		$this->base_builder = $base_builder ?: new AIPS_Prompt_Builder();
	}

	/**
	 * Build the AI prompt for author suggestion generation.
	 *
	 * @param array $inputs {
	 *     Context inputs.
	 *
	 *     @type string $site_niche      Required. The site/blog niche.
	 *     @type string $target_audience Optional. Intended readers.
	 *     @type string $content_goals   Optional. What the content aims to achieve.
	 *     @type string $brand_voice     Optional. Overall brand voice/tone.
	 *     @type string $site_url        Optional. Site URL for context only.
	 * }
	 * @param int $count Number of author suggestions to request.
	 * @return string
	 */
	public function build(array $inputs, $count) {
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
		$prompt .= "[\n";
		$prompt .= "  {\n";
		$prompt .= "    \"name\": \"Alex Rivera\",\n";
		$prompt .= "    \"field_niche\": \"Personal Finance for Millennials\",\n";
		$prompt .= "    \"description\": \"Alex is a certified financial planner who specialises in helping millennials navigate student debt, investing and home ownership. He writes in a down-to-earth way that makes money talk accessible.\",\n";
		$prompt .= "    \"keywords\": \"budgeting, investing, debt payoff, savings, retirement\",\n";
		$prompt .= "    \"voice_tone\": \"conversational and encouraging\",\n";
		$prompt .= "    \"writing_style\": \"practical how-to guides with real examples\",\n";
		$prompt .= "    \"topic_generation_prompt\": \"Generate actionable personal finance topics aimed at millennials dealing with student loans, career changes and early investing.\"\n";
		$prompt .= "  }\n";
		$prompt .= "]";

		return $prompt;
	}
}
