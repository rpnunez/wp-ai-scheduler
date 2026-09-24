<?php
/**
 * Test case for Schedule Timing & Drift Prevention
 *
 * Tests phase preservation, interval calculations across multiple cycles,
 * day-specific weekday cadences, catch-up from overdue slots, and author
 * workflow last-run tracking.
 *
 * AIPS_Interval_Calculator works in UTC Unix timestamps only. Exact
 * single-step advances are exercised through calculate_next_occurrence_after()
 * (the first occurrence strictly after a slot); calculate_next_run() is the
 * catch-up entry point for overdue slots.
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
	 * UTC timestamp for a 'Y-m-d H:i:s' string.
	 *
	 * @param string $datetime Datetime in UTC.
	 * @return int
	 */
	private function ts( $datetime ) {
		return strtotime( $datetime . ' UTC' );
	}

	/**
	 * The occurrence immediately after $slot.
	 *
	 * @param string $frequency Frequency identifier.
	 * @param int    $slot      UTC timestamp.
	 * @return int
	 */
	private function step( $frequency, $slot ) {
		return $this->calculator->calculate_next_occurrence_after( $frequency, $slot, $slot + 1 );
	}

	/**
	 * Test that daily schedules preserve phase (exact hour/minute/second) across multiple cycles without drifting.
	 */
	public function test_phase_preservation_daily_no_drift() {
		$slot = $this->ts( '2030-06-15 09:00:00' );

		for ( $i = 1; $i <= 7; $i++ ) {
			$slot = $this->step( 'daily', $slot );
			$this->assertSame( sprintf( '2030-06-%02d 09:00:00', 15 + $i ), gmdate( 'Y-m-d H:i:s', $slot ), "Failed at cycle {$i}" );
		}
	}

	/**
	 * Test that hourly schedules preserve exact minute and second across multiple cycles.
	 */
	public function test_phase_preservation_hourly_no_drift() {
		$slot = $this->ts( '2030-06-15 09:14:22' );

		for ( $i = 1; $i <= 5; $i++ ) {
			$slot = $this->step( 'hourly', $slot );
			$this->assertSame( sprintf( '2030-06-15 %02d:14:22', 9 + $i ), gmdate( 'Y-m-d H:i:s', $slot ), "Failed at cycle {$i}" );
		}
	}

	/**
	 * Test that 2-hour, 4-hour, 6-hour, 8-hour, and 12-hour intervals preserve minute/second phase.
	 */
	public function test_phase_preservation_multi_hour_intervals() {
		$slot = $this->ts( '2030-06-15 08:30:15' );

		$expected = array(
			'every_2_hours'  => '2030-06-15 10:30:15',
			'every_4_hours'  => '2030-06-15 12:30:15',
			'every_6_hours'  => '2030-06-15 14:30:15',
			'every_8_hours'  => '2030-06-15 16:30:15',
			'every_12_hours' => '2030-06-15 20:30:15',
		);

		foreach ( $expected as $frequency => $next ) {
			$this->assertSame( $next, gmdate( 'Y-m-d H:i:s', $this->step( $frequency, $slot ) ), $frequency );
		}
	}

	/**
	 * Test that weekly day-specific cadences jump exactly 7 days and maintain hour/minute/second.
	 */
	public function test_phase_preservation_day_specific_weekly() {
		// June 13, 2030 is a Thursday.
		$slot = $this->ts( '2030-06-13 14:00:00' );

		$next1 = $this->step( 'every_thursday', $slot );
		$this->assertSame( '2030-06-20 14:00:00', gmdate( 'Y-m-d H:i:s', $next1 ) );

		$next2 = $this->step( 'every_thursday', $next1 );
		$this->assertSame( '2030-06-27 14:00:00', gmdate( 'Y-m-d H:i:s', $next2 ) );
	}

	/**
	 * Test all 7 weekday cadences jump to the correct subsequent day of week with identical time.
	 */
	public function test_all_seven_weekday_cadences() {
		// June 10, 2030 is a Monday.
		$base_monday = $this->ts( '2030-06-10 18:45:00' );

		$expected_dates = array(
			'every_monday'    => '2030-06-17 18:45:00',
			'every_tuesday'   => '2030-06-11 18:45:00',
			'every_wednesday' => '2030-06-12 18:45:00',
			'every_thursday'  => '2030-06-13 18:45:00',
			'every_friday'    => '2030-06-14 18:45:00',
			'every_saturday'  => '2030-06-15 18:45:00',
			'every_sunday'    => '2030-06-16 18:45:00',
		);

		foreach ( $expected_dates as $frequency => $expected_next ) {
			$this->assertSame( $expected_next, gmdate( 'Y-m-d H:i:s', $this->step( $frequency, $base_monday ) ), "Failed for frequency {$frequency}" );
		}
	}

	/**
	 * Test that single-step advances match plain UTC arithmetic for fixed intervals.
	 */
	public function test_calculate_next_timestamp_matches_utc_math() {
		$base_timestamp = $this->ts( '2030-06-15 10:00:00' );

		$this->assertSame( $base_timestamp + 3600, $this->step( 'hourly', $base_timestamp ) );
		$this->assertSame( $base_timestamp + 86400, $this->step( 'daily', $base_timestamp ) );
		$this->assertSame( $base_timestamp + 604800, $this->step( 'weekly', $base_timestamp ) );
	}

	/**
	 * Test that an overdue slot catches up to the first future occurrence in its own phase.
	 */
	public function test_overdue_slot_catches_up_without_drift() {
		$now  = AIPS_DateTime::now()->timestamp();
		$slot = $this->ts( gmdate( 'Y-m-d', $now - 5 * DAY_IN_SECONDS ) . ' 09:00:00' );

		$next = $this->calculator->calculate_next_run( 'daily', $slot );

		$this->assertGreaterThan( $now, $next );
		$this->assertLessThanOrEqual( $now, $next - DAY_IN_SECONDS );
		$this->assertSame( '09:00:00', gmdate( 'H:i:s', $next ) );
	}

	/**
	 * Test that an overdue weekday slot catches up on the same weekday and time.
	 */
	public function test_overdue_weekday_slot_keeps_day_and_time() {
		$now  = AIPS_DateTime::now()->timestamp();
		$slot = strtotime( 'last monday 18:45:00 UTC', $now - 3 * WEEK_IN_SECONDS );

		$next = $this->calculator->calculate_next_run( 'every_monday', $slot );

		$this->assertGreaterThan( $now, $next );
		$this->assertSame( 'Mon 18:45:00', gmdate( 'D H:i:s', $next ) );
	}

	/**
	 * Test Authors repository updates last_run timestamps properly.
	 */
	public function test_author_last_run_updates() {
		$repo      = new AIPS_Authors_Repository();
		$author_id = $repo->create(
			array(
				'name'        => 'Test Timing Author',
				'field_niche' => 'Timing',
				'is_active'   => 1,
			)
		);

		$this->assertNotEmpty( $author_id );

		$now_ts = AIPS_DateTime::now()->timestamp();

		$repo->update_topic_generation_last_run( $author_id, $now_ts );
		$author = $repo->get_by_id( $author_id );
		$this->assertEquals( $now_ts, (int) $author->topic_generation_last_run );

		$repo->update_post_generation_last_run( $author_id, $now_ts + 120 );
		$author = $repo->get_by_id( $author_id );
		$this->assertEquals( $now_ts + 120, (int) $author->post_generation_last_run );
	}
}
