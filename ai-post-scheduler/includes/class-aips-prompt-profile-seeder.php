<?php
/**
 * Prompt Profile Seeder
 *
 * Provides initial seed data and archetypes for the Prompt Profiles system.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Profile_Seeder
 *
 * Manages default Prompt Profile archetype definitions and initial database seeding.
 */
class AIPS_Prompt_Profile_Seeder {

	/**
	 * Get list of builtin system archetype slugs.
	 *
	 * @return string[]
	 */
	public static function get_builtin_slugs() {
		return array(
			'standard-default',
			'seo-maximizer',
			'conversational-storyteller',
			'technical-authority',
		);
	}

	/**
	 * Get definitions of all default prompt profile archetypes.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_default_profiles() {
		return array(
			array(
				'name'                    => 'Standard Default',
				'slug'                    => 'standard-default',
				'description'             => 'The balanced standard prompt architecture with direct parity to core AI Post Scheduler defaults.',
				'is_default'              => 1,
				'title_prompt'            => "Generate a title for a blog post, based on the content below. Respond with ONLY the most relevant title, nothing else.{{instructions_block}}",
				'title_followup_prompt'   => "Now generate a title for the article you just wrote.{{instructions_block}}",
				'content_prompt'          => "{{voice_instructions}}\n\n{{content_prompt}}",
				'excerpt_prompt'          => "Write an excerpt for an article. Must be between {{word_count_min}} and {{word_count_max}} words. Write naturally as a human would. Output only the excerpt, no formatting.{{instructions_block}}",
				'excerpt_followup_prompt' => "Now write an excerpt for that article. Must be between {{word_count_min}} and {{word_count_max}} words. Write naturally as a human would. Output only the excerpt, no formatting.{{instructions_block}}",
				'featured_image_prompt'   => "{{image_prompt}}",
				'topic_ideas_prompt'      => "Generate {{quantity}} unique and engaging blog post topic ideas about: {{niche}}",
				'metadata_prompt'         => "Generate search-optimized metadata for the article.",
				'taxonomy_prompt'         => "Select the most relevant category and tags for the article.",
				'is_active'               => 1,
			),
			array(
				'name'                    => 'SEO & Search Intent Maximizer',
				'slug'                    => 'seo-maximizer',
				'description'             => 'Optimized for organic search ranking, keyword relevance, high CTR headlines, and direct search intent fulfillment.',
				'is_default'              => 0,
				'title_prompt'            => "Generate a high-CTR, SEO-optimized title for a blog post targeting primary search intent based on the content below. Keep it under 60 characters with strong keyword placement. Respond with ONLY the single title, nothing else.{{instructions_block}}",
				'title_followup_prompt'   => "Now generate a high-CTR, search-optimized title under 60 characters for the article you just wrote. Respond with ONLY the title.{{instructions_block}}",
				'content_prompt'          => "Write a comprehensive, authoritative, search-optimized article that directly answers search intent. Use structured H2 and H3 subheadings, bulleted lists, and actionable takeaways.\n\n{{voice_instructions}}\n\n{{content_prompt}}",
				'excerpt_prompt'          => "Write an enticing, SEO-friendly meta description and excerpt for an article. Must be between {{word_count_min}} and {{word_count_max}} words. Include a clear value hook and call-to-action. Output only the excerpt plain text without quotes.{{instructions_block}}",
				'excerpt_followup_prompt' => "Now write an enticing, SEO meta excerpt between {{word_count_min}} and {{word_count_max}} words for that article. Output only plain text.{{instructions_block}}",
				'featured_image_prompt'   => "A clean, modern, professional editorial header illustration representing: {{topic}}. High resolution, vibrant, minimalist style.",
				'topic_ideas_prompt'      => "Generate {{quantity}} high-search-volume, low-competition blog post topic ideas with clear search intent about: {{niche}}",
				'metadata_prompt'         => "Generate comprehensive SEO metadata including search intent, focus keyphrase, and schema hints.",
				'taxonomy_prompt'         => "Assign the most specific taxonomy classification that matches primary search intent.",
				'is_active'               => 1,
			),
			array(
				'name'                    => 'Engaging & Conversational Storyteller',
				'slug'                    => 'conversational-storyteller',
				'description'             => 'Narrative-driven, emotionally engaging style with storytelling hooks, digestible pacing, and personal human warmth.',
				'is_default'              => 0,
				'title_prompt'            => "Generate a captivating, curiosity-inducing, conversational title for a story-driven article based on the content below. Make it intriguing and memorable. Respond with ONLY one plain-text title.{{instructions_block}}",
				'title_followup_prompt'   => "Now generate a captivating, conversational headline that sparks curiosity for the article you just wrote. Output ONLY the title.{{instructions_block}}",
				'content_prompt'          => "Write in an engaging, personal, and conversational storytelling voice. Open with a relatable hook, use vivid analogies, keep paragraphs digestible, and maintain an authentic human tone.\n\n{{voice_instructions}}\n\n{{content_prompt}}",
				'excerpt_prompt'          => "Write an intriguing teaser excerpt for this story. Must be between {{word_count_min}} and {{word_count_max}} words. Make the reader eager to read the full piece. Output only the excerpt text.{{instructions_block}}",
				'excerpt_followup_prompt' => "Now write an intriguing teaser excerpt between {{word_count_min}} and {{word_count_max}} words for that story. Output only plain text.{{instructions_block}}",
				'featured_image_prompt'   => "A cinematic, evocative, storytelling visual composition depicting: {{topic}}. Warm lighting, expressive atmosphere.",
				'topic_ideas_prompt'      => "Brainstorm {{quantity}} deeply engaging, story-worthy topic angles and fresh perspectives about: {{niche}}",
				'metadata_prompt'         => "Generate metadata emphasizing reader engagement and social sharing appeal.",
				'taxonomy_prompt'         => "Categorize this story by overarching narrative theme.",
				'is_active'               => 1,
			),
			array(
				'name'                    => 'Technical & In-Depth Authority',
				'slug'                    => 'technical-authority',
				'description'             => 'Rigorous, analytical, step-by-step technical depth for developers, engineers, and domain specialists.',
				'is_default'              => 0,
				'title_prompt'            => "Generate a precise, technical, problem-solving title for an in-depth guide based on the content below. Highlight technical solutions and actionable implementation. Respond with ONLY the title.{{instructions_block}}",
				'title_followup_prompt'   => "Now generate a precise, technical title for the guide you just wrote. Output ONLY the title.{{instructions_block}}",
				'content_prompt'          => "Provide rigorous, technically accurate, and comprehensive documentation/tutorial content. Include code examples, architecture rationale, edge cases, and best practices.\n\n{{voice_instructions}}\n\n{{content_prompt}}",
				'excerpt_prompt'          => "Write a concise technical summary of this guide. Must be between {{word_count_min}} and {{word_count_max}} words. Highlight the primary technical problem solved and implementation scope. Output only plain text.{{instructions_block}}",
				'excerpt_followup_prompt' => "Now write a concise technical summary between {{word_count_min}} and {{word_count_max}} words for that guide. Output only plain text.{{instructions_block}}",
				'featured_image_prompt'   => "A sleek technical architectural diagram or abstract digital schematic visual for: {{topic}}. Clean vectors, dark blueprint aesthetic.",
				'topic_ideas_prompt'      => "Generate {{quantity}} advanced, problem-solving technical deep-dive topics about: {{niche}}",
				'metadata_prompt'         => "Generate structured technical documentation metadata and code taxonomy.",
				'taxonomy_prompt'         => "Classify by engineering domain and complexity tier.",
				'is_active'               => 1,
			),
		);
	}

	/**
	 * Seed default prompt profiles into the database if the table is empty.
	 *
	 * @return int Number of seeded profiles.
	 */
	public static function seed_defaults() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_prompt_profiles';

		$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
		if ($table_exists !== $table) {
			return 0;
		}

		$count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
		if ($count > 0) {
			return 0;
		}

		$now = AIPS_DateTime::now()->timestamp();
		$inserted = 0;

		foreach (self::get_default_profiles() as $profile) {
			$profile['created_at'] = $now;
			$profile['updated_at'] = $now;
			$res = $wpdb->insert($table, $profile);
			if ($res !== false) {
				$inserted++;
			}
		}

		return $inserted;
	}
}
