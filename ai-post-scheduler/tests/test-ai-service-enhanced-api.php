<?php
/**
 * Test case for AI Service Enhanced API
 *
 * Tests the enhanced AI Engine API features including set_instructions,
 * set_context, and set_env_id support.
 *
 * @package AI_Post_Scheduler
 * @since 1.6.0
 */

class Test_AIPS_AI_Service_Enhanced_API extends WP_UnitTestCase {

    private $service;

    public function setUp(): void {
        parent::setUp();
        $this->service = new AIPS_AI_Service();
    }

    public function tearDown(): void {
        $this->service->clear_call_log();
        parent::tearDown();
    }

    /**
     * Test that generate_text accepts instructions option
     */
    public function test_generate_text_accepts_instructions_option() {
        if (!$this->service->is_available()) {
            $options = array(
                'instructions' => 'Write in a formal tone.',
                'max_tokens' => 500,
            );
            
            $result = $this->service->generate_text('Test prompt', $options);
            
            // Should still fail (AI unavailable) but accept options
            $this->assertInstanceOf('WP_Error', $result);
            
            $log = $this->service->get_call_log();
            $this->assertCount(1, $log);
            $this->assertEquals('Write in a formal tone.', $log[0]['request']['options']['instructions']);
        } else {
            $this->markTestSkipped('AI Engine is available, cannot test failure scenario');
        }
    }

    /**
     * Test that generate_text accepts context option
     */
    public function test_generate_text_accepts_context_option() {
        if (!$this->service->is_available()) {
            $options = array(
                'context' => 'This is background information about the topic.',
                'max_tokens' => 500,
            );
            
            $result = $this->service->generate_text('Test prompt', $options);
            
            // Should still fail (AI unavailable) but accept options
            $this->assertInstanceOf('WP_Error', $result);
            
            $log = $this->service->get_call_log();
            $this->assertCount(1, $log);
            $this->assertEquals('This is background information about the topic.', $log[0]['request']['options']['context']);
        } else {
            $this->markTestSkipped('AI Engine is available, cannot test failure scenario');
        }
    }

    /**
     * Test that generate_text accepts env_id option
     */
    public function test_generate_text_accepts_env_id_option() {
        if (!$this->service->is_available()) {
            $options = array(
                'env_id' => 'test_env_123',
                'max_tokens' => 500,
            );
            
            $result = $this->service->generate_text('Test prompt', $options);
            
            // Should still fail (AI unavailable) but accept options
            $this->assertInstanceOf('WP_Error', $result);
            
            $log = $this->service->get_call_log();
            $this->assertCount(1, $log);
            $this->assertEquals('test_env_123', $log[0]['request']['options']['env_id']);
        } else {
            $this->markTestSkipped('AI Engine is available, cannot test failure scenario');
        }
    }

    /**
     * Test that all enhanced options can be passed together
     */
    public function test_generate_text_accepts_all_enhanced_options() {
        if (!$this->service->is_available()) {
            $options = array(
                'instructions' => 'Write in a formal tone.',
                'context' => 'Background information.',
                'env_id' => 'test_env_123',
                'model' => 'gpt-4',
                'max_tokens' => 1000,
                'temperature' => 0.5,
            );
            
            $result = $this->service->generate_text('Test prompt', $options);
            
            $this->assertInstanceOf('WP_Error', $result);
            
            $log = $this->service->get_call_log();
            $this->assertCount(1, $log);
            
            $logged_options = $log[0]['request']['options'];
            $this->assertEquals('Write in a formal tone.', $logged_options['instructions']);
            $this->assertEquals('Background information.', $logged_options['context']);
            $this->assertEquals('test_env_123', $logged_options['env_id']);
            $this->assertEquals('gpt-4', $logged_options['model']);
            $this->assertEquals(1000, $logged_options['max_tokens']);
            $this->assertEquals(0.5, $logged_options['temperature']);
        } else {
            $this->markTestSkipped('AI Engine is available, cannot test failure scenario');
        }
    }

    /**
     * Test that empty enhanced options default correctly
     */
    public function test_enhanced_options_have_defaults() {
        if (!$this->service->is_available()) {
            $result = $this->service->generate_text('Test prompt', array());
            
            $this->assertInstanceOf('WP_Error', $result);
            
            $log = $this->service->get_call_log();
            $this->assertCount(1, $log);
            
            $logged_options = $log[0]['request']['options'];
            
            // Verify defaults are set
            $this->assertArrayHasKey('instructions', $logged_options);
            $this->assertArrayHasKey('context', $logged_options);
            $this->assertArrayHasKey('env_id', $logged_options);
            
            // Empty string defaults
            $this->assertEquals('', $logged_options['instructions']);
            $this->assertEquals('', $logged_options['context']);
            $this->assertEquals('', $logged_options['env_id']);
        } else {
            $this->markTestSkipped('AI Engine is available, cannot test failure scenario');
        }
    }
}
