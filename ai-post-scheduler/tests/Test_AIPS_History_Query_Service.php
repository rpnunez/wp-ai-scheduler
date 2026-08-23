<?php

/**
 * Class Test_AIPS_History_Query_Service
 *
 * Tests for the AIPS_History_Query_Service class.
 *
 * @package AI_Post_Scheduler
 */

require_once dirname(__DIR__) . '/includes/class-aips-history-query-service.php';

class Test_AIPS_History_Query_Service extends WP_UnitTestCase {

    private $query_service;

    public function setUp(): void {
        parent::setUp();
        $this->query_service = new AIPS_History_Query_Service();
    }

    public function test_get_auxiliary_creation_methods_is_private() {
        $reflection = new ReflectionClass(AIPS_History_Query_Service::class);
        $method = $reflection->getMethod('get_auxiliary_creation_methods');
        $this->assertTrue($method->isPrivate());

        $method->setAccessible(true);
        $methods = $method->invoke($this->query_service);

        $this->assertIsArray($methods);
        $this->assertContains('schedule_lifecycle', $methods);
        $this->assertContains('template_lifecycle', $methods);
    }

    public function test_get_history_returns_expected_structure() {
        $result = $this->query_service->get_history();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('pages', $result);
        $this->assertArrayHasKey('current_page', $result);
    }

    public function test_get_partial_generations_returns_expected_structure() {
        $result = $this->query_service->get_partial_generations();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('pages', $result);
        $this->assertArrayHasKey('current_page', $result);
    }
}
