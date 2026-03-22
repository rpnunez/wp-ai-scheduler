<?php
/**
 * Post Content Prompt Builder
 *
 * Responsible for assembling AI prompts that are used exclusively for
 * post content generation. Extracted from AIPS_Prompt_Builder to keep
 * content prompt construction isolated as the prompt builder layer is
 * progressively split into focused classes.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.3
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Builder_Post_Content
 *
 * Builds the AI prompt for post content generation.
 */
class AIPS_Prompt_Builder_Post_Content extends AIPS_Prompt_Builder_Base {

	/**
	 * Build the complete content prompt based on context.
	 *
	 * Supports both legacy template-based and generation-context-based flows.
	 *
	 * @param object|AIPS_Generation_Context $primary_input Template object (legacy) or Generation Context.
	 * @param mixed                          ...$args Optional topic and voice values for legacy flows.
	 * @return string
	 */
	public function build($primary_input, ...$args) {
		$template_or_context = $primary_input;
		$topic = isset($args[0]) ? $args[0] : null;
		$voice = isset($args[1]) ? $args[1] : null;

		if ($template_or_context instanceof AIPS_Generation_Context) {
			return $this->build_from_context($template_or_context);
		}

		return $this->build_from_template($template_or_context, $topic, $voice);
	}

	/**
	 * Build content prompt from a generation context.
	 *
	 * @param AIPS_Generation_Context $context Generation context.
	 * @return string
	 */
	private function build_from_context($context) {
		do_action('aips_before_build_content_prompt', $context, null);

		$processed_prompt = $context->get_content_prompt();
		$article_structure_id = $context->get_article_structure_id();
		$topic = $context->get_topic();

		if ($article_structure_id && $topic) {
			$structured_prompt = $this->get_article_structure_section_builder()->build($article_structure_id, $topic);

			if (!is_wp_error($structured_prompt)) {
				$processed_prompt = $structured_prompt;
			} else {
				$processed_prompt = $this->get_template_processor()->process($processed_prompt, $topic);
			}
		} elseif ($topic) {
			$processed_prompt = $this->get_template_processor()->process($processed_prompt, $topic);
		}

		if ($context->get_type() === 'template' && $context->get_voice_id()) {
			$voice = $context->get_voice();
			if ($voice && !empty($voice->content_instructions)) {
				$voice_instructions = $this->get_template_processor()->process($voice->content_instructions, $topic);
				$processed_prompt = $voice_instructions . "\n\n" . $processed_prompt;
			}
		}

		return apply_filters('aips_content_prompt', $processed_prompt, $context, $topic);
	}

	/**
	 * Build content prompt from a legacy template object.
	 *
	 * @param object      $template Template object.
	 * @param string|null $topic Topic string.
	 * @param object|null $voice Voice object.
	 * @return string
	 */
	private function build_from_template($template, $topic = null, $voice = null) {
		do_action('aips_before_build_content_prompt', $template, $topic);
		$processed_prompt = $this->get_template_processor()->process($template->prompt_template, $topic);

		if ($voice) {
			$voice_instructions = $this->get_template_processor()->process($voice->content_instructions, $topic);
			$processed_prompt = $voice_instructions . "\n\n" . $processed_prompt;
		}

		return apply_filters('aips_content_prompt', $processed_prompt, $template, $topic);
	}
}
