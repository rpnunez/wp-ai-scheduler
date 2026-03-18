<?php
/**
 * Test case for AIPS_Resilience_Service
 *
 * Tests retry logic, circuit breaker pattern, rate limiting,
 * per-service circuit breakers, retryable error classification,
 * and auto-recording of success/failure.
 *
 * @package AI_Post_Scheduler
 * @since 1.10.0
 */

class Test_AIPS_Resilience_Service extends WP_UnitTestCase {

	private $service;
	private $config;

	public function setUp(): void {
		parent::setUp();

		// Reset transients
		delete_transient('aips_circuit_breaker_states');
		delete_transient('aips_circuit_breaker_state');
		delete_transient('aips_rate_limiter_requests');

		// Reset the Config singleton so tests start clean
		$reflection = new ReflectionClass('AIPS_Config');
		$instance = $reflection->getProperty('instance');
		$instance->setAccessible(true);
		$instance->setValue(null, null);

		$this->config = AIPS_Config::get_instance();
		$this->service = new AIPS_Resilience_Service(new AIPS_Logger(), $this->config);
	}

	public function tearDown(): void {
		delete_transient('aips_circuit_breaker_states');
		delete_transient('aips_circuit_breaker_state');
		delete_transient('aips_rate_limiter_requests');

		// Clean up test options
		delete_option('aips_enable_circuit_breaker');
		delete_option('aips_circuit_breaker_threshold');
		delete_option('aips_circuit_breaker_timeout');
		delete_option('aips_enable_rate_limiting');
		delete_option('aips_rate_limit_requests');
		delete_option('aips_rate_limit_period');

		// Reset the Config singleton
		$reflection = new ReflectionClass('AIPS_Config');
		$instance = $reflection->getProperty('instance');
		$instance->setAccessible(true);
		$instance->setValue(null, null);

		parent::tearDown();
	}

	// ========================================
	// Basic Instantiation
	// ========================================

	public function test_instantiation() {
		$this->assertInstanceOf('AIPS_Resilience_Service', $this->service);
	}

	// ========================================
	// Retry Logic
	// ========================================

	public function test_execute_with_retry_returns_result_when_retry_disabled() {
		// Retry is disabled by default in config
		$result = $this->service->execute_with_retry(function() {
			return 'success';
		}, 'text', 'prompt', array());

		$this->assertEquals('success', $result);
	}

	public function test_execute_with_retry_returns_error_when_retry_disabled() {
		$result = $this->service->execute_with_retry(function() {
			return new WP_Error('test_error', 'Test failure');
		}, 'text', 'prompt', array());

		$this->assertInstanceOf('WP_Error', $result);
		$this->assertEquals('test_error', $result->get_error_code());
	}

	// ========================================
	// Retryable Error Classification
	// ========================================

	public function test_is_retryable_returns_false_for_non_retryable_errors() {
		$non_retryable_codes = array(
			'ai_unavailable',
			'chatbot_unavailable',
			'invalid_api_key',
			'invalid_prompt',
		);

		foreach ($non_retryable_codes as $code) {
			$error = new WP_Error($code, 'Test error');
			$this->assertFalse(
				$this->service->is_retryable($error),
				"Error code '$code' should not be retryable"
			);
		}
	}

	public function test_is_retryable_returns_true_for_transient_errors() {
		$retryable_codes = array(
			'generation_failed',
			'empty_response',
			'timeout',
			'rate_limited_by_provider',
		);

		foreach ($retryable_codes as $code) {
			$error = new WP_Error($code, 'Test error');
			$this->assertTrue(
				$this->service->is_retryable($error),
				"Error code '$code' should be retryable"
			);
		}
	}

	public function test_set_retryable_checker_custom_callback() {
		$this->service->set_retryable_checker(function($error) {
			return $error->get_error_code() === 'custom_retryable';
		});

		$retryable = new WP_Error('custom_retryable', 'Retry me');
		$non_retryable = new WP_Error('something_else', 'Do not retry');

		$this->assertTrue($this->service->is_retryable($retryable));
		$this->assertFalse($this->service->is_retryable($non_retryable));
	}

