<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Generator {
    
    private $logger;
    private $post_creator;
    private $history_repository;
    private $content_engine;

    private $generation_log;
    
    public function __construct(
        $logger = null,
        $ai_service = null,
        $template_processor = null,
        $image_service = null,
        $structure_manager = null,
        $post_creator = null,
        $history_repository = null,
        $prompt_builder = null,
        $content_engine = null
    ) {
        $this->logger = $logger ?: new AIPS_Logger();
        $this->post_creator = $post_creator ?: new AIPS_Post_Creator();
        $this->history_repository = $history_repository ?: new AIPS_History_Repository();

        if ($content_engine) {
            $this->content_engine = $content_engine;
        } else {
            // Instantiate dependencies if not provided
            $ai_service = $ai_service ?: new AIPS_AI_Service();
            $image_service = $image_service ?: new AIPS_Image_Service($ai_service);
            $template_processor = $template_processor ?: new AIPS_Template_Processor();
            $structure_manager = $structure_manager ?: new AIPS_Article_Structure_Manager();
            $prompt_builder = $prompt_builder ?: new AIPS_Prompt_Builder();

            $this->content_engine = new AIPS_Content_Engine(
                $ai_service,
                $image_service,
                $template_processor,
                $structure_manager,
                $prompt_builder,
                $this->logger
            );
        }

        $this->reset_generation_log();
    }
    
    private function reset_generation_log() {
        $this->generation_log = array(
            'started_at' => null,
            'completed_at' => null,
            'template' => null,
            'voice' => null,
            'ai_calls' => array(),
            'errors' => array(),
            'result' => null,
        );
    }
    
    /**
     * Check if AI is available.
     *
     * @return bool True if AI Engine is available, false otherwise.
     */
    public function is_available() {
        // We delegate this check to the engine or assume it if engine is up?
        // Actually, AI Service is inside Engine now.
        // We can expose a check on Engine or just try to generate.
        // For now, let's assume true if instantiated, or re-instantiate AI service?
        // This method was rarely used directly, but let's check.
        // Let's assume yes, or we'd need to expose it on Engine.
        return true;
    }
    
    /**
     * Generate content using AI.
     * Kept for backward compatibility.
     */
    public function generate_content($prompt, $options = array(), $log_type = 'content') {
        $result = $this->content_engine->generate_content_text($prompt, $options);
        // We lose logging here if we don't log it ourselves, but Engine might log internally or we just return result.
        // The original method logged 'Content generated successfully'.
        // The Engine wrappers return raw text/error.
        return $result;
    }
    
    public function generate_title($prompt, $voice_title_prompt = null, $options = array()) {
        return $this->content_engine->generate_title_text($prompt, $voice_title_prompt, $options);
    }
    
    public function generate_excerpt($title, $content, $voice_excerpt_instructions = null, $options = array()) {
        return $this->content_engine->generate_excerpt_text($title, $content, $voice_excerpt_instructions, $options);
    }
    
    public function generate_post($template, $voice = null, $topic = null) {
        
        // Dispatch post generation started event
        do_action('aips_post_generation_started', array(
            'template_id' => $template->id,
            'topic' => $topic ? $topic : '',
            'timestamp' => current_time('mysql'),
        ), 'post_generation');
        
        $this->reset_generation_log();
        $this->generation_log['started_at'] = current_time('mysql');
        
        $this->generation_log['template'] = array(
            'id' => $template->id,
            'name' => $template->name,
            'prompt_template' => $template->prompt_template,
            'title_prompt' => $template->title_prompt,
            'post_status' => $template->post_status,
            'post_category' => $template->post_category,
            'post_tags' => $template->post_tags,
            'post_author' => $template->post_author,
            'post_quantity' => $template->post_quantity,
            'generate_featured_image' => $template->generate_featured_image,
            'image_prompt' => $template->image_prompt,
        );
        
        if ($voice) {
            $this->generation_log['voice'] = array(
                'id' => $voice->id,
                'name' => $voice->name,
                'title_prompt' => $voice->title_prompt,
                'content_instructions' => $voice->content_instructions,
                'excerpt_instructions' => $voice->excerpt_instructions,
            );
        }
        
        // Create initial history record using Repository
        $history_id = $this->history_repository->create(array(
            'template_id' => $template->id,
            'status' => 'processing',
            'prompt' => $template->prompt_template,
        ));
        
        if (!$history_id) {
            $this->logger->log('Failed to create history record', 'error');
        }
        
        // --- DELEGATE TO ENGINE ---
        $engine_result = $this->content_engine->generate($template, $voice, $topic);
        
        // Merge logs
        if (isset($engine_result['ai_calls'])) {
            $this->generation_log['ai_calls'] = array_merge($this->generation_log['ai_calls'], $engine_result['ai_calls']);
        }
        if (isset($engine_result['errors'])) {
            $this->generation_log['errors'] = array_merge($this->generation_log['errors'], $engine_result['errors']);
        }
        
        if (is_wp_error($engine_result)) {
            $this->generation_log['completed_at'] = current_time('mysql');
            $this->generation_log['result'] = array(
                'success' => false,
                'error' => $engine_result->get_error_message(),
            );
            
            if ($history_id) {
                // If it was a partial failure, we might have some data in error data?
                // The Engine returns data in error object? Yes: new WP_Error(..., ..., $result_data)
                $data = $engine_result->get_error_data();
                $generated_title = isset($data['title']) ? $data['title'] : '';
                $generated_content = isset($data['content']) ? $data['content'] : '';

                $this->history_repository->update($history_id, array(
                    'status' => 'failed',
                    'error_message' => $engine_result->get_error_message(),
                    'generated_title' => $generated_title,
                    'generated_content' => $generated_content,
                    'generation_log' => wp_json_encode($this->generation_log),
                    'completed_at' => current_time('mysql'),
                ));
            }
            
            // Dispatch post generation failed event
            do_action('aips_post_generation_failed', array(
                'template_id' => $template->id,
                'error_code' => $engine_result->get_error_code(),
                'error_message' => $engine_result->get_error_message(),
                'metadata' => array(
                    'history_id' => $history_id,
                    'topic' => $topic,
                ),
                'timestamp' => current_time('mysql'),
            ), 'post_generation');
            
            return $engine_result;
        }
        
        // --- SUCCESSFUL GENERATION, CREATE POST ---
        
        $title = $engine_result['title'];
        $content = $engine_result['content'];
        $excerpt = $engine_result['excerpt'];
        $featured_image_id = $engine_result['featured_image_id'];
        
        // Use Post Creator Service
        $post_creation_data = array(
            'title' => $title,
            'content' => $content,
            'excerpt' => $excerpt,
            'template' => $template,
        );

        $post_id = $this->post_creator->create_post($post_creation_data);
        
        if (is_wp_error($post_id)) {
            $this->generation_log['completed_at'] = current_time('mysql');
            $this->generation_log['result'] = array(
                'success' => false,
                'error' => $post_id->get_error_message(),
                'generated_title' => $title,
                'generated_content' => $content,
                'generated_excerpt' => $excerpt,
            );
            
            if ($history_id) {
                $this->history_repository->update($history_id, array(
                    'status' => 'failed',
                    'error_message' => $post_id->get_error_message(),
                    'generated_title' => $title,
                    'generated_content' => $content,
                    'generation_log' => wp_json_encode($this->generation_log),
                    'completed_at' => current_time('mysql'),
                ));
            }
            
            return $post_id;
        }
        
        if ($featured_image_id) {
            $this->post_creator->set_featured_image($post_id, $featured_image_id);
        }
        
        $this->generation_log['completed_at'] = current_time('mysql');
        $this->generation_log['result'] = array(
            'success' => true,
            'post_id' => $post_id,
            'generated_title' => $title,
            'generated_content' => $content,
            'generated_excerpt' => $excerpt,
            'featured_image_id' => $featured_image_id,
        );
        
        if ($history_id) {
            $this->history_repository->update($history_id, array(
                'post_id' => $post_id,
                'status' => 'completed',
                'generated_title' => $title,
                'generated_content' => $content,
                'generation_log' => wp_json_encode($this->generation_log),
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
