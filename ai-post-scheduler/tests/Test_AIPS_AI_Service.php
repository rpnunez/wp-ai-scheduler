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
        remove_all_filters('aips_ability_provider');
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
     * Helper: create a mock $mwai that captures simpleTextQuery params.
     *
     * Returns an object that, after the call, exposes `params` and `prompt`
     * via its public `capture` stdClass property.
     *
     * @param stdClass $capture Object whose `params` property is populated on call.
     * @param string   $return  Value returned by simpleTextQuery.
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

    /**
     * Test that maxTokens passed directly overrides the built-in default of 2000.
     */
    public function test_prepare_options_maxTokens_overrides_default() {
        global $mwai;
        $original_mwai = $mwai;

        $capture = new stdClass();
        $capture->params = null;
        $mwai = $this->make_text_query_mock($capture);

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt', array('maxTokens' => 5000));

            $this->assertNotInstanceOf('WP_Error', $result, 'Expected successful generation, got WP_Error.');
            $this->assertSame(5000, $capture->params['maxTokens'], 'maxTokens should override the 2000 default.');
        } finally {
            $mwai = $original_mwai;
        }
    }

    /**
     * Test that legacy max_tokens is normalized to maxTokens and forwarded to the engine.
     */
    public function test_prepare_options_legacy_max_tokens_accepted() {
        global $mwai;
        $original_mwai = $mwai;

        $capture = new stdClass();
        $capture->params = null;
        $mwai = $this->make_text_query_mock($capture);

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt', array('max_tokens' => 3000));

            $this->assertNotInstanceOf('WP_Error', $result, 'Expected successful generation, got WP_Error.');
            $this->assertSame(3000, $capture->params['maxTokens'], 'Legacy max_tokens should be normalized to maxTokens.');
        } finally {
            $mwai = $original_mwai;
        }
    }

    /**
     * Test that legacy env_id is normalized to envId and forwarded to the engine.
     */
    public function test_prepare_options_legacy_env_id_accepted() {
        global $mwai;
        $original_mwai = $mwai;

        $capture = new stdClass();
        $capture->params = null;
        $mwai = $this->make_text_query_mock($capture);

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt', array('env_id' => 'legacy-env'));

            $this->assertNotInstanceOf('WP_Error', $result, 'Expected successful generation, got WP_Error.');
            $this->assertSame('legacy-env', $capture->params['envId'], 'Legacy env_id should be normalized to envId.');
        } finally {
            $mwai = $original_mwai;
        }
    }

    /**
     * Test that envId passed directly is forwarded to the engine unchanged.
     */
    public function test_prepare_options_envId_direct_accepted() {
        global $mwai;
        $original_mwai = $mwai;

        $capture = new stdClass();
        $capture->params = null;
        $mwai = $this->make_text_query_mock($capture);

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt', array('envId' => 'direct-env'));

            $this->assertNotInstanceOf('WP_Error', $result, 'Expected successful generation, got WP_Error.');
            $this->assertSame('direct-env', $capture->params['envId'], 'envId should be forwarded as-is.');
        } finally {
            $mwai = $original_mwai;
        }
    }

    /**
     * Test that maxTokens is dynamically calculated when no token option is supplied.
     *
     * With no explicit maxTokens and no request_type, the 'content' type sizing is used.
     * Calculation: (prompt_tokens + output_tokens) + 25% buffer, capped at aips_max_tokens_limit (16000).
     * For a prompt of "Prompt" (6 chars): prompt_tokens = ceil(6/4) = 2; output_tokens = 4000 (content);
     * base_total = 4002; buffer = ceil(4002 * 0.25) = ceil(1000.5) = 1001; result = 4002 + 1001 = 5003.
     */
    public function test_prepare_options_default_maxTokens_used_when_not_specified() {
        global $mwai;
        $original_mwai = $mwai;

        $capture = new stdClass();
        $capture->params = null;
        $mwai = $this->make_text_query_mock($capture);

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt');

            $this->assertNotInstanceOf('WP_Error', $result, 'Expected successful generation, got WP_Error.');
            $this->assertArrayHasKey('maxTokens', $capture->params, 'maxTokens must always be set in params.');
            $this->assertIsInt($capture->params['maxTokens'], 'maxTokens must be an integer.');
            $this->assertGreaterThan(0, $capture->params['maxTokens'], 'maxTokens must be a positive integer.');
            // "Prompt" = 6 chars → prompt_tokens = ceil(6/4) = 2; content output_tokens from setting (default 4000);
            // base_total = 2 + output_tokens; buffer = ceil(base_total * 0.25); result = base_total + buffer.
            $prompt_tokens = (int) ceil(strlen('Prompt') / 4); // 2
            $output_tokens = (int) get_option('aips_max_tokens_content', 4000);
            $base_total    = $prompt_tokens + $output_tokens;
            $buffer        = (int) ceil($base_total * 0.25);
            $expected      = $base_total + $buffer;
            $this->assertSame($expected, $capture->params['maxTokens'], 'Dynamic maxTokens should include prompt size in calculation.');
        } finally {
            $mwai = $original_mwai;
        }
    }

    /**
     * Test that title request_type produces title-sized maxTokens.
     */
    public function test_calculate_max_tokens_title_type() {
        global $mwai;
        $original_mwai = $mwai;

        $capture = new stdClass();
        $capture->params = null;
        $mwai = $this->make_text_query_mock($capture);

        $prompt = 'Generate a title for this article.';

        try {
            $service = new AIPS_AI_Service();
            $service->generate_text($prompt, array('request_type' => 'title'));

            $prompt_tokens = (int) ceil(strlen($prompt) / 4);
            $output_tokens = (int) get_option('aips_max_tokens_title', 150);
            $base_total    = $prompt_tokens + $output_tokens;
            $buffer        = (int) ceil($base_total * 0.25);
            $expected      = $base_total + $buffer;

            $this->assertSame($expected, $capture->params['maxTokens'], 'Title request_type should produce title-sized maxTokens.');
        } finally {
            $mwai = $original_mwai;
        }
    }

    /**
     * Test that excerpt request_type produces excerpt-sized maxTokens.
     */
    public function test_calculate_max_tokens_excerpt_type() {
        global $mwai;
        $original_mwai = $mwai;

        $capture = new stdClass();
        $capture->params = null;
        $mwai = $this->make_text_query_mock($capture);

        $prompt = 'Write a short excerpt for this article.';

        try {
            $service = new AIPS_AI_Service();
            $service->generate_text($prompt, array('request_type' => 'excerpt'));

            $prompt_tokens = (int) ceil(strlen($prompt) / 4);
            $output_tokens = (int) get_option('aips_max_tokens_excerpt', 300);
            $base_total    = $prompt_tokens + $output_tokens;
            $buffer        = (int) ceil($base_total * 0.25);
            $expected      = $base_total + $buffer;

            $this->assertSame($expected, $capture->params['maxTokens'], 'Excerpt request_type should produce excerpt-sized maxTokens.');
        } finally {
            $mwai = $original_mwai;
        }
    }

    /**
     * Test that setting aips_max_tokens_title overrides the default title budget.
     */
    public function test_calculate_max_tokens_title_custom_setting() {
        global $mwai;
        $original_mwai = $mwai;

        $capture = new stdClass();
        $capture->params = null;
        $mwai = $this->make_text_query_mock($capture);

        $original = get_option('aips_max_tokens_title');
        update_option('aips_max_tokens_title', 500);

        $prompt = 'Generate a title.';

        try {
            $service = new AIPS_AI_Service();
            $service->generate_text($prompt, array('request_type' => 'title'));

            $prompt_tokens = (int) ceil(strlen($prompt) / 4);
            $base_total    = $prompt_tokens + 500;
            $buffer        = (int) ceil($base_total * 0.25);
            $expected      = $base_total + $buffer;

            $this->assertSame($expected, $capture->params['maxTokens'], 'Custom aips_max_tokens_title should override the default title budget.');
        } finally {
            $mwai = $original_mwai;
            if ($original === false) {
                delete_option('aips_max_tokens_title');
            } else {
                update_option('aips_max_tokens_title', $original);
            }
        }
    }

    /**
     * Test that setting aips_max_tokens_excerpt overrides the default excerpt budget.
     */
    public function test_calculate_max_tokens_excerpt_custom_setting() {
        global $mwai;
        $original_mwai = $mwai;

        $capture = new stdClass();
        $capture->params = null;
        $mwai = $this->make_text_query_mock($capture);

        $original = get_option('aips_max_tokens_excerpt');
        update_option('aips_max_tokens_excerpt', 800);

        $prompt = 'Write an excerpt.';

        try {
            $service = new AIPS_AI_Service();
            $service->generate_text($prompt, array('request_type' => 'excerpt'));

            $prompt_tokens = (int) ceil(strlen($prompt) / 4);
            $base_total    = $prompt_tokens + 800;
            $buffer        = (int) ceil($base_total * 0.25);
            $expected      = $base_total + $buffer;

            $this->assertSame($expected, $capture->params['maxTokens'], 'Custom aips_max_tokens_excerpt should override the default excerpt budget.');
        } finally {
            $mwai = $original_mwai;
            if ($original === false) {
                delete_option('aips_max_tokens_excerpt');
            } else {
                update_option('aips_max_tokens_excerpt', $original);
            }
        }
    }

    /**
     * Test that setting aips_max_tokens_content overrides the default content budget.
     */
    public function test_calculate_max_tokens_content_custom_setting() {
        global $mwai;
        $original_mwai = $mwai;

        $capture = new stdClass();
        $capture->params = null;
        $mwai = $this->make_text_query_mock($capture);

        $original = get_option('aips_max_tokens_content');
        update_option('aips_max_tokens_content', 8000);

        $prompt = 'Write a full article.';

        try {
            $service = new AIPS_AI_Service();
            $service->generate_text($prompt, array('request_type' => 'content'));

            $prompt_tokens = (int) ceil(strlen($prompt) / 4);
            $base_total    = $prompt_tokens + 8000;
            $buffer        = (int) ceil($base_total * 0.25);
            $expected      = $base_total + $buffer;

            $this->assertSame($expected, $capture->params['maxTokens'], 'Custom aips_max_tokens_content should override the default content budget.');
        } finally {
            $mwai = $original_mwai;
            if ($original === false) {
                delete_option('aips_max_tokens_content');
            } else {
                update_option('aips_max_tokens_content', $original);
            }
        }
    }

    /**
     * Test that a zero or empty per-type token option is clamped to 1 so
     * maxTokens is always at least prompt-sized (never unexpectedly tiny).
     */
    public function test_calculate_max_tokens_zero_content_setting_clamped_to_one() {
        global $mwai;
        $original_mwai = $mwai;

        $capture = new stdClass();
        $capture->params = null;
        $mwai = $this->make_text_query_mock($capture);

        $original = get_option('aips_max_tokens_content');
        update_option('aips_max_tokens_content', 0);

        $prompt = 'Write a full article.';

        try {
            $service = new AIPS_AI_Service();
            $service->generate_text($prompt, array('request_type' => 'content'));

            // output_tokens clamped to 1; expected = (prompt_tokens + 1) + 25% buffer.
            $prompt_tokens = (int) ceil(strlen($prompt) / 4);
            $base_total    = $prompt_tokens + 1;
            $buffer        = (int) ceil($base_total * 0.25);
            $expected      = $base_total + $buffer;

            $this->assertGreaterThan(0, $capture->params['maxTokens'], 'maxTokens must be positive even when per-type setting is 0.');
            $this->assertSame($expected, $capture->params['maxTokens'], 'Zero content setting should be clamped to 1 for the output token budget.');
        } finally {
            $mwai = $original_mwai;
            if ($original === false) {
                delete_option('aips_max_tokens_content');
            } else {
                update_option('aips_max_tokens_content', $original);
            }
        }
    }

    /**
     * Test that the aips_max_tokens_limit cap is respected.
     */
    public function test_calculate_max_tokens_respects_limit() {
        global $mwai;
        $original_mwai = $mwai;

        $capture = new stdClass();
        $capture->params = null;
        $mwai = $this->make_text_query_mock($capture);

        // Temporarily set a very small limit so the calculated value exceeds it.
        $original_limit = get_option('aips_max_tokens_limit');
        update_option('aips_max_tokens_limit', 100);

        try {
            $service = new AIPS_AI_Service();
            $service->generate_text('Some prompt', array('request_type' => 'content'));

            $this->assertSame(100, $capture->params['maxTokens'], 'maxTokens should be capped at aips_max_tokens_limit.');
        } finally {
            $mwai = $original_mwai;
            if ($original_limit === false) {
                delete_option('aips_max_tokens_limit');
            } else {
                update_option('aips_max_tokens_limit', $original_limit);
            }
        }
    }

    /**
     * Test that model is forwarded to the engine when provided.
     */
    public function test_prepare_options_model_forwarded_to_engine() {
        global $mwai;
        $original_mwai = $mwai;

        $capture = new stdClass();
        $capture->params = null;
        $mwai = $this->make_text_query_mock($capture);

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt', array('model' => 'gpt-4'));

            $this->assertNotInstanceOf('WP_Error', $result, 'Expected successful generation, got WP_Error.');
            $this->assertSame('gpt-4', $capture->params['model'], 'model should be forwarded to the engine.');
        } finally {
            $mwai = $original_mwai;
        }
    }

    /**
     * Test generate_json with mocked simpleJsonQuery success path
     */
    public function test_generate_json_with_simpleJsonQuery_success() {
        // This test validates the simpleJsonQuery success path by mocking $mwai
        global $mwai;
        
        // Save original state
        $original_mwai = $mwai;
        
        // Mock $mwai with simpleJsonQuery method
        $mwai = new class {
            public function simpleJsonQuery($prompt, $options) {
                // Return mock JSON data
                return array(
                    array('title' => 'Topic 1', 'score' => 85, 'keywords' => array('key1', 'key2')),
                    array('title' => 'Topic 2', 'score' => 90, 'keywords' => array('key3', 'key4')),
                );
            }
        };
        
        // Also need to mock AI Engine availability
        global $mwai_core;
        $original_core = $mwai_core;
        if (!$mwai_core) {
            $mwai_core = new stdClass();
        }
        
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
            $mwai = $original_mwai;
            $mwai_core = $original_core;
        }
    }

    // -------------------------------------------------------------------
    // Ability-fallback path (generate_with_ability())
    //
    // These tests deliberately construct their own AIPS_AI_Service with a
    // *fresh* AIPS_Ability_Service passed as the 4th constructor arg rather
    // than relying on $this->service or the DI container:
    //
    //  - AIPS_AI_Service::get_ability_service() only accepts an object that
    //    is `instanceof AIPS_Ability_Service` for its constructor-injected
    //    4th arg; a duck-typed double would be silently discarded.
    //  - AIPS_Container's bound AIPS_Ability_Service is a process-wide
    //    singleton, and AIPS_Ability_Service::get_provider() caches its
    //    resolved provider on first success and never re-checks the
    //    aips_ability_provider filter afterward — reusing the container
    //    singleton across tests would leak one test's fake provider into
    //    every later test in the same run.
    //
    // A fresh, explicitly-passed AIPS_Ability_Service instance per test
    // avoids both hazards. The aips_ability_provider filter registered by
    // register_fake_ability_provider() is removed in tearDown() above.
    // -------------------------------------------------------------------

    /**
     * Force AIPS_AI_Service::get_ai_engine() to report unavailable without
     * depending on the implicit absence of global $mwai in the test
     * bootstrap. Setting the private $ai_engine property to `false` (not
     * `null`) permanently pins it, since get_ai_engine() only re-fetches
     * from the global when the cached value is strictly null.
     *
     * @param AIPS_AI_Service $service Service instance to pin.
     * @return void
     */
    private function force_ai_engine_unavailable(AIPS_AI_Service $service) {
        $reflection = new ReflectionClass($service);
        $prop = $reflection->getProperty('ai_engine');
        $prop->setAccessible(true);
        $prop->setValue($service, false);
    }

    /**
     * Register a fake ability provider via the aips_ability_provider filter.
     *
     * @param callable $list   Callable returning the raw ability list.
     * @param callable $invoke Callable(slug, payload, options) returning the raw invoke response.
     * @return void
     */
    private function register_fake_ability_provider($list, $invoke) {
        add_filter('aips_ability_provider', function () use ($list, $invoke) {
            return array(
                'name'   => 'test-provider',
                'list'   => $list,
                'invoke' => $invoke,
            );
        });
    }

    public function test_generate_text_falls_back_to_ability_when_ai_engine_unavailable() {
        $this->register_fake_ability_provider(
            function () {
                return array('aips_generate_text' => array('slug' => 'aips_generate_text'));
            },
            function ($slug, $payload, $options) {
                return array('content' => 'Generated text');
            }
        );

        $service = new AIPS_AI_Service(null, null, null, new AIPS_Ability_Service());
        $this->force_ai_engine_unavailable($service);

        $result = $service->generate_text('Test prompt');

        $this->assertSame('Generated text', $result);
    }

    public function test_generate_json_falls_back_to_ability_when_ai_engine_unavailable() {
        $this->register_fake_ability_provider(
            function () {
                return array('aips_generate_json' => array('slug' => 'aips_generate_json'));
            },
            function ($slug, $payload, $options) {
                return array('data' => array('title' => 'Generated title'));
            }
        );

        $service = new AIPS_AI_Service(null, null, null, new AIPS_Ability_Service());
        $this->force_ai_engine_unavailable($service);

        $result = $service->generate_json('Test prompt');

        $this->assertIsArray($result);
        $this->assertSame('Generated title', $result['title']);
    }

    public function test_generate_image_falls_back_to_ability_when_ai_engine_unavailable() {
        $this->register_fake_ability_provider(
            function () {
                return array('aips_generate_image' => array('slug' => 'aips_generate_image'));
            },
            function ($slug, $payload, $options) {
                return array('url' => 'https://example.com/image.png');
            }
        );

        $service = new AIPS_AI_Service(null, null, null, new AIPS_Ability_Service());
        $this->force_ai_engine_unavailable($service);

        $result = $service->generate_image('Test image prompt');

        $this->assertSame('https://example.com/image.png', $result);
    }

    public function test_generate_json_from_text_falls_back_to_ability_when_unavailable() {
        $this->register_fake_ability_provider(
            function () {
                return array('aips_generate_json' => array('slug' => 'aips_generate_json'));
            },
            function ($slug, $payload, $options) {
                return array('data' => array('title' => 'From text fallback'));
            }
        );

        $service = new AIPS_AI_Service(null, null, null, new AIPS_Ability_Service());
        $this->force_ai_engine_unavailable($service);

        $result = $service->generate_json_from_text('Test prompt');

        $this->assertIsArray($result);
        $this->assertSame('From text fallback', $result['title']);
    }

    public function test_ai_unavailable_error_includes_ability_error_data() {
        $this->register_fake_ability_provider(
            function () {
                return array('aips_generate_text' => array('slug' => 'aips_generate_text'));
            },
            function ($slug, $payload, $options) {
                return new WP_Error('ability_invocation_failed', 'Ability blew up');
            }
        );

        $service = new AIPS_AI_Service(null, null, null, new AIPS_Ability_Service());
        $this->force_ai_engine_unavailable($service);

        $result = $service->generate_text('Test prompt');

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('ai_unavailable', $result->get_error_code());

        $data = $result->get_error_data();
        $this->assertArrayHasKey('ability_error', $data);
        $this->assertInstanceOf('WP_Error', $data['ability_error']);
        $this->assertEquals('ability_invocation_failed', $data['ability_error']->get_error_code());
    }

    public function test_generate_with_ability_respects_ability_slug_option_override() {
        $this->register_fake_ability_provider(
            function () {
                return array('my_custom_slug' => array('slug' => 'my_custom_slug'));
            },
            function ($slug, $payload, $options) {
                if ('my_custom_slug' !== $slug) {
                    return new WP_Error('unexpected_slug', 'Wrong slug invoked: ' . $slug);
                }
                return array('content' => 'Custom slug content');
            }
        );

        $service = new AIPS_AI_Service(null, null, null, new AIPS_Ability_Service());
        $this->force_ai_engine_unavailable($service);

        $result = $service->generate_text('Test prompt', array('ability_slug' => 'my_custom_slug'));

        $this->assertSame('Custom slug content', $result);
    }

    public function test_generate_with_ability_skips_unavailable_slug_and_tries_next() {
        // Only the second candidate slug for 'text' ('generate_text') is advertised;
        // the first ('aips_generate_text') is absent from the list, so is_available()
        // should be false for it and generate_with_ability() should move on.
        $this->register_fake_ability_provider(
            function () {
                return array('generate_text' => array('slug' => 'generate_text'));
            },
            function ($slug, $payload, $options) {
                return array('content' => 'Second candidate content');
            }
        );

        $service = new AIPS_AI_Service(null, null, null, new AIPS_Ability_Service());
        $this->force_ai_engine_unavailable($service);

        $result = $service->generate_text('Test prompt');

        $this->assertSame('Second candidate content', $result);
    }

    /**
     * Documents current behavior (not a bug fix): generate_with_ability()
     * stops at the first candidate slug whose is_available() is true and
     * returns whatever invoke() gives back, even a WP_Error — it does not
     * fall through to try a later candidate slug for the same type just
     * because invoke() itself failed. If this should instead try every
     * available candidate before giving up, that is a behavior change that
     * belongs on the codex/create-ability-service-adapter branch, not here.
     */
    public function test_generate_with_ability_stops_on_first_invoke_error() {
        $this->register_fake_ability_provider(
            function () {
                return array(
                    'aips_generate_text' => array('slug' => 'aips_generate_text'),
                    'generate_text'      => array('slug' => 'generate_text'),
                );
            },
            function ($slug, $payload, $options) {
                // Both candidates are "available", but the first one invoked fails.
                return new WP_Error('ability_invocation_failed', 'First candidate failed: ' . $slug);
            }
        );

        $service = new AIPS_AI_Service(null, null, null, new AIPS_Ability_Service());
        $this->force_ai_engine_unavailable($service);

        $result = $service->generate_text('Test prompt');

        $this->assertInstanceOf('WP_Error', $result);
        $data = $result->get_error_data();
        $this->assertArrayHasKey('ability_error', $data);
        $this->assertEquals('ability_invocation_failed', $data['ability_error']->get_error_code());
    }

    public function test_ai_unavailable_when_no_ability_provider_registered() {
        // No aips_ability_provider filter registered — the existing implicit
        // "no provider available at all" scenario, now asserted explicitly
        // rather than incidentally.
        $service = new AIPS_AI_Service(null, null, null, new AIPS_Ability_Service());
        $this->force_ai_engine_unavailable($service);

        $result = $service->generate_text('Test prompt');

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('ai_unavailable', $result->get_error_code());
    }
}
