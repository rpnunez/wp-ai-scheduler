<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Generator {
    
    private $ai_service;
    private $logger;
    
    /**
     * @var AIPS_Generation_Session Current generation session tracker.
     * 
     * This tracks runtime details of a single post generation attempt.
     * It is ephemeral (exists only during the current request) and is
     * serialized to JSON for storage in the History database table.
     * 
     * Key Distinction:
     * - Generation Session: Runtime tracking (this property)
     * - History: Persistent database records (managed by AIPS_History_Repository)
     * 
     * The session is saved as the `generation_log` JSON field in History records.
     * 
     * Note: The session is automatically reset at the start of each generate_post()
     * call via the start() method, so it's safe to reuse the same Generator instance
     * for multiple post generations.
     */
    private $current_session;
    
    private $template_processor;
    private $image_service;
    private $structure_manager;
    private $post_creator;
    private $history_repository;
    private $prompt_builder;
    private $content_generator;
    
    public function __construct(
        $logger = null,
        $ai_service = null,
        $template_processor = null,
        $image_service = null,
        $structure_manager = null,
        $post_creator = null,
        $history_repository = null,
        $prompt_builder = null,
        $content_generator = null
    ) {
        $this->logger = $logger ?: new AIPS_Logger();
        $this->ai_service = $ai_service ?: new AIPS_AI_Service();
        $this->template_processor = $template_processor ?: new AIPS_Template_Processor();
        $this->image_service = $image_service ?: new AIPS_Image_Service($this->ai_service);
        $this->structure_manager = $structure_manager ?: new AIPS_Article_Structure_Manager();
        $this->post_creator = $post_creator ?: new AIPS_Post_Creator();
        $this->history_repository = $history_repository ?: new AIPS_History_Repository();
        $this->prompt_builder = $prompt_builder ?: new AIPS_Prompt_Builder($this->template_processor, $this->structure_manager);

        // Atlas: Use Content Generator for text generation operations
        $this->content_generator = $content_generator ?: new AIPS_Content_Generator($this->ai_service, $this->logger, $this->prompt_builder);

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
     */
    private function log_ai_call($type, $prompt, $response, $options = array(), $error = null) {
        $this->current_session->log_ai_call($type, $prompt, $response, $options, $error);
    }

    /**
     * Log a message with optional AI data.
     *
     * @param string $message Message to log.
     * @param string $level   Log level (info, error, warning).
     * @param array  $ai_data Optional AI call data to log.
     * @param array  $context Optional context data.
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
     * Check if AI is available.
     *
     * @return bool True if AI Engine is available, false otherwise.
     */
    public function is_available() {
        return $this->ai_service->is_available();
    }
    
    public function generate_post($template, $voice = null, $topic = null) {
        
        // Dispatch post generation started event
        do_action('aips_post_generation_started', array(
            'template_id' => $template->id,
            'topic' => $topic ? $topic : '',
            'timestamp' => current_time('mysql'),
        ), 'post_generation');
        
        // Start new generation session
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
            // We should probably stop here, but let's try to continue or just return error.
            // For now, let's proceed but we won't be able to update history.
        }
        
        $content_prompt = $this->prompt_builder->build_content_prompt($template, $topic, $voice);
        
        $content = $this->content_generator->generate_content($content_prompt, array(), 'content');
        
        if (is_wp_error($content)) {
            // Complete session with failure result
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
            do_action('aips_post_generation_failed', array(
                'template_id' => $template->id,
                'error_code' => $content->get_error_code(),
                'error_message' => $content->get_error_message(),
                'metadata' => array(
                    'history_id' => $history_id,
                    'topic' => $topic,
                ),
                'timestamp' => current_time('mysql'),
            ), 'post_generation');
            
            return $content;
        }
        
        $voice_title_prompt = null;
        if ($voice) {
            $voice_title_prompt = $this->template_processor->process($voice->title_prompt, $topic);
        }
        
        // We still need the base processed prompt for title generation if no specific title prompt exists
        $base_processed_prompt = $this->prompt_builder->build_base_content_prompt($template, $topic);
        if ($voice) {
             // Re-append voice instruction to base prompt for context if needed,
             // but generate_title usually just takes the "context" as the prompt argument if no title prompt.
             // Original logic: $title = $this->generate_title($processed_prompt, $voice_title_prompt);
             // $processed_prompt included voice instructions in original logic?
             // Yes: $processed_prompt = $voice_instructions . "\n\n" . $processed_prompt;

             $voice_instructions = $this->template_processor->process($voice->content_instructions, $topic);
             $base_processed_prompt = $voice_instructions . "\n\n" . $base_processed_prompt;
        }

        if (!empty($template->title_prompt)) {
            $title_prompt = $this->template_processor->process($template->title_prompt, $topic);
            $title = $this->content_generator->generate_title($title_prompt, $voice_title_prompt);
        } else {
            $title = $this->content_generator->generate_title($base_processed_prompt, $voice_title_prompt);
        }
        
        if (is_wp_error($title)) {
            $title = __('AI Generated Post', 'ai-post-scheduler') . ' - ' . date('Y-m-d H:i:s');
        }
        
        $voice_excerpt_instructions = $this->prompt_builder->build_excerpt_instructions($voice, $topic);
        
        $excerpt = $this->content_generator->generate_excerpt($title, $base_processed_prompt, $voice_excerpt_instructions);
        
        // Use Post Creator Service
        $post_creation_data = array(
            'title' => $title,
            'content' => $content,
            'excerpt' => $excerpt,
            'template' => $template,
        );

        $post_id = $this->post_creator->create_post($post_creation_data);
        
        if (is_wp_error($post_id)) {
            // Complete session with failure result
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
        if ($template->generate_featured_image && !empty($template->image_prompt)) {
            $image_prompt = $this->template_processor->process($template->image_prompt, $topic);
            $featured_image_result = $this->image_service->generate_and_upload_featured_image($image_prompt, $title);
            
            if (!is_wp_error($featured_image_result)) {
                $featured_image_id = $featured_image_result;
                $this->post_creator->set_featured_image($post_id, $featured_image_id);
                $this->log_ai_call('featured_image', $image_prompt, $featured_image_id, array());
            } else {
                $this->logger->log('Featured image generation failed: ' . $featured_image_result->get_error_message(), 'error');
                $this->current_session->add_error('featured_image', $featured_image_result->get_error_message());
            }
        }
        
        // Complete session with success result
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
        
        // Dispatch post generation completed event
        do_action('aips_post_generation_completed', array(
            'template_id' => $template->id,
            'post_id' => $post_id,
            'metadata' => array(
                'history_id' => $history_id,
                'topic' => $topic,
                'title' => $title,
                'featured_image_id' => $featured_image_id,
            ),
            'timestamp' => current_time('mysql'),
        ), 'post_generation');
        
        do_action('aips_post_generated', $post_id, $template, $history_id);
        
        return $post_id;
    }
}
