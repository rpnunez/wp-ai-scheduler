<?php
/**
 * Tests for template save behavior.
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Test_Templates_Save_Stub {
	/**
	 * @var array|null
	 */
	public $saved_data = null;

	/**
	 * Capture save payload and return a fake template ID.
	 *
	 * @param array $data Template payload.
	 * @return int
	 */
	public function save($data) {
		$this->saved_data = $data;
		return 123;
	}
}

class Test_AIPS_Templates_Controller_Save extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	private $admin_user;

	/**
	 * @var AIPS_Test_Templates_Save_Stub
	 */
	private $templates_stub;

	/**
	 * @var AIPS_Templates_Controller
	 */
	private $controller;

	public function setUp(): void {
		parent::setUp();
		$this->admin_user = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($this->admin_user);

		$this->templates_stub = new AIPS_Test_Templates_Save_Stub();
		$this->controller = new AIPS_Templates_Controller($this->templates_stub);
	}

	public function tearDown(): void {
		$_POST = array();
		$_REQUEST = array();
		wp_set_current_user(0);
		parent::tearDown();
	}

	public function test_ajax_save_template_preserves_explicit_zero_for_generate_featured_image() {
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['name'] = 'Template without image';
		$_POST['prompt_template'] = 'Write about {{topic}}';
		$_POST['generate_featured_image'] = '0';
		$_REQUEST = $_POST;

		ob_start();
		try {
			$this->controller->ajax_save_template();
		} catch (WPAjaxDieStopException $e) {
			// Expected for wp_send_json_* in tests.
		} catch (WPAjaxDieContinueException $e) {
			// Some environments throw continue exceptions for AJAX responses.
		}
		$output = ob_get_clean();

		$response = json_decode($output, true);
		$this->assertTrue($response['success']);
		$this->assertNotNull($this->templates_stub->saved_data);
		$this->assertSame(0, $this->templates_stub->saved_data['generate_featured_image']);
	}

	public function test_ajax_save_template_sets_generate_featured_image_to_one_when_enabled() {
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['name'] = 'Template with image';
		$_POST['prompt_template'] = 'Write about {{topic}}';
		$_POST['generate_featured_image'] = '1';
		$_REQUEST = $_POST;

		ob_start();
		try {
			$this->controller->ajax_save_template();
		} catch (WPAjaxDieStopException $e) {
			// Expected for wp_send_json_* in tests.
		} catch (WPAjaxDieContinueException $e) {
			// Some environments throw continue exceptions for AJAX responses.
		}
		$output = ob_get_clean();

		$response = json_decode($output, true);
		$this->assertTrue($response['success']);
		$this->assertNotNull($this->templates_stub->saved_data);
		$this->assertSame(1, $this->templates_stub->saved_data['generate_featured_image']);
	}
}
