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
    
    public function __construct(
        $logger = null,
        $ai_service = null,
        $template_processor = null,
        $image_service = null,
        $structure_manager = null,
        $post_creator = null,
        $history_repository = null
    ) {
        $this->logger = $logger ?: new AIPS_Logger();
        $this->ai_service = $ai_service ?: new AIPS_AI_Service();
        $this->template_processor = $template_processor ?: new AIPS_Template_Processor();
        $this->image_service = $image_service ?: new AIPS_Image_Service($this->ai_service);
        $this->structure_manager = $structure_manager ?: new AIPS_Article_Structure_Manager();
        $this->post_creator = $post_creator ?: new AIPS_Post_Creator();
        $this->history_repository = $history_repository ?: new AIPS_History_Repository();

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
    
    /**
     * Generate content using AI.
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
            $this->log($result->get_error_message(), 'error', array(
                'type' => $log_type,
                'prompt' => $prompt,
                'options' => $options,
                'error' => $result->get_error_message()
            ));
        } else {
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
    
    public function generate_topics($niche, $count = 10) {
        $prompt = "Generate a list of {$count} unique, engaging blog post titles/topics about '{$niche}'. \n";
        $prompt .= "Return ONLY a valid JSON array of strings. Do not include any other text, markdown formatting, or numbering. \n";
        $prompt .= "Example: [\"Topic 1\", \"Topic 2\", \"Topic 3\"]";

        $result = $this->generate_content($prompt, array('temperature' => 0.7, 'max_tokens' => 1000), 'planner_topics');

        if (is_wp_error($result)) {
            return $result;
        }

        // Clean up the result to ensure it's valid JSON
        $json_str = trim($result);
        // Remove potential markdown code blocks
        $json_str = preg_replace('/^```json/', '', $json_str);
        $json_str = preg_replace('/^```/', '', $json_str);
        $json_str = preg_replace('/```$/', '', $json_str);
        $json_str = trim($json_str);

        $topics = json_decode($json_str);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($topics)) {
            // Fallback: try to parse line by line if JSON fails
            $topics = array_filter(array_map('trim', explode("\n", $json_str)));

            if (empty($topics)) {
                 return new WP_Error('json_parse_error', 'Failed to parse AI response.', array('raw' => $json_str));
            }
        }

        return $topics;
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
        $title = preg_replace('/^["\'"]|["\'"]$/', '', $title);
        
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
        $excerpt = preg_replace('/^["\'"]|["\'"]$/', '', $excerpt);
        
        return substr($excerpt, 0, 160);
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
        
        // NEW: Check if article_structure_id is provided, build prompt with structure
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
        
        $content = $this->generate_content($content_prompt, array(), 'content');
        
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
        
        if (!empty($template->title_prompt)) {
            $title_prompt = $this->template_processor->process($template->title_prompt, $topic);
            $title = $this->generate_title($title_prompt, $voice_title_prompt);
        } else {
            $title = $this->generate_title($processed_prompt, $voice_title_prompt);
        }
        
        if (is_wp_error($title)) {
            $title = __('AI Generated Post', 'ai-post-scheduler') . ' - ' . date('Y-m-d H:i:s');
        }
        
        $voice_excerpt_instructions = null;
        if ($voice && !empty($voice->excerpt_instructions)) {
            $voice_excerpt_instructions = $this->template_processor->process($voice->excerpt_instructions, $topic);
        }
        
        $excerpt = $this->generate_excerpt($title, $processed_prompt, $voice_excerpt_instructions);
        
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
