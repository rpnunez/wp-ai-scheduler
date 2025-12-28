<?php
/**
 * Interval Calculator Service
 *
 * Handles calculation of scheduling intervals and next run times.
 * Separates scheduling math from the scheduler's orchestration logic.
 *
 * @package AI_Post_Scheduler
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Interval_Calculator
 *
 * Provides interval calculation and WordPress cron schedule management
 * for the AI Post Scheduler plugin.
 */
class AIPS_Interval_Calculator {
    
    /**
     * Get all available scheduling intervals.
     *
     * Returns an associative array of interval IDs and their configurations,
     * including interval duration in seconds and human-readable display names.
     *
     * @return array Associative array of intervals with 'interval' (seconds) and 'display' (name).
     */
    public function get_intervals() {
        $intervals = array();

        $intervals['hourly'] = array(
            'interval' => 3600,
            'display' => __('Hourly', 'ai-post-scheduler')
        );

        $intervals['every_4_hours'] = array(
            'interval' => 14400,
            'display' => __('Every 4 Hours', 'ai-post-scheduler')
        );

        $intervals['every_6_hours'] = array(
            'interval' => 21600,
            'display' => __('Every 6 Hours', 'ai-post-scheduler')
        );
        
        $intervals['every_12_hours'] = array(
            'interval' => 43200,
            'display' => __('Every 12 Hours', 'ai-post-scheduler')
        );
        
        $intervals['daily'] = array(
            'interval' => 86400,
            'display' => __('Daily', 'ai-post-scheduler')
        );

        $intervals['weekly'] = array(
            'interval' => 604800,
            'display' => __('Once Weekly', 'ai-post-scheduler')
        );

        $intervals['bi_weekly'] = array(
            'interval' => 1209600,
            'display' => __('Every 2 Weeks', 'ai-post-scheduler')
        );

        $intervals['monthly'] = array(
            'interval' => 2592000,
            'display' => __('Monthly', 'ai-post-scheduler')
        );

        $intervals['once'] = array(
            'interval' => 86400, // Default to daily interval, but handled specially
            'display' => __('Once', 'ai-post-scheduler')
        );

        // Add day-specific intervals
        $days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
        foreach ($days as $day) {
            $intervals['every_' . strtolower($day)] = array(
                'interval' => 604800,
                'display' => sprintf(__('Every %s', 'ai-post-scheduler'), $day)
            );
        }
        
        return $intervals;
    }
    
    /**
     * Calculate the next run time for a given frequency.
     *
     * Determines when a scheduled task should run next based on its frequency setting.
     * Handles various interval types including hourly, daily, weekly, and day-specific schedules.
     *
     * @param string      $frequency  The frequency identifier (e.g., 'daily', 'hourly', 'every_monday').
     * @param string|null $start_time Optional. The base time to calculate from. Defaults to current time.
     * @return string The next run time in MySQL datetime format (Y-m-d H:i:s).
     */
    public function calculate_next_run($frequency, $start_time = null) {
        $base_time = $start_time ? strtotime($start_time) : current_time('timestamp');
        
        // If start time is in the past, use current time
        if ($base_time < current_time('timestamp')) {
            $base_time = current_time('timestamp');
        }
        
        $next = $this->calculate_next_timestamp($frequency, $base_time);
        
        return date('Y-m-d H:i:s', $next);
    }
    
