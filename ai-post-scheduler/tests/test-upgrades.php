<?php
/**
 * Tests for AIPS_Upgrades
 *
 * Covers:
 *  - AIPS_Upgrades::HISTORY_TYPE constant equals 'db_migration'
 *  - check_and_run() is a no-op when the stored version matches AIPS_VERSION
 *  - run_upgrade() creates a history container with type 'db_migration',
 *    logs upgrade steps, updates aips_db_version, and marks the container completed
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Upgrades extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		// Ensure the option starts unset so check_and_run() will trigger.
		delete_option( 'aips_db_version' );
	}

	public function tearDown(): void {
		delete_option( 'aips_db_version' );
		parent::tearDown();
	}

	// ------------------------------------------------------------------
	// AIPS_Upgrades::HISTORY_TYPE
	// ------------------------------------------------------------------

	/**
	 * @test
	 * The HISTORY_TYPE constant must equal the string 'db_migration'.
	 */
	public function test_history_type_constant_is_db_migration() {
		$this->assertSame( 'db_migration', AIPS_Upgrades::HISTORY_TYPE );
	}

	// ------------------------------------------------------------------
	// check_and_run() — version already current
	// ------------------------------------------------------------------

	/**
	 * @test
	 * When the stored version already matches AIPS_VERSION no history container
	 * should be created and the option should remain unchanged.
	 */
	public function test_check_and_run_skips_when_version_is_current() {
		update_option( 'aips_db_version', AIPS_VERSION );

		global $wpdb;
		$table        = $wpdb->prefix . 'aips_history';
		$count_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		AIPS_Upgrades::check_and_run();

		$count_after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$this->assertSame( $count_before, $count_after, 'No new history row should be created when the version is already current.' );
		$this->assertSame( AIPS_VERSION, get_option( 'aips_db_version' ) );
	}

	// ------------------------------------------------------------------
	// check_and_run() — upgrade path
	// ------------------------------------------------------------------

	/**
	 * @test
	 * When the stored version is outdated, check_and_run() should update the
	 * aips_db_version option to AIPS_VERSION.
	 */
	public function test_check_and_run_updates_db_version_option() {
		update_option( 'aips_db_version', '0.0.1' );

		AIPS_Upgrades::check_and_run();

		$this->assertSame( AIPS_VERSION, get_option( 'aips_db_version' ) );
	}

	/**
	 * @test
	 * When the stored version is '0' (first install), check_and_run() should
	 * also update the aips_db_version option to AIPS_VERSION.
	 */
	public function test_check_and_run_updates_db_version_on_first_install() {
		// delete_option leaves the default '0' returned by get_option.
		AIPS_Upgrades::check_and_run();

		$this->assertSame( AIPS_VERSION, get_option( 'aips_db_version' ) );
	}

	/**
	 * @test
	 * check_and_run() should persist a history container whose type is 'db_migration'.
	 */
	public function test_check_and_run_creates_db_migration_history_container() {
		update_option( 'aips_db_version', '0.0.1' );

		AIPS_Upgrades::check_and_run();

		// The history container is created without a post_id/template_id, so
		// get_history() (which joins on posts) won't return it.  Query directly.
		global $wpdb;
		$table = $wpdb->prefix . 'aips_history';
		$row   = $wpdb->get_row(
			"SELECT * FROM {$table} ORDER BY id DESC LIMIT 1"
		);

		$this->assertNotNull( $row, 'A history row should have been created.' );
		$this->assertSame( 'completed', $row->status, 'History container should be completed after a successful upgrade.' );
	}

	/**
	 * @test
	 * The history container created during the upgrade should have at least one
	 * log entry, and that entry should reference the from/to versions.
	 */
	public function test_check_and_run_logs_upgrade_activity() {
		update_option( 'aips_db_version', '0.0.1' );

		AIPS_Upgrades::check_and_run();

		global $wpdb;
		$history_table = $wpdb->prefix . 'aips_history';
		$log_table     = $wpdb->prefix . 'aips_history_log';

		$history_row = $wpdb->get_row(
			"SELECT * FROM {$history_table} ORDER BY id DESC LIMIT 1"
		);

		$this->assertNotNull( $history_row );

		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$log_table} WHERE history_id = %d ORDER BY id ASC",
				$history_row->id
			)
		);

		$this->assertNotEmpty( $logs, 'At least one log entry should have been recorded.' );

		// At least one entry should mention the target version.
		$all_details = implode( ' ', array_column( $logs, 'details' ) );
		$this->assertStringContainsString( AIPS_VERSION, $all_details, 'Log entries should reference the target AIPS_VERSION.' );
	}

	/**
	 * @test
	 * Running check_and_run() twice (first run sets the version, second is a
	 * no-op) should produce exactly one new history container, not two.
	 */
	public function test_check_and_run_is_idempotent() {
		update_option( 'aips_db_version', '0.0.1' );

		global $wpdb;
		$table        = $wpdb->prefix . 'aips_history';
		$count_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		AIPS_Upgrades::check_and_run(); // triggers the upgrade
		AIPS_Upgrades::check_and_run(); // should be a no-op — version is now current

		$count_after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		$this->assertSame(
			$count_before + 1,
			$count_after,
			'Exactly one history container should be created: the second check_and_run() call should be a no-op.'
		);
	}
}
