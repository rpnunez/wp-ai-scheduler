<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Parse natural-language schedule phrases into scheduler frequency/start_time.
 *
 * Supported examples:
 * - every weekday at 8 AM
 * - every monday at 9:30
 * - daily at 14:00
 * - every 6 hours
 */
class AIPS_Natural_Schedule_Parser {

    /**
     * Parse a natural-language schedule.
     *
     * @param string   $input               Natural-language schedule text.
     * @param int|null $reference_timestamp Optional timestamp to parse relative to.
     * @return array|WP_Error
     */
    public function parse($input, $reference_timestamp = null) {
        $input = is_string($input) ? trim($input) : '';
        if ($input == '') {
            return new WP_Error('invalid_schedule_text', __('Please enter a schedule phrase.', 'ai-post-scheduler'));
        }

        $normalized = strtolower(preg_replace('/\s+/', ' ', $input));
        $reference_timestamp = $reference_timestamp ?: current_time('timestamp');
        $time_parts = $this->extract_time_parts($normalized);

        // If the input appears to specify a time but we could not parse it,
        // return an explicit error instead of silently falling back to the reference time.
        if ($time_parts === null && preg_match('/\b(?:at\s+\d{1,2}(?::\d{2})?\s*(?:am|pm)?|at\s+(?:noon|midnight)|noon|midnight)\b/', $normalized)) {
            return new WP_Error(
                'invalid_schedule_time',
                __('The time in your schedule phrase could not be understood. Please use a time like "8am" or "14:30".', 'ai-post-scheduler')
            );
        }
        $days = array(
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7,
        );

        if (preg_match('/\b(every\s+weekday|weekdays?)\b/', $normalized)) {
            $start = $this->next_weekday_at_time($reference_timestamp, $time_parts);
            return array('frequency' => 'weekdays', 'start_time' => date('Y-m-d H:i:s', $start));
        }

        foreach ($days as $day_name => $day_number) {
            if (preg_match('/\b(every\s+' . preg_quote($day_name, '/') . '|on\s+' . preg_quote($day_name, '/') . ')\b/', $normalized)) {
                $start = $this->next_day_of_week_at_time($reference_timestamp, $day_number, $time_parts);
                return array('frequency' => 'every_' . $day_name, 'start_time' => date('Y-m-d H:i:s', $start));
            }
        }

        if (preg_match('/\b(every\s+4\s+hours?|every\s+four\s+hours?)\b/', $normalized)) {
            return $this->parse_fixed_interval_result('every_4_hours', $reference_timestamp, $time_parts);
        }

        if (preg_match('/\b(every\s+6\s+hours?|every\s+six\s+hours?)\b/', $normalized)) {
            return $this->parse_fixed_interval_result('every_6_hours', $reference_timestamp, $time_parts);
        }

        if (preg_match('/\b(every\s+12\s+hours?|every\s+twelve\s+hours?)\b/', $normalized)) {
            return $this->parse_fixed_interval_result('every_12_hours', $reference_timestamp, $time_parts);
        }

        if (preg_match('/\b(every\s+hour|hourly)\b/', $normalized)) {
            return $this->parse_fixed_interval_result('hourly', $reference_timestamp, $time_parts);
        }

        if (preg_match('/\b(bi\s*weekly|every\s+2\s+weeks?)\b/', $normalized)) {
            return $this->parse_fixed_interval_result('bi_weekly', $reference_timestamp, $time_parts);
        }

        if (preg_match('/\b(weekly|every\s+week)\b/', $normalized)) {
            return $this->parse_fixed_interval_result('weekly', $reference_timestamp, $time_parts);
        }

        if (preg_match('/\b(monthly|every\s+month)\b/', $normalized)) {
            return $this->parse_fixed_interval_result('monthly', $reference_timestamp, $time_parts);
        }

        if (preg_match('/\b(once|one\s*time|one-time|onetime)\b/', $normalized)) {
            return array(
                'frequency' => 'once',
                'start_time' => date('Y-m-d H:i:s', $this->next_time_at_or_after($reference_timestamp, $time_parts)),
            );
        }

        if (preg_match('/\b(daily|every\s+day|each\s+day)\b/', $normalized)) {
            return $this->parse_fixed_interval_result('daily', $reference_timestamp, $time_parts);
        }

        return new WP_Error(
            'unsupported_schedule_text',
            __('Could not parse schedule text. Try phrases like "every weekday at 8 AM" or "every monday at 9:30".', 'ai-post-scheduler')
        );
    }

