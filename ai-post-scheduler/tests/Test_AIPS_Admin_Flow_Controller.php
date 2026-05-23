<?php
/**
 * Tests for the campaign wizard admin flow controller.
 *
 * @package AI_Post_Scheduler
 */

require_once dirname(__DIR__) . '/includes/class-aips-date-time.php';
require_once dirname(__DIR__) . '/includes/class-aips-admin-flow-controller.php';

if (!function_exists('get_post_type_object')) {
	function get_post_type_object($post_type) {
		if (empty($post_type)) {
			return null;
		}

		return (object) array(
			'name' => $post_type,
			'public' => true,
		);
	}
}

class Test_AIPS_Admin_Flow_Controller extends WP_UnitTestCase {

	/**
	 * Reset test options between runs.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$GLOBALS['aips_test_options'] = array();
	}

	/**
	 * Drafts should be isolated to the current admin's option key.
	 *
	 * @return void
	 */
	public function test_get_draft_reads_user_specific_option() {
		update_option('aips_campaign_wizard_draft_1', array('campaign_name' => 'Per-user draft'));
		update_option('aips_campaign_wizard_draft', array('campaign_name' => 'Legacy global draft'));

		$controller = $this->make_controller();
		$draft = $this->invoke_private_method($controller, 'get_draft');

		$this->assertSame('Per-user draft', $draft['campaign_name']);
	}

	/**
	 * Default start time should match the site's display timezone format.
	 *
	 * @return void
	 */
	public function test_normalise_payload_uses_site_local_default_start_time() {
		$controller = $this->make_controller();
		$payload = $this->invoke_private_method($controller, 'normalise_payload', array(array()));

		$this->assertSame(AIPS_DateTime::now()->toDisplay('Y-m-d\TH:i'), $payload['start_time']);
	}

	/**
	 * Helper for invoking private controller methods.
	 *
	 * @param object $object Object instance.
	 * @param string $method Method name.
	 * @param array  $args   Method arguments.
	 * @return mixed
	 */
	private function invoke_private_method($object, $method, $args = array()) {
		$reflection = new ReflectionMethod($object, $method);
		$reflection->setAccessible(true);

		return $reflection->invokeArgs($object, $args);
	}

	/**
	 * Build a controller instance without running its full constructor.
	 *
	 * @return AIPS_Admin_Flow_Controller
	 */
	private function make_controller() {
		$reflection = new ReflectionClass('AIPS_Admin_Flow_Controller');
		$controller = $reflection->newInstanceWithoutConstructor();

		$config_property = $reflection->getProperty('config');
		$config_property->setAccessible(true);
		$config_property->setValue($controller, new class() {
			public function get_option($option_name) {
				return 'aips_default_post_status' === $option_name ? 'draft' : 0;
			}
		});

		return $controller;
	}
}
