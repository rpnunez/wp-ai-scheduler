<?php

/**
 * Class Test_AIPS_History_Query_Service
 *
 * Tests for the history query methods now hosted on AIPS_History_Repository.
 * The AIPS_History_Query_Service class remains as a BC shim; both entry points
 * are exercised here.
 *
 * @package AI_Post_Scheduler
 */

require_once dirname(__DIR__) . '/includes/class-aips-history-query-service.php';

class Test_AIPS_History_Query_Service extends WP_UnitTestCase {

    private $shim;
    private $repository;

    public function setUp(): void {
        parent::setUp();
        $this->shim = new AIPS_History_Query_Service();
        $this->repository = AIPS_History_Repository::instance();
    }

    public function test_get_auxiliary_creation_methods_is_private_on_repository() {
        $reflection = new ReflectionClass(AIPS_History_Repository::class);
        $method = $reflection->getMethod('get_auxiliary_creation_methods');
        $this->assertTrue($method->isPrivate());

        $method->setAccessible(true);
        $methods = $method->invoke($this->repository);

        $this->assertIsArray($methods);
        $this->assertContains('schedule_lifecycle', $methods);
        $this->assertContains('template_lifecycle', $methods);
    }

    public function test_get_history_returns_expected_structure() {
        $result = $this->shim->get_history();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('pages', $result);
        $this->assertArrayHasKey('current_page', $result);
    }

    public function test_get_partial_generations_returns_expected_structure() {
        $result = $this->shim->get_partial_generations();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('pages', $result);
        $this->assertArrayHasKey('current_page', $result);
    }

    public function test_shim_delegates_to_repository() {
        $direct = $this->repository->get_history();
        $through_shim = $this->shim->get_history();
        $this->assertSame(array_keys($direct), array_keys($through_shim));
    }
}
