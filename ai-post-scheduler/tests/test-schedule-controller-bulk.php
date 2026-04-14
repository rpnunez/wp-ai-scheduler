<?php
/**
 * Tests for AIPS_Schedule_Controller bulk AJAX endpoints.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Schedule_Controller_Bulk extends WP_UnitTestCase {

	/** @var AIPS_Schedule_Controller */
	private $controller;

	/** @var int */
	private $admin_user_id;

	/** @var int */
	private $subscriber_user_id;

	/** @var int */
	private $template_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin_user_id      = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$this->controller = new AIPS_Schedule_Controller();

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'aips_templates',
			array(
				'name'            => 'Schedule Bulk Test Template',
				'prompt_template' => 'Write about {{topic}}',
				'is_active'       => 1,
				'post_quantity'   => 1,
			)
		);
		$this->template_id = (int) $wpdb->insert_id;
	}

	public function tearDown(): void {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}aips_schedule WHERE template_id = %d", $this->template_id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}aips_templates WHERE id = %d", $this->template_id ) );

		$_POST    = array();
		$_REQUEST = array();

		parent::tearDown();
	}

	/**
	 * @param callable $callable Endpoint callback.
	 * @return array Decoded JSON response.
	 */
	private function call_ajax( callable $callable ) {
		$_REQUEST = array_merge( $_REQUEST, $_POST );
		ob_start();
		try {
			$callable();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected for wp_send_json.
		}

		return json_decode( ob_get_clean(), true );
	}

	/**
	 * @param int $is_active Active state.
	 * @return int Created schedule ID.
	 */
	private function create_template_schedule( $is_active = 1 ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'aips_schedule',
			array(
				'template_id' => $this->template_id,
				'frequency'   => 'daily',
				'next_run'    => gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ),
				'is_active'   => (int) $is_active,
				'topic'       => 'Schedule bulk topic',
			)
		);

		return (int) $wpdb->insert_id;
	}

	public function test_schedule_bulk_delete_requires_items() {
		wp_set_current_user( $this->admin_user_id );

		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['items'] = array();

		$response = $this->call_ajax( array( $this->controller, 'ajax_schedule_bulk_delete' ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'No items provided.', $response['data']['message'] );
	}

	public function test_schedule_bulk_delete_permission_denied() {
		wp_set_current_user( $this->subscriber_user_id );

		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['items'] = array(
			array(
				'id'   => 123,
				'type' => AIPS_Schedule_Service::TYPE_TEMPLATE,
			),
		);

		$response = $this->call_ajax( array( $this->controller, 'ajax_schedule_bulk_delete' ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Permission denied.', $response['data']['message'] );
	}

	public function test_schedule_bulk_delete_template_schedule_success() {
		wp_set_current_user( $this->admin_user_id );
		$schedule_id = $this->create_template_schedule( 1 );

		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['items'] = array(
			array(
				'id'   => $schedule_id,
				'type' => AIPS_Schedule_Service::TYPE_TEMPLATE,
			),
		);

		$response = $this->call_ajax( array( $this->controller, 'ajax_schedule_bulk_delete' ) );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 1, (int) $response['data']['deleted'] );

		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}aips_schedule WHERE id = %d", $schedule_id ) );
		$this->assertNull( $row );
	}

	public function test_schedule_bulk_toggle_requires_items() {
		wp_set_current_user( $this->admin_user_id );

		$_POST['nonce']     = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['items']     = array();
		$_POST['is_active'] = 0;

		$response = $this->call_ajax( array( $this->controller, 'ajax_schedule_bulk_toggle' ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'No items provided.', $response['data']['message'] );
	}

	public function test_schedule_bulk_toggle_template_schedule_success() {
		wp_set_current_user( $this->admin_user_id );
		$schedule_id = $this->create_template_schedule( 1 );

		$_POST['nonce']     = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['items']     = array(
			array(
				'id'   => $schedule_id,
				'type' => AIPS_Schedule_Service::TYPE_TEMPLATE,
			),
		);
		$_POST['is_active'] = 0;

		$response = $this->call_ajax( array( $this->controller, 'ajax_schedule_bulk_toggle' ) );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 1, (int) $response['data']['updated'] );

		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT is_active FROM {$wpdb->prefix}aips_schedule WHERE id = %d", $schedule_id ) );
		$this->assertNotNull( $row );
		$this->assertSame( 0, (int) $row->is_active );
	}

	public function test_schedule_bulk_run_now_rejects_too_many_items() {
		wp_set_current_user( $this->admin_user_id );

		$items = array();
		for ( $i = 1; $i <= 6; $i++ ) {
			$items[] = array(
				'id'   => $i,
				'type' => AIPS_Schedule_Service::TYPE_TEMPLATE,
			);
		}

		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['items'] = $items;

		$response = $this->call_ajax( array( $this->controller, 'ajax_schedule_bulk_run_now' ) );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Too many schedules selected', $response['data']['message'] );
	}
}
