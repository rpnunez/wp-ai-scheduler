<?php
/**
 * Test Simplified AIPS_History_Container::record() Method
 *
 * Tests for the refactored record() method with simplified signature.
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 * @since 2.1.0
 */

class Test_History_Container_Simplified extends WP_UnitTestCase {

	private $history_service;
	private $history_repository;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->history_repository = new AIPS_History_Repository();
		$this->history_service = new AIPS_History_Service($this->history_repository);
	}

	/**
	 * Test record() with simplified signature - only context.
	 */
	public function test_record_with_only_context() {
		$container = $this->history_service->create('test_process', array(
			'test_id' => 123,
		));

		$log_id = $container->record(
			'info',
			'Test message',
			array('key' => 'value', 'component' => 'test')
		);

		$this->assertNotFalse($log_id, 'record() should return a log ID');
		$this->assertIsInt($log_id, 'Log ID should be an integer');

		// Verify the log was created
		$logs = $this->history_repository->get_logs_by_history_id($container->get_id());
		$this->assertCount(1, $logs, 'Should have one log entry');
		
		$log = $logs[0];
		$details = json_decode($log->details, true);
		$this->assertEquals('Test message', $details['message']);
		$this->assertEquals('value', $details['context']['key']);
		$this->assertEquals('test', $details['context']['component']);
	}

	/**
	 * Test record() with input data in context.
	 */
	public function test_record_with_input_in_context() {
		$container = $this->history_service->create('test_process', array());

		$log_id = $container->record(
			'ai_request',
			'AI request sent',
			array(
				'input' => array(
					'prompt' => 'Test prompt',
					'options' => array('temperature' => 0.7),
				),
				'component' => 'ai',
			)
		);

		$this->assertNotFalse($log_id);

		// Verify input was stored correctly
		$logs = $this->history_repository->get_logs_by_history_id($container->get_id());
		$log = $logs[0];
		$details = json_decode($log->details, true);
		
		$this->assertArrayHasKey('input', $details);
		$this->assertEquals('Test prompt', $details['input']['prompt']);
		$this->assertEquals(0.7, $details['input']['options']['temperature']);
		$this->assertEquals('ai', $details['context']['component']);
	}

	/**
	 * Test record() with output data in context.
	 */
	public function test_record_with_output_in_context() {
		$container = $this->history_service->create('test_process', array());

		$log_id = $container->record(
			'ai_response',
			'AI response received',
			array(
				'output' => 'Generated content here',
				'component' => 'ai',
			)
		);

		$this->assertNotFalse($log_id);

		// Verify output was stored correctly
		$logs = $this->history_repository->get_logs_by_history_id($container->get_id());
		$log = $logs[0];
		$details = json_decode($log->details, true);
		
		$this->assertArrayHasKey('output', $details);
		$this->assertEquals('Generated content here', $details['output']['value']);
	}

	/**
	 * Test record() with both input and output in context.
	 */
	public function test_record_with_input_and_output() {
		$container = $this->history_service->create('test_process', array());

		$log_id = $container->record(
			'activity',
			'Process completed',
			array(
				'input' => array('source_id' => 456),
				'output' => array('result_id' => 789),
				'component' => 'processor',
				'duration_ms' => 1234,
			)
		);

		$this->assertNotFalse($log_id);

		// Verify both input and output were stored
		$logs = $this->history_repository->get_logs_by_history_id($container->get_id());
		$log = $logs[0];
		$details = json_decode($log->details, true);
		
		$this->assertArrayHasKey('input', $details);
		$this->assertArrayHasKey('output', $details);
		$this->assertEquals(456, $details['input']['source_id']);
		$this->assertEquals(789, $details['output']['result_id']);
		$this->assertEquals('processor', $details['context']['component']);
		$this->assertEquals(1234, $details['context']['duration_ms']);
	}

	/**
	 * Test record() with large output gets base64 encoded.
	 */
	public function test_record_with_large_output() {
		$container = $this->history_service->create('test_process', array());

		// Create a string larger than 500 characters
		$large_output = str_repeat('This is a test output. ', 50); // ~1150 characters

		$log_id = $container->record(
			'ai_response',
			'Large AI response',
			array(
				'output' => $large_output,
			)
		);

		$this->assertNotFalse($log_id);

		// Verify output was base64 encoded
		$logs = $this->history_repository->get_logs_by_history_id($container->get_id());
		$log = $logs[0];
		$details = json_decode($log->details, true);
		
		$this->assertArrayHasKey('output', $details);
		$this->assertArrayHasKey('output_encoded', $details);
		$this->assertTrue($details['output_encoded']);
		
		// Verify we can decode it back
		$decoded = base64_decode($details['output']);
		$this->assertEquals($large_output, $decoded);
	}

	/**
	 * Test record_error() helper method.
	 */
	public function test_record_error_helper() {
		$container = $this->history_service->create('test_process', array());

		$log_id = $container->record_error(
			'Test error occurred',
			array(
				'error_code' => 'TEST_ERROR',
				'component' => 'tester',
			)
		);

		$this->assertNotFalse($log_id);

		// Verify error context was added
		$logs = $this->history_repository->get_logs_by_history_id($container->get_id());
		$log = $logs[0];
		$details = json_decode($log->details, true);
		
		$this->assertEquals('error', $log->log_type);
		$this->assertEquals('Test error occurred', $details['message']);
		$this->assertArrayHasKey('timestamp', $details['context']);
		$this->assertArrayHasKey('memory_usage', $details['context']);
		$this->assertEquals('TEST_ERROR', $details['context']['error_code']);
	}

	/**
	 * Test record_user_action() helper method.
	 */
	public function test_record_user_action_helper() {
		// Create a test user
		$user_id = $this->factory->user->create(array('user_login' => 'testuser'));
		wp_set_current_user($user_id);

		$container = $this->history_service->create('test_process', array());

		$log_id = $container->record_user_action(
			'manual_test',
			'User performed test action',
			array('test_data' => 'value')
		);

		$this->assertNotFalse($log_id);

		// Verify user context was added
		$logs = $this->history_repository->get_logs_by_history_id($container->get_id());
		$log = $logs[0];
		$details = json_decode($log->details, true);
		
		$this->assertEquals('activity', $log->log_type);
		$this->assertEquals($user_id, $details['context']['user_id']);
		$this->assertEquals('testuser', $details['context']['user_login']);
		$this->assertEquals('manual_ui', $details['context']['source']);
		$this->assertEquals('value', $details['context']['test_data']);
	}

	/**
	 * Test that PHP error_log is called when WP_DEBUG is enabled.
	 */
	public function test_record_writes_to_error_log_when_debug_enabled() {
		if (!defined('WP_DEBUG')) {
			define('WP_DEBUG', true);
		}

		$container = $this->history_service->create('test_process', array());

		// We can't easily test error_log output in PHPUnit, but we can verify
		// the method completes without errors when WP_DEBUG is true
		$log_id = $container->record(
			'debug',
			'Debug message',
			array('debug_data' => 'test')
		);

		$this->assertNotFalse($log_id);
	}
}
