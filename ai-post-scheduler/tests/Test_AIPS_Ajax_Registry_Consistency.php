<?php
/**
 * Consistency tests for AIPS_Ajax_Registry and AIPS_Ajax_Guard.
 *
 * @package AI_Post_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Test_AIPS_Ajax_Registry_Consistency extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	private $admin_id;

	/**
	 * @var int
	 */
	private $subscriber_id;

	public function setUp(): void {
		parent::setUp();
		$this->admin_id = $this->factory->user->create(array('role' => 'administrator'));
		$this->subscriber_id = $this->factory->user->create(array('role' => 'subscriber'));
	}

	public function tearDown(): void {
		$_POST = array();
		$_REQUEST = array();
		parent::tearDown();
	}

	/**
	 * Every wp_ajax_aips_* hook in includes/ must be in AIPS_Ajax_Registry.
	 */
	public function test_all_php_ajax_hooks_exist_in_registry() {
		$includes_dir = AIPS_PLUGIN_DIR . 'includes/';
		$missing = array();

		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($includes_dir));
		foreach ($files as $file) {
			if (!$file->isFile() || $file->getExtension() !== 'php') {
				continue;
			}
			$content = file_get_contents($file->getPathname());
			if (preg_match_all("/add_action\s*\(\s*['\"]wp_ajax_(aips_[a-zA-Z0-9_]+)['\"]/", $content, $matches)) {
				foreach ($matches[1] as $action) {
					if (!AIPS_Ajax_Registry::has_action($action)) {
						$missing[] = $action . ' in ' . $file->getFilename();
					}
				}
			}
		}

		$this->assertEmpty(
			$missing,
			'AJAX hooks registered in code but missing from AIPS_Ajax_Registry: ' . implode(', ', array_unique($missing))
		);
	}

	/**
	 * Every class mapped in AIPS_Ajax_Registry must exist in the runtime.
	 */
	public function test_all_registry_controllers_exist() {
		$map = AIPS_Ajax_Registry::get_map();
		$missing_classes = array();

		foreach ($map as $action => $class_name) {
			if (!class_exists($class_name)) {
				$missing_classes[$class_name][] = $action;
			}
		}

		$this->assertEmpty(
			$missing_classes,
			'Registry references non-existent controller classes: ' . json_encode($missing_classes)
		);
	}

	/**
	 * Test canonical registration helper attaches actions to controller instance.
	 */
	public function test_canonical_registration_helper() {
		$mock_controller = new class {
			public function ajax_save_schedule() {}
			public function ajax_delete_schedule() {}
		};

		// Fake mapping for this test class
		$class_name = get_class($mock_controller);
		
		// Use Reflection to temporarily register mock actions in AIPS_Ajax_Registry
		$ref = new ReflectionClass('AIPS_Ajax_Registry');
		$prop = $ref->getProperty('map');
		$prop->setAccessible(true);
		$original_map = $prop->getValue();

		$test_map = $original_map;
		$test_map['aips_mock_save'] = $class_name;
		$test_map['aips_mock_delete'] = $class_name;
		$prop->setValue(null, $test_map);

		try {
			$registered = AIPS_Ajax_Registry::register_controller_actions(
				$mock_controller,
				array(
					'aips_mock_save' => 'ajax_save_schedule',
					'aips_mock_delete' => 'ajax_delete_schedule',
				)
			);

			$this->assertArrayHasKey('aips_mock_save', $registered);
			$this->assertArrayHasKey('aips_mock_delete', $registered);
			$this->assertTrue(has_action('wp_ajax_aips_mock_save') !== false);
			$this->assertTrue(has_action('wp_ajax_aips_mock_delete') !== false);
		} finally {
			$prop->setValue(null, $original_map);
		}
	}

	/**
	 * Test AIPS_Ajax_Guard permits authorized administrator with valid nonce.
	 */
	public function test_ajax_guard_allows_authorized_admin() {
		wp_set_current_user($this->admin_id);
		$_REQUEST['nonce'] = wp_create_nonce('aips_ajax_nonce');

		$result = AIPS_Ajax_Guard::verify();
		$this->assertTrue($result);
	}

	/**
	 * Test AIPS_Ajax_Guard rejects invalid nonce.
	 */
	public function test_ajax_guard_rejects_invalid_nonce() {
		wp_set_current_user($this->admin_id);
		$_REQUEST['nonce'] = 'invalid_nonce';

		$result = AIPS_Ajax_Guard::verify();
		$this->assertInstanceOf('WP_Error', $result);
		$this->assertSame('security_check_failed', $result->get_error_code());
	}

	/**
	 * Test AIPS_Ajax_Guard rejects unauthorized subscriber.
	 */
	public function test_ajax_guard_rejects_unauthorized_user() {
		wp_set_current_user($this->subscriber_id);
		$_REQUEST['nonce'] = wp_create_nonce('aips_ajax_nonce');

		$result = AIPS_Ajax_Guard::verify();
		$this->assertInstanceOf('WP_Error', $result);
		$this->assertSame('permission_denied', $result->get_error_code());
	}
}
