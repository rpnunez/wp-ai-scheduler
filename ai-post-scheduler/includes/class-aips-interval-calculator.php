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
     * Handles catch-up logic using O(1) math where possible.
     *
     * @param string      $frequency  The frequency identifier (e.g., 'daily', 'hourly', 'every_monday').
     * @param string|null $start_time Optional. The base time to calculate from. Defaults to current time.
     * @return string The next run time in MySQL datetime format (Y-m-d H:i:s).
     */
    public function calculate_next_run($frequency, $start_time = null) {
        $base_time = $start_time ? strtotime($start_time) : current_time('timestamp');
        $now = current_time('timestamp');
        
        // If the base time is already in the future, we don't need to do anything.
        // However, if we are strictly calculating the *next* interval from a *previous* run time,
        // we might still want to advance it if the "previous run" was e.g. scheduled for 10:00
        // and it is now 09:55 (premature call?), but usually this is called after execution.
        // If called with a future time, we usually just return the next one relative to that.
        // BUT, for catch-up logic: if base_time < now, we need to find the first occurrence > now.

        if ($base_time > $now) {
            // It's already in the future, so calculate the next one from there?
            // Or just return it?
            // Standard behavior: calculate the NEXT run after the base_time.
            // If the user passes "Tomorrow 10am" as start_time, the "next run" is "Tomorrow 10am" (Wait, no).
            // Usually this function is used to UPDATE the schedule AFTER a run.
            // So if it ran at 10:00 (base_time), we want 11:00.
            // If it was supposed to run at 09:00 (base_time) but ran at 10:05 (late),
            // we want the next run relative to 09:00 that is > 10:05.

            // Let's standardise: We always want the first occurrence > NOW, preserving phase from base_time.
            // EXCEPT if base_time is ALREADY > NOW, then we assume that IS the next run.
            // Wait, if I save a schedule for tomorrow, calculate_next_run('daily', 'tomorrow') should return 'tomorrow'?
            // Or 'day after tomorrow'?
            // The Scheduler calls this:
            // 1. Initial Save: uses start_time directly.
            // 2. Post-Process: passes $schedule->next_run (which was just processed).
            //    So base_time is <= now (usually).
            //    We want result > now.

            // If base_time > now, it implies we are projecting further into the future.
            // Logic: Just calculate one step forward.
             $next = $this->calculate_next_timestamp($frequency, $base_time);
             return date('Y-m-d H:i:s', $next);
        }

        // Catch-up Logic: base_time <= now

        // Strategy 1: Fixed Interval (Seconds) - O(1) Math
        $interval_seconds = $this->get_interval_duration($frequency);

        // We use Math for fixed seconds intervals, but NOT for monthly/flexible ones
        $is_fixed_math_safe = !in_array($frequency, array('monthly', 'yearly')) && strpos($frequency, 'every_') !== 0; // Days of week are special
        // Note: 'every_4_hours' etc are in the seconds array, so they are fixed.
        // 'every_monday' is special.

        if ($is_fixed_math_safe && $interval_seconds > 0) {
            $diff = $now - $base_time;
            $steps_needed = floor($diff / $interval_seconds) + 1;
            $next_timestamp = $base_time + ($steps_needed * $interval_seconds);

            return date('Y-m-d H:i:s', $next_timestamp);
        }

        // Strategy 2: Variable Intervals (Monthly, Weekly-Specific) - Optimized Iteration
        // Since we can't do pure math, we iterate, but we can jump ahead if far behind.

        $next_timestamp = $this->calculate_next_timestamp($frequency, $base_time);

        // If one step wasn't enough to pass NOW...
        if ($next_timestamp <= $now) {

            // Special optimization for Monthly to avoid 100 loops
            if ($frequency === 'monthly') {
                // Approximate jumps
                $months_behind = floor(($now - $base_time) / 2592000); // 30 days approx
                if ($months_behind > 1) {
                    $base_time = strtotime("+$months_behind months", $base_time);
                    $next_timestamp = $this->calculate_next_timestamp($frequency, $base_time);
                }
            }

            // Fallback safety loop (should run very few times now)
            $limit = 50;
            while ($next_timestamp <= $now && $limit > 0) {
                $next_timestamp = $this->calculate_next_timestamp($frequency, $next_timestamp);
                $limit--;
            }

            // Hard fallback
            if ($limit === 0) {
                 $next_timestamp = $this->calculate_next_timestamp($frequency, $now);
            }
        }
        
        return date('Y-m-d H:i:s', $next_timestamp);
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
                return $base_time + 3600;
                
            case 'every_4_hours':
                return $base_time + 14400;
                
            case 'every_6_hours':
                return $base_time + 21600;
                
            case 'every_12_hours':
                return $base_time + 43200;
                
            case 'daily':
                return $base_time + 86400;
                
            case 'weekly':
                return $base_time + 604800;
                
            case 'bi_weekly':
                return $base_time + 1209600;
                
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
            return $base_time + 86400; // Default to daily
        }
        
        $day = ucfirst(str_replace('every_', '', $frequency));
        $valid_days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');

        if (!in_array($day, $valid_days)) {
             return $base_time + 86400;
        }
        
        // Calculate next occurrence of the day while preserving time
        // Note: 'next Monday' from a Monday is next week.
        $next = strtotime("next $day", $base_time);
        
        // Reset the time to the base_time's time
        // This is important because "next Monday" might reset time to 00:00 depending on PHP version/context?
        // Actually "next Monday" usually takes current time if not specified, but we pass base_time.
        // But strtotime("next Monday", $base_time) keeps the time?
        // Let's verify: date('Y-m-d H:i:s', strtotime("next Monday", strtotime("2023-01-01 10:00:00")))
        // Output: 2023-01-02 00:00:00.
        // Correct, relative formats like "next Monday" reset time to 00:00 usually.

        // We must restore the time.
        $h = date('H', $base_time);
        $m = date('i', $base_time);
        $s = date('s', $base_time);
        
        return strtotime(date("Y-m-d $h:$m:$s", $next));
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
        foreach ($intervals as $key => $data) {
            if (!isset($schedules[$key])) {
                $schedules[$key] = $data;
            }
        }

        return $schedules;
    }
}
