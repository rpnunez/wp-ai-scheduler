<?php
/**
 * SEO Prompt Builder
 *
 * Assembles the AI prompt used to generate structured canonical SEO metadata
 * (focus keywords, SEO title, meta description, and OpenGraph/Twitter social tags)
 * for a post. Supports both fresh template generation and existing post optimization,
 * with full support for selecting specific subsets of fields via SEO Profiles.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Prompt_Builder_SEO {

	/**
	 * Canonical field definitions and default guidelines.
	 *
	 * @var array<string, array{label: string, instruction: string, example: string}>
	 */
	private static $field_definitions = array(
		'focus_keyword' => array(
			'label'       => 'Primary Focus Keyword',
			'instruction' => 'Single high-intent primary keyword/keyphrase (1-4 words) that accurately represents the article.',
			'example'     => '"focus_keyword": "primary target keyword"',
		),
		'secondary_keywords' => array(
			'label'       => 'Secondary Keywords',
			'instruction' => 'Array of 2-4 related LSI search terms and keyword variations.',
			'example'     => '"secondary_keywords": ["secondary keyword 1", "secondary keyword 2", "secondary keyword 3"]',
		),
		'seo_title' => array(
			'label'       => 'SEO Meta Title',
			'instruction' => 'High-converting meta title between 50 and 60 characters. Place the primary keyword near the front.',
			'example'     => '"seo_title": "Optimized SEO Meta Title (50-60 chars)"',
		),
		'meta_description' => array(
			'label'       => 'SEO Meta Description',
			'instruction' => 'Engaging summary snippet between 140 and 160 characters with a clear call-to-action and primary keyword. Plain text only.',
			'example'     => '"meta_description": "Compelling search snippet (140-160 chars) with keywords and CTA."',
		),
		'og_title' => array(
			'label'       => 'OpenGraph (Facebook) Title',
			'instruction' => 'Social media OpenGraph title optimized for clicks and shares (50-65 chars).',
			'example'     => '"og_title": "Social OpenGraph Title"',
		),
		'og_description' => array(
			'label'       => 'OpenGraph (Facebook) Description',
			'instruction' => 'Social media OpenGraph description snippet (120-160 chars).',
			'example'     => '"og_description": "Social OpenGraph Description"',
		),
		'twitter_title' => array(
			'label'       => 'Twitter / X Card Title',
			'instruction' => 'Twitter card title (50-65 chars).',
			'example'     => '"twitter_title": "Twitter Card Title"',
		),
		'twitter_description' => array(
			'label'       => 'Twitter / X Card Description',
			'instruction' => 'Twitter card snippet (120-160 chars).',
			'example'     => '"twitter_description": "Twitter Card Description"',
		),
		'canonical_url' => array(
			'label'       => 'Canonical URL',
			'instruction' => 'Custom canonical URL if required, or empty string for default permalink.',
			'example'     => '"canonical_url": ""',
		),
		'robots_index' => array(
			'label'       => 'Robots Index Directive',
			'instruction' => '"index" or "noindex". Defaults to "index".',
			'example'     => '"robots_index": "index"',
		),
		'robots_follow' => array(
			'label'       => 'Robots Follow Directive',
			'instruction' => '"follow" or "nofollow". Defaults to "follow".',
			'example'     => '"robots_follow": "follow"',
		),
	);

	/**
	 * @var AIPS_Template_Processor
	 */
	private $template_processor;

	/**
	 * @param AIPS_Template_Processor|null $template_processor Optional template processor.
	 */
	public function __construct($template_processor = null) {
		$this->template_processor = $template_processor ?: new AIPS_Template_Processor();
	}

	/**
	 * Build the SEO prompt for a generation context (new post flow).
	 *
	 * @param AIPS_Generation_Context $context             Generation context.
	 * @param string                  $content             Generated post body content.
	 * @param string                  $title               Generated post title.
	 * @param string                  $custom_instructions Optional custom SEO prompt instructions.
	 * @param array                   $selected_fields     Optional list of specific field keys to generate.
	 * @param array                   $field_prompts       Optional map of field_key => custom prompt.
	 * @return string
	 */
	public function build($context, $content = '', $title = '', $custom_instructions = '', $selected_fields = array(), $field_prompts = array()) {
		do_action('aips_before_build_seo_prompt', $context);

		$topic = ($context instanceof AIPS_Generation_Context) ? $context->get_topic() : null;
		$site_config = AIPS_Config::get_instance()->get_site_content_config();

		$fields = !empty($selected_fields) && is_array($selected_fields)
			? array_values(array_intersect($selected_fields, array_keys(self::$field_definitions)))
			: array('focus_keyword', 'secondary_keywords', 'seo_title', 'meta_description', 'og_title', 'og_description', 'twitter_title', 'twitter_description');

		$sections = array();
		$sections[] = __('You are an expert Search Engine Optimization (SEO) specialist. Based on the article provided below, generate high-performing, search-optimized metadata. Respond with a single valid JSON object containing ONLY the requested fields and nothing else.', 'ai-post-scheduler');

		if (!empty($title)) {
			$sections[] = sprintf("POST TITLE:\n%s", $title);
		}

		if (!empty($site_config['niche'])) {
			$sections[] = sprintf("SITE NICHE / TOPIC:\n%s", $site_config['niche']);
		}

		if (!empty($site_config['target_audience'])) {
			$sections[] = sprintf("TARGET AUDIENCE:\n%s", $site_config['target_audience']);
		}

		if (!empty($site_config['brand_voice'])) {
			$sections[] = sprintf("BRAND VOICE:\n%s", $site_config['brand_voice']);
		}

		if (!empty($custom_instructions)) {
			$processed_custom = $this->template_processor->process($custom_instructions, $topic);
			$sections[] = sprintf("CUSTOM SEO INSTRUCTIONS:\n%s", $processed_custom);
		}

		$sections[] = $this->build_field_guidelines($fields, $field_prompts, $topic);

		if (!empty($content)) {
			$trimmed_content = wp_trim_words($content, 1500, '...');
			$sections[] = sprintf("ARTICLE CONTENT:\n%s", $trimmed_content);
		}

		$sections[] = $this->build_response_shape($fields);

		$prompt = implode("\n\n", $sections);

		return apply_filters('aips_seo_prompt', $prompt, $context, $content, $title, $fields);
	}

	/**
	 * Build the SEO prompt for an existing WordPress post.
	 *
	 * @param WP_Post|int $post                Post object or post ID.
	 * @param string      $custom_instructions Optional custom SEO instructions.
	 * @param array       $selected_fields     Optional list of specific field keys to generate.
	 * @param array       $field_prompts       Optional map of field_key => custom prompt.
	 * @return string|WP_Error Prompt string on success, WP_Error if post not found.
	 */
	public function build_for_post($post, $custom_instructions = '', $selected_fields = array(), $field_prompts = array()) {
		$post_obj = is_numeric($post) ? get_post($post) : $post;

		if (!($post_obj instanceof WP_Post)) {
			return new WP_Error('invalid_post', __('Post not found.', 'ai-post-scheduler'));
		}

		$title = $post_obj->post_title;
		$content = !empty($post_obj->post_content) ? wp_strip_all_tags($post_obj->post_content) : '';
		$excerpt = !empty($post_obj->post_excerpt) ? $post_obj->post_excerpt : '';

		$site_config = AIPS_Config::get_instance()->get_site_content_config();

		$fields = !empty($selected_fields) && is_array($selected_fields)
			? array_values(array_intersect($selected_fields, array_keys(self::$field_definitions)))
			: array('focus_keyword', 'secondary_keywords', 'seo_title', 'meta_description', 'og_title', 'og_description', 'twitter_title', 'twitter_description');

		$sections = array();
		$sections[] = __('You are an expert Search Engine Optimization (SEO) specialist. Based on the existing blog post below, generate comprehensive, search-optimized metadata. Respond with a single valid JSON object containing ONLY the requested fields and nothing else.', 'ai-post-scheduler');

		$sections[] = sprintf("POST TITLE:\n%s", $title);

		if (!empty($excerpt)) {
			$sections[] = sprintf("POST EXCERPT:\n%s", $excerpt);
		}

		if (!empty($site_config['niche'])) {
			$sections[] = sprintf("SITE NICHE / TOPIC:\n%s", $site_config['niche']);
		}

		if (!empty($site_config['target_audience'])) {
			$sections[] = sprintf("TARGET AUDIENCE:\n%s", $site_config['target_audience']);
		}

		if (!empty($custom_instructions)) {
			$sections[] = sprintf("CUSTOM INSTRUCTIONS:\n%s", $custom_instructions);
		}

		$sections[] = $this->build_field_guidelines($fields, $field_prompts);

		if (!empty($content)) {
			$trimmed_content = wp_trim_words($content, 1500, '...');
			$sections[] = sprintf("ARTICLE CONTENT:\n%s", $trimmed_content);
		}

		$sections[] = $this->build_response_shape($fields);

		$prompt = implode("\n\n", $sections);

		return apply_filters('aips_seo_prompt_for_post', $prompt, $post_obj, $fields);
	}

	/**
	 * Return the JSON schema for providers supporting structured outputs.
	 *
	 * @param array $selected_fields Optional subset of fields.
	 * @return array
	 */
	public function get_schema($selected_fields = array()) {
		$fields = !empty($selected_fields) && is_array($selected_fields)
			? array_intersect($selected_fields, array_keys(self::$field_definitions))
			: array('focus_keyword', 'secondary_keywords', 'seo_title', 'meta_description', 'og_title', 'og_description', 'twitter_title', 'twitter_description');

		$properties = array();
		$required = array();

		foreach ($fields as $field_key) {
			if ($field_key === 'secondary_keywords') {
				$properties[$field_key] = array(
					'type'        => 'array',
					'items'       => array('type' => 'string'),
					'description' => self::$field_definitions[$field_key]['instruction'],
				);
			} else {
				$properties[$field_key] = array(
					'type'        => 'string',
					'description' => self::$field_definitions[$field_key]['instruction'],
				);
			}
			$required[] = $field_key;
		}

		return array(
			'type'       => 'object',
			'properties' => $properties,
			'required'   => $required,
		);
	}

	/**
	 * Build the SEO field rules and guidelines section for selected fields.
	 *
	 * @param array       $fields        Selected field keys.
	 * @param array       $field_prompts Custom prompt overrides for specific fields.
	 * @param string|null $topic         Topic for template variable processing.
	 * @return string
	 */
	private function build_field_guidelines($fields, $field_prompts = array(), $topic = null) {
		$lines = array("REQUESTED SEO FIELDS & GUIDELINES:");
		$num = 1;

		foreach ($fields as $field_key) {
			if (!isset(self::$field_definitions[$field_key])) {
				continue;
			}

			$def = self::$field_definitions[$field_key];
			$instruction = $def['instruction'];

			if (!empty($field_prompts[$field_key])) {
				$custom = $this->template_processor->process($field_prompts[$field_key], $topic);
				$instruction .= ' Custom rule: ' . $custom;
			}

			$lines[] = sprintf('%d. %s: %s', $num++, $field_key, $instruction);
		}

		return implode("\n", $lines);
	}

	/**
	 * Build the JSON format specification section for selected fields.
	 *
	 * @param array $fields Selected field keys.
	 * @return string
	 */
	private function build_response_shape($fields) {
		$example_pairs = array();

		foreach ($fields as $field_key) {
			if (isset(self::$field_definitions[$field_key])) {
				$example_pairs[] = '  ' . self::$field_definitions[$field_key]['example'];
			}
		}

		return "RESPOND WITH EXACTLY THIS JSON FORMAT:\n{\n" . implode(",\n", $example_pairs) . "\n}";
	}
}
