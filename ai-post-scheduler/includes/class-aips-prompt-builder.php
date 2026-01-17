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

        $content_prompt = $processed_prompt;

        $content_prompt = apply_filters('aips_content_prompt', $content_prompt, $template, $topic);

        return $content_prompt;
    }

    /**
     * Builds an auxiliary context string for AI Engine queries.
     *
     * This keeps instructions (voice, formatting, safety) out of the main message
     * while still providing them through the AI Engine's context channel.
     *
     * @param object $template The template object.
     * @param string $topic    The topic for the post.
     * @param object $voice    Optional. The voice object.
     * @return string Context string (may be empty).
     */
    public function build_content_context($template, $topic, $voice = null) {
        $context_parts = array();

        if ($voice && !empty($voice->content_instructions)) {
            $context_parts[] = $this->template_processor->process($voice->content_instructions, $topic);
        }

        $context_parts[] = $this->get_output_instructions();

        /**
         * Filter the context sent to AI Engine for content generation.
         *
         * @since 1.6.0
         *
         * @param array  $context_parts Array of context fragments.
         * @param object $template      Template object.
         * @param string $topic         Topic string.
         * @param object $voice         Optional voice object.
         */
        $context_parts = apply_filters('aips_content_context_parts', $context_parts, $template, $topic, $voice);

        $context_parts = array_filter(
            array_map('trim', $context_parts),
            function($part) {
                return !empty($part);
            }
        );

        return implode("\n\n", $context_parts);
    }

    /**
     * Builds the complete prompt for title generation.
     *
     * This method encapsulates all title prompt construction logic. It uses the
     * generated article content as primary context, and applies the following
     * precedence for title instructions:
     *   1. Voice title prompt (if provided)
     *   2. Template title prompt (if provided)
     *
     * The final prompt structure sent to AI:
     *   "Generate a title for a blog post, based on the content below. Here are your instructions:\n\n"
     *   (Voice Title Prompt OR Template Title Prompt)
     *   "\n\nHere is the content:\n\n"
     *   (Generated Post Content)
     *
     * @param object      $template Template object containing prompts and settings.
     * @param string|null $topic    Optional topic to be injected into prompts.
     * @param object|null $voice    Optional voice object with overrides.
     * @param string      $content  Generated article content used as context.
     * @return string The complete title generation prompt.
     */
    public function build_title_prompt($template, $topic, $voice = null, $content = '') {
        // Build title instructions based on voice or template configuration.
        // Voice title prompt takes precedence over template title prompt.
        $title_instructions = '';

        if ($voice && !empty($voice->title_prompt)) {
            $title_instructions = $this->template_processor->process($voice->title_prompt, $topic);
        } elseif (!empty($template->title_prompt)) {
            $title_instructions = $this->template_processor->process($template->title_prompt, $topic);
        }

        // Build the title generation prompt using the generated content as context.
        $prompt = "Generate a title for a blog post, based on the content below. Here are your instructions:\n\n";

        if (!empty($title_instructions)) {
            $prompt .= $title_instructions . "\n\n";
        }

        $prompt .= "Here is the content:\n\n" . $content;

        // Allow filtering of title prompt
        $prompt = apply_filters('aips_title_prompt', $prompt, $template, $topic, $voice, $content);

        return $prompt;
    }

    /**
     * Builds the complete prompt for excerpt generation.
     *
     * Constructs a prompt that instructs the AI to create a short, compelling
     * excerpt for the article. Includes voice-specific instructions if provided.
     *
     * @param string      $title   Title of the generated article.
     * @param string      $content The article content to summarize.
     * @param object|null $voice   Optional voice object with excerpt instructions.
     * @param string|null $topic   Optional topic to be injected into prompts.
     * @return string The complete excerpt generation prompt.
     */
    public function build_excerpt_prompt($title, $content, $voice = null, $topic = null) {
        $excerpt_prompt = "Write an excerpt for an article. Must be between 40 and 60 words. Write naturally as a human would. Output only the excerpt, no formatting.\n\n";
        
        // Add voice-specific excerpt instructions if provided
        if ($voice && !empty($voice->excerpt_instructions)) {
            $voice_instructions = $this->template_processor->process($voice->excerpt_instructions, $topic);
            $excerpt_prompt .= $voice_instructions . "\n\n";
        }
        
        $excerpt_prompt .= "ARTICLE TITLE:\n" . $title . "\n\n";
        $excerpt_prompt .= "ARTICLE BODY:\n" . $content . "\n\n";
        $excerpt_prompt .= "Create a compelling excerpt that captures the essence of the article while considering the context.";
        
        // Allow filtering of excerpt prompt
        $excerpt_prompt = apply_filters('aips_excerpt_prompt', $excerpt_prompt, $title, $content, $voice, $topic);
        
        return $excerpt_prompt;
    }

    /**
     * Builds voice-specific excerpt instructions (legacy method for backward compatibility).
     *
     * This method is maintained for backward compatibility but the new
     * build_excerpt_prompt() should be preferred for full excerpt generation.
     *
     * @deprecated Use build_excerpt_prompt() instead
     * @param object|null $voice
     * @param string|null $topic
     * @return string|null
     */
    public function build_excerpt_instructions($voice, $topic) {
        if ($voice && !empty($voice->excerpt_instructions)) {
            return $this->template_processor->process($voice->excerpt_instructions, $topic);
        }
        return null;
    }

    /**
     * Standard output instructions for article formatting.
     *
     * @return string
     */
    private function get_output_instructions() {
        return 'Output the response for use as a WordPress post with HTML tags, using <h2> for section titles, <pre> tags for code samples. Be sure to end the post with a concise summary.';
    }
}
