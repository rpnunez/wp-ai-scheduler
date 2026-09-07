<?php
/**
 * Tests for AIPS_Resilience_Service error classification.
 *
 * Focuses on the provider-abstraction guarantees: permanent capability errors
 * (raised by an adapter before any remote call) must not be retried and must
 * not count toward the circuit breaker, and permanent WP AI Client codes must
 * abort the retry loop immediately.
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

class Test_AIPS_Resilience_Service extends WP_UnitTestCase {

    /** @var AIPS_Resilience_Service */
    private $service;

    public function setUp(): void {
        parent::setUp();

        update_option('aips_enable_retry', 1);
        update_option('aips_retry_max_attempts', 3);
        update_option('aips_retry_initial_delay', 0);
        update_option('aips_enable_rate_limiting', 0);
        update_option('aips_enable_circuit_breaker', 1);
        update_option('aips_circuit_breaker_threshold', 5);

        $this->service = new AIPS_Resilience_Service();
        $this->service->reset_circuit_breaker();
    }

    public function tearDown(): void {
        $this->service->reset_circuit_breaker();

        delete_option('aips_enable_retry');
        delete_option('aips_retry_max_attempts');
        delete_option('aips_retry_initial_delay');
        delete_option('aips_enable_rate_limiting');
        delete_option('aips_enable_circuit_breaker');
        delete_option('aips_circuit_breaker_threshold');

        parent::tearDown();
    }

    /**
     * @return array<string,array{string}>
     */
    public function capability_code_provider() {
        return array(
            'text'       => array('text_generation_not_supported'),
            'image'      => array('image_generation_not_supported'),
            'embeddings' => array('embeddings_not_supported'),
        );
    }

    /**
     * @dataProvider capability_code_provider
     */
    public function test_capability_errors_are_not_retried($code) {
        $calls = 0;

        $result = $this->service->execute_with_retry(function() use (&$calls, $code) {
            $calls++;
            return new WP_Error($code, 'capability unavailable');
        }, 'text', 'prompt', array());

        $this->assertSame(1, $calls, "Closure must run exactly once for '{$code}'.");
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertSame($code, $result->get_error_code());
    }

    /**
     * @dataProvider capability_code_provider
     */
    public function test_capability_errors_do_not_record_circuit_breaker_failure($code) {
        $before = $this->service->get_circuit_breaker_status();

        $result = $this->service->execute_safely(function() use ($code) {
            return new WP_Error($code, 'capability unavailable');
        }, 'text', 'prompt', array());

        $after = $this->service->get_circuit_breaker_status();

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertSame($before['failures'], $after['failures'], "'{$code}' must not count as a circuit-breaker failure.");
        $this->assertSame('closed', $after['state']);
    }

    public function test_no_connector_is_non_retryable() {
        $calls = 0;

        $result = $this->service->execute_with_retry(function() use (&$calls) {
            $calls++;
            return new WP_Error('no_connector', 'No AI connector configured.');
        }, 'text', 'prompt', array());

        $this->assertSame(1, $calls);
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertSame('no_connector', $result->get_error_code());
    }

    public function test_retryable_errors_still_retry_to_max_attempts() {
        $calls = 0;

        $result = $this->service->execute_with_retry(function() use (&$calls) {
            $calls++;
            return new WP_Error('generation_failed', 'transient upstream error');
        }, 'text', 'prompt', array());

        $this->assertSame(3, $calls, 'Unknown/transient codes must keep retrying to max attempts.');
        $this->assertInstanceOf('WP_Error', $result);
    }
}
