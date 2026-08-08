<?php
/**
 * Campaign Prompt Builder
 *
 * @package AI_Post_Scheduler
 * @since 2.9.1
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Prompt_Builder_Campaign {

	public function build_guided_setup_prompt(array $context) {
		return
			"You are helping a WordPress user configure an AI content campaign.\n" .
			"Return only a single JSON object with the exact keys below.\n" .
			"Do not wrap the JSON in markdown and do not include any extra keys.\n\n" .
			"Required keys:\n" .
			"- campaign_name (string)\n" .
			"- content_goal (string)\n" .
			"- post_type (string)\n" .
			"- prompt_template (string; use {{topic}} - lowercase, double curly braces - as the sole placeholder for the article subject; never use [TOPIC] or any other format)\n" .
			"- title_prompt (string; must instruct the AI to generate exactly 1 title (never more); do NOT use {{topic}} here because {{topic}} maps to the final title in this system; this prompt must be self-contained and not rely on template variables; example: 'Generate exactly one concise, SEO-friendly article title aligned with the campaign goal and audience.')\n" .
			"- author_persona (string)\n" .
			"- campaign_mode (string: template|author)\n" .
			"- review_policy (string: draft|approval|auto_publish)\n" .
			"- frequency (string)\n" .
			"- time_window_start (string HH:MM or empty)\n" .
			"- time_window_end (string HH:MM or empty)\n" .
			"- post_tags (string)\n" .
			"- post_category (number, use 0 when unknown)\n" .
			"- template_style (string from available_output_styles)\n" .
			"- sample_article_ideas (array of 3 to 5 strings)\n" .
			"- risks_assumptions (array of 2 to 4 strings)\n\n" .
			"Use realistic, user-friendly values suitable for immediate editing and publishing.\n\n" .
			"Context:\n" . wp_json_encode($context);
	}
}
