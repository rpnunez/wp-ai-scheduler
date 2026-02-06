<?php
/**
 * Tests for Dashboard Controller
 *
 * @package AI_Post_Scheduler
 */

class Test_Dashboard_Controller extends WP_UnitTestCase {

	private $controller;

	public function setUp(): void {
		parent::setUp();
		$this->controller = new AIPS_Dashboard_Controller();
	}

	/**
	 * Test that the controller can be instantiated
	 */
	public function test_controller_instantiation() {
		$this->assertInstanceOf('AIPS_Dashboard_Controller', $this->controller);
	}

	/**
	 * Test render_page method
	 *
	 * Since we are mocking everything, we just check if it runs without errors.
	 * We can't easily check output buffering in this setup without output buffering enabled in PHPUnit.
	 */
	public function test_render_page() {
		ob_start();
		$this->controller->render_page();
		$output = ob_get_clean();

		// Basic check to see if template was included and produced output
		// Note: The mock $wpdb returns empty results, so the dashboard will show "No scheduled posts yet." etc.
		$this->assertNotEmpty($output);
		$this->assertStringContainsString('AI Post Scheduler', $output);
	}

	/**
	 * Test that render_page includes all statistics sections
	 */
	public function test_render_page_includes_statistics() {
		ob_start();
		$this->controller->render_page();
		$output = ob_get_clean();

		// Check for all four statistics sections as they appear in the template
		$this->assertStringContainsString('Posts Generated', $output);
		$this->assertStringContainsString('Active Schedules', $output);
		$this->assertStringContainsString('Active Templates', $output);
		$this->assertStringContainsString('Failed Generations', $output);
	}

	/**
	 * Test that render_page includes Recent Activity section
	 */
	public function test_render_page_includes_recent_activity() {
		ob_start();
		$this->controller->render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString('Recent Activity', $output);
	}

	/**
	 * Test that render_page includes Upcoming Scheduled Posts section
	 */
	public function test_render_page_includes_upcoming_scheduled_posts() {
		ob_start();
		$this->controller->render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString('Upcoming Scheduled Posts', $output);
	}

	/**
	 * Test that render_page includes Quick Actions section
	 */
	public function test_render_page_includes_quick_actions() {
		ob_start();
		$this->controller->render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString('Quick Actions', $output);
		$this->assertStringContainsString('Create Template', $output);
		$this->assertStringContainsString('Add Schedule', $output);
		$this->assertStringContainsString('Settings', $output);
	}

	/**
	 * Test that render_page shows empty state messages when no data
	 */
	public function test_render_page_shows_empty_state() {
		ob_start();
		$this->controller->render_page();
		$output = ob_get_clean();

		// With mock data (empty results), should show empty state messages
		$this->assertStringContainsString('No scheduled posts yet.', $output);
		$this->assertStringContainsString('No posts generated yet.', $output);
	}

	/**
	 * Test that controller uses repositories for data access
	 */
	public function test_controller_uses_repositories() {
		// This test verifies that the controller instantiates repository classes
		// We can't easily mock in this setup, but we can verify no errors occur
		ob_start();
		$this->controller->render_page();
		$output = ob_get_clean();

		// If repositories weren't instantiated correctly, we'd get fatal errors
		$this->assertNotEmpty($output);
	}
}
