<?php
/**
 * Ensure templates reuse the shared interval calculator logic.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Templates_Interval extends WP_UnitTestCase {

	public function test_calculate_next_run_matches_interval_calculator() {
		$templates = new AIPS_Templates();
		$calculator = new AIPS_Interval_Calculator();

		$base_time   = current_time('timestamp');
		$frequencies = array('hourly', 'monthly', 'every_wednesday');

		foreach ($frequencies as $frequency) {
			$template_next = $this->invoke_private_method($templates, 'calculate_next_run', array($frequency, $base_time));
			$expected_next = strtotime($calculator->calculate_next_run($frequency, date('Y-m-d H:i:s', $base_time)));

			$this->assertSame($expected_next, $template_next);
		}
	}

	private function invoke_private_method($object, $method, $args = array()) {
		$reflection = new ReflectionMethod($object, $method);
		$reflection->setAccessible(true);

		return $reflection->invokeArgs($object, $args);
	}
}
