<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Prompt_Builder
 *
 * Responsible for constructing prompt strings for AI generation.
 * Handles the combination of templates, topics, voices, and structure instructions.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Prompt_Builder {

    /**
     * @var AIPS_Template_Processor
     */
    private $template_processor;

    /**
     * @var AIPS_Article_Structure_Manager
     */
    private $structure_manager;

    /**
     * Initialize the prompt builder.
     *
     * @param AIPS_Template_Processor|null      $template_processor Optional template processor.
     * @param AIPS_Article_Structure_Manager|null $structure_manager   Optional structure manager.
     */
    public function __construct($template_processor = null, $structure_manager = null) {
        $this->template_processor = $template_processor ?: new AIPS_Template_Processor();
        $this->structure_manager = $structure_manager ?: new AIPS_Article_Structure_Manager();
    }

    /**
     * Build the raw content prompt (before voice or format instructions).
     *
     * @param object $template The template object.
     * @param string $topic    The topic string.
     * @return string|WP_Error The raw prompt or error.
     */
    public function build_base_content_prompt($template, $topic) {
        $article_structure_id = isset($template->article_structure_id) ? $template->article_structure_id : null;

        if ($article_structure_id) {
            $processed_prompt = $this->structure_manager->build_prompt($article_structure_id, $topic);

            if (!is_wp_error($processed_prompt)) {
                return $processed_prompt;
            }
        }

        // Fallback or default template processing
        return $this->template_processor->process($template->prompt_template, $topic);
    }

    /**
     * Apply voice instructions to a prompt.
     *
     * @param string $prompt The prompt to prepend voice instructions to.
     * @param object $voice  The voice object.
     * @param string $topic  The topic (for variable substitution in voice).
     * @param string $type   The type of prompt ('content', 'title', 'excerpt').
     * @return string The prompt with voice instructions applied.
     */
    public function apply_voice($prompt, $voice, $topic, $type = 'content') {
        if (!$voice) {
            return $prompt;
        }

        $instructions = '';
        if ($type === 'content') {
            $instructions = $this->template_processor->process($voice->content_instructions, $topic);
        } elseif ($type === 'title') {
            $instructions = $this->template_processor->process($voice->title_prompt, $topic);
        } elseif ($type === 'excerpt' && !empty($voice->excerpt_instructions)) {
             $instructions = $this->template_processor->process($voice->excerpt_instructions, $topic);
        }

        if (empty($instructions)) {
            return $prompt;
        }

        return $instructions . "\n\n" . $prompt;
    }

    /**
     * Apply default formatting instructions for WordPress posts.
     *
     * @param string $prompt The prompt to append instructions to.
     * @return string The prompt with formatting instructions.
     */
    public function apply_format_instructions($prompt) {
        return $prompt . "\n\nOutput the response for use as a WordPress post with HTML tags, using <h2> for section titles, <pre> tags for code samples. Be sure to end the post with a concise summary.";
    }

    /**
     * Build the full content prompt ready for AI.
     *
     * @param object $template The template object.
     * @param string $topic    The topic.
     * @param object $voice    Optional voice object.
     * @return string|WP_Error The full prompt or error.
     */
    public function build_full_content_prompt($template, $topic, $voice = null) {
        $base = $this->build_base_content_prompt($template, $topic);

        if (is_wp_error($base)) {
            return $base;
        }

        $with_voice = $this->apply_voice($base, $voice, $topic, 'content');
        return $this->apply_format_instructions($with_voice);
    }

    /**
     * Build title prompt from raw string and optional voice.
     *
     * @param string $prompt             The topic or content prompt.
     * @param string $voice_title_prompt Optional voice instructions for title.
     * @return string The final title prompt.
     */
    public function build_title_prompt_from_raw($prompt, $voice_title_prompt = null) {
        if ($voice_title_prompt) {
            return $voice_title_prompt . "\n\n" . $prompt;
        }

        return "Generate a compelling blog post title for the following topic. Return only the title, nothing else:\n\n" . $prompt;
    }

    /**
     * Build the excerpt prompt.
     *
     * @param string $title   The post title.
     * @param string $content The post content.
     * @param object $voice   Optional voice object.
     * @param string $topic   Optional topic (for voice variable substitution).
     * @return string The excerpt prompt.
     */
    public function build_excerpt_prompt($title, $content, $voice = null, $topic = null) {
        $voice_excerpt_instructions = null;
        if ($voice && !empty($voice->excerpt_instructions)) {
            $voice_excerpt_instructions = $this->template_processor->process($voice->excerpt_instructions, $topic);
        }

        return $this->build_excerpt_prompt_from_raw($title, $content, $voice_excerpt_instructions);
    }

    /**
     * Build the excerpt prompt from raw instructions.
     *
     * @param string $title                      The post title.
     * @param string $content                    The post content.
     * @param string $voice_excerpt_instructions Optional voice instructions.
     * @return string The excerpt prompt.
     */
    public function build_excerpt_prompt_from_raw($title, $content, $voice_excerpt_instructions = null) {
        $excerpt_prompt = "Write an excerpt for an article. Must be between 40 and 60 characters. Write naturally as a human would. Output only the excerpt, no formatting.\n\n";

        if ($voice_excerpt_instructions) {
            $excerpt_prompt .= $voice_excerpt_instructions . "\n\n";
        }

        $excerpt_prompt .= "ARTICLE TITLE:\n" . $title . "\n\n";
        $excerpt_prompt .= "ARTICLE BODY:\n" . $content . "\n\n";
        $excerpt_prompt .= "Create a compelling excerpt that captures the essence of the article while considering the context.";

        return $excerpt_prompt;
    }

    /**
     * Build the image prompt.
     *
     * @param object $template The template object.
     * @param string $topic    The topic.
     * @return string|null The image prompt or null if empty.
     */
    public function build_image_prompt($template, $topic) {
        if (empty($template->image_prompt)) {
            return null;
        }
        return $this->template_processor->process($template->image_prompt, $topic);
    }

    /**
     * Build title prompt.
     *
     * @param object $template            The template object.
     * @param string $topic               The topic.
     * @param string $base_content_prompt The base content prompt.
     * @param object $voice               Optional voice object.
     * @return string The final title prompt.
     */
    public function build_title_prompt($template, $topic, $base_content_prompt, $voice = null) {
        $voice_title_prompt = null;
        if ($voice) {
             $voice_title_prompt = $this->template_processor->process($voice->title_prompt, $topic);
        }

        $prompt_to_use = $base_content_prompt; // Default fallback

        if (!empty($template->title_prompt)) {
            $prompt_to_use = $this->template_processor->process($template->title_prompt, $topic);
        }

        return $this->build_title_prompt_from_raw($prompt_to_use, $voice_title_prompt);
    }
}
