<?php
if (!defined('ABSPATH')) {
    exit;
}

// Deprecated wrapper kept for backward compatibility. Use AIPS_Template_Service instead.
class AIPS_Templates {

    /** @var AIPS_Template_Service */
    private $service;

    public function __construct() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            _doing_it_wrong(__CLASS__, __('AIPS_Templates is deprecated. Use AIPS_Template_Service instead.', 'ai-post-scheduler'), '1.7.0');
        }

        $this->service = new AIPS_Template_Service();
    }

    public function get_all($active_only = false) {
        return $this->service->get_all($active_only);
    }

    public function get($id) {
        return $this->service->get($id);
    }

    public function save($data) {
        return $this->service->save($data);
    }

    public function delete($id) {
        return $this->service->delete($id);
    }

    public function get_pending_stats($template_id) {
        return $this->service->get_pending_stats($template_id);
    }

    public function get_all_pending_stats() {
        return $this->service->get_all_pending_stats();
    }

    public function render_page() {
        // Delegate rendering to the controller or settings flow. For backward compatibility
        // we implement the previous behavior by gathering the same variables and including the template.
        $templates = $this->get_all();
        $categories = get_categories(array('hide_empty' => false));
        $users = get_users(array('role__in' => array('administrator', 'editor', 'author')));

        include AIPS_PLUGIN_DIR . 'templates/admin/main.php';
    }
}