	public function test_custom_retryable_checker_does_not_override_non_retryable_codes() {
		// Even with a custom checker that always returns true,
		// non-retryable codes should still not be retried
		$this->service->set_retryable_checker(function($error) {
			return true;
		});

		$error = new WP_Error('ai_unavailable', 'Not available');
		$this->assertFalse($this->service->is_retryable($error));
	}

	// ========================================
	// Circuit Breaker - Per-Service
	// ========================================

	public function test_circuit_breaker_defaults_to_closed() {
		update_option('aips_enable_circuit_breaker', true);

		$status = $this->service->get_circuit_breaker_status('text');
		$this->assertEquals('closed', $status['state']);
		$this->assertEquals(0, $status['failures']);
	}

	public function test_circuit_breaker_per_service_isolation() {
		update_option('aips_enable_circuit_breaker', true);
		update_option('aips_circuit_breaker_threshold', 2);

		// Fail the 'image' service twice
		$this->service->record_failure('image');
		$this->service->record_failure('image');

		// Image should be open
		$image_status = $this->service->get_circuit_breaker_status('image');
		$this->assertEquals('open', $image_status['state']);

		// Text should still be closed
		$text_status = $this->service->get_circuit_breaker_status('text');
		$this->assertEquals('closed', $text_status['state']);

		// Text circuit should allow requests
		$this->assertTrue($this->service->check_circuit_breaker('text'));
		// Image circuit should block requests
		$this->assertFalse($this->service->check_circuit_breaker('image'));
	}

	public function test_circuit_breaker_opens_at_threshold() {
		update_option('aips_enable_circuit_breaker', true);
		update_option('aips_circuit_breaker_threshold', 3);

		$this->service->record_failure('text');
		$this->service->record_failure('text');

		// Still below threshold
		$status = $this->service->get_circuit_breaker_status('text');
		$this->assertEquals('closed', $status['state']);
		$this->assertEquals(2, $status['failures']);

		// Third failure reaches threshold
		$this->service->record_failure('text');
		$status = $this->service->get_circuit_breaker_status('text');
		$this->assertEquals('open', $status['state']);
	}

	public function test_circuit_breaker_half_open_failure_reopens() {
		update_option('aips_enable_circuit_breaker', true);
		update_option('aips_circuit_breaker_threshold', 2);
		update_option('aips_circuit_breaker_timeout', 1);

		// Trip the circuit breaker
		$this->service->record_failure('text');
		$this->service->record_failure('text');

		$status = $this->service->get_circuit_breaker_status('text');
		$this->assertEquals('open', $status['state']);

		// Wait for timeout (1 second)
		sleep(2);

		// Check should transition to half-open
		$this->assertTrue($this->service->check_circuit_breaker('text'));
		$status = $this->service->get_circuit_breaker_status('text');
		$this->assertEquals('half_open', $status['state']);

		// A single failure in half-open state should immediately re-open
		$this->service->record_failure('text');
		$status = $this->service->get_circuit_breaker_status('text');
		$this->assertEquals('open', $status['state']);
	}

	public function test_circuit_breaker_half_open_success_closes() {
		update_option('aips_enable_circuit_breaker', true);
		update_option('aips_circuit_breaker_threshold', 2);
		update_option('aips_circuit_breaker_timeout', 1);

		// Trip the circuit breaker
		$this->service->record_failure('text');
		$this->service->record_failure('text');

		// Wait for timeout
		sleep(2);

		// Check transitions to half-open
		$this->assertTrue($this->service->check_circuit_breaker('text'));

		// Success in half-open state should close
		$this->service->record_success('text');
		$status = $this->service->get_circuit_breaker_status('text');
		$this->assertEquals('closed', $status['state']);
		$this->assertEquals(0, $status['failures']);
	}

	public function test_circuit_breaker_success_resets_failure_count() {
		update_option('aips_enable_circuit_breaker', true);
		update_option('aips_circuit_breaker_threshold', 5);

		$this->service->record_failure('text');
		$this->service->record_failure('text');

		$status = $this->service->get_circuit_breaker_status('text');
		$this->assertEquals(2, $status['failures']);

		// Success should reset failures
		$this->service->record_success('text');
		$status = $this->service->get_circuit_breaker_status('text');
		$this->assertEquals(0, $status['failures']);
	}

