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

    /**
     * Handle AJAX request to retrieve available article structures.
     */
    public function ajax_get_structures() {
        $this->validate_manage_options_request();

        $structures = $this->repo->get_all(false);
        wp_send_json_success(array('structures' => $structures));
    }

    /**
     * Handle AJAX request to retrieve a single article structure.
     */
    public function ajax_get_structure() {
        $this->validate_manage_options_request();

        $id = isset($_POST['structure_id']) ? absint($_POST['structure_id']) : 0;
        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid structure ID.', 'ai-post-scheduler')));
        }

        $structure = $this->repo->get_by_id($id);
        if (!$structure) {
            wp_send_json_error(array('message' => __('Structure not found.', 'ai-post-scheduler')));
        }

        wp_send_json_success(array('structure' => $structure));
    }

    /**
     * Handle AJAX request to save or update an article structure.
     */
    public function ajax_save_structure() {
        $this->validate_manage_options_request();

        $id = isset($_POST['structure_id']) ? absint($_POST['structure_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $sections = isset($_POST['sections']) && is_array($_POST['sections']) ? array_map('sanitize_text_field', $_POST['sections']) : array();
        $prompt_template = isset($_POST['prompt_template']) ? wp_kses_post($_POST['prompt_template']) : '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_default = isset($_POST['is_default']) ? 1 : 0;

        if (empty($name) || empty($prompt_template)) {
            wp_send_json_error(array('message' => __('Name and prompt template are required.', 'ai-post-scheduler')));
        }

        $manager = new AIPS_Article_Structure_Manager();

        if ($id) {
            $result = $manager->update_structure($id, $name, $sections, $prompt_template, $description, $is_default == 1, $is_active == 1);
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }
            wp_send_json_success(array('message' => __('Structure updated.', 'ai-post-scheduler'), 'structure_id' => $id));
        } else {
            $new_id = $manager->create_structure($name, $sections, $prompt_template, $description, $is_default == 1, $is_active == 1);
            if (is_wp_error($new_id)) {
                wp_send_json_error(array('message' => $new_id->get_error_message()));
            }
            wp_send_json_success(array('message' => __('Structure created.', 'ai-post-scheduler'), 'structure_id' => $new_id));
        }
    }

    /**
     * Handle AJAX request to delete an article structure.
     */
    public function ajax_delete_structure() {
        $this->validate_manage_options_request();

        $id = isset($_POST['structure_id']) ? absint($_POST['structure_id']) : 0;
        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid structure ID.', 'ai-post-scheduler')));
        }

        $manager = new AIPS_Article_Structure_Manager();
        $result = $manager->delete_structure($id);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Structure deleted.', 'ai-post-scheduler')));
    }

    /**
     * Handle AJAX request to mark a structure as default.
     */
    public function ajax_set_structure_default() {
        $this->validate_manage_options_request();

        $id = isset($_POST['structure_id']) ? absint($_POST['structure_id']) : 0;
        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid structure ID.', 'ai-post-scheduler')));
        }

        $result = $this->repo->set_default($id);
        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to set default structure.', 'ai-post-scheduler')));
        }

        wp_send_json_success(array('message' => __('Default structure updated.', 'ai-post-scheduler')));
    }

    /**
     * Handle AJAX request to toggle a structure's active status.
     */
    public function ajax_toggle_structure_active() {
        $this->validate_manage_options_request();

        $id = isset($_POST['structure_id']) ? absint($_POST['structure_id']) : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid structure ID.', 'ai-post-scheduler')));
        }

        $result = $this->repo->set_active($id, $is_active);
        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to update active status.', 'ai-post-scheduler')));
        }

        wp_send_json_success(array('message' => __('Structure status updated.', 'ai-post-scheduler')));
    }

    /**
     * Validate nonce and capability for structure management requests.
     */
    private function validate_manage_options_request() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }
    }
}

