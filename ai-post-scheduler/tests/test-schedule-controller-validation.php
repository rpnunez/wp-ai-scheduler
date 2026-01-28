<?php
/**
 * Test Schedule Controller Validation
 *
 * @package AI_Post_Scheduler
 */

class Test_Schedule_Controller_Validation extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        // Set user as admin
        $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user(1);
    }

    public function test_run_now_validates_topic_length() {
        $_POST['action'] = 'aips_run_now';
        $_POST['template_id'] = 1;
        $_POST['topic'] = str_repeat('a', 300); // 300 chars
        $_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
        $_REQUEST['nonce'] = $_POST['nonce'];

        $controller = new AIPS_Schedule_Controller();

        try {
            $controller->ajax_run_now();
            $this->fail('Should have thrown WPAjaxDieContinueException');
        } catch (WPAjaxDieContinueException $e) {
            // Check output buffer for JSON
            // Since we mocked wp_send_json_error to echo and throw, we can inspect output if we capture it
            // But checking output in PHPUnit is tricky if we don't capture it.
            // bootstrap.php mock:
            // echo json_encode(array('success' => false, 'data' => $data));

            $this->expectOutputRegex('/"success":false/');
            $this->expectOutputRegex('/Topic is too long/');
        }
    }
}
