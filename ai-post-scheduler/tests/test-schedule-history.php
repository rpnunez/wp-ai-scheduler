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
->onlyMethods( array( 'run_schedule_now', 'save_schedule', 'delete_schedule', 'toggle_active' ) )
->getMock();

$this->controller = new AIPS_Schedule_Controller( $mock_scheduler );
}

public function tearDown(): void {
unset( $_POST );
parent::tearDown();
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
// Mock get_row to return a schedule without schedule_history_id
global $wpdb;
$schedule_obj = new stdClass();
$schedule_obj->id = 5;
// no schedule_history_id property set

$_POST['schedule_id'] = 5;
$_POST['nonce']       = wp_create_nonce( 'aips_ajax_nonce' );
$_REQUEST['nonce']    = $_POST['nonce'];

try {
$this->controller->ajax_get_schedule_history();
} catch ( WPAjaxDieContinueException $e ) {
// expected
}

$output   = $this->getActualOutput();
$response = json_decode( $output, true );

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

try {
$this->controller->ajax_get_schedule_history();
} catch ( WPAjaxDieContinueException $e ) {
// expected
}

$output   = $this->getActualOutput();
$response = json_decode( $output, true );

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

try {
$this->controller->ajax_get_schedule_history();
} catch ( WPAjaxDieContinueException $e ) {
// expected
}

$output   = $this->getActualOutput();
$response = json_decode( $output, true );

$this->assertFalse( $response['success'] );
$this->assertStringContainsString( 'Permission denied', $response['data']['message'] );
}
}
