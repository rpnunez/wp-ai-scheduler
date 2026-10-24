<?php
/**
 * Tests for AIPS_Import_Export_Controller.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Import_Export_Controller extends WP_UnitTestCase {

	private $admin_id;

	public function setUp(): void {
		parent::setUp();
		$this->admin_id = self::factory()->user->create(array('role' => 'administrator'));
		wp_set_current_user($this->admin_id);
	}

	public function tearDown(): void {
		delete_option('aips_installed_presets');
		delete_option('aips_preset_entities_how_to_guides');
		delete_option('aips_preset_entities_news_updates');
		wp_set_current_user(0);
		parent::tearDown();
	}

	public function test_get_presets_status() {
		$controller = new AIPS_Import_Export_Controller();

		$_REQUEST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['nonce']    = $_REQUEST['nonce'];

		// Capture JSON response
		try {
			ob_start();
			$controller->ajax_get_presets_status();
		} catch (WPAjaxDieContinueException $e) {
			// Expected WP exit
		} catch (WPAjaxDieStopException $e) {
			// Expected WP exit
		}
		$output = ob_get_clean();
		$json = json_decode($output, true);

		$this->assertTrue($json['success']);
		$this->assertArrayHasKey('presets', $json['data']);
		$this->assertCount(4, $json['data']['presets']);

		$first = $json['data']['presets'][0];
		$this->assertArrayHasKey('id', $first);
		$this->assertArrayHasKey('name', $first);
		$this->assertArrayHasKey('is_installed', $first);
		$this->assertArrayHasKey('post_count', $first);
	}

	public function test_install_and_remove_preset_via_ajax() {
		$controller = new AIPS_Import_Export_Controller();

		$_REQUEST['nonce']  = wp_create_nonce('aips_ajax_nonce');
		$_POST['nonce']     = $_REQUEST['nonce'];
		$_POST['preset_id'] = 'how_to_guides';

		// 1. Install
		try {
			ob_start();
			$controller->ajax_install_preset();
		} catch (WPAjaxDieContinueException $e) {
			// Expected WP exit
		} catch (WPAjaxDieStopException $e) {
			// Expected WP exit
		}
		$output = ob_get_clean();
		$json = json_decode($output, true);

		$this->assertTrue($json['success']);
		$this->assertNotEmpty($json['data']['entities']['template_ids']);

		$installer = new AIPS_Preset_Installer();
		$this->assertTrue($installer->is_installed('how_to_guides'));

		// 2. Remove
		$_REQUEST['nonce']  = wp_create_nonce('aips_ajax_nonce');
		$_POST['nonce']     = $_REQUEST['nonce'];
		$_POST['preset_id'] = 'how_to_guides';
		$_POST['force']     = '0';

		try {
			ob_start();
			$controller->ajax_remove_preset();
		} catch (WPAjaxDieContinueException $e) {
			// Expected WP exit
		} catch (WPAjaxDieStopException $e) {
			// Expected WP exit
		}
		$output = ob_get_clean();
		$json = json_decode($output, true);

		$this->assertTrue($json['success']);
		$this->assertFalse($installer->is_installed('how_to_guides'));
	}

	public function test_export_data_via_ajax() {
		$controller = new AIPS_Import_Export_Controller();

		$_REQUEST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['nonce']    = $_REQUEST['nonce'];
		$_POST['types']    = array('templates', 'voices');

		try {
			ob_start();
			$controller->ajax_export_data();
		} catch (WPAjaxDieContinueException $e) {
			// Expected WP exit
		} catch (WPAjaxDieStopException $e) {
			// Expected WP exit
		}
		$output = ob_get_clean();
		$json = json_decode($output, true);

		$this->assertTrue($json['success']);
		$this->assertNotEmpty($json['data']['json']);
		$this->assertNotEmpty($json['data']['filename']);
	}

	public function test_execute_import_via_ajax() {
		$controller = new AIPS_Import_Export_Controller();

		// Create a mock transient payload
		$import_key = 'aips_import_' . $this->admin_id . '_test123';
		$payload = array(
			'voices' => array(
				array(
					'id'                   => 999,
					'name'                 => 'Ajax Import Test Voice',
					'title_prompt'         => 'Test',
					'content_instructions' => 'Test',
					'is_active'            => 1,
				),
			),
		);
		set_transient($import_key, $payload, HOUR_IN_SECONDS);

		$_REQUEST['nonce']        = wp_create_nonce('aips_ajax_nonce');
		$_POST['nonce']           = $_REQUEST['nonce'];
		$_POST['import_key']      = $import_key;
		$_POST['conflict_policy'] = 'copy';
		$_POST['types']           = array('voices');

		try {
			ob_start();
			$controller->ajax_execute_import();
		} catch (WPAjaxDieContinueException $e) {
			// Expected WP exit
		} catch (WPAjaxDieStopException $e) {
			// Expected WP exit
		}
		$output = ob_get_clean();
		$json = json_decode($output, true);

		$this->assertTrue($json['success']);
		$this->assertNotEmpty($json['data']['report']['imported']);
	}
}
