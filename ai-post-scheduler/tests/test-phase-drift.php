<?php
/**
 * Regression test for Schedule Phase Drift Bug
 *
 * Ensures that long catch-up periods do not cause the schedule phase (minute/second) to drift.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Phase_Drift extends WP_UnitTestCase {

    /**
     * Test that phase is preserved even after hitting the catch-up limit (now increased).
     */
    public function test_phase_drift_on_catchup() {
        $calculator = new AIPS_Interval_Calculator();

        // Pick a distinct minute/second to verify phase preservation
        // Use 15 minutes and 30 seconds past the hour.
        // Start time: 120 hours ago (5 days).
        $base_timestamp = strtotime('-120 hours');

        // Force minutes and seconds to 15:30
        $start_time = date('Y-m-d H:15:30', $base_timestamp);

        // Ensure start time is indeed in the past relative to now
        $now = current_time('timestamp');
        $start_ts = strtotime($start_time);

        if ($start_ts > $now) {
             $start_time = date('Y-m-d H:15:30', strtotime('-121 hours'));
        }

        // Calculate next run
        $next_run = $calculator->calculate_next_run('hourly', $start_time);

        $next_min = date('i', strtotime($next_run));
        $next_sec = date('s', strtotime($next_run));

        // Assert that phase is preserved (15:30)
        $this->assertEquals('15', $next_min, "Minute phase drifted! Expected 15, got $next_min");
        $this->assertEquals('30', $next_sec, "Second phase drifted! Expected 30, got $next_sec");
    }
}
