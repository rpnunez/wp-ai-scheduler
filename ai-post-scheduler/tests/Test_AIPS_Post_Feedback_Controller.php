<?php
class Test_AIPS_Post_Feedback_Controller extends WP_UnitTestCase {
	public function test_registers_all_feedback_ajax_hooks() {
		$service = $this->getMockBuilder(AIPS_Post_Feedback_Service::class)->disableOriginalConstructor()->getMock();
		$controller = new AIPS_Post_Feedback_Controller($service);
		foreach (array('set', 'clear', 'get', 'bulk') as $action) {
			$this->assertNotFalse(has_action('wp_ajax_aips_post_feedback_' . $action));
			$this->assertSame(AIPS_Post_Feedback_Controller::class, AIPS_Ajax_Registry::get_controller_for('aips_post_feedback_' . $action));
		}
		$this->assertSame(100, AIPS_Post_Feedback_Controller::MAX_BULK);
	}
}
