<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Settings_AJAX
 *
 * Handles the AJAX endpoints for the settings page.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Settings_AJAX {

    /**
     * Initialize the AJAX handler.
     *
     * Hooks into wp_ajax.
     */
    public function __construct() {
        add_action('wp_ajax_aips_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_aips_notifications_data_hygiene', array($this, 'ajax_notifications_data_hygiene'));
    }

    /**
     * Handle AJAX request to test AI connection.
     *
     * @return void
     */
    public function ajax_test_connection() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized access.', 'ai-post-scheduler')));
        }

        $ai_service = new AIPS_AI_Service();
        $result = $ai_service->generate_text('Say "Hello World" in 2 words.', array('maxTokens' => 10));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            // SECURITY: Escape the AI response before sending it to the browser to prevent XSS.
            // Even though the prompt is hardcoded ("Say Hello World"), the AI response should be treated as untrusted.
            wp_send_json_success(array('message' => __('Connection successful! AI response: ', 'ai-post-scheduler') . esc_html($result)));
        }
    }

    /**
     * Run one-time notifications hygiene actions from System Status.
     *
     * @return void
     */
    public function ajax_notifications_data_hygiene() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized access.', 'ai-post-scheduler')));
        }

        $removed_options = 0;
        if (false !== get_option('aips_review_notifications_enabled', false)) {
            delete_option('aips_review_notifications_enabled');
            $removed_options++;
        }

        $unscheduled_events = 0;
        $legacy_hook = 'aips_send_review_notifications';
        $next_run = wp_next_scheduled($legacy_hook);
        while ($next_run) {
            wp_unschedule_event($next_run, $legacy_hook);
            $unscheduled_events++;
            $next_run = wp_next_scheduled($legacy_hook);
        }

        $rollup_scheduled = (bool) wp_next_scheduled('aips_notification_rollups');
        if (!$rollup_scheduled) {
            wp_schedule_event(time(), 'daily', 'aips_notification_rollups');
            $rollup_scheduled = (bool) wp_next_scheduled('aips_notification_rollups');
        }

        $registry = AIPS_Notifications::get_notification_type_registry();
        $allowed_modes = array_keys(AIPS_Notifications::get_channel_mode_options());
        $current_preferences = get_option('aips_notification_preferences', array());
        $current_preferences = is_array($current_preferences) ? $current_preferences : array();
        $config_defaults = AIPS_Config::get_instance()->get_option('aips_notification_preferences', array());
        $config_defaults = is_array($config_defaults) ? $config_defaults : array();

        $cleaned_preferences = array();
        foreach ($registry as $type => $meta) {
            $fallback = isset($config_defaults[$type]) ? $config_defaults[$type] : (isset($meta['default_mode']) ? $meta['default_mode'] : AIPS_Notifications::MODE_BOTH);
            $mode = isset($current_preferences[$type]) ? sanitize_key($current_preferences[$type]) : $fallback;
            if (!in_array($mode, $allowed_modes, true)) {
                $mode = $fallback;
            }
            $cleaned_preferences[$type] = $mode;
        }

        $preferences_changed = ($cleaned_preferences !== $current_preferences);
        if ($preferences_changed) {
            update_option('aips_notification_preferences', $cleaned_preferences, false);
        }

        wp_send_json_success(array(
            'message' => __('Notifications hygiene completed successfully.', 'ai-post-scheduler'),
            'details' => array(
                'removed_options'    => $removed_options,
                'unscheduled_events' => $unscheduled_events,
                'rollup_scheduled'   => $rollup_scheduled ? 1 : 0,
                'preferences_changed'=> $preferences_changed ? 1 : 0,
            ),
        ));
    }
}
