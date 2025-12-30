<?php
// verification/reproduce_drift.php

// Mock the environment
function current_time($type) {
    // Simulate "now" as being 5 minutes late for the schedule
    // Let's say the schedule was for 10:00:00, and now it is 10:05:00
    // We want the next run to be 11:00:00 (hourly), not 11:05:00
    global $mock_now;
    return $type === 'mysql' ? date('Y-m-d H:i:s', $mock_now) : $mock_now;
}

// Mock translation
function __($text, $domain) { return $text; }

// Minimal implementation of AIPS_Interval_Calculator based on the file I read
class AIPS_Interval_Calculator {
    public function calculate_next_run($frequency, $start_time = null) {
        $base_time = $start_time ? strtotime($start_time) : current_time('timestamp');

        // THE BUGGY LOGIC
        if ($base_time < current_time('timestamp')) {
            $base_time = current_time('timestamp');
        }

        $next = $this->calculate_next_timestamp($frequency, $base_time);

        return date('Y-m-d H:i:s', $next);
    }

    private function calculate_next_timestamp($frequency, $base_time) {
        switch ($frequency) {
            case 'hourly':
                return strtotime('+1 hour', $base_time);
            default:
                return strtotime('+1 day', $base_time);
        }
    }
}

// Test Setup
$calculator = new AIPS_Interval_Calculator();

// Scenario:
// Schedule Next Run: 10:00:00
// Current Time: 10:05:00 (Cron ran 5 minutes late)
// Expected Next Run: 11:00:00
// Actual Next Run (with bug): 11:05:00

$schedule_next_run = '2023-10-27 10:00:00';
$mock_now = strtotime('2023-10-27 10:05:00');

echo "Scheduled Run: " . $schedule_next_run . "\n";
echo "Actual Execution Time: " . date('Y-m-d H:i:s', $mock_now) . "\n";

// Emulate what AIPS_Scheduler::process_scheduled_posts currently does:
// $next_run = $this->calculate_next_run($schedule->frequency);
// It passes ONLY frequency, so start_time defaults to null, which becomes current_time.
$next_run_current_impl = $calculator->calculate_next_run('hourly');
echo "Next Run (Current Implementation): " . $next_run_current_impl . "\n";

// Emulate what AIPS_Scheduler SHOULD do (pass next_run):
// But notice the bug in calculate_next_run will still reset it because 10:00 < 10:05
$next_run_with_arg = $calculator->calculate_next_run('hourly', $schedule_next_run);
echo "Next Run (With Argument, but still buggy): " . $next_run_with_arg . "\n";

$expected_next_run = '2023-10-27 11:00:00';

if ($next_run_current_impl !== $expected_next_run) {
    echo "\n[FAIL] Drift detected! Expected $expected_next_run but got $next_run_current_impl\n";
} else {
    echo "\n[PASS] No drift.\n";
}
