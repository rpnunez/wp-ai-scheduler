<?php
/**
 * Unified Schedule Controller.
 *
 * Handles AJAX requests for the unified schedule interface.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Unified_Schedule_Controller {

    /**
     * Initialize the controller and register AJAX hooks.
     */
    public function __construct() {
        // Unified schedule endpoints (all types)
        add_action('wp_ajax_aips_unified_run_now', array($this, 'ajax_unified_run_now'));
        add_action('wp_ajax_aips_unified_toggle', array($this, 'ajax_unified_toggle'));
        add_action('wp_ajax_aips_unified_bulk_toggle', array($this, 'ajax_unified_bulk_toggle'));
        add_action('wp_ajax_aips_unified_bulk_run_now', array($this, 'ajax_unified_bulk_run_now'));
        add_action('wp_ajax_aips_unified_bulk_delete', array($this, 'ajax_unified_bulk_delete'));
        add_action('wp_ajax_aips_get_unified_schedule_history', array($this, 'ajax_get_unified_schedule_history'));
    }

    /**
     * Expects POST: id (int), type (string).
     */
    public function ajax_unified_run_now() {
        check_ajax_referer('aips_ajax_nonce', 'nonce', false);

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $id   = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $type = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';

        if (!$id || empty($type)) {
            AIPS_Ajax_Response::error(__('Invalid parameters.', 'ai-post-scheduler'));
        }

        $service = new AIPS_Unified_Schedule_Service();
        $result  = $service->run_now($id, $type);

        if (is_wp_error($result)) {
            AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
        }

        AIPS_Ajax_Response::success(array('message' => __('Schedule generation started.', 'ai-post-scheduler')));
    }

    /**
     * Expects POST: id (int), type (string), is_active (0|1).
     */
    public function ajax_unified_toggle() {
        check_ajax_referer('aips_ajax_nonce', 'nonce', false);

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $id        = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $type      = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';
        $is_active = isset($_POST['is_active']) ? absint($_POST['is_active']) : 0;

        if (!$id || empty($type)) {
            AIPS_Ajax_Response::error(__('Invalid parameters.', 'ai-post-scheduler'));
        }

        $service = new AIPS_Unified_Schedule_Service();
        $result  = $service->toggle($id, $type, $is_active);

        if ($result !== false) {
            $label = $is_active
                ? __('Schedule activated.', 'ai-post-scheduler')
                : __('Schedule paused.', 'ai-post-scheduler');
            AIPS_Ajax_Response::success(array('message' => $label));
        }

        AIPS_Ajax_Response::error(__('Failed to toggle schedule.', 'ai-post-scheduler'));
    }

    /**
     * Expects POST: items (array of {id, type}), is_active (0|1).
     */
    public function ajax_unified_bulk_toggle() {
        check_ajax_referer('aips_ajax_nonce', 'nonce', false);

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $items     = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : array();
        $is_active = isset($_POST['is_active']) ? absint($_POST['is_active']) : 0;

        if (empty($items)) {
            AIPS_Ajax_Response::error(__('No items provided.', 'ai-post-scheduler'));
        }

        $service = new AIPS_Unified_Schedule_Service();
        $updated = 0;
        $errors  = array();

        foreach ($items as $item) {
            $id   = isset($item['id']) ? absint($item['id']) : 0;
            $type = isset($item['type']) ? sanitize_key($item['type']) : '';

            if (!$id || empty($type)) {
                continue;
            }

            $res = $service->toggle($id, $type, $is_active);
            if ($res !== false) {
                $updated++;
            } else {
                $errors[] = sprintf(__('Failed to toggle item ID %d (%s)', 'ai-post-scheduler'), $id, $type);
            }
        }

        $message = sprintf(
            /* translators: %d: count of schedules updated */
            _n('%d schedule updated.', '%d schedules updated.', $updated, 'ai-post-scheduler'),
            $updated
        );

        if ($updated > 0) {
            AIPS_Ajax_Response::success(array('message' => $message, 'errors' => $errors));
        } else {
            AIPS_Ajax_Response::error(array(
                'message' => __('No schedules were updated.', 'ai-post-scheduler'),
                'errors'  => $errors,
            ));
        }
    }

    /**
     * Expects POST: items (array of {id, type}).
     */
    public function ajax_unified_bulk_run_now() {
        check_ajax_referer('aips_ajax_nonce', 'nonce', false);

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $items = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : array();

        if (empty($items)) {
            AIPS_Ajax_Response::error(__('No items provided.', 'ai-post-scheduler'));
        }

        $max_bulk = apply_filters('aips_unified_bulk_run_now_limit', 5);
        if (count($items) > $max_bulk) {
            AIPS_Ajax_Response::error(array(
                'message' => sprintf(
                    /* translators: 1: selected count, 2: max allowed */
                    __('Too many schedules selected (%1$d). Please select no more than %2$d at a time.', 'ai-post-scheduler'),
                    count($items),
                    $max_bulk
                )
            ));
        }

        $service = new AIPS_Unified_Schedule_Service();
        $started = 0;
        $errors  = array();

        foreach ($items as $item) {
            $id   = isset($item['id']) ? absint($item['id']) : 0;
            $type = isset($item['type']) ? sanitize_key($item['type']) : '';

            if (!$id || empty($type)) {
                continue;
            }

            $result = $service->run_now($id, $type);
            if (is_wp_error($result)) {
                $errors[] = sprintf(
                    __('Failed to run item ID %1$d (%2$s): %3$s', 'ai-post-scheduler'),
                    $id,
                    $type,
                    $result->get_error_message()
                );
            } else {
                $started++;
            }
        }

        $message = sprintf(
            /* translators: %d: count of schedules started */
            _n('%d schedule generation started.', '%d schedule generations started.', $started, 'ai-post-scheduler'),
            $started
        );

        if ($started > 0) {
            AIPS_Ajax_Response::success(array('message' => $message, 'errors' => $errors));
        } else {
            AIPS_Ajax_Response::error(array(
                'message' => __('No schedules were started.', 'ai-post-scheduler'),
                'errors'  => $errors,
            ));
        }
    }

    /**
     * Only template schedules are deletable.
     */
    public function ajax_unified_bulk_delete() {
        check_ajax_referer('aips_ajax_nonce', 'nonce', false);

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $items = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : array();

        if (empty($items)) {
            AIPS_Ajax_Response::error(__('No items provided.', 'ai-post-scheduler'));
        }

        $service       = new AIPS_Unified_Schedule_Service();
        $deleted_count = 0;
        $deleted_items = array();
        $failed_items  = array();

        foreach ($items as $item) {
            $id   = isset($item['id']) ? absint($item['id']) : 0;
            $type = isset($item['type']) ? sanitize_key($item['type']) : '';

            if (!$id || $type !== 'template') {
                continue; // only templates support delete in unified model right now
            }

            $res = $service->delete_template_schedule($id);
            if ($res) {
                $deleted_count++;
                $deleted_items[] = $id;
            } else {
                $failed_items[] = $id;
            }
        }

        $message = sprintf(
            /* translators: %d: count of schedules deleted */
            _n('%d schedule deleted.', '%d schedules deleted.', $deleted_count, 'ai-post-scheduler'),
            $deleted_count
        );

        if ($deleted_count > 0) {
            AIPS_Ajax_Response::success(array(
                'message'       => $message,
                'deleted_items' => $deleted_items,
                'failed_items'  => $failed_items,
            ));
        } else {
            AIPS_Ajax_Response::error(array(
                'message'      => __('No schedules were deleted.', 'ai-post-scheduler'),
                'failed_items' => $failed_items,
            ));
        }
    }

    /**
     * Gets execution history for a specific scheduled entity.
     * Required POST fields: id, type.
     */
    public function ajax_get_unified_schedule_history() {
        check_ajax_referer('aips_ajax_nonce', 'nonce', false);

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $id   = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $type = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';

        if (!$id || empty($type)) {
            AIPS_Ajax_Response::error(__('Missing entity parameters.', 'ai-post-scheduler'));
        }

        $service = new AIPS_Unified_Schedule_Service();
        $history = $service->get_history($id, $type);

        if (is_wp_error($history)) {
            AIPS_Ajax_Response::error($history->get_error_message());
        }

        if (empty($history)) {
            $html = '<div class="aips-empty-state">';
            $html .= '<span class="dashicons dashicons-backup aips-empty-state-icon" style="opacity: 0.5; font-size: 32px; width: 32px; height: 32px;"></span>';
            $html .= '<h3 class="aips-empty-state-title" style="margin-top: 15px;">' . esc_html__('No History Yet', 'ai-post-scheduler') . '</h3>';
            $html .= '<p class="aips-empty-state-description" style="max-width: 300px; margin: 10px auto;">' . esc_html__('This schedule hasn\'t run yet. Once it generates posts, the history will appear here.', 'ai-post-scheduler') . '</p>';
            $html .= '</div>';
            AIPS_Ajax_Response::success(array('html' => $html));
        }

        // Render the list of generation events
        ob_start();
        echo '<div class="aips-history-events">';

        foreach ($history as $event) {
            $status_class = 'aips-badge-' . ($event['status'] === 'success' ? 'success' : 'error');

            echo '<div class="aips-history-event">';
            echo '<div class="aips-history-event-header">';
            echo '<strong>' . esc_html(wp_date('M j, Y g:i A', $event['timestamp'])) . '</strong>';
            echo '<span class="aips-badge ' . esc_attr($status_class) . '">' . esc_html($event['status']) . '</span>';
            echo '</div>';

            if ($event['status'] === 'success' && !empty($event['post_id'])) {
                $post_url = get_edit_post_link($event['post_id']);
                $post_title = get_the_title($event['post_id']);
                echo '<div class="aips-history-event-body">';
                echo '<span>' . esc_html__('Generated:', 'ai-post-scheduler') . '</span> ';
                echo '<a href="' . esc_url($post_url) . '">' . esc_html($post_title ?: __('(No Title)', 'ai-post-scheduler')) . '</a>';
                echo '</div>';
            } elseif (!empty($event['error_message'])) {
                echo '<div class="aips-history-event-error" style="color: #d63638; margin-top: 5px;">';
                echo esc_html($event['error_message']);
                echo '</div>';
            }

            echo '</div>';
        }

        echo '</div>';

        $html = ob_get_clean();

        AIPS_Ajax_Response::success(array('html' => $html));
    }
}
