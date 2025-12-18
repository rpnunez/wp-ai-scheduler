<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Voices {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aips_voices';
        
        add_action('wp_ajax_aips_save_voice', array($this, 'ajax_save_voice'));
        add_action('wp_ajax_aips_delete_voice', array($this, 'ajax_delete_voice'));
        add_action('wp_ajax_aips_get_voice', array($this, 'ajax_get_voice'));
        add_action('wp_ajax_aips_search_voices', array($this, 'ajax_search_voices'));
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
        
        $voice_data = array(
            'name' => sanitize_text_field($data['name']),
            'title_prompt' => wp_kses_post($data['title_prompt']),
            'content_instructions' => wp_kses_post($data['content_instructions']),
            'is_active' => isset($data['is_active']) ? 1 : 0,
        );
        
        if (!empty($data['id'])) {
            $wpdb->update(
                $this->table_name,
                $voice_data,
                array('id' => absint($data['id'])),
                array('%s', '%s', '%s', '%d'),
                array('%d')
            );
            return absint($data['id']);
        } else {
            $wpdb->insert(
                $this->table_name,
                $voice_data,
                array('%s', '%s', '%s', '%d')
            );
            return $wpdb->insert_id;
        }
    }
    
    public function delete($id) {
        global $wpdb;
        return $wpdb->delete($this->table_name, array('id' => $id), array('%d'));
    }
    
    public function ajax_save_voice() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }
        
        $data = array(
            'id' => isset($_POST['voice_id']) ? absint($_POST['voice_id']) : 0,
            'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '',
            'title_prompt' => isset($_POST['title_prompt']) ? wp_kses_post($_POST['title_prompt']) : '',
            'content_instructions' => isset($_POST['content_instructions']) ? wp_kses_post($_POST['content_instructions']) : '',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        );
        
        if (empty($data['name']) || empty($data['title_prompt']) || empty($data['content_instructions'])) {
            wp_send_json_error(array('message' => __('All fields are required.', 'ai-post-scheduler')));
        }
        
        $id = $this->save($data);
        
        if ($id) {
            wp_send_json_success(array(
                'message' => __('Voice saved successfully.', 'ai-post-scheduler'),
                'voice_id' => $id
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to save voice.', 'ai-post-scheduler')));
        }
    }
    
    public function ajax_delete_voice() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }
        
        $id = isset($_POST['voice_id']) ? absint($_POST['voice_id']) : 0;
        
        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid voice ID.', 'ai-post-scheduler')));
        }
        
        if ($this->delete($id)) {
            wp_send_json_success(array('message' => __('Voice deleted successfully.', 'ai-post-scheduler')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete voice.', 'ai-post-scheduler')));
        }
    }
    
    public function ajax_get_voice() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }
        
        $id = isset($_POST['voice_id']) ? absint($_POST['voice_id']) : 0;
        
        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid voice ID.', 'ai-post-scheduler')));
        }
        
        $voice = $this->get($id);
        
        if ($voice) {
            wp_send_json_success(array('voice' => $voice));
        } else {
            wp_send_json_error(array('message' => __('Voice not found.', 'ai-post-scheduler')));
        }
    }
    
    public function ajax_search_voices() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }
        
        global $wpdb;
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $where = $search ? $wpdb->prepare("WHERE is_active = 1 AND name LIKE %s", '%' . $wpdb->esc_like($search) . '%') : "WHERE is_active = 1";
        
        $voices = $wpdb->get_results("SELECT id, name FROM {$this->table_name} $where ORDER BY name ASC LIMIT 20");
        
        wp_send_json_success(array('voices' => $voices));
    }
    
    public function render_page() {
        $voices = $this->get_all();
        
        include AIPS_PLUGIN_DIR . 'templates/admin/voices.php';
    }
}
