<?php

class Test_AIPS_System_Diagnostics_Service extends WP_UnitTestCase {

    public function test_get_system_info_aggregates_from_providers() {
        $service = new AIPS_System_Diagnostics_Service();

        $mock_provider = new class() implements AIPS_System_Diagnostic_Provider_Interface {
            public function get_diagnostics(): array {
                return ['environment' => 'mock_value'];
            }
        };

        $reflection = new ReflectionClass($service);
        $prop = $reflection->getProperty('providers');
        $prop->setAccessible(true);
        $prop->setValue($service, []);

        $service->add_provider($mock_provider);
        $info = $service->get_system_info();

        $this->assertIsArray($info);
        $this->assertTrue(array_key_exists('environment', $info));
        $this->assertEquals('mock_value', $info['environment']);
    }
}