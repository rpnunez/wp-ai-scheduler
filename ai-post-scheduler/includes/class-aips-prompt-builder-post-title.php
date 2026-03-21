<?php
/**
 * Post Title Prompt Builder
 *
 * Responsible for assembling AI prompts that are used exclusively for
 * post title generation. Extracted from AIPS_Prompt_Builder to keep
 * title prompt construction isolated as the prompt builder layer is
 * progressively split into focused classes.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.3
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Builder_Post_Title
 *
 * Builds the AI prompt for post title generation.
 */
class AIPS_Prompt_Builder_Post_Title {

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
	 * Build the complete prompt for title generation.
	 *
	 * This method encapsulates all title prompt construction logic. It uses the
	 * generated article content as primary context, and applies the following
	 * precedence for title instructions:
	 *   1. Voice title prompt (if provided)
	 *   2. Template/Context title prompt (if provided)
	 *
	 * @param object|AIPS_Generation_Context $template_or_context Template object (legacy) or Generation Context.
	 * @param string|null                    $topic Optional topic to inject into prompts for legacy calls.
	 * @param object|null                    $voice Optional voice object with overrides for legacy calls.
	 * @param string                         $content Generated article content used as context.
	 * @return string
	 */
	public function build($template_or_context, $topic = null, $voice = null, $content = '') {
		$title_instructions = '';

		if ($template_or_context instanceof AIPS_Generation_Context) {
			$context = $template_or_context;
			$topic_str = $context->get_topic();

			if ($context->get_type() === 'template' && $context->get_voice_id()) {
				$voice_obj = $context->get_voice();
				if ($voice_obj && !empty($voice_obj->title_prompt)) {
					$title_instructions = $this->template_processor->process($voice_obj->title_prompt, $topic_str);
				}
			}

			if (empty($title_instructions)) {
				$title_prompt = $context->get_title_prompt();
				if (!empty($title_prompt)) {
					$title_instructions = $this->template_processor->process($title_prompt, $topic_str);
				}
			}

			$prompt = $this->build_base_prompt($title_instructions, $content);

			return apply_filters('aips_title_prompt', $prompt, $context, $topic_str, null, $content);
		}

		$template = $template_or_context;

		if ($voice && !empty($voice->title_prompt)) {
			$title_instructions = $this->template_processor->process($voice->title_prompt, $topic);
		} elseif (!empty($template->title_prompt)) {
			$title_instructions = $this->template_processor->process($template->title_prompt, $topic);
		}

		$prompt = $this->build_base_prompt($title_instructions, $content);

		return apply_filters('aips_title_prompt', $prompt, $template, $topic, $voice, $content);
	}

	/**
	 * Build the common title prompt shell used by both legacy and context flows.
	 *
	 * @param string $title_instructions Processed title instructions.
	 * @param string $content Generated article content.
	 * @return string
	 */
	private function build_base_prompt($title_instructions, $content) {
		$prompt = 'Generate a title for a blog post, based on the content below. Respond with ONLY the most relevant title, nothing else.';

		if (!empty($title_instructions)) {
			$prompt .= " Here are your instructions:\n\n" . $title_instructions;
		}

		$prompt .= "\n\nHere is the content:\n\n" . $content;

		return $prompt;
	}
}
