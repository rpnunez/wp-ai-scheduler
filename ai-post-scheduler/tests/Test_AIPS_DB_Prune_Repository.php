<?php
/**
 * Tests for AIPS_DB_Prune_Repository orphaned-log purge.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_DB_Prune_Repository extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		if ( ! file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			$this->markTestSkipped( 'Requires the full WordPress test library.' );
		}
		AIPS_DB_Manager::install_tables();
	}

	/**
	 * purge_orphaned_history_logs() must delete log rows with no parent history
	 * row and keep rows whose parent exists.
	 */
	public function test_purge_orphaned_history_logs() {
		global $wpdb;
		$history = $wpdb->prefix . 'aips_history';
		$log     = $wpdb->prefix . 'aips_history_log';

		$wpdb->insert(
			$history,
			array( 'status' => 'completed', 'record_kind' => 'event', 'created_at' => 1700000000 ),
			array( '%s', '%s', '%d' )
		);
		$parent_id = (int) $wpdb->insert_id;

		$wpdb->insert( $log, array( 'history_id' => $parent_id, 'timestamp' => 1700000000 ), array( '%d', '%d' ) );
		$live_log_id = (int) $wpdb->insert_id;

		$wpdb->insert( $log, array( 'history_id' => 987654321, 'timestamp' => 1700000000 ), array( '%d', '%d' ) );
		$orphan_log_id = (int) $wpdb->insert_id;

		$deleted = ( new AIPS_DB_Prune_Repository() )->purge_orphaned_history_logs();

		$this->assertGreaterThanOrEqual( 1, (int) $deleted );
		$this->assertNull(
			$wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$log}` WHERE id = %d", $orphan_log_id ) ),
			'Orphaned log row should be deleted'
		);
		$this->assertSame(
			(string) $live_log_id,
			$wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$log}` WHERE id = %d", $live_log_id ) ),
			'Log row with a live parent must be kept'
		);

		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$log}` WHERE id = %d", $live_log_id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$history}` WHERE id = %d", $parent_id ) );
	}
}
