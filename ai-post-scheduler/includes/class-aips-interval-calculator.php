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

        $intervals['custom'] = array(
            'interval' => 86400, // Placeholder
            'display' => __('Custom Schedule', 'ai-post-scheduler')
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
     * Handles various interval types including hourly, daily, weekly, day-specific, and custom advanced schedules.
     *
     * @param string      $frequency  The frequency identifier (e.g., 'daily', 'hourly', 'every_monday').
     * @param string|null $start_time Optional. The base time to calculate from. Defaults to current time.
     * @param array|null  $rules      Optional. Advanced rules for custom schedules.
     * @return string The next run time in MySQL datetime format (Y-m-d H:i:s).
     */
    public function calculate_next_run($frequency, $start_time = null, $rules = null) {
        $base_time = $start_time ? strtotime($start_time) : current_time('timestamp');
        $now = current_time('timestamp');
        
        // If start time is in the past, add intervals until future (Catch-up logic)
        if ($base_time < $now) {
            $limit = 100;
            while ($base_time <= $now && $limit > 0) {
                $base_time = $this->calculate_next_timestamp($frequency, $base_time, $rules);
                $limit--;
            }
            if ($limit === 0) {
                 // Fallback to calculating from NOW if we've looped too much
                 $base_time = $this->calculate_next_timestamp($frequency, $now, $rules);
            }
            return date('Y-m-d H:i:s', $base_time);
        }
        
        $next = $this->calculate_next_timestamp($frequency, $base_time, $rules);
        
        return date('Y-m-d H:i:s', $next);
    }
    
    /**
     * Calculate the next run timestamp for a given frequency.
     *
     * Internal method that performs the actual timestamp calculations.
     *
     * @param string     $frequency The frequency identifier.
     * @param int        $base_time The base timestamp to calculate from.
     * @param array|null $rules     Advanced rules.
     * @return int The next run timestamp.
     */
    private function calculate_next_timestamp($frequency, $base_time, $rules = null) {
        if ($frequency === 'custom' && !empty($rules)) {
            return $this->calculate_custom_interval($base_time, $rules);
        }

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
     * Calculate next run for Custom Rules.
     * Supported Rules:
     * - specific_time: "09:00"
     * - days_of_week: [1, 3, 5] (Mon, Wed, Fri)
     * - day_of_month: 15
     */
    private function calculate_custom_interval($base_time, $rules) {
    	// Decode if JSON string
    	if (is_string($rules)) {
    		$rules = json_decode($rules, true);
    	}

    	// Ensure rules is an array
    	if (!is_array($rules)) {
    		// Fallback: simple +1 day if rules are not usable
    		return strtotime('+1 day', $base_time);
    	}

    	// 1. Handle multiple times per day: look for the next time later TODAY first.
    	if (isset($rules['times']) && is_array($rules['times']) && !empty($rules['times'])) {
    		// Multi-time support
    		sort($rules['times']);

    		$date_str         = date('Y-m-d', $base_time);
    		$current_time_str = date('H:i', $base_time);

    		foreach ($rules['times'] as $time) {
    			if ($time > $current_time_str) {
    				$candidate = strtotime($date_str . ' ' . $time);
    				if ($this->is_valid_custom_date($candidate, $rules)) {
    					return $candidate;
    				}
    			}
    		}
    	}

    	// Decide the time-of-day to use for future days.
    	if (isset($rules['times']) && is_array($rules['times']) && !empty($rules['times'])) {
    		$time_for_new_days = $rules['times'][0];
    	} elseif (!empty($rules['specific_time'])) {
    		$time_for_new_days = $rules['specific_time'];
    	} else {
    		$time_for_new_days = date('H:i', $base_time);
    	}

    	// 2. If day_of_month is configured, use month-based arithmetic instead of day-by-day looping.
    	if (!empty($rules['day_of_month'])) {
    		$days_of_month = $rules['day_of_month'];
    		if (!is_array($days_of_month)) {
    			$days_of_month = array((int) $days_of_month);
    		}

    		// Normalize and sort days of month
    		$normalized_days = array();
    		foreach ($days_of_month as $dom) {
    			$dom = (int) $dom;
    			if ($dom > 0 && $dom <= 31) {
    				$normalized_days[] = $dom;
    			}
    		}
    		$normalized_days = array_unique($normalized_days);
    		sort($normalized_days);

    		if (!empty($normalized_days)) {
    			$start_of_day   = strtotime(date('Y-m-d 00:00:00', $base_time));
    			$best_candidate = null;

    			// Look ahead a reasonable number of months to find the next valid run.
    			for ($m_offset = 0; $m_offset < 24; $m_offset++) {
    				$month_base_ts = strtotime('first day of +' . $m_offset . ' month', $start_of_day);
    				$year          = (int) date('Y', $month_base_ts);
    				$month         = (int) date('n', $month_base_ts);

    				foreach ($normalized_days as $dom) {
    					if (!checkdate($month, $dom, $year)) {
    						continue;
    					}

    					$day_ts = mktime(0, 0, 0, $month, $dom, $year);
    					// We want strictly after the base time.
    					if ($day_ts <= $base_time) {
    						continue;
    					}

    					$candidate_str = date('Y-m-d', $day_ts) . ' ' . $time_for_new_days;
    					$candidate     = strtotime($candidate_str);

    					if ($candidate > $base_time && $this->is_valid_custom_date($candidate, $rules)) {
    						if ($best_candidate === null || $candidate < $best_candidate) {
    							$best_candidate = $candidate;
    						}
    					}
    				}
    			}

    			if ($best_candidate !== null) {
    				return $best_candidate;
    			}
    		}
    	}

    	// 3. Fallback: iterate forward by day with a small safety limit when no day_of_month constraint.
    	//    Only days_of_week and similar constraints are left; they are satisfied within a few days.
    	$max_days_ahead = 32; // safety cap, but far smaller than 366 and enough for weekly patterns.
    	$base_time      = strtotime('+1 day', $base_time);

    	for ($i = 0; $i < $max_days_ahead; $i++) {
    		$candidate_str = date('Y-m-d', $base_time) . ' ' . $time_for_new_days;
    		$candidate     = strtotime($candidate_str);

    		if ($this->is_valid_custom_date($candidate, $rules)) {
    			return $candidate;
    		}

    		$base_time = strtotime('+1 day', $base_time);
    	}

    	// Fallback: if nothing found within the safety window, just move one day ahead.
    	return strtotime('+1 day', $base_time);
    }

    /**
     * Validate whether a given timestamp matches the provided custom schedule rules.
     *
     * Expected structure of $rules:
     * - 'days_of_week' (optional): int|int[]
     *       Accepts either:
     *       - 0–6 (Sunday=0, Monday=1, ...), matching PHP date('w') and JS getDay(), or
     *       - 1–7 (Monday=1, ..., Sunday=7), matching ISO-8601 / PHP date('N').
     *   Internally, all values are normalized to 0–6 (Sunday=0) to compare with date('w').
     * - 'day_of_month' (optional): int|int[]
     *
     * @param int   $timestamp Unix timestamp to validate.
     * @param array $rules     Custom schedule rules.
     *
     * @return bool True if the timestamp matches the rules, false otherwise.
     */
    private function is_valid_custom_date($timestamp, $rules) {
        // Check Day of Week
        if (!empty($rules['days_of_week'])) {
            $day_w = (int) date('w', $timestamp); // 0 (Sun) - 6 (Sat)

            // Normalize configured days_of_week to 0-6 (Sunday=0) to match date('w').
            $days_of_week = $rules['days_of_week'];
            if (!is_array($days_of_week)) {
                $days_of_week = array($days_of_week);
            }

            $normalized_days = array();
            foreach ($days_of_week as $day) {
                $day = (int) $day;

                // Support ISO 1–7 (Mon=1..Sun=7) and 0–6 (Sun=0..Sat=6).
                if ($day >= 1 && $day <= 7) {
                    // Convert ISO: 1–6 stay as 1–6, 7 (Sunday) becomes 0.
                    $day = $day % 7;
                } elseif ($day < 0 || $day > 6) {
                    // Ignore out-of-range values.
                    continue;
                }

                $normalized_days[] = $day;
            }

            $normalized_days = array_values(array_unique($normalized_days));

            // If nothing valid remains after normalization, the rule cannot match.
            if (empty($normalized_days)) {
                return false;
            }

            if (!in_array($day_w, $normalized_days, true)) {
                return false;
            }
        }

        // Check Day of Month
        if (!empty($rules['day_of_month'])) {
            $day_d = (int) date('j', $timestamp);
            // Support array or single int
            if (is_array($rules['day_of_month'])) {
                if (!in_array($day_d, $rules['day_of_month'])) {
                    return false;
                }
            } else {
                if ($day_d !== (int) $rules['day_of_month']) {
                    return false;
                }
            }
        }

        return true;
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
}
