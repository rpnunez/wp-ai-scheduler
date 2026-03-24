<?php
/**
 * Tests for Session To JSON Converter
 *
 * @package AI_Post_Scheduler
 */

class Test_Session_To_JSON extends WP_UnitTestCase {
	
	private $converter;
	private $history_repository;
	
	public function setUp(): void {
		parent::setUp();
		$this->history_repository = new AIPS_History_Repository();
		$this->converter = new AIPS_Session_To_JSON();
	}
	
	public function tearDown(): void {
		parent::tearDown();
	}
	
	/**
	 * Test that the converter can be instantiated
	 */
	public function test_converter_instantiation() {
		$this->assertInstanceOf('AIPS_Session_To_JSON', $this->converter);
	}
	
	/**
	 * Test that converter returns error for non-existent history ID
	 */
	public function test_generate_session_json_invalid_id() {
		$result = $this->converter->generate_session_json(999999);
		
		$this->assertInstanceOf('WP_Error', $result);
		$this->assertEquals('not_found', $result->get_error_code());
	}
	
	/**
	 * Test that converter generates JSON for a valid history entry
	 */
	public function test_generate_session_json_valid_entry() {
		// Create a test post
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Test Generated Post',
			'post_content' => 'This is test content.',
			'post_status' => 'publish',
		));
		
		// Create a history entry
		$history_id = $this->history_repository->create(array(
			'template_id' => 1,
			'status' => 'completed',
			'post_id' => $post_id,
			'generated_title' => 'Test Generated Post',
			'generated_content' => 'This is test content.',
		));
		
		$this->assertNotFalse($history_id);
		
		// Add some log entries
		$this->history_repository->add_log_entry(
			$history_id,
			'title_request',
			array('prompt' => 'Generate a title', 'options' => array()),
			AIPS_History_Type::AI_REQUEST
		);
		
		$this->history_repository->add_log_entry(
			$history_id,
			'title_response',
			array('output' => 'Test Generated Post'),
			AIPS_History_Type::AI_RESPONSE
		);
		
		// Generate session JSON
		$session_data = $this->converter->generate_session_json($history_id);
		
		// Verify structure
		$this->assertIsArray($session_data);
		$this->assertArrayHasKey('metadata', $session_data);
		$this->assertArrayHasKey('post_id', $session_data);
		$this->assertArrayHasKey('wp_post', $session_data);
		$this->assertArrayHasKey('history', $session_data);
		$this->assertArrayHasKey('history_containers', $session_data);
		
		// Verify metadata
		$this->assertArrayHasKey('generated_at', $session_data['metadata']);
		$this->assertArrayHasKey('version', $session_data['metadata']);
		
		// Verify post ID
		$this->assertEquals($post_id, $session_data['post_id']);
		
		// Verify WP_Post data
		$this->assertIsArray($session_data['wp_post']);
		$this->assertEquals($post_id, $session_data['wp_post']['ID']);
		$this->assertEquals('Test Generated Post', $session_data['wp_post']['post_title']);
		
		// Verify history
		$this->assertIsArray($session_data['history']);
		$this->assertEquals($history_id, $session_data['history']['id']);
		$this->assertEquals('completed', $session_data['history']['status']);
		
		// Verify containers
		$this->assertIsArray($session_data['history_containers']);
		$this->assertNotEmpty($session_data['history_containers']);
		
		// Verify first container structure
		$container = $session_data['history_containers'][0];
		$this->assertArrayHasKey('uuid', $container);
		$this->assertArrayHasKey('type', $container);
		$this->assertArrayHasKey('logs', $container);
		$this->assertArrayHasKey('statistics', $container);
		
		// Verify logs in container
		$this->assertIsArray($container['logs']);
		$this->assertCount(2, $container['logs']);
		
		// Verify statistics
		$this->assertArrayHasKey('total_logs', $container['statistics']);
		$this->assertArrayHasKey('ai_requests', $container['statistics']);
		$this->assertArrayHasKey('ai_responses', $container['statistics']);
		$this->assertEquals(2, $container['statistics']['total_logs']);
		$this->assertEquals(1, $container['statistics']['ai_requests']);
		$this->assertEquals(1, $container['statistics']['ai_responses']);
	}
	
	/**
	 * Test that JSON string generation works
	 */
	public function test_generate_json_string() {
		// Create a test post
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Test Post for JSON',
			'post_content' => 'Content here.',
		));
		
		// Create a history entry
		$history_id = $this->history_repository->create(array(
			'template_id' => 1,
			'status' => 'completed',
			'post_id' => $post_id,
			'generated_title' => 'Test Post for JSON',
		));
		
		// Generate JSON string
		$json_string = $this->converter->generate_json_string($history_id, true);
		
		// Verify it's a string
		$this->assertIsString($json_string);
		
		// Verify it's valid JSON
		$decoded = json_decode($json_string, true);
		$this->assertIsArray($decoded);
		$this->assertArrayHasKey('metadata', $decoded);
		$this->assertArrayHasKey('wp_post', $decoded);
	}
	
	/**
	 * Test that session without post returns null for wp_post
	 */
	public function test_session_without_post() {
		// Create a history entry without a post_id
		$history_id = $this->history_repository->create(array(
			'template_id' => 1,
			'status' => 'failed',
			'generated_title' => 'Failed Generation',
		));
		
		// Generate session JSON
		$session_data = $this->converter->generate_session_json($history_id);
		
		// Verify wp_post is null
		$this->assertNull($session_data['wp_post']);
		$this->assertNull($session_data['post_id']);
	}
	
	/**
	 * Test that base64 encoded logs are decoded
	 */
	public function test_base64_log_decoding() {
		// Create a history entry
		$history_id = $this->history_repository->create(array(
			'template_id' => 1,
			'status' => 'completed',
			'generated_title' => 'Test Post',
		));
		
		// Add log with base64 encoded content
		$encoded_content = base64_encode('This is encoded content');
		$this->history_repository->add_log_entry(
			$history_id,
			'content_response',
			array(
				'output' => $encoded_content,
				'output_encoded' => true,
			),
			AIPS_History_Type::AI_RESPONSE
		);
		
		// Generate session JSON
		$session_data = $this->converter->generate_session_json($history_id);
		
		// Verify log was decoded
		$logs = $session_data['history_containers'][0]['logs'];
		$this->assertCount(1, $logs);
		
		$log = $logs[0];
		$this->assertArrayHasKey('details', $log);
		$this->assertArrayHasKey('output', $log['details']);
		
		// The output should be decoded, not base64
		$this->assertEquals('This is encoded content', $log['details']['output']);
		$this->assertArrayNotHasKey('output_encoded', $log['details']);
	}
	
	/**
	 * Test that malformed JSON in log details is handled gracefully
	 */
	public function test_malformed_json_handling() {
		// Create a history entry
		$history_id = $this->history_repository->create(array(
			'template_id' => 1,
			'status' => 'completed',
			'generated_title' => 'Test Post',
		));
		
		// Manually insert a log with malformed JSON
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'aips_history_log',
			array(
				'history_id' => $history_id,
				'log_type' => 'malformed_test',
				'history_type_id' => AIPS_History_Type::LOG,
				'details' => '{invalid json here}', // Malformed JSON
			),
			array('%d', '%s', '%d', '%s')
		);
		
		// Generate session JSON - should not throw error
		$session_data = $this->converter->generate_session_json($history_id);
		
		// Verify the log entry exists with error information
		$logs = $session_data['history_containers'][0]['logs'];
		$this->assertCount(1, $logs);
		
		$log = $logs[0];
		$this->assertArrayHasKey('details', $log);
		
		// Should have error information
		$this->assertArrayHasKey('error', $log['details']);
		$this->assertArrayHasKey('json_error', $log['details']);
		$this->assertArrayHasKey('raw_details', $log['details']);
		$this->assertEquals('{invalid json here}', $log['details']['raw_details']);
	}
	
	/**
	 * Test that malformed base64 is handled gracefully
	 */
	public function test_malformed_base64_handling() {
		// Create a history entry
		$history_id = $this->history_repository->create(array(
			'template_id' => 1,
			'status' => 'completed',
			'generated_title' => 'Test Post',
		));
		
		// Add log with malformed base64
		$this->history_repository->add_log_entry(
			$history_id,
			'content_response',
			array(
				'output' => 'not-valid-base64!!!', // Invalid base64
				'output_encoded' => true,
			),
			AIPS_History_Type::AI_RESPONSE
		);
		
		// Generate session JSON - should not throw error
		$session_data = $this->converter->generate_session_json($history_id);
		
		// Verify log exists
		$logs = $session_data['history_containers'][0]['logs'];
		$this->assertCount(1, $logs);
		
		$log = $logs[0];
		$this->assertArrayHasKey('details', $log);
		$this->assertArrayHasKey('output', $log['details']);
		
		// Should keep original value and flag the decode error
		$this->assertEquals('not-valid-base64!!!', $log['details']['output']);
		$this->assertArrayHasKey('output_decode_error', $log['details']);
		$this->assertTrue($log['details']['output_decode_error']);
	}
}
