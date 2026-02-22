<?php
/**
 * Test Activity Controller
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Activity_Controller extends WP_UnitTestCase {

    private $controller;

    public function setUp(): void {
        parent::setUp();
        $this->controller = new AIPS_Activity_Controller();

        // Mock current user as admin
        $admin_id = $this->factory->user->create(array('role' => 'administrator'));
        wp_set_current_user($admin_id);
    }

    public function tearDown(): void {
        // Reset globals modified during tests
        $_POST    = array();
        $_REQUEST = array();

        // Reset current user
        wp_set_current_user(0);

        parent::tearDown();
    }

    public function test_render_page() {
        ob_start();
        $this->controller->render_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('class="wrap aips-wrap"', $output);
        $this->assertMatchesRegularExpression('/<h1[^>]*>\s*Activity\s*<\/h1>/', $output);
    }

    public function test_ajax_get_activity_unauthorized() {
        // Mock non-admin user
        $user_id = $this->factory->user->create(array('role' => 'subscriber'));
        wp_set_current_user($user_id);

        $_REQUEST['nonce'] = wp_create_nonce('aips_ajax_nonce');

        try {
            $this->controller->ajax_get_activity();
            $this->fail('Expected WPAjaxDieContinueException not thrown');
        } catch (WPAjaxDieContinueException $e) {
            $this->expectOutputRegex('/"success":false/');
            $this->expectOutputRegex('/Unauthorized access/');
        }
    }

    public function test_ajax_get_activity_success() {
        $_REQUEST['nonce'] = wp_create_nonce('aips_ajax_nonce');
        $_POST['filter'] = 'all';

        try {
            $this->controller->ajax_get_activity();
            $this->fail('Expected WPAjaxDieContinueException not thrown');
        } catch (WPAjaxDieContinueException $e) {
            $output = $this->getActualOutput();

            $this->assertNotEmpty($output, 'Expected JSON response output from ajax_get_activity().');

            $data = json_decode($output, true);
            $this->assertIsArray($data, 'Expected decoded JSON response to be an array.');

            $this->assertArrayHasKey('success', $data);
            $this->assertTrue($data['success'], 'Expected success flag to be true.');

            $this->assertArrayHasKey('data', $data);
            $this->assertArrayHasKey('activities', $data['data']);
            $this->assertIsArray($data['data']['activities'], 'Expected activities to be an array.');
        }
    }

    public function test_ajax_get_activity_detail_invalid_id() {
        $_REQUEST['nonce'] = wp_create_nonce('aips_ajax_nonce');
        $_POST['post_id'] = 0;

        try {
            $this->controller->ajax_get_activity_detail();
            $this->fail('Expected WPAjaxDieContinueException not thrown');
        } catch (WPAjaxDieContinueException $e) {
            $this->expectOutputRegex('/"success":false/');
            $this->expectOutputRegex('/Invalid post ID/');
        }
    }
}
