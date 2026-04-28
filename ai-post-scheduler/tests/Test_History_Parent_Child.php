<?php
/**
 * Tests for History v2 parent-child relationship in AIPS_History_Repository.
 *
 * Covers:
 *  - Creating a history record with parent_id
 *  - get_children()
 *  - get_top_level()
 *  - get_child_summary() aggregates
 *  - Zero-child summary case
 *
 * @package AI_Post_Scheduler
 */

class Test_History_Parent_Child extends WP_UnitTestCase {

	/** @var AIPS_History_Repository */
	private $repository;

	/** @var int */
	private $parent_id;

	/** @var array */
	private $child_ids = array();

	public function setUp(): void {
		parent::setUp();
		$this->repository = new AIPS_History_Repository();

		// Create the parent history record.
		$this->parent_id = $this->repository->create( array(
			'uuid'            => wp_generate_uuid4(),
			'correlation_id'  => wp_generate_uuid4(),
			'status'          => 'processing',
			'creation_method' => AIPS_History_Operation_Type::TOPIC_GENERATION_BATCH,
			'trigger_name'    => 'cron',
		) );

		$this->assertIsInt( $this->parent_id, 'Parent history record must be created with an integer ID.' );
		$this->assertGreaterThan( 0, $this->parent_id );
	}

	public function tearDown(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_history';

		// Clean up all records we created, plus any children.
		if ( $this->parent_id ) {
			$wpdb->delete( $table, array( 'id' => $this->parent_id ), array( '%d' ) );
		}
		foreach ( $this->child_ids as $cid ) {
			$wpdb->delete( $table, array( 'id' => $cid ), array( '%d' ) );
		}

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Create a child record linked to $this->parent_id and register it for teardown.
	 *
	 * @param string $status   'completed', 'error', or 'processing'.
	 * @param string $title    Optional generated_title value.
	 * @return int Child record ID.
	 */
	private function create_child( $status = 'completed', $title = '' ) {
		$id = $this->repository->create( array(
			'uuid'             => wp_generate_uuid4(),
			'correlation_id'   => wp_generate_uuid4(),
			'status'           => $status,
			'creation_method'  => AIPS_History_Operation_Type::AUTHOR_TOPIC_GENERATION,
			'parent_id'        => $this->parent_id,
			'generated_title'  => $title ?: ( 'Child post ' . wp_generate_password( 6, false ) ),
		) );
		$this->assertGreaterThan( 0, $id, 'Child history record must be persisted.' );
		$this->child_ids[] = $id;
		return $id;
	}

	// -------------------------------------------------------------------------
	// Tests
	// -------------------------------------------------------------------------

	/**
	 * Creating a record with parent_id stores and retrieves it correctly.
	 */
	public function test_create_with_parent_id_round_trips() {
		$child_id = $this->create_child( 'completed', 'Round-trip title' );

		$record = $this->repository->get_by_id( $child_id );

		$this->assertNotNull( $record, 'Child record should be retrievable by ID.' );
		$this->assertEquals( $this->parent_id, (int) $record->parent_id, 'parent_id should match the parent.' );
		$this->assertEquals( 'completed', $record->status );
		$this->assertEquals( 'Round-trip title', $record->generated_title );
	}

	/**
	 * get_children() returns all children and only children of the specified parent.
	 */
	public function test_get_children_returns_all_children() {
		$child_a = $this->create_child( 'completed', 'Alpha' );
		$child_b = $this->create_child( 'error',     'Beta' );
		$child_c = $this->create_child( 'processing', 'Gamma' );

		$children = $this->repository->get_children( $this->parent_id );

		$this->assertIsArray( $children, 'get_children must return an array.' );
		$this->assertCount( 3, $children, 'Should return exactly 3 child records.' );

		$returned_ids = array_map( function ( $row ) { return (int) $row->id; }, $children );
		$this->assertContains( $child_a, $returned_ids );
		$this->assertContains( $child_b, $returned_ids );
		$this->assertContains( $child_c, $returned_ids );
	}

	/**
	 * get_children() returns an empty array when the parent has no children.
	 */
	public function test_get_children_returns_empty_for_childless_parent() {
		$children = $this->repository->get_children( $this->parent_id );
		$this->assertIsArray( $children );
		$this->assertEmpty( $children, 'No children should be returned when none exist.' );
	}

	/**
	 * get_top_level() returns the parent record but not child records.
	 */
	public function test_get_top_level_excludes_child_records() {
		$this->create_child( 'completed' );
		$this->create_child( 'completed' );

		$result = $this->repository->get_top_level( array( 'per_page' => 100 ) );

		$this->assertArrayHasKey( 'items', $result );
		$this->assertArrayHasKey( 'total', $result );

		$top_ids = array_map( function ( $row ) { return (int) $row->id; }, $result['items'] );

		$this->assertContains( $this->parent_id, $top_ids, 'Parent should appear in top-level results.' );
		foreach ( $this->child_ids as $cid ) {
			$this->assertNotContains( $cid, $top_ids, 'Child records must not appear in top-level results.' );
		}
	}

	/**
	 * get_top_level() can filter by operation_type.
	 */
	public function test_get_top_level_filters_by_operation_type() {
		$result = $this->repository->get_top_level( array(
			'per_page'       => 100,
			'operation_type' => AIPS_History_Operation_Type::TOPIC_GENERATION_BATCH,
		) );

		$top_ids = array_map( function ( $row ) { return (int) $row->id; }, $result['items'] );
		$this->assertContains( $this->parent_id, $top_ids );
	}

	/**
	 * get_child_summary() returns correct aggregate counts.
	 */
	public function test_get_child_summary_aggregates_counts() {
		$this->create_child( 'completed' );
		$this->create_child( 'completed' );
		$this->create_child( 'error' );
		$this->create_child( 'processing' );

		$summary = $this->repository->get_child_summary( $this->parent_id );

		$this->assertNotNull( $summary, 'Summary must not be null when children exist.' );
		$this->assertEquals( 4, (int) $summary->total,           'total should be 4.' );
		$this->assertEquals( 2, (int) $summary->completed_count, 'completed_count should be 2.' );
		$this->assertEquals( 1, (int) $summary->failed_count,    'failed_count should be 1.' );
		$this->assertEquals( 1, (int) $summary->processing_count, 'processing_count should be 1.' );
	}

	/**
	 * get_child_summary() returns zeroed counts when the parent has no children.
	 */
	public function test_get_child_summary_returns_zeros_with_no_children() {
		$summary = $this->repository->get_child_summary( $this->parent_id );

		// The repository may return null or an object with zero counts — both acceptable.
		if ( $summary !== null ) {
			$this->assertEquals( 0, (int) $summary->total, 'total should be 0 when no children.' );
		} else {
			$this->assertNull( $summary );
		}
	}

	/**
	 * Parent record's parent_id is NULL (it is a true top-level record).
	 */
	public function test_parent_record_has_null_parent_id() {
		$parent = $this->repository->get_by_id( $this->parent_id );
		$this->assertNull( $parent->parent_id, 'Top-level parent_id must be NULL.' );
	}
}
