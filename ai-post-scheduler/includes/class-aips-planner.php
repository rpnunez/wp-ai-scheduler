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

        $generator = new AIPS_Generator();
        if (!$generator->is_available()) {
            wp_send_json_error(array('message' => __('AI Engine is not available.', 'ai-post-scheduler')));
        }

        $prompt = "Generate a list of {$count} unique, engaging blog post titles/topics about '{$niche}'. \n";
        $prompt .= "Return ONLY a valid JSON array of strings. Do not include any other text, markdown formatting, or numbering. \n";
        $prompt .= "Example: [\"Topic 1\", \"Topic 2\", \"Topic 3\"]";

        $result = $generator->generate_content($prompt, array('temperature' => 0.7, 'max_tokens' => 1000), 'planner_topics');

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Clean up the result to ensure it's valid JSON
        $json_str = trim($result);
        // Remove potential markdown code blocks
        $json_str = preg_replace('/^```json/', '', $json_str);
        $json_str = preg_replace('/^```/', '', $json_str);
        $json_str = preg_replace('/```$/', '', $json_str);
        $json_str = trim($json_str);

        $topics = json_decode($json_str);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($topics)) {
            // Fallback: try to parse line by line if JSON fails
            $topics = array_filter(array_map('trim', explode("\n", $json_str)));
            // Remove empty lines and lines that look like list markers if strictly splitting by newline
            // But if the AI followed instructions, it should be JSON.
            // If it failed JSON, let's just log it and return error or try best effort.
            if (empty($topics)) {
                wp_send_json_error(array(
                    'message' => __('Failed to parse AI response. Raw response: ', 'ai-post-scheduler') . substr($json_str, 0, 100) . '...'
                ));
            }
        }

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
        $table_name = class_exists('AIPS_DB_Tables') ? AIPS_DB_Tables::get('aips_schedule') : $wpdb->prefix . 'aips_schedule';

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
