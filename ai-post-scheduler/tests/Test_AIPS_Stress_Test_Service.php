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

/**
 * AI service stub whose generate_json returns a value for every integration
 * field key the meta cases use, so the write-back path can be exercised.
 */
class AIPS_Test_Stress_Meta_AI_Service {
    public function is_available() {
        return true;
    }

    public function clear_call_log() {}

    public function generate_json($prompt, $options = array()) {
        return array(
            'aips_stress_summary'  => 'A concise generated summary.',
            'aips_stress_headline' => 'A Generated Headline',
            'aips_stress_cta'      => 'Read more today',
            'aips_stress_keywords' => 'alpha, beta, gamma',
        );
    }

    public function generate_text($prompt, $options = array()) {
        return 'Generated text value.';
    }

    public function get_call_log() {
        return array(
            array(
                'type'      => 'json',
                'timestamp' => '2026-08-07T00:00:00+00:00',
                'request'   => array('prompt' => 'prompt', 'options' => array()),
                'response'  => array('success' => true, 'content' => '{"aips_stress_summary":"..."}'),
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

    public function test_get_cases_includes_native_meta_cases() {
        $service = new AIPS_Stress_Test_Service(new AIPS_Test_Stress_AI_Service(), new AIPS_Test_Stress_Logger());
        $ids     = wp_list_pluck($service->get_cases(), 'id');

        $this->assertContains('meta_fields_single', $ids);
        $this->assertContains('meta_fields_multi', $ids);
        $this->assertContains('meta_fields_cpt', $ids);
    }

    public function test_get_cases_excludes_acf_case_when_acf_unavailable() {
        // ACF is not installed in the test suite, so its case must not appear.
        $service = new AIPS_Stress_Test_Service(new AIPS_Test_Stress_AI_Service(), new AIPS_Test_Stress_Logger());
        $ids     = wp_list_pluck($service->get_cases(), 'id');

        $this->assertNotContains('meta_fields_acf', $ids);
    }

    public function test_fixed_mappings_repository_returns_given_rows() {
        $rows = array((object) array('field_key' => 'x'));
        $repo = new AIPS_Stress_Test_Fixed_Mappings_Repository($rows);

        $this->assertSame($rows, $repo->get_by_template(123));
        $this->assertSame($rows, $repo->get_by_template(0, false));
    }

    public function test_meta_fields_multi_generates_and_writes_every_field() {
        $service = new AIPS_Stress_Test_Service(new AIPS_Test_Stress_Meta_AI_Service(), new AIPS_Test_Stress_Logger());

        $result = $service->run('meta_fields_multi');

        $this->assertSame('passed', $result['status'], isset($result['error']) ? (string) $result['error'] : '');

        $this->assertNotEmpty($result['artifacts']['post_ids']);
        $post_id = $result['artifacts']['post_ids'][0];

        $this->assertNotSame('', get_post_meta($post_id, 'aips_stress_headline', true));
        $this->assertNotSame('', get_post_meta($post_id, 'aips_stress_summary', true));
        $this->assertNotSame('', get_post_meta($post_id, 'aips_stress_cta', true));
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
