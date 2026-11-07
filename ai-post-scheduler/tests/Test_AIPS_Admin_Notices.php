<?php
/**
 * Unit Tests for AIPS_Admin_Notices
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Admin_Notices extends WP_UnitTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	private $admin_user_id;

	/**
	 * Setup test case with an administrator user.
	 */
	public function set_up() {
		parent::set_up();
		$this->admin_user_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($this->admin_user_id);
	}

	/**
	 * Teardown cleanups.
	 */
	public function tear_down() {
		wp_set_current_user(0);
		parent::tear_down();
	}

	/**
	 * Test adding a notice to the in-memory queue.
	 */
	public function test_add_notice() {
		AIPS_Admin_Notices::add('test_notice_1', 'Hello World', array(
			'type'        => 'success',
			'dismissible' => true,
		));

		ob_start();
		AIPS_Admin_Notices::render_notices();
		$output = ob_get_clean();

		$this->assertStringContainsString('Hello World', $output);
		$this->assertStringContainsString('notice-success', $output);
		$this->assertStringContainsString('data-aips-notice-id="test_notice_1"', $output);
	}

	/**
	 * Test user dismissal persistence.
	 */
	public function test_dismiss_and_is_dismissed() {
		$notice_id = 'test_dismissible_notice';

		$this->assertFalse(AIPS_Admin_Notices::is_dismissed($notice_id, $this->admin_user_id));

		$success = AIPS_Admin_Notices::dismiss($notice_id, $this->admin_user_id);
		$this->assertTrue($success);

		$this->assertTrue(AIPS_Admin_Notices::is_dismissed($notice_id, $this->admin_user_id));

		// When dismissed, render_notices should suppress it
		AIPS_Admin_Notices::add($notice_id, 'Dismissed Message', array('dismissible' => true));
		ob_start();
		AIPS_Admin_Notices::render_notices();
		$output = ob_get_clean();

		$this->assertStringNotContainsString('Dismissed Message', $output);
	}

	/**
	 * Test flash notice queuing and purge upon rendering.
	 */
	public function test_add_flash_notice() {
		AIPS_Admin_Notices::add_flash('Flash message for next load', 'warning', true, $this->admin_user_id);

		// First render should display the flash message
		ob_start();
		AIPS_Admin_Notices::render_notices();
		$output = ob_get_clean();

		$this->assertStringContainsString('Flash message for next load', $output);
		$this->assertStringContainsString('notice-warning', $output);

		// Second render should not display it because it was purged
		ob_start();
		AIPS_Admin_Notices::render_notices();
		$output_second = ob_get_clean();

		$this->assertStringNotContainsString('Flash message for next load', $output_second);
	}

	/**
	 * Test render_single_notice fallback output.
	 */
	public function test_render_single_notice() {
		ob_start();
		AIPS_Admin_Notices::render_single_notice('Single notice message', 'error', true, 'custom_err_id');
		$output = ob_get_clean();

		$this->assertStringContainsString('Single notice message', $output);
		$this->assertStringContainsString('notice-error', $output);
		$this->assertStringContainsString('data-aips-notice-id="custom_err_id"', $output);
	}
}
