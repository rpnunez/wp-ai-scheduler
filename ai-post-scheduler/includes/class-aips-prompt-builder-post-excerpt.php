<?php
/**
 * Post Excerpt Prompt Builder
 *
 * Responsible for assembling AI prompts that are used exclusively for
 * post excerpt generation. Extracted from AIPS_Prompt_Builder to keep
 * excerpt prompt construction isolated as the prompt builder layer is
 * progressively split into focused classes.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.3
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Builder_Post_Excerpt
 *
 * Builds the AI prompt for post excerpt generation.
 */
class AIPS_Prompt_Builder_Post_Excerpt extends AIPS_Prompt_Builder_Base {

	/**
	 * Build the complete prompt for excerpt generation.
	 *
	 * @param string $primary_input Article title.
	 * @param mixed  ...$args Optional content, voice, and topic values.
	 * @return string
	 */
	public function build($primary_input, ...$args) {
		$title = $primary_input;
		$content = isset($args[0]) ? $args[0] : '';
		$voice = isset($args[1]) ? $args[1] : null;
		$topic = isset($args[2]) ? $args[2] : null;
		$excerpt_prompt = "Write an excerpt for an article. Must be between 40 and 60 words. Write naturally as a human would. Output only the excerpt, no formatting.\n\n";

		$voice_instructions = $this->build_instructions($voice, $topic);
		if (!empty($voice_instructions)) {
			$excerpt_prompt .= $voice_instructions . "\n\n";
		}

		$excerpt_prompt .= "ARTICLE TITLE:\n" . $title . "\n\n";
		$excerpt_prompt .= "ARTICLE BODY:\n" . $content . "\n\n";
		$excerpt_prompt .= 'Create a compelling excerpt that captures the essence of the article while considering the context.';

		return apply_filters('aips_excerpt_prompt', $excerpt_prompt, $title, $content, $voice, $topic);
	}

	/**
	 * Build voice-specific excerpt instructions.
	 *
	 * @param object|null $voice Voice configuration object.
	 * @param string|null $topic Topic to inject into instructions.
	 * @return string|null
	 */
	public function build_instructions($voice, $topic) {
		if ($voice && !empty($voice->excerpt_instructions)) {
			return $this->get_template_processor()->process($voice->excerpt_instructions, $topic);
		}

		return null;
	}
}
