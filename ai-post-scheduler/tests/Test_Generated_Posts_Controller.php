<?php
/**
 * Tests for Generated Posts Controller
 *
 * @package AI_Post_Scheduler
 */

class Test_Generated_Posts_Controller extends WP_UnitTestCase {
	
	private $controller;
	private $history_repository;
	
	public function setUp(): void {
		parent::setUp();
		$this->history_repository = new AIPS_History_Repository();
		$this->controller = new AIPS_Generated_Posts_Controller();
	}
	
	public function tearDown(): void {
		parent::tearDown();
	}
	
	/**
	 * Test that the controller can be instantiated
	 */
	public function test_controller_instantiation() {
		$this->assertInstanceOf('AIPS_Generated_Posts_Controller', $this->controller);
	}
	
	/**
	 * Test that history type constants are defined correctly
	 */
	public function test_history_type_constants() {
		$this->assertEquals(1, AIPS_History_Type::LOG);
		$this->assertEquals(2, AIPS_History_Type::ERROR);
		$this->assertEquals(3, AIPS_History_Type::WARNING);
		$this->assertEquals(5, AIPS_History_Type::AI_REQUEST);
		$this->assertEquals(6, AIPS_History_Type::AI_RESPONSE);
	}
	
	/**
	 * Test that history type labels are returned correctly
	 */
	public function test_history_type_labels() {
		$this->assertEquals('Log', AIPS_History_Type::get_label(AIPS_History_Type::LOG));
		$this->assertEquals('Error', AIPS_History_Type::get_label(AIPS_History_Type::ERROR));
		$this->assertEquals('AI Request', AIPS_History_Type::get_label(AIPS_History_Type::AI_REQUEST));
		$this->assertEquals('AI Response', AIPS_History_Type::get_label(AIPS_History_Type::AI_RESPONSE));
	}
	
	/**
	 * Test that all history types can be retrieved
	 */
	public function test_get_all_types() {
		$types = AIPS_History_Type::get_all_types();
		$this->assertIsArray($types);
		$this->assertArrayHasKey(AIPS_History_Type::LOG, $types);
		$this->assertArrayHasKey(AIPS_History_Type::ERROR, $types);
		$this->assertArrayHasKey(AIPS_History_Type::AI_REQUEST, $types);
		$this->assertArrayHasKey(AIPS_History_Type::AI_RESPONSE, $types);
	}
	
	/**
	 * Test that activity type check works correctly
	 */
	public function test_is_activity_type() {
		$this->assertTrue(AIPS_History_Type::is_activity_type(AIPS_History_Type::ACTIVITY));
		$this->assertTrue(AIPS_History_Type::is_activity_type(AIPS_History_Type::ERROR));
		$this->assertFalse(AIPS_History_Type::is_activity_type(AIPS_History_Type::LOG));
		$this->assertFalse(AIPS_History_Type::is_activity_type(AIPS_History_Type::AI_REQUEST));
	}
	
	/**
	 * Test that log entries can be added with history types
	 */
	public function test_add_log_entry_with_history_type() {
		// Create a history entry first
		$history_id = $this->history_repository->create(array(
			'template_id' => 1,
			'status' => 'processing',
			'generated_title' => 'Test Post',
		));
		
		$this->assertNotFalse($history_id);
		
		// Add a log entry with AI_REQUEST type
		$log_id = $this->history_repository->add_log_entry(
			$history_id,
			'title_request',
			array('prompt' => 'Generate a title', 'options' => array()),
			AIPS_History_Type::AI_REQUEST
		);
		
		$this->assertNotFalse($log_id);
		
		// Add a log entry with AI_RESPONSE type
		$log_id2 = $this->history_repository->add_log_entry(
			$history_id,
			'title_response',
			array('response' => base64_encode('Test Title Generated')),
			AIPS_History_Type::AI_RESPONSE
		);
		
		$this->assertNotFalse($log_id2);
		
		// Retrieve and verify
		$history_item = $this->history_repository->get_by_id($history_id);
		$this->assertNotNull($history_item);
		$this->assertIsArray($history_item->log);
		$this->assertCount(2, $history_item->log);
		
		// Verify history_type_id is set
		$this->assertEquals(AIPS_History_Type::AI_REQUEST, $history_item->log[0]->history_type_id);
		$this->assertEquals(AIPS_History_Type::AI_RESPONSE, $history_item->log[1]->history_type_id);
	}
}
