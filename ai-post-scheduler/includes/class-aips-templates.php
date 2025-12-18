<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Templates {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aips_templates';
        
        add_action('wp_ajax_aips_save_template', array($this, 'ajax_save_template'));
        add_action('wp_ajax_aips_delete_template', array($this, 'ajax_delete_template'));
        add_action('wp_ajax_aips_get_template', array($this, 'ajax_get_template'));
        add_action('wp_ajax_aips_test_template', array($this, 'ajax_test_template'));
    }
    
    public function get_all($active_only = false) {
        global $wpdb;
        
        $where = $active_only ? "WHERE is_active = 1" : "";
        return $wpdb->get_results("SELECT * FROM {$this->table_name} $where ORDER BY name ASC");
    }
    
    public function get($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id));
    }
    
    public function save($data) {
        global $wpdb;
        
        $template_data = array(
            'name' => sanitize_text_field($data['name']),
            'prompt_template' => wp_kses_post($data['prompt_template']),
            'title_prompt' => isset($data['title_prompt']) ? sanitize_text_field($data['title_prompt']) : '',
            'voice_id' => isset($data['voice_id']) ? absint($data['voice_id']) : NULL,
            'post_quantity' => isset($data['post_quantity']) ? absint($data['post_quantity']) : 1,
            'image_prompt' => isset($data['image_prompt']) ? wp_kses_post($data['image_prompt']) : '',
            'generate_featured_image' => isset($data['generate_featured_image']) ? 1 : 0,
            'post_status' => sanitize_text_field($data['post_status']),
            'post_category' => absint($data['post_category']),
            'post_tags' => isset($data['post_tags']) ? sanitize_text_field($data['post_tags']) : '',
            'post_author' => isset($data['post_author']) ? absint($data['post_author']) : get_current_user_id(),
            'is_active' => isset($data['is_active']) ? 1 : 0,
        );
        
        if (!empty($data['id'])) {
            $wpdb->update(
                $this->table_name,
                $template_data,
                array('id' => absint($data['id'])),
                array('%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%d', '%s', '%d', '%d'),
                array('%d')
            );
            return absint($data['id']);
        } else {
            $wpdb->insert(
                $this->table_name,
                $template_data,
                array('%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%d', '%s', '%d', '%d')
            );
            return $wpdb->insert_id;
        }
    }
    
    public function delete($id) {
        global $wpdb;
        return $wpdb->delete($this->table_name, array('id' => $id), array('%d'));
    }
    
    public function ajax_save_template() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }
        
        $data = array(
            'id' => isset($_POST['template_id']) ? absint($_POST['template_id']) : 0,
            'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '',
            'prompt_template' => isset($_POST['prompt_template']) ? wp_kses_post($_POST['prompt_template']) : '',
            'title_prompt' => isset($_POST['title_prompt']) ? sanitize_text_field($_POST['title_prompt']) : '',
            'voice_id' => isset($_POST['voice_id']) ? absint($_POST['voice_id']) : 0,
            'post_quantity' => isset($_POST['post_quantity']) ? absint($_POST['post_quantity']) : 1,
            'image_prompt' => isset($_POST['image_prompt']) ? wp_kses_post($_POST['image_prompt']) : '',
            'generate_featured_image' => isset($_POST['generate_featured_image']) ? 1 : 0,
            'post_status' => isset($_POST['post_status']) ? sanitize_text_field($_POST['post_status']) : 'draft',
            'post_category' => isset($_POST['post_category']) ? absint($_POST['post_category']) : 0,
            'post_tags' => isset($_POST['post_tags']) ? sanitize_text_field($_POST['post_tags']) : '',
            'post_author' => isset($_POST['post_author']) ? absint($_POST['post_author']) : get_current_user_id(),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        );
        
        if (empty($data['name']) || empty($data['prompt_template'])) {
            wp_send_json_error(array('message' => __('Name and prompt template are required.', 'ai-post-scheduler')));
        }
        
        if ($data['post_quantity'] < 1 || $data['post_quantity'] > 20) {
            $data['post_quantity'] = 1;
        }
        
        $id = $this->save($data);
        
        if ($id) {
            wp_send_json_success(array(
                'message' => __('Template saved successfully.', 'ai-post-scheduler'),
                'template_id' => $id
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to save template.', 'ai-post-scheduler')));
        }
    }
    
    public function ajax_delete_template() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }
        
        $id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
        
        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid template ID.', 'ai-post-scheduler')));
        }
        
        if ($this->delete($id)) {
            wp_send_json_success(array('message' => __('Template deleted successfully.', 'ai-post-scheduler')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete template.', 'ai-post-scheduler')));
        }
    }
    
    public function ajax_get_template() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }
        
        $id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
        
        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid template ID.', 'ai-post-scheduler')));
        }
        
        $template = $this->get($id);
        
        if ($template) {
            wp_send_json_success(array('template' => $template));
        } else {
            wp_send_json_error(array('message' => __('Template not found.', 'ai-post-scheduler')));
        }
    }
    
    public function ajax_test_template() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }
        
        $prompt = isset($_POST['prompt_template']) ? wp_kses_post($_POST['prompt_template']) : '';
        
        if (empty($prompt)) {
            wp_send_json_error(array('message' => __('Prompt template is required.', 'ai-post-scheduler')));
        }
        
        $generator = new AIPS_Generator();
        $result = $generator->generate_content($prompt);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array(
            'content' => $result,
            'message' => __('Test generation successful.', 'ai-post-scheduler')
        ));
    }
    
    public function render_page() {
        $templates = $this->get_all();
        $categories = get_categories(array('hide_empty' => false));
        $users = get_users(array('role__in' => array('administrator', 'editor', 'author')));
        
        include AIPS_PLUGIN_DIR . 'templates/admin/templates.php';
    }
}
