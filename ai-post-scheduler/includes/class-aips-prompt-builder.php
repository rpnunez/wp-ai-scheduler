<?php
/**
 * Prompt Builder Service
 *
 * Responsible for constructing AI prompts for various content types
 * (content, title, excerpt, image) by combining templates, voices,
 * structures, and topics.
 *
 * @package AI_Post_Scheduler
 * @since 1.6.0
 */

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
     * Build the base content prompt (without formatting suffix).
     *
     * Combines article structure (if any) or template prompt
     * and voice instructions.
     *
     * @param object      $template The template object.
     * @param object|null $voice    The voice object (optional).
     * @param string|null $topic    The topic string.
     * @return string|WP_Error The constructed base prompt or error.
     */
    public function build_base_content_prompt($template, $voice = null, $topic = null) {
        $processed_prompt = '';
        $article_structure_id = isset($template->article_structure_id) ? $template->article_structure_id : null;

        if ($article_structure_id) {
            // Use article structure to build prompt
            $processed_prompt = $this->structure_manager->build_prompt($article_structure_id, $topic);

            if (is_wp_error($processed_prompt)) {
                // Fall back to regular template processing if structure fails
                $processed_prompt = $this->template_processor->process($template->prompt_template, $topic);
            }
        } else {
            // Use traditional template processing
            $processed_prompt = $this->template_processor->process($template->prompt_template, $topic);
        }

        if ($voice && !empty($voice->content_instructions)) {
            $voice_instructions = $this->template_processor->process($voice->content_instructions, $topic);
            $processed_prompt = $voice_instructions . "\n\n" . $processed_prompt;
        }

        return $processed_prompt;
    }

    /**
     * Append standard formatting instructions to a prompt.
     *
     * @param string $prompt The base prompt.
     * @return string The prompt with formatting instructions appended.
     */
    public function append_formatting_instructions($prompt) {
        $suffix = "\n\nOutput the response for use as a WordPress post with HTML tags, using <h2> for section titles, <pre> tags for code samples. Be sure to end the post with a concise summary.";
        return $prompt . $suffix;
    }

    /**
     * Build the full content prompt (with formatting).
     *
     * @param object      $template The template object.
     * @param object|null $voice    The voice object (optional).
     * @param string|null $topic    The topic string.
     * @return string|WP_Error The constructed full prompt or error.
     */
    public function build_content_prompt($template, $voice = null, $topic = null) {
        $base_prompt = $this->build_base_content_prompt($template, $voice, $topic);

        if (is_wp_error($base_prompt)) {
            return $base_prompt;
        }

        return $this->append_formatting_instructions($base_prompt);
    }

    /**
     * Compose the final title prompt.
     *
     * Prepares the specific instructions for title generation.
     *
     * @param string      $base_prompt  The base prompt or topic.
     * @param string|null $voice_prompt The voice-specific title instructions.
     * @return string The final prompt string.
     */
    public function compose_title_prompt($base_prompt, $voice_prompt = null) {
        if ($voice_prompt) {
            return $voice_prompt . "\n\n" . $base_prompt;
        }

        return "Generate a compelling blog post title for the following topic. Return only the title, nothing else:\n\n" . $base_prompt;
    }

    /**
     * Prepare the base prompt for title generation.
     *
     * Decides whether to use the template's custom title prompt or the generated content prompt.
     *
     * @param object      $template          The template object.
     * @param string      $processed_content_prompt The processed content prompt (fallback).
     * @param string|null $topic             The topic.
     * @return string The base prompt for the title.
     */
    public function prepare_title_base_prompt($template, $processed_content_prompt, $topic = null) {
        if (!empty($template->title_prompt)) {
            return $this->template_processor->process($template->title_prompt, $topic);
        }
        return $processed_content_prompt;
    }

    /**
     * Compose the excerpt prompt.
     *
     * @param string      $title              The article title.
     * @param string      $content            The article content.
     * @param string|null $voice_instructions Voice specific excerpt instructions.
     * @return string The final excerpt prompt.
     */
    public function compose_excerpt_prompt($title, $content, $voice_instructions = null) {
        $excerpt_prompt = "Write an excerpt for an article. Must be between 40 and 60 characters. Write naturally as a human would. Output only the excerpt, no formatting.\n\n";

        if ($voice_instructions) {
            $excerpt_prompt .= $voice_instructions . "\n\n";
        }

        $excerpt_prompt .= "ARTICLE TITLE:\n" . $title . "\n\n";
        $excerpt_prompt .= "ARTICLE BODY:\n" . $content . "\n\n";
        $excerpt_prompt .= "Create a compelling excerpt that captures the essence of the article while considering the context.";

        return $excerpt_prompt;
    }

    /**
     * Build the image generation prompt.
     *
     * @param object      $template The template object.
     * @param string|null $topic    The topic.
     * @return string The processed image prompt.
     */
    public function build_image_prompt($template, $topic = null) {
        if (empty($template->image_prompt)) {
            return '';
        }
        return $this->template_processor->process($template->image_prompt, $topic);
    }

    /**
     * Process a raw string with template variables.
     *
     * helper to expose processor capability if needed.
     */
    public function process_string($string, $topic = null) {
        return $this->template_processor->process($string, $topic);
    }
}
