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
                'max_tokens' => 500,
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
                'max_tokens' => 500,
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
     * Test that max_tokens passed directly overrides the calculated default.
     */
    public function test_prepare_options_max_tokens_overrides_default() {
        global $mwai;
        $original_mwai = $mwai;

        $capture = new stdClass();
        $capture->params = null;
        $mwai = $this->make_text_query_mock($capture);

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt', array('max_tokens' => 5000));

            $this->assertNotInstanceOf('WP_Error', $result, 'Expected successful generation, got WP_Error.');
            $this->assertSame(5000, $capture->params['maxTokens'], 'The Meow adapter should receive the max_tokens override.');
        } finally {
            $mwai = $original_mwai;
        }
    }

    /**
     * Test that canonical max_tokens is translated for Meow AI Engine.
     */
    public function test_prepare_options_max_tokens_translated_for_meow() {
        global $mwai;
        $original_mwai = $mwai;

        $capture = new stdClass();
        $capture->params = null;
        $mwai = $this->make_text_query_mock($capture);

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt', array('max_tokens' => 3000));

            $this->assertNotInstanceOf('WP_Error', $result, 'Expected successful generation, got WP_Error.');
            $this->assertSame(3000, $capture->params['maxTokens'], 'The Meow adapter should translate max_tokens to its native key.');
        } finally {
            $mwai = $original_mwai;
        }
    }

    /**
     * Test that canonical env_id is translated for Meow AI Engine.
     */
    public function test_prepare_options_env_id_translated_for_meow() {
        global $mwai;
        $original_mwai = $mwai;

        $capture = new stdClass();
        $capture->params = null;
        $mwai = $this->make_text_query_mock($capture);

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt', array('env_id' => 'configured-env'));

            $this->assertNotInstanceOf('WP_Error', $result, 'Expected successful generation, got WP_Error.');
            $this->assertSame('configured-env', $capture->params['envId'], 'The Meow adapter should translate env_id to its native key.');
        } finally {
            $mwai = $original_mwai;
        }
    }

    /**
     * Test that max_tokens is dynamically calculated when no token option is supplied.
     *
     * With no explicit max_tokens and no request_type, the 'content' type sizing is used.
     * Calculation: output_tokens + 25% buffer, capped at aips_max_tokens_limit (16000).
     * max_tokens is an output-only cap, so the prompt
     * length is not part of it: output_tokens = 4000 (content); result = 5000.
     */
    public function test_prepare_options_default_max_tokens_used_when_not_specified() {
        global $mwai;
        $original_mwai = $mwai;

        $capture = new stdClass();
        $capture->params = null;
        $mwai = $this->make_text_query_mock($capture);

        try {
            $service = new AIPS_AI_Service();
            $result  = $service->generate_text('Prompt');

            $this->assertNotInstanceOf('WP_Error', $result, 'Expected successful generation, got WP_Error.');
            $this->assertArrayHasKey('maxTokens', $capture->params, 'The Meow-native token parameter must always be set.');
            $this->assertIsInt($capture->params['maxTokens'], 'The Meow-native token parameter must be an integer.');
            $this->assertGreaterThan(0, $capture->params['maxTokens'], 'The Meow-native token parameter must be positive.');
            // max_tokens is an output-only cap, so the prompt length is not a factor:
            // result = output_tokens + ceil(output_tokens * 0.25).
            $output_tokens = (int) get_option('aips_max_tokens_content', 4000);
            $buffer        = (int) ceil($output_tokens * 0.25);
            $expected      = $output_tokens + $buffer;
            $this->assertSame($expected, $capture->params['maxTokens'], 'Dynamic max_tokens should size the output budget only.');
        } finally {
            $mwai = $original_mwai;
        }
    }

    /**
     * Test that title request_type produces title-sized max_tokens.
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

            $output_tokens = (int) get_option('aips_max_tokens_title', 150);
            $expected      = max(1200, $output_tokens + (int) ceil($output_tokens * 0.25));
            $limit         = (int) get_option('aips_max_tokens_limit', 16000);

            if ($limit > 0) {
                $expected = min($expected, $limit);
            }

            $this->assertSame($expected, $capture->params['maxTokens'], 'Title requests should reserve enough output headroom for reasoning-capable models.');
        } finally {
            $mwai = $original_mwai;
        }
    }

    /**
     * Test that excerpt request_type produces excerpt-sized max_tokens.
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

            $output_tokens = (int) get_option('aips_max_tokens_excerpt', 300);
            $expected      = max(1200, $output_tokens + (int) ceil($output_tokens * 0.25));
            $limit         = (int) get_option('aips_max_tokens_limit', 16000);

            if ($limit > 0) {
                $expected = min($expected, $limit);
            }

            $this->assertSame($expected, $capture->params['maxTokens'], 'Excerpt requests should reserve enough output headroom for reasoning-capable models.');
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

            $this->assertSame(1200, $capture->params['maxTokens'], 'A small custom title budget should retain the short-form reasoning reserve.');
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

            $this->assertSame(1200, $capture->params['maxTokens'], 'A small custom excerpt budget should retain the short-form reasoning reserve.');
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

            $output_tokens = 8000;
            $buffer        = (int) ceil($output_tokens * 0.25);
            $expected      = $output_tokens + $buffer;

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
     * max_tokens is always a positive integer (never zero or negative).
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

            // output_tokens clamped to 1; expected = 1 + 25% buffer.
            $output_tokens = 1;
            $buffer        = (int) ceil($output_tokens * 0.25);
            $expected      = $output_tokens + $buffer;

            $this->assertGreaterThan(0, $capture->params['maxTokens'], 'max_tokens must be positive even when per-type setting is 0.');
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
            $service->generate_text('Some prompt', array('request_type' => 'title'));

            $this->assertSame(100, $capture->params['maxTokens'], 'The global max_tokens limit should cap the short-form reasoning reserve.');
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
}
