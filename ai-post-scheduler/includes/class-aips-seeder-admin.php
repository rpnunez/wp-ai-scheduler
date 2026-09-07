<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Seeder_Admin {

    private $service;

    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_aips_process_seeder', array($this, 'ajax_process_seeder'));

        $this->service = new AIPS_Seeder_Service();
    }

    public function enqueue_assets($hook) {
        unset($hook);

        $page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $tab  = filter_input(INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $page = $page ? sanitize_key($page) : '';
        $tab  = $tab ? sanitize_key($tab) : '';

        // Seeder UI now lives only under Diagnostics -> Dev Tools.
        if ('aips-diagnostics' !== $page || 'dev-tools' !== $tab) {
            return;
        }

        wp_enqueue_script(
            'aips-admin-seeder',
            AIPS_PLUGIN_URL . 'assets/js/admin-seeder.js',
            array('jquery', 'wp-i18n', 'aips-admin-script'),
            AIPS_VERSION,
            true
        );

        wp_set_script_translations('aips-admin-seeder', 'ai-post-scheduler', AIPS_PLUGIN_DIR . 'languages');
    }

    public function ajax_process_seeder() {
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        if (!AIPS_Config::get_instance()->get_option('aips_developer_mode')) {
            AIPS_Ajax_Response::error(__('Developer Mode is disabled.', 'ai-post-scheduler'));
        }

        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : '';
        $count = isset($_POST['count']) ? absint($_POST['count']) : 0;
        $keywords = isset($_POST['keywords']) ? sanitize_textarea_field(wp_unslash($_POST['keywords'])) : '';

        if (empty($type)) {
            AIPS_Ajax_Response::error(__('Missing type.', 'ai-post-scheduler'));
        }

        // Increase timeout for AI generation
        if (function_exists('set_time_limit')) {
            set_time_limit(300);
        }

        $result = $this->service->seed($type, $count, $keywords);

        if ($result['success']) {
            do_action('aips_seeder_completed', array(
                'type'    => $type,
                'count'   => $count,
                'message' => isset($result['message']) ? $result['message'] : __('Seeder completed.', 'ai-post-scheduler'),
                'user_id' => get_current_user_id(),
            ));

            AIPS_Ajax_Response::success($result);
        } else {
            AIPS_Ajax_Response::error($result);
        }
    }
}
