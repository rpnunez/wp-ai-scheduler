<?php
/**
 * Tests for database migration version tracking.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_DB_Migrations extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		if (!defined('ABSPATH') || !file_exists(ABSPATH . 'wp-admin/includes/upgrade.php')) {
			$this->markTestSkipped('Database migration tests require the full WordPress test library.');
		}

		delete_option('aips_db_version');
	}

	public function tearDown(): void {
		delete_option('aips_db_version');
		parent::tearDown();
	}

	/**
	 * install_tables() must persist aips_db_version so the schema level is
	 * always recorded, regardless of the call site.
	 */
	public function test_install_tables_saves_db_version() {
		AIPS_DB_Manager::install_tables();

		$saved = get_option('aips_db_version');
		$this->assertSame(AIPS_VERSION, $saved, 'aips_db_version should equal AIPS_VERSION after install_tables()');
	}

	/**
	 * install_tables() must create the composite indexes on aips_notifications
	 * so that get_unread() and was_recently_sent() can use covering scans.
	 */
	public function test_install_tables_creates_notifications_composite_indexes() {
		AIPS_DB_Manager::install_tables();

		global $wpdb;
		$table = $wpdb->prefix . 'aips_notifications';

		$index_names = $wpdb->get_col( "SHOW INDEX FROM `{$table}`", 2 );

		$this->assertContains(
			'is_read_created_at',
			$index_names,
			'Composite index is_read_created_at must exist on aips_notifications'
		);
		$this->assertContains(
			'dedupe_key_created_at',
			$index_names,
			'Composite index dedupe_key_created_at must exist on aips_notifications'
		);
	}

	/**
	 * install_tables() must create the notification read-receipts table used
	 * for per-user unread state in the admin bar.
	 */
	public function test_install_tables_creates_notification_reads_table() {
		AIPS_DB_Manager::install_tables();

		global $wpdb;
		$table = $wpdb->prefix . 'aips_notification_reads';

		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		$this->assertSame( $table, $table_exists, 'aips_notification_reads table must exist after install_tables().' );

		$index_names = $wpdb->get_col( "SHOW INDEX FROM `{$table}`", 2 );
		$this->assertContains( 'PRIMARY', $index_names );
		$this->assertContains( 'user_id', $index_names );
	}

	/**
	 * install_tables() must repair legacy tables that have lost their id
	 * primary key and contain duplicate zero ids before dbDelta runs.
	 */
	public function test_install_tables_repairs_history_duplicate_zero_ids_before_dbdelta() {
		AIPS_DB_Manager::install_tables();

		global $wpdb;
		$table = $wpdb->prefix . 'aips_history';

		$wpdb->query( "DELETE FROM `{$table}`" );

		$wpdb->insert(
			$table,
			array(
				'id'           => 1,
				'status'       => 'pending',
				'created_at'   => 100,
				'completed_at' => 0,
			),
			array( '%d', '%s', '%d', '%d' )
		);
		$wpdb->insert(
			$table,
			array(
				'id'           => 2,
				'status'       => 'completed',
				'created_at'   => 200,
				'completed_at' => 300,
			),
			array( '%d', '%s', '%d', '%d' )
		);
		$wpdb->insert(
			$table,
			array(
				'id'           => 3,
				'status'       => 'failed',
				'created_at'   => 400,
				'completed_at' => 500,
			),
			array( '%d', '%s', '%d', '%d' )
		);

		$wpdb->query( "ALTER TABLE `{$table}` MODIFY COLUMN id bigint(20) NOT NULL DEFAULT 0" );
		$wpdb->query( "ALTER TABLE `{$table}` DROP PRIMARY KEY" );
		$wpdb->query( "UPDATE `{$table}` SET id = 0" );

		$result = AIPS_DB_Manager::install_tables();

		$this->assertTrue( $result === true, 'install_tables() should repair duplicate zero ids and succeed.' );

		$index_names = $wpdb->get_col( "SHOW INDEX FROM `{$table}`", 2 );
		$this->assertContains( 'PRIMARY', $index_names, 'History table must have a primary key again after repair.' );

		$duplicate_zero_rows = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM (
				SELECT id
				FROM `{$table}`
				GROUP BY id
				HAVING id <= 0 OR COUNT(*) > 1
			) AS invalid_ids"
		);
		$this->assertSame( 0, $duplicate_zero_rows, 'History table ids must be unique positive integers after repair.' );

		$row_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE created_at IN (100, 200, 400)" );
		$this->assertSame( 3, $row_count, 'History rows must be preserved during primary-key repair.' );
	}

	/**
	 * check_and_run() with a pre-2.3.1 version must add the composite indexes
	 * to aips_notifications via the versioned migrate_to_2_3_1() migration.
	 */
	public function test_migrate_to_2_3_1_adds_composite_indexes() {
		// Simulate an existing install at a pre-2.3.1 version that has the
		// notifications table but lacks the composite indexes.
		AIPS_DB_Manager::install_tables();

		global $wpdb;
		$table = $wpdb->prefix . 'aips_notifications';

		// Drop the composite indexes to simulate the pre-2.3.1 state.
		$wpdb->query( "ALTER TABLE `{$table}` DROP INDEX IF EXISTS is_read_created_at" );
		$wpdb->query( "ALTER TABLE `{$table}` DROP INDEX IF EXISTS dedupe_key_created_at" );

		// Confirm the indexes are gone before the migration runs.
		$index_names_before = $wpdb->get_col( "SHOW INDEX FROM `{$table}`", 2 );
		$this->assertNotContains( 'is_read_created_at', $index_names_before, 'Pre-condition: index should be absent before migration' );
		$this->assertNotContains( 'dedupe_key_created_at', $index_names_before, 'Pre-condition: index should be absent before migration' );

		// Trigger upgrade from a version older than 2.3.1.
		update_option( 'aips_db_version', '2.3.0' );
		AIPS_DB_Migrations::check_and_run();

		$index_names_after = $wpdb->get_col( "SHOW INDEX FROM `{$table}`", 2 );
		$this->assertContains(
			'is_read_created_at',
			$index_names_after,
			'migrate_to_2_3_1 must add is_read_created_at index'
		);
		$this->assertContains(
			'dedupe_key_created_at',
			$index_names_after,
			'migrate_to_2_3_1 must add dedupe_key_created_at index'
		);
	}

	/**
	 * check_and_run() must trigger an upgrade and save the new version when the
	 * stored version is older than the current plugin version.
	 */
	public function test_check_and_run_saves_version_after_upgrade() {
		update_option('aips_db_version', '0.0.1');

		AIPS_DB_Migrations::check_and_run();

		$saved = get_option('aips_db_version');
		$this->assertSame(AIPS_VERSION, $saved, 'aips_db_version should equal AIPS_VERSION after check_and_run() upgrade');
	}

	/**
	 * check_and_run() must not change the stored version when it already matches
	 * the current plugin version.
	 */
	public function test_check_and_run_skips_when_version_matches() {
		update_option('aips_db_version', AIPS_VERSION);

		AIPS_DB_Migrations::check_and_run();

		$saved = get_option('aips_db_version');
		$this->assertSame(AIPS_VERSION, $saved, 'aips_db_version should remain unchanged when versions match');
	}
}
