<?php
/**
 * Tests for Partial Generation Email Notifications.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Partial_Generation_Notifications extends WP_UnitTestCase {

	/**
	 * Test that the notification email body includes the partial generations link and missing components.
	 */
	public function test_email_message_includes_partial_generation_details() {
		$notifications = new AIPS_Partial_Generation_Notifications();
		$context = new AIPS_Template_Context((object) array(
			'id' => 55,
			'name' => 'Recovery Template',
			'prompt_template' => 'Prompt',
			'post_status' => 'draft',
			'post_category' => 0,
		));

		$reflection = new ReflectionClass($notifications);
		$method = $reflection->getMethod('build_email_message');
		$method->setAccessible(true);

		$message = $method->invoke(
			$notifications,
			123,
			'Recovery Post',
			array('Excerpt', 'Featured Image'),
			$context,
			77
		);

		$this->assertStringContainsString('Recovery Post', $message);
		$this->assertStringContainsString('Excerpt', $message);
		$this->assertStringContainsString('Featured Image', $message);
		$this->assertStringContainsString(admin_url('admin.php?page=aips-generated-posts#aips-partial-generations'), $message);
		$this->assertStringContainsString('Recovery Template', $message);
		$this->assertStringContainsString('77', $message);
	}
}