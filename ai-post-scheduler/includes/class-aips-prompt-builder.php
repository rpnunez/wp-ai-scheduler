<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Prompt_Builder
 *
 * Handles the construction and assembly of AI prompts.
 * Extracted from AIPS_Generator to adhere to Single Responsibility Principle.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Prompt_Builder {

    /**
     * Build the title generation prompt.
     *
     * @param string      $topic        The topic to generate a title for.
     * @param string|null $voice_prompt Optional voice instructions for the title.
     * @return string The constructed title prompt.
     */
    public function build_title_prompt($topic, $voice_prompt = null) {
        if ($voice_prompt) {
            return $voice_prompt . "\n\n" . $topic;
        }
        return "Generate a compelling blog post title for the following topic. Return only the title, nothing else:\n\n" . $topic;
    }

    /**
     * Build the excerpt generation prompt.
     *
     * @param string      $title              The article title.
     * @param string      $content            The article body content (or base prompt).
     * @param string|null $voice_instructions Optional voice instructions for the excerpt.
     * @return string The constructed excerpt prompt.
     */
    public function build_excerpt_prompt($title, $content, $voice_instructions = null) {
        $prompt = "Write an excerpt for an article. Must be between 40 and 60 characters. Write naturally as a human would. Output only the excerpt, no formatting.\n\n";

        if ($voice_instructions) {
            $prompt .= $voice_instructions . "\n\n";
        }

        $prompt .= "ARTICLE TITLE:\n" . $title . "\n\n";
        $prompt .= "ARTICLE BODY:\n" . $content . "\n\n";
        $prompt .= "Create a compelling excerpt that captures the essence of the article while considering the context.";

        return $prompt;
    }

    /**
     * Build the base content prompt (merging main prompt with voice instructions).
     *
     * @param string      $main_prompt        The core prompt content (from template or structure).
     * @param string|null $voice_instructions Optional voice content instructions.
     * @return string The merged base prompt.
     */
    public function build_base_content_prompt($main_prompt, $voice_instructions = null) {
        if ($voice_instructions) {
            return $voice_instructions . "\n\n" . $main_prompt;
        }
        return $main_prompt;
    }

    /**
     * Add final formatting instructions to the content prompt.
     *
     * @param string $prompt The base content prompt.
     * @return string The final prompt with formatting instructions.
     */
    public function add_formatting_instructions($prompt) {
        return $prompt . "\n\nOutput the response for use as a WordPress post with HTML tags, using <h2> for section titles, <pre> tags for code samples. Be sure to end the post with a concise summary.";
    }
}
