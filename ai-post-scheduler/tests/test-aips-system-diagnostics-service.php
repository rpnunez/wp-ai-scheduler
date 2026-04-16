<?php

class Test_AIPS_System_Diagnostics_Service extends WP_UnitTestCase {

	public function test_get_system_info_aggregates_from_providers() {
		$mock_provider = new class() implements AIPS_System_Diagnostic_Provider_Interface {
			public function get_diagnostics(): array {
				return array( 'environment' => 'mock_value' );
			}
		};

		$service = new AIPS_System_Diagnostics_Service( array( $mock_provider ) );
		$info    = $service->get_system_info();

		$this->assertIsArray( $info );
		$this->assertTrue( array_key_exists( 'environment', $info ) );
		$this->assertEquals( 'mock_value', $info['environment'] );
	}

	public function test_add_provider_appends_to_provider_list() {
		$first_provider = new class() implements AIPS_System_Diagnostic_Provider_Interface {
			public function get_diagnostics(): array {
				return array( 'first_key' => 'first_value' );
			}
		};

		$second_provider = new class() implements AIPS_System_Diagnostic_Provider_Interface {
			public function get_diagnostics(): array {
				return array( 'second_key' => 'second_value' );
			}
		};

		$service = new AIPS_System_Diagnostics_Service( array( $first_provider ) );
		$service->add_provider( $second_provider );
		$info = $service->get_system_info();

		$this->assertArrayHasKey( 'first_key', $info );
		$this->assertArrayHasKey( 'second_key', $info );
	}

	public function test_custom_provider_keys_are_not_silently_dropped() {
		$custom_provider = new class() implements AIPS_System_Diagnostic_Provider_Interface {
			public function get_diagnostics(): array {
				return array( 'my_custom_section' => array( 'status' => 'ok' ) );
			}
		};

		$service = new AIPS_System_Diagnostics_Service( array( $custom_provider ) );
		$info    = $service->get_system_info();

		$this->assertArrayHasKey( 'my_custom_section', $info );
	}

	public function test_expected_keys_are_ordered_before_custom_keys() {
		$environment_provider = new class() implements AIPS_System_Diagnostic_Provider_Interface {
			public function get_diagnostics(): array {
				return array( 'environment' => 'env_value' );
			}
		};

		$custom_provider = new class() implements AIPS_System_Diagnostic_Provider_Interface {
			public function get_diagnostics(): array {
				return array( 'my_custom_section' => 'custom_value' );
			}
		};

		$service = new AIPS_System_Diagnostics_Service( array( $custom_provider, $environment_provider ) );
		$info    = $service->get_system_info();
		$keys    = array_keys( $info );

		$env_pos    = array_search( 'environment', $keys );
		$custom_pos = array_search( 'my_custom_section', $keys );

		$this->assertNotFalse( $env_pos );
		$this->assertNotFalse( $custom_pos );
		$this->assertLessThan( $custom_pos, $env_pos, 'Expected keys should appear before custom keys' );
	}

	public function test_empty_providers_returns_empty_array() {
		$service = new AIPS_System_Diagnostics_Service( array() );
		$info    = $service->get_system_info();

		$this->assertIsArray( $info );
		$this->assertEmpty( $info );
	}
}
