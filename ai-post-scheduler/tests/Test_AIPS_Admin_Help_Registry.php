<?php
/**
 * Unit Tests for AIPS_Admin_Help_Registry
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Admin_Help_Registry extends WP_UnitTestCase {

	/**
	 * Test get_help_dir returns valid directory.
	 */
	public function test_get_help_dir() {
		$dir = AIPS_Admin_Help_Registry::get_help_dir();
		$this->assertNotEmpty($dir);
		$this->assertStringContainsString('templates', $dir);
		$this->assertStringContainsString('help', $dir);
	}

	/**
	 * Test rendering existing help partials.
	 */
	public function test_render_partial_success() {
		$tokens_html = AIPS_Admin_Help_Registry::render_partial('template-tokens.php');
		$this->assertNotEmpty($tokens_html);
		$this->assertStringContainsString('{topic}', $tokens_html);
		$this->assertStringContainsString('{author_name}', $tokens_html);

		$cadence_html = AIPS_Admin_Help_Registry::render_partial('schedule-cadence.php');
		$this->assertNotEmpty($cadence_html);
		$this->assertStringContainsString('Cadence', $cadence_html);

		$engine_html = AIPS_Admin_Help_Registry::render_partial('settings-engine.php');
		$this->assertNotEmpty($engine_html);
		$this->assertStringContainsString('AI Engine', $engine_html);

		$sidebar_html = AIPS_Admin_Help_Registry::render_partial('sidebar-help.php');
		$this->assertNotEmpty($sidebar_html);
		$this->assertStringContainsString('Site Health', $sidebar_html);
	}

	/**
	 * Test rendering non-existent partial safely returns empty string.
	 */
	public function test_render_partial_missing() {
		$output = AIPS_Admin_Help_Registry::render_partial('non-existent-partial.php');
		$this->assertSame('', $output);
	}

	/**
	 * Test is_aips_screen detection.
	 */
	public function test_is_aips_screen() {
		$this->assertFalse(AIPS_Admin_Help_Registry::is_aips_screen(null));

		$screen_core     = convert_to_screen('edit-post');
		$this->assertFalse(AIPS_Admin_Help_Registry::is_aips_screen($screen_core));

		$screen_aips     = convert_to_screen('toplevel_page_aips-templates');
		$this->assertTrue(AIPS_Admin_Help_Registry::is_aips_screen($screen_aips));
	}

	/**
	 * Test get_default_help_tabs for different screens.
	 */
	public function test_get_default_help_tabs() {
		// Template screen
		$tabs = AIPS_Admin_Help_Registry::get_default_help_tabs('toplevel_page_aips-templates', 'aips-templates', '');
		$this->assertNotEmpty($tabs);
		$this->assertSame('aips_template_tokens', $tabs[0]['id']);

		// Schedule screen
		$tabs = AIPS_Admin_Help_Registry::get_default_help_tabs('aips_page_aips-schedule', 'aips-schedule', '');
		$this->assertNotEmpty($tabs);
		$this->assertSame('aips_schedule_cadence', $tabs[0]['id']);

		// Settings screen
		$tabs = AIPS_Admin_Help_Registry::get_default_help_tabs('aips_page_aips-settings', 'aips-settings', '');
		$this->assertNotEmpty($tabs);
		$this->assertSame('aips_settings_engine', $tabs[0]['id']);
	}

	/**
	 * Test custom tab registration.
	 */
	public function test_register_custom_tab() {
		$custom_tab = array(
			'id'      => 'custom_test_tab',
			'title'   => 'Custom Test',
			'content' => '<p>Custom Content</p>',
		);
		AIPS_Admin_Help_Registry::register_tab('test_screen', $custom_tab);

		// Setup screen help should invoke add_help_tab if screen matches
		$screen = convert_to_screen('test_screen_aips');
		$this->assertTrue(AIPS_Admin_Help_Registry::is_aips_screen($screen));
	}
}
