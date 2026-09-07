<?php
/**
 * Test case for Schedule Timing & Drift Prevention
 *
 * Tests phase preservation, interval calculations across multiple cycles,
 * day-specific weekday cadences, and author workflow last-run tracking.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Schedule_Timing_Drift extends WP_UnitTestCase {

	/**
	 * @var AIPS_Interval_Calculator
	 */
	private $calculator;

	public function setUp(): void {
		parent::setUp();
		$this->calculator = new AIPS_Interval_Calculator();
	}

	/**
	 * Test that daily schedules preserve phase (exact hour/minute/second) across multiple cycles without drifting.
	 */
	public function test_phase_preservation_daily_no_drift() {
		$slot = '2030-06-15 09:00:00';

		for ($i = 1; $i <= 7; $i++) {
			$next = $this->calculator->calculate_next_run('daily', $slot);
			$expected_day = sprintf('2030-06-%02d 09:00:00', 15 + $i);
			$this->assertEquals($expected_day, $next, "Failed at cycle {$i}");
			$slot = $next;
		}
	}

	/**
	 * Test that hourly schedules preserve exact minute and second across multiple cycles.
	 */
	public function test_phase_preservation_hourly_no_drift() {
		$slot = '2030-06-15 09:14:22';

		for ($i = 1; $i <= 5; $i++) {
			$next = $this->calculator->calculate_next_run('hourly', $slot);
			$expected = sprintf('2030-06-15 %02d:14:22', 9 + $i);
			$this->assertEquals($expected, $next, "Failed at cycle {$i}");
			$slot = $next;
		}
	}

	/**
	 * Test that 2-hour, 4-hour, 6-hour, 8-hour, and 12-hour intervals preserve minute/second phase.
	 */
	public function test_phase_preservation_multi_hour_intervals() {
		$slot = '2030-06-15 08:30:15';

		$next_2h = $this->calculator->calculate_next_run('every_2_hours', $slot);
		$this->assertEquals('2030-06-15 10:30:15', $next_2h);

		$next_4h = $this->calculator->calculate_next_run('every_4_hours', $slot);
		$this->assertEquals('2030-06-15 12:30:15', $next_4h);

		$next_6h = $this->calculator->calculate_next_run('every_6_hours', $slot);
		$this->assertEquals('2030-06-15 14:30:15', $next_6h);

		$next_8h = $this->calculator->calculate_next_run('every_8_hours', $slot);
		$this->assertEquals('2030-06-15 16:30:15', $next_8h);

		$next_12h = $this->calculator->calculate_next_run('every_12_hours', $slot);
		$this->assertEquals('2030-06-15 20:30:15', $next_12h);
	}

	/**
	 * Test that weekly day-specific cadences jump exactly 7 days and maintain hour/minute/second.
	 */
	public function test_phase_preservation_day_specific_weekly() {
		// June 13, 2030 is a Thursday
		$slot = '2030-06-13 14:00:00';

		$next1 = $this->calculator->calculate_next_run('every_thursday', $slot);
		$this->assertEquals('2030-06-20 14:00:00', $next1);

		$next2 = $this->calculator->calculate_next_run('every_thursday', $next1);
		$this->assertEquals('2030-06-27 14:00:00', $next2);
	}

	/**
	 * Test all 7 weekday cadences jump to the correct subsequent day of week with identical time.
	 */
	public function test_all_seven_weekday_cadences() {
		// June 10, 2030 is a Monday (10: Mon, 11: Tue, 12: Wed, 13: Thu, 14: Fri, 15: Sat, 16: Sun)
		$base_monday = '2030-06-10 18:45:00';

		$expected_dates = array(
			'every_monday'    => '2030-06-17 18:45:00',
			'every_tuesday'   => '2030-06-11 18:45:00',
			'every_wednesday' => '2030-06-12 18:45:00',
			'every_thursday'  => '2030-06-13 18:45:00',
			'every_friday'    => '2030-06-14 18:45:00',
			'every_saturday'  => '2030-06-15 18:45:00',
			'every_sunday'    => '2030-06-16 18:45:00',
		);

		foreach ($expected_dates as $frequency => $expected_next) {
			$actual = $this->calculator->calculate_next_run($frequency, $base_monday);
			$this->assertEquals($expected_next, $actual, "Failed for frequency {$frequency}");
		}
	}

	/**
	 * Test that calculate_next_timestamp produces exact timestamps matching DateTimeImmutable UTC.
	 */
	public function test_calculate_next_timestamp_matches_utc_math() {
		$base_timestamp = strtotime('2030-06-15 10:00:00 UTC');

		$next_hourly = $this->calculator->calculate_next_run('hourly', $base_timestamp);
		$this->assertEquals($base_timestamp + 3600, $next_hourly);

		$next_daily = $this->calculator->calculate_next_run('daily', $base_timestamp);
		$this->assertEquals($base_timestamp + 86400, $next_daily);

		$next_weekly = $this->calculator->calculate_next_run('weekly', $base_timestamp);
		$this->assertEquals($base_timestamp + 604800, $next_weekly);
	}

	/**
	 * Test Authors repository updates last_run timestamps properly.
	 */
	public function test_author_last_run_updates() {
		$repo = new AIPS_Authors_Repository();
		$author_id = $repo->save(array(
			'name' => 'Test Timing Author',
			'system_prompt' => 'Test prompt',
			'is_active' => 1,
		));

		$this->assertNotEmpty($author_id);

		$now_ts = AIPS_DateTime::now()->timestamp();

		$repo->update_topic_generation_last_run($author_id, $now_ts);
		$author = $repo->get_by_id($author_id);
		$this->assertEquals($now_ts, (int) $author->topic_generation_last_run);

		$repo->update_post_generation_last_run($author_id, $now_ts + 120);
		$author = $repo->get_by_id($author_id);
		$this->assertEquals($now_ts + 120, (int) $author->post_generation_last_run);
	}
}
