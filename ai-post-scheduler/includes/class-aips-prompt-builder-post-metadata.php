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
	 * @return string
	 */
	public function build($context, array $ai_variables = array(), $image_prompt = '') {
		$topic_str = $context->get_topic();
		$sections  = array();

		$sections[] = 'Based on the article you just wrote, produce its metadata. Respond with a single JSON object and nothing else.';

		$title_instructions = $this->resolve_title_instructions($context, $topic_str);

		if (!empty($title_instructions)) {
			$sections[] = "TITLE INSTRUCTIONS:\n" . $title_instructions;
		}

		$sections[] = "EXCERPT INSTRUCTIONS:\nBetween 40 and 60 words. Write naturally as a human would. Plain text, no formatting.";

		if (!empty($image_prompt)) {
			$sections[] = "FEATURED IMAGE INSTRUCTIONS:\nUsing the template below, produce a finished image generation prompt describing the article's featured image. Substitute every placeholder with a concrete value drawn from the article.\n\n" . $image_prompt;
		}

		if (!empty($ai_variables)) {
			$sections[] = $this->build_variables_section($ai_variables);
		}

		$sections[] = $this->build_response_shape($ai_variables, !empty($image_prompt));

		$prompt = implode("\n\n", $sections);
		$prompt = $this->append_diversity_blocks($prompt, $context);

		/**
		 * Filters the combined metadata prompt used by conversational generation.
		 *
		 * @since 3.2.0
		 *
		 * @param string                  $prompt       The assembled prompt.
		 * @param AIPS_Generation_Context $context      Generation context.
		 * @param string[]                $ai_variables AI variable names to resolve.
		 * @param string                  $image_prompt Raw image prompt template.
		 */
		return apply_filters('aips_post_metadata_prompt', $prompt, $context, $ai_variables, $image_prompt);
	}

	/**
	 * JSON schema describing the expected response.
	 *
	 * Passed to providers with a native structured-output path; providers without
	 * one fall back to AIPS_AI_Service's text-based JSON extraction, which the
	 * explicit response shape in the prompt keeps parseable.
	 *
	 * @param string[] $ai_variables  AI variable names to resolve.
	 * @param bool     $include_image Whether an image prompt was requested.
	 * @return array
	 */
	public function get_schema(array $ai_variables = array(), $include_image = false) {
		$properties = array(
			'title'   => array('type' => 'string'),
			'excerpt' => array('type' => 'string'),
		);

		if ($include_image) {
			$properties['image_prompt'] = array('type' => 'string');
		}

		if (!empty($ai_variables)) {
			$variable_properties = array();

			foreach ($ai_variables as $variable) {
				$variable_properties[$variable] = array('type' => 'string');
			}

			$properties['ai_variables'] = array(
				'type'       => 'object',
				'properties' => $variable_properties,
			);
		}

		return array(
			'type'       => 'object',
			'properties' => $properties,
			'required'   => array('title', 'excerpt'),
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
	 * @return string
	 */
	private function build_response_shape(array $ai_variables, $include_image) {
		$shape = array(
			'"title": "the finished post title, with no surrounding quotes"',
			'"excerpt": "the 40-60 word excerpt"',
		);

		if ($include_image) {
			$shape[] = '"image_prompt": "the finished featured image prompt"';
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
	 * Append the shared diversity blocks so titles stay varied across posts.
	 *
	 * @param string $prompt  Prompt built so far.
	 * @param mixed  $subject Generation context.
	 * @return string
	 */
	private function append_diversity_blocks($prompt, $subject) {
		$blocks = array(
			$this->diversity_injector->build_avoid_titles_block($subject),
			$this->diversity_injector->build_content_format_block($subject),
			$this->diversity_injector->build_post_slice_block($subject),
		);

		foreach ($blocks as $block) {
			if (!empty($block)) {
				$prompt .= "\n\n" . $block;
			}
		}

		return $prompt;
	}
}
