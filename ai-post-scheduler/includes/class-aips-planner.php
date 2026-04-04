<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Planner {

    public function __construct() {
        add_action('wp_ajax_aips_generate_topics', array($this, 'ajax_generate_topics'));
        add_action('wp_ajax_aips_bulk_schedule', array($this, 'ajax_bulk_schedule'));
        add_action('wp_ajax_aips_bulk_generate_now', array($this, 'ajax_bulk_generate_now'));
    }

    public function ajax_generate_topics() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $niche = isset($_POST['niche']) ? sanitize_text_field(wp_unslash($_POST['niche'])) : '';
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

        $result = $generator->generate_content($prompt, array('temperature' => 0.7, 'maxTokens' => 1000), 'planner_topics');

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Normalize the raw AI response and guard against empty output. Coerce to
        // string to avoid deprecated warnings when AI responses are null.
        $raw_result = (string) $result;

        if ('' === trim($raw_result)) {
            wp_send_json_error(array(
                'message' => __('AI did not return any topics. Please try again.', 'ai-post-scheduler'),
            ));
        }

        // Clean up the result to ensure it's valid JSON
        $json_str = trim($raw_result);
        // Remove potential markdown code blocks
        $json_str = preg_replace('/^```json/', '', $json_str);
        $json_str = preg_replace('/^```/', '', $json_str);
        $json_str = preg_replace('/```$/', '', $json_str);
        $json_str = trim($json_str);

        // First, try to parse the whole string as JSON.
        $topics = json_decode($json_str);

        // If that fails, try to extract the first JSON array substring.
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($topics)) {
            $first_bracket = strpos($json_str, '[');
            $last_bracket  = strrpos($json_str, ']');

            if ($first_bracket !== false && $last_bracket !== false && $last_bracket > $first_bracket) {
                $json_candidate = substr($json_str, $first_bracket, $last_bracket - $first_bracket + 1);
                $topics_candidate = json_decode($json_candidate);

                if (json_last_error() === JSON_ERROR_NONE && is_array($topics_candidate)) {
                    $topics = $topics_candidate;
                }
            }
        }

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

        do_action('aips_planner_topics_generated', $topics, $niche);

        wp_send_json_success(array('topics' => $topics));
    }

    public function ajax_bulk_schedule() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $topics = isset($_POST['topics']) ? wp_unslash((array) $_POST['topics']) : array();
        $template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $frequency = isset($_POST['frequency']) ? sanitize_text_field(wp_unslash($_POST['frequency'])) : 'daily';

        if (empty($topics) || empty($template_id) || empty($start_date)) {
            wp_send_json_error(array('message' => __('Missing required fields.', 'ai-post-scheduler')));
        }

        // Sanitize topics
        $topics = AIPS_Utilities::sanitize_string_array($topics);

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

    public function ajax_bulk_generate_now() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $raw_topics  = isset($_POST['topics']) ? wp_unslash((array) $_POST['topics']) : array();
        $topics      = array_values(array_filter(AIPS_Utilities::sanitize_string_array($raw_topics)));
        $template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;

        if (empty($topics) || empty($template_id)) {
            wp_send_json_error(array('message' => __('Missing required fields.', 'ai-post-scheduler')));
        }

        // Enforce a bulk limit for synchronous generation to avoid PHP timeouts
        $max_bulk = apply_filters('aips_bulk_run_now_limit', 5);
        $max_bulk = absint($max_bulk);
        if (0 === $max_bulk) {
            $max_bulk = 5;
        }
        if (count($topics) > $max_bulk) {
            wp_send_json_error(array(
                'message' => sprintf(
                    /* translators: 1: selected count, 2: max allowed */
                    __('Too many topics selected (%1$d). Please select no more than %2$d at a time for immediate generation, or use "Schedule Selected Topics" instead.', 'ai-post-scheduler'),
                    count($topics),
                    $max_bulk
                ),
            ));
        }

        $template = $this->get_template_by_id($template_id);

        if (!$template) {
            wp_send_json_error(array('message' => __('Template not found.', 'ai-post-scheduler')));
        }

        $generator = $this->make_generator();

        if (!$generator->is_available()) {
            wp_send_json_error(array('message' => __('AI Engine is not available.', 'ai-post-scheduler')));
        }

        $post_ids = array();
        $errors = array();

        foreach ($topics as $topic) {
            // Using legacy signature which generates a context inside AIPS_Generator
            $result = $generator->generate_post($template, null, $topic);

            if (is_wp_error($result)) {
                $errors[] = sprintf(__('Topic "%1$s": %2$s', 'ai-post-scheduler'), $topic, $result->get_error_message());
            } else {
                $post_ids[] = is_array($result) ? $result : (int) $result;
            }
        }

        if (empty($post_ids) && !empty($errors)) {
            wp_send_json_error(array(
                'message' => __('All topic generations failed.', 'ai-post-scheduler'),
                'errors'  => $errors,
            ));
        }

        $message = sprintf(
            /* translators: %d: number of posts */
            _n('%d post generated successfully!', '%d posts generated successfully!', count($post_ids), 'ai-post-scheduler'),
            count($post_ids)
        );

        if (!empty($errors)) {
            $message .= ' ' . sprintf(
                /* translators: %d: number of failed topics */
                _n('(%d failed)', '(%d failed)', count($errors), 'ai-post-scheduler'),
                count($errors)
            );
        }

        wp_send_json_success(array(
            'message'  => $message,
            'post_ids' => $post_ids,
            'errors'   => $errors,
        ));
    }

    /**
     * Factory method for AIPS_Generator. Overrideable in tests.
     *
     * @return AIPS_Generator
     */
    protected function make_generator() {
        return new AIPS_Generator();
    }

    /**
     * Retrieve a template by ID. Overrideable in tests.
     *
     * @param int $template_id
     * @return object|null
     */
    protected function get_template_by_id( $template_id ) {
        $templates = new AIPS_Templates();
        return $templates->get($template_id);
    }

    public function render_page() {
        // Just for consistency if we want to render the planner page separately,
        // but currently we might include it in the main admin view.
        $templates_obj = new AIPS_Templates();
        $templates = $templates_obj->get_all(true); // Active only

        include AIPS_PLUGIN_DIR . 'templates/admin/planner.php';
    }
}
