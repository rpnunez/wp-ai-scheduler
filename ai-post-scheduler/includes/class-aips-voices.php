<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Voices {
    
    /**
     * @var AIPS_Voices_Repository Repository for database operations
     */
    private $repository;
    
    public function __construct() {
        $container = AIPS_Container::get_instance();
        $this->repository = $container->make(AIPS_Voices_Repository::class);
        
        add_action('wp_ajax_aips_save_voice', array($this, 'ajax_save_voice'));
        add_action('wp_ajax_aips_delete_voice', array($this, 'ajax_delete_voice'));
        add_action('wp_ajax_aips_get_voice', array($this, 'ajax_get_voice'));
        add_action('wp_ajax_aips_search_voices', array($this, 'ajax_search_voices'));
    }
    
    public function get_all($active_only = false) {
        return $this->repository->get_all($active_only);
    }
    
    public function get($id) {
        return $this->repository->get_by_id($id);
    }
    
    public function save($data) {
        // Enforce defaults for backward compatibility with legacy save behavior
        if (!isset($data['is_active'])) {
            $data['is_active'] = 0;
        }
        if (!isset($data['excerpt_instructions'])) {
            $data['excerpt_instructions'] = '';
        }
        
        if (!empty($data['id'])) {
            $this->repository->update(absint($data['id']), $data);
            return absint($data['id']);
        } else {
            return $this->repository->create($data);
        }
    }
    
    public function delete($id) {
        return $this->repository->delete($id);
    }
    
    public function ajax_save_voice() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }
        
        $data = array(
            'id' => isset($_POST['voice_id']) ? absint($_POST['voice_id']) : 0,
            'name' => isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '',
            'title_prompt' => isset($_POST['title_prompt']) ? wp_kses_post(wp_unslash($_POST['title_prompt'])) : '',
            'content_instructions' => isset($_POST['content_instructions']) ? wp_kses_post(wp_unslash($_POST['content_instructions'])) : '',
            'excerpt_instructions' => isset($_POST['excerpt_instructions']) ? wp_kses_post(wp_unslash($_POST['excerpt_instructions'])) : '',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        );
        
        if (empty($data['name']) || empty($data['title_prompt']) || empty($data['content_instructions'])) {
            AIPS_Ajax_Response::error(__('Name, Title Prompt, and Content Instructions are required.', 'ai-post-scheduler'));
        }
        
        $id = $this->save($data);
        
        if ($id) {
            AIPS_Ajax_Response::success(array(
                'message' => __('Voice saved successfully.', 'ai-post-scheduler'),
                'voice_id' => $id
            ));
        } else {
            AIPS_Ajax_Response::error(__('Failed to save voice.', 'ai-post-scheduler'));
        }
    }
    
    public function ajax_delete_voice() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }
        
        $id = isset($_POST['voice_id']) ? absint($_POST['voice_id']) : 0;
        
        if (!$id) {
            AIPS_Ajax_Response::error(__('Invalid voice ID.', 'ai-post-scheduler'));
        }
        
        if ($this->delete($id)) {
            AIPS_Ajax_Response::success(array(), __('Voice deleted successfully.', 'ai-post-scheduler'));
        } else {
            AIPS_Ajax_Response::error(__('Failed to delete voice.', 'ai-post-scheduler'));
        }
    }
    
    public function ajax_get_voice() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }
        
        $id = isset($_POST['voice_id']) ? absint($_POST['voice_id']) : 0;
        
        if (!$id) {
            AIPS_Ajax_Response::error(__('Invalid voice ID.', 'ai-post-scheduler'));
        }
        
        $voice = $this->get($id);
        
        if ($voice) {
            AIPS_Ajax_Response::success(array('voice' => $voice));
        } else {
            AIPS_Ajax_Response::error(__('Voice not found.', 'ai-post-scheduler'));
        }
    }
    
    public function ajax_search_voices() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }
        
        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        $voices = $this->repository->search($search);
        
        AIPS_Ajax_Response::success(array('voices' => $voices));
    }
    
    public function render_page() {
        $voices = $this->get_all();
        
        include AIPS_PLUGIN_DIR . 'templates/admin/voices.php';
    }
}
