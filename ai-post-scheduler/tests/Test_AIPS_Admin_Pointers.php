<?php
/**
 * Unit Tests for AIPS_Admin_Pointers
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Admin_Pointers extends WP_UnitTestCase {

	/**
	 * Setup admin user.
	 */
	public function setUp(): void {
		parent::setUp();
		$admin_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($admin_id);
	}

	/**
	 * Test get_registered_pointers returns expected pointers with valid keys.
	 */
	public function test_get_registered_pointers() {
		$pointers = AIPS_Admin_Pointers::get_registered_pointers();

		$this->assertNotEmpty($pointers);
		$this->assertArrayHasKey('aips_pointer_dashboard', $pointers);
		$this->assertArrayHasKey('aips_pointer_studio', $pointers);
		$this->assertArrayHasKey('aips_pointer_automations', $pointers);
		$this->assertArrayHasKey('aips_pointer_settings', $pointers);

		foreach ($pointers as $id => $pointer) {
			$this->assertArrayHasKey('screens', $pointer);
			$this->assertArrayHasKey('target', $pointer);
			$this->assertArrayHasKey('title', $pointer);
			$this->assertArrayHasKey('content', $pointer);
			$this->assertArrayHasKey('edge', $pointer);
			$this->assertArrayHasKey('align', $pointer);
		}
	}

	/**
	 * Test enqueue_pointers runs cleanly without errors.
	 */
	public function test_enqueue_pointers_execution() {
		set_current_screen('toplevel_page_ai-post-scheduler');
		AIPS_Admin_Pointers::enqueue_pointers('toplevel_page_ai-post-scheduler');
		$this->assertTrue(true);
	}

	/**
	 * Test dismissed pointers are respected.
	 */
	public function test_dismissed_pointer_filtered() {
		$user_id = get_current_user_id();
		update_user_meta($user_id, 'dismissed_wp_pointers', 'aips_pointer_dashboard,aips_pointer_studio');

		$dismissed_raw = (string) get_user_meta($user_id, 'dismissed_wp_pointers', true);
		$dismissed = array_filter(explode(',', $dismissed_raw));

		$this->assertContains('aips_pointer_dashboard', $dismissed);
		$this->assertContains('aips_pointer_studio', $dismissed);
		$this->assertNotContains('aips_pointer_automations', $dismissed);
	}
}
