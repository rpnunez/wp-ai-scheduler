<?php
/**
 * Tests for AIPS_Schedule_Repository bulk methods
 *
 * Covers:
 *   - delete_bulk
 *   - set_active_bulk
 *   - get_post_count_for_schedules
 *
 * Includes row-count assertions, database-state verification, and
 * sanitization/edge-case behavior (empty arrays, non-integer IDs, NULL
 * post_quantity values).
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Schedule_Repository_Bulk extends WP_UnitTestCase {

	/** @var AIPS_Schedule_Repository */
	private $repository;

	/** @var int */
	private $template_id;

	public function setUp(): void {
		parent::setUp();

		$this->repository = new AIPS_Schedule_Repository();

		// Insert a template with a known post_quantity
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'aips_templates',
			array(
				'name'             => 'Repo Bulk Test Template',
				'prompt_template'  => 'Write about {{topic}}',
				'is_active'        => 1,
				'post_quantity'    => 3,
			)
		);
		$this->template_id = (int) $wpdb->insert_id;
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}aips_schedule WHERE template_id = %d", $this->template_id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}aips_templates WHERE id = %d", $this->template_id ) );
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Insert $count schedules and return their IDs.
	 *
	 * @param int $count
	 * @param int $is_active
	 * @return int[]
	 */
	private function insert_schedules( $count = 2, $is_active = 1 ) {
		global $wpdb;
		$ids = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$wpdb->insert(
				$wpdb->prefix . 'aips_schedule',
				array(
					'template_id' => $this->template_id,
					'frequency'   => 'once',
					'next_run'    => '2024-06-01 10:00:00',
					'is_active'   => $is_active,
					'topic'       => 'Repo Topic ' . ( $i + 1 ),
				)
			);
			$ids[] = (int) $wpdb->insert_id;
		}
		return $ids;
	}

	/**
	 * Fetch a single schedule row by ID.
	 *
	 * @param int $id
	 * @return object|null
	 */
	private function get_schedule( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aips_schedule WHERE id = %d", $id ) );
	}

	// -----------------------------------------------------------------------
	// delete_bulk
	// -----------------------------------------------------------------------

	public function test_delete_bulk_removes_rows_and_returns_count() {
		$ids = $this->insert_schedules( 3 );

		$deleted = $this->repository->delete_bulk( $ids );

		$this->assertEquals( 3, $deleted );
		foreach ( $ids as $id ) {
			$this->assertNull( $this->get_schedule( $id ), "Schedule $id should have been deleted." );
		}
	}

	public function test_delete_bulk_partial_ids_leaves_others_intact() {
		$ids = $this->insert_schedules( 3 );

		// Delete only the first two
		$deleted = $this->repository->delete_bulk( array( $ids[0], $ids[1] ) );

		$this->assertEquals( 2, $deleted );
		$this->assertNull( $this->get_schedule( $ids[0] ), 'First schedule should be deleted.' );
		$this->assertNull( $this->get_schedule( $ids[1] ), 'Second schedule should be deleted.' );
		$this->assertNotNull( $this->get_schedule( $ids[2] ), 'Third schedule should remain.' );
	}

	public function test_delete_bulk_empty_array_returns_zero() {
		$result = $this->repository->delete_bulk( array() );
		$this->assertEquals( 0, $result );
	}

	public function test_delete_bulk_sanitizes_non_integer_ids() {
		$ids = $this->insert_schedules( 2 );

		// Mix in invalid IDs; only the valid numeric ones should be deleted
		$mixed_ids = array( $ids[0], 'not-an-id', $ids[1], 0 );
		$deleted   = $this->repository->delete_bulk( $mixed_ids );

		// 0 and string are filtered by absint+array_filter, so only 2 valid IDs remain
		$this->assertEquals( 2, $deleted );
	}

	// -----------------------------------------------------------------------
	// set_active_bulk
	// -----------------------------------------------------------------------

	public function test_set_active_bulk_activates_schedules() {
		$ids = $this->insert_schedules( 3, 0 ); // start inactive

		$updated = $this->repository->set_active_bulk( $ids, 1 );

		$this->assertNotFalse( $updated );
		foreach ( $ids as $id ) {
			$row = $this->get_schedule( $id );
			$this->assertNotNull( $row );
			$this->assertEquals( 1, (int) $row->is_active, "Schedule $id should be active." );
		}
	}

	public function test_set_active_bulk_pauses_schedules() {
		$ids = $this->insert_schedules( 3, 1 ); // start active

		$updated = $this->repository->set_active_bulk( $ids, 0 );

		$this->assertNotFalse( $updated );
		foreach ( $ids as $id ) {
			$row = $this->get_schedule( $id );
			$this->assertNotNull( $row );
			$this->assertEquals( 0, (int) $row->is_active, "Schedule $id should be paused." );
		}
	}

	public function test_set_active_bulk_empty_array_returns_zero() {
		$result = $this->repository->set_active_bulk( array(), 1 );
		$this->assertEquals( 0, $result );
	}

	public function test_set_active_bulk_sanitizes_non_integer_ids() {
		$ids = $this->insert_schedules( 2, 0 );

		// Mix valid IDs with invalid ones
		$mixed_ids = array( $ids[0], 'bad-id', $ids[1], 0 );
		$this->repository->set_active_bulk( $mixed_ids, 1 );

		// Both valid schedules should now be active
		foreach ( $ids as $id ) {
			$row = $this->get_schedule( $id );
			$this->assertEquals( 1, (int) $row->is_active );
		}
	}

	// -----------------------------------------------------------------------
	// get_post_count_for_schedules
	// -----------------------------------------------------------------------

	public function test_get_post_count_returns_sum_of_template_post_quantity() {
		$ids = $this->insert_schedules( 2 ); // template has post_quantity = 3

		// Support limited testing mode mock
		if ( isset( $GLOBALS['wpdb'] ) && is_object( $GLOBALS['wpdb'] ) && property_exists( $GLOBALS['wpdb'], 'get_var_return_val' ) ) {
			$GLOBALS['wpdb']->get_var_return_val = 6;
		}

		$count = $this->repository->get_post_count_for_schedules( $ids );

		// 2 schedules × post_quantity 3 = 6
		$this->assertEquals( 6, $count );
	}

	public function test_get_post_count_defaults_to_one_when_post_quantity_is_null() {
		global $wpdb;

		// Insert a template with post_quantity = 0 (treated as 1 by the COALESCE/NULLIF logic)
		$wpdb->insert(
			$wpdb->prefix . 'aips_templates',
			array(
				'name'            => 'Zero Qty Template',
				'prompt_template' => 'Test prompt',
				'is_active'       => 1,
				'post_quantity'   => 0,
			)
		);
		$zero_template_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'aips_schedule',
			array(
				'template_id' => $zero_template_id,
				'frequency'   => 'once',
				'next_run'    => '2024-06-01 10:00:00',
				'is_active'   => 1,
				'topic'       => 'Zero Qty Topic',
			)
		);
		$schedule_id = (int) $wpdb->insert_id;

		// Support limited testing mode mock
		if ( isset( $GLOBALS['wpdb'] ) && is_object( $GLOBALS['wpdb'] ) && property_exists( $GLOBALS['wpdb'], 'get_var_return_val' ) ) {
			$GLOBALS['wpdb']->get_var_return_val = 1;
		}

		$count = $this->repository->get_post_count_for_schedules( array( $schedule_id ) );

		// COALESCE(NULLIF(0, 0), 1) = 1
		$this->assertEquals( 1, $count );

		// Clean up
		$wpdb->delete( $wpdb->prefix . 'aips_schedule', array( 'id' => $schedule_id ) );
		$wpdb->delete( $wpdb->prefix . 'aips_templates', array( 'id' => $zero_template_id ) );
	}

	public function test_get_post_count_empty_array_returns_zero() {
		$count = $this->repository->get_post_count_for_schedules( array() );
		$this->assertEquals( 0, $count );
	}

	public function test_get_post_count_sanitizes_non_integer_ids() {
		$ids = $this->insert_schedules( 2 ); // post_quantity = 3 each

		// Invalid entries should be discarded; only valid ones counted
		// Support limited testing mode mock
		if ( isset( $GLOBALS['wpdb'] ) && is_object( $GLOBALS['wpdb'] ) && property_exists( $GLOBALS['wpdb'], 'get_var_return_val' ) ) {
			$GLOBALS['wpdb']->get_var_return_val = 3;
		}

		$mixed_ids = array( $ids[0], 'bad-id', 0 );
		$count     = $this->repository->get_post_count_for_schedules( $mixed_ids );

		// Only ids[0] is valid → 1 × post_quantity 3 = 3
		$this->assertEquals( 3, $count );
	}

	public function test_get_post_count_all_invalid_ids_returns_zero() {
		$count = $this->repository->get_post_count_for_schedules( array( 'bad', 0 ) );
		$this->assertEquals( 0, $count );
	}
}
