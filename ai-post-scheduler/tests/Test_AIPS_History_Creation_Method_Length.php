<?php
/**
 * Regression coverage for the aips_history.creation_method column width.
 *
 * The column was varchar(20), which is shorter than several history type
 * names that production code already passes to AIPS_History_Service::create()
 * -- 'topic_post_generation' (21) and 'author_post_generation' (22) among
 * them. wpdb rejects an over-long value before the INSERT is issued, so those
 * history containers silently failed to persist and the generation runs they
 * described produced no history row at all.
 *
 * @package AI_Post_Scheduler
 */
class Test_AIPS_History_Creation_Method_Length extends WP_UnitTestCase {

	/**
	 * @var AIPS_History_Repository
	 */
	private $repository;

	public function setUp(): void {
		parent::setUp();
		$this->repository = new AIPS_History_Repository();
	}

	/**
	 * Every canonical event type must fit, since the catalog is the vocabulary
	 * callers draw history type names from.
	 */
	public function test_column_accommodates_longest_canonical_event_type() {
		global $wpdb;

		$table  = $wpdb->prefix . 'aips_history';
		$length = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
				 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'creation_method'",
				$table
			)
		);

		$longest = 0;
		foreach ( AIPS_History_Event_Type::all() as $event_type ) {
			$longest = max( $longest, strlen( $event_type ) );
		}

		$this->assertGreaterThanOrEqual(
			$longest,
			$length,
			"creation_method varchar({$length}) is too narrow for the longest canonical event type ({$longest} chars)."
		);
	}

	/**
	 * The specific names production code passes today must round-trip.
	 *
	 * @dataProvider provide_production_creation_methods
	 *
	 * @param string $creation_method Creation method written by production code.
	 */
	public function test_production_creation_methods_persist( $creation_method ) {
		$history_id = (int) $this->repository->create( array(
			'uuid'            => wp_generate_uuid4(),
			'creation_method' => $creation_method,
			'status'          => 'completed',
		) );

		$this->assertGreaterThan( 0, $history_id, "create() rejected creation_method '{$creation_method}'." );

		$row = $this->repository->get_by_id( $history_id );
		$this->assertSame( $creation_method, $row->creation_method );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function provide_production_creation_methods() {
		return array(
			'author post generation' => array( 'author_post_generation' ),
			'topic post generation'  => array( 'topic_post_generation' ),
			'schedule lifecycle'     => array( 'schedule_lifecycle' ),
			'template lifecycle'     => array( 'template_lifecycle' ),
			'campaign lifecycle'     => array( 'campaign_lifecycle' ),
			'scheduled'              => array( 'scheduled' ),
		);
	}
}
