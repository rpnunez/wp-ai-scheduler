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
	 * @var AIPS_Template_Processor Template processor for prompt variables.
	 */
	private $template_processor;

	/**
	 * @var AIPS_Prompt_Builder_ArticleStructure_Section Builder for structured section prompts.
	 */
	private $article_structure_section_builder;

	/**
	 * @var AIPS_Sources_Repository Repository for fetching source URLs.
	 */
	private $sources_repo;

	/**
	 * @param AIPS_Template_Processor|null $template_processor Optional template processor.
	 * @param AIPS_Prompt_Builder_ArticleStructure_Section|null $article_structure_section_builder Optional section prompt builder.
	 * @param AIPS_Sources_Repository|null $sources_repo Optional sources repository.
	 */
	public function __construct($template_processor = null, $article_structure_section_builder = null) {
		$this->template_processor = $template_processor ?: new AIPS_Template_Processor();
		$this->article_structure_section_builder = $article_structure_section_builder ?: new AIPS_Prompt_Builder_ArticleStructure_Section(null, null, $this->template_processor);
		$this->sources_repo = new AIPS_Sources_Repository();
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

		if ($article_structure_id && $topic) {
			$structured_prompt = $this->article_structure_section_builder->build($article_structure_id, $topic);

			if (!is_wp_error($structured_prompt)) {
				$processed_prompt = $structured_prompt;
			} else {
				$processed_prompt = $this->template_processor->process($processed_prompt, $topic);
			}
		} elseif ($topic) {
			$processed_prompt = $this->template_processor->process($processed_prompt, $topic);
		}

		if ($context->get_type() === 'template' && $context->get_voice_id()) {
			$voice = $context->get_voice();
			if ($voice && !empty($voice->content_instructions)) {
				$voice_instructions = $this->template_processor->process($voice->content_instructions, $topic);
				$processed_prompt = $voice_instructions . "\n\n" . $processed_prompt;
			}
		}

		// Inject sources when the context has include_sources enabled.
		if ($context->get_include_sources()) {
			$sources_block = $this->build_sources_block($context->get_source_group_ids());
			if (!empty($sources_block)) {
				$processed_prompt = $sources_block . $processed_prompt;
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
		$processed_prompt = $this->template_processor->process($template->prompt_template, $topic);

		if ($voice) {
			$voice_instructions = $this->template_processor->process($voice->content_instructions, $topic);
			$processed_prompt = $voice_instructions . "\n\n" . $processed_prompt;
		}

		// Inject sources when the template has include_sources enabled.
        if (!empty($template->include_sources)) {
            $group_ids = array();
            if (!empty($template->source_group_ids)) {
                $decoded = json_decode($template->source_group_ids, true);
                $group_ids = is_array($decoded) ? array_map('intval', $decoded) : array();
            }
            $sources_block = $this->build_sources_block($group_ids);
            if (!empty($sources_block)) {
                $processed_prompt = $sources_block . $processed_prompt;
            }
        }

		return apply_filters('aips_content_prompt', $processed_prompt, $template, $topic);
	}

	/**
     * Build a trusted sources block for inclusion in AI prompts.
     *
     * Fetches active source URLs from the given source group term IDs and
     * formats them into a prompt instruction block. Returns an empty string
     * when no matching sources are found.
     *
     * @param int[] $term_ids Source group term IDs to fetch sources from.
     * @return string Formatted sources block, or empty string.
     */
    public function build_sources_block(array $term_ids) {
        if (empty($term_ids)) {
            return '';
        }

        $source_urls = $this->sources_repo->get_urls_by_group_term_ids($term_ids, true);

        if (empty($source_urls)) {
            return '';
        }

        $block  = "Trusted sources (reference and cite these URLs when relevant):\n";
        foreach ($source_urls as $url) {
            $block .= '  - ' . $url . "\n";
        }

        return $block . "\n";
    }
}
