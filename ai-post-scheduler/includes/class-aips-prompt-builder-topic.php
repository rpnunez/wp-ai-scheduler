<?php
/**
 * Topic Prompt Builder
 *
 * Responsible for assembling AI prompts that are used exclusively for
 * author-driven topic generation. Extracted from AIPS_Author_Topics_Generator
 * to keep prompt construction in the prompt-builder layer.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.2
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Builder_Topic
 *
 * Builds the AI prompt for author topic generation. It incorporates:
 * - Site-wide content context (via AIPS_Prompt_Builder::build_site_context_block)
 * - Author profile (niche, keywords, voice, extended fields)
 * - Historical feedback (approved/rejected topic titles)
 * - Qualitative feedback guidance (admin-supplied rejection/approval reasons)
 */
class AIPS_Prompt_Builder_Topic extends AIPS_Prompt_Builder_Base {

	/**
	 * Build the complete topic generation prompt for the given author.
	 *
	 * @param object   $primary_input Author database record.
	 * @param string[] ...$args Optional approved topics, rejected topics, and feedback guidance.
	 * @return string
	 */
	public function build($primary_input, ...$args) {
		$author = $primary_input;
		$approved_topics = isset($args[0]) && is_array($args[0]) ? $args[0] : array();
		$rejected_topics = isset($args[1]) && is_array($args[1]) ? $args[1] : array();
		$feedback_guidance = isset($args[2]) ? $args[2] : '';
		$quantity = (int) $author->topic_generation_quantity;
		if ($quantity < 1) {
			$quantity = 5;
		}

		$prompt = "Generate {$quantity} unique and engaging blog post topic ideas about: {$author->field_niche}\n\n";

		// ---- Site-wide context (injected first so author-level settings override if needed) ----
		$prompt .= $this->get_base_builder()->build_site_context_block();

		// ---- Trusted sources (injected when the author opts in) ----
		if (!empty($author->include_sources)) {
			$group_ids = array();
			if (!empty($author->source_group_ids)) {
				$decoded   = json_decode($author->source_group_ids, true);
				$group_ids = is_array($decoded) ? array_map('intval', $decoded) : array();
			}
			$sources_block = $this->get_base_builder()->build_sources_block($group_ids);
			if (!empty($sources_block)) {
				$prompt .= $sources_block;
			}
		}

		// ---- Extended author profile fields ----
		if (!empty($author->target_audience)) {
			$prompt .= "Target audience for this author: {$author->target_audience}\n\n";
		}

		if (!empty($author->expertise_level)) {
			$prompt .= "Author expertise level: {$author->expertise_level}\n\n";
		}

		if (!empty($author->content_goals)) {
			$prompt .= "Content goals for this author: {$author->content_goals}\n\n";
		}

		if (!empty($author->keywords)) {
			$prompt .= "Keywords/Focus Areas: {$author->keywords}\n\n";
		}

		if (!empty($author->details)) {
			$prompt .= "Additional Context:\n{$author->details}\n\n";
		}

		if (!empty($author->voice_tone)) {
			$prompt .= "Tone: {$author->voice_tone}\n\n";
		}

		if (!empty($author->writing_style)) {
			$prompt .= "Writing Style: {$author->writing_style}\n\n";
		}

		// Preferred content length
		if (!empty($author->preferred_content_length)) {
			$length_map = array(
				'short'  => 'under 800 words',
				'medium' => '800–1,500 words',
				'long'   => '1,500 words or more',
			);
			$length_label = isset($length_map[$author->preferred_content_length])
				? $length_map[$author->preferred_content_length]
				: $author->preferred_content_length;
			$prompt .= "Preferred post length: {$length_label}\n\n";
		}

		// Language (only explicit when non-English)
		$lang = !empty($author->language) ? $author->language : 'en';
		if ($lang !== 'en') {
			$prompt .= "Generate topics in language code: {$lang}\n\n";
		}

		// ---- Excluded topics (merge site-wide + author-level) ----
		$excluded_parts = array();
		$site_excluded  = AIPS_Site_Context::get_setting('excluded_topics', '');
		if (!empty($site_excluded)) {
			$excluded_parts[] = $site_excluded;
		}
		if (!empty($author->excluded_topics)) {
			$excluded_parts[] = $author->excluded_topics;
		}
		if (!empty($excluded_parts)) {
			$prompt .= 'Topics to avoid: ' . implode(', ', $excluded_parts) . "\n\n";
		}

		// ---- Custom per-author generation prompt ----
		if (!empty($author->topic_generation_prompt)) {
			$prompt .= "{$author->topic_generation_prompt}\n\n";
		}

		// ---- Historical feedback ----
		if (!empty($approved_topics)) {
			$prompt .= "Previously approved topics (for diversity — avoid duplicating these concepts):\n";
			foreach ($approved_topics as $topic) {
				$prompt .= "- {$topic}\n";
			}
			$prompt .= "\n";
		}

		if (!empty($rejected_topics)) {
			$prompt .= "Previously rejected topics (avoid similar ideas):\n";
			foreach ($rejected_topics as $topic) {
				$prompt .= "- {$topic}\n";
			}
			$prompt .= "\n";
		}

		// ---- Qualitative feedback guidance ----
		if (!empty($feedback_guidance)) {
			$prompt .= $feedback_guidance;
		}

		$prompt .= "Requirements:\n";
		$prompt .= "- Each topic should be specific and actionable\n";
		$prompt .= "- Topics should be diverse and cover different aspects of {$author->field_niche}\n";
		$prompt .= "- Avoid duplicating previously approved or rejected topics\n";
		$prompt .= "- Format each topic as a clear, engaging blog post title\n\n";

		$prompt .= "Return a JSON array of objects. Each object must have:\n";
		$prompt .= "- \"title\": The blog post topic/title (string)\n";
		$prompt .= "- \"score\": Estimated engagement score 1-100 (integer)\n";
		$prompt .= "- \"keywords\": 3-5 relevant keywords (array of strings)\n\n";

		$prompt .= "Example format:\n";
		$prompt .= "[\n";
		$prompt .= "  {\n";
		$prompt .= "    \"title\": \"10 Best Practices for WordPress SEO in 2025\",\n";
		$prompt .= "    \"score\": 85,\n";
		$prompt .= "    \"keywords\": [\"WordPress\", \"SEO\", \"best practices\", \"2025\", \"optimization\"]\n";
		$prompt .= "  }\n";
		$prompt .= "]";

		return $prompt;
	}
}
