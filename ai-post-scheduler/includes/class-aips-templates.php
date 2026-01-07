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
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aips_templates';
        $this->repository = new AIPS_Template_Repository();
    }
    
    public function get_all($active_only = false) {
        return $this->repository->get_all($active_only);
    }
    
    public function get($id) {
        return $this->repository->get_by_id($id);
    }
    
    public function save($data) {
        $allowed_sources = array('ai_prompt', 'unsplash', 'media_library');
        $selected_source = isset($data['featured_image_source']) ? sanitize_text_field($data['featured_image_source']) : 'ai_prompt';
        $featured_image_source = in_array($selected_source, $allowed_sources, true) ? $selected_source : 'ai_prompt';

        $media_ids = '';
        if (!empty($data['featured_image_media_ids'])) {
            $parsed_ids = array_filter(array_map('absint', explode(',', $data['featured_image_media_ids'])));
            if (!empty($parsed_ids)) {
                $media_ids = implode(',', array_unique($parsed_ids));
            }
        }

        $template_data = array(
            'name' => sanitize_text_field($data['name']),
            'prompt_template' => wp_kses_post($data['prompt_template']),
            'title_prompt' => isset($data['title_prompt']) ? sanitize_text_field($data['title_prompt']) : '',
            'voice_id' => isset($data['voice_id']) ? absint($data['voice_id']) : null,
            'post_quantity' => isset($data['post_quantity']) ? absint($data['post_quantity']) : 1,
            'image_prompt' => isset($data['image_prompt']) ? wp_kses_post($data['image_prompt']) : '',
            'generate_featured_image' => isset($data['generate_featured_image']) ? 1 : 0,
            'featured_image_source' => $featured_image_source,
            'featured_image_unsplash_keywords' => isset($data['featured_image_unsplash_keywords']) ? sanitize_textarea_field($data['featured_image_unsplash_keywords']) : '',
            'featured_image_media_ids' => $media_ids,
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
            $counts = $this->calculate_stats_for_schedule(
                strtotime($schedule->next_run),
                $schedule->frequency,
                $today_end,
                $week_end,
                $month_end
            );

            $stats['today'] += $counts['today'];
            $stats['week'] += $counts['week'];
            $stats['month'] += $counts['month'];
        }

        return $stats;
    }

    public function get_all_pending_stats() {
        $cached_stats = get_transient('aips_pending_schedule_stats');
        if ($cached_stats !== false) {
            return $cached_stats;
        }

        global $wpdb;
        $table_schedule = $wpdb->prefix . 'aips_schedule';

        // Get all active schedules ordered by template_id
        // OPTIMIZATION: Only select necessary columns to reduce memory usage (Bolt)
        $schedules = $wpdb->get_results("SELECT template_id, next_run, frequency FROM $table_schedule WHERE is_active = 1 ORDER BY template_id");

        $stats = array();
        if (empty($schedules)) {
            set_transient('aips_pending_schedule_stats', $stats, HOUR_IN_SECONDS);
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

            $counts = $this->calculate_stats_for_schedule(
                strtotime($schedule->next_run),
                $schedule->frequency,
                $today_end,
                $week_end,
                $month_end
            );

            $stats[$tid]['today'] += $counts['today'];
            $stats[$tid]['week'] += $counts['week'];
            $stats[$tid]['month'] += $counts['month'];
        }

        set_transient('aips_pending_schedule_stats', $stats, HOUR_IN_SECONDS);
        return $stats;
    }

    /**
     * Calculate pending stats for a single schedule using O(1) math for fixed intervals.
     *
     * @param int $next_run_ts Next run timestamp
     * @param string $frequency Frequency identifier
     * @param int $today_end Timestamp for end of today
     * @param int $week_end Timestamp for end of week
     * @param int $month_end Timestamp for end of month
     * @return array Counts for today, week, month
     */
    private function calculate_stats_for_schedule($next_run_ts, $frequency, $today_end, $week_end, $month_end) {
        $counts = array('today' => 0, 'week' => 0, 'month' => 0);
        $cursor = $next_run_ts;

        // Handle single run
        if ($frequency === 'once') {
             if ($cursor <= $month_end) {
                 if ($cursor <= $today_end) $counts['today'] = 1;
                 if ($cursor <= $week_end) $counts['week'] = 1;
                 $counts['month'] = 1;
             }
             return $counts;
        }

        // OPTIMIZATION: Use O(1) math for fixed intervals instead of O(N) loop (Bolt)
        $fixed_intervals = array(
            'hourly' => 3600,
            'every_4_hours' => 14400,
            'every_6_hours' => 21600,
            'every_12_hours' => 43200
            // Daily and others are excluded due to potential DST/variable length issues
        );

        if (isset($fixed_intervals[$frequency])) {
            $interval = $fixed_intervals[$frequency];

            // Formula: floor((boundary - start) / interval) + 1

            if ($cursor <= $today_end) {
                $counts['today'] = floor(($today_end - $cursor) / $interval) + 1;
            }

            if ($cursor <= $week_end) {
                $counts['week'] = floor(($week_end - $cursor) / $interval) + 1;
            }

            if ($cursor <= $month_end) {
                $counts['month'] = floor(($month_end - $cursor) / $interval) + 1;
            }

            return $counts;
        }

        // Fallback to iterative approach for variable intervals
        $max_iterations = 100;
        $i = 0;

        while ($cursor <= $month_end && $i < $max_iterations) {
            if ($cursor <= $today_end) {
                $counts['today']++;
            }

            if ($cursor <= $week_end) {
                $counts['week']++;
            }

            if ($cursor <= $month_end) {
                $counts['month']++;
            } else {
                break;
            }

            // Calculate next run
            $cursor = $this->calculate_next_run($frequency, $cursor);
            $i++;
        }

        return $counts;
    }

    private function calculate_next_run($frequency, $base_time) {
        switch ($frequency) {
            case 'hourly':
                return strtotime('+1 hour', $base_time);
            case 'every_4_hours':
                return strtotime('+4 hours', $base_time);
            case 'every_6_hours':
                return strtotime('+6 hours', $base_time);
            case 'every_12_hours':
                return strtotime('+12 hours', $base_time);
            case 'daily':
                return strtotime('+1 day', $base_time);
            case 'weekly':
                return strtotime('+1 week', $base_time);
            case 'bi_weekly':
                return strtotime('+2 weeks', $base_time);
            case 'monthly':
                return strtotime('+1 month', $base_time);
            default:
                if (strpos($frequency, 'every_') === 0) {
                    $day = ucfirst(str_replace('every_', '', $frequency));
                    $valid_days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');

                    if (in_array($day, $valid_days)) {
                        $next = strtotime("next $day", $base_time);
                        return strtotime(date('H:i:s', $base_time), $next);
                    }
                }
                return strtotime('+1 day', $base_time);
        }
    }
    
    public function render_page() {
        $templates = $this->get_all();
        $categories = get_categories(array('hide_empty' => false));
        $users = get_users(array('role__in' => array('administrator', 'editor', 'author')));
        
        include AIPS_PLUGIN_DIR . 'templates/admin/main.php';
    }
}
