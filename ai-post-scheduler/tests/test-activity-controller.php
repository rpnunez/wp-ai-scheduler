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

    public function test_render_page() {
        ob_start();
        $this->controller->render_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('class="wrap aips-wrap"', $output);
        $this->assertStringContainsString('<h1>Activity</h1>', $output);
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
            $this->expectOutputRegex('/"success":true/');
            $this->expectOutputRegex('/"activities":\[\]/');
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
