<?php
/**
 * AJAX contract tests for pending-review endpoints on AIPS_Post_Review.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Post_Review_Controller extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	private $admin_user_id;

	/**
	 * @var int
	 */
	private $subscriber_user_id;

	/**
	 * @var AIPS_Post_Review
	 */
	private $controller;

	public function setUp(): void {
		parent::setUp();

		$this->admin_user_id      = $this->factory->user->create(array('role' => 'administrator'));
		$this->subscriber_user_id = $this->factory->user->create(array('role' => 'subscriber'));

		wp_set_current_user($this->admin_user_id);

		$_POST    = array();
		$_GET     = array();
		$_REQUEST = array();

		$this->controller = new AIPS_Post_Review();
	}

	public function tearDown(): void {
		$_POST    = array();
		$_GET     = array();
		$_REQUEST = array();

		AIPS_Container::get_instance()->clear();
		AI_Post_Scheduler::get_instance()->register_container_bindings();

		parent::tearDown();
	}

	private function capture_ajax(callable $callable): array {
		ob_start();

		try {
			$callable();
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		} catch (WPAjaxDieStopException $e) {
			// Expected.
		}

		$output  = ob_get_clean();
		$decoded = json_decode($output, true);

		return is_array($decoded) ? $decoded : array('raw' => $output);
	}

	public function test_pending_review_endpoints_fail_on_invalid_nonce() {
		$_POST    = array('nonce' => 'invalid_nonce_value');
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($this->controller, 'ajax_get_draft_posts'));
		$this->assertFalse($response['success']);
		$this->assertSame('Invalid nonce.', $response['data']['message']);

		$response = $this->capture_ajax(array($this->controller, 'ajax_publish_post'));
		$this->assertFalse($response['success']);
		$this->assertSame('Invalid nonce.', $response['data']['message']);

		$response = $this->capture_ajax(array($this->controller, 'ajax_bulk_publish_posts'));
		$this->assertFalse($response['success']);
		$this->assertSame('Invalid nonce.', $response['data']['message']);
	}

	public function test_pending_review_endpoints_fail_on_unauthorized_user() {
		wp_set_current_user($this->subscriber_user_id);

		$_POST    = array('nonce' => wp_create_nonce('aips_ajax_nonce'));
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($this->controller, 'ajax_get_draft_posts'));
		$this->assertFalse($response['success']);
		$this->assertSame('permission_denied', $response['data']['code']);

		$response = $this->capture_ajax(array($this->controller, 'ajax_publish_post'));
		$this->assertFalse($response['success']);
		$this->assertSame('permission_denied', $response['data']['code']);
	}

	public function test_publish_post_rejects_missing_post_id() {
		$_POST    = array(
			'nonce'   => wp_create_nonce('aips_ajax_nonce'),
			'post_id' => 0,
		);
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($this->controller, 'ajax_publish_post'));
		$this->assertFalse($response['success']);
		$this->assertSame('Invalid post ID.', $response['data']['message']);
	}

	public function test_get_draft_posts_returns_list_shape() {
		$_POST    = array(
			'nonce'    => wp_create_nonce('aips_ajax_nonce'),
			'page'     => 1,
			'per_page' => 10,
		);
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($this->controller, 'ajax_get_draft_posts'));
		$this->assertTrue($response['success']);
		$this->assertIsArray($response['data']);
	}

	public function test_registry_maps_pending_review_actions() {
		$this->assertSame('AIPS_Post_Review', AIPS_Ajax_Registry::get_controller_for('aips_get_draft_posts'));
		$this->assertSame('AIPS_Post_Review', AIPS_Ajax_Registry::get_controller_for('aips_publish_post'));
		$this->assertSame('AIPS_Post_Review', AIPS_Ajax_Registry::get_controller_for('aips_bulk_publish_posts'));
		$this->assertSame('AIPS_Post_Review', AIPS_Ajax_Registry::get_controller_for('aips_bulk_delete_draft_posts'));
		$this->assertSame('AIPS_AI_Edit_Controller', AIPS_Ajax_Registry::get_controller_for('aips_save_post_components'));
	}
}
