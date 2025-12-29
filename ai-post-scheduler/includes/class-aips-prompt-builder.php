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
    public function build_title_prompt($template, $topic, $voice = null, $base_prompt = '') {
        $voice_title_prompt = null;
        if ($voice) {
            $voice_title_prompt = $this->template_processor->process($voice->title_prompt, $topic);
        }

        if (!empty($template->title_prompt)) {
            $title_prompt = $this->template_processor->process($template->title_prompt, $topic);

            if ($voice_title_prompt) {
                return $voice_title_prompt . "\n\n" . $title_prompt;
            }
            return $title_prompt; // AIPS_Generator::generate_title handles the "Generate a compelling..." wrapper if strict arg is not passed, but let's check.
            // Actually, AIPS_Generator::generate_title logic is:
            // if ($voice_title_prompt) $title_prompt = $voice_title_prompt . "\n\n" . $prompt;
            // else $title_prompt = "Generate a compelling... " . $prompt;

            // So this method should just return the "prompt" part, and AIPS_Generator handles the wrapper?
            // Or should we move that logic here?
            // Let's keep it simple: return the specific instruction part.
        }

        // If no template title prompt, we use the base prompt.
        return $base_prompt;
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
