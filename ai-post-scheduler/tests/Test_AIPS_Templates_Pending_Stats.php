<?php
/**
 * Tests for AIPS_Templates pending-run statistics.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Templates_Pending_Stats extends WP_UnitTestCase {

	public function tearDown(): void {
		// get_all_pending_stats() caches its result; never leak it to later tests.
		delete_transient( 'aips_pending_schedule_stats' );
		parent::tearDown();
	}

	/**
	 * Create an active template with one active schedule.
	 *
	 * @param string $frequency Schedule frequency.
	 * @param int    $next_run  UTC timestamp.
	 * @return int Template ID.
	 */
	private function template_with_schedule( $frequency, $next_run ) {
		$template_id = ( new AIPS_Template_Repository() )->create(
			array(
				'name'            => 'Pending Stats Template',
				'prompt_template' => 'Write about {{topic}}',
				'post_status'     => 'draft',
				'post_category'   => 1,
				'is_active'       => 1,
			)
		);

		( new AIPS_Schedule_Repository() )->create(
			array(
				'template_id' => $template_id,
				'frequency'   => $frequency,
				'next_run'    => $next_run,
				'is_active'   => 1,
			)
		);

		delete_transient( 'aips_pending_schedule_stats' );

		return $template_id;
	}

	public function test_daily_schedule_counts_one_run_per_day() {
		$template_id = $this->template_with_schedule( 'daily', AIPS_DateTime::now()->timestamp() + 2 * HOUR_IN_SECONDS );

		$stats = ( new AIPS_Templates() )->get_pending_stats( $template_id );

		// Runs at +2h, +1d2h ... +6d2h fall inside 7 days; +29d2h is the last inside 30.
		$this->assertSame( 7, $stats['week'] );
		$this->assertSame( 30, $stats['month'] );
	}

	public function test_all_pending_stats_match_per_template_stats() {
		$template_id = $this->template_with_schedule( 'weekly', AIPS_DateTime::now()->timestamp() + HOUR_IN_SECONDS );

		$all = ( new AIPS_Templates() )->get_all_pending_stats();

		// Runs at +1h, +7d1h, +14d1h, +21d1h, +28d1h: one inside 7 days, five inside 30.
		$this->assertArrayHasKey( $template_id, $all );
		$this->assertSame( 1, $all[ $template_id ]['week'] );
		$this->assertSame( 5, $all[ $template_id ]['month'] );
	}
}
