<?php
/**
 * Post Metadata Prompt Builder
 *
 * Builds the single structured follow-up turn that asks for every remaining post
 * component at once — AI variables, title, excerpt, and featured image prompt —
 * on a conversation that already contains the generated article.
 *
 * This collapses up to four separate requests into one. Because the article is
 * already in the conversation as a model turn, none of those components need the
 * body pasted into their prompt.
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Prompt_Builder_Post_Metadata {

	/**
	 * @var AIPS_Template_Processor Template processor for prompt variables.
	 */
	private $template_processor;

	/**
	 * @var AIPS_Prompt_Builder_Diversity_Injector Diversity block builder.
	 */
	private $diversity_injector;

	/**
	 * @param AIPS_Template_Processor|null                $template_processor Optional template processor.
	 * @param AIPS_Prompt_Builder_Diversity_Injector|null $diversity_injector Optional diversity injector.
	 */
	public function __construct($template_processor = null, $diversity_injector = null) {
		$this->template_processor = $template_processor ?: new AIPS_Template_Processor();
		$this->diversity_injector = $diversity_injector ?: new AIPS_Prompt_Builder_Diversity_Injector();
	}

	/**
	 * Build the combined metadata prompt.
	 *
	 * @param AIPS_Generation_Context $context      Generation context.
	 * @param string[]                $ai_variables Unresolved AI variable names found in the
	 *                                              title and image prompts.
	 * @param string                  $image_prompt Raw image prompt template, or '' when the
	 *                                              post has no AI-generated featured image.
	 * @param bool|null               $include_slug Whether to include slug generation (null = auto-detect).
	 * @param array                   $content_image_templates Content image prompt templates keyed by index.
	 * @return string
	 */
	public function build($context, array $ai_variables = array(), $image_prompt = '', $include_slug = null, array $content_image_templates = array()) {
		$topic_str = $context->get_topic();
		$sections  = array();

		$sections[] = 'Based on the article you just wrote, produce its metadata. Respond with a single JSON object and nothing else.';

		$title_instructions = $this->resolve_title_instructions($context, $topic_str);

		if (!empty($title_instructions)) {
			$sections[] = "TITLE INSTRUCTIONS:\n" . $title_instructions;
		}

		$excerpt_instructions = "Between 40 and 60 words. Write naturally as a human would. Plain text, no formatting.";
		if ($context->get_type() === 'template') {
			$voice_obj = $context->get_voice();
			if ($voice_obj && !empty($voice_obj->excerpt_instructions)) {
				$excerpt_instructions .= "\n" . $this->template_processor->process($voice_obj->excerpt_instructions, $topic_str);
			}
		} elseif ($context instanceof AIPS_Topic_Context) {
			$author_obj = $context->get_author();
			if ($author_obj) {
				$author_style = array();
				if (!empty($author_obj->voice_tone)) {
					$author_style[] = 'Tone: ' . $author_obj->voice_tone;
				}
				if (!empty($author_obj->writing_style)) {
					$author_style[] = 'Writing Style: ' . $author_obj->writing_style;
				}
				if (!empty($author_style)) {
					$excerpt_instructions .= "\n" . implode("\n", $author_style);
				}
			}
		}
		$sections[] = "EXCERPT INSTRUCTIONS:\n" . $excerpt_instructions;

		$include_slug = $include_slug !== null ? $include_slug : $context->should_generate_slug();

		if ($include_slug) {
			$slug_builder      = new AIPS_Prompt_Builder_Post_Slug($this->template_processor);
			$slug_instructions = $slug_builder->resolve_slug_instructions($context, $topic_str);
			$slug_section      = "SLUG INSTRUCTIONS:\n" . $slug_builder->get_slug_guidelines();
			if (!empty($slug_instructions)) {
				$slug_section .= "\n\nSpecific instructions:\n" . $slug_instructions;
			}
			$sections[] = $slug_section;
		}

		if (!empty($image_prompt)) {
			$sections[] = "FEATURED IMAGE INSTRUCTIONS:\nUsing the template below, produce a finished image generation prompt describing the article's featured image. Substitute every placeholder with a concrete value drawn from the article.\n\n" . $image_prompt;
		}

		if (!empty($content_image_templates)) {
			// Instruct the model to generate finished visual prompts for each in-article content image,
			// referencing their corresponding placeholder indices so the output maps directly to template configuration.
			$img_instructions = array("CONTENT IMAGES INSTRUCTIONS:\nFor each in-article image listed below, produce a finished image generation prompt based on the article. Substitute any placeholders with concrete details from the article:");
			foreach ($content_image_templates as $idx => $tmpl) {
				$img_instructions[] = sprintf('Image %d: %s', $idx, $tmpl);
			}
			$sections[] = implode("\n", $img_instructions);
		}

		if (!empty($ai_variables)) {
			$sections[] = $this->build_variables_section($ai_variables);
		}

		$prompt = implode("\n\n", $sections);
		$prompt = $this->append_diversity_blocks($prompt, $context);
		$prompt .= "\n\n" . $this->build_response_shape($ai_variables, !empty($image_prompt), $include_slug, $content_image_templates);

		/**
		 * Filters the combined metadata prompt used by conversational generation.
		 *
		 * @since 3.2.0
		 *
		 * @param string                  $prompt                 The assembled prompt.
		 * @param AIPS_Generation_Context $context                Generation context.
		 * @param string[]                $ai_variables           AI variable names to resolve.
		 * @param string                  $image_prompt           Raw image prompt template.
		 * @param array                   $content_image_templates Content image templates.
		 */
		return apply_filters('aips_post_metadata_prompt', $prompt, $context, $ai_variables, $image_prompt, $content_image_templates);
	}

	/**
	 * JSON schema describing the expected response.
	 *
	 * Passed to providers with a native structured-output path; providers without
	 * one fall back to AIPS_AI_Service's text-based JSON extraction, which the
	 * explicit response shape in the prompt keeps parseable.
	 *
	 * @param string[] $ai_variables          AI variable names to resolve.
	 * @param bool     $include_image         Whether an image prompt was requested.
	 * @param bool     $include_slug          Whether a post slug was requested.
	 * @param array    $content_image_templates Content image prompt templates keyed by index.
	 * @return array
	 */
	public function get_schema(array $ai_variables = array(), $include_image = false, $include_slug = false, array $content_image_templates = array()) {
		$properties = array(
			'title'   => array('type' => 'string'),
			'excerpt' => array('type' => 'string'),
		);

		if ($include_slug) {
			$properties['slug'] = array('type' => 'string');
		}

		if ($include_image) {
			$properties['image_prompt'] = array('type' => 'string');
		}

		if (!empty($content_image_templates)) {
			// Structured outputs (OpenAI/Gemini JSON Schema) require object keys to be explicit string properties.
			// Content images are identified by their shortcode placeholder index (e.g. [post_image index="1"]).
			// We define each configured index as a distinct string property and require every one of them to ensure
			// the model generates an image prompt for all requested images without omitting any placeholders.
			// additionalProperties => false guarantees the response contains no unexpected extra keys.
			$img_props = array();
			foreach (array_keys($content_image_templates) as $idx) {
				$img_props[(string) $idx] = array('type' => 'string');
			}
			$properties['content_image_prompts'] = array(
				'type'                 => 'object',
				'properties'           => $img_props,
				'required'             => array_map('strval', array_keys($content_image_templates)),
				'additionalProperties' => false,
			);
		}

		if (!empty($ai_variables)) {
			$variable_properties = array();

			foreach ($ai_variables as $variable) {
				$variable_properties[$variable] = array('type' => 'string');
			}

			$properties['ai_variables'] = array(
				'type'                 => 'object',
				'properties'           => $variable_properties,
				'required'             => array_values($ai_variables),
				'additionalProperties' => false,
			);
		}

		$required = array('title', 'excerpt');
		if ($include_slug) {
			$required[] = 'slug';
		}
		if ($include_image) {
			$required[] = 'image_prompt';
		}
		if (!empty($content_image_templates)) {
			$required[] = 'content_image_prompts';
		}
		if (!empty($ai_variables)) {
			$required[] = 'ai_variables';
		}

		return array(
			'type'                 => 'object',
			'properties'           => $properties,
			'required'             => $required,
			'additionalProperties' => false,
		);
	}

	/**
	 * Resolve title instructions, preferring the voice override.
	 *
	 * Public so callers can extract AI variables from the same string that ends up
	 * in the prompt — a voice override may carry different placeholders than the
	 * template's own title prompt.
	 *
	 * @param AIPS_Generation_Context $context   Generation context.
	 * @param string|null             $topic_str Topic string.
	 * @return string
	 */
	public function resolve_title_instructions($context, $topic_str = null) {
		if ($context->get_type() === 'template' && $context->get_voice_id()) {
			$voice_obj = $context->get_voice();

			if ($voice_obj && !empty($voice_obj->title_prompt)) {
				return $this->template_processor->process($voice_obj->title_prompt, $topic_str);
			}
		}

		$title_prompt = $context->get_title_prompt();

		return !empty($title_prompt) ? $this->template_processor->process($title_prompt, $topic_str) : '';
	}

	/**
	 * Describe the placeholders the model must resolve.
	 *
	 * @param string[] $ai_variables AI variable names.
	 * @return string
	 */
	private function build_variables_section(array $ai_variables) {
		$section = "PLACEHOLDERS:\nThe instructions above contain placeholders written as {{Name}}. Determine an appropriate value for each from the article, then use those values when writing the title and image prompt. Placeholders to resolve:\n";

		foreach ($ai_variables as $variable) {
			$section .= '- ' . $variable . "\n";
		}

		return rtrim($section);
	}

	/**
	 * Describe the exact JSON shape expected back.
	 *
	 * @param string[] $ai_variables  AI variable names.
	 * @param bool     $include_image Whether an image prompt was requested.
	 * @param bool     $include_slug  Whether a post slug was requested.
	 * @return string
	 */
	private function build_response_shape(array $ai_variables, $include_image, $include_slug = false, array $content_image_templates = array()) {
		$shape = array(
			'"title": "the finished post title, with no surrounding quotes"',
			'"excerpt": "the 40-60 word excerpt"',
		);

		if ($include_slug) {
			$shape[] = '"slug": "the finished lowercase hyphenated URL slug"';
		}

		if ($include_image) {
			$shape[] = '"image_prompt": "the finished featured image prompt"';
		}

		if (!empty($content_image_templates)) {
			// For providers without native schema validation, explicitly illustrate the expected
			// JSON dictionary shape containing stringified index keys to keep response parsing reliable.
			$pairs = array();
			foreach (array_keys($content_image_templates) as $idx) {
				$pairs[] = sprintf('"%s": "finished prompt for content image %s"', $idx, $idx);
			}
			$shape[] = '"content_image_prompts": { ' . implode(', ', $pairs) . ' }';
		}

		if (!empty($ai_variables)) {
			$pairs = array();

			foreach ($ai_variables as $variable) {
				$pairs[] = '"' . $variable . '": "resolved value"';
			}

			$shape[] = '"ai_variables": { ' . implode(', ', $pairs) . ' }';
		}

		return "RESPOND WITH EXACTLY THIS JSON SHAPE:\n{\n  " . implode(",\n  ", $shape) . "\n}";
	}

	/**
	 * Build JSON schema for content image prompt recovery.
	 *
	 * @param int[] $missing_indexes Missing placeholder indexes.
	 * @return array JSON schema definition.
	 */
	public function build_recovery_schema(array $missing_indexes): array {
		$props = array();
		foreach ($missing_indexes as $idx) {
			$props[(string) $idx] = array('type' => 'string');
		}

		return array(
			'type'                 => 'object',
			'properties'           => $props,
			'required'             => array_map('strval', $missing_indexes),
			'additionalProperties' => false,
		);
	}

	/**
	 * Build conversational mini-turn recovery prompt for missing content image prompts.
	 *
	 * @param int[] $missing_indexes Missing placeholder indexes.
	 * @param array $configured_templates Optional configured prompt templates keyed by index.
	 * @return string Model prompt.
	 */
	public function build_conversational_recovery_prompt(array $missing_indexes, array $configured_templates = array()): string {
		$template_instructions = array();
		foreach ($missing_indexes as $idx) {
			if (!empty($configured_templates[$idx])) {
				$template_instructions[] = sprintf('Image %1$d style instruction: %2$s', $idx, $configured_templates[$idx]);
			}
		}

		$prompt = sprintf(
			'The previous response was missing image descriptions for content image index(es): %s. Based on the article content above, provide a descriptive visual prompt for each missing content image as JSON matching the schema.',
			implode(', ', $missing_indexes)
		);

		if (!empty($template_instructions)) {
			$prompt .= "

Follow these configured image styles:
" . implode("
", $template_instructions);
		}

		$shape = array();
		foreach ($missing_indexes as $idx) {
			$shape[] = sprintf('"%s": "visual prompt description"', $idx);
		}
		$prompt .= "

RESPOND WITH EXACTLY THIS JSON SHAPE:
{
  " . implode(",
  ", $shape) . "
}";

		return $prompt;
	}

	/**
	 * Build standalone fallback recovery prompt for missing content image prompts.
	 *
	 * @param int[]  $missing_indexes Missing placeholder indexes.
	 * @param string $content         Article content.
	 * @param string $title           Article title.
	 * @param array  $configured_templates Optional configured prompt templates keyed by index.
	 * @return string Model prompt.
	 */
	public function build_standalone_recovery_prompt(array $missing_indexes, string $content, string $title, array $configured_templates = array()): string {
		$clean_excerpt = wp_trim_words(wp_strip_all_tags($content), 120, '...');

		$prompt = sprintf(
			'Based on the article "%1$s" and its excerpt below, generate a vivid, descriptive visual prompt for each required content image index: %2$s.' . "

" . 'Article excerpt:' . "
" . '%3$s' . "

" . 'Respond ONLY with a JSON object where keys are the index numbers as strings and values are the image prompts.',
			$title,
			implode(', ', $missing_indexes),
			$clean_excerpt
		);

		$template_instructions = array();
		foreach ($missing_indexes as $idx) {
			if (!empty($configured_templates[$idx])) {
				$template_instructions[] = sprintf('Image %1$d style instruction: %2$s', $idx, $configured_templates[$idx]);
			}
		}

		if (!empty($template_instructions)) {
			$prompt .= "

Follow these configured image styles:
" . implode("
", $template_instructions);
		}

		return $prompt;
	}

	/**
	 * Append the shared diversity blocks so titles stay varied across posts.
	 *
	 * Only injects avoid_titles block; content-format and post-slice guidance
	 * were already established during content generation in turn 1 and are not
	 * repeated here to prevent input prompt bloat.
	 *
	 * @param string $prompt  Prompt built so far.
	 * @param mixed  $subject Generation context.
	 * @return string
	 */
	private function append_diversity_blocks($prompt, $subject) {
		$avoid_titles_block = $this->diversity_injector->build_avoid_titles_block($subject);

		if (!empty($avoid_titles_block)) {
			$prompt .= "\n\n" . $avoid_titles_block;
		}

		return $prompt;
	}
}
