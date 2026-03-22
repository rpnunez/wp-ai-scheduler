<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Planner {

    public function __construct() {
        add_action('wp_ajax_aips_generate_topics', array($this, 'ajax_generate_topics'));
        add_action('wp_ajax_aips_bulk_schedule', array($this, 'ajax_bulk_schedule'));
    }

    public function ajax_generate_topics() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $niche = isset($_POST['niche']) ? sanitize_text_field($_POST['niche']) : '';
        $count = isset($_POST['count']) ? absint($_POST['count']) : 10;

        if (empty($niche)) {
            wp_send_json_error(array('message' => __('Please provide a niche or topic.', 'ai-post-scheduler')));
        }

        if ($count < 1 || $count > 50) {
            $count = 10;
        }

        $ai_service = new AIPS_AI_Service();
        if (!$ai_service->is_available()) {
            wp_send_json_error(array('message' => __('AI Engine is not available.', 'ai-post-scheduler')));
        }

        $prompt = "Generate a list of {$count} unique, engaging blog post titles/topics about '{$niche}'.";

        $result = $ai_service->generate_json($prompt, array('temperature' => 0.7, 'max_tokens' => 1000));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // generate_json returns a parsed array; normalise to a flat list of strings
        $topics = array();
        if (is_array($result)) {
            foreach ($result as $item) {
                if (is_string($item) && !empty(trim($item))) {
                    $topics[] = sanitize_text_field(trim($item));
                } elseif (is_array($item)) {
                    // Handle objects with a title/topic key
                    if (!empty($item['title'])) {
                        $topics[] = sanitize_text_field($item['title']);
                    } elseif (!empty($item['topic'])) {
                        $topics[] = sanitize_text_field($item['topic']);
                    }
                }
            }
        }

        if (empty($topics)) {
            wp_send_json_error(array(
                'message' => __('Failed to parse AI response into topics.', 'ai-post-scheduler'),
            ));
        }

        do_action('aips_planner_topics_generated', $topics, $niche);

        wp_send_json_success(array('topics' => $topics));
    }

    public function ajax_bulk_schedule() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $topics = isset($_POST['topics']) ? (array) $_POST['topics'] : array();
        $template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $frequency = isset($_POST['frequency']) ? sanitize_text_field($_POST['frequency']) : 'daily';

        if (empty($topics) || empty($template_id) || empty($start_date)) {
            wp_send_json_error(array('message' => __('Missing required fields.', 'ai-post-scheduler')));
        }

        // Sanitize topics
        $topics = array_map('sanitize_text_field', $topics);

        $scheduler = new AIPS_Scheduler();
        $count = 0;
        $base_time = strtotime($start_date);

        // Determine interval in seconds
        $intervals = $scheduler->get_intervals();
        $interval = 86400; // default fallback

        if (isset($intervals[$frequency])) {
            $interval = $intervals[$frequency]['interval'];
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'aips_schedule';

        // Optimization: Use single bulk INSERT query instead of loop
        // This reduces N database calls to 1, significantly improving performance for large batches
        $schedules = array();

        foreach ($topics as $index => $topic) {
            $next_run_timestamp = $base_time + ($index * $interval);
            $next_run = date('Y-m-d H:i:s', $next_run_timestamp);

            $schedules[] = array(
                'template_id' => $template_id,
                'frequency' => 'once',
                'next_run' => $next_run,
                'is_active' => 1,
                'topic' => $topic
            );
        }

        $count = $scheduler->save_schedule_bulk($schedules);

        if ($count === false || $count === 0) {
            wp_send_json_error(array('message' => __('Failed to schedule topics.', 'ai-post-scheduler')));
        }

        do_action('aips_planner_bulk_scheduled', $count, $template_id);

        wp_send_json_success(array(
            'message' => sprintf(__('%d topics scheduled successfully.', 'ai-post-scheduler'), $count),
            'count' => $count
        ));
    }

    public function render_page() {
        // Just for consistency if we want to render the planner page separately,
        // but currently we might include it in the main admin view.
        $templates_obj = new AIPS_Templates();
        $templates = $templates_obj->get_all(true); // Active only

        include AIPS_PLUGIN_DIR . 'templates/admin/planner.php';
    }
}
