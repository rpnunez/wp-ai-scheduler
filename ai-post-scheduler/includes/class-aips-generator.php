<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Generator {
    
    private $ai_engine;
    private $logger;
    
    public function __construct() {
        $this->logger = new AIPS_Logger();
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
    
    public function generate_content($prompt, $options = array()) {
        $ai = $this->get_ai_engine();
        
        if (!$ai) {
            $this->logger->log('AI Engine not available', 'error');
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
                $this->logger->log('Content generated successfully', 'info', array(
                    'prompt_length' => strlen($prompt),
                    'response_length' => strlen($response->result)
                ));
                return $response->result;
            }
            
            $this->logger->log('Empty response from AI Engine', 'error');
            return new WP_Error('empty_response', __('AI Engine returned an empty response.', 'ai-post-scheduler'));
            
        } catch (Exception $e) {
            $this->logger->log('AI generation failed: ' . $e->getMessage(), 'error');
            return new WP_Error('generation_failed', $e->getMessage());
        }
    }
    
    public function generate_title($prompt, $voice_title_prompt = null, $options = array()) {
        if ($voice_title_prompt) {
            $title_prompt = $voice_title_prompt . "\n\n" . $prompt;
        } else {
            $title_prompt = "Generate a compelling blog post title for the following topic. Return only the title, nothing else:\n\n" . $prompt;
        }
        
        $options['max_tokens'] = 100;
        
        $result = $this->generate_content($title_prompt, $options);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $title = trim($result);
        $title = preg_replace('/^["\'"]|["\'"]$/', '', $title);
        
        return $title;
    }
    
    public function generate_post($template, $voice = null) {
        global $wpdb;
        
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
        
        $processed_prompt = $this->process_template_variables($template->prompt_template);
        
        if ($voice) {
            $voice_instructions = $this->process_template_variables($voice->content_instructions);
            $processed_prompt = $voice_instructions . "\n\n" . $processed_prompt;
        }
        
        $content = $this->generate_content($processed_prompt);
        
        if (is_wp_error($content)) {
            $wpdb->update(
                $history_table,
                array(
                    'status' => 'failed',
                    'error_message' => $content->get_error_message(),
                    'completed_at' => current_time('mysql'),
                ),
                array('id' => $history_id),
                array('%s', '%s', '%s'),
                array('%d')
            );
            
            return $content;
        }
        
        $voice_title_prompt = null;
        if ($voice) {
            $voice_title_prompt = $this->process_template_variables($voice->title_prompt);
        }
        
        if (!empty($template->title_prompt)) {
            $title_prompt = $this->process_template_variables($template->title_prompt);
            $title = $this->generate_title($title_prompt, $voice_title_prompt);
        } else {
            $title = $this->generate_title($processed_prompt, $voice_title_prompt);
        }
        
        if (is_wp_error($title)) {
            $title = __('AI Generated Post', 'ai-post-scheduler') . ' - ' . date('Y-m-d H:i:s');
        }
        
        $post_data = array(
            'post_title' => $title,
            'post_content' => $content,
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
            $wpdb->update(
                $history_table,
                array(
                    'status' => 'failed',
                    'error_message' => $post_id->get_error_message(),
                    'generated_title' => $title,
                    'generated_content' => $content,
                    'completed_at' => current_time('mysql'),
                ),
                array('id' => $history_id),
                array('%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );
            
            return $post_id;
        }
        
        if (!empty($template->post_tags)) {
            $tags = array_map('trim', explode(',', $template->post_tags));
            wp_set_post_tags($post_id, $tags);
        }
        
        $wpdb->update(
            $history_table,
            array(
                'post_id' => $post_id,
                'status' => 'completed',
                'generated_title' => $title,
                'generated_content' => $content,
                'completed_at' => current_time('mysql'),
            ),
            array('id' => $history_id),
            array('%d', '%s', '%s', '%s', '%s'),
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
    
    private function process_template_variables($template) {
        $variables = array(
            '{{date}}' => date('F j, Y'),
            '{{year}}' => date('Y'),
            '{{month}}' => date('F'),
            '{{day}}' => date('l'),
            '{{time}}' => current_time('H:i'),
            '{{site_name}}' => get_bloginfo('name'),
            '{{site_description}}' => get_bloginfo('description'),
            '{{random_number}}' => rand(1, 1000),
        );
        
        $variables = apply_filters('aips_template_variables', $variables);
        
        return str_replace(array_keys($variables), array_values($variables), $template);
    }
}
