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
     * Builds AI generation options with separated instructions and context.
     *
     * This method separates voice/style instructions from the main prompt,
     * allowing them to be passed to the AI Engine as system-level instructions.
     * This improves AI response quality by clearly separating:
     * - Instructions: How the AI should behave/write (voice, style, tone)
     * - Context: Background information and template-specific details
     * - Prompt: The actual task/request
     *
     * @param object      $template The template object.
     * @param string      $topic    The topic for the post.
     * @param object|null $voice    Optional. The voice object.
     * @return array Array with keys: 'prompt', 'instructions', 'context'
     */
    public function build_content_options($template, $topic, $voice = null) {
        do_action('aips_before_build_content_prompt', $template, $topic);

        // Build system-level instructions from voice
        $instructions = $this->build_voice_instructions($voice, $topic);

        // Build context from template metadata
        $context = $this->build_template_context($template, $topic);

        // Build the main prompt (the actual task)
        $article_structure_id = isset($template->article_structure_id) ? $template->article_structure_id : null;

        if ($article_structure_id) {
            $processed_prompt = $this->structure_manager->build_prompt($article_structure_id, $topic);

            if (is_wp_error($processed_prompt)) {
                $processed_prompt = $this->template_processor->process($template->prompt_template, $topic);
            }
        } else {
            $processed_prompt = $this->template_processor->process($template->prompt_template, $topic);
        }

        // Add formatting instructions to the prompt
        $prompt = $processed_prompt . "\n\nOutput the response for use as a WordPress post with HTML tags, using <h2> for section titles, <pre> tags for code samples. Be sure to end the post with a concise summary.";

        $prompt = apply_filters('aips_content_prompt', $prompt, $template, $topic);
        $instructions = apply_filters('aips_content_instructions', $instructions, $template, $voice, $topic);
        $context = apply_filters('aips_content_context', $context, $template, $topic);

        return array(
            'prompt' => $prompt,
            'instructions' => $instructions,
            'context' => $context,
        );
    }

    /**
     * Builds system-level instructions from voice settings.
     *
     * These instructions guide the AI's behavior, style, and tone.
     * They are passed to AI Engine's set_instructions() method.
     *
     * @param object|null $voice The voice object.
     * @param string      $topic The topic for variable replacement.
     * @return string Voice instructions or empty string if no voice.
     */
    public function build_voice_instructions($voice, $topic) {
        if (!$voice) {
            return '';
        }

        $instructions_parts = array();

        // Add main content instructions from voice
        if (!empty($voice->content_instructions)) {
            $instructions_parts[] = $this->template_processor->process($voice->content_instructions, $topic);
        }

        // Add any additional voice-specific guidance
        if (!empty($voice->name)) {
            $instructions_parts[] = sprintf('Write in the style and voice of "%s".', $voice->name);
        }

        return implode("\n\n", array_filter($instructions_parts));
    }

    /**
     * Builds context information from template settings.
     *
     * This context provides background information the AI can reference.
     * It is passed to AI Engine's set_context() method.
     *
     * @param object $template The template object.
     * @param string $topic    The topic for variable replacement.
     * @return string Template context or empty string.
     */
    public function build_template_context($template, $topic) {
        $context_parts = array();

        // Add template name/description if available
        if (!empty($template->name)) {
            $context_parts[] = sprintf('Content Template: %s', $template->name);
        }

        if (!empty($template->description)) {
            $context_parts[] = sprintf('Template Description: %s', $template->description);
        }

        // Add topic as context
        if (!empty($topic)) {
            $context_parts[] = sprintf('Topic: %s', $topic);
        }

        // Add category context if available
        if (!empty($template->post_category)) {
            $context_parts[] = sprintf('Target Category: %s', $template->post_category);
        }

        return implode("\n", array_filter($context_parts));
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
     * Builds title generation options with separated instructions and context.
     *
     * @param object      $template The template object.
     * @param string      $topic    The topic for the post.
     * @param object|null $voice    Optional. The voice object.
     * @param string      $content  The generated article content.
     * @return array Array with keys: 'prompt', 'instructions', 'context'
     */
    public function build_title_options($template, $topic, $voice = null, $content = '') {
        // Build voice instructions for title generation
        $instructions = '';
        if ($voice && !empty($voice->title_prompt)) {
            $instructions = $this->template_processor->process($voice->title_prompt, $topic);
        }

        // Build the title prompt
        $prompt = "Generate a title for a blog post based on the content provided.";

        if (!empty($template->title_prompt)) {
            $prompt .= "\n\n" . $this->template_processor->process($template->title_prompt, $topic);
        }

        // Provide the article content as context
        $context = "Article content:\n\n" . $content;

        return array(
            'prompt' => $prompt,
            'instructions' => $instructions,
            'context' => $context,
        );
    }

    /**
     * Builds excerpt generation options with separated instructions and context.
     *
     * @param object|null $voice   Optional. The voice object.
     * @param string      $topic   The topic for variable replacement.
     * @param string      $title   The article title.
     * @param string      $content The article content.
     * @return array Array with keys: 'prompt', 'instructions', 'context'
     */
    public function build_excerpt_options($voice, $topic, $title, $content) {
        // Build voice instructions for excerpt generation
        $instructions = '';
        if ($voice && !empty($voice->excerpt_instructions)) {
            $instructions = $this->template_processor->process($voice->excerpt_instructions, $topic);
        }

        // Build the excerpt prompt
        $prompt = "Write an excerpt for the article. Must be between 40 and 60 characters. Write naturally as a human would. Output only the excerpt, no formatting.";

        // Provide the article title and content as context
        $context = "Article Title: " . $title . "\n\nArticle Body:\n" . $content;

        return array(
            'prompt' => $prompt,
            'instructions' => $instructions,
            'context' => $context,
        );
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
