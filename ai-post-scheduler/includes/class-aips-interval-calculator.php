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
     * @param string      $frequency      The frequency identifier (e.g., 'daily', 'hourly', 'every_monday').
     * @param string|null $start_time     Optional. The base time to calculate from. Defaults to current time.
     * @param bool        $allow_catch_up Optional. Whether to fast-forward past times to the future. Default true.
     * @return string The next run time in MySQL datetime format (Y-m-d H:i:s).
     */
    public function calculate_next_run($frequency, $start_time = null, $allow_catch_up = true) {
        $base_time = $start_time ? strtotime($start_time) : current_time('timestamp');
        $now = current_time('timestamp');
        
        // If start time is in the past, add intervals until future (Catch-up logic)
        // This prevents schedule drift by preserving the phase of the schedule
        if ($allow_catch_up && $base_time < $now) {
            $interval_duration = $this->get_interval_duration($frequency);
            
            // For fixed intervals (with known duration), use mathematical calculation
            if ($interval_duration > 0) {
                $time_diff = $now - $base_time;
                $intervals_needed = ceil($time_diff / $interval_duration);
                
                // Safety check: if more than 1000 intervals are needed, something is likely wrong
                if ($intervals_needed > 1000) {
                    // Log warning or handle error - for now, just jump to now + interval
                    $base_time = $this->calculate_next_timestamp($frequency, $now);
                } else {
                    // Add the calculated number of intervals
                    $base_time += ($intervals_needed * $interval_duration);
                }
            } else {
                // For day-specific intervals (e.g., every_monday), iterate with safety limit
                $limit = 1000; // Reduced from 50000 - if we need more than 1000 iterations, something is wrong
                while ($base_time <= $now && $limit > 0) {
                    $base_time = $this->calculate_next_timestamp($frequency, $base_time);
                    $limit--;
                }
                
                // If we hit the limit, log error and fall back to now + interval
                if ($limit === 0) {
                    $base_time = $this->calculate_next_timestamp($frequency, $now);
                }
            }
            return date('Y-m-d H:i:s', $base_time);
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
     * Get an associative array of all frequency IDs to their display labels.
     *
     * @param array $allowed Optional subset of frequency IDs to include.
     * @return array Key/value pairs of frequency => display label.
     */
    public function get_all_interval_displays($allowed = array()) {
        $intervals = $this->get_intervals();

        if (!empty($allowed)) {
            $intervals = array_intersect_key($intervals, array_flip($allowed));
        }

        $displays = array();
        foreach ($intervals as $key => $data) {
            $displays[$key] = isset($data['display']) ? $data['display'] : $key;
        }

        return $displays;
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
}