	public function test_circuit_breaker_disabled_always_allows() {
		update_option('aips_enable_circuit_breaker', false);

		// Record many failures
		for ($i = 0; $i < 100; $i++) {
			$this->service->record_failure('text');
		}

		// Should always be allowed when disabled
		$this->assertTrue($this->service->check_circuit_breaker('text'));
	}

	public function test_reset_circuit_breaker_single_service() {
		update_option('aips_enable_circuit_breaker', true);
		update_option('aips_circuit_breaker_threshold', 2);

		// Trip both text and image
		$this->service->record_failure('text');
		$this->service->record_failure('text');
		$this->service->record_failure('image');
		$this->service->record_failure('image');

		// Reset only text
		$this->service->reset_circuit_breaker('text');

		$text_status = $this->service->get_circuit_breaker_status('text');
		$image_status = $this->service->get_circuit_breaker_status('image');

		$this->assertEquals('closed', $text_status['state']);
		$this->assertEquals(0, $text_status['failures']);
		$this->assertEquals('open', $image_status['state']);
	}

	public function test_reset_circuit_breaker_all_services() {
		update_option('aips_enable_circuit_breaker', true);
		update_option('aips_circuit_breaker_threshold', 2);

		$this->service->record_failure('text');
		$this->service->record_failure('text');
		$this->service->record_failure('image');
		$this->service->record_failure('image');

		// Reset all (null argument)
		$this->service->reset_circuit_breaker(null);

		$all_states = $this->service->get_circuit_breaker_status(null);
		$this->assertEmpty($all_states);
	}

	public function test_get_circuit_breaker_status_all_returns_array() {
		update_option('aips_enable_circuit_breaker', true);

		$this->service->record_failure('text');
		$this->service->record_failure('image');

		$all = $this->service->get_circuit_breaker_status(null);
		$this->assertIsArray($all);
		$this->assertArrayHasKey('text', $all);
		$this->assertArrayHasKey('image', $all);
	}

	// ========================================
	// Circuit Breaker - Legacy Migration
	// ========================================

	public function test_legacy_circuit_breaker_state_migrated() {
		// Simulate legacy single-state transient
		$legacy_state = array(
			'failures' => 3,
			'last_failure_time' => time() - 60,
			'state' => 'open',
		);
		set_transient('aips_circuit_breaker_state', $legacy_state, HOUR_IN_SECONDS);

		// Create a new service which should migrate the state
		$service = new AIPS_Resilience_Service(new AIPS_Logger(), $this->config);

		update_option('aips_enable_circuit_breaker', true);
		update_option('aips_circuit_breaker_timeout', 300);

		$status = $service->get_circuit_breaker_status('default');
		$this->assertEquals('open', $status['state']);
		$this->assertEquals(3, $status['failures']);

		// Legacy transient should be deleted
		$this->assertFalse(get_transient('aips_circuit_breaker_state'));
	}

	// ========================================
	// Circuit Breaker - WordPress Hooks
	// ========================================

	public function test_circuit_breaker_fires_opened_action() {
		update_option('aips_enable_circuit_breaker', true);
		update_option('aips_circuit_breaker_threshold', 2);

		$fired = false;
		$captured_service = null;
		add_action('aips_circuit_breaker_opened', function($service) use (&$fired, &$captured_service) {
			$fired = true;
			$captured_service = $service;
		});

		$this->service->record_failure('text');
		$this->service->record_failure('text');

		$this->assertTrue($fired, 'aips_circuit_breaker_opened action should fire');
		$this->assertEquals('text', $captured_service);
	}

	public function test_circuit_breaker_fires_half_open_action() {
		update_option('aips_enable_circuit_breaker', true);
		update_option('aips_circuit_breaker_threshold', 2);
		update_option('aips_circuit_breaker_timeout', 1);

		$fired = false;
		add_action('aips_circuit_breaker_half_open', function($service) use (&$fired) {
			$fired = true;
		});

		$this->service->record_failure('text');
		$this->service->record_failure('text');

		sleep(2);
		$this->service->check_circuit_breaker('text');

		$this->assertTrue($fired, 'aips_circuit_breaker_half_open action should fire');
	}

