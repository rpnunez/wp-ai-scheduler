<?php
/**
 * Unit Tests for AIPS_Site_Health_Integration
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Site_Health_Integration extends WP_UnitTestCase {

	/**
	 * Test registering site status tests in site_status_tests filter.
	 */
	public function test_register_site_status_tests() {
		$tests = AIPS_Site_Health_Integration::register_site_status_tests(array());

		$this->assertArrayHasKey('direct', $tests);
		$this->assertArrayHasKey('async', $tests);

		$this->assertArrayHasKey('aips_ai_provider', $tests['direct']);
		$this->assertArrayHasKey('aips_cron_health', $tests['direct']);
		$this->assertArrayHasKey('aips_database_integrity', $tests['direct']);
		$this->assertArrayHasKey('aips_token_budget', $tests['direct']);
		$this->assertArrayHasKey('aips_ai_connectivity', $tests['async']);
	}

	/**
	 * Test test_ai_provider returns valid site health test structure.
	 */
	public function test_test_ai_provider() {
		$result = AIPS_Site_Health_Integration::test_ai_provider();

		$this->assertArrayHasKey('label', $result);
		$this->assertArrayHasKey('status', $result);
		$this->assertArrayHasKey('badge', $result);
		$this->assertArrayHasKey('description', $result);
		$this->assertArrayHasKey('test', $result);
		$this->assertContains($result['status'], array('good', 'recommended', 'critical'));
		$this->assertSame('aips_ai_provider', $result['test']);
	}

	/**
	 * Test test_cron_health returns valid site health test structure.
	 */
	public function test_test_cron_health() {
		$result = AIPS_Site_Health_Integration::test_cron_health();

		$this->assertArrayHasKey('label', $result);
		$this->assertArrayHasKey('status', $result);
		$this->assertArrayHasKey('badge', $result);
		$this->assertArrayHasKey('description', $result);
		$this->assertContains($result['status'], array('good', 'recommended', 'critical'));
		$this->assertSame('aips_cron_health', $result['test']);
	}

	/**
	 * Test test_database_integrity returns valid site health test structure.
	 */
	public function test_test_database_integrity() {
		$result = AIPS_Site_Health_Integration::test_database_integrity();

		$this->assertArrayHasKey('label', $result);
		$this->assertArrayHasKey('status', $result);
		$this->assertArrayHasKey('badge', $result);
		$this->assertArrayHasKey('description', $result);
		$this->assertContains($result['status'], array('good', 'recommended', 'critical'));
		$this->assertSame('aips_database_integrity', $result['test']);
	}

	/**
	 * Test test_token_budget returns valid site health test structure.
	 */
	public function test_test_token_budget() {
		$result = AIPS_Site_Health_Integration::test_token_budget();

		$this->assertArrayHasKey('label', $result);
		$this->assertArrayHasKey('status', $result);
		$this->assertArrayHasKey('badge', $result);
		$this->assertArrayHasKey('description', $result);
		$this->assertContains($result['status'], array('good', 'recommended', 'critical'));
		$this->assertSame('aips_token_budget', $result['test']);
	}

	/**
	 * Test test_ai_connectivity_direct returns valid test structure.
	 */
	public function test_test_ai_connectivity_direct() {
		$result = AIPS_Site_Health_Integration::test_ai_connectivity_direct();

		$this->assertArrayHasKey('label', $result);
		$this->assertArrayHasKey('status', $result);
		$this->assertArrayHasKey('badge', $result);
		$this->assertArrayHasKey('description', $result);
		$this->assertContains($result['status'], array('good', 'recommended', 'critical'));
		$this->assertSame('aips_ai_connectivity', $result['test']);
	}

	/**
	 * Test adding debug information for Tools > Site Health > Info.
	 */
	public function test_add_debug_information() {
		$debug = AIPS_Site_Health_Integration::add_debug_information(array());

		$this->assertArrayHasKey('ai-post-scheduler', $debug);
		$this->assertArrayHasKey('label', $debug['ai-post-scheduler']);
		$this->assertArrayHasKey('fields', $debug['ai-post-scheduler']);

		$fields = $debug['ai-post-scheduler']['fields'];
		$this->assertArrayHasKey('version', $fields);
		$this->assertArrayHasKey('next_cron_event', $fields);
	}
}
