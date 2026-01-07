<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AIPS_Generator
 *
 * Responsible for orchestrating the AI content generation pipeline:
 * - Building prompts
 * - Calling the AI service for text and images
 * - Creating posts and updating history
 * - Tracking a per-request generation session for observability
 */
class AIPS_Generator {
    
    private $ai_service;
    private $logger;
    
    /**
     * @var AIPS_Generation_Session Current generation session tracker.
     *
     * This tracks runtime details of a single post generation attempt.
     * It is ephemeral (exists only during the current request) and is
     * serialized to JSON for storage in the History database table.
     */
    private $current_session;
    
    private $template_processor;
    private $image_service;
    private $structure_manager;
    private $post_creator;
    private $history_repository;
    private $prompt_builder;
    
    /**
     * Constructor.
     *
     * Accepts dependencies for easier testing; falls back to concrete
     * implementations when not provided.
     *
     * @param object|null $logger
     * @param object|null $ai_service
     * @param object|null $template_processor
     * @param object|null $image_service
     * @param object|null $structure_manager
     * @param object|null $post_creator
     * @param object|null $history_repository
     * @param object|null $prompt_builder
     */
    public function __construct(
        $logger = null,
        $ai_service = null,
        $template_processor = null,
        $image_service = null,
        $structure_manager = null,
        $post_creator = null,
        $history_repository = null,
        $prompt_builder = null
    ) {
        $this->logger = $logger ?: new AIPS_Logger();
        $this->ai_service = $ai_service ?: new AIPS_AI_Service();
        $this->template_processor = $template_processor ?: new AIPS_Template_Processor();
        $this->image_service = $image_service ?: new AIPS_Image_Service($this->ai_service);
        $this->structure_manager = $structure_manager ?: new AIPS_Article_Structure_Manager();
        $this->post_creator = $post_creator ?: new AIPS_Post_Creator();
        $this->history_repository = $history_repository ?: new AIPS_History_Repository();
        $this->prompt_builder = $prompt_builder ?: new AIPS_Prompt_Builder($this->template_processor, $this->structure_manager);

        // Initialize session tracker
        $this->current_session = new AIPS_Generation_Session();
    }
    
    /**
     * Log an AI call to the current generation session.
     *
     * @param string      $type     Type of AI call (e.g., 'title', 'content', 'excerpt', 'featured_image').
     * @param string      $prompt   The prompt sent to AI.
     * @param string|null $response The AI response, if successful.
     * @param array       $options  Options used for the call.
     * @param string|null $error    Error message, if call failed.
     * @return void
     */
    private function log_ai_call($type, $prompt, $response, $options = array(), $error = null) {
        $this->current_session->log_ai_call($type, $prompt, $response, $options, $error);
    }

    /**
     * Log a message with optional AI data to both the logger and the session.
     *
     * @param string $message Message to log.
     * @param string $level   Log level (info, error, warning).
     * @param array  $ai_data Optional AI call data to log.
     * @param array  $context Optional context data.
     * @return void
     */
    private function log($message, $level, $ai_data = array(), $context = array()) {
        $this->logger->log($message, $level, $context);

        if (!empty($ai_data) && isset($ai_data['type']) && isset($ai_data['prompt'])) {
            $type = $ai_data['type'];
            $prompt = $ai_data['prompt'];
            $response = isset($ai_data['response']) ? $ai_data['response'] : null;
            $options = isset($ai_data['options']) ? $ai_data['options'] : array();
            $error = isset($ai_data['error']) ? $ai_data['error'] : null;

            $this->log_ai_call($type, $prompt, $response, $options, $error);
        }
    }
    
    /**
     * Check if AI is available in the configured AI service.
     *
     * @return bool True if AI Engine is available, false otherwise.
     */
    public function is_available() {
        return $this->ai_service->is_available();
    }
    
    /**
     * Generate content using AI.
     *
     * Wrapper method that uses the AI Service to generate text content and
     * records the call in the session log. Returns generated text or WP_Error.
     *
     * @param string $prompt   The prompt to send to AI.
     * @param array  $options  Optional AI generation options.
     * @param string $log_type Optional type label for logging.
     * @return string|WP_Error The generated content or WP_Error on failure.
     */
    public function generate_content($prompt, $options = array(), $log_type = 'content') {
        $result = $this->ai_service->generate_text($prompt, $options);
        
        if (is_wp_error($result)) {
            // Log the error and record the failed AI call in the session
            $this->log($result->get_error_message(), 'error', array(
                'type' => $log_type,
                'prompt' => $prompt,
                'options' => $options,
                'error' => $result->get_error_message()
            ));
        } else {
            // Successful generation: log details and metrics
            $this->log('Content generated successfully', 'info', array(
                'type' => $log_type,
                'prompt' => $prompt,
                'response' => $result,
                'options' => $options
            ), array(
                'prompt_length' => strlen($prompt),
                'response_length' => strlen($result)
            ));
        }
        
        return $result;
    }
    
