<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Generator {
    
    private $ai_engine;
    private $logger;
    private $generation_log;
    
    public function __construct() {
        $this->logger = new AIPS_Logger();
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
    
    private function log_ai_call($type, $prompt, $response, $options = array(), $error = null) {
        $call_log = array(
            'type' => $type,
            'timestamp' => current_time('mysql'),
            'request' => array(
                'prompt' => $prompt,
                'options' => $options,
            ),
            'response' => array(
                'success' => $error === null,
                'content' => $response,
                'error' => $error,
            ),
        );
        
        $this->generation_log['ai_calls'][] = $call_log;
        
        if ($error) {
            $this->generation_log['errors'][] = array(
                'type' => $type,
                'timestamp' => current_time('mysql'),
                'message' => $error,
            );
        }
    }

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
    
    private function get_ai_engine() {
        if ($this->ai_engine === null) {
            if (class_exists('Meow_MWAI_Core')) {
                global $mwai_core;
                $this->ai_engine = $mwai_core;
            }
        }
        return $this->ai_engine;
    }
    
    public function is_available() {
        return $this->get_ai_engine() !== null;
    }
    
    public function generate_content($prompt, $options = array(), $log_type = 'content') {
        $ai = $this->get_ai_engine();
        
        if (!$ai) {
            $error_msg = 'AI Engine not available';
            $this->log($error_msg, 'error', array(
                'type' => $log_type,
                'prompt' => $prompt,
                'options' => $options,
                'error' => $error_msg
            ));
            return new WP_Error('ai_unavailable', __('AI Engine plugin is not available.', 'ai-post-scheduler'));
        }
        
        $model = get_option('aips_ai_model', '');
        
        $default_options = array(
            'model' => $model,
            'max_tokens' => 2000,
            'temperature' => 0.7,
        );
        
        $options = wp_parse_args($options, $default_options);
        
        try {
            $query = new Meow_MWAI_Query_Text($prompt);
            
            if (!empty($options['model'])) {
                $query->set_model($options['model']);
            }
            
            if (isset($options['max_tokens'])) {
                $query->set_max_tokens($options['max_tokens']);
            }
            
            if (isset($options['temperature'])) {
                $query->set_temperature($options['temperature']);
            }
            
            $response = $ai->run_query($query);
            
            if ($response && !empty($response->result)) {
                $this->log('Content generated successfully', 'info', array(
                    'type' => $log_type,
                    'prompt' => $prompt,
                    'response' => $response->result,
                    'options' => $options
                ), array(
                    'prompt_length' => strlen($prompt),
                    'response_length' => strlen($response->result)
                ));
                return $response->result;
            }
            
            $error_msg = 'Empty response from AI Engine';
            $this->log($error_msg, 'error', array(
                'type' => $log_type,
                'prompt' => $prompt,
                'options' => $options,
                'error' => $error_msg
            ));
            return new WP_Error('empty_response', __('AI Engine returned an empty response.', 'ai-post-scheduler'));
            
        } catch (Exception $e) {
            $error_msg = $e->getMessage();
            $this->log('AI generation failed: ' . $error_msg, 'error', array(
                'type' => $log_type,
                'prompt' => $prompt,
                'options' => $options,
                'error' => $error_msg
            ));
            return new WP_Error('generation_failed', $error_msg);
        }
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
        global $wpdb;
        
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
        
        $history_table = $wpdb->prefix . 'aips_history';
        
        $history_id = $wpdb->insert(
            $history_table,
            array(
                'template_id' => $template->id,
                'status' => 'processing',
                'prompt' => $template->prompt_template,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s')
        );
        
        $history_id = $wpdb->insert_id;
        
        $processed_prompt = $this->process_template_variables($template->prompt_template, $topic);
        
        if ($voice) {
            $voice_instructions = $this->process_template_variables($voice->content_instructions, $topic);
            $processed_prompt = $voice_instructions . "\n\n" . $processed_prompt;
        }
        
        $content_prompt = $processed_prompt . "\n\nOutput the response for use as a WordPress post with HTML tags, using <h2> for section titles, <pre> tags for code samples. Be sure to end the post with a concise summary.";
        
        $content = $this->generate_content($content_prompt, array(), 'content');
        
        if (is_wp_error($content)) {
            $this->generation_log['completed_at'] = current_time('mysql');
            $this->generation_log['result'] = array(
                'success' => false,
                'error' => $content->get_error_message(),
            );
            
            $wpdb->update(
                $history_table,
                array(
                    'status' => 'failed',
                    'error_message' => $content->get_error_message(),
                    'generation_log' => wp_json_encode($this->generation_log),
                    'completed_at' => current_time('mysql'),
                ),
                array('id' => $history_id),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );
            
            return $content;
        }
        
        $voice_title_prompt = null;
        if ($voice) {
            $voice_title_prompt = $this->process_template_variables($voice->title_prompt, $topic);
        }
        
        if (!empty($template->title_prompt)) {
            $title_prompt = $this->process_template_variables($template->title_prompt, $topic);
            $title = $this->generate_title($title_prompt, $voice_title_prompt);
        } else {
            $title = $this->generate_title($processed_prompt, $voice_title_prompt);
        }
        
        if (is_wp_error($title)) {
            $title = __('AI Generated Post', 'ai-post-scheduler') . ' - ' . date('Y-m-d H:i:s');
        }
        
        $voice_excerpt_instructions = null;
        if ($voice && !empty($voice->excerpt_instructions)) {
            $voice_excerpt_instructions = $this->process_template_variables($voice->excerpt_instructions, $topic);
        }
        
        $excerpt = $this->generate_excerpt($title, $processed_prompt, $voice_excerpt_instructions);
        
        $post_data = array(
            'post_title' => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status' => $template->post_status ?: get_option('aips_default_post_status', 'draft'),
            'post_author' => $template->post_author ?: get_current_user_id(),
            'post_type' => 'post',
        );
        
        if (!empty($template->post_category)) {
            $post_data['post_category'] = array($template->post_category);
        } elseif ($default_cat = get_option('aips_default_category')) {
            $post_data['post_category'] = array($default_cat);
        }
        
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            $this->generation_log['completed_at'] = current_time('mysql');
            $this->generation_log['result'] = array(
                'success' => false,
                'error' => $post_id->get_error_message(),
                'generated_title' => $title,
                'generated_content' => $content,
                'generated_excerpt' => $excerpt,
            );
            
            $wpdb->update(
                $history_table,
                array(
                    'status' => 'failed',
                    'error_message' => $post_id->get_error_message(),
                    'generated_title' => $title,
                    'generated_content' => $content,
                    'generation_log' => wp_json_encode($this->generation_log),
                    'completed_at' => current_time('mysql'),
                ),
                array('id' => $history_id),
                array('%s', '%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );
            
            return $post_id;
        }
        
        if (!empty($template->post_tags)) {
            $tags = array_map('trim', explode(',', $template->post_tags));
            wp_set_post_tags($post_id, $tags);
        }
        
        $featured_image_id = null;
        if ($template->generate_featured_image && !empty($template->image_prompt)) {
            $image_prompt = $this->process_template_variables($template->image_prompt, $topic);
            $featured_image_id = $this->generate_and_upload_featured_image($image_prompt, $title);
            
            if ($featured_image_id) {
                set_post_thumbnail($post_id, $featured_image_id);
            }
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
        
        $wpdb->update(
            $history_table,
            array(
                'post_id' => $post_id,
                'status' => 'completed',
                'generated_title' => $title,
                'generated_content' => $content,
                'generation_log' => wp_json_encode($this->generation_log),
                'completed_at' => current_time('mysql'),
            ),
            array('id' => $history_id),
            array('%d', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        $this->logger->log('Post generated successfully', 'info', array(
            'post_id' => $post_id,
            'template_id' => $template->id,
            'title' => $title
        ));
        
        do_action('aips_post_generated', $post_id, $template, $history_id);
        
        return $post_id;
    }
    
    private function process_template_variables($template, $topic = null) {
        $variables = array(
            '{{date}}' => date('F j, Y'),
            '{{year}}' => date('Y'),
            '{{month}}' => date('F'),
            '{{day}}' => date('l'),
            '{{time}}' => current_time('H:i'),
            '{{site_name}}' => get_bloginfo('name'),
            '{{site_description}}' => get_bloginfo('description'),
            '{{random_number}}' => rand(1, 1000),
            '{{topic}}' => $topic ? $topic : '',
            '{{title}}' => $topic ? $topic : '', // Alias for topic if user prefers
        );
        
        $variables = apply_filters('aips_template_variables', $variables);
        
        return str_replace(array_keys($variables), array_values($variables), $template);
    }
    
    private function generate_and_upload_featured_image($image_prompt, $post_title) {
        $ai = $this->get_ai_engine();
        
        if (!$ai) {
            $error_msg = 'AI Engine not available for image generation';
            $this->log($error_msg, 'error', array(
                'type' => 'featured_image',
                'prompt' => $image_prompt,
                'options' => array(),
                'error' => $error_msg
            ));
            return false;
        }
        
        try {
            $query = new Meow_MWAI_Query_Image($image_prompt);
            $response = $ai->run_query($query);
            
            if (!$response || empty($response->result)) {
                $error_msg = 'Empty response from AI Engine for image generation';
                $this->log($error_msg, 'error', array(
                    'type' => 'featured_image',
                    'prompt' => $image_prompt,
                    'options' => array(),
                    'error' => $error_msg
                ));
                return false;
            }
            
            $image_url = $response->result;
            
            if (is_array($image_url) && !empty($image_url[0])) {
                $image_url = $image_url[0];
            }
            
            $this->log_ai_call('featured_image', $image_prompt, $image_url, array());
            
            if (empty($image_url)) {
                $error_msg = 'No image URL in AI response';
                $this->logger->log($error_msg, 'error');
                return false;
            }
            
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            
            // SECURITY FIX: Use wp_safe_remote_get to prevent SSRF
            $response_object = wp_safe_remote_get($image_url);

            // Check response code and content type
            if (is_wp_error($response_object)) {
                $error_msg = 'Failed to fetch image: ' . $response_object->get_error_message();
                $this->logger->log($error_msg, 'error');
                $this->generation_log['errors'][] = array(
                    'type' => 'image_download',
                    'timestamp' => current_time('mysql'),
                    'message' => $error_msg,
                );
                return false;
            }

            $response_code = wp_remote_retrieve_response_code($response_object);
            if ($response_code !== 200) {
                $error_msg = 'Failed to fetch image. HTTP Code: ' . $response_code;
                $this->logger->log($error_msg, 'error');
                $this->generation_log['errors'][] = array(
                    'type' => 'image_download',
                    'timestamp' => current_time('mysql'),
                    'message' => $error_msg,
                );
                return false;
            }

            $content_type = wp_remote_retrieve_header($response_object, 'content-type');
            if (strpos($content_type, 'image/') !== 0) {
                 $error_msg = 'Invalid content type: ' . $content_type;
                 $this->logger->log($error_msg, 'error');
                 $this->generation_log['errors'][] = array(
                    'type' => 'image_content_type',
                    'timestamp' => current_time('mysql'),
                    'message' => $error_msg,
                );
                return false;
            }

            $image_data = wp_remote_retrieve_body($response_object);
            $post_slug = sanitize_title($post_title);
            $filename = $post_slug . '.jpg';
            
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['path'] . '/' . $filename;
            
            if (!file_put_contents($file_path, $image_data)) {
                $error_msg = 'Failed to write image file: ' . $file_path;
                $this->logger->log($error_msg, 'error');
                $this->generation_log['errors'][] = array(
                    'type' => 'image_save',
                    'timestamp' => current_time('mysql'),
                    'message' => $error_msg,
                );
                return false;
            }
            
            $file_type = wp_check_filetype($filename);
            
            $attachment = array(
                'post_mime_type' => $file_type['type'],
                'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
                'post_content' => '',
                'post_status' => 'inherit'
            );
            
            $attachment_id = wp_insert_attachment($attachment, $file_path);
            
            if (is_wp_error($attachment_id)) {
                $error_msg = 'Failed to insert attachment: ' . $attachment_id->get_error_message();
                $this->logger->log($error_msg, 'error');
                $this->generation_log['errors'][] = array(
                    'type' => 'image_attachment',
                    'timestamp' => current_time('mysql'),
                    'message' => $error_msg,
                );
                return false;
            }
            
            $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
            wp_update_attachment_metadata($attachment_id, $attach_data);
            
            $this->logger->log('Featured image generated and uploaded', 'info', array(
                'attachment_id' => $attachment_id,
                'filename' => $filename
            ));
            
            return $attachment_id;
            
        } catch (Exception $e) {
            $error_msg = 'Image generation error: ' . $e->getMessage();
            $this->log($error_msg, 'error', array(
                'type' => 'featured_image',
                'prompt' => $image_prompt,
                'options' => array(),
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
}
