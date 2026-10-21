<?php
/**
 * Tests for Schedule History & Logging feature.
 *
 * Covers:
 *  - AIPS_History_Repository::get_logs_by_history_id()
 *  - AIPS_Schedule_Controller::ajax_get_schedule_history()
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Schedule_History extends WP_UnitTestCase {

/** @var AIPS_History_Repository */
private $history_repo;

/** @var AIPS_Schedule_Controller */
private $controller;

public function setUp(): void {
parent::setUp();

$this->history_repo = new AIPS_History_Repository();

// Create an admin user and set as current
$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
wp_set_current_user( $user_id );

$mock_scheduler = $this->getMockBuilder( 'AIPS_Scheduler' )
->disableOriginalConstructor()
->onlyMethods( array( 'run_schedule_now', 'save_schedule', 'toggle_active' ) )
->getMock();

$this->controller = new AIPS_Schedule_Controller( $mock_scheduler );
}

public function tearDown(): void {
unset( $_POST );
parent::tearDown();
}

private function set_private_property( $object, $property_name, $value ) {
$reflection = new ReflectionClass( $object );
$property = $reflection->getProperty( $property_name );
$property->setAccessible( true );
$property->setValue( $object, $value );
}

private function capture_ajax_response( $callback ) {
ob_start();

try {
$callback();
} catch ( WPAjaxDieContinueException $e ) {
// Expected.
}

$output = ob_get_clean();
if ( '' === $output && isset( $GLOBALS['aips_last_ajax_output'] ) ) {
$output = (string) $GLOBALS['aips_last_ajax_output'];
}

return json_decode( $output, true );
}

// ------------------------------------------------------------------
// AIPS_History_Repository::get_logs_by_history_id
// ------------------------------------------------------------------

/**
 * @test
 * Verifies that get_logs_by_history_id always returns an array.
 */
public function test_get_logs_by_history_id_returns_array() {
$result = $this->history_repo->get_logs_by_history_id( 999 );
$this->assertIsArray( $result );
}

/**
 * @test
 * Verifies that get_logs_by_history_id with type filter returns an array.
 */
public function test_get_logs_by_history_id_with_filter_returns_array() {
$result = $this->history_repo->get_logs_by_history_id(
999,
array( AIPS_History_Type::ACTIVITY, AIPS_History_Type::ERROR )
);
$this->assertIsArray( $result );
}

/**
 * @test
 * Verifies that get_logs_by_history_id for unknown ID returns empty array.
 */
public function test_get_logs_by_history_id_returns_empty_for_unknown_id() {
$logs = $this->history_repo->get_logs_by_history_id( 999999 );
$this->assertIsArray( $logs );
$this->assertEmpty( $logs );
}

// ------------------------------------------------------------------
// AIPS_Schedule_Controller::ajax_get_schedule_history
// ------------------------------------------------------------------

/**
 * @test
 * Schedule with no history ID should return success with empty entries array.
 */
public function test_ajax_get_schedule_history_returns_empty_when_no_history() {
$schedule_obj = new stdClass();
$schedule_obj->id = 5;

$schedule_repository = $this->getMockBuilder( 'AIPS_Schedule_Repository' )
->disableOriginalConstructor()
->onlyMethods( array( 'get_by_id' ) )
->getMock();

$schedule_repository->method( 'get_by_id' )
->with( 5 )
->willReturn( $schedule_obj );

$this->set_private_property( $this->controller, 'schedule_repository', $schedule_repository );

$_POST['schedule_id'] = 5;
$_POST['nonce']       = wp_create_nonce( 'aips_ajax_nonce' );
$_REQUEST['nonce']    = $_POST['nonce'];

$response = $this->capture_ajax_response( array( $this->controller, 'ajax_get_schedule_history' ) );

$this->assertTrue( $response['success'] );
$this->assertIsArray( $response['data']['entries'] );
}

/**
 * @test
 * Should return error when schedule_id is 0 or missing.
 */
public function test_ajax_get_schedule_history_requires_valid_schedule_id() {
$_POST['schedule_id'] = 0;
$_POST['nonce']       = wp_create_nonce( 'aips_ajax_nonce' );
$_REQUEST['nonce']    = $_POST['nonce'];

$response = $this->capture_ajax_response( array( $this->controller, 'ajax_get_schedule_history' ) );

$this->assertFalse( $response['success'] );
$this->assertStringContainsString( 'Invalid schedule ID', $response['data']['message'] );
}

/**
 * @test
 * Non-admin user should be denied.
 */
public function test_ajax_get_schedule_history_permission_denied() {
wp_set_current_user( 0 );

$_POST['schedule_id'] = 1;
$_POST['nonce']       = wp_create_nonce( 'aips_ajax_nonce' );
$_REQUEST['nonce']    = $_POST['nonce'];

$response = $this->capture_ajax_response( array( $this->controller, 'ajax_get_schedule_history' ) );

$this->assertFalse( $response['success'] );
$this->assertStringContainsString( 'Permission denied', $response['data']['message'] );
}

/**
 * @test
 * Bulk toggling schedules records schedule_enabled / schedule_disabled lifecycle events.
 */
public function test_ajax_bulk_toggle_schedules_records_lifecycle_events() {
$schedule_obj        = new stdClass();
$schedule_obj->id    = 77;
$schedule_obj->title = 'Bulk Toggled Schedule';

$schedule_repository = $this->getMockBuilder( 'AIPS_Schedule_Repository' )
->disableOriginalConstructor()
->onlyMethods( array( 'set_active_bulk', 'get_by_id' ) )
->getMock();

$schedule_repository->expects( $this->once() )
->method( 'set_active_bulk' )
->with( array( 77 ), 0 )
->willReturn( 1 );

$schedule_repository->method( 'get_by_id' )
->with( 77 )
->willReturn( $schedule_obj );

$this->set_private_property( $this->controller, 'schedule_repository', $schedule_repository );

$_POST['ids']       = array( 77 );
$_POST['is_active'] = 0;
$_POST['nonce']     = wp_create_nonce( 'aips_ajax_nonce' );
$_REQUEST['nonce']  = $_POST['nonce'];

$response = $this->capture_ajax_response( array( $this->controller, 'ajax_bulk_toggle_schedules' ) );

$this->assertTrue( $response['success'] );

$logs = $this->history_repo->get_schedule_history_logs(
77,
array( AIPS_History_Type::ACTIVITY, AIPS_History_Type::ERROR )
);
$this->assertNotEmpty( $logs );
$this->assertSame( AIPS_History_Event_Type::SCHEDULE_DISABLED, $logs[0]->event_type );
}
}
