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
class AIPS_Prompt_Builder_Post_Excerpt {

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
	 * Build the complete prompt for excerpt generation.
	 *
	 * @param string      $title Title of the generated article.
	 * @param string      $content The article content to summarize.
	 * @param object|null $voice Optional voice object with excerpt instructions.
	 * @param string|null $topic Optional topic to inject into voice instructions.
	 * @param mixed       $subject Optional template or generation context.
	 * @return string
	 */
	public function build($title, $content, $voice = null, $topic = null, $subject = null) {
		$excerpt_prompt = "Write an excerpt for an article. Must be between 40 and 60 words. Write naturally as a human would. Output only the excerpt, no formatting.\n\n";

		$voice_instructions = $this->build_instructions($voice, $topic);
		if (!empty($voice_instructions)) {
			$excerpt_prompt .= $voice_instructions . "\n\n";
		}

		$excerpt_prompt .= "ARTICLE TITLE:\n" . $title . "\n\n";
		$excerpt_prompt .= "ARTICLE BODY:\n" . $content . "\n\n";

		$diversity_block = $this->diversity_injector->build_avoid_titles_block($subject);
		if (!empty($diversity_block)) {
			$excerpt_prompt .= $diversity_block . "\n\n";
		}

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
			return $this->template_processor->process($voice->excerpt_instructions, $topic);
		}

		return null;
	}
}
