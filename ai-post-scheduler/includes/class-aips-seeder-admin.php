<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Seeder_Admin {

    private $service;
    private $hook_suffix;

    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_aips_process_seeder', array($this, 'ajax_process_seeder'));

        $this->service = new AIPS_Seeder_Service();
    }

    public function add_menu_page() {
        $this->hook_suffix = add_submenu_page(
            'ai-post-scheduler',
            __('Seeder', 'ai-post-scheduler'),
            __('Seeder', 'ai-post-scheduler'),
            'manage_options',
            'aips-seeder',
            array($this, 'render_page')
        );
    }

    public function enqueue_assets($hook) {
        if ($hook !== $this->hook_suffix) {
            return;
        }

        wp_enqueue_script(
            'aips-admin-seeder',
            AIPS_PLUGIN_URL . 'assets/js/admin-seeder.js',
            array('jquery', 'aips-admin-script'), // Depends on core admin script
            AIPS_VERSION,
            true
        );
    }

    public function render_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/seeder.php';
    }

    public function ajax_process_seeder() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $count = isset($_POST['count']) ? absint($_POST['count']) : 0;
        $keywords = isset($_POST['keywords']) ? sanitize_textarea_field($_POST['keywords']) : '';

        if (empty($type)) {
            wp_send_json_error(array('message' => 'Missing type.'));
        }

        // Increase timeout for AI generation
        if (function_exists('set_time_limit')) {
            set_time_limit(300);
        }

        $result = $this->service->seed($type, $count, $keywords);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
