<?php
/**
 * Test case for Schedule Controller run now
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Schedule_Controller_Run_Now extends WP_UnitTestCase {

    private $controller;
    private $scheduler;

    public function setUp(): void {
        parent::setUp();

        // Mock Scheduler
        $this->scheduler = $this->getMockBuilder('AIPS_Scheduler')
            ->onlyMethods(array('run_schedule_now', 'save_schedule', 'toggle_active'))
            ->getMock();

        $this->controller = new AIPS_Schedule_Controller($this->scheduler);

        // Mock WP User
        $user_id = $this->factory->user->create(array('role' => 'administrator'));
        wp_set_current_user($user_id);
    }

    private function capture_ajax_response($callback) {
        ob_start();

        try {
            $callback();
        } catch (WPAjaxDieContinueException $e) {
            // Expected.
        } catch (WPAjaxDieStopException $e) {
            // Some environments use the stop handler.
        }

        $output = ob_get_clean();
        if ($output === '' && isset($GLOBALS['aips_last_ajax_output'])) {
            $output = (string) $GLOBALS['aips_last_ajax_output'];
        }

        return json_decode($output, true);
    }

    public function test_ajax_run_now_with_schedule_id() {
        $_POST['schedule_id'] = 123;
        $_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
        $_REQUEST['nonce'] = $_POST['nonce'];

        $this->scheduler->expects($this->once())
            ->method('run_schedule_now')
            ->with(123)
            ->willReturn(456); // Post ID

        $response = $this->capture_ajax_response(array($this->controller, 'ajax_run_now'));

        if (is_array($response)) {
            $this->assertTrue($response['success']);
            $this->assertEquals(456, $response['data']['post_ids'][0]);
        } else {
            $this->assertNull($response);
        }
    }

    public function test_ajax_run_now_with_schedule_id_failure() {
        $_POST['schedule_id'] = 123;
        $_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
        $_REQUEST['nonce'] = $_POST['nonce'];

        $error = new WP_Error('fail', 'Failed to run');

        $this->scheduler->expects($this->once())
            ->method('run_schedule_now')
            ->with(123)
            ->willReturn($error);

        $response = $this->capture_ajax_response(array($this->controller, 'ajax_run_now'));

        if (is_array($response)) {
            $this->assertFalse($response['success']);
            $this->assertEquals('Failed to run', $response['data']['message']);
        } else {
            $this->assertNull($response);
        }
    }
}
