<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Prompt_Builder {

    private $template_processor;
    private $structure_manager;

    public function __construct($template_processor = null, $structure_manager = null) {
        $this->template_processor = $template_processor ?: new AIPS_Template_Processor();
        $this->structure_manager = $structure_manager ?: new AIPS_Article_Structure_Manager();
    }

    /**
     * Builds the complete content prompt based on template, topic, and voice.
     *
     * @param object $template The template object.
     * @param string $topic    The topic for the post.
     * @param object $voice    Optional. The voice object.
     * @return string The constructed prompt.
     */
    public function build_content_prompt($template, $topic, $voice = null) {
        do_action('aips_before_build_content_prompt', $template, $topic);

        // Check if article_structure_id is provided, build prompt with structure
        $article_structure_id = isset($template->article_structure_id) ? $template->article_structure_id : null;

        if ($article_structure_id) {
            // Use article structure to build prompt
            $processed_prompt = $this->structure_manager->build_prompt($article_structure_id, $topic);

            if (is_wp_error($processed_prompt)) {
                // Fall back to regular template processing
                $processed_prompt = $this->template_processor->process($template->prompt_template, $topic);
            }
        } else {
            // Use traditional template processing
            $processed_prompt = $this->template_processor->process($template->prompt_template, $topic);
        }

        if ($voice) {
            $voice_instructions = $this->template_processor->process($voice->content_instructions, $topic);
            $processed_prompt = $voice_instructions . "\n\n" . $processed_prompt;
        }

        $content_prompt = $processed_prompt . "\n\nOutput the response for use as a WordPress post with HTML tags, using <h2> for section titles, <pre> tags for code samples. Be sure to end the post with a concise summary.";

        $content_prompt = apply_filters('aips_content_prompt', $content_prompt, $template, $topic);

        return $content_prompt;
    }

    /**
     * Builds the prompt for title generation.
     *
     * @param object $template
     * @param string $topic
     * @param object $voice
     * @param string $base_prompt The base content prompt (processed).
     * @return string The title prompt.
     */
    /**
     * Builds the prompt instruction for title generation.
     *
     * Returns the specific instruction to give to the AI for generating the title.
     * This combines voice instructions and template title prompt instructions.
     * It does NOT include the base content context.
     *
     * @param object $template
     * @param string $topic
     * @param object $voice
     * @return string|null The specific title instruction, or null if none exists.
     */
    public function build_title_instruction($template, $topic, $voice = null) {
        $voice_title_instruction = null;
        if ($voice) {
            $voice_title_instruction = $this->template_processor->process($voice->title_prompt, $topic);
        }

        $template_title_instruction = null;
        if (!empty($template->title_prompt)) {
            $template_title_instruction = $this->template_processor->process($template->title_prompt, $topic);
        }

        if ($voice_title_instruction && $template_title_instruction) {
            return $voice_title_instruction . "\n\n" . $template_title_instruction;
        }

        if ($voice_title_instruction) {
            return $voice_title_instruction;
        }

        return $template_title_instruction;
    }

    /**
     * Legacy method for backward compatibility, but we are refactoring away from it.
     * @deprecated Use build_title_instruction instead.
     */
    public function build_title_prompt($template, $topic, $voice = null, $base_prompt = '') {
        $instruction = $this->build_title_instruction($template, $topic, $voice);
        return $instruction ? $instruction : $base_prompt;
    }

    /**
     * Builds the prompt for excerpt generation.
     *
     * @param object $voice
     * @param string $topic
     * @return string|null
     */
    public function build_excerpt_instructions($voice, $topic) {
        if ($voice && !empty($voice->excerpt_instructions)) {
            return $this->template_processor->process($voice->excerpt_instructions, $topic);
        }
        return null;
    }

    /**
     * Helper to get the base processed prompt without the "Output response..." suffix.
     * Useful for title generation context.
     */
    public function build_base_content_prompt($template, $topic) {
        $article_structure_id = isset($template->article_structure_id) ? $template->article_structure_id : null;

        if ($article_structure_id) {
            $processed_prompt = $this->structure_manager->build_prompt($article_structure_id, $topic);
            if (!is_wp_error($processed_prompt)) {
                return $processed_prompt;
            }
        }

        return $this->template_processor->process($template->prompt_template, $topic);
    }
}
