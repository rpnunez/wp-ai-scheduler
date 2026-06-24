<?php
/**
 * Research Prompt Builder
 *
 * Responsible for assembling AI prompts used by topic research workflows.
 *
 * @package AI_Post_Scheduler
 * @since 2.9.1
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Builder_Research
 *
 * Builds AI prompts for generic trending-topic research and source-grounded
 * research while keeping AI execution and response parsing in the service.
 */
class AIPS_Prompt_Builder_Research {

	/**
	 * Build the AI prompt for trending topics research.
	 *
	 * @param string $niche    The niche to research.
	 * @param int    $count    Number of topics to generate.
	 * @param array  $keywords Additional keywords for context.
	 * @return string The formatted prompt.
	 */
	public function build_trending_topics_prompt($niche, $count, $keywords = array()) {
		$now          = AIPS_DateTime::now();
		$current_date = $now->toDisplay('F j, Y');
		$current_year = $now->toDisplay('Y');

		$prompt = "You are a content research expert analyzing trending topics for '{$niche}' as of {$current_date}.\n\n";

		$prompt .= "Your task: Identify the top {$count} most trending, relevant, and engaging topics in this niche right now.\n\n";

		if (!empty($keywords)) {
			$keyword_list = implode(', ', (array) $keywords);
			$prompt .= "Focus areas: {$keyword_list}\n\n";
		}

		$prompt .= "Consider:\n";
		$prompt .= "1. Current events and news in {$current_year}\n";
		$prompt .= "2. Seasonal relevance for " . $now->toDisplay('F') . "\n";
		$prompt .= "3. Search trends and user interest\n";
		$prompt .= "4. Evergreen value combined with timeliness\n";
		$prompt .= "5. Content gap opportunities\n\n";

		$prompt .= "Return ONLY a valid JSON array of objects. Each object must have:\n";
		$prompt .= "- \"topic\": The topic/title (string)\n";
		$prompt .= "- \"score\": Relevance score 1-100 (integer)\n";
		$prompt .= "- \"reason\": Why it's trending (max 100 chars, string)\n";
		$prompt .= "- \"keywords\": Related keywords (array of 3-5 strings)\n\n";

		$prompt .= "Example format:\n";
		$prompt .= "[\n";
		$prompt .= "  {\n";
		$prompt .= "    \"topic\": \"How AI is Transforming Content Creation in 2025\",\n";
		$prompt .= "    \"score\": 95,\n";
		$prompt .= "    \"reason\": \"High search volume, current AI adoption surge\",\n";
		$prompt .= "    \"keywords\": [\"AI content\", \"automation\", \"GPT-4\", \"content marketing\", \"2025 trends\"]\n";
		$prompt .= "  }\n";
		$prompt .= "]\n\n";

		$prompt .= "Return ONLY the JSON array. No markdown, no explanations, no code blocks.";

		return $prompt;
	}

	/**
	 * Build the AI prompt for source-grounded research.
	 *
	 * @param string $niche          The niche context.
	 * @param int    $count          Number of topics to produce.
	 * @param array  $keywords       Optional focus keywords.
	 * @param string $source_context Scraped source content block.
	 * @return string Formatted prompt.
	 */
	public function build_source_research_prompt($niche, $count, $keywords, $source_context) {
		$current_date = AIPS_DateTime::now()->toDisplay('F j, Y');

		$prompt  = "You are a content research expert. Using the source material below as your primary reference, ";
		$prompt .= "identify {$count} specific, high-value blog post topics for the '{$niche}' niche as of {$current_date}.\n\n";

		if (!empty($keywords)) {
			$keyword_list = implode(', ', (array) $keywords);
			$prompt .= "Additional focus keywords: {$keyword_list}\n\n";
		}

		$prompt .= "SOURCE MATERIAL:\n";
		$prompt .= $source_context . "\n";

		$prompt .= "Instructions:\n";
		$prompt .= "- Ground your topic suggestions in the specific facts, trends, and insights from the sources above.\n";
		$prompt .= "- Prefer specific, actionable topics over generic ones.\n";
		$prompt .= "- Consider gaps or follow-up angles suggested by the source content.\n\n";

		$prompt .= "Return ONLY a valid JSON array of objects. Each object must have:\n";
		$prompt .= "- \"topic\": The topic/title (string)\n";
		$prompt .= "- \"score\": Relevance score 1-100 (integer)\n";
		$prompt .= "- \"reason\": Why it's relevant to the source material (max 100 chars, string)\n";
		$prompt .= "- \"keywords\": Related keywords (array of 3-5 strings)\n\n";
		$prompt .= "Return ONLY the JSON array. No markdown, no explanations, no code blocks.";

		return $prompt;
	}
}
