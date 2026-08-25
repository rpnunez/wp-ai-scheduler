<?php
/**
 * Tests for AIPS_Integration_Registry
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Integration_Registry extends WP_UnitTestCase {

	public function tearDown(): void {
		remove_all_filters('aips_integrations_registry');
		parent::tearDown();
	}

	public function test_get_registered_includes_core_acf_adapter() {
		$registered = AIPS_Integration_Registry::get_registered();
		$this->assertArrayHasKey('acf', $registered);
		$this->assertSame('AIPS_Integration_ACF', $registered['acf']);
	}

	public function test_has_returns_true_for_registered_id() {
		$this->assertTrue(AIPS_Integration_Registry::has('acf'));
	}

	public function test_has_returns_false_for_unknown_id() {
		$this->assertFalse(AIPS_Integration_Registry::has('does_not_exist'));
	}

	public function test_get_returns_null_for_unknown_id() {
		$this->assertNull(AIPS_Integration_Registry::get('does_not_exist'));
	}

	public function test_get_returns_adapter_instance() {
		$adapter = AIPS_Integration_Registry::get('acf');
		$this->assertInstanceOf(AIPS_Integration_Interface::class, $adapter);
		$this->assertSame('acf', $adapter->get_id());
	}

	public function test_third_party_plugin_can_register_via_filter() {
		add_filter('aips_integrations_registry', function ($map) {
			$map['stub'] = 'AIPS_Test_Stub_Integration';
			return $map;
		});

		$this->assertTrue(AIPS_Integration_Registry::has('stub'));
		$adapter = AIPS_Integration_Registry::get('stub');
		$this->assertInstanceOf('AIPS_Test_Stub_Integration', $adapter);
	}

	public function test_get_available_excludes_unavailable_adapters() {
		// ACF is not installed in the test environment, so it should not
		// appear in the available set even though it's registered.
		$available = AIPS_Integration_Registry::get_available();
		$this->assertArrayNotHasKey('acf', $available);
	}

	public function test_get_available_includes_available_third_party_adapter() {
		add_filter('aips_integrations_registry', function ($map) {
			$map['stub'] = 'AIPS_Test_Stub_Integration';
			return $map;
		});

		$available = AIPS_Integration_Registry::get_available();
		$this->assertArrayHasKey('stub', $available);
	}

	public function test_get_registered_includes_core_native_meta_adapter() {
		$registered = AIPS_Integration_Registry::get_registered();
		$this->assertArrayHasKey('native_meta', $registered);
		$this->assertSame('AIPS_Integration_Native_Meta', $registered['native_meta']);
	}

	public function test_native_meta_is_always_available() {
		$available = AIPS_Integration_Registry::get_available();
		$this->assertArrayHasKey('native_meta', $available);
	}
}

if (!class_exists('AIPS_Test_Stub_Integration')) {
	/**
	 * Minimal always-available adapter used to exercise the third-party
	 * registration path (the 'aips_integrations_registry' filter) without
	 * depending on ACF being installed in the test environment.
	 */
	class AIPS_Test_Stub_Integration implements AIPS_Integration_Interface {
		public function get_id() {
			return 'stub';
		}
		public function get_label() {
			return 'Stub Integration';
		}
		public function is_available() {
			return true;
		}
		public function get_field_groups($post_type = null) {
			return array();
		}
		public function get_fields($group_id, $args = array()) {
			return array();
		}
		public function get_supported_field_types() {
			return array();
		}
		public function write_field_value($post_id, $field_key, $value) {
			return true;
		}
		public function supports_custom_field_keys() {
			return false;
		}
		public function validate_field_key($field_key) {
			return true;
		}
	}
}
