<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Queue_Controller {

    private $queue_table;

    public function __construct() {
        global $wpdb;
        $this->queue_table = $wpdb->prefix . 'aips_schedule_queue';

        add_action('wp_ajax_aips_add_to_queue', array($this, 'ajax_add_to_queue'));
        add_action('wp_ajax_aips_get_queue_stats', array($this, 'ajax_get_queue_stats'));
        add_action('wp_ajax_aips_generate_topic_ideas', array($this, 'ajax_generate_topic_ideas'));
    }

    public function ajax_add_to_queue() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $schedule_id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;
        $topics = isset($_POST['topics']) ? $_POST['topics'] : array();

        if ($schedule_id <= 0) {
            wp_send_json_error(array('message' => __('Schedule ID is required.', 'ai-post-scheduler')));
        } elseif (!is_array($topics)) {
            wp_send_json_error(array('message' => __('Topics must be an array.', 'ai-post-scheduler')));
        } elseif (empty($topics)) {
            wp_send_json_error(array('message' => __('Topics array is empty.', 'ai-post-scheduler')));
        }

        global $wpdb;
        $inserted = 0;
        $now = current_time('mysql');

        foreach ($topics as $topic) {
            $topic = sanitize_text_field($topic);
            if (empty($topic)) continue;

            $result = $wpdb->insert(
                $this->queue_table,
                array(
                    'schedule_id' => $schedule_id,
                    'topic' => $topic,
                    'status' => 'pending',
                    'created_at' => $now
                ),
                array('%d', '%s', '%s', '%s')
            );

            if ($result) {
                $inserted++;
            }
        }

        wp_send_json_success(array(
            'message' => sprintf(__('%d topics added to queue.', 'ai-post-scheduler'), $inserted),
            'count' => $inserted
        ));
    }

    public function ajax_get_queue_stats() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $schedule_id = isset($_GET['schedule_id']) ? absint($_GET['schedule_id']) : 0;

        global $wpdb;
        $pending = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$this->queue_table}
            WHERE schedule_id = %d AND status = 'pending'
        ", $schedule_id));

        wp_send_json_success(array(
            'pending_count' => (int)$pending
        ));
    }

    /**
     * Generate Topic Ideas via AI
     */
    public function ajax_generate_topic_ideas() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $keywords = isset($_POST['keywords']) ? sanitize_text_field($_POST['keywords']) : '';
        if (empty($keywords)) {
            wp_send_json_error(array('message' => 'Keywords are required.'));
        }

        // Use AIPS_Generator to get topics
        // We might need to extend AIPS_Generator or AIPS_Content_Generator to support this specifically if not exists.
        // AIPS_Generator::generate_topics exists? Let's check.
        // If not, we use a prompt here.

        $generator = new AIPS_Generator();
        // Assuming generate_topics exists or we create a prompt

        // Construct a prompt for topics
        $prompt = "Generate 10 engaging blog post topics based on these keywords: '{$keywords}'. Return ONLY the topics as a JSON array of strings. Do not number them.";

        $ai_service = new AIPS_AI_Service();
        $response = $ai_service->generate_text($prompt);

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        // Clean and parse JSON
        $json_str = $this->extract_json_array($response);
        $topics = json_decode($json_str, true);

        if (!is_array($topics)) {
            // Fallback: split by newlines if JSON fails
            $topics = array_filter(array_map('trim', explode("\n", $response)));
        }

        wp_send_json_success(array(
            'topics' => array_slice($topics, 0, 10) // Limit to 10
        ));
    }

    private function extract_json_array($text) {
        $length     = strlen($text);
        $depth      = 0;
        $start      = null;
        $in_string  = false;
        $escape     = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];

            // Handle escaping inside strings
            if ($in_string) {
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($char === '\\') {
                    $escape = true;
                    continue;
                }
                if ($char === '"') {
                    $in_string = false;
                }
                continue;
            } else {
                if ($char === '"') {
                    $in_string = true;
                    $escape    = false;
                    continue;
                }
            }

            // Outside of strings: track array brackets
            if ($char === '[') {
                if ($depth === 0) {
                    $start = $i;
                }
                $depth++;
            } elseif ($char === ']' && $depth > 0) {
                $depth--;
                if ($depth === 0 && $start !== null) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        // No complete JSON array found
        return '[]';
    }
}
