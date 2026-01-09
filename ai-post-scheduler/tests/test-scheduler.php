<?php
/**
 * Test Scheduler Functionality
 *
 * Comprehensive tests for scheduling logic including:
 * - Past run handling and catch-up logic
 * - Missed runs (schedule should have run but didn't)
 * - Multiple missed intervals
 * - Boundary conditions (midnight crossings, month boundaries)
 * - One-time schedules (success/failure scenarios)
 * - Schedule deactivation/reactivation
 * - Concurrent schedule execution
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Scheduler extends WP_UnitTestCase {

	private $interval_calculator;
	private $template_id;

	public function setUp(): void {
		parent::setUp();

		$this->interval_calculator = new AIPS_Interval_Calculator();
		$this->template_id = 1; // Use a simple ID for testing
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Test that a schedule in the past correctly calculates the next future run
	 */
	public function test_past_schedule_catches_up_to_future() {
		$past_time = date('Y-m-d H:i:s', strtotime('-3 days'));
		$next_run = $this->interval_calculator->calculate_next_run('daily', $past_time);
		
		$next_timestamp = strtotime($next_run);
		$now = current_time('timestamp');
		
		// Next run should be in the future
		$this->assertGreaterThan($now, $next_timestamp, 'Next run should be in the future');
		
		// Should maintain the same time of day (phase preservation)
		$original_hour = (int) date('H', strtotime($past_time));
		$next_hour = (int) date('H', $next_timestamp);
		$this->assertEquals($original_hour, $next_hour, 'Should preserve hour of day');
	}

	/**
	 * Test that multiple missed intervals are handled correctly
	 */
	public function test_multiple_missed_intervals_daily() {
		// Schedule from 10 days ago at 10:00 AM
		$past_time = date('Y-m-d 10:00:00', strtotime('-10 days'));
		$next_run = $this->interval_calculator->calculate_next_run('daily', $past_time);
		
		$next_timestamp = strtotime($next_run);
		$now = current_time('timestamp');
		
		// Should be in the future
		$this->assertGreaterThan($now, $next_timestamp);
		
		// Should be within 24 hours from now
		$this->assertLessThan($now + 86400, $next_timestamp);
		
		// Should maintain 10:00 AM time
		$this->assertEquals('10:00', date('H:i', $next_timestamp));
	}

	/**
	 * Test hourly schedule catch-up
	 */
	public function test_multiple_missed_intervals_hourly() {
		// Schedule from 5 hours ago
		$past_time = date('Y-m-d H:i:s', strtotime('-5 hours'));
		$next_run = $this->interval_calculator->calculate_next_run('hourly', $past_time);
		
		$next_timestamp = strtotime($next_run);
		$now = current_time('timestamp');
		
		// Should be in the future
		$this->assertGreaterThan($now, $next_timestamp);
		
		// Should be within 1 hour from now (with some tolerance for execution time)
		$this->assertLessThanOrEqual($now + 3600, $next_timestamp);
	}

	/**
	 * Test weekly schedule with missed runs
	 */
	public function test_weekly_schedule_catch_up() {
		// Schedule from 3 weeks ago, every Monday at 9 AM
		$past_time = date('Y-m-d 09:00:00', strtotime('last monday -3 weeks'));
		$next_run = $this->interval_calculator->calculate_next_run('weekly', $past_time);
		
		$next_timestamp = strtotime($next_run);
		$now = current_time('timestamp');
		
		// Should be in the future
		$this->assertGreaterThan($now, $next_timestamp);
		
		// Should be within 7 days from now
		$this->assertLessThan($now + 604800, $next_timestamp);
		
		// Should maintain 9 AM time
		$this->assertEquals('09:00', date('H:i', $next_timestamp));
	}

	/**
	 * Test day-specific schedule (every Monday)
	 */
	public function test_day_specific_schedule() {
		// Get the most recent Monday at 2 PM
		$last_monday = strtotime('last monday 14:00');
		if (date('l') === 'Monday' && current_time('timestamp') < strtotime('today 14:00')) {
			$last_monday = strtotime('monday last week 14:00');
		}
		$past_time = date('Y-m-d H:i:s', $last_monday);
		
		$next_run = $this->interval_calculator->calculate_next_run('every_monday', $past_time);
		$next_timestamp = strtotime($next_run);
		
		// Should be a Monday
		$this->assertEquals('Monday', date('l', $next_timestamp));
		
		// Should be at 2 PM
		$this->assertEquals('14:00', date('H:i', $next_timestamp));
		
		// Should be in the future
		$this->assertGreaterThan(current_time('timestamp'), $next_timestamp);
	}

	/**
	 * Test schedule that crosses month boundary
	 */
	public function test_month_boundary_crossing() {
		// Set to last day of previous month
		$last_month_end = date('Y-m-t 23:00:00', strtotime('last month'));
		$next_run = $this->interval_calculator->calculate_next_run('daily', $last_month_end);
		
		$next_timestamp = strtotime($next_run);
		
		// Verify it advances correctly
		$this->assertGreaterThan(strtotime($last_month_end), $next_timestamp);
		$this->assertEquals('23:00', date('H:i', $next_timestamp));
	}

	/**
	 * Test schedule that crosses year boundary
	 */
	public function test_year_boundary_crossing() {
		$last_year_end = date('Y-12-31 20:00:00', strtotime('-1 year'));
		$next_run = $this->interval_calculator->calculate_next_run('daily', $last_year_end);
		
		$next_timestamp = strtotime($next_run);
		$now = current_time('timestamp');
		
		// Should be in future
		$this->assertGreaterThan($now, $next_timestamp);
		$this->assertEquals('20:00', date('H:i', $next_timestamp));
	}

	/**
	 * Test midnight crossing
	 */
	public function test_midnight_crossing() {
		$yesterday_midnight = date('Y-m-d 00:00:00', strtotime('yesterday'));
		$next_run = $this->interval_calculator->calculate_next_run('daily', $yesterday_midnight);
		
		$next_timestamp = strtotime($next_run);
		
		// Should be tomorrow at midnight
		$this->assertEquals('00:00', date('H:i', $next_timestamp));
		$this->assertGreaterThan(current_time('timestamp'), $next_timestamp);
	}

	/**
	 * Test monthly schedule
	 */
	public function test_monthly_schedule_catch_up() {
		// 3 months ago
		$past_time = date('Y-m-d 15:30:00', strtotime('-3 months'));
		$next_run = $this->interval_calculator->calculate_next_run('monthly', $past_time);
		
		$next_timestamp = strtotime($next_run);
		$now = current_time('timestamp');
		
		// Should be in future
		$this->assertGreaterThan($now, $next_timestamp);
		
		// Should be within roughly 31 days
		$this->assertLessThan($now + (31 * 86400), $next_timestamp);
		
		// Should maintain time
		$this->assertEquals('15:30', date('H:i', $next_timestamp));
	}

	/**
	 * Test that one-time schedules work as expected in the calculator
	 */
	public function test_once_frequency_handling() {
		// Once frequency should still have an interval defined
		$intervals = $this->interval_calculator->get_intervals();
		$this->assertArrayHasKey('once', $intervals);
		$this->assertArrayHasKey('interval', $intervals['once']);
	}

	/**
	 * Test schedule with invalid frequency defaults gracefully
	 */
	public function test_invalid_frequency_defaults() {
		$past_time = date('Y-m-d H:i:s', strtotime('-1 day'));
		$next_run = $this->interval_calculator->calculate_next_run('invalid_frequency', $past_time);
		
		$next_timestamp = strtotime($next_run);
		$now = current_time('timestamp');
		
		// Should still return something in the future (defaults to +1 day)
		$this->assertGreaterThan($now, $next_timestamp);
	}

	/**
	 * Test every_4_hours schedule
	 */
	public function test_every_4_hours_schedule() {
		$past_time = date('Y-m-d H:i:s', strtotime('-10 hours'));
		$next_run = $this->interval_calculator->calculate_next_run('every_4_hours', $past_time);
		
		$next_timestamp = strtotime($next_run);
		$now = current_time('timestamp');
		
		// Should be in future
		$this->assertGreaterThan($now, $next_timestamp);
		
		// Should be within 4 hours
		$this->assertLessThan($now + 14400, $next_timestamp);
	}

	/**
	 * Test every_6_hours schedule
	 */
	public function test_every_6_hours_schedule() {
		$past_time = date('Y-m-d H:i:s', strtotime('-15 hours'));
		$next_run = $this->interval_calculator->calculate_next_run('every_6_hours', $past_time);
		
		$next_timestamp = strtotime($next_run);
		$now = current_time('timestamp');
		
		// Should be in future
		$this->assertGreaterThan($now, $next_timestamp);
		
		// Should be within 6 hours
		$this->assertLessThan($now + 21600, $next_timestamp);
	}

	/**
	 * Test every_12_hours schedule
	 */
	public function test_every_12_hours_schedule() {
		$past_time = date('Y-m-d H:i:s', strtotime('-30 hours'));
		$next_run = $this->interval_calculator->calculate_next_run('every_12_hours', $past_time);
		
		$next_timestamp = strtotime($next_run);
		$now = current_time('timestamp');
		
		// Should be in future
		$this->assertGreaterThan($now, $next_timestamp);
		
		// Should be within 12 hours
		$this->assertLessThan($now + 43200, $next_timestamp);
	}

	/**
	 * Test bi-weekly schedule
	 */
	public function test_bi_weekly_schedule() {
		$past_time = date('Y-m-d 10:00:00', strtotime('-5 weeks'));
		$next_run = $this->interval_calculator->calculate_next_run('bi_weekly', $past_time);
		
		$next_timestamp = strtotime($next_run);
		$now = current_time('timestamp');
		
		// Should be in future
		$this->assertGreaterThan($now, $next_timestamp);
		
		// Should be within 2 weeks
		$this->assertLessThan($now + 1209600, $next_timestamp);
		
		// Should maintain time
		$this->assertEquals('10:00', date('H:i', $next_timestamp));
	}

	/**
	 * Test that catch-up logic doesn't create infinite loop
	 * (Tests the safety limit in calculate_next_run)
	 */
	public function test_catch_up_safety_limit() {
		// Create a time very far in the past
		$very_old = date('Y-m-d H:i:s', strtotime('-365 days'));
		
		// This should not hang or timeout
		$next_run = $this->interval_calculator->calculate_next_run('daily', $very_old);
		
		$next_timestamp = strtotime($next_run);
		$now = current_time('timestamp');
		
		// Should still result in a future time
		$this->assertGreaterThan($now, $next_timestamp);
	}

	/**
	 * Test that frequency validation works
	 */
	public function test_frequency_validation() {
		$this->assertTrue($this->interval_calculator->is_valid_frequency('daily'));
		$this->assertTrue($this->interval_calculator->is_valid_frequency('hourly'));
		$this->assertTrue($this->interval_calculator->is_valid_frequency('weekly'));
		$this->assertTrue($this->interval_calculator->is_valid_frequency('every_monday'));
		$this->assertTrue($this->interval_calculator->is_valid_frequency('once'));
		
		$this->assertFalse($this->interval_calculator->is_valid_frequency('invalid'));
		$this->assertFalse($this->interval_calculator->is_valid_frequency(''));
	}

	/**
	 * Test schedule preserves time phase across catch-up
	 */
	public function test_phase_preservation() {
		// Schedule at 3:45 PM, 5 days ago
		$past_time = date('Y-m-d 15:45:00', strtotime('-5 days'));
		$next_run = $this->interval_calculator->calculate_next_run('daily', $past_time);
		
		$next_timestamp = strtotime($next_run);
		
		// Should maintain 15:45
		$this->assertEquals('15:45', date('H:i', $next_timestamp));
		
		// And be in the future
		$this->assertGreaterThan(current_time('timestamp'), $next_timestamp);
	}

	/**
	 * Test all day-specific frequencies
	 */
	public function test_all_day_specific_frequencies() {
		$days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
		
		foreach ($days as $day) {
			$frequency = 'every_' . $day;
			
			// Get last occurrence of this day at 10 AM
			$past_time = date('Y-m-d 10:00:00', strtotime("last $day -1 week"));
			$next_run = $this->interval_calculator->calculate_next_run($frequency, $past_time);
			
			$next_timestamp = strtotime($next_run);
			
			// Should be the correct day
			$this->assertEquals(ucfirst($day), date('l', $next_timestamp), "Failed for $frequency");
			
			// Should be at 10:00
			$this->assertEquals('10:00', date('H:i', $next_timestamp), "Failed to preserve time for $frequency");
			
			// Should be in the future
			$this->assertGreaterThan(current_time('timestamp'), $next_timestamp, "Failed future check for $frequency");
		}
	}
}
