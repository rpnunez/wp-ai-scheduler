<?php
/**
 * Post Suggestion Prompts Builder.
 *
 * Constructs AI prompts for analyzing existing posts and generating improvement
 * suggestions. Formats post data, categories, and analysis instructions into
 * structured JSON prompts optimized for AI content analysis.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Builder_Post_Improvement
 *
 * Builds comprehensive prompts for AI-powered post improvement analysis.
 */
class AIPS_Prompt_Builder_Post_Improvement {

	/**
	 * Build a comprehensive post analysis prompt for AI.
	 *
	 * Constructs a JSON-formatted prompt with post metadata, content, and analysis
	 * instructions. The AI is asked to analyze the post and suggest improvements
	 * for title, excerpt, content, categories, factuality, and citation sources.
	 *
	 * @param WP_Post $post       Post object to analyze.
	 * @param array   $categories Array of category objects (WP_Term instances) associated with the post.
	 *
	 * @return string JSON-formatted prompt for AI analysis.
	 * @since 2.10.0
	 */
	public function build_post_scan_prompt($post, $categories = array()) {
		$category_names = array();

		foreach ((array) $categories as $category) {
			if ($category instanceof WP_Term) {
				$category_names[] = $category->name;
			}
		}

		// Build structured prompt payload
		$payload = array(
			'post'          => array(
				'id'         => (int) $post->ID,
				'title'      => (string) $post->post_title,
				'excerpt'    => (string) $post->post_excerpt,
				'content'    => wp_strip_all_tags((string) $post->post_content),
				'categories' => $category_names,
			),
			'instructions'  => array(
				'Analyze readability and typos',
				'Suggest title rewrite and excerpt rewrite if meaningful',
				'Suggest content expansion or shortening improvements',
				'Suggest category updates when category fit is poor',
				'Flag factual staleness concerns and suggest citation sources',
			),
			'output_schema' => array(
				'suggestions' => array(
					array(
						'component'       => 'title|excerpt|content|categories|factuality|sources',
						'item_type'       => 'rewrite|expand|shorten|fix_typo|recommendation|freshness_check|citation_suggestion',
						'suggested_value' => 'string|array',
						'rationale'       => 'string',
						'confidence'      => 0.0,
						'priority'        => 'low|medium|high',
						'severity'        => 'low|medium|high',
					),
				),
			),
		);

		return "You are an expert content editor for existing blog posts.\nRespond with strict JSON only.\n" . wp_json_encode($payload);
	}
}
