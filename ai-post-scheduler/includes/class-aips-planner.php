<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Planner {

    /**
     * @var AIPS_Bulk_Generator_Service Shared bulk generation harness.
     */
    private $bulk_generator_service;

    public function __construct() {
        $this->bulk_generator_service = $this->make_bulk_generator_service();
        add_action('wp_ajax_aips_generate_topics', array($this, 'ajax_generate_topics'));
        add_action('wp_ajax_aips_bulk_schedule', array($this, 'ajax_bulk_schedule'));
        add_action('wp_ajax_aips_bulk_generate_now', array($this, 'ajax_bulk_generate_now'));
    }

    public function ajax_generate_topics() {
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $niche = isset($_POST['niche']) ? sanitize_text_field(wp_unslash($_POST['niche'])) : '';
        $count = isset($_POST['count']) ? absint($_POST['count']) : 10;

        if (empty($niche)) {
            AIPS_Ajax_Response::error(__('Please provide a niche or topic.', 'ai-post-scheduler'));
        }

        if ($count < 1 || $count > 50) {
            $count = 10;
        }

        $generator = new AIPS_Generator();
        if (!$generator->is_available()) {
            AIPS_Ajax_Response::error(__('AI Engine is not available.', 'ai-post-scheduler'));
        }

        $prompt = "Generate a list of {$count} unique, engaging blog post titles/topics about '{$niche}'. \n";
        $prompt .= "Return ONLY a valid JSON array of strings. Do not include any other text, markdown formatting, or numbering. \n";
        $prompt .= "Example: [\"Topic 1\", \"Topic 2\", \"Topic 3\"]";

        $result = $generator->generate_content($prompt, array('temperature' => 0.7), 'planner_topics');

        if (is_wp_error($result)) {
            AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
        }

        // Normalize the raw AI response and guard against empty output. Coerce to
        // string to avoid deprecated warnings when AI responses are null.
        $raw_result = (string) $result;

        if ('' === trim($raw_result)) {
            AIPS_Ajax_Response::error(array(
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
                AIPS_Ajax_Response::error(array(
                    'message' => __('Failed to parse AI response. Raw response: ', 'ai-post-scheduler') . substr($json_str, 0, 100) . '...'
                ));
            }
        }

        do_action('aips_planner_topics_generated', $topics, $niche);

        AIPS_Ajax_Response::success(array('topics' => $topics));
    }

    public function ajax_bulk_schedule() {
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $topics = isset($_POST['topics']) ? wp_unslash((array) $_POST['topics']) : array();
        $template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $frequency = isset($_POST['frequency']) ? sanitize_text_field(wp_unslash($_POST['frequency'])) : 'daily';

        if (empty($topics) || empty($template_id) || empty($start_date)) {
            AIPS_Ajax_Response::error(__('Missing required fields.', 'ai-post-scheduler'));
        }

        // Sanitize topics
        $topics = AIPS_Utilities::sanitize_string_array($topics);

        $scheduler = $this->make_scheduler();
        $count = 0;
        $base_time = strtotime($start_date);

        // Optimization: Use single bulk INSERT query instead of loop
        // This reduces N database calls to 1, significantly improving performance for large batches
        $schedules = array();
        $next_run = date('Y-m-d H:i:s', $base_time);

        foreach ($topics as $topic) {
            $schedules[] = array(
                'template_id' => $template_id,
                'frequency' => 'once',
                'next_run' => $next_run,
                'is_active' => 1,
                'topic' => $topic
            );
        }

        $count = $schedule_repository->create_bulk($schedules);

        if ($count === false || $count === 0) {
            AIPS_Ajax_Response::error(__('Failed to schedule topics.', 'ai-post-scheduler'));
        }

        do_action('aips_planner_bulk_scheduled', $count, $template_id);

        AIPS_Ajax_Response::success(array(
            'message' => sprintf(__('%d topics scheduled successfully.', 'ai-post-scheduler'), $count),
            'count' => $count
        ));
    }

    public function ajax_bulk_generate_now() {
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $raw_topics  = isset($_POST['topics']) ? wp_unslash((array) $_POST['topics']) : array();
        $topics      = array_values(array_filter(AIPS_Utilities::sanitize_string_array($raw_topics)));
        $template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;

        if (empty($topics) || empty($template_id)) {
            AIPS_Ajax_Response::error(__('Missing required fields.', 'ai-post-scheduler'));
        }

        // Enforce the bulk limit BEFORE the expensive template lookup so the
        // error is returned immediately (this also preserves original method ordering
        // that existing tests depend on).
        $max_bulk = absint(apply_filters('aips_bulk_run_now_limit', 5));
        if ($max_bulk < 1) {
            $max_bulk = 5;
        }
        if (count($topics) > $max_bulk) {
            AIPS_Ajax_Response::error(array(
                'message' => sprintf(
                    /* translators: 1: selected count, 2: max allowed */
                    __('Too many topics selected (%1$d). Please select no more than %2$d at a time for immediate generation, or use "Schedule Selected Topics" instead.', 'ai-post-scheduler'),
                    count($topics),
                    $max_bulk
                ),
            ));
            return;
        }

        $template = $this->get_template_by_id($template_id);

        if (!$template) {
            AIPS_Ajax_Response::error(__('Template not found.', 'ai-post-scheduler'));
        }

        $generator = $this->make_generator();

        if (!$generator->is_available()) {
            AIPS_Ajax_Response::error(__('AI Engine is not available.', 'ai-post-scheduler'));
        }

        // Pass a matching limit so the service never rejects (pre-check already done above).
        $result = $this->bulk_generator_service->run(
            $topics,
            function ( $topic ) use ( $generator, $template ) {
                return $generator->generate_post($template, null, $topic);
            },
            array(
                'limit_default'   => $max_bulk,
                'history_type'    => 'bulk_generate_now',
                'trigger_name'    => 'ajax_bulk_generate_now',
                'user_action'     => 'bulk_generate_now',
                'user_message'    => sprintf(
                    /* translators: %d: number of topics */
                    __('User initiated bulk generation for %d topics', 'ai-post-scheduler'),
                    count($topics)
                ),
                'error_formatter' => function ( $topic, $msg ) {
                    /* translators: 1: topic string, 2: error message */
                    return sprintf(__('Topic "%1$s": %2$s', 'ai-post-scheduler'), $topic, $msg);
                },
            )
        );

        if (empty($result->post_ids) && !empty($result->errors)) {
            AIPS_Ajax_Response::error(array(
                'message' => __('All topic generations failed.', 'ai-post-scheduler'),
                'errors'  => $result->errors,
            ));
            return;
        }

        $message = sprintf(
            /* translators: %d: number of posts */
            _n('%d post generated successfully!', '%d posts generated successfully!', count($result->post_ids), 'ai-post-scheduler'),
            count($result->post_ids)
        );

        if (!empty($result->errors)) {
            $message .= ' ' . sprintf(
                /* translators: %d: number of failed topics */
                _n('(%d failed)', '(%d failed)', count($result->errors), 'ai-post-scheduler'),
                count($result->errors)
            );
        }

        AIPS_Ajax_Response::success(array(
            'message'  => $message,
            'post_ids' => $result->post_ids,
            'errors'   => $result->errors,
        ));
    }

    /**
     * Factory method for AIPS_Scheduler. Overrideable in tests.
     *
     * @return AIPS_Scheduler
     */
    protected function make_scheduler() {
        return new AIPS_Scheduler();
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
     * Factory method for AIPS_Bulk_Generator_Service. Overrideable in tests.
     *
     * @return AIPS_Bulk_Generator_Service
     */
    protected function make_bulk_generator_service() {
        return new AIPS_Bulk_Generator_Service();
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
}
