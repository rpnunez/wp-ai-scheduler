<?php
/** Bounded, opt-in source archive maintenance. */
class Test_AIPS_Sources_Maintenance extends WP_UnitTestCase {
	public function tearDown(): void {
		delete_option( 'aips_source_archive_retention_days' );
		wp_clear_scheduled_hook( AIPS_Sources_Maintenance::HOOK );
		wp_clear_scheduled_hook( AIPS_Sources_Maintenance::CONTINUATION_HOOK );
		parent::tearDown();
	}

	public function test_disabled_retention_does_not_delete_or_schedule() {
		$repo = $this->createMock( AIPS_Sources_Data_Repository::class );
		$repo->expects( $this->never() )->method( 'prune_archive' );
		update_option( 'aips_source_archive_retention_days', 0 );
		$worker = new AIPS_Sources_Maintenance( $repo );
		$worker->run();
		$this->assertFalse( wp_next_scheduled( AIPS_Sources_Maintenance::HOOK ) );
	}

	public function test_enabled_retention_prunes_one_batch_and_schedules_continuation() {
		$repo = $this->createMock( AIPS_Sources_Data_Repository::class );
		$repo->expects( $this->once() )->method( 'prune_archive' )->with( $this->isType( 'int' ), 100 )->willReturn( 100 );
		$repo->method( 'count_prunable_archive' )->willReturn( 1 );
		update_option( 'aips_source_archive_retention_days', 30 );
		$worker = new AIPS_Sources_Maintenance( $repo );
		$worker->run();
		$this->assertNotFalse( wp_next_scheduled( AIPS_Sources_Maintenance::CONTINUATION_HOOK ) );
	}

	public function test_contention_does_not_prune() {
		update_option( 'aips_source_archive_retention_days', 30 );
		$repo = $this->createMock( AIPS_Sources_Data_Repository::class );
		$repo->expects( $this->never() )->method( 'prune_archive' );
		$lease = $this->createMock( AIPS_Sources_Fetch_Lease::class );
		$lease->method( 'acquire' )->willReturn( false );
		$worker = new AIPS_Sources_Maintenance( $repo, $lease );
		$worker->run();
		$this->assertNotFalse( wp_next_scheduled( AIPS_Sources_Maintenance::CONTINUATION_HOOK ) );
	}

	public function test_database_failure_releases_lease_without_success_log() {
		update_option( 'aips_source_archive_retention_days', 30 );
		$repo = $this->createMock( AIPS_Sources_Data_Repository::class );
		$repo->method( 'prune_archive' )->willReturn( false );
		$lease = $this->createMock( AIPS_Sources_Fetch_Lease::class );
		$lease->method( 'acquire' )->willReturn( 'test-owner' );
		$lease->expects( $this->once() )->method( 'release' )->with( 'test-owner' );
		$logger = $this->createMock( AIPS_Logger::class );
		$logger->expects( $this->once() )->method( 'log' )->with( $this->stringContains( 'failed' ), 'error' );
		$worker = new AIPS_Sources_Maintenance( $repo, $lease, $logger );
		$worker->run();
	}

	public function test_retention_setting_is_bounded() {
		$ui = new AIPS_Settings_UI();
		$this->assertSame( 0, $ui->sanitize_source_archive_retention_days( -12 ) );
		$this->assertSame( 3650, $ui->sanitize_source_archive_retention_days( 9000 ) );
		$this->assertSame( 30, $ui->sanitize_source_archive_retention_days( '30' ) );
	}
}