    /**
     * Calculate the next run timestamp for a given frequency.
     *
     * Internal method that performs the actual timestamp calculations.
     *
     * @param string $frequency The frequency identifier.
     * @param int    $base_time The base timestamp to calculate from.
     * @return int The next run timestamp.
     */
    private function calculate_next_timestamp($frequency, $base_time) {
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
                return $this->calculate_day_specific_interval($frequency, $base_time);
        }
    }
    
    /**
     * Calculate next run time for day-specific intervals (e.g., "every_monday").
     *
     * Handles special cases where the frequency is set to a specific day of the week.
     * Preserves the time component while advancing to the next occurrence of that day.
     *
     * @param string $frequency The frequency identifier (should start with 'every_').
     * @param int    $base_time The base timestamp to calculate from.
     * @return int The next run timestamp, or default to +1 day if invalid.
     */
    private function calculate_day_specific_interval($frequency, $base_time) {
        if (strpos($frequency, 'every_') !== 0) {
            return strtotime('+1 day', $base_time);
        }
        
        $day = ucfirst(str_replace('every_', '', $frequency));
        $valid_days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');

        if (!in_array($day, $valid_days)) {
            return strtotime('+1 day', $base_time);
        }
        
        // Calculate next occurrence of the day while preserving time
        $next = strtotime("next $day", $base_time);
        
        // Reset the time to the base_time's time
        $next = strtotime(date('H:i:s', $base_time), $next);
        
        return $next;
    }
    
    /**
     * Get interval duration in seconds for a given frequency.
     *
     * Useful for calculating time spans and displaying durations.
     *
     * @param string $frequency The frequency identifier.
     * @return int The interval duration in seconds, or 0 if unknown.
     */
    public function get_interval_duration($frequency) {
        $intervals = $this->get_intervals();
        
        if (isset($intervals[$frequency])) {
            return $intervals[$frequency]['interval'];
        }
        
        return 0;
    }
    
    /**
     * Get human-readable display name for a frequency.
     *
     * @param string $frequency The frequency identifier.
     * @return string The display name, or the frequency itself if not found.
     */
    public function get_interval_display($frequency) {
        $intervals = $this->get_intervals();
        
        if (isset($intervals[$frequency])) {
            return $intervals[$frequency]['display'];
        }
        
        return $frequency;
    }
    
    /**
     * Validate that a frequency identifier is valid.
     *
     * @param string $frequency The frequency identifier to validate.
     * @return bool True if valid, false otherwise.
     */
    public function is_valid_frequency($frequency) {
        $intervals = $this->get_intervals();
        return isset($intervals[$frequency]);
    }
    
    /**
     * Merge intervals into WordPress cron schedules.
     *
     * Callback for the 'cron_schedules' filter. Adds plugin-specific intervals
     * to WordPress's built-in cron system.
     *
     * @param array $schedules Existing WordPress cron schedules.
     * @return array Modified schedules array with plugin intervals added.
     */
    public function merge_with_wp_schedules($schedules) {
        $intervals = $this->get_intervals();

        // Merge our intervals into WP schedules
        // Note: 'hourly' is a default WP interval, so we might overlap, but that's fine.
        foreach ($intervals as $key => $data) {
            if (!isset($schedules[$key])) {
                $schedules[$key] = $data;
            }
        }

        return $schedules;
    }

    /**
     * Calculate pending statistics for a schedule using O(1) math where possible.
     *
     * @param int    $next_run_ts Next run timestamp.
     * @param string $frequency   Schedule frequency.
     * @param int    $today_end   Timestamp for end of today.
     * @param int    $week_end    Timestamp for end of week.
     * @param int    $month_end   Timestamp for end of month.
     * @return array Associative array with 'today', 'week', 'month' counts.
     */
    public function calculate_pending_stats($next_run_ts, $frequency, $today_end, $week_end, $month_end) {
        $stats = array('today' => 0, 'week' => 0, 'month' => 0);

        // Handle 'once' frequency separately
        if ($frequency === 'once') {
            if ($next_run_ts <= $today_end) $stats['today'] = 1;
            if ($next_run_ts <= $week_end) $stats['week'] = 1;
            if ($next_run_ts <= $month_end) $stats['month'] = 1;
            return $stats;
        }

        // Determine if we can use O(1) math
        $is_fixed = in_array($frequency, array(
            'hourly', 'every_4_hours', 'every_6_hours', 'every_12_hours',
            'daily', 'weekly', 'bi_weekly'
        ));

        // Check for day-specific (every_monday, etc) which are also fixed weekly (604800s)
        if (!$is_fixed && strpos($frequency, 'every_') === 0 && $frequency !== 'every_month') {
            // Check if it's a day name
            $day = ucfirst(str_replace('every_', '', $frequency));
            $valid_days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
            if (in_array($day, $valid_days)) {
                $is_fixed = true;
            }
        }

        if ($is_fixed && $frequency !== 'monthly') {
            $interval = $this->get_interval_duration($frequency);
            if ($interval > 0) {
                // O(1) Calculation
                $stats['today'] = ($next_run_ts > $today_end) ? 0 : 1 + floor(($today_end - $next_run_ts) / $interval);
                $stats['week'] = ($next_run_ts > $week_end) ? 0 : 1 + floor(($week_end - $next_run_ts) / $interval);
                $stats['month'] = ($next_run_ts > $month_end) ? 0 : 1 + floor(($month_end - $next_run_ts) / $interval);
                return $stats;
            }
        }

        // Fallback to iterative approach for variable intervals (like monthly)
        $cursor = $next_run_ts;
        $max_iterations = 100;
        $i = 0;

        while ($cursor <= $month_end && $i < $max_iterations) {
            if ($cursor <= $today_end) $stats['today']++;
            if ($cursor <= $week_end) $stats['week']++;
            if ($cursor <= $month_end) $stats['month']++;

            $cursor = $this->calculate_next_timestamp($frequency, $cursor);
            $i++;
        }

        return $stats;
    }
}
