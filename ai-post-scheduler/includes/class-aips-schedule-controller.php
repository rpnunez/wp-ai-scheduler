<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AIPS Schedule Controller
 *
 * Provides both traditional AJAX and HTMX-compatible admin-ajax endpoints.
 * Traditional endpoints return JSON, HTMX endpoints return HTML fragments.
 */
class AIPS_Schedule_Controller {

    private $scheduler;

    public function __construct($scheduler = null) {
        $this->scheduler = $scheduler ?: new AIPS_Scheduler();

        // Traditional AJAX endpoints (return JSON)
        add_action('wp_ajax_aips_save_schedule', array($this, 'ajax_save_schedule'));
        add_action('wp_ajax_aips_delete_schedule', array($this, 'ajax_delete_schedule'));
        add_action('wp_ajax_aips_toggle_schedule', array($this, 'ajax_toggle_schedule'));
        add_action('wp_ajax_aips_run_now', array($this, 'ajax_run_now'));

        // HTMX endpoints (return HTML fragments)
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_aips_htmx_get_schedules', array($this, 'ajax_get_schedules'));
        add_action('wp_ajax_aips_htmx_toggle_schedule', array($this, 'ajax_htmx_toggle_schedule'));
        add_action('wp_ajax_aips_htmx_delete_schedule', array($this, 'ajax_htmx_delete_schedule'));
    }

    public function enqueue_assets($hook_suffix) {
        // Load assets only on plugin admin pages. Adjust detection to your admin hooks as needed.
        if (strpos($hook_suffix, 'aips') === false && strpos($hook_suffix, 'ai-post-scheduler') === false) {
            return;
        }

        // HTMX (CDN)
        wp_register_script('aips-htmx', 'https://unpkg.com/htmx.org@1.10.0', array(), '1.10.0', true);
        wp_enqueue_script('aips-htmx');

        // Local init script
        $init_path = AIPS_PLUGIN_URL . 'assets/js/aips-htmx-init.js';
        wp_register_script('aips-htmx-init', $init_path, array('aips-htmx'), AIPS_VERSION, true);

        wp_localize_script('aips-htmx-init', 'AIPS_HTMX', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('aips_ajax_nonce'),
        ));