    /**
     * Generate a title from a prompt. Optionally accept voice-specific title prompt
     * which will be prepended to the prompt that becomes the input to the AI.
     *
     * @param string      $prompt The prompt or context for title generation.
     * @param string|null $voice_title_prompt Optional voice-specific title instructions.
     * @param array       $options AI options (e.g., model, max_tokens override).
     * @return string|WP_Error Generated title string or WP_Error on failure.
     */
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

        // Strip surrounding quotes from AI responses
        $title = preg_replace('/^["\']|["\']$/', '', $title);

        return $title;
    }
    
    /**
     * Generate an excerpt (short summary) for a post.
     *
     * Ensures the excerpt length is within a reasonable limit and removes
     * surrounding quotes from the AI output.
     *
     * @param string $title Title of the generated article.
     * @param string $content The article content to summarize.
     * @param string|null $voice_excerpt_instructions Voice-specific instructions for excerpt.
     * @param array $options AI options.
     * @return string Short excerpt string (max 160 chars). Empty string on failure.
     */
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
            // Return a safe empty excerpt when excerpt generation fails
            return '';
        }
        
        $excerpt = trim($result);
        $excerpt = preg_replace('/^["\']|["\']$/', '', $excerpt);

        return substr($excerpt, 0, 160);
    }
    
    /**
     * Main entry point to generate a post from a template and optional voice/topic.
     *
     * Steps performed:
     * 1. Start a generation session and create a history record
     * 2. Build content prompt and request body content from AI
     * 3. Generate title and excerpt
     * 4. Create the WordPress post and optionally the featured image
     * 5. Complete session, update history, and dispatch hooks
     *
     * @param object $template Template object containing prompts and settings.
     * @param object|null $voice Optional voice object with overrides.
     * @param string|null $topic Optional topic to be injected into prompts.
     * @return int|WP_Error ID of created post or WP_Error on failure.
     */
    public function generate_post($template, $voice = null, $topic = null) {
        // Dispatch post generation started event
        do_action('aips_post_generation_started', $template->id, $topic ? $topic : '');
        
        // Start new generation session - track runtime details
        $this->current_session->start($template, $voice);
        
        // Create initial history record using Repository
        $history_id = $this->history_repository->create(array(
            'template_id' => $template->id,
            'status' => 'processing',
            'prompt' => $template->prompt_template,
        ));
        
        if (!$history_id) {
            // Fallback if repository fails (though unlikely)
            $this->logger->log('Failed to create history record', 'error');
            // Proceeding without history updates but generation may continue.
        }
        
        // Build the full content prompt (template + topic + voice if provided)
        $content_prompt = $this->prompt_builder->build_content_prompt($template, $topic, $voice);
        
        // Ask AI to generate the article body
        $content = $this->generate_content($content_prompt, array(), 'content');
        
        if (is_wp_error($content)) {
            // Complete session with failure result and update history
            $this->current_session->complete(array(
                'success' => false,
                'error' => $content->get_error_message(),
            ));
            
            if ($history_id) {
                $this->history_repository->update($history_id, array(
                    'status' => 'failed',
                    'error_message' => $content->get_error_message(),
                    'generation_log' => $this->current_session->to_json(),
                    'completed_at' => current_time('mysql'),
                ));
            }
            
            // Dispatch post generation failed event
            do_action('aips_post_generation_failed', $template->id, $content->get_error_message(), $topic);
            
            return $content;
        }
        
        $voice_title_prompt = null;

        if ($voice) {
            // Voice may provide a custom title prompt; process template variables
            $voice_title_prompt = $this->template_processor->process($voice->title_prompt, $topic);
        }
        
        // We still need the base processed prompt for title generation if no specific title prompt exists
        $base_processed_prompt = $this->prompt_builder->build_base_content_prompt($template, $topic);

        if ($voice) {
             // Re-append voice instruction to base prompt for context if needed
             $voice_instructions = $this->template_processor->process($voice->content_instructions, $topic);
             $base_processed_prompt = $voice_instructions . "\n\n" . $base_processed_prompt;
        }

        // Determine title source: template-specific title prompt or generated from content/context
        if (!empty($template->title_prompt)) {
            $title_prompt = $this->template_processor->process($template->title_prompt, $topic);
            $title = $this->generate_title($title_prompt, $voice_title_prompt);
        } else {
            $title = $this->generate_title($base_processed_prompt, $voice_title_prompt);
        }
        
        if (is_wp_error($title)) {
            // Fall back to a safe default title when AI fails
            $title = __('AI Generated Post', 'ai-post-scheduler') . ' - ' . date('Y-m-d H:i:s');
        }
        
        // Build voice-aware excerpt instructions and request an excerpt
        $voice_excerpt_instructions = $this->prompt_builder->build_excerpt_instructions($voice, $topic);
        
        $excerpt = $this->generate_excerpt($title, $base_processed_prompt, $voice_excerpt_instructions);
        
        // Use Post Creator Service to save the generated post in WP
        $post_creation_data = array(
            'title' => $title,
            'content' => $content,
            'excerpt' => $excerpt,
            'template' => $template,
            // Provide SEO context for downstream plugins.
            'focus_keyword' => $topic ? $topic : $title,
            'meta_description' => $excerpt,
            'seo_title' => $title,
        );

        // Allow integrations to hook before the post is created.
        do_action('aips_post_generation_before_post_create', $post_creation_data);

        $post_id = $this->post_creator->create_post($post_creation_data);
        
        if (is_wp_error($post_id)) {
            // Complete session with failure result and update history
            $this->current_session->complete(array(
                'success' => false,
                'error' => $post_id->get_error_message(),
                'generated_title' => $title,
                'generated_content' => $content,
                'generated_excerpt' => $excerpt,
            ));
            
            if ($history_id) {
                $this->history_repository->update($history_id, array(
                    'status' => 'failed',
                    'error_message' => $post_id->get_error_message(),
                    'generated_title' => $title,
                    'generated_content' => $content,
                    'generation_log' => $this->current_session->to_json(),
                    'completed_at' => current_time('mysql'),
                ));
            }
            
            return $post_id;
        }
        
        $featured_image_id = null;

        if ($template->generate_featured_image) {
            $featured_image_source = isset($template->featured_image_source) ? $template->featured_image_source : 'ai_prompt';
            $allowed_sources = array('ai_prompt', 'unsplash', 'media_library');

            if (!in_array($featured_image_source, $allowed_sources, true)) {
                $featured_image_source = 'ai_prompt';
            }
            $featured_image_result = null;

            if ($featured_image_source === 'unsplash') {
                $keywords = isset($template->featured_image_unsplash_keywords) ? $template->featured_image_unsplash_keywords : '';
                $processed_keywords = $this->template_processor->process($keywords, $topic);
                $featured_image_result = $this->image_service->fetch_and_upload_unsplash_image($processed_keywords, $title);
            } elseif ($featured_image_source === 'media_library') {
                $featured_image_result = $this->image_service->select_media_library_image(isset($template->featured_image_media_ids) ? $template->featured_image_media_ids : '');
                if (!is_wp_error($featured_image_result)) {
                    $this->post_creator->set_featured_image($post_id, $featured_image_result);
                    $featured_image_id = $featured_image_result;
                }
            } elseif (!empty($template->image_prompt)) {
                $image_prompt = $this->template_processor->process($template->image_prompt, $topic);
                $featured_image_result = $this->image_service->generate_and_upload_featured_image($image_prompt, $title);
                
                if (!is_wp_error($featured_image_result)) {
                    $featured_image_id = $featured_image_result;
                    $this->post_creator->set_featured_image($post_id, $featured_image_id);
                    $this->log_ai_call('featured_image', $image_prompt, $featured_image_id, array());
                }
            } else {
                $featured_image_result = new WP_Error('missing_image_prompt', __('Image prompt is required to generate a featured image.', 'ai-post-scheduler'));
            }

            if (is_wp_error($featured_image_result)) {
                $this->logger->log('Featured image handling failed: ' . $featured_image_result->get_error_message(), 'error');
                $this->current_session->add_error('featured_image', $featured_image_result->get_error_message());
            } elseif ($featured_image_source === 'unsplash' && $featured_image_result) {
                $featured_image_id = $featured_image_result;
                $this->post_creator->set_featured_image($post_id, $featured_image_id);
            }
        }
        
        // Complete session with success result and update history
        $this->current_session->complete(array(
            'success' => true,
            'post_id' => $post_id,
            'generated_title' => $title,
            'generated_content' => $content,
            'generated_excerpt' => $excerpt,
            'featured_image_id' => $featured_image_id,
        ));
        
        if ($history_id) {
            $this->history_repository->update($history_id, array(
                'post_id' => $post_id,
                'status' => 'completed',
                'generated_title' => $title,
                'generated_content' => $content,
                'generation_log' => $this->current_session->to_json(),
                'completed_at' => current_time('mysql'),
            ));
        }
        
        $this->logger->log('Post generated successfully', 'info', array(
            'post_id' => $post_id,
            'template_id' => $template->id,
            'title' => $title
        ));
        
        // Trigger hook for other systems to respond to the new post
        do_action('aips_post_generated', $post_id, $template, $history_id);
        
        return $post_id;
    }
}
