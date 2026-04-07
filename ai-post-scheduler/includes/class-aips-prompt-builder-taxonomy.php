<?php
/**
 * Taxonomy Prompt Builder
 *
 * Responsible for assembling AI prompts that are used exclusively for
 * taxonomy term suggestion generation. Extracted from
 * AIPS_Taxonomy_Controller to keep prompt construction in the
 * prompt-builder layer.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.3
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Builder_Taxonomy
 *
 * Builds AI prompts for taxonomy suggestion generation.
 */
class AIPS_Prompt_Builder_Taxonomy {

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
	 * Build the AI prompt for taxonomy generation.
	 *
	 * @param string $taxonomy_type Either category or post_tag.
	 * @param array  $post_contents Post title/excerpt summaries.
	 * @param string $generation_prompt Optional generation prompt.
	 * @return string
	 */
	public function build($taxonomy_type, array $post_contents, $generation_prompt = '') {
		$taxonomy_type = $taxonomy_type === 'category' ? 'category' : 'post_tag';
		$type_label    = $taxonomy_type === 'category' ? 'categories' : 'tags';

		$prompt  = "Based on the following posts, generate appropriate {$type_label} for a WordPress site.\n\n";
		$prompt .= "Posts:\n";

		foreach ($post_contents as $content) {
			$title   = isset($content['title']) ? sanitize_text_field($content['title']) : '';
			$excerpt = isset($content['excerpt']) ? sanitize_textarea_field($content['excerpt']) : '';

			if ($title === '') {
				continue;
			}

			$prompt .= "- Title: {$title}\n";
			if ($excerpt !== '') {
				$prompt .= "  Excerpt: {$excerpt}\n";
			}
		}

		$generation_prompt = sanitize_textarea_field($generation_prompt);
		if ($generation_prompt !== '') {
			$prompt .= "\n\nAdditional instructions: {$generation_prompt}\n";
		}

		$prompt .= "\n\nGenerate 5-10 relevant {$type_label}. Return only the {$type_label} names, one per line, without numbering or bullet points.";

		return $prompt;
	}
}