    /**
     * Build result array for fixed-interval frequencies.
     */
    private function parse_fixed_interval_result($frequency, $reference_timestamp, $time_parts) {
        return array(
            'frequency' => $frequency,
            'start_time' => date('Y-m-d H:i:s', $this->next_time_at_or_after($reference_timestamp, $time_parts)),
        );
    }

    /**
     * Extract a time component from text.
     *
     * @return array|null Array with keys hour/minute or null when omitted.
     */
    private function extract_time_parts($normalized) {
        if (preg_match('/\bmidnight\b/', $normalized)) {
            return array('hour' => 0, 'minute' => 0);
        }

        if (preg_match('/\bnoon\b/', $normalized)) {
            return array('hour' => 12, 'minute' => 0);
        }

        if (preg_match('/\bat\s+([0-1]?\d|2[0-3])(?::([0-5]\d))?\s*(am|pm)\b/', $normalized, $m)) {
            $hour = (int) $m[1];
            $minute = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
            $ampm = $m[3];

            if ($ampm === 'am' && $hour === 12) {
                $hour = 0;
            } elseif ($ampm === 'pm' && $hour < 12) {
                $hour += 12;
            }

            return array('hour' => $hour, 'minute' => $minute);
        }

        if (preg_match('/\bat\s+([0-1]?\d|2[0-3]):([0-5]\d)\b/', $normalized, $m)) {
            return array('hour' => (int) $m[1], 'minute' => (int) $m[2]);
        }

        return null;
    }

    /**
     * Get the next occurrence of a day/time (or immediate reference when no time specified).
     */
    private function next_time_at_or_after($reference_timestamp, $time_parts) {
        if ($time_parts === null) {
            return $reference_timestamp;
        }

        $candidate = $this->timestamp_for_date_and_time($reference_timestamp, $time_parts);
        if ($candidate <= $reference_timestamp) {
            $candidate = strtotime('+1 day', $candidate);
        }

        return $candidate;
    }

    /**
     * Get next weekday occurrence at requested time.
     */
    private function next_weekday_at_time($reference_timestamp, $time_parts) {
        $candidate = $this->timestamp_for_date_and_time($reference_timestamp, $time_parts);
        $day = (int) date('N', $candidate);

        if ($day <= 5 && $candidate > $reference_timestamp) {
            return $candidate;
        }

        do {
            $candidate = strtotime('+1 day', $candidate);
            $day = (int) date('N', $candidate);
        } while ($day > 5);

        return $candidate;
    }

    /**
     * Get next requested day-of-week occurrence at requested time.
     */
    private function next_day_of_week_at_time($reference_timestamp, $target_day, $time_parts) {
        $candidate = $this->timestamp_for_date_and_time($reference_timestamp, $time_parts);

        for ($i = 0; $i < 8; $i++) {
            $day = (int) date('N', $candidate);
            if ($day === (int) $target_day && $candidate > $reference_timestamp) {
                return $candidate;
            }
            $candidate = strtotime('+1 day', $candidate);
        }

        return $candidate;
    }

    /**
     * Build a timestamp on the reference date with provided time.
     */
    private function timestamp_for_date_and_time($reference_timestamp, $time_parts) {
        if ($time_parts === null) {
            return $reference_timestamp;
        }

        $date = date('Y-m-d', $reference_timestamp);
        $hour = (int) $time_parts['hour'];
        $minute = (int) $time_parts['minute'];

        return strtotime(sprintf('%s %02d:%02d:00', $date, $hour, $minute));
    }
}
