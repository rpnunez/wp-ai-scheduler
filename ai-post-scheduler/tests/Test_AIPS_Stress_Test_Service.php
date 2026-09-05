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
            'topics'                     => array(
                array('topic' => 'AI in Healthcare', 'category' => 'Tech', 'angle' => 'Practical Guide'),
                array('topic' => 'Quantum Computing', 'category' => 'Science', 'angle' => 'Deep Dive'),
            ),
            'categories'                 => array('Technology', 'Innovation'),
            'tags'                       => array('AI', 'Automation', 'WordPress'),
            'aips_stress_summary'        => 'A concise generated summary.',
            'aips_stress_headline'       => 'A Generated Headline',
            'aips_stress_cta'            => 'Read more today',
            'aips_stress_keywords'       => 'alpha, beta, gamma',
            'aips_stress_page_subtitle'  => 'A Generated Page Subtitle',
            'aips_stress_page_overview'  => 'A detailed overview of the page.',
            'aips_stress_page_details'   => 'Key details about the page content.',
            'aips_stress_page_cta'       => 'Get started now',
            'aips_stress_score'          => '9.2',
            'aips_stress_deep_summary'   => 'A comprehensive two-paragraph summary.',
            'aips_stress_specs_table'    => '<table><tr><td>Spec</td><td>Value</td></tr></table>',
        );
    }

    public function generate_text($prompt, $options = array()) {
        return 'Generated text value for stress test.';
    }

    public function generate_embedding($text, $params = array()) {
        return array_fill(0, 768, 0.05);
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

    public function test_get_cases_includes_native_meta_and_page_cases() {
        $service = new AIPS_Stress_Test_Service(new AIPS_Test_Stress_AI_Service(), new AIPS_Test_Stress_Logger());
        $ids     = wp_list_pluck($service->get_cases(), 'id');

        $this->assertContains('save_page', $ids);
        $this->assertContains('meta_fields_single', $ids);
        $this->assertContains('meta_fields_multi', $ids);
        $this->assertContains('meta_fields_post_3', $ids);
        $this->assertContains('meta_fields_page_4', $ids);
        $this->assertContains('meta_fields_cpt', $ids);
        $this->assertContains('regen_components', $ids);
        $this->assertContains('regen_content', $ids);
        $this->assertContains('generate_embedding', $ids);
        $this->assertContains('post_with_taxonomies', $ids);
        $this->assertContains('author_post', $ids);
        $this->assertContains('author_topics', $ids);
        $this->assertContains('cpt_complex_meta', $ids);
    }

    public function test_get_cases_excludes_acf_case_when_acf_unavailable() {
        // ACF is not installed in the test suite, so its case must not appear.
        $service = new AIPS_Stress_Test_Service(new AIPS_Test_Stress_AI_Service(), new AIPS_Test_Stress_Logger());
        $ids     = wp_list_pluck($service->get_cases(), 'id');

        $this->assertNotContains('meta_fields_acf', $ids);
    }

    public function test_author_topics_case() {
        $service = new AIPS_Stress_Test_Service(new AIPS_Test_Stress_Meta_AI_Service(), new AIPS_Test_Stress_Logger());
        $result  = $service->run('author_topics');

        $this->assertSame('passed', $result['status']);
        $this->assertGreaterThan(0, $result['plugin_value']['topic_count']);
    }

    public function test_generate_embedding_case() {
        $service = new AIPS_Stress_Test_Service(new AIPS_Test_Stress_Meta_AI_Service(), new AIPS_Test_Stress_Logger());
        $result  = $service->run('generate_embedding');

        $this->assertSame('passed', $result['status']);
        $this->assertSame(768, $result['plugin_value']['dimensions']);
    }

    public function test_cpt_complex_meta_generates_and_writes_3_shapes() {
        $service = new AIPS_Stress_Test_Service(new AIPS_Test_Stress_Meta_AI_Service(), new AIPS_Test_Stress_Logger());
        $result  = $service->run('cpt_complex_meta');

        $this->assertSame('passed', $result['status'], isset($result['error']) ? (string) $result['error'] : '');
        $post_id = $result['plugin_value']['post_id'];

        $this->assertNotSame('', get_post_meta($post_id, 'aips_stress_score', true));
        $this->assertNotSame('', get_post_meta($post_id, 'aips_stress_deep_summary', true));
        $this->assertNotSame('', get_post_meta($post_id, 'aips_stress_specs_table', true));
    }

    public function test_post_with_taxonomies_case() {
        $service = new AIPS_Stress_Test_Service(new AIPS_Test_Stress_Meta_AI_Service(), new AIPS_Test_Stress_Logger());
        $result  = $service->run('post_with_taxonomies');

        $this->assertSame('passed', $result['status']);
        $this->assertNotEmpty($result['plugin_value']['categories']);
        $this->assertNotEmpty($result['plugin_value']['tags']);
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

        $this->assertSame('post', get_post_type($post_id));
        $this->assertNotSame('', get_post_meta($post_id, 'aips_stress_headline', true));
        $this->assertNotSame('', get_post_meta($post_id, 'aips_stress_summary', true));
        $this->assertNotSame('', get_post_meta($post_id, 'aips_stress_cta', true));
    }

    public function test_meta_fields_post_3_generates_post_and_writes_3_fields() {
        $service = new AIPS_Stress_Test_Service(new AIPS_Test_Stress_Meta_AI_Service(), new AIPS_Test_Stress_Logger());

        $result = $service->run('meta_fields_post_3');

        $this->assertSame('passed', $result['status'], isset($result['error']) ? (string) $result['error'] : '');

        $this->assertNotEmpty($result['artifacts']['post_ids']);
        $post_id = $result['artifacts']['post_ids'][0];

        $this->assertSame('post', get_post_type($post_id));
        $this->assertNotSame('', get_post_meta($post_id, 'aips_stress_headline', true));
        $this->assertNotSame('', get_post_meta($post_id, 'aips_stress_summary', true));
        $this->assertNotSame('', get_post_meta($post_id, 'aips_stress_cta', true));
    }

    public function test_meta_fields_page_4_generates_page_and_writes_4_fields() {
        $service = new AIPS_Stress_Test_Service(new AIPS_Test_Stress_Meta_AI_Service(), new AIPS_Test_Stress_Logger());

        $result = $service->run('meta_fields_page_4');

        $this->assertSame('passed', $result['status'], isset($result['error']) ? (string) $result['error'] : '');

        $this->assertNotEmpty($result['artifacts']['post_ids']);
        $post_id = $result['artifacts']['post_ids'][0];

        $this->assertSame('page', get_post_type($post_id));
        $this->assertNotSame('', get_post_meta($post_id, 'aips_stress_page_subtitle', true));
        $this->assertNotSame('', get_post_meta($post_id, 'aips_stress_page_overview', true));
        $this->assertNotSame('', get_post_meta($post_id, 'aips_stress_page_details', true));
        $this->assertNotSame('', get_post_meta($post_id, 'aips_stress_page_cta', true));
    }

    public function test_save_run_to_history_and_diff() {
        $service = new AIPS_Stress_Test_Service(new AIPS_Test_Stress_Meta_AI_Service(), new AIPS_Test_Stress_Logger());

        $run_data_1 = array(
            'environment' => array('provider' => 'OpenAI', 'model' => 'gpt-4o'),
            'totals'      => array('cases' => 2, 'passed' => 2, 'failed' => 0, 'duration_ms' => 1500),
            'results'     => array(
                array('case' => 'generate_title', 'label' => 'Generate a Title', 'status' => 'passed', 'duration_ms' => 700, 'summary' => 'OK', 'ai_value' => 'T1', 'plugin_value' => 'T1'),
                array('case' => 'generate_json', 'label' => 'Generate JSON', 'status' => 'passed', 'duration_ms' => 800, 'summary' => 'OK', 'ai_value' => 'J1', 'plugin_value' => 'J1'),
            ),
        );

        $run_data_2 = array(
            'environment' => array('provider' => 'Google Gemini', 'model' => 'gemini-1.5-pro'),
            'totals'      => array('cases' => 2, 'passed' => 1, 'failed' => 1, 'duration_ms' => 1100),
            'results'     => array(
                array('case' => 'generate_title', 'label' => 'Generate a Title', 'status' => 'passed', 'duration_ms' => 500, 'summary' => 'OK', 'ai_value' => 'T2', 'plugin_value' => 'T2'),
                array('case' => 'generate_json', 'label' => 'Generate JSON', 'status' => 'failed', 'duration_ms' => 600, 'summary' => 'Error', 'ai_value' => 'Err', 'plugin_value' => 'Err'),
            ),
        );

        $id1 = $service->save_run_to_history($run_data_1);
        $id2 = $service->save_run_to_history($run_data_2);

        $this->assertIsInt($id1);
        $this->assertIsInt($id2);

        $history = $service->get_run_history(10);
        $this->assertNotEmpty($history);

        $diff = $service->diff_runs($id1, $id2);
        $this->assertIsArray($diff);
        $this->assertArrayHasKey('case_diffs', $diff);
        $this->assertArrayHasKey('totals_diff', $diff);
        $this->assertSame(-1, $diff['totals_diff']['passed_diff']);
        $this->assertSame(-400, $diff['totals_diff']['duration_diff']);
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
