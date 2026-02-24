<?php
/**
 * Test Authors AJAX Workflow.
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

if (!function_exists('wp_get_current_user')) {
	function wp_get_current_user() {
		$user = new stdClass();
		$user->ID = 1;
		$user->display_name = 'Test User';
		$user->user_login = 'testuser';
		$user->user_email = 'test@example.com';
		return $user;
	}
}

class AIPS_Authors_Ajax_Workflow_Test extends WP_UnitTestCase {

	private $authors_controller;
	private $topics_controller;
	private $mock_scheduler;
	private $mock_post_generator;
	private $mock_authors_repo;
	private $mock_topics_repo;
	private $mock_penalty_service;

	public function setUp(): void {
		parent::setUp();

		// Create mock scheduler
		$this->mock_scheduler = $this->getMockBuilder('AIPS_Author_Topics_Scheduler')
			->onlyMethods(['generate_now'])
			->getMock();

		// Create mock post generator
		$this->mock_post_generator = $this->getMockBuilder('AIPS_Author_Post_Generator')
			->onlyMethods(['generate_now'])
			->getMock();

		// Create mock repositories
		$this->mock_authors_repo = $this->getMockBuilder('AIPS_Authors_Repository')
			->onlyMethods(['create', 'get_by_id'])
			->getMock();

		$this->mock_topics_repo = $this->getMockBuilder('AIPS_Author_Topics_Repository')
			->onlyMethods(['create', 'get_by_id', 'update_status'])
			->getMock();

		// Create mock penalty service
		$this->mock_penalty_service = $this->getMockBuilder('AIPS_Topic_Penalty_Service')
			->onlyMethods(['apply_reward', 'apply_penalty'])
			->getMock();

		// Instantiate controllers
		$this->authors_controller = new AIPS_Authors_Controller();
		$this->topics_controller = new AIPS_Author_Topics_Controller();

		// Inject mocks using Reflection
		$this->inject_property($this->authors_controller, 'topics_scheduler', $this->mock_scheduler);
		$this->inject_property($this->authors_controller, 'repository', $this->mock_authors_repo);

		$this->inject_property($this->topics_controller, 'post_generator', $this->mock_post_generator);
		$this->inject_property($this->topics_controller, 'repository', $this->mock_topics_repo);
		$this->inject_property($this->topics_controller, 'penalty_service', $this->mock_penalty_service);

		// Mock current user
		$user_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($user_id);
	}

	public function tearDown(): void {
		parent::tearDown();
		$_POST = array();
	}

	private function inject_property($object, $property_name, $value) {
		$reflection = new ReflectionClass($object);
		$property = $reflection->getProperty($property_name);
		$property->setAccessible(true);
		$property->setValue($object, $value);
	}

	public function test_create_author() {
		$_POST = array();
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['name'] = 'Test Author';
		$_POST['field_niche'] = 'Software Testing';
		$_POST['is_active'] = '1';
		$_REQUEST = $_POST;

		$this->mock_authors_repo->expects($this->once())
			->method('create')
			->willReturn(101);

		ob_start();
		try {
			$this->authors_controller->ajax_save_author();
		} catch (WPAjaxDieContinueException $e) {}
		$output = ob_get_clean();

		$response = json_decode($output, true);
		$this->assertTrue($response['success'], 'Author creation failed');
		$this->assertEquals(101, $response['data']['author_id']);
	}

	public function test_generate_topics() {
		$author_id = 101;
		$_POST = array();
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['author_id'] = $author_id;
		$_REQUEST = $_POST;

		$this->mock_scheduler->expects($this->once())
			->method('generate_now')
			->with($author_id)
			->willReturn(['Topic 1', 'Topic 2']);

		ob_start();
		try {
			$this->authors_controller->ajax_generate_topics_now();
		} catch (WPAjaxDieContinueException $e) {}
		$output = ob_get_clean();

		$response = json_decode($output, true);
		$this->assertTrue($response['success'], 'Generate topics failed');
	}

	public function test_approve_topic() {
		$topic_id = 202;
		$_POST = array();
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['topic_id'] = $topic_id;
		$_POST['reason'] = 'Good topic';
		$_REQUEST = $_POST;

		$mock_topic = new stdClass();
		$mock_topic->id = $topic_id;
		$mock_topic->topic_title = 'Pending Topic';
		$mock_topic->author_id = 101;
		$mock_topic->status = 'pending';
		$mock_topic->score = 50;

		$this->mock_topics_repo->method('get_by_id')
			->willReturn($mock_topic);

		$this->mock_topics_repo->expects($this->once())
			->method('update_status')
			->with($topic_id, 'approved')
			->willReturn(true);

		ob_start();
		try {
			$this->topics_controller->ajax_approve_topic();
		} catch (WPAjaxDieContinueException $e) {}
		$output = ob_get_clean();

		$response = json_decode($output, true);
		$this->assertTrue($response['success'], 'Approve topic failed');
	}

	public function test_generate_post() {
		$topic_id = 202;
		$_POST = array();
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['topic_id'] = $topic_id;
		$_REQUEST = $_POST;

		$mock_topic = new stdClass();
		$mock_topic->id = $topic_id;
		$mock_topic->status = 'approved';
		$mock_topic->topic_title = 'Approved Topic';

		$this->mock_topics_repo->method('get_by_id')
			->willReturn($mock_topic);

		$this->mock_post_generator->expects($this->once())
			->method('generate_now')
			->with($topic_id)
			->willReturn(123);

		ob_start();
		try {
			$this->topics_controller->ajax_generate_post_from_topic();
		} catch (WPAjaxDieContinueException $e) {}
		$output = ob_get_clean();

		$response = json_decode($output, true);
		$this->assertTrue($response['success'], 'Generate post failed');
		$this->assertEquals(123, $response['data']['post_id']);
	}
}
