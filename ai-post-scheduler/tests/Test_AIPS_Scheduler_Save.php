<?php
/**
 * Tests for AIPS_Scheduler::save_schedule().
 *
 * @package AI_Post_Scheduler
 */

if ( ! function_exists( 'wp_get_current_user' ) ) {
	function wp_get_current_user() {
		return (object) array(
			'ID'         => 0,
			'user_login' => '',
		);
	}
}

class Test_AIPS_Scheduler_Save extends WP_UnitTestCase {

	/** @var AIPS_Scheduler */
	private $scheduler;

	/** @var AIPS_Schedule_Repository|\PHPUnit\Framework\MockObject\MockObject */
	private $repository;

	public function setUp(): void {
		parent::setUp();

		$this->scheduler = new AIPS_Scheduler();
		$this->repository = $this->getMockBuilder( 'AIPS_Schedule_Repository' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'create', 'get_by_id', 'update' ) )
			->getMock();

		$this->scheduler->set_repository( $this->repository );
	}

	public function test_save_schedule_creates_inactive_schedule_when_flag_is_zero() {
		$this->repository->expects( $this->once() )
			->method( 'create' )
			->with(
				$this->callback(
					function ( $data ) {
						return isset( $data['is_active'] )
							&& 0 === $data['is_active']
							&& 15 === $data['template_id']
							&& 'daily' === $data['frequency'];
					}
				)
			)
			->willReturn( false );

		$schedule_id = $this->scheduler->save_schedule(
			array(
				'template_id' => 15,
				'frequency'   => 'daily',
				'start_time'  => '2026-03-20 09:00:00',
				'is_active'   => 0,
				'topic'       => 'Inactive schedule',
			)
		);

		$this->assertFalse( $schedule_id );
	}

	public function test_save_schedule_updates_existing_schedule_to_inactive_when_flag_is_zero() {
		$this->repository->expects( $this->exactly( 3 ) )
			->method( 'get_by_id' )
			->with( 77 )
			->willReturn( null );

		$this->repository->expects( $this->once() )
			->method( 'update' )
			->with(
				77,
				$this->callback(
					function ( $data ) {
						return isset( $data['is_active'] )
							&& 0 === $data['is_active']
							&& 15 === $data['template_id']
							&& 'daily' === $data['frequency'];
					}
				)
			)
			->willReturn( true );

		$updated_id = $this->scheduler->save_schedule(
			array(
				'id'          => 77,
				'template_id' => 15,
				'frequency'   => 'daily',
				'start_time'  => '2026-03-20 10:00:00',
				'is_active'   => 0,
				'topic'       => 'Now inactive schedule',
			)
		);

		$this->assertSame( 77, $updated_id );
	}

	public function test_save_schedule_uses_site_timezone_for_datetime_local_start_time() {
		update_option( 'timezone_string', 'America/New_York' );

		$expected_next_run = ( new DateTimeImmutable( '2026-03-20 16:30:00', wp_timezone() ) )->getTimestamp();

		$this->repository->expects( $this->once() )
			->method( 'create' )
			->with(
				$this->callback(
					function ( $data ) use ( $expected_next_run ) {
						return isset( $data['next_run'] ) && (int) $data['next_run'] === $expected_next_run;
					}
				)
			)
			->willReturn( false );

		$schedule_id = $this->scheduler->save_schedule(
			array(
				'template_id' => 33,
				'frequency'   => 'daily',
				'start_time'  => '2026-03-20T16:30',
				'is_active'   => 1,
				'topic'       => 'Timezone-aware schedule',
			)
		);

		$this->assertFalse( $schedule_id );
	}
}
