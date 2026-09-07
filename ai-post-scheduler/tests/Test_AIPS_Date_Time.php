<?php
/**
 * Tests for AIPS_DateTime::fromSiteLocal().
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Date_Time extends WP_UnitTestCase {

	private $original_timezone_string;
	private $original_gmt_offset;

	public function setUp(): void {
		parent::setUp();

		$this->original_timezone_string = get_option( 'timezone_string' );
		$this->original_gmt_offset      = get_option( 'gmt_offset' );
	}

	public function tearDown(): void {
		update_option( 'timezone_string', $this->original_timezone_string );
		update_option( 'gmt_offset', $this->original_gmt_offset );

		parent::tearDown();
	}

	public function test_from_site_local_interprets_naive_datetime_local_string_in_site_timezone() {
		update_option( 'timezone_string', 'America/New_York' );

		// 2026-03-20 09:00 in America/New_York (EDT, UTC-4 after the March DST change).
		$dt = AIPS_DateTime::fromSiteLocal( '2026-03-20T09:00' );

		$this->assertSame( '2026-03-20 13:00:00', $dt->toUtc()->toMysql() );
	}

	public function test_from_site_local_accepts_mysql_style_string_with_seconds() {
		update_option( 'timezone_string', 'America/New_York' );

		$dt = AIPS_DateTime::fromSiteLocal( '2026-03-20 09:00:00' );

		$this->assertSame( '2026-03-20 13:00:00', $dt->toUtc()->toMysql() );
	}

	public function test_from_site_local_round_trips_back_to_display_time() {
		update_option( 'timezone_string', 'America/New_York' );

		$utc_timestamp = AIPS_DateTime::fromSiteLocal( '2026-03-20T09:00' )->toUtc()->timestamp();

		$this->assertSame(
			'2026-03-20 09:00',
			AIPS_DateTime::fromTimestamp( $utc_timestamp )->toDisplay( 'Y-m-d H:i' )
		);
	}

	public function test_from_site_local_throws_on_unparseable_input() {
		$this->expectException( \InvalidArgumentException::class );

		AIPS_DateTime::fromSiteLocal( 'not-a-date' );
	}
}
