<?php
/**
 * Post Featured Image Prompt Builder
 *
 * Responsible for assembling AI prompts that are used exclusively for
 * featured image generation. Extracted from AIPS_Prompt_Builder to keep
 * image prompt construction isolated as the prompt builder layer is
 * progressively split into focused classes.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.3
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Builder_Post_Featured_Image
 *
 * Builds the AI prompt for featured image generation.
 */
class AIPS_Prompt_Builder_Post_Featured_Image {

	/**
	 * @var AIPS_Template_Processor Template processor for prompt variables.
	 */
	private $template_processor;

	/**
	 * @param AIPS_Template_Processor|null $template_processor Optional template processor.
	 */
	public function __construct($template_processor = null) {
		$this->template_processor = $template_processor ?: new AIPS_Template_Processor();
	}

	/**
	 * Build the processed featured image prompt.
	 *
	 * Returns an empty string when featured image generation is disabled, when the
	 * source is not `ai_prompt`, or when no image prompt is available.
	 *
	 * @param object|AIPS_Generation_Context $template_or_context Template object (legacy) or Generation Context.
	 * @param string|null                    $topic Topic string for legacy flows.
	 * @return string
	 */
	public function build($template_or_context, $topic = null) {
		if ($template_or_context instanceof AIPS_Generation_Context) {
			return $this->build_from_context($template_or_context);
		}

		return $this->build_from_template($template_or_context, $topic);
	}

	/**
	 * Build image prompt from a generation context.
	 *
	 * @param AIPS_Generation_Context $context Generation context.
	 * @return string
	 */
	private function build_from_context($context) {
		if (!$context->should_generate_featured_image()) {
			return '';
		}

		if ($context->get_featured_image_source() !== 'ai_prompt') {
			return '';
		}

		$image_prompt = $context->get_image_prompt();
		if (empty($image_prompt)) {
			return '';
		}

		return $this->template_processor->process($image_prompt, $context->get_topic());
	}

	/**
	 * Build image prompt from a legacy template object.
	 *
	 * @param object      $template Template object.
	 * @param string|null $topic Topic string.
	 * @return string
	 */
	private function build_from_template($template, $topic = null) {
		$should_generate = isset($template->generate_featured_image) && $template->generate_featured_image;
		$source = isset($template->featured_image_source) ? $template->featured_image_source : 'ai_prompt';
		$image_prompt = isset($template->image_prompt) ? $template->image_prompt : '';

		if (!$should_generate || $source !== 'ai_prompt' || empty($image_prompt)) {
			return '';
		}

		return $this->template_processor->process($image_prompt, $topic);
	}
}