	public function test_circuit_breaker_fires_closed_action() {
		update_option('aips_enable_circuit_breaker', true);
		update_option('aips_circuit_breaker_threshold', 2);
		update_option('aips_circuit_breaker_timeout', 1);

		$fired = false;
		add_action('aips_circuit_breaker_closed', function($service) use (&$fired) {
			$fired = true;
		});

		// Trip, wait, half-open, succeed
		$this->service->record_failure('text');
		$this->service->record_failure('text');
		sleep(2);
		$this->service->check_circuit_breaker('text');
		$this->service->record_success('text');

		$this->assertTrue($fired, 'aips_circuit_breaker_closed action should fire');
	}

	// ========================================
	// Rate Limiting
	// ========================================

	public function test_rate_limit_disabled_always_allows() {
		update_option('aips_enable_rate_limiting', false);

		$this->assertTrue($this->service->check_rate_limit());
	}

	public function test_rate_limit_allows_within_quota() {
		update_option('aips_enable_rate_limiting', true);
		update_option('aips_rate_limit_requests', 5);
		update_option('aips_rate_limit_period', 60);

		// First check should pass without consuming quota
		$this->assertTrue($this->service->check_rate_limit());
	}

	public function test_rate_limit_check_does_not_consume_quota() {
		update_option('aips_enable_rate_limiting', true);
		update_option('aips_rate_limit_requests', 2);
		update_option('aips_rate_limit_period', 60);

		// Multiple checks should all pass (no quota consumed)
		$this->assertTrue($this->service->check_rate_limit());
		$this->assertTrue($this->service->check_rate_limit());
		$this->assertTrue($this->service->check_rate_limit());

		// Now record actual usage
		$this->service->record_rate_limit_usage();
		$this->service->record_rate_limit_usage();

		// Now check should fail
		$this->assertFalse($this->service->check_rate_limit());
	}

	public function test_rate_limiter_status_returns_correct_structure() {
		update_option('aips_enable_rate_limiting', true);
		update_option('aips_rate_limit_requests', 10);
		update_option('aips_rate_limit_period', 60);

		$status = $this->service->get_rate_limiter_status();

		$this->assertIsArray($status);
		$this->assertArrayHasKey('enabled', $status);
		$this->assertArrayHasKey('current_requests', $status);
		$this->assertArrayHasKey('max_requests', $status);
		$this->assertArrayHasKey('period', $status);
		$this->assertArrayHasKey('remaining', $status);
		$this->assertTrue($status['enabled']);
		$this->assertEquals(0, $status['current_requests']);
		$this->assertEquals(10, $status['max_requests']);
		$this->assertEquals(10, $status['remaining']);
	}

	public function test_rate_limiter_status_reflects_usage() {
		update_option('aips_enable_rate_limiting', true);
		update_option('aips_rate_limit_requests', 5);
		update_option('aips_rate_limit_period', 60);

		$this->service->record_rate_limit_usage();
		$this->service->record_rate_limit_usage();

		$status = $this->service->get_rate_limiter_status();
		$this->assertEquals(2, $status['current_requests']);
		$this->assertEquals(3, $status['remaining']);
	}

	public function test_reset_rate_limiter() {
		update_option('aips_enable_rate_limiting', true);
		update_option('aips_rate_limit_requests', 2);
		update_option('aips_rate_limit_period', 60);

		$this->service->record_rate_limit_usage();
		$this->service->record_rate_limit_usage();

		// Should be at limit
		$this->assertFalse($this->service->check_rate_limit());

		// Reset
		$this->assertTrue($this->service->reset_rate_limiter());

		// Should allow again
		$this->assertTrue($this->service->check_rate_limit());
	}

	// ========================================
	// execute_safely - Auto-Recording
	// ========================================

