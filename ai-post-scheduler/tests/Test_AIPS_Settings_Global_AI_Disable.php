<?php
/**
 * Tests global AI-disable settings registration.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Settings_Global_AI_Disable extends WP_UnitTestCase {
	public function test_default_options_include_global_ai_disable_flag() {
		$defaults = AIPS_Config::get_instance()->get_default_options();

		$this->assertArrayHasKey('aips_prevent_scheduled_ai_generation', $defaults);
		$this->assertFalse((bool) $defaults['aips_prevent_scheduled_ai_generation']);
	}

	public function test_registered_settings_include_global_ai_disable_flag() {
		$settings = AIPS_Settings::get_registered_settings_args(new AIPS_Settings_UI());

		$this->assertArrayHasKey('aips_prevent_scheduled_ai_generation', $settings);
		$this->assertSame('absint', $settings['aips_prevent_scheduled_ai_generation']['sanitize_callback']);
		$this->assertFalse((bool) $settings['aips_prevent_scheduled_ai_generation']['default']);
	}
}
