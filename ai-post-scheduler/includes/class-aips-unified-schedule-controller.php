<?php
/**
 * Unified Schedule Controller
 *
 * Handles AJAX requests for the unified schedule interface.
 * Extracted from AIPS_Schedule_Controller to separate concerns.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Unified_Schedule_Controller {

    public function __construct() {
        add_action('wp_ajax_aips_unified_run_now', array($this, 'ajax_unified_run_now'));
        add_action('wp_ajax_aips_unified_toggle', array($this, 'ajax_unified_toggle'));
        add_action('wp_ajax_aips_unified_bulk_toggle', array($this, 'ajax_unified_bulk_toggle'));
        add_action('wp_ajax_aips_unified_bulk_run_now', array($this, 'ajax_unified_bulk_run_now'));
        add_action('wp_ajax_aips_get_unified_schedule_history', array($this, 'ajax_get_unified_schedule_history'));
    }

    public function ajax_unified_run_now() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $id   = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $type = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';

        if (!$id || empty($type)) {
            wp_send_json_error(array('message' => __('Invalid parameters.', 'ai-post-scheduler')));
        }

        $service = new AIPS_Unified_Schedule_Service();
        $result  = $service->run_now($id, $type);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Format the success message based on type.
        if ($type === AIPS_Unified_Schedule_Service::TYPE_TEMPLATE) {
            $post_ids = is_array($result) ? $result : array($result);
            $first_post_id = !empty($post_ids) ? $post_ids[0] : 0;
            $edit_url = $first_post_id ? esc_url_raw(get_edit_post_link($first_post_id, 'raw')) : '';

            $msg = sprintf(
                _n('Schedule executed — %d post generated successfully!', 'Schedule executed — %d posts generated successfully!', count($post_ids), 'ai-post-scheduler'),
                count($post_ids)
            );

            wp_send_json_success(array(
                'message'  => $msg,
                'post_ids' => $post_ids,
                'post_id'  => $first_post_id, // keep post_id for backward compatibility
                'edit_url' => $edit_url,
            ));
        } elseif ($type === AIPS_Unified_Schedule_Service::TYPE_AUTHOR_TOPIC) {
            $count = is_array($result) ? count($result) : 0;
            wp_send_json_success(array(
                'message' => sprintf(
                    _n('%d topic generated successfully!', '%d topics generated successfully!', $count, 'ai-post-scheduler'),
                    $count
                ),
            ));
        } elseif ($type === AIPS_Unified_Schedule_Service::TYPE_AUTHOR_POST) {
            $post_id  = is_int($result) ? $result : 0;
            $edit_url = $post_id ? esc_url_raw(get_edit_post_link($post_id, 'raw')) : '';
            wp_send_json_success(array(
                'message'  => __('Post generated successfully from author topic!', 'ai-post-scheduler'),
                'post_id'  => $post_id,
                'edit_url' => $edit_url,
            ));
        } else {
            wp_send_json_success(array('message' => __('Schedule executed successfully.', 'ai-post-scheduler')));
        }
    }

    public function ajax_unified_toggle() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $id        = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $type      = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';
        $is_active = isset($_POST['is_active']) ? absint($_POST['is_active']) : 0;

        if (!$id || empty($type)) {
            wp_send_json_error(array('message' => __('Invalid parameters.', 'ai-post-scheduler')));
        }

        $service = new AIPS_Unified_Schedule_Service();
        $updated = $service->toggle_active($id, $type, $is_active);

        if ($updated) {
            $action_label = $is_active ? __('activated', 'ai-post-scheduler') : __('paused', 'ai-post-scheduler');
            wp_send_json_success(array(
                'message' => sprintf(__('Schedule %s successfully.', 'ai-post-scheduler'), $action_label),
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to update schedule status.', 'ai-post-scheduler')));
        }
    }

    public function ajax_unified_bulk_toggle() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $items = isset($_POST['items']) && is_array($_POST['items']) ? wp_unslash($_POST['items']) : array();
        $is_active = isset($_POST['is_active']) ? absint($_POST['is_active']) : 0;

        if (empty($items)) {
            wp_send_json_error(array('message' => __('No items provided.', 'ai-post-scheduler')));
        }

        $service = new AIPS_Unified_Schedule_Service();
        $success_count = 0;
        $failed_count  = 0;

        foreach ($items as $item) {
            $id   = isset($item['id']) ? absint($item['id']) : 0;
            $type = isset($item['type']) ? sanitize_key($item['type']) : '';
            if ($id && $type) {
                if ($service->toggle_active($id, $type, $is_active)) {
                    $success_count++;
                } else {
                    $failed_count++;
                }
            }
        }

        $action_label = $is_active ? __('activated', 'ai-post-scheduler') : __('paused', 'ai-post-scheduler');
        $message = sprintf(
            _n('%1$d schedule %2$s successfully.', '%1$d schedules %2$s successfully.', $success_count, 'ai-post-scheduler'),
            $success_count,
            $action_label
        );

        if ($failed_count > 0) {
            $message .= ' ' . sprintf(__('%d failed.', 'ai-post-scheduler'), $failed_count);
        }

        wp_send_json_success(array(
            'message'       => $message,
            'success_count' => $success_count,
            'failed_count'  => $failed_count,
        ));
    }

    public function ajax_unified_bulk_run_now() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $items = isset($_POST['items']) && is_array($_POST['items']) ? wp_unslash($_POST['items']) : array();

        if (empty($items)) {
            wp_send_json_error(array('message' => __('No items provided.', 'ai-post-scheduler')));
        }

        // Limit the number of bulk run now operations
        $limit = apply_filters('aips_unified_bulk_run_now_limit', 3);
        if (count($items) > $limit) {
            $items = array_slice($items, 0, $limit);
            $capped = true;
        } else {
            $capped = false;
        }

        $service = new AIPS_Unified_Schedule_Service();
        $success_count = 0;
        $failed_count  = 0;
        $errors        = array();

        foreach ($items as $item) {
            $id   = isset($item['id']) ? absint($item['id']) : 0;
            $type = isset($item['type']) ? sanitize_key($item['type']) : '';

            if ($id && $type) {
                $result = $service->run_now($id, $type);
                if (is_wp_error($result)) {
                    $failed_count++;
                    $errors[] = $result->get_error_message();
                } else {
                    $success_count++;
                }
            }
        }

        $message = sprintf(
            _n('%d schedule executed successfully.', '%d schedules executed successfully.', $success_count, 'ai-post-scheduler'),
            $success_count
        );

        if ($capped) {
            $message .= ' ' . sprintf(__('(Limited to %d for immediate execution)', 'ai-post-scheduler'), $limit);
        }

        if ($failed_count > 0) {
            $message .= ' ' . sprintf(__('%d failed.', 'ai-post-scheduler'), $failed_count);
        }

        wp_send_json_success(array(
            'message'       => $message,
            'success_count' => $success_count,
            'failed_count'  => $failed_count,
            'errors'        => $errors,
        ));
    }

    public function ajax_get_unified_schedule_history() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $id   = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $type = isset($_GET['type']) ? sanitize_key(wp_unslash($_GET['type'])) : '';

        if (!$id || empty($type)) {
            wp_send_json_error(array('message' => __('Invalid parameters.', 'ai-post-scheduler')));
        }

        $service = new AIPS_Unified_Schedule_Service();
        $entries = $service->get_schedule_history($id, $type, 5); // Limit to last 5 entries

        wp_send_json_success(array(
            'entries' => $entries
        ));
    }
}