	public function test_execute_safely_auto_records_success() {
		update_option('aips_enable_circuit_breaker', true);
		update_option('aips_circuit_breaker_threshold', 5);

		// First, cause some failures
		$this->service->record_failure('text');
		$this->service->record_failure('text');

		$status_before = $this->service->get_circuit_breaker_status('text');
		$this->assertEquals(2, $status_before['failures']);

		// execute_safely with success should auto-reset failures
		$result = $this->service->execute_safely(function() {
			return 'good result';
		}, 'text', 'prompt', array());

		$this->assertEquals('good result', $result);

		$status_after = $this->service->get_circuit_breaker_status('text');
		$this->assertEquals(0, $status_after['failures']);
	}

	public function test_execute_safely_auto_records_failure() {
		update_option('aips_enable_circuit_breaker', true);
		update_option('aips_circuit_breaker_threshold', 5);

		$result = $this->service->execute_safely(function() {
			return new WP_Error('test_fail', 'Something broke');
		}, 'text', 'prompt', array());

		$this->assertInstanceOf('WP_Error', $result);

		$status = $this->service->get_circuit_breaker_status('text');
		$this->assertEquals(1, $status['failures']);
	}

	public function test_execute_safely_returns_circuit_breaker_error_when_open() {
		update_option('aips_enable_circuit_breaker', true);
		update_option('aips_circuit_breaker_threshold', 2);

		$this->service->record_failure('text');
		$this->service->record_failure('text');

		$result = $this->service->execute_safely(function() {
			return 'should not run';
		}, 'text', 'prompt', array());

		$this->assertInstanceOf('WP_Error', $result);
		$this->assertEquals('circuit_breaker_open', $result->get_error_code());
	}

	public function test_execute_safely_returns_rate_limit_error() {
		update_option('aips_enable_rate_limiting', true);
		update_option('aips_rate_limit_requests', 1);
		update_option('aips_rate_limit_period', 60);

		// Consume the quota
		$this->service->record_rate_limit_usage();

		$result = $this->service->execute_safely(function() {
			return 'should not run';
		}, 'text', 'prompt', array());

		$this->assertInstanceOf('WP_Error', $result);
		$this->assertEquals('rate_limit_exceeded', $result->get_error_code());
	}

	public function test_execute_safely_records_rate_limit_usage() {
		update_option('aips_enable_rate_limiting', true);
		update_option('aips_rate_limit_requests', 3);
		update_option('aips_rate_limit_period', 60);

		// Execute safely should record usage
		$this->service->execute_safely(function() {
			return 'result';
		}, 'text', 'prompt', array());

		$status = $this->service->get_rate_limiter_status();
		$this->assertEquals(1, $status['current_requests']);
	}

	public function test_execute_safely_does_not_record_rate_limit_on_circuit_breaker_block() {
		update_option('aips_enable_circuit_breaker', true);
		update_option('aips_circuit_breaker_threshold', 1);
		update_option('aips_enable_rate_limiting', true);
		update_option('aips_rate_limit_requests', 10);
		update_option('aips_rate_limit_period', 60);

		// Trip the circuit breaker
		$this->service->record_failure('text');

		// This should be blocked by circuit breaker, not consuming rate limit
		$this->service->execute_safely(function() {
			return 'should not run';
		}, 'text', 'prompt', array());

		$status = $this->service->get_rate_limiter_status();
		$this->assertEquals(0, $status['current_requests']);
	}

	// ========================================
	// execute_safely - Per-Service Type
	// ========================================

	public function test_execute_safely_uses_service_type_for_circuit_breaker() {
		update_option('aips_enable_circuit_breaker', true);
		update_option('aips_circuit_breaker_threshold', 1);

		// Fail the text service
		$this->service->execute_safely(function() {
			return new WP_Error('fail', 'text fail');
		}, 'text', 'prompt', array());

		// Text should be open
		$text_status = $this->service->get_circuit_breaker_status('text');
		$this->assertEquals('open', $text_status['state']);

		// Image should still work
		$result = $this->service->execute_safely(function() {
			return 'image works';
		}, 'image', 'prompt', array());

		$this->assertEquals('image works', $result);
	}
}
