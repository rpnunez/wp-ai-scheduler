<?php
/**
 * Test case for AI Service
 *
 * Tests the extraction and functionality of AIPS_AI_Service class.
 * Note: These tests assume AI Engine is not available in the test environment.
 *
 * @package AI_Post_Scheduler
 * @since 1.4.0
 */

class Test_AIPS_AI_Service extends WP_UnitTestCase {

    private $service;

    public function setUp(): void {
        parent::setUp();
        $this->service = new AIPS_AI_Service();
    }

    public function tearDown(): void {
        parent::tearDown();
    }

    /**
     * Test service instantiation
     */
    public function test_service_instantiation() {
        $this->assertInstanceOf('AIPS_AI_Service', $this->service);
    }

    /**
     * Test is_available returns boolean
     */
    public function test_is_available_returns_boolean() {
        $result = $this->service->is_available();
        $this->assertIsBool($result);
    }

    /**
     * Test generate_text returns WP_Error when AI unavailable
     */
    public function test_generate_text_unavailable() {
        // Assuming AI Engine is not available in test environment
        if (!$this->service->is_available()) {
            $result = $this->service->generate_text('Test prompt');
            $this->assertInstanceOf('WP_Error', $result);
            $this->assertEquals('ai_unavailable', $result->get_error_code());
        } else {
            $this->markTestSkipped('AI Engine is available, cannot test unavailable scenario');
        }
    }

    /**
     * Test generate_image returns WP_Error when AI unavailable
     */
    public function test_generate_image_unavailable() {
        // Assuming AI Engine is not available in test environment
        if (!$this->service->is_available()) {
            $result = $this->service->generate_image('Test image prompt');
            $this->assertInstanceOf('WP_Error', $result);
            $this->assertEquals('ai_unavailable', $result->get_error_code());
        } else {
            $this->markTestSkipped('AI Engine is available, cannot test unavailable scenario');
        }
    }

    /**
     * Test get_call_log returns array
     */
    public function test_get_call_log_returns_array() {
        $log = $this->service->get_call_log();
        $this->assertIsArray($log);
    }

    /**
     * Test get_call_log is initially empty
     */
    public function test_get_call_log_initially_empty() {
        $log = $this->service->get_call_log();
        $this->assertEmpty($log);
    }

    /**
     * Test clear_call_log empties the log
     */
    public function test_clear_call_log() {
        // Make a call to populate log (will fail but still log)
        $this->service->generate_text('Test');
        
        $log_before = $this->service->get_call_log();
        $this->assertNotEmpty($log_before);
        
        $this->service->clear_call_log();
        
        $log_after = $this->service->get_call_log();
        $this->assertEmpty($log_after);
    }

    /**
     * Test get_call_statistics returns correct structure
     */
    public function test_get_call_statistics_structure() {
        $stats = $this->service->get_call_statistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('successes', $stats);
        $this->assertArrayHasKey('failures', $stats);
        $this->assertArrayHasKey('by_type', $stats);
    }

    /**
     * Test call statistics are initially zero
     */
    public function test_call_statistics_initially_zero() {
        $stats = $this->service->get_call_statistics();
        
        $this->assertEquals(0, $stats['total']);
        $this->assertEquals(0, $stats['successes']);
        $this->assertEquals(0, $stats['failures']);
        $this->assertEmpty($stats['by_type']);
    }

    /**
     * Test call statistics track failures
     */
    public function test_call_statistics_track_failures() {
        // Make calls that will fail (AI unavailable)
        if (!$this->service->is_available()) {
            $this->service->generate_text('Test 1');
            $this->service->generate_text('Test 2');
            $this->service->generate_image('Image test');
            
            $stats = $this->service->get_call_statistics();
            
            $this->assertEquals(3, $stats['total']);
            $this->assertEquals(0, $stats['successes']);
            $this->assertEquals(3, $stats['failures']);
            $this->assertEquals(2, $stats['by_type']['text']);
            $this->assertEquals(1, $stats['by_type']['image']);
        } else {
            $this->markTestSkipped('AI Engine is available, cannot test failure scenario');
        }
    }

