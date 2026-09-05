<?php
class Test_AIPS_Post_Feedback_Editor extends WP_UnitTestCase {
	public function test_editor_registers_meta_box_hook_without_save_post_mutation() {
		$service = $this->getMockBuilder(AIPS_Post_Feedback_Service::class)->disableOriginalConstructor()->getMock();
		$editor = new AIPS_Post_Feedback_Editor($service);
		$this->assertNotFalse(has_action('add_meta_boxes_post', array($editor, 'register_meta_box')));
		$this->assertFalse(has_action('save_post', array($editor, 'save')));
	}
}
