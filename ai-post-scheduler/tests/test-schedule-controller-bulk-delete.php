<?php
/**
 * Test case for Schedule Controller Bulk Delete
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Schedule_Controller_Bulk_Delete extends WP_UnitTestCase {

    private $controller;
    private $scheduler;

    public function setUp(): void {
        parent::setUp();

        // Mock Scheduler
        $this->scheduler = $this->getMockBuilder('AIPS_Scheduler')
            ->onlyMethods(array('delete_schedule', 'run_schedule_now', 'save_schedule', 'toggle_active'))
            ->getMock();

        $this->controller = new AIPS_Schedule_Controller($this->scheduler);

        // Mock WP User
        $user_id = $this->factory->user->create(array('role' => 'administrator'));
        wp_set_current_user($user_id);
    }

    public function test_ajax_bulk_delete_schedules_success() {
        $_POST['ids'] = array(1, 2, 3);
        $_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
        $_REQUEST['nonce'] = $_POST['nonce'];

        $this->scheduler->expects($this->exactly(3))
            ->method('delete_schedule')
            ->willReturn(true);

        try {
            $this->controller->ajax_bulk_delete_schedules();
        } catch (WPAjaxDieContinueException $e) {
            // Expected
        }

        $output = $this->getActualOutput();
        $response = json_decode($output, true);

        $this->assertTrue($response['success']);
        $this->assertStringContainsString('3 schedule(s) deleted', $response['data']['message']);
    }

    public function test_ajax_bulk_delete_schedules_no_ids() {
        $_POST['ids'] = array();
        $_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
        $_REQUEST['nonce'] = $_POST['nonce'];

        try {
            $this->controller->ajax_bulk_delete_schedules();
        } catch (WPAjaxDieContinueException $e) {
            // Expected
        }

        $output = $this->getActualOutput();
        $response = json_decode($output, true);

        $this->assertFalse($response['success']);
        $this->assertEquals('No schedules selected.', $response['data']['message']);
    }

    public function test_ajax_bulk_delete_schedules_partial_success() {
        $_POST['ids'] = array(1, 2);
        $_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
        $_REQUEST['nonce'] = $_POST['nonce'];

        $this->scheduler->expects($this->exactly(2))
            ->method('delete_schedule')
            ->willReturnOnConsecutiveCalls(true, false);

        try {
            $this->controller->ajax_bulk_delete_schedules();
        } catch (WPAjaxDieContinueException $e) {
            // Expected
        }

        $output = $this->getActualOutput();
        $response = json_decode($output, true);

        $this->assertTrue($response['success']);
        $this->assertStringContainsString('1 schedule(s) deleted', $response['data']['message']);
    }

    public function test_ajax_bulk_delete_schedules_all_fail() {
        $_POST['ids'] = array(1);
        $_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
        $_REQUEST['nonce'] = $_POST['nonce'];

        $this->scheduler->expects($this->once())
            ->method('delete_schedule')
            ->willReturn(false);

        try {
            $this->controller->ajax_bulk_delete_schedules();
        } catch (WPAjaxDieContinueException $e) {
            // Expected
        }

        $output = $this->getActualOutput();
        $response = json_decode($output, true);

        $this->assertFalse($response['success']);
        $this->assertEquals('Failed to delete schedules.', $response['data']['message']);
    }
}
