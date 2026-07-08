<?php
/**
 * Seeder Prompt Builder
 *
 * @package AI_Post_Scheduler
 * @since 2.9.1
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Prompt_Builder_Seeder {

	public function build_voices_prompt($count, $keywords = '') {
		$prompt = "Generate a list of {$count} unique personas for blog writing. \n";
		if (!empty($keywords)) {
			$prompt .= "Use the following keywords to inspire the personas: {$keywords}. \n";
		}
		$prompt .= "Each persona must have a 'name', 'content_instructions' (writing style description), and 'title_prompt' (instructions for writing titles). \n";
		$prompt .= "Return ONLY a valid JSON array of objects. Example: [{\"name\": \"Tech Guru\", \"content_instructions\": \"...\", \"title_prompt\": \"...\"}]";

		return $prompt;
	}

	public function build_templates_prompt($count, $keywords = '') {
		$prompt = "Generate a list of {$count} blog post templates. \n";
		if (!empty($keywords)) {
			$prompt .= "The templates should be relevant to these keywords/niche: {$keywords}. \n";
		}
		$prompt .= "Each template needs a 'name', 'prompt_template' (e.g., 'Write a blog post about {{topic}}...'), and 'image_prompt'. \n";
		$prompt .= "Return ONLY a valid JSON array of objects. Example: [{\"name\": \"How-to Guide\", \"prompt_template\": \"...\", \"image_prompt\": \"...\"}]";

		return $prompt;
	}

	public function build_planner_topics_prompt($count, $keywords = '') {
		$prompt = "Generate a list of {$count} interesting blog post topics/titles. \n";
		if (!empty($keywords)) {
			$prompt .= "The topics MUST be related to these keywords: {$keywords}. \n";
		} else {
			$prompt .= "Topics should be about Technology, Lifestyle, or Business. \n";
		}
		$prompt .= "Return ONLY a valid JSON array of strings. Example: [\"Topic 1\", \"Topic 2\"]";

		return $prompt;
	}
}
