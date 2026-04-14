<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Schedule_Controller {

    private $scheduler;

    public function __construct($scheduler = null) {
        $this->scheduler = $scheduler ?: new AIPS_Scheduler();

        add_action('wp_ajax_aips_save_schedule', array($this, 'ajax_save_schedule'));

        // Primary schedule endpoints (all types)
        add_action('wp_ajax_aips_schedule_run_now', array($this, 'ajax_schedule_run_now'));
        add_action('wp_ajax_aips_schedule_toggle', array($this, 'ajax_schedule_toggle'));
        add_action('wp_ajax_aips_schedule_bulk_toggle', array($this, 'ajax_schedule_bulk_toggle'));
        add_action('wp_ajax_aips_schedule_bulk_run_now', array($this, 'ajax_schedule_bulk_run_now'));
        add_action('wp_ajax_aips_schedule_bulk_delete', array($this, 'ajax_schedule_bulk_delete'));
        add_action('wp_ajax_aips_get_schedule_history', array($this, 'ajax_get_schedule_history'));
    }

    /**
     * Build the generated-post preview payload used by the Templates run-now modal.
     *
     * @param array $post_ids Generated post IDs.
     * @return array<int, array<string, mixed>>
     */
    private function get_generated_post_modal_data($post_ids) {
        $posts = array();

        foreach ($post_ids as $post_id) {
            $post_id = absint($post_id);

            if (!$post_id) {
                continue;
            }

            $post = get_post($post_id);

            if (!$post instanceof WP_Post) {
                continue;
            }

            $title = get_the_title($post_id);
            if ('' === trim($title)) {
                $title = __('(no title)', 'ai-post-scheduler');
            }

            $excerpt = wp_strip_all_tags(get_the_excerpt($post));
            if ('' === trim($excerpt)) {
                $excerpt = __('No excerpt available.', 'ai-post-scheduler');
            }

            $content_snippet = wp_trim_words(
                wp_strip_all_tags((string) $post->post_content),
                28,
                '...'
            );

            if ('' === trim($content_snippet)) {
                $content_snippet = __('No content available.', 'ai-post-scheduler');
            }

            $view_url = 'publish' === $post->post_status
                ? get_permalink($post_id)
                : get_preview_post_link($post);

            if (!$view_url) {
                $view_url = get_edit_post_link($post_id, 'raw');
            }

            $posts[] = array(
                'id'              => $post_id,
                'title'           => $title,
                'excerpt'         => $excerpt,
                'content_snippet' => $content_snippet,
                'post_content'    => wpautop(wp_kses_post((string) $post->post_content)),
                'edit_url'        => esc_url_raw(get_edit_post_link($post_id, 'raw')),
                'view_url'        => esc_url_raw($view_url),
            );
        }

        return $posts;
    }

    public function ajax_save_schedule() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $data = array(
            'id' => isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0,
            'template_id' => isset($_POST['template_id']) ? absint($_POST['template_id']) : 0,
            'title' => isset($_POST['schedule_title']) ? sanitize_text_field(wp_unslash($_POST['schedule_title'])) : '',
            'frequency' => isset($_POST['frequency']) ? sanitize_text_field(wp_unslash($_POST['frequency'])) : 'daily',
            'start_time' => isset($_POST['start_time']) ? sanitize_text_field(wp_unslash($_POST['start_time'])) : null,
            'is_active' => isset($_POST['is_active']) && 1 === absint($_POST['is_active']) ? 1 : 0,
            'topic' => isset($_POST['topic']) ? sanitize_text_field(wp_unslash($_POST['topic'])) : '',
            'article_structure_id' => isset($_POST['article_structure_id']) && $_POST['article_structure_id'] !== '' ? absint($_POST['article_structure_id']) : null,
            'rotation_pattern' => isset($_POST['rotation_pattern']) && $_POST['rotation_pattern'] !== '' ? sanitize_text_field(wp_unslash($_POST['rotation_pattern'])) : null,
        );

        if (empty($data['template_id'])) {
            AIPS_Ajax_Response::error(__('Please select a template.', 'ai-post-scheduler'));
        }

        $interval_calculator = new AIPS_Interval_Calculator();
        if (!$interval_calculator->is_valid_frequency($data['frequency'])) {
            AIPS_Ajax_Response::error(__('Invalid frequency selected.', 'ai-post-scheduler'));
        }

        $id = $this->scheduler->save_schedule($data);

        if ($id) {
            AIPS_Ajax_Response::success(array(
                'message' => __('Schedule saved successfully.', 'ai-post-scheduler'),
                'schedule_id' => $id
            ));
        } else {
            AIPS_Ajax_Response::error(__('Failed to save schedule.', 'ai-post-scheduler'));
        }
    }

    /**
     * AJAX: Run any schedule type immediately.
     *
     * Expects POST: id (int), type (string).
     */
    public function ajax_schedule_run_now() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $id   = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $type = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';

        if (!$id || empty($type)) {
            AIPS_Ajax_Response::error(__('Invalid parameters.', 'ai-post-scheduler'));
        }

        $service = new AIPS_Schedule_Service();
        $result  = $service->run_now($id, $type);

        if (is_wp_error($result)) {
            AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
        }

        // Format the success message based on type.
        if ($type === AIPS_Schedule_Service::TYPE_TEMPLATE) {
            $post_ids = is_array($result) ? $result : array($result);
            $first_post_id = !empty($post_ids) ? $post_ids[0] : 0;
            $edit_url = $first_post_id ? esc_url_raw(get_edit_post_link($first_post_id, 'raw')) : '';
            $generated_posts = $this->get_generated_post_modal_data($post_ids);
            $generated_count = count($post_ids);
            $summary_message = sprintf(
                _n('%d post has been generated.', '%d posts have been generated.', $generated_count, 'ai-post-scheduler'),
                $generated_count
            );

            $msg = sprintf(
                _n('Schedule executed — %d post generated successfully!', 'Schedule executed — %d posts generated successfully!', count($post_ids), 'ai-post-scheduler'),
                count($post_ids)
            );

            AIPS_Ajax_Response::success(array(
                'message'         => $msg,
                'summary_message' => $summary_message,
                'notice_message'  => '',
                'generated_count' => $generated_count,
                'post_ids'        => $post_ids,
                'post_id'         => $first_post_id, // keep post_id for backward compatibility
                'posts'           => $generated_posts,
                'errors'          => array(),
                'edit_url'        => $edit_url,
            ));
        } elseif ($type === AIPS_Schedule_Service::TYPE_AUTHOR_TOPIC) {
            $count = is_array($result) ? count($result) : 0;
            AIPS_Ajax_Response::success(array(
                'message' => sprintf(
                    _n('%d topic generated successfully!', '%d topics generated successfully!', $count, 'ai-post-scheduler'),
                    $count
                ),
            ));
        } elseif ($type === AIPS_Schedule_Service::TYPE_AUTHOR_POST) {
            $post_id  = is_int($result) ? $result : 0;
            $edit_url = $post_id ? esc_url_raw(get_edit_post_link($post_id, 'raw')) : '';
            AIPS_Ajax_Response::success(array(
                'message'  => __('Post generated successfully from author topic!', 'ai-post-scheduler'),
                'post_id'  => $post_id,
                'edit_url' => $edit_url,
            ));
        } else {
            AIPS_Ajax_Response::success(array(), __('Schedule executed successfully.', 'ai-post-scheduler'));
        }
    }

    /**
     * AJAX: Toggle active status for any schedule type.
     *
     * Expects POST: id (int), type (string), is_active (0|1).
     */
    public function ajax_schedule_toggle() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $id        = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $type      = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';
        $is_active = isset($_POST['is_active']) ? absint($_POST['is_active']) : 0;

        if (!$id || empty($type)) {
            AIPS_Ajax_Response::error(__('Invalid parameters.', 'ai-post-scheduler'));
        }

        $service = new AIPS_Schedule_Service();
        $result  = $service->toggle($id, $type, $is_active);

        if ($result !== false) {
            $label = $is_active
                ? __('Schedule activated.', 'ai-post-scheduler')
                : __('Schedule paused.', 'ai-post-scheduler');
            AIPS_Ajax_Response::success(array('message' => $label, 'is_active' => $is_active));
        } else {
            AIPS_Ajax_Response::error(__('Failed to update schedule.', 'ai-post-scheduler'));
        }
    }

    /**
     * AJAX: Bulk pause or resume multiple schedules of mixed types.
     *
     * Expects POST: items (array of {id, type}), is_active (0|1).
     */
    public function ajax_schedule_bulk_toggle() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $items     = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : array();
        $is_active = isset($_POST['is_active']) ? absint($_POST['is_active']) : 0;

        if (empty($items)) {
            AIPS_Ajax_Response::error(__('No items provided.', 'ai-post-scheduler'));
        }

        $service = new AIPS_Schedule_Service();
        $updated = 0;
        $errors  = array();

        foreach ($items as $item) {
            $id   = isset($item['id']) ? absint($item['id']) : 0;
            $type = isset($item['type']) ? sanitize_key($item['type']) : '';
            if (!$id || empty($type)) {
                continue;
            }
            $result = $service->toggle($id, $type, $is_active);
            if ($result !== false) {
                $updated++;
            } else {
                $errors[] = $id;
            }
        }

        $action_label = $is_active
            ? __('activated', 'ai-post-scheduler')
            : __('paused', 'ai-post-scheduler');

        AIPS_Ajax_Response::success(array(
            'message' => sprintf(
                /* translators: 1: count, 2: action */
                _n('%1$d schedule %2$s.', '%1$d schedules %2$s.', $updated, 'ai-post-scheduler'),
                $updated,
                $action_label
            ),
            'updated'   => $updated,
            'errors'    => $errors,
            'is_active' => $is_active,
        ));
    }

    /**
     * AJAX: Bulk run-now for multiple schedules of mixed types.
     *
     * Expects POST: items (array of {id, type}).
     */
    public function ajax_schedule_bulk_run_now() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $items = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : array();

        if (empty($items)) {
            AIPS_Ajax_Response::error(__('No items provided.', 'ai-post-scheduler'));
        }

        $max_bulk = apply_filters('aips_schedule_bulk_run_now_limit', 5);
        if (count($items) > $max_bulk) {
            AIPS_Ajax_Response::error(array(
                'message' => sprintf(
                    /* translators: 1: selected count, 2: max allowed */
                    __('Too many schedules selected (%1$d). Please select no more than %2$d at a time.', 'ai-post-scheduler'),
                    count($items),
                    $max_bulk
                ),
            ));
        }

        $service  = new AIPS_Schedule_Service();
        $success  = 0;
        $errors   = array();

        foreach ($items as $item) {
            $id   = isset($item['id']) ? absint($item['id']) : 0;
            $type = isset($item['type']) ? sanitize_key($item['type']) : '';
            if (!$id || empty($type)) {
                continue;
            }

            $result = $service->run_now($id, $type);
            if (is_wp_error($result)) {
                $errors[] = sprintf(
                    /* translators: 1: ID, 2: error */
                    __('ID %1$d (%2$s): %3$s', 'ai-post-scheduler'),
                    $id,
                    $type,
                    $result->get_error_message()
                );
            } else {
                $success++;
            }
        }

        if ($success === 0 && !empty($errors)) {
            AIPS_Ajax_Response::error(array(
                'message' => __('All scheduled runs failed.', 'ai-post-scheduler'),
                'errors'  => $errors,
            ));
        }

        $message = sprintf(
            _n('%d schedule ran successfully!', '%d schedules ran successfully!', $success, 'ai-post-scheduler'),
            $success
        );
        if (!empty($errors)) {
            $message .= ' ' . sprintf(
                _n('(%d failed)', '(%d failed)', count($errors), 'ai-post-scheduler'),
                count($errors)
            );
        }

        AIPS_Ajax_Response::success(array(
            'message' => $message,
            'success' => $success,
            'errors'  => $errors,
        ));
    }

    /**
     * AJAX: Bulk delete schedules of mixed types.
     *
     * Expects POST: items (array of {id, type}).
     * Only template schedules are deletable.
     */
    public function ajax_schedule_bulk_delete() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $items = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : array();

        if (empty($items)) {
            AIPS_Ajax_Response::error(__('No items provided.', 'ai-post-scheduler'));
        }

        $service       = new AIPS_Schedule_Service();
        $deleted_count = 0;
        $deleted_items = array();
        $failed_items  = array();

        foreach ($items as $item) {
            $id   = isset($item['id']) ? absint($item['id']) : 0;
            $type = isset($item['type']) ? sanitize_key($item['type']) : '';

            if (!$id || empty($type)) {
                continue;
            }

            $result = $service->delete($id, $type);
            if (is_wp_error($result)) {
                $failed_items[] = array(
                    'id'      => $id,
                    'type'    => $type,
                    'message' => $result->get_error_message(),
                );
            } else {
                $deleted_count++;
                $deleted_items[] = array(
                    'id'   => $id,
                    'type' => $type,
                );
            }
        }

        if ($deleted_count === 0) {
            AIPS_Ajax_Response::error(array(
                'message'      => __('No selected schedules could be deleted.', 'ai-post-scheduler'),
                'deleted'      => 0,
                'deleted_items'=> array(),
                'failed_items' => $failed_items,
            ));
        }

        $message = sprintf(
            _n('%d schedule deleted successfully.', '%d schedules deleted successfully.', $deleted_count, 'ai-post-scheduler'),
            $deleted_count
        );

        if (!empty($failed_items)) {
            $message .= ' ' . sprintf(
                _n('(%d could not be deleted)', '(%d could not be deleted)', count($failed_items), 'ai-post-scheduler'),
                count($failed_items)
            );
        }

        AIPS_Ajax_Response::success(array(
            'message'      => $message,
            'deleted'      => $deleted_count,
            'deleted_items'=> $deleted_items,
            'failed_items' => $failed_items,
        ));
    }

    /**
     * AJAX: Get run-history for any schedule type.
     *
     * Expects POST: id (int), type (string).
     */
    public function ajax_get_schedule_history() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $id    = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $type  = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 0;

        if (!$id || empty($type)) {
            AIPS_Ajax_Response::error(__('Invalid parameters.', 'ai-post-scheduler'));
        }

        $service = new AIPS_Schedule_Service();
        $entries = $service->get_history($id, $type, $limit);

        AIPS_Ajax_Response::success(array('entries' => $entries));
    }
}
