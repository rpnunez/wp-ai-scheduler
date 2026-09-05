<?php
/**
 * Tests for monetization REST telemetry validation.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.2
 */

class Test_AIPS_REST_Monetization_Controller extends WP_UnitTestCase {

	/**
	 * Build a telemetry request for one campaign-attributed impression.
	 *
	 * @param int $campaign_id Campaign ID supplied by the client.
	 * @return WP_REST_Request
	 */
	private function make_request( $campaign_id ) {
		$request = new WP_REST_Request( 'POST', '/aips/v1/monetization/track' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'events' => array(
						array(
							'slot_id'     => 0,
							'post_id'     => 0,
							'campaign_id' => $campaign_id,
							'event_type'  => 'impression',
							'device_type' => 'desktop',
							'count'       => 1,
						),
					),
				)
			)
		);

		return $request;
	}

	/**
	 * Nonexistent campaign IDs must not enter aggregated telemetry.
	 */
	public function test_track_events_normalizes_nonexistent_campaign_id_to_zero() {
		$sponsors = new AIPS_Test_Sponsor_Campaigns_Repository();
		$telemetry = new AIPS_Test_Monetization_Telemetry_Repository();
		$controller = new AIPS_REST_Monetization_Controller(
			new AIPS_Test_Ad_Slots_Repository(),
			$sponsors,
			$telemetry
		);

		$response = $controller->track_events( $this->make_request( 999999 ) );

		$this->assertSame( 1, $response->get_data()['recorded'] );
		$this->assertSame( 0, $telemetry->events[0]['campaign_id'] );
		$this->assertSame( array( 999999 ), $sponsors->requested_ids );
	}

	/**
	 * Existing campaign IDs must retain their attribution.
	 */
	public function test_track_events_preserves_existing_campaign_id() {
		$sponsors = new AIPS_Test_Sponsor_Campaigns_Repository( array( 42 ) );
		$telemetry = new AIPS_Test_Monetization_Telemetry_Repository();
		$controller = new AIPS_REST_Monetization_Controller(
			new AIPS_Test_Ad_Slots_Repository(),
			$sponsors,
			$telemetry
		);

		$response = $controller->track_events( $this->make_request( 42 ) );

		$this->assertSame( 1, $response->get_data()['recorded'] );
		$this->assertSame( 42, $telemetry->events[0]['campaign_id'] );
		$this->assertSame( array( 42 ), $sponsors->requested_ids );
	}
}

class AIPS_Test_Sponsor_Campaigns_Repository extends AIPS_Sponsor_Campaigns_Repository {

	/**
	 * @var int[]
	 */
	private $existing_ids;

	/**
	 * @var int[]
	 */
	public $requested_ids = array();

	/**
	 * @param int[] $existing_ids Existing campaign IDs.
	 */
	public function __construct( array $existing_ids = array() ) {
		$this->existing_ids = $existing_ids;
	}

	/**
	 * @param int $id Campaign ID.
	 * @return object|null
	 */
	public function get_by_id( $id ) {
		$id = absint( $id );
		$this->requested_ids[] = $id;

		return in_array( $id, $this->existing_ids, true ) ? (object) array( 'id' => $id ) : null;
	}
}

class AIPS_Test_Ad_Slots_Repository extends AIPS_Ad_Slots_Repository {
	public function __construct() {}
}

class AIPS_Test_Monetization_Telemetry_Repository extends AIPS_Monetization_Telemetry_Repository {

	/**
	 * @var array<int,array<string,mixed>>
	 */
	public $events = array();

	public function __construct() {}

	/**
	 * @param array<int,array<string,mixed>> $events Events to record.
	 * @return int
	 */
	public function record_events_batch( array $events ) {
		$this->events = $events;
		return count( $events );
	}
}
