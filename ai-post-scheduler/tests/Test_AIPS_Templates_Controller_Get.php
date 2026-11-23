<?php
/**
 * Tests for fetching a template for the admin editor.
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Test_Templates_Get_Stub {
	public function get($id) {
		return (object) array(
			'id'       => (int) $id,
			'name'     => 'Inactive voice template',
			'voice_id' => 321,
		);
	}
}

class AIPS_Test_Voices_Get_Stub {
	public function get_by_id($id) {
		return (object) array(
			'id'        => (int) $id,
			'name'      => 'Archived Editorial Voice',
			'is_active' => 0,
		);
	}
}

class Test_AIPS_Templates_Controller_Get extends WP_Ajax_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		wp_set_current_user($this->factory->user->create(array('role' => 'administrator')));
	}

	public function tearDown(): void {
		$_POST = array();
		$_REQUEST = array();
		wp_set_current_user(0);
		parent::tearDown();
	}

	public function test_get_template_returns_label_for_inactive_referenced_voice() {
		$controller = new AIPS_Templates_Controller(new AIPS_Test_Templates_Get_Stub(), new AIPS_Test_Voices_Get_Stub());
		$_POST = array(
			'nonce'       => wp_create_nonce('aips_ajax_nonce'),
			'template_id' => '123',
		);
		$_REQUEST = $_POST;

		ob_start();
		try {
			$controller->ajax_get_template();
		} catch (WPAjaxDieStopException $exception) {
			// Expected for wp_send_json_* in tests.
		} catch (WPAjaxDieContinueException $exception) {
			// Some test environments use a continue exception.
		}
		$response = json_decode((string) ob_get_clean(), true);

		$this->assertTrue($response['success']);
		$this->assertSame('Archived Editorial Voice', $response['data']['template']['voice_name']);
	}
}