    /**
     * Test call log captures prompt
     */
    public function test_call_log_captures_prompt() {
        if (!$this->service->is_available()) {
            $prompt = 'Test prompt for logging';
            $this->service->generate_text($prompt);
            
            $log = $this->service->get_call_log();
            $this->assertCount(1, $log);
            $this->assertEquals($prompt, $log[0]['request']['prompt']);
        } else {
            $this->markTestSkipped('AI Engine is available, cannot test failure scenario');
        }
    }

    /**
     * Test call log captures type
     */
    public function test_call_log_captures_type() {
        if (!$this->service->is_available()) {
            $this->service->generate_text('Text prompt');
            $this->service->generate_image('Image prompt');
            
            $log = $this->service->get_call_log();
            $this->assertEquals('text', $log[0]['type']);
            $this->assertEquals('image', $log[1]['type']);
        } else {
            $this->markTestSkipped('AI Engine is available, cannot test failure scenario');
        }
    }

    /**
     * Test call log captures timestamp
     */
    public function test_call_log_captures_timestamp() {
        if (!$this->service->is_available()) {
            $this->service->generate_text('Test');
            
            $log = $this->service->get_call_log();
            $this->assertArrayHasKey('timestamp', $log[0]);
            $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $log[0]['timestamp']);
        } else {
            $this->markTestSkipped('AI Engine is available, cannot test failure scenario');
        }
    }

    /**
     * Test call log captures success status
     */
    public function test_call_log_captures_success_status() {
        if (!$this->service->is_available()) {
            $this->service->generate_text('Test');
            
            $log = $this->service->get_call_log();
            $this->assertFalse($log[0]['response']['success']);
        } else {
            $this->markTestSkipped('AI Engine is available, cannot test failure scenario');
        }
    }

    /**
     * Test call log captures error message
     */
    public function test_call_log_captures_error() {
        if (!$this->service->is_available()) {
            $this->service->generate_text('Test');
            
            $log = $this->service->get_call_log();
            $this->assertNotEmpty($log[0]['response']['error']);
        } else {
            $this->markTestSkipped('AI Engine is available, cannot test failure scenario');
        }
    }

    /**
     * Test generate_text accepts options
     */
    public function test_generate_text_accepts_options() {
        if (!$this->service->is_available()) {
            $options = array(
                'model' => 'gpt-4',
                'maxTokens' => 500,
                'temperature' => 0.8,
            );
            
            $result = $this->service->generate_text('Test', $options);
            
            // Should still fail but accept options
            $this->assertInstanceOf('WP_Error', $result);
            
            $log = $this->service->get_call_log();
            $this->assertEquals($options, $log[0]['request']['options']);
        } else {
            $this->markTestSkipped('AI Engine is available, cannot test failure scenario');
        }
    }

    /**
     * Test generate_text accepts context and advanced options
     */
    public function test_generate_text_accepts_context() {
        if (!$this->service->is_available()) {
            $options = array(
                'model' => 'gpt-4',
                'maxTokens' => 500,
                'temperature' => 0.6,
                'context' => 'These are supplemental instructions.',
                'instructions' => 'Always stay concise.',
                'env_id' => 'env-123',
                'max_results' => 1,
            );

            $result = $this->service->generate_text('Test with context', $options);

            // Should still fail but the options should be captured
            $this->assertInstanceOf('WP_Error', $result);

            $log = $this->service->get_call_log();
            $this->assertArrayHasKey('context', $log[0]['request']['options']);
            $this->assertEquals('These are supplemental instructions.', $log[0]['request']['options']['context']);
            $this->assertArrayHasKey('instructions', $log[0]['request']['options']);
            $this->assertEquals('Always stay concise.', $log[0]['request']['options']['instructions']);
        } else {
            $this->markTestSkipped('AI Engine is available, cannot test failure scenario');
        }
    }

    /**
     * Test multiple calls accumulate in log
     */
    public function test_multiple_calls_accumulate() {
        if (!$this->service->is_available()) {
            $this->service->generate_text('First');
            $this->service->generate_text('Second');
            $this->service->generate_text('Third');
            
            $log = $this->service->get_call_log();
            $this->assertCount(3, $log);
        } else {
            $this->markTestSkipped('AI Engine is available, cannot test failure scenario');
        }
    }

    // =========================================================
    // prepare_options normalization tests (via mocked AI Engine)
    // =========================================================

    /**
     * Helper: create a mock $mwai_core that captures the query passed to run_query().
     *
     * After the call $capture->query will hold the Meow_MWAI_Query_Text instance
     * so tests can assert on the properties set by apply_query_settings().
     *
     * @param stdClass $capture    Object whose `query` property is populated on call.
     * @param string   $return_val Text returned as $reply->result.
     * @return object Anonymous mock for $mwai_core.
     */
    private function make_core_engine_mock(stdClass $capture, $return_val = 'generated text') {
        $reply = new Meow_MWAI_Reply($return_val);
        return new class($capture, $reply) {
            private $capture;
            private $reply;
            public function __construct($capture, $reply) {
                $this->capture = $capture;
                $this->reply   = $reply;
            }
            public function run_query($query) {
                $this->capture->query = $query;
                return $this->reply;
            }
        };
    }

    /**
     * Helper: create a mock $mwai that captures simpleTextQuery params.
     *
     * Used to exercise the simpleTextQuery fallback path (when $mwai_core is null).
     *
     * @param stdClass $capture Object whose `params` property is populated on call.
     * @param string   $return_value Value returned by simpleTextQuery.
     * @return object Anonymous mock.
     */
    private function make_text_query_mock(stdClass $capture, $return_value = 'generated text') {
        return new class($capture, $return_value) {
            private $capture;
            private $return_value;
            public function __construct($capture, $return_value) {
                $this->capture      = $capture;
                $this->return_value = $return_value;
            }
            public function simpleTextQuery($prompt, $params) {
                $this->capture->prompt = $prompt;
                $this->capture->params = $params;
                return $this->return_value;
            }
        };
    }

    // ---------------------------------------------------------
    // Query-object path: verify setter calls on Meow_MWAI_Query_Text
    // ---------------------------------------------------------

    /**
     * Test that maxTokens is forwarded via set_max_tokens() on the query object.
     */
    public function test_query_object_max_tokens_set_via_setter() {
        global $mwai_core;
        $original = $mwai_core;

        $capture   = new stdClass();
        $mwai_core = $this->make_core_engine_mock($capture);

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt', array('maxTokens' => 4000));

            $this->assertNotInstanceOf('WP_Error', $result);
            $this->assertInstanceOf('Meow_MWAI_Query_Text', $capture->query);
            $this->assertSame(4000, $capture->query->max_tokens, 'set_max_tokens() should receive 4000.');
        } finally {
            $mwai_core = $original;
        }
    }

    /**
     * Test that legacy max_tokens key is normalized and forwarded via set_max_tokens().
     */
    public function test_query_object_legacy_max_tokens_normalized() {
        global $mwai_core;
        $original = $mwai_core;

        $capture   = new stdClass();
        $mwai_core = $this->make_core_engine_mock($capture);

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt', array('max_tokens' => 3000));

            $this->assertNotInstanceOf('WP_Error', $result);
            $this->assertSame(3000, $capture->query->max_tokens, 'Legacy max_tokens should be forwarded via set_max_tokens().');
        } finally {
            $mwai_core = $original;
        }
    }

    /**
     * Test that env_id (snake_case) is forwarded via set_env_id().
     */
    public function test_query_object_env_id_set_via_setter() {
        global $mwai_core;
        $original = $mwai_core;

        $capture   = new stdClass();
        $mwai_core = $this->make_core_engine_mock($capture);

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt', array('env_id' => 'env-abc'));

            $this->assertNotInstanceOf('WP_Error', $result);
            $this->assertSame('env-abc', $capture->query->env_id, 'env_id should be forwarded via set_env_id().');
        } finally {
            $mwai_core = $original;
        }
    }

    /**
     * Test that envId (camelCase) is normalised and forwarded via set_env_id().
     */
    public function test_query_object_envId_camel_case_normalized() {
        global $mwai_core;
        $original = $mwai_core;

        $capture   = new stdClass();
        $mwai_core = $this->make_core_engine_mock($capture);

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt', array('envId' => 'env-xyz'));

            $this->assertNotInstanceOf('WP_Error', $result);
            $this->assertSame('env-xyz', $capture->query->env_id, 'envId should be normalised to env_id via set_env_id().');
        } finally {
            $mwai_core = $original;
        }
    }

    /**
     * Test that model is forwarded via set_model().
     */
    public function test_query_object_model_set_via_setter() {
        global $mwai_core;
        $original = $mwai_core;

        $capture   = new stdClass();
        $mwai_core = $this->make_core_engine_mock($capture);

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt', array('model' => 'gpt-4o'));

            $this->assertNotInstanceOf('WP_Error', $result);
            $this->assertSame('gpt-4o', $capture->query->model, 'model should be forwarded via set_model().');
        } finally {
            $mwai_core = $original;
        }
    }

    /**
     * Test that temperature is forwarded via set_temperature().
     */
    public function test_query_object_temperature_set_via_setter() {
        global $mwai_core;
        $original = $mwai_core;

        $capture   = new stdClass();
        $mwai_core = $this->make_core_engine_mock($capture);

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt', array('temperature' => 0.3));

            $this->assertNotInstanceOf('WP_Error', $result);
            $this->assertSame(0.3, $capture->query->temperature, 'temperature should be forwarded via set_temperature().');
        } finally {
            $mwai_core = $original;
        }
    }

    /**
     * Test that max_results is forwarded via set_max_results().
     */
    public function test_query_object_max_results_set_via_setter() {
        global $mwai_core;
        $original = $mwai_core;

        $capture   = new stdClass();
        $mwai_core = $this->make_core_engine_mock($capture);

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt', array('max_results' => 3));

            $this->assertNotInstanceOf('WP_Error', $result);
            $this->assertSame(3, $capture->query->max_results, 'max_results should be forwarded via set_max_results().');
        } finally {
            $mwai_core = $original;
        }
    }

    /**
     * Test that context is forwarded via set_context().
     */
    public function test_query_object_context_set_via_setter() {
        global $mwai_core;
        $original = $mwai_core;

        $capture   = new stdClass();
        $mwai_core = $this->make_core_engine_mock($capture);

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt', array('context' => 'Extra context here.'));

            $this->assertNotInstanceOf('WP_Error', $result);
            $this->assertSame('Extra context here.', $capture->query->context, 'context should be forwarded via set_context().');
        } finally {
            $mwai_core = $original;
        }
    }

    /**
     * Test that instructions are forwarded via set_instructions().
     */
    public function test_query_object_instructions_set_via_setter() {
        global $mwai_core;
        $original = $mwai_core;

        $capture   = new stdClass();
        $mwai_core = $this->make_core_engine_mock($capture);

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt', array('instructions' => 'Be concise.'));

            $this->assertNotInstanceOf('WP_Error', $result);
            $this->assertSame('Be concise.', $capture->query->instructions, 'instructions should be forwarded via set_instructions().');
        } finally {
            $mwai_core = $original;
        }
    }

    /**
     * Test that the default maxTokens of 2000 is applied when not supplied.
     */
    public function test_query_object_default_max_tokens_applied() {
        global $mwai_core;
        $original = $mwai_core;

        $capture   = new stdClass();
        $mwai_core = $this->make_core_engine_mock($capture);

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt');

            $this->assertNotInstanceOf('WP_Error', $result);
            $this->assertSame(2000, $capture->query->max_tokens, 'Default max_tokens of 2000 should be applied via set_max_tokens().');
        } finally {
            $mwai_core = $original;
        }
    }

    /**
     * Test that the prompt is passed as the query message.
     */
    public function test_query_object_prompt_passed_as_message() {
        global $mwai_core;
        $original = $mwai_core;

        $capture   = new stdClass();
        $mwai_core = $this->make_core_engine_mock($capture);

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Hello world prompt');

            $this->assertNotInstanceOf('WP_Error', $result);
            $this->assertSame('Hello world prompt', $capture->query->message, 'Prompt should be passed to Meow_MWAI_Query_Text constructor.');
        } finally {
            $mwai_core = $original;
        }
    }

    /**
     * Test that is_available() returns true when $mwai_core is set (even if $mwai is null).
     */
    public function test_is_available_via_core_engine() {
        global $mwai_core;
        $original = $mwai_core;

        $capture   = new stdClass();
        $mwai_core = $this->make_core_engine_mock($capture);

        try {
            $service = new AIPS_AI_Service();
            $this->assertTrue($service->is_available(), 'is_available() should return true when $mwai_core is set.');
        } finally {
            $mwai_core = $original;
        }
    }

    // ---------------------------------------------------------
    // simpleTextQuery fallback path tests (when $mwai_core is null)
    // ---------------------------------------------------------

    /**
     * Test that maxTokens passed directly overrides the built-in default of 2000
     * via the simpleTextQuery fallback path.
     */
    public function test_prepare_options_maxTokens_overrides_default() {
        global $mwai, $mwai_core;
        $original_mwai      = $mwai;
        $original_mwai_core = $mwai_core;

        $capture   = new stdClass();
        $capture->params = null;
        $mwai      = $this->make_text_query_mock($capture);
        $mwai_core = null;

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt', array('maxTokens' => 5000));

            $this->assertNotInstanceOf('WP_Error', $result, 'Expected successful generation, got WP_Error.');
            $this->assertSame(5000, $capture->params['maxTokens'], 'maxTokens should override the 2000 default.');
        } finally {
            $mwai      = $original_mwai;
            $mwai_core = $original_mwai_core;
        }
    }

    /**
     * Test that legacy max_tokens is normalized to maxTokens and forwarded to the engine
     * via the simpleTextQuery fallback path.
     */
    public function test_prepare_options_legacy_max_tokens_accepted() {
        global $mwai, $mwai_core;
        $original_mwai      = $mwai;
        $original_mwai_core = $mwai_core;

        $capture   = new stdClass();
        $capture->params = null;
        $mwai      = $this->make_text_query_mock($capture);
        $mwai_core = null;

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt', array('max_tokens' => 3000));

            $this->assertNotInstanceOf('WP_Error', $result, 'Expected successful generation, got WP_Error.');
            $this->assertSame(3000, $capture->params['maxTokens'], 'Legacy max_tokens should be normalized to maxTokens.');
        } finally {
            $mwai      = $original_mwai;
            $mwai_core = $original_mwai_core;
        }
    }

    /**
     * Test that legacy env_id is normalized to envId and forwarded to the engine
     * via the simpleTextQuery fallback path.
     */
    public function test_prepare_options_legacy_env_id_accepted() {
        global $mwai, $mwai_core;
        $original_mwai      = $mwai;
        $original_mwai_core = $mwai_core;

        $capture   = new stdClass();
        $capture->params = null;
        $mwai      = $this->make_text_query_mock($capture);
        $mwai_core = null;

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt', array('env_id' => 'legacy-env'));

            $this->assertNotInstanceOf('WP_Error', $result, 'Expected successful generation, got WP_Error.');
            $this->assertSame('legacy-env', $capture->params['envId'], 'Legacy env_id should be normalized to envId.');
        } finally {
            $mwai      = $original_mwai;
            $mwai_core = $original_mwai_core;
        }
    }

    /**
     * Test that envId passed directly is forwarded to the engine unchanged
     * via the simpleTextQuery fallback path.
     */
    public function test_prepare_options_envId_direct_accepted() {
        global $mwai, $mwai_core;
        $original_mwai      = $mwai;
        $original_mwai_core = $mwai_core;

        $capture   = new stdClass();
        $capture->params = null;
        $mwai      = $this->make_text_query_mock($capture);
        $mwai_core = null;

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt', array('envId' => 'direct-env'));

            $this->assertNotInstanceOf('WP_Error', $result, 'Expected successful generation, got WP_Error.');
            $this->assertSame('direct-env', $capture->params['envId'], 'envId should be forwarded as-is.');
        } finally {
            $mwai      = $original_mwai;
            $mwai_core = $original_mwai_core;
        }
    }

    /**
     * Test that the default maxTokens of 2000 is used when no token option is supplied
     * via the simpleTextQuery fallback path.
     */
    public function test_prepare_options_default_maxTokens_used_when_not_specified() {
        global $mwai, $mwai_core;
        $original_mwai      = $mwai;
        $original_mwai_core = $mwai_core;

        $capture   = new stdClass();
        $capture->params = null;
        $mwai      = $this->make_text_query_mock($capture);
        $mwai_core = null;

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt');

            $this->assertNotInstanceOf('WP_Error', $result, 'Expected successful generation, got WP_Error.');
            $this->assertSame(2000, $capture->params['maxTokens'], 'Default maxTokens should be 2000.');
        } finally {
            $mwai      = $original_mwai;
            $mwai_core = $original_mwai_core;
        }
    }

    /**
     * Test that model is forwarded to the engine when provided
     * via the simpleTextQuery fallback path.
     */
    public function test_prepare_options_model_forwarded_to_engine() {
        global $mwai, $mwai_core;
        $original_mwai      = $mwai;
        $original_mwai_core = $mwai_core;

        $capture   = new stdClass();
        $capture->params = null;
        $mwai      = $this->make_text_query_mock($capture);
        $mwai_core = null;

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt', array('model' => 'gpt-4'));

            $this->assertNotInstanceOf('WP_Error', $result, 'Expected successful generation, got WP_Error.');
            $this->assertSame('gpt-4', $capture->params['model'], 'model should be forwarded to the engine.');
        } finally {
            $mwai      = $original_mwai;
            $mwai_core = $original_mwai_core;
        }
    }

    /**
     * Test generate_json with mocked simpleJsonQuery success path
     */
    public function test_generate_json_with_simpleJsonQuery_success() {
        // This test validates the simpleJsonQuery success path by mocking $mwai
        global $mwai, $mwai_core;
        
        // Save original state
        $original_mwai      = $mwai;
        $original_mwai_core = $mwai_core;
        
        // Mock $mwai with simpleJsonQuery method (simpleTextQuery not needed here)
        $mwai = new class {
            public function simpleJsonQuery($prompt, $options) {
                // Return mock JSON data
                return array(
                    array('title' => 'Topic 1', 'score' => 85, 'keywords' => array('key1', 'key2')),
                    array('title' => 'Topic 2', 'score' => 90, 'keywords' => array('key3', 'key4')),
                );
            }
        };
        // Force simpleJsonQuery path (not the query-object path)
        $mwai_core = null;
        
        try {
            $service = new AIPS_AI_Service();
            $result = $service->generate_json('Test prompt');
            
            // Should succeed with array result
            $this->assertIsArray($result);
            $this->assertCount(2, $result);
            $this->assertEquals('Topic 1', $result[0]['title']);
            $this->assertEquals(85, $result[0]['score']);
            
            // Should be logged as 'json' type
            $log = $service->get_call_log();
            $this->assertCount(1, $log);
            $this->assertEquals('json', $log[0]['type']);
            
        } finally {
            // Restore original state
            $mwai      = $original_mwai;
            $mwai_core = $original_mwai_core;
        }
    }
}
