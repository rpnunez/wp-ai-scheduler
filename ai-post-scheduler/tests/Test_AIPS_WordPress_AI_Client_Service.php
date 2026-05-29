<?php
/**
 * Tests for WordPress AI Client backend service.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class Test_AIPS_WordPress_AI_Client_Service extends WP_UnitTestCase {

	public function test_get_call_statistics_matches_expected_shape() {
		$service = new AIPS_WordPress_AI_Client_Service();

		$reflection = new ReflectionClass($service);
		$property   = $reflection->getProperty('call_log');
		$property->setAccessible(true);
		$property->setValue(
			$service,
			array(
				array(
					'type' => 'text',
				),
				array(
					'type'       => 'json',
					'error_code' => 'invalid_json',
				),
			)
		);

		$stats = $service->get_call_statistics();

		$this->assertSame(2, $stats['total']);
		$this->assertSame(1, $stats['successes']);
		$this->assertSame(1, $stats['failures']);
		$this->assertSame(1, $stats['by_type']['text']);
		$this->assertSame(1, $stats['by_type']['json']);
	}

	public function test_decode_json_response_extracts_balanced_fragment() {
		$service = new AIPS_WordPress_AI_Client_Service();

		$reflection = new ReflectionClass($service);
		$method     = $reflection->getMethod('decode_json_response');
		$method->setAccessible(true);
		$result = $method->invoke(
			$service,
			'Sure — here you go: ```json' . "\n" . '{"title":"Hello","items":[1,2]}' . "\n" . '``` Thanks!'
		);

		$this->assertSame(
			array(
				'title' => 'Hello',
				'items' => array(1, 2),
			),
			$result
		);
	}
}
