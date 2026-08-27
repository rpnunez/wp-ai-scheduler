<?php
/**
 * Tests for Stress Test request hardening.
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

class AIPS_Test_Stress_Logger implements AIPS_Logger_Interface {
    public function log($message, $level = 'info', $context = array()) {}
    public function addSeparator($text) {}
}

class AIPS_Test_Stress_AI_Service {
    public $json_options = array();

    public function is_available() {
        return true;
    }

    public function clear_call_log() {}

    public function generate_json($prompt, $options = array()) {
        $this->json_options = $options;

        return array('topics' => array('One', 'Two', 'Three'));
    }

    public function get_call_log() {
        return array(
            array(
                'type'      => 'json',
                'timestamp' => '2026-08-07T00:00:00+00:00',
                'request'   => array('prompt' => 'prompt', 'options' => $this->json_options),
                'response'  => array('success' => true, 'content' => '{"topics":["One","Two","Three"]}'),
            ),
        );
    }
}

class AIPS_Test_Stress_Base_Config {
    public function get_circuit_breaker_config() {
        return array('enabled' => false, 'failure_threshold' => 5, 'timeout' => 300);
    }

    public function get_rate_limit_config() {
        return array('enabled' => false, 'requests' => 10, 'period' => 60);
    }
}

class Test_AIPS_Stress_Test_Service extends WP_UnitTestCase {

    public function test_json_case_reserves_budget_for_reasoning_models() {
        $ai_service = new AIPS_Test_Stress_AI_Service();
        $service    = new AIPS_Stress_Test_Service($ai_service, new AIPS_Test_Stress_Logger());

        $result = $service->run('generate_json');

        $this->assertSame('passed', $result['status']);
        $this->assertSame(1200, $ai_service->json_options['max_tokens']);
        $this->assertSame(0.1, $ai_service->json_options['temperature']);
    }

    public function test_stress_resilience_config_uses_one_short_retry() {
        // Loading the service also declares its request-scoped config adapter.
        class_exists('AIPS_Stress_Test_Service');

        $config = new AIPS_Stress_Test_Resilience_Config(new AIPS_Test_Stress_Base_Config());
        $retry  = $config->get_retry_config();

        $this->assertTrue($retry['enabled']);
        $this->assertSame(2, $retry['max_attempts']);
        $this->assertSame(1, $retry['initial_delay']);
        $this->assertFalse($config->get_circuit_breaker_config()['enabled']);
        $this->assertFalse($config->get_rate_limit_config()['enabled']);
    }
}
