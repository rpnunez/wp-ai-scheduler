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
    
    /**
     * Test chatbot method returns WP_Error when AI unavailable
     */
    public function test_generate_with_chatbot_unavailable() {
        if (!$this->service->is_available()) {
            $result = $this->service->generate_with_chatbot('default', 'Test message');
            $this->assertInstanceOf('WP_Error', $result);
            $this->assertEquals('ai_unavailable', $result->get_error_code());
        } else {
            $this->markTestSkipped('AI Engine is available, cannot test unavailable scenario');
        }
    }
    
    /**
     * Test chatbot method accepts chatId for continuing conversation
     */
    public function test_generate_with_chatbot_accepts_chat_id() {
        if (!$this->service->is_available()) {
            $options = array('chatId' => 'test-chat-123');
            $result = $this->service->generate_with_chatbot('default', 'Test message', $options);
            
            // Should still fail but options should be captured
            $this->assertInstanceOf('WP_Error', $result);
            
            $log = $this->service->get_call_log();
            $this->assertNotEmpty($log);
            $last_log = end($log);
            $this->assertArrayHasKey('chatId', $last_log['request']['options']);
            $this->assertEquals('test-chat-123', $last_log['request']['options']['chatId']);
        } else {
            $this->markTestSkipped('AI Engine is available, cannot test failure scenario');
        }
    }
    
    /**
     * Test chatbot logs type correctly
     */
    public function test_generate_with_chatbot_logs_type() {
        if (!$this->service->is_available()) {
            $this->service->generate_with_chatbot('default', 'Test', array(), 'content');
            
            $log = $this->service->get_call_log();
            $this->assertNotEmpty($log);
            $last_log = end($log);
            $this->assertEquals('content', $last_log['type']);
        } else {
            $this->markTestSkipped('AI Engine is available, cannot test failure scenario');
        }
    }
    
    /**
     * Test chatbot increments call statistics
     */
    public function test_generate_with_chatbot_increments_statistics() {
        if (!$this->service->is_available()) {
            $this->service->clear_call_log();
            $this->service->generate_with_chatbot('default', 'First message');
            $this->service->generate_with_chatbot('default', 'Second message');
            
            $stats = $this->service->get_call_statistics();
            $this->assertEquals(2, $stats['total']);
            $this->assertEquals(2, $stats['failures']);
        } else {
            $this->markTestSkipped('AI Engine is available, cannot test failure scenario');
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
            public function simpleJsonQuery($prompt, $options = array()) {
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

    /**
     * Test fallback_json_generation extracts JSON from ```json code blocks
     */
    public function test_fallback_json_extraction_from_json_fenced_block() {
        // Use reflection to test the private method
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('fallback_json_generation');
        $method->setAccessible(true);
        
        // Mock generate_text to return JSON in a ```json fence
        $mock_service = $this->getMockBuilder('AIPS_AI_Service')
            ->onlyMethods(array('generate_text'))
            ->getMock();
        
        $json_data = array('title' => 'Test', 'content' => 'Data');
        $response = "Here's the JSON you requested:\n\n```json\n" . json_encode($json_data) . "\n```\n\nHope that helps!";
        
        $mock_service->method('generate_text')->willReturn($response);
        
        $result = $method->invoke($mock_service, 'Test prompt');
        
        $this->assertIsArray($result);
        $this->assertEquals('Test', $result['title']);
        $this->assertEquals('Data', $result['content']);
    }

    /**
     * Test fallback_json_generation extracts JSON from generic code blocks
     */
    public function test_fallback_json_extraction_from_generic_code_block() {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('fallback_json_generation');
        $method->setAccessible(true);
        
        $mock_service = $this->getMockBuilder('AIPS_AI_Service')
            ->onlyMethods(array('generate_text'))
            ->getMock();
        
        $json_data = array('status' => 'ok', 'value' => 42);
        $response = "Here's your data:\n```\n" . json_encode($json_data) . "\n```";
        
        $mock_service->method('generate_text')->willReturn($response);
        
        $result = $method->invoke($mock_service, 'Test prompt');
        
        $this->assertIsArray($result);
        $this->assertEquals('ok', $result['status']);
        $this->assertEquals(42, $result['value']);
    }

    /**
     * Test fallback_json_generation extracts JSON surrounded by text
     */
    public function test_fallback_json_extraction_from_plain_text() {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('fallback_json_generation');
        $method->setAccessible(true);
        
        $mock_service = $this->getMockBuilder('AIPS_AI_Service')
            ->onlyMethods(array('generate_text'))
            ->getMock();
        
        $json_data = array('name' => 'Test User', 'email' => 'test@example.com');
        $response = "Sure! Here is the JSON data: " . json_encode($json_data) . " - let me know if you need anything else.";
        
        $mock_service->method('generate_text')->willReturn($response);
        
        $result = $method->invoke($mock_service, 'Test prompt');
        
        $this->assertIsArray($result);
        $this->assertEquals('Test User', $result['name']);
        $this->assertEquals('test@example.com', $result['email']);
    }

    /**
     * Test fallback_json_generation prefers json-tagged blocks over non-json blocks
     */
    public function test_fallback_json_extraction_prefers_json_tagged_blocks() {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('fallback_json_generation');
        $method->setAccessible(true);
        
        $mock_service = $this->getMockBuilder('AIPS_AI_Service')
            ->onlyMethods(array('generate_text'))
            ->getMock();
        
        // Response contains a bash code block first, then a json block
        $bash_code = 'curl -X POST http://example.com';
        $json_data = array('api' => 'v1', 'endpoint' => '/data');
        $response = "First, run this command:\n```bash\n{$bash_code}\n```\n\nThen use this JSON:\n```json\n" . json_encode($json_data) . "\n```";
        
        $mock_service->method('generate_text')->willReturn($response);
        
        $result = $method->invoke($mock_service, 'Test prompt');
        
        // Should extract the JSON block, not the bash block
        $this->assertIsArray($result);
        $this->assertEquals('v1', $result['api']);
        $this->assertEquals('/data', $result['endpoint']);
    }

    /**
     * Test fallback_json_generation tries multiple non-json code blocks
     */
    public function test_fallback_json_extraction_tries_multiple_code_blocks() {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('fallback_json_generation');
        $method->setAccessible(true);
        
        $mock_service = $this->getMockBuilder('AIPS_AI_Service')
            ->onlyMethods(array('generate_text'))
            ->getMock();
        
        // Response has a bash block, then a generic block with valid JSON
        $bash_code = 'npm install package';
        $json_data = array('package' => 'test', 'version' => '1.0.0');
        $response = "Install it:\n```bash\n{$bash_code}\n```\n\nConfig:\n```\n" . json_encode($json_data) . "\n```";
        
        $mock_service->method('generate_text')->willReturn($response);
        
        $result = $method->invoke($mock_service, 'Test prompt');
        
        // Should find the valid JSON in the second block
        $this->assertIsArray($result);
        $this->assertEquals('test', $result['package']);
        $this->assertEquals('1.0.0', $result['version']);
    }

    /**
     * Test fallback_json_generation handles invalid JSON gracefully
     */
    public function test_fallback_json_extraction_handles_invalid_json() {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('fallback_json_generation');
        $method->setAccessible(true);
        
        $mock_service = $this->getMockBuilder('AIPS_AI_Service')
            ->onlyMethods(array('generate_text'))
            ->getMock();
        
        $response = "Here's some invalid JSON: {invalid json data}";
        
        $mock_service->method('generate_text')->willReturn($response);
        
        $result = $method->invoke($mock_service, 'Test prompt');
        
        // Should return WP_Error
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('json_parse_error', $result->get_error_code());
    }

    /**
     * Test fallback_json_generation handles non-array JSON
     */
    public function test_fallback_json_extraction_rejects_non_array() {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('fallback_json_generation');
        $method->setAccessible(true);
        
        $mock_service = $this->getMockBuilder('AIPS_AI_Service')
            ->onlyMethods(array('generate_text'))
            ->getMock();
        
        // Valid JSON but not an array
        $response = '"just a string"';
        
        $mock_service->method('generate_text')->willReturn($response);
        
        $result = $method->invoke($mock_service, 'Test prompt');
        
        // Should return WP_Error for invalid format
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_json_format', $result->get_error_code());
    }
}
