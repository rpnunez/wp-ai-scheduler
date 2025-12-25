<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Prompt_Builder
 *
 * Encapsulates logic for constructing AI prompts for content, titles, and excerpts.
 */
class AIPS_Prompt_Builder {

    /**
     * Build the prompt for generating the article title.
     *
     * @param string $main_prompt The base prompt (topic or specific title prompt).
     * @param string|null $voice_prompt Optional voice instructions for title.
     * @return string The constructed title prompt.
     */
    public function build_title_prompt($main_prompt, $voice_prompt = null) {
        if ($voice_prompt) {
            return $voice_prompt . "\n\n" . $main_prompt;
        }
        return "Generate a compelling blog post title for the following topic. Return only the title, nothing else:\n\n" . $main_prompt;
    }

    /**
     * Build the prompt for generating the article excerpt.
     *
     * @param string $title The article title.
     * @param string $content The article content (or prompt used to generate it).
     * @param string|null $voice_instructions Optional voice instructions for excerpt.
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
     * Build the prompt for generating the article content.
     *
     * @param string $main_prompt The main content prompt (from template or structure).
     * @param string|null $voice_instructions Optional voice instructions for content.
     * @return string The constructed content prompt.
     */
    public function build_content_prompt($main_prompt, $voice_instructions = null) {
        $prompt = $this->build_base_content_prompt($main_prompt, $voice_instructions);

        $prompt .= "\n\nOutput the response for use as a WordPress post with HTML tags, using <h2> for section titles, <pre> tags for code samples. Be sure to end the post with a concise summary.";

        return $prompt;
    }

    /**
     * Build the base content prompt without formatting instructions.
     *
     * @param string $main_prompt
     * @param string|null $voice_instructions
     * @return string
     */
    public function build_base_content_prompt($main_prompt, $voice_instructions = null) {
        $prompt = $main_prompt;

        if ($voice_instructions) {
            $prompt = $voice_instructions . "\n\n" . $prompt;
        }

        return $prompt;
    }
}
