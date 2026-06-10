<?php
/**
 * Tests for AIPS_Action_Handler.
 *
 * Verifies that the generic action and filter hooks fire correctly and
 * are handled by the AIPS_Action_Handler to manage the database history logs.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Action_Handler extends WP_UnitTestCase {

	/**
	 * @var AIPS_History_Repository
	 */
	private $history_repo;

	/**
	 * @var AIPS_History_Service
	 */
	private $history_service;

	/**
	 * Set up test state.
	 */
	public function setUp(): void {
		parent::setUp();

		if (!defined('ABSPATH') || !file_exists(ABSPATH . 'wp-admin/includes/upgrade.php')) {
			$this->markTestSkipped('WordPress upgrade helpers are unavailable.');
		}

		// Ensure tables exist.
		AIPS_DB_Manager::install_tables();

		$this->history_repo = new AIPS_History_Repository();
		$this->history_service = new AIPS_History_Service($this->history_repo);

		// Ensure AIPS_Action_Handler hooks are registered.
		AIPS_Action_Handler::instance();
	}

	/**
	 * Test that Action Handler is a singleton.
	 */
	public function test_action_handler_is_singleton() {
		$instance1 = AIPS_Action_Handler::instance();
		$instance2 = AIPS_Action_Handler::instance();
		$this->assertSame($instance1, $instance2);
	}

	/**
	 * Test that aips_create_history_container filter resolves the container.
	 */
	public function test_create_history_container_filter() {
		$context = new AIPS_Template_Context( (object) array('id' => 123, 'campaign_id' => 456), null, 'my topic' );
		
		$container = apply_filters('aips_create_history_container', null, 'post_generation', array(
			'creation_method' => 'manual',
			'template_id' => 123,
			'campaign_id' => 456,
		), $context);

		$this->assertInstanceOf(AIPS_History_Container::class, $container);
		$this->assertNotEmpty($container->get_id());
		$this->assertSame('post_generation', $this->history_repo->get_by_id($container->get_id())->type);
	}

	/**
	 * Test that aips_ai_call_started action logs to the container.
	 */
	public function test_ai_call_started_action() {
		$container = $this->history_service->create('post_generation', array());
		
		$prompt = 'Hello AI';
		$options = array('model' => 'gpt-3.5-turbo');
		$log_type = 'content';

		do_action('aips_ai_call_started', $prompt, $options, $log_type, $container);

		$logs = $this->history_repo->get_logs_by_history_id($container->get_id());
		$this->assertCount(1, $logs);
		$this->assertSame('ai_request', $logs[0]->type);
		
		$details = json_decode($logs[0]->details, true);
		$this->assertSame($prompt, $details['input']['prompt']);
		$this->assertSame('gpt-3.5-turbo', $details['input']['options']['model']);
		$this->assertSame('content', $details['context']['component']);
	}

	/**
	 * Test that aips_ai_call_completed action logs to the container.
	 */
	public function test_ai_call_completed_action() {
		$container = $this->history_service->create('post_generation', array());
		
		$response = 'AI Response text here';
		$prompt = 'Write a post';
		$options = array();
		$log_type = 'title';

		do_action('aips_ai_call_completed', $response, $prompt, $options, $log_type, $container);

		$logs = $this->history_repo->get_logs_by_history_id($container->get_id());
		$this->assertCount(1, $logs);
		$this->assertSame('ai_response', $logs[0]->type);

		$details = json_decode($logs[0]->details, true);
		$this->assertSame($response, $details['output']);
		$this->assertSame('title', $details['context']['component']);
	}

	/**
	 * Test that aips_ai_call_failed action logs to the container.
	 */
	public function test_ai_call_failed_action() {
		$container = $this->history_service->create('post_generation', array());
		
		$error = new WP_Error('provider_error', 'Rate limit hit');
		$prompt = 'Generate image';
		$options = array();
		$log_type = 'featured_image';

		do_action('aips_ai_call_failed', $error, $prompt, $options, $log_type, $container);

		$logs = $this->history_repo->get_logs_by_history_id($container->get_id());
		$this->assertCount(1, $logs);
		$this->assertSame('error', $logs[0]->type);

		$details = json_decode($logs[0]->details, true);
		$this->assertStringContainsString('Rate limit hit', $details['message']);
		$this->assertSame('featured_image', $details['context']['component']);
	}

	/**
	 * Test that aips_post_generation_completed action logs to the container.
	 */
	public function test_post_generation_completed_action() {
		$context = new AIPS_Template_Context( (object) array('id' => 99), null, 'my topic' );
		$container = $this->history_service->create('post_generation', array())->with_session($context);
		
		$post_id = 999;
		$title = 'Success Title';
		$content = 'Success Content';
		$generation_incomplete = false;
		$component_statuses = array(
			'post_title' => true,
			'post_excerpt' => true,
			'post_content' => true,
			'featured_image' => true,
		);

		do_action(
			'aips_post_generation_completed',
			$post_id,
			$title,
			$content,
			$generation_incomplete,
			$component_statuses,
			$context,
			$container
		);

		// Verify history table status
		$history = $this->history_repo->get_by_id($container->get_id());
		$this->assertSame('completed', $history->status);
		$this->assertSame($post_id, (int) $history->post_id);

		// Verify metric entry recorded
		$logs = $this->history_repo->get_logs_by_history_id($container->get_id());
		$metric_log = null;
		foreach ($logs as $log) {
			if ($log->type === 'metric_generation_result') {
				$metric_log = $log;
				break;
			}
		}
		$this->assertNotNull($metric_log);
		$details = json_decode($metric_log->details, true);
		$this->assertSame('completed', $details['input']['outcome']);
	}

	/**
	 * Test that aips_post_generation_failed action logs to the container.
	 */
	public function test_post_generation_failed_action() {
		$context = new AIPS_Template_Context( (object) array('id' => 99), null, 'my topic' );
		$container = $this->history_service->create('post_generation', array())->with_session($context);
		
		$error = new WP_Error('db_insert_failed', 'Could not insert post');

		do_action('aips_post_generation_failed', $error, $context, $container);

		// Verify history table status
		$history = $this->history_repo->get_by_id($container->get_id());
		$this->assertSame('failed', $history->status);
		$this->assertSame('Could not insert post', $history->error_message);
	}
}