        wp_enqueue_script('aips-htmx-init');
    }

    /**
     * Return the schedules list fragment (HTML) for HTMX to swap.
     */
    public function ajax_get_schedules() {
        // Accept nonce from X-WP-Nonce header or fallback to standard 'nonce' param
        $header_nonce = isset($_SERVER['HTTP_X_WP_NONCE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_WP_NONCE'])) : '';
        if (empty($header_nonce) || !wp_verify_nonce($header_nonce, 'aips_ajax_nonce')) {
            check_ajax_referer('aips_ajax_nonce', 'nonce');
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'ai-post-scheduler'));
        }

        $scheduler = new AIPS_Scheduler();
        $schedules = $scheduler->get_all_schedules();

        // Reuse existing template markup where possible. We'll produce a table fragment.
        ob_start();
        ?>
        <div id="aips-schedules-list">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html(__('Template', 'ai-post-scheduler')); ?></th>
                        <th><?php echo esc_html(__('Frequency', 'ai-post-scheduler')); ?></th>
                        <th><?php echo esc_html(__('Next Run', 'ai-post-scheduler')); ?></th>
                        <th><?php echo esc_html(__('Status', 'ai-post-scheduler')); ?></th>
                        <th><?php echo esc_html(__('Actions', 'ai-post-scheduler')); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($schedules)) : ?>
                    <tr>
                        <td colspan="5"><?php echo esc_html__('No schedules found.', 'ai-post-scheduler'); ?></td>
                    </tr>
                <?php else :
                    $structure_repo = new AIPS_Article_Structure_Repository();
                    foreach ($schedules as $schedule) :
                        $id = isset($schedule->id) ? intval($schedule->id) : intval($schedule['id']);
                        $template_label = '';
                        if (!empty($schedule->template_id)) {
                            $templates_handler = new AIPS_Templates();
                            $t = $templates_handler->get_by_id($schedule->template_id);
                            $template_label = $t ? $t->name : sprintf('#%d', $schedule->template_id);
                        }
                        $frequency = isset($schedule->frequency) ? esc_html($schedule->frequency) : '';
                        $next_run = isset($schedule->next_run) ? esc_html($schedule->next_run) : '';
                        $is_active = !empty($schedule->is_active);
                        ?>
                        <tr id="aips-schedule-<?php echo esc_attr($id); ?>">
                            <td><?php echo esc_html($template_label); ?></td>
                            <td><?php echo $frequency; ?></td>
                            <td><?php echo $next_run; ?></td>
                            <td>
                                <button class="button"
                                    hx-post="<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=aips_htmx_toggle_schedule"
                                    hx-vals='{"id":"<?php echo esc_attr($id); ?>"}'
                                    hx-headers='{"X-WP-Nonce":"<?php echo esc_attr(wp_create_nonce('aips_ajax_nonce')); ?>"}'
                                    hx-swap="outerHTML">
                                    <?php echo $is_active ? esc_html__('Active', 'ai-post-scheduler') : esc_html__('Paused', 'ai-post-scheduler'); ?>
                                </button>
                            </td>
                            <td>
                                <button class="button aips-delete"
                                    hx-post="<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=aips_htmx_delete_schedule"
                                    hx-vals='{"id":"<?php echo esc_attr($id); ?>"}'
                                    hx-headers='{"X-WP-Nonce":"<?php echo esc_attr(wp_create_nonce('aips_ajax_nonce')); ?>"}'
                                    hx-swap="none"
                                    hx-on="htmx:afterRequest:removeRow('aips-schedule-<?php echo esc_js($id); ?>')">
                                    <?php echo esc_html__('Delete', 'ai-post-scheduler'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <script>
        function removeRow(id) {
            var el = document.getElementById(id);
            if (el) { el.parentNode.removeChild(el); }
        }
        </script>
        <?php
        $html = ob_get_clean();
        echo $html;

        wp_die();
    }

    public function ajax_htmx_toggle_schedule() {
        $header_nonce = isset($_SERVER['HTTP_X_WP_NONCE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_WP_NONCE'])) : '';
        if (empty($header_nonce) || !wp_verify_nonce($header_nonce, 'aips_ajax_nonce')) {
            check_ajax_referer('aips_ajax_nonce', 'nonce');
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'ai-post-scheduler'));
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id) {
            wp_die(__('Invalid schedule ID', 'ai-post-scheduler'));
        }

        $repo = new AIPS_Schedule_Repository();
        $sched = $repo->get_by_id($id);
        if (!$sched) {
            wp_die(__('Schedule not found', 'ai-post-scheduler'));
        }

        $new_active = empty($sched->is_active) ? 1 : 0;
        $repo->update($id, array('is_active' => $new_active));

        // Return updated button fragment
        if ($new_active) {
            echo '<button class="button" hx-post="' . esc_url(admin_url('admin-ajax.php')) . '?action=aips_htmx_toggle_schedule" hx-vals=\'{"id":"' . esc_attr($id) . '"}\' hx-headers=\'{"X-WP-Nonce":"' . esc_attr(wp_create_nonce('aips_ajax_nonce')) . '"}\' hx-swap="outerHTML">' . esc_html__('Active', 'ai-post-scheduler') . '</button>';
        } else {
            echo '<button class="button" hx-post="' . esc_url(admin_url('admin-ajax.php')) . '?action=aips_htmx_toggle_schedule" hx-vals=\'{"id":"' . esc_attr($id) . '"}\' hx-headers=\'{"X-WP-Nonce":"' . esc_attr(wp_create_nonce('aips_ajax_nonce')) . '"}\' hx-swap="outerHTML">' . esc_html__('Paused', 'ai-post-scheduler') . '</button>';
        }

        wp_die();
    }

    public function ajax_htmx_delete_schedule() {
        $header_nonce = isset($_SERVER['HTTP_X_WP_NONCE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_WP_NONCE'])) : '';
        if (empty($header_nonce) || !wp_verify_nonce($header_nonce, 'aips_ajax_nonce')) {
            check_ajax_referer('aips_ajax_nonce', 'nonce');
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'ai-post-scheduler'));
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id) {
            wp_die(__('Invalid schedule ID', 'ai-post-scheduler'));
        }

        $repo = new AIPS_Schedule_Repository();
        $repo->delete($id);

        // Return empty (client removes row)
        wp_die();
    }

    // Traditional AJAX endpoints (return JSON) - maintained for backward compatibility

    public function ajax_save_schedule() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $data = array(
            'id' => isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0,
            'template_id' => isset($_POST['template_id']) ? absint($_POST['template_id']) : 0,
            'frequency' => isset($_POST['frequency']) ? sanitize_text_field($_POST['frequency']) : 'daily',
            'start_time' => isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'topic' => isset($_POST['topic']) ? sanitize_text_field($_POST['topic']) : '',
            'article_structure_id' => isset($_POST['article_structure_id']) && $_POST['article_structure_id'] !== '' ? absint($_POST['article_structure_id']) : null,
            'rotation_pattern' => isset($_POST['rotation_pattern']) && $_POST['rotation_pattern'] !== '' ? sanitize_text_field($_POST['rotation_pattern']) : null,
        );

        if (empty($data['template_id'])) {
            wp_send_json_error(array('message' => __('Please select a template.', 'ai-post-scheduler')));
        }

        $interval_calculator = new AIPS_Interval_Calculator();
        if (!$interval_calculator->is_valid_frequency($data['frequency'])) {
            wp_send_json_error(array('message' => __('Invalid frequency selected.', 'ai-post-scheduler')));
        }

        $id = $this->scheduler->save_schedule($data);

        if ($id) {
            wp_send_json_success(array(
                'message' => __('Schedule saved successfully.', 'ai-post-scheduler'),
                'schedule_id' => $id
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to save schedule.', 'ai-post-scheduler')));
        }
    }

    public function ajax_delete_schedule() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;

        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid schedule ID.', 'ai-post-scheduler')));
        }

        if ($this->scheduler->delete_schedule($id)) {
            wp_send_json_success(array('message' => __('Schedule deleted successfully.', 'ai-post-scheduler')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete schedule.', 'ai-post-scheduler')));
        }
    }

    public function ajax_toggle_schedule() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;
        $is_active = isset($_POST['is_active']) ? absint($_POST['is_active']) : 0;

        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid schedule ID.', 'ai-post-scheduler')));
        }

        $result = $this->scheduler->toggle_active($id, $is_active);

        if ($result !== false) {
            wp_send_json_success(array('message' => __('Schedule updated.', 'ai-post-scheduler')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update schedule.', 'ai-post-scheduler')));
        }
    }

    public function ajax_run_now() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;

        if (!$template_id) {
            wp_send_json_error(array('message' => __('Invalid template ID.', 'ai-post-scheduler')));
        }

        $templates = new AIPS_Templates();
        $template = $templates->get($template_id);

        if (!$template) {
            wp_send_json_error(array('message' => __('Template not found.', 'ai-post-scheduler')));
        }

        $voice = null;
        if (!empty($template->voice_id)) {
            $voices = new AIPS_Voices();
            $voice = $voices->get($template->voice_id);
        }

        $quantity = $template->post_quantity ?: 1;

        // SECURITY: Enforce a hard limit for immediate execution to prevent PHP timeouts
        // and potential API rate limiting issues.
        $max_run_now = 5;
        $capped = false;
        if ($quantity > $max_run_now) {
            $quantity = $max_run_now;
            $capped = true;
        }

        $post_ids = array();
        $errors = array();

        $generator = new AIPS_Generator();
        $topic = isset($_POST['topic']) ? sanitize_text_field($_POST['topic']) : '';

        // Enforce hard limit of 5 to prevent timeouts (Bolt)
        if ($quantity > 5) {
            $quantity = 5;
        }

        for ($i = 0; $i < $quantity; $i++) {
            $result = $generator->generate_post($template, $voice, $topic);

            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
            } else {
                $post_ids[] = $result;
            }
        }

        if (empty($post_ids) && !empty($errors)) {
            // All attempts failed
            $error_msg = count($errors) > 1
                ? __('All generation attempts failed.', 'ai-post-scheduler')
                : $errors[0];
            wp_send_json_error(array('message' => $error_msg, 'errors' => $errors));
        }

        $message = sprintf(
            __('%d post(s) generated successfully!', 'ai-post-scheduler'),
            count($post_ids)
        );

        if ($capped) {
            $message .= ' ' . sprintf(
                __('(Limited to %d for manual run)', 'ai-post-scheduler'),
                $max_run_now
            );
        }

        if (!empty($errors)) {
            $message .= ' ' . sprintf(
                __('(%d failed attempts)', 'ai-post-scheduler'),
                count($errors)
            );
        }

        wp_send_json_success(array(
            'message' => $message,
            'post_ids' => $post_ids,
            'errors' => $errors,
            'edit_url' => !empty($post_ids) ? get_edit_post_link($post_ids[0], 'raw') : ''
        ));
    }
}
