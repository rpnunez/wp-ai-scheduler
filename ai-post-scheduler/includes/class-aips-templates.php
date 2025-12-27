<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Templates {
    
    private $table_name;
    
    /**
     * @var AIPS_Template_Repository Repository for database operations
     */
    private $repository;
    
    /**
     * @var AIPS_Interval_Calculator Service for interval calculations
     */
    private $interval_calculator;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aips_templates';
        $this->repository = new AIPS_Template_Repository();
        $this->interval_calculator = new AIPS_Interval_Calculator();
    }
    
    public function get_all($active_only = false) {
        return $this->repository->get_all($active_only);
    }
    
    public function get($id) {
        return $this->repository->get_by_id($id);
    }
    
    public function save($data) {
        $template_data = array(
            'name' => sanitize_text_field($data['name']),
            'prompt_template' => wp_kses_post($data['prompt_template']),
            'title_prompt' => isset($data['title_prompt']) ? sanitize_text_field($data['title_prompt']) : '',
            'voice_id' => isset($data['voice_id']) ? absint($data['voice_id']) : null,
            'post_quantity' => isset($data['post_quantity']) ? absint($data['post_quantity']) : 1,
            'image_prompt' => isset($data['image_prompt']) ? wp_kses_post($data['image_prompt']) : '',
            'generate_featured_image' => isset($data['generate_featured_image']) ? 1 : 0,
            'post_status' => sanitize_text_field($data['post_status']),
            'post_category' => absint($data['post_category']),
            'post_tags' => isset($data['post_tags']) ? sanitize_text_field($data['post_tags']) : '',
            'post_author' => isset($data['post_author']) ? absint($data['post_author']) : get_current_user_id(),
            'is_active' => isset($data['is_active']) ? 1 : 0,
        );
        
        if (!empty($data['id'])) {
            $this->repository->update(absint($data['id']), $template_data);
            return absint($data['id']);
        } else {
            return $this->repository->create($template_data);
        }
    }
    
    public function delete($id) {
        return $this->repository->delete($id);
    }

    public function get_pending_stats($template_id) {
        global $wpdb;
        $table_schedule = $wpdb->prefix . 'aips_schedule';

        $schedules = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_schedule WHERE template_id = %d AND is_active = 1",
            $template_id
        ));

        $stats = array(
            'today' => 0,
            'week' => 0,
            'month' => 0
        );

        if (empty($schedules)) {
            return $stats;
        }

        $now = current_time('timestamp');
        $today_end = strtotime('today 23:59:59', $now);
        $week_end = strtotime('+7 days', $now);
        $month_end = strtotime('+30 days', $now);

        foreach ($schedules as $schedule) {
            $cursor = strtotime($schedule->next_run);
            $frequency = $schedule->frequency;

            // Limit iterations to prevent infinite loops or excessive processing
            $max_iterations = 100;
            $i = 0;

            while ($cursor <= $month_end && $i < $max_iterations) {
                if ($cursor < $now) {
                    // Skip past events that haven't run yet but update cursor?
                    // Actually if next_run is in past, it will run next cron.
                    // So count it as imminent.
                }

                if ($cursor <= $today_end) {
                    $stats['today']++;
                }

                if ($cursor <= $week_end) {
                    $stats['week']++;
                }

                if ($cursor <= $month_end) {
                    $stats['month']++;
                } else {
                    break;
                }

                if ($frequency === 'once') {
                    break;
                }

                // Calculate next run using AIPS_Interval_Calculator
                $cursor_date = date('Y-m-d H:i:s', $cursor);
                $next_date = $this->interval_calculator->calculate_next_run($frequency, $cursor_date);
                $cursor = strtotime($next_date);
                $i++;
            }
        }

        return $stats;
    }

    public function get_all_pending_stats() {
        global $wpdb;
        $table_schedule = $wpdb->prefix . 'aips_schedule';

        // Get all active schedules ordered by template_id
        $schedules = $wpdb->get_results("SELECT * FROM $table_schedule WHERE is_active = 1 ORDER BY template_id");

        $stats = array();
        if (empty($schedules)) {
            return $stats;
        }

        $now = current_time('timestamp');
        $today_end = strtotime('today 23:59:59', $now);
        $week_end = strtotime('+7 days', $now);
        $month_end = strtotime('+30 days', $now);

        foreach ($schedules as $schedule) {
            $tid = $schedule->template_id;
            if (!isset($stats[$tid])) {
                $stats[$tid] = array('today' => 0, 'week' => 0, 'month' => 0);
            }

            $cursor = strtotime($schedule->next_run);
            $frequency = $schedule->frequency;

            // Limit iterations to prevent infinite loops or excessive processing
            $max_iterations = 100;
            $i = 0;

            while ($cursor <= $month_end && $i < $max_iterations) {
                // If cursor is in the past, it's considered imminent (Today)
                if ($cursor <= $today_end) {
                    $stats[$tid]['today']++;
                }

                if ($cursor <= $week_end) {
                    $stats[$tid]['week']++;
                }

                if ($cursor <= $month_end) {
                    $stats[$tid]['month']++;
                } else {
                    break;
                }

                if ($frequency === 'once') {
                    break;
                }

                // Calculate next run using AIPS_Interval_Calculator
                $cursor_date = date('Y-m-d H:i:s', $cursor);
                $next_date = $this->interval_calculator->calculate_next_run($frequency, $cursor_date);
                $cursor = strtotime($next_date);
                $i++;
            }
        }

        return $stats;
    }
    
    public function render_page() {
        $templates = $this->get_all();
        $categories = get_categories(array('hide_empty' => false));
        $users = get_users(array('role__in' => array('administrator', 'editor', 'author')));
        
        include AIPS_PLUGIN_DIR . 'templates/admin/main.php';
    }
}
