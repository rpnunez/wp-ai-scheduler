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
class AIPS_Prompt_Builder_Post_Content {

	/**
	 * Number of random bytes used for the uniqueness seed.
	 */
	const UNIQUENESS_SEED_BYTES = 4;

	/**
	 * @var AIPS_Template_Processor Template processor for prompt variables.
	 */
	private $template_processor;

	/**
	 * @var AIPS_Prompt_Builder_Article_Structure_Section Builder for structured section prompts.
	 */
	private $article_structure_section_builder;

	/**
	 * @var AIPS_Prompt_Builder_Diversity_Injector Diversity block builder.
	 */
	private $diversity_injector;

	/**
	 * @param AIPS_Template_Processor|null                 $template_processor             Optional template processor.
	 * @param AIPS_Prompt_Builder_Article_Structure_Section|null $article_structure_section_builder Optional section prompt builder.
	 * @param AIPS_Prompt_Builder_Diversity_Injector|null  $diversity_injector            Optional diversity injector.
	 */
	public function __construct($template_processor = null, $article_structure_section_builder = null, $diversity_injector = null) {
		$this->template_processor = $template_processor ?: new AIPS_Template_Processor();
		$this->article_structure_section_builder = $article_structure_section_builder ?: new AIPS_Prompt_Builder_Article_Structure_Section(null, null, $this->template_processor);
		$this->diversity_injector = $diversity_injector ?: new AIPS_Prompt_Builder_Diversity_Injector();
	}

	/**
	 * Build the complete content prompt based on context.
	 *
	 * Supports both legacy template-based and generation-context-based flows.
	 *
	 * @param object|AIPS_Generation_Context $template_or_context Template object (legacy) or Generation Context.
	 * @param string|null                    $topic The topic for the post in legacy flows.
	 * @param object|null                    $voice Optional voice object in legacy flows.
	 * @return string
	 */
	public function build($template_or_context, $topic = null, $voice = null) {
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
		$used_structured_prompt = false;

		if ($article_structure_id && $topic) {
			$structured_prompt = $this->article_structure_section_builder->build($article_structure_id, $topic);

			if (!is_wp_error($structured_prompt)) {
				$processed_prompt = $structured_prompt;
				$used_structured_prompt = true;
			}
		}

		if (!$used_structured_prompt) {
			// Always process template variables, even when topic is empty.
			// This prevents raw placeholders like {{topic}} from leaking into prompts.
			$processed_prompt = $this->template_processor->process($processed_prompt, $topic);
		}

		if ($context->get_type() === 'template' && $context->get_voice_id()) {
			$voice = $context->get_voice();
			if ($voice && !empty($voice->content_instructions)) {
				$voice_instructions = $this->template_processor->process($voice->content_instructions, $topic);
				$processed_prompt = $voice_instructions . "\n\n" . $processed_prompt;
			}
		}

		$diversity_block = $this->diversity_injector->build_avoid_titles_block($context);
		if (!empty($diversity_block)) {
			$processed_prompt .= "\n\n" . $diversity_block;
		}

		$content_format_block = $this->diversity_injector->build_content_format_block($context);
		if (!empty($content_format_block)) {
			$processed_prompt .= "\n\n" . $content_format_block;
		}

		$processed_prompt .= "\n\n" . $this->get_uniqueness_seed_line();

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
		$processed_prompt = $this->template_processor->process($template->prompt_template, $topic);

		if ($voice) {
			$voice_instructions = $this->template_processor->process($voice->content_instructions, $topic);
			$processed_prompt = $voice_instructions . "\n\n" . $processed_prompt;
		}

		$diversity_block = $this->diversity_injector->build_avoid_titles_block($template);
		if (!empty($diversity_block)) {
			$processed_prompt .= "\n\n" . $diversity_block;
		}

		$content_format_block = $this->diversity_injector->build_content_format_block($template);
		if (!empty($content_format_block)) {
			$processed_prompt .= "\n\n" . $content_format_block;
		}

		$processed_prompt .= "\n\n" . $this->get_uniqueness_seed_line();

		return apply_filters('aips_content_prompt', $processed_prompt, $template, $topic);
	}

	/**
	 * Generate a uniqueness seed line to append to content prompts.
	 *
	 * Appending a cryptographically random seed for each generation run nudges
	 * the AI to produce varied angles and framing rather than converging on the
	 * same post structure when the same base prompt is used repeatedly.
	 *
	 * @return string
	 */
	private function get_uniqueness_seed_line() {
		// Keep wording neutral so this instruction is valid even when no diversity block is appended.
		return 'Unique generation seed: ' . $this->generate_uniqueness_seed() . '. Use this to add extra variation in angle, framing, and structure while keeping the post meaningfully distinct from past generations.';
	}

	/**
	 * Generate a uniqueness seed with fallback entropy.
	 *
	 * Uses random_bytes() first, then falls back to pseudo-random sources
	 * when secure random generation is unavailable.
	 *
	 * @return string
	 */
	private function generate_uniqueness_seed() {
		try {
			return bin2hex(random_bytes(self::UNIQUENESS_SEED_BYTES));
		} catch (Random\RandomException) {
			$random_one = function_exists('wp_rand') ? wp_rand(0, 0xffffffff) : mt_rand(0, 0xffffffff);
			$random_two = function_exists('wp_rand') ? wp_rand(0, 0xffffffff) : mt_rand(0, 0xffffffff);
			$fallback = $random_one . '|' . $random_two . '|' . uniqid('', true) . '|' . microtime(true);

			return substr(hash('sha256', $fallback), 0, self::UNIQUENESS_SEED_BYTES * 2);
		}
	}
}
