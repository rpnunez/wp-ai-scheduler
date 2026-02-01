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
}
