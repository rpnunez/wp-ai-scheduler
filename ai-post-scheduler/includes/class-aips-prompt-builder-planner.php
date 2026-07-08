<?php
/**
 * Planner Prompt Builder
 *
 * @package AI_Post_Scheduler
 * @since 2.9.1
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Prompt_Builder_Planner {

	public function build_topics_prompt($niche, $count) {
		$prompt  = "Generate a list of {$count} unique, engaging blog post titles/topics about '{$niche}'. \n";
		$prompt .= "Return ONLY a valid JSON array of strings. Do not include any other text, markdown formatting, or numbering. \n";
		$prompt .= "Example: [\"Topic 1\", \"Topic 2\", \"Topic 3\"]";

		return $prompt;
	}
}
