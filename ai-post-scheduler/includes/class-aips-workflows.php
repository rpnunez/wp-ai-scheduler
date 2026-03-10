<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manage the admin Workflows page and persistence hooks.
 */
class AIPS_Workflows {

    const STATUS_GENERATED = 'generated';
    const STATUS_NEEDS_REVIEW = 'needs_review';
    const STATUS_APPROVED = 'approved';
    const STATUS_READY_TO_PUBLISH = 'ready_to_publish';

    /**
     * @var AIPS_Workflow_Repository|null
     */
    private static $repo;

    /**
     * Initialize hooks and repository.
     *
     * @param AIPS_Workflow_Repository|null $repo
     */
    public static function init(AIPS_Workflow_Repository $repo = null) {
        self::$repo = $repo ?: new AIPS_Workflow_Repository();
        add_action('admin_post_aips_save_workflow', array(__CLASS__, 'handle_save_workflow'));
        add_action('admin_post_aips_delete_workflow', array(__CLASS__, 'handle_delete_workflow'));
    }

    /**
     * Render the Workflows admin page.
     */
    public static function render_page() {
        $message_key = isset($_GET['message']) ? sanitize_key($_GET['message']) : '';
        $message = self::get_message_for_key($message_key);
        $workflows = self::get_all_workflows();
        $edit_workflow = self::get_workflow_from_request();
        include AIPS_PLUGIN_DIR . 'templates/admin/workflows.php';
    }

    /**
     * Return the stored workflows.
     *
     * @return array
     */
    public static function get_all_workflows() {
        return self::get_repo()->get_all();
    }

    /**
     * Return a workflow by ID.
     *
     * @param int $id
     * @return object|null
     */
    public static function get_workflow($id) {
        return self::get_repo()->get_by_id($id);
    }

    /**
     * Return status choices.
     *
     * @return array
     */
    public static function get_statuses() {
        return array(
            self::STATUS_GENERATED => __('Generated', 'ai-post-scheduler'),
            self::STATUS_NEEDS_REVIEW => __('Needs Review', 'ai-post-scheduler'),
            self::STATUS_APPROVED => __('Approved', 'ai-post-scheduler'),
            self::STATUS_READY_TO_PUBLISH => __('Ready to Publish', 'ai-post-scheduler'),
        );
    }

    /**
     * Get the translated label for a status key.
     *
     * @param string $key
     * @return string
     */
    public static function get_status_label($key) {
        $statuses = self::get_statuses();
        return isset($statuses[$key]) ? $statuses[$key] : $key;
    }

    /**
     * Handle saving (creating/updating) a workflow.
     */
    public static function handle_save_workflow() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'ai-post-scheduler'));
        }

        check_admin_referer('aips_save_workflow');

        $workflow_id = isset($_POST['workflow_id']) ? absint($_POST['workflow_id']) : 0;
        $name = isset($_POST['workflow_name']) ? sanitize_text_field($_POST['workflow_name']) : '';
        $description = isset($_POST['workflow_description']) ? sanitize_textarea_field($_POST['workflow_description']) : '';
        $status = self::sanitize_status($_POST['workflow_status'] ?? '');

        if (empty($name)) {
            $extra = $workflow_id ? array('workflow_id' => $workflow_id) : array();
            self::redirect_with_message('workflow_name_required', $extra);
        }

        $data = array(
            'name' => $name,
            'description' => $description,
            'status' => $status,
        );

        if ($workflow_id) {
            if (!self::get_workflow($workflow_id)) {
                self::redirect_with_message('workflow_not_found');
            }

            $updated = self::get_repo()->update($workflow_id, $data);
            if ($updated === false) {
                self::redirect_with_message('workflow_save_failed', array('workflow_id' => $workflow_id));
            }

            self::redirect_with_message('workflow_updated', array('workflow_id' => $workflow_id));
        }

        $inserted_id = self::get_repo()->create($data);
        if ($inserted_id === false) {
            self::redirect_with_message('workflow_save_failed');
        }

        self::redirect_with_message('workflow_created', array('workflow_id' => $inserted_id));
    }

    /**
     * Handle deleting a workflow.
     */
    public static function handle_delete_workflow() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'ai-post-scheduler'));
        }

        check_admin_referer('aips_delete_workflow');

        $workflow_id = isset($_POST['workflow_id']) ? absint($_POST['workflow_id']) : 0;
        if (!$workflow_id) {
            self::redirect_with_message('workflow_not_found');
        }

        if (!self::get_workflow($workflow_id)) {
            self::redirect_with_message('workflow_not_found');
        }

        $deleted = self::get_repo()->delete($workflow_id);
        if (!$deleted) {
            self::redirect_with_message('workflow_delete_failed');
        }

        self::redirect_with_message('workflow_deleted');
    }

    /**
     * Normalize status value.
     *
     * @param string $status
     * @return string
     */
    private static function sanitize_status($status) {
        $statuses = array_keys(self::get_statuses());
        return in_array($status, $statuses, true) ? $status : self::STATUS_GENERATED;
    }

    /**
     * Redirect back to the workflows page with a message.
     *
     * @param string $message_key
     * @param array $extra
     */
    private static function redirect_with_message($message_key, array $extra = array()) {
        $args = array_merge($extra, array('message' => $message_key));
        $url = add_query_arg($args, self::get_base_page_url());
        wp_safe_redirect($url);
        exit;
    }

    /**
     * Return base admin URL for workflows.
     *
     * @return string
     */
    private static function get_base_page_url() {
        return admin_url('admin.php?page=aips-workflows');
    }

    /**
     * Fetch the message configuration for a key.
     *
     * @param string $key
     * @return array|null
     */
    public static function get_message_for_key($key) {
        $map = array(
            'workflow_created' => array('text' => __('Workflow created.', 'ai-post-scheduler'), 'type' => 'success'),
            'workflow_updated' => array('text' => __('Workflow updated.', 'ai-post-scheduler'), 'type' => 'success'),
            'workflow_deleted' => array('text' => __('Workflow deleted.', 'ai-post-scheduler'), 'type' => 'success'),
            'workflow_name_required' => array('text' => __('Workflow name is required.', 'ai-post-scheduler'), 'type' => 'error'),
            'workflow_not_found' => array('text' => __('Workflow not found.', 'ai-post-scheduler'), 'type' => 'error'),
            'workflow_save_failed' => array('text' => __('Unable to save workflow.', 'ai-post-scheduler'), 'type' => 'error'),
            'workflow_delete_failed' => array('text' => __('Unable to delete workflow.', 'ai-post-scheduler'), 'type' => 'error'),
        );
        return isset($map[$key]) ? $map[$key] : null;
    }

    /**
     * Return repository instance.
     *
     * @return AIPS_Workflow_Repository
     */
    private static function get_repo() {
        if (self::$repo === null) {
            self::$repo = new AIPS_Workflow_Repository();
        }
        return self::$repo;
    }

    /**
     * Get workflow requested by query string.
     *
     * @return object|null
     */
    private static function get_workflow_from_request() {
        $workflow_id = isset($_GET['workflow_id']) ? absint($_GET['workflow_id']) : 0;
        if (!$workflow_id) {
            return null;
        }
        return self::get_workflow($workflow_id);
    }
}
