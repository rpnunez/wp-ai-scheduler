<?php
/**
 * Content Audit Prompt Builder
 *
 * @package AI_Post_Scheduler
 * @since 2.9.1
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Prompt_Builder_Content_Audit {

	public function build_gap_analysis_prompt($niche, array $existing_content) {
		$content_list = '';
		if (!empty($existing_content)) {
			foreach ($existing_content as $item) {
				$content_list .= "- {$item['title']} (Category: {$item['categories']})\n";
			}
		} else {
			$content_list = '(No existing content found)';
		}

		$prompt  = "You are an SEO Content Strategist. The website's core niche is: {$niche}.\n\n";
		$prompt .= 'Here is a list of the last ' . count($existing_content) . " published articles on the site:\n";
		$prompt .= $content_list . "\n\n";
		$prompt .= "Task: Analyze the existing content coverage against the target niche. Identify 5-7 major sub-topics, 'pillar' pages, or content clusters that are MISSING or under-represented.\n\n";
		$prompt .= "Return a JSON array of objects. Each object must have:\n";
		$prompt .= "- \"missing_topic\": The title of the missing topic or cluster (string)\n";
		$prompt .= "- \"priority\": \"High\" or \"Medium\" (string)\n";
		$prompt .= "- \"reason\": A brief explanation of why this is a gap and why it's needed (string)\n";
		$prompt .= "- \"search_intent\": The primary user intent (e.g., Informational, Transactional) (string)\n\n";
		$prompt .= "Example format:\n";
		$prompt .= "[\n";
		$prompt .= "  {\n";
		$prompt .= "    \"missing_topic\": \"Advanced Composting Techniques\",\n";
		$prompt .= "    \"priority\": \"High\",\n";
		$prompt .= "    \"reason\": \"You have basic gardening tips but lack technical soil health content which establishes authority.\",\n";
		$prompt .= "    \"search_intent\": \"Informational\"\n";
		$prompt .= "  }\n";
		$prompt .= "]";

		return $prompt;
	}
}
