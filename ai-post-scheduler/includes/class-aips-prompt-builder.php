<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Prompt_Builder
 *
 * Responsible for constructing AI prompts for titles, excerpts, and content.
 * Extracted from AIPS_Generator to adhere to SRP.
 */
class AIPS_Prompt_Builder {

    private $template_processor;
    private $structure_manager;

    public function __construct($template_processor = null, $structure_manager = null) {
        $this->template_processor = $template_processor ?: new AIPS_Template_Processor();
        $this->structure_manager = $structure_manager ?: new AIPS_Article_Structure_Manager();
    }

    /**
     * Build the content generation prompt.
     *
     * @param object $template The template object.
     * @param string $topic    The topic.
     * @param object|null $voice The voice object.
     * @return string The processed prompt.
     */
    public function build_content_prompt($template, $topic, $voice = null) {
        // Check if article_structure_id is provided, build prompt with structure
        $article_structure_id = isset($template->article_structure_id) ? $template->article_structure_id : null;

        $processed_prompt = '';

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

        if ($voice) {
            $voice_instructions = $this->template_processor->process($voice->content_instructions, $topic);
            $processed_prompt = $voice_instructions . "\n\n" . $processed_prompt;
        }

        $content_prompt = $processed_prompt . "\n\nOutput the response for use as a WordPress post with HTML tags, using <h2> for section titles, <pre> tags for code samples. Be sure to end the post with a concise summary.";

        return $content_prompt;
    }

    /**
     * Build the base prompt (without voice/formatting instructions)
     * Used for context when generating titles/excerpts.
     *
     * @param object $template
     * @param string $topic
     * @return string
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

    /**
     * Build the title generation prompt.
     *
     * @param string $base_prompt The content prompt or topic.
     * @param object|null $template The template object.
     * @param string $topic The topic.
     * @param object|null $voice The voice object.
     * @return string The title prompt.
     */
    public function build_title_prompt($base_prompt, $template, $topic, $voice = null) {
        $voice_title_prompt = null;
        if ($voice) {
            $voice_title_prompt = $this->template_processor->process($voice->title_prompt, $topic);
        }

        if (!empty($template->title_prompt)) {
            $title_prompt = $this->template_processor->process($template->title_prompt, $topic);

            if ($voice_title_prompt) {
                return $voice_title_prompt . "\n\n" . $title_prompt;
            }
            return $title_prompt;
        }

        // Default title generation logic
        if ($voice_title_prompt) {
            return $voice_title_prompt . "\n\n" . $base_prompt;
        } else {
            return "Generate a compelling blog post title for the following topic. Return only the title, nothing else:\n\n" . $base_prompt;
        }
    }

    /**
     * Build the excerpt generation prompt.
     *
     * @param string $title The generated title.
     * @param string $content The generated content.
     * @param object|null $voice The voice object.
     * @param string $topic The topic.
     * @return string The excerpt prompt.
     */
    public function build_excerpt_prompt($title, $content, $voice = null, $topic = '') {
        $excerpt_prompt = "Write an excerpt for an article. Must be between 40 and 60 characters. Write naturally as a human would. Output only the excerpt, no formatting.\n\n";

        if ($voice && !empty($voice->excerpt_instructions)) {
            $voice_excerpt_instructions = $this->template_processor->process($voice->excerpt_instructions, $topic);
            $excerpt_prompt .= $voice_excerpt_instructions . "\n\n";
        }

        $excerpt_prompt .= "ARTICLE TITLE:\n" . $title . "\n\n";
        $excerpt_prompt .= "ARTICLE BODY:\n" . $content . "\n\n";
        $excerpt_prompt .= "Create a compelling excerpt that captures the essence of the article while considering the context.";

        return $excerpt_prompt;
    }

    /**
     * Build the image generation prompt.
     *
     * @param object $template The template object.
     * @param string $topic The topic.
     * @return string|null The image prompt or null if not applicable.
     */
    public function build_image_prompt($template, $topic) {
        if ($template->generate_featured_image && !empty($template->image_prompt)) {
            return $this->template_processor->process($template->image_prompt, $topic);
        }
        return null;
    }
}
