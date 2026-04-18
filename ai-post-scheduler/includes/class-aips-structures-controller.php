<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Structures_Controller {

    private $repo;

    public function __construct($repo = null) {
        $this->repo = $repo ?: new AIPS_Article_Structure_Repository();

        add_action('wp_ajax_aips_get_structures', array($this, 'ajax_get_structures'));
        add_action('wp_ajax_aips_get_structure', array($this, 'ajax_get_structure'));
        add_action('wp_ajax_aips_save_structure', array($this, 'ajax_save_structure'));
        add_action('wp_ajax_aips_delete_structure', array($this, 'ajax_delete_structure'));
        add_action('wp_ajax_aips_set_structure_default', array($this, 'ajax_set_structure_default'));
        add_action('wp_ajax_aips_toggle_structure_active', array($this, 'ajax_toggle_structure_active'));
    }

    public function ajax_get_structures() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $structures = $this->repo->get_all(false);
        AIPS_Ajax_Response::success(array('structures' => $structures));
    }

    public function ajax_get_structure() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $id = isset($_POST['structure_id']) ? absint($_POST['structure_id']) : 0;
        if (!$id) {
            AIPS_Ajax_Response::error(__('Invalid structure ID.', 'ai-post-scheduler'));
        }

        $structure = $this->repo->get_by_id($id);
        if (!$structure) {
            AIPS_Ajax_Response::error(__('Structure not found.', 'ai-post-scheduler'));
        }

        AIPS_Ajax_Response::success(array('structure' => $structure));
    }

    public function ajax_save_structure() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $id = isset($_POST['structure_id']) ? absint($_POST['structure_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
        $sections = isset($_POST['sections']) && is_array($_POST['sections']) ? AIPS_Utilities::sanitize_string_array(wp_unslash($_POST['sections'])) : array();
        $prompt_template = isset($_POST['prompt_template']) ? wp_kses_post(wp_unslash($_POST['prompt_template'])) : '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_default = isset($_POST['is_default']) ? 1 : 0;

        if (empty($name) || empty($prompt_template)) {
            AIPS_Ajax_Response::error(__('Name and prompt template are required.', 'ai-post-scheduler'));
        }

        $manager = new AIPS_Article_Structure_Manager();

        if ($id) {
            $result = $manager->update_structure($id, $name, $sections, $prompt_template, $description, $is_default == 1, $is_active == 1);
            if (is_wp_error($result)) {
                AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
            }
            $structure = $this->repo->get_by_id($id);
            AIPS_Ajax_Response::success(array('message' => __('Structure updated.', 'ai-post-scheduler'), 'structure_id' => $id, 'structure' => $structure));
        } else {
            $new_id = $manager->create_structure($name, $sections, $prompt_template, $description, $is_default == 1, $is_active == 1);
            if (is_wp_error($new_id)) {
                AIPS_Ajax_Response::error(array('message' => $new_id->get_error_message()));
            }
            $structure = $this->repo->get_by_id($new_id);
            AIPS_Ajax_Response::success(array('message' => __('Structure created.', 'ai-post-scheduler'), 'structure_id' => $new_id, 'structure' => $structure));
        }
    }

    public function ajax_delete_structure() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $id = isset($_POST['structure_id']) ? absint($_POST['structure_id']) : 0;
        if (!$id) {
            AIPS_Ajax_Response::error(__('Invalid structure ID.', 'ai-post-scheduler'));
        }

        $manager = new AIPS_Article_Structure_Manager();
        $result = $manager->delete_structure($id);
        if (is_wp_error($result)) {
            AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
        }

        AIPS_Ajax_Response::success(array(), __('Structure deleted.', 'ai-post-scheduler'));
    }

    public function ajax_set_structure_default() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $id = isset($_POST['structure_id']) ? absint($_POST['structure_id']) : 0;
        if (!$id) {
            AIPS_Ajax_Response::error(__('Invalid structure ID.', 'ai-post-scheduler'));
        }

        $result = $this->repo->set_default($id);
        if (!$result) {
            AIPS_Ajax_Response::error(__('Failed to set default structure.', 'ai-post-scheduler'));
        }

        AIPS_Ajax_Response::success(array(), __('Default structure updated.', 'ai-post-scheduler'));
    }

    public function ajax_toggle_structure_active() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $id = isset($_POST['structure_id']) ? absint($_POST['structure_id']) : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (!$id) {
            AIPS_Ajax_Response::error(__('Invalid structure ID.', 'ai-post-scheduler'));
        }

        $result = $this->repo->set_active($id, $is_active);
        if (!$result) {
            AIPS_Ajax_Response::error(__('Failed to update active status.', 'ai-post-scheduler'));
        }

        AIPS_Ajax_Response::success(array(), __('Structure status updated.', 'ai-post-scheduler'));
    }
}
