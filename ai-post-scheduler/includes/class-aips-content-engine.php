<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Content_Engine
 *
 * Handles the generation of post content (title, body, excerpt, image)
 * by orchestrating the AI Service, Template Processor, and other helpers.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Content_Engine {

    private $ai_service;
    private $image_service;
    private $template_processor;
    private $structure_manager;
    private $prompt_builder;
    private $logger;

    public function __construct(
        $ai_service,
        $image_service,
        $template_processor,
        $structure_manager,
        $prompt_builder,
        $logger
    ) {
        $this->ai_service = $ai_service;
        $this->image_service = $image_service;
        $this->template_processor = $template_processor;
        $this->structure_manager = $structure_manager;
        $this->prompt_builder = $prompt_builder;
        $this->logger = $logger;
    }

    /**
     * Generate all content components for a post.
     *
     * @param object      $template The template object.
     * @param object|null $voice    The voice object (optional).
     * @param string|null $topic    The topic string.
     * @return array Array containing post data and execution logs, or WP_Error.
     */
    public function generate($template, $voice = null, $topic = null) {
        $result_data = array(
            'title' => '',
            'content' => '',
            'excerpt' => '',
            'featured_image_id' => null,
            'ai_calls' => array(),
            'errors' => array(),
        );

        // --- 1. Generate Content ---

        // Determine Prompt Structure
        $article_structure_id = isset($template->article_structure_id) ? $template->article_structure_id : null;
        $processed_prompt = '';

        if ($article_structure_id) {
            $processed_prompt = $this->structure_manager->build_prompt($article_structure_id, $topic);
            if (is_wp_error($processed_prompt)) {
                // Fallback
                $processed_prompt = $this->template_processor->process($template->prompt_template, $topic);
            }
        } else {
            $processed_prompt = $this->template_processor->process($template->prompt_template, $topic);
        }

        $voice_instructions = null;
        if ($voice) {
            $voice_instructions = $this->template_processor->process($voice->content_instructions, $topic);
        }

        // Build prompt
        $base_prompt = $this->prompt_builder->build_base_content_prompt($processed_prompt, $voice_instructions);
        $final_content_prompt = $this->prompt_builder->add_formatting_instructions($base_prompt);

        // Execute AI
        $content_result = $this->generate_text($final_content_prompt, array(), 'content');

        if (is_wp_error($content_result)) {
            $result_data['errors'][] = $content_result->get_error_message();
            return new WP_Error('content_generation_failed', $content_result->get_error_message(), $result_data);
        }

        $result_data['content'] = $content_result['text'];
        $result_data['ai_calls'][] = $content_result['log'];

        // --- 2. Generate Title ---

        $voice_title_prompt = null;
        if ($voice) {
            $voice_title_prompt = $this->template_processor->process($voice->title_prompt, $topic);
        }

        if (!empty($template->title_prompt)) {
            $title_prompt_base = $this->template_processor->process($template->title_prompt, $topic);
            // If template has specific title prompt, we use that as the base "topic/input" for the builder
            // Wait, logic in Generator was: if template->title_prompt exists, process it and use it.
            // If NOT, use the processed_prompt (content prompt) as the source.
            // AND apply voice if present.
            $title_source = $title_prompt_base;
        } else {
            $title_source = $processed_prompt;
        }

        $title_prompt = $this->prompt_builder->build_title_prompt($title_source, $voice_title_prompt);

        $title_result = $this->generate_text($title_prompt, array('max_tokens' => 100), 'title');

        if (is_wp_error($title_result)) {
            // Non-fatal error, use fallback
            $result_data['title'] = __('AI Generated Post', 'ai-post-scheduler') . ' - ' . date('Y-m-d H:i:s');
            $result_data['errors'][] = $title_result->get_error_message();
        } else {
            $raw_title = trim($title_result['text']);
            $result_data['title'] = preg_replace('/^["\'"]|["\'"]$/', '', $raw_title);
            $result_data['ai_calls'][] = $title_result['log'];
        }

        // --- 3. Generate Excerpt ---

        $voice_excerpt_instructions = null;
        if ($voice && !empty($voice->excerpt_instructions)) {
            $voice_excerpt_instructions = $this->template_processor->process($voice->excerpt_instructions, $topic);
        }

        $excerpt_prompt = $this->prompt_builder->build_excerpt_prompt(
            $result_data['title'],
            $processed_prompt, // Using the prompt as context, similar to original code
            $voice_excerpt_instructions
        );

        $excerpt_result = $this->generate_text($excerpt_prompt, array('max_tokens' => 150), 'excerpt');

        if (is_wp_error($excerpt_result)) {
             $result_data['excerpt'] = '';
             // Non-fatal
        } else {
            $raw_excerpt = trim($excerpt_result['text']);
            $cleaned_excerpt = preg_replace('/^["\'"]|["\'"]$/', '', $raw_excerpt);
            $result_data['excerpt'] = mb_substr($cleaned_excerpt, 0, 160);
            $result_data['ai_calls'][] = $excerpt_result['log'];
        }

        // --- 4. Generate Featured Image ---

        if ($template->generate_featured_image && !empty($template->image_prompt)) {
            $image_prompt = $this->template_processor->process($template->image_prompt, $topic);
            $featured_image_result = $this->image_service->generate_and_upload_featured_image($image_prompt, $result_data['title']);

            if (!is_wp_error($featured_image_result)) {
                $result_data['featured_image_id'] = $featured_image_result;
                $result_data['ai_calls'][] = array(
                    'type' => 'featured_image',
                    'timestamp' => current_time('mysql'),
                    'request' => array('prompt' => $image_prompt, 'options' => array()),
                    'response' => array('success' => true, 'content' => $featured_image_result, 'error' => null)
                );
            } else {
                $this->logger->log('Featured image generation failed: ' . $featured_image_result->get_error_message(), 'error');
                $result_data['errors'][] = array(
                    'type' => 'featured_image',
                    'timestamp' => current_time('mysql'),
                    'message' => $featured_image_result->get_error_message(),
                );
            }
        }

        return $result_data;
    }

    /**
     * Helper to generate text and format the log entry.
     */
    public function generate_text($prompt, $options, $log_type) {
        $result = $this->ai_service->generate_text($prompt, $options);

        if (is_wp_error($result)) {
             $this->logger->log($result->get_error_message(), 'error', array('type' => $log_type));
             return $result;
        }

        $log_entry = array(
            'type' => $log_type,
            'timestamp' => current_time('mysql'),
            'request' => array(
                'prompt' => $prompt,
                'options' => $options,
            ),
            'response' => array(
                'success' => true,
                'content' => $result,
                'error' => null,
            ),
        );

        $this->logger->log('Content generated successfully', 'info', array('type' => $log_type));

        return array('text' => $result, 'log' => $log_entry);
    }

    // Public wrappers for backward compatibility / direct use
    public function generate_title_text($prompt, $voice_title_prompt = null, $options = array()) {
        $title_prompt = $this->prompt_builder->build_title_prompt($prompt, $voice_title_prompt);
        $options['max_tokens'] = 100;
        $result = $this->ai_service->generate_text($title_prompt, $options);

        if (is_wp_error($result)) { return $result; }

        $title = trim($result);
        return preg_replace('/^["\'"]|["\'"]$/', '', $title);
    }

    public function generate_excerpt_text($title, $content, $voice_excerpt_instructions = null, $options = array()) {
        $excerpt_prompt = $this->prompt_builder->build_excerpt_prompt($title, $content, $voice_excerpt_instructions);
        $options['max_tokens'] = 150;
        $result = $this->ai_service->generate_text($excerpt_prompt, $options);

        if (is_wp_error($result)) { return ''; }

        $excerpt = trim($result);
        $excerpt = preg_replace('/^["\'"]|["\'"]$/', '', $excerpt);
        return substr($excerpt, 0, 160);
    }

    public function generate_content_text($prompt, $options = array()) {
        return $this->ai_service->generate_text($prompt, $options);
    }
}
