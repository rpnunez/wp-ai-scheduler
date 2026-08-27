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

	/**
	 * Legacy partial-generation keys should be renamed and the generated-post
	 * marker should be backfilled without duplicating rows on re-run.
	 */
	public function test_migrate_to_3_0_1_normalizes_partial_generation_meta() {
		AIPS_DB_Manager::install_tables();

		$post_id = self::factory()->post->create( array(
			'post_title' => 'Legacy Partial Generation Post',
		) );

		add_post_meta( $post_id, 'aips_post_generation_component_statuses', wp_json_encode( array( 'post_title' => true ) ) );
		add_post_meta( $post_id, 'aips_post_generation_incomplete', 'true' );
		add_post_meta( $post_id, AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES, wp_json_encode( array( 'post_title' => false ) ) );

		update_option( 'aips_db_version', '3.0.0' );
		AIPS_DB_Migrations::check_and_run();
		update_option( 'aips_db_version', '3.0.0' );
		AIPS_DB_Migrations::check_and_run();

		$this->assertFalse( metadata_exists( 'post', $post_id, 'aips_post_generation_component_statuses' ) );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'aips_post_generation_incomplete' ) );
		$this->assertSame(
			wp_json_encode( array( 'post_title' => false ) ),
			get_post_meta( $post_id, AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES, true )
		);
		$this->assertSame( 'true', get_post_meta( $post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, true ) );
		$this->assertSame( '1', get_post_meta( $post_id, AIPS_Post_Manager::META_GENERATED_POST, true ) );

		global $wpdb;
		$generated_marker_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$wpdb->postmeta}
				WHERE post_id = %d
				AND meta_key = %s",
				$post_id,
				AIPS_Post_Manager::META_GENERATED_POST
			)
		);
		$this->assertSame( 1, $generated_marker_count );
	}

	/**
	 * The generated-post marker should also backfill for posts referenced only
	 * by generation history.
	 */
	public function test_migrate_to_3_0_1_backfills_generated_post_marker_from_history() {
		AIPS_DB_Manager::install_tables();

		$post_id = self::factory()->post->create( array(
			'post_title' => 'History Only Generated Post',
		) );

		$history_repository = new AIPS_History_Repository();
		$history_id         = $history_repository->create( array(
			'post_id' => $post_id,
			'status'  => 'completed',
		) );

		$this->assertNotFalse( $history_id );
		$this->assertFalse( metadata_exists( 'post', $post_id, AIPS_Post_Manager::META_GENERATED_POST ) );

		update_option( 'aips_db_version', '3.0.0' );
		AIPS_DB_Migrations::check_and_run();

		$this->assertSame( '1', get_post_meta( $post_id, AIPS_Post_Manager::META_GENERATED_POST, true ) );
	}
}
