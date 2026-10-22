<?php
/**
 * Tests for the AJAX rate-limit enrichment: AIPS_Ajax_Response::wp_error(),
 * AIPS_Ajax_Middleware and AIPS_Resilience_Service::get_rate_limit_retry_after().
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ajax_Rate_Limit_Middleware extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		AIPS_Ajax_Middleware::register();
		update_option('aips_enable_rate_limiting', 1);
		update_option('aips_rate_limit_requests', 2);
		update_option('aips_rate_limit_period', 60);
		delete_transient('aips_rate_limiter_requests');
	}

	public function tearDown(): void {
		delete_transient('aips_rate_limiter_requests');
		delete_option('aips_enable_rate_limiting');
		delete_option('aips_rate_limit_requests');
		delete_option('aips_rate_limit_period');
		remove_all_filters('aips_ajax_error_response');
		parent::tearDown();
	}

	private function capture(callable $callable) {
		ob_start();
		try {
			$callable();
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		} catch (WPAjaxDieStopException $e) {
			// Expected.
		}
		return json_decode(ob_get_clean(), true);
	}

	public function test_wp_error_forwards_code_message_and_data() {
		$err  = new WP_Error('some_code', 'Boom', array('foo' => 'bar'));
		$json = $this->capture(function () use ($err) {
			AIPS_Ajax_Response::wp_error($err);
		});

		$this->assertFalse($json['success']);
		$this->assertSame('some_code', $json['data']['code']);
		$this->assertSame('Boom', $json['data']['message']);
		$this->assertSame('bar', $json['data']['foo']);
	}

	public function test_wp_error_data_cannot_override_message_or_code() {
		$err  = new WP_Error('real_code', 'Real message', array('message' => 'spoof', 'code' => 'spoof'));
		$json = $this->capture(function () use ($err) {
			AIPS_Ajax_Response::wp_error($err);
		});

		$this->assertSame('real_code', $json['data']['code']);
		$this->assertSame('Real message', $json['data']['message']);
	}

	public function test_middleware_enriches_rate_limit_errors_with_retry_after() {
		$err  = new WP_Error('rate_limit_exceeded', 'raw', array('retry_after' => 125));
		$json = $this->capture(function () use ($err) {
			AIPS_Ajax_Response::wp_error($err);
		});

		$this->assertSame('rate_limit_exceeded', $json['data']['code']);
		$this->assertSame(125, $json['data']['retry_after']);
		$this->assertStringContainsString('2m 5s', $json['data']['message']);
	}

	public function test_middleware_falls_back_to_service_when_retry_after_missing() {
		set_transient('aips_rate_limiter_requests', array(time() - 10, time() - 5), 60);

		$json = $this->capture(function () {
			AIPS_Ajax_Response::error('raw', 'rate_limit_exceeded');
		});

		$this->assertGreaterThan(0, $json['data']['retry_after']);
		$this->assertLessThanOrEqual(50, $json['data']['retry_after']);
	}

	public function test_middleware_leaves_other_errors_untouched() {
		$json = $this->capture(function () {
			AIPS_Ajax_Response::error('plain', 'other_code');
		});

		$this->assertSame('plain', $json['data']['message']);
		$this->assertArrayNotHasKey('retry_after', $json['data']);
	}

	public function test_retry_after_is_zero_when_under_limit() {
		$service = new AIPS_Resilience_Service();
		$this->assertSame(0, $service->get_rate_limit_retry_after());

		set_transient('aips_rate_limiter_requests', array(time() - 5), 60);
		$this->assertSame(0, $service->get_rate_limit_retry_after());
	}

	public function test_retry_after_waits_for_the_slot_that_actually_frees_capacity() {
		$now = time();
		// Limit is 2 but three requests are tracked (limit lowered after the fact):
		// two must age out, so the wait is for the second-oldest, not the oldest.
		set_transient('aips_rate_limiter_requests', array($now - 50, $now - 30, $now - 10), 60);

		$service = new AIPS_Resilience_Service();
		$wait    = $service->get_rate_limit_retry_after();

		$this->assertGreaterThanOrEqual(29, $wait);
		$this->assertLessThanOrEqual(30, $wait);
	}
}
