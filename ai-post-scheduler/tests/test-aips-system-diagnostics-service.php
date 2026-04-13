<?php

class Test_AIPS_System_Diagnostics_Service extends WP_UnitTestCase {

    private $service;

    public function setUp(): void {
        parent::setUp();
        $this->service = new AIPS_System_Diagnostics_Service();
    }

    public function test_get_system_info_returns_expected_keys() {
        $info = $this->service->get_system_info();

        $this->assertIsArray($info);
        $this->assertArrayHasKey('environment', $info);
        $this->assertArrayHasKey('plugin', $info);
        $this->assertArrayHasKey('database', $info);
        $this->assertArrayHasKey('filesystem', $info);
        $this->assertArrayHasKey('cron', $info);
        $this->assertArrayHasKey('scheduler health', $info);
        $this->assertArrayHasKey('queue health', $info);
        $this->assertArrayHasKey('generation metrics', $info);
        $this->assertArrayHasKey('resilience', $info);
        $this->assertArrayHasKey('notifications', $info);
        $this->assertArrayHasKey('logs', $info);
    }
}
