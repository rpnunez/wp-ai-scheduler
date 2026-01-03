<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Content_Generator {

    private $ai_service;
    private $logger;
    private $prompt_builder;

    public function __construct($ai_service, $logger, $prompt_builder) {
        $this->ai_service = $ai_service;
        $this->logger = $logger;
        $this->prompt_builder = $prompt_builder;
    }

    /**
     * Generate text content using AI.
     *
     * Wrapper method that uses the AI Service to generate text content.
     *
     * @param string $prompt   The prompt to send to AI.
     * @param array  $options  Optional AI generation options.
     * @param string $log_type Optional type label for logging.
     * @return string|WP_Error The generated content or WP_Error on failure.
     */
    public function generate_content($prompt, $options = array(), $log_type = 'content') {
        $result = $this->ai_service->generate_text($prompt, $options);

        if (is_wp_error($result)) {
            $this->logger->log($result->get_error_message(), 'error', array(
                'type' => $log_type,
                'prompt' => $prompt,
                'options' => $options,
                'error' => $result->get_error_message()
            ));
        } else {
            $this->logger->log('Content generated successfully', 'info', array(
                'type' => $log_type,
                'prompt' => $prompt,
                'response' => $result,
                'options' => $options
            ));
        }

        return $result;
    }

    public function generate_title($prompt, $voice_title_prompt = null, $options = array()) {
        if ($voice_title_prompt) {
            $title_prompt = $voice_title_prompt . "\n\n" . $prompt;
        } else {
            $title_prompt = "Generate a compelling blog post title for the following topic. Return only the title, nothing else:\n\n" . $prompt;
        }

        $options['max_tokens'] = 100;

        $result = $this->generate_content($title_prompt, $options, 'title');

        if (is_wp_error($result)) {
            return $result;
        }

        $title = trim($result);
        $title = preg_replace('/^["\']|["\']$/', '', $title);

        return $title;
    }

    public function generate_excerpt($title, $content, $voice_excerpt_instructions = null, $options = array()) {
        $excerpt_prompt = "Write an excerpt for an article. Must be between 40 and 60 characters. Write naturally as a human would. Output only the excerpt, no formatting.\n\n";

        if ($voice_excerpt_instructions) {
            $excerpt_prompt .= $voice_excerpt_instructions . "\n\n";
        }

        $excerpt_prompt .= "ARTICLE TITLE:\n" . $title . "\n\n";
        $excerpt_prompt .= "ARTICLE BODY:\n" . $content . "\n\n";
        $excerpt_prompt .= "Create a compelling excerpt that captures the essence of the article while considering the context.";

        $options['max_tokens'] = 150;

        $result = $this->generate_content($excerpt_prompt, $options, 'excerpt');

        if (is_wp_error($result)) {
            return '';
        }

        $excerpt = trim($result);
        $excerpt = preg_replace('/^["\']|["\']$/', '', $excerpt);

        return mb_substr($excerpt, 0, 160);
    }
}
