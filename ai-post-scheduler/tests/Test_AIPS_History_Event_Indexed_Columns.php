<?php
/**
 * Tests that event_type / event_status are mirrored into indexed columns on
 * write and that repository reads filter on those columns with alias expansion.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_History_Event_Indexed_Columns extends WP_UnitTestCase {

	/**
	 * @var AIPS_History_Repository
	 */
	private $repository;

	public function setUp(): void {
		parent::setUp();
		$this->repository = new AIPS_History_Repository();
	}

	/**
	 * Insert a history container row and return its id.
	 *
	 * @param array $overrides Column overrides.
	 * @return int
	 */
	private function create_history_row( $overrides = array() ) {
		return (int) $this->repository->create( array_merge( array(
			'uuid'            => wp_generate_uuid4(),
			'creation_method' => 'author_post_generation',
			'status'          => 'completed',
		), $overrides ) );
	}

	/**
	 * Fetch the raw log row by id.
	 *
	 * @param int $log_id Log id.
	 * @return object|null
	 */
	private function get_log_row( $log_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_history_log';
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $log_id ) );
	}

	public function test_add_log_entry_mirrors_event_identity_into_columns() {
		$history_id = $this->create_history_row();

		$details = array(
			'log_subtype' => 'activity',
			'message'     => 'Author post generated',
			'input'       => array(
				'event_type'   => 'author_post_generation',
				'event_status' => 'success',
			),
			'context'     => array( 'author_id' => 1 ),
		);

		$log_id = $this->repository->add_log_entry( $history_id, $details, AIPS_History_Type::ACTIVITY );
		$this->assertNotFalse( $log_id );

		$row = $this->get_log_row( $log_id );
		$this->assertSame( 'author_post_generation', $row->event_type );
		$this->assertSame( 'success', $row->event_status );
	}

	public function test_add_log_entry_leaves_columns_null_without_event_identity() {
		$history_id = $this->create_history_row();

		$log_id = $this->repository->add_log_entry(
			$history_id,
			array( 'log_subtype' => 'debug', 'message' => 'just a debug line' ),
			AIPS_History_Type::DEBUG
		);

		$row = $this->get_log_row( $log_id );
		$this->assertNull( $row->event_type );
		$this->assertNull( $row->event_status );
	}

	public function test_author_schedule_logs_match_legacy_alias_via_expansion() {
		$author_id  = 77;
		$history_id = $this->create_history_row( array( 'author_id' => $author_id ) );

		// Legacy row stored with the pre-contract event name.
		$this->repository->add_log_entry(
			$history_id,
			array(
				'log_subtype' => 'activity',
				'message'     => 'legacy row',
				'input'       => array( 'event_type' => 'topic_post_generation', 'event_status' => 'success' ),
			),
			AIPS_History_Type::ACTIVITY
		);

		// New row stored with the canonical event name.
		$this->repository->add_log_entry(
			$history_id,
			array(
				'log_subtype' => 'activity',
				'message'     => 'canonical row',
				'input'       => array( 'event_type' => 'author_post_generation', 'event_status' => 'success' ),
			),
			AIPS_History_Type::ACTIVITY
		);

		// Querying by the canonical name must return BOTH rows.
		$logs = $this->repository->get_author_schedule_logs_by_event_types(
			$author_id,
			array( AIPS_History_Event_Type::AUTHOR_POST_GENERATION ),
			50
		);

		$this->assertCount( 2, $logs );
	}

	public function test_activity_feed_filters_by_event_type_column() {
		$history_id = $this->create_history_row();

		$this->repository->add_log_entry(
			$history_id,
			array(
				'log_subtype' => 'activity',
				'message'     => 'approved',
				'input'       => array( 'event_type' => 'topic_approved', 'event_status' => 'success' ),
			),
			AIPS_History_Type::ACTIVITY
		);
		$this->repository->add_log_entry(
			$history_id,
			array(
				'log_subtype' => 'activity',
				'message'     => 'rejected',
				'input'       => array( 'event_type' => 'topic_rejected', 'event_status' => 'failed' ),
			),
			AIPS_History_Type::ACTIVITY
		);

		$approved = $this->filter_by_history( $this->repository->get_activity_feed( 50, 0, array( 'event_type' => 'topic_approved' ) ), $history_id );
		$this->assertCount( 1, $approved );

		$failed = $this->filter_by_history( $this->repository->get_activity_feed( 50, 0, array( 'event_status' => 'failed' ) ), $history_id );
		$this->assertCount( 1, $failed );
	}

	public function test_activity_feed_status_filter_matches_legacy_synonym() {
		$history_id = $this->create_history_row();

		// Row persisted with a legacy status synonym ("complete").
		$this->repository->add_log_entry(
			$history_id,
			array(
				'log_subtype' => 'activity',
				'message'     => 'batch complete',
				'input'       => array( 'event_type' => 'embeddings_batch_complete', 'event_status' => 'complete' ),
			),
			AIPS_History_Type::ACTIVITY
		);

		// Filtering by the canonical status must find the synonym row.
		$results = $this->filter_by_history( $this->repository->get_activity_feed( 50, 0, array( 'event_status' => 'success' ) ), $history_id );
		$this->assertCount( 1, $results );
	}

	/**
	 * Restrict activity-feed rows to those belonging to a given history container,
	 * so unrelated fixture rows cannot affect exact-count assertions.
	 *
	 * @param array $rows       Activity feed rows.
	 * @param int   $history_id History container id.
	 * @return array
	 */
	private function filter_by_history( $rows, $history_id ) {
		return array_values( array_filter( (array) $rows, static function ( $row ) use ( $history_id ) {
			return isset( $row->history_id ) && (int) $row->history_id === (int) $history_id;
		} ) );
	}
}
