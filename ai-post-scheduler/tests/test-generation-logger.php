<?php
/**
 * Test AIPS_Generation_Logger
 *
 * @deprecated 2.1.0 AIPS_Generation_Logger is deprecated. Use AIPS_History_Container directly.
 * 
 * These tests are maintained for backward compatibility but the class itself
 * has been deprecated in favor of using AIPS_History_Container directly via
 * AIPS_History_Service.
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 * @since 1.8.0
 */

class Test_AIPS_Generation_Logger extends WP_UnitTestCase {

	private $logger;
	private $history_repository;
	private $session;
	private $generation_logger;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create mock logger
		$this->logger = $this->createMock(AIPS_Logger::class);

		// Create mock history repository
		$this->history_repository = $this->createMock(AIPS_History_Repository::class);

		// Create mock session
		$this->session = $this->createMock(AIPS_Generation_Session::class);

		// Create the generation logger
		$this->generation_logger = new AIPS_Generation_Logger(
			$this->logger,
			$this->history_repository,
			$this->session
		);
	}

	/**
	 * Test log_ai_call updates both session and history repository.
	 */
	public function test_log_ai_call_updates_session_and_history() {
		$history_id = 123;
		$this->generation_logger->set_history_id($history_id);

		// Expect session to log the AI call
		$this->session->expects($this->once())
			->method('log_ai_call');

		// Expect history repository to add log entry
		$this->history_repository->expects($this->once())
			->method('add_log_entry')
			->with(
				$this->equalTo($history_id),
				$this->equalTo('title'),
				$this->callback(function($details) {
					return isset($details['prompt']) &&
					       isset($details['options']) &&
					       isset($details['response']) &&
					       array_key_exists('error', $details) &&
					       $details['prompt'] === 'Generate a title' &&
					       $details['response'] === base64_encode('Great Title') &&
					       $details['error'] === null;
				})
			);

		$this->generation_logger->log_ai_call(
			'title',
			'Generate a title',
			'Great Title',
			array('model' => 'gpt-4'),
			null
		);
	}

	/**
	 * Test log_ai_call with null response.
	 */
	public function test_log_ai_call_with_null_response() {
		$history_id = 123;
		$this->generation_logger->set_history_id($history_id);

		$this->session->expects($this->once())
			->method('log_ai_call');

		// Expect history repository to receive null for response (not base64_encode(null))
		$this->history_repository->expects($this->once())
			->method('add_log_entry')
			->with(
				$this->equalTo($history_id),
				$this->equalTo('content'),
				$this->callback(function($details) {
					return $details['response'] === null;
				})
			);

		$this->generation_logger->log_ai_call(
			'content',
			'Generate content',
			null,
			array(),
			'API error'
		);
	}

	/**
	 * Test log_ai_call with error adds error to session.
	 */
	public function test_log_ai_call_with_error_adds_error_to_session() {
		$history_id = 123;
		$this->generation_logger->set_history_id($history_id);

		// Expect session to log AI call and add error
		$this->session->expects($this->once())
			->method('log_ai_call');
		$this->session->expects($this->once())
			->method('add_error');

		$this->history_repository->expects($this->once())
			->method('add_log_entry');

		$this->generation_logger->log_ai_call(
			'excerpt',
			'Generate excerpt',
			null,
			array(),
			'API timeout'
		);
	}

	/**
	 * Test log_ai_call without history_id does not call repository.
	 */
	public function test_log_ai_call_without_history_id() {
		// Do not set history_id

		$this->session->expects($this->once())
			->method('log_ai_call');

		// History repository should not be called
		$this->history_repository->expects($this->never())
			->method('add_log_entry');

		$this->generation_logger->log_ai_call(
			'title',
			'Generate a title',
			'Great Title',
			array(),
			null
		);
	}

	/**
	 * Test log method delegates to logger.
	 */
	public function test_log_delegates_to_logger() {
		$this->logger->expects($this->once())
			->method('log')
			->with(
				$this->equalTo('Test message'),
				$this->equalTo('info'),
				$this->equalTo(array('key' => 'value'))
			);

		$this->generation_logger->log('Test message', 'info', array(), array('key' => 'value'));
	}

	/**
	 * Test log method with ai_data calls log_ai_call.
	 */
	public function test_log_with_ai_data_calls_log_ai_call() {
		$history_id = 456;
		$this->generation_logger->set_history_id($history_id);

		$this->logger->expects($this->once())
			->method('log');

		$this->session->expects($this->once())
			->method('log_ai_call');

		$this->history_repository->expects($this->once())
			->method('add_log_entry')
			->with(
				$this->equalTo($history_id),
				$this->equalTo('featured_image'),
				$this->callback(function($details) {
					return $details['prompt'] === 'Generate image' &&
					       $details['response'] === base64_encode('image_url.jpg');
				})
			);

		$ai_data = array(
			'type' => 'featured_image',
			'prompt' => 'Generate image',
			'response' => 'image_url.jpg',
			'options' => array('size' => 'large'),
		);

		$this->generation_logger->log('AI call made', 'info', $ai_data);
	}

	/**
	 * Test log_error adds error to session and history.
	 */
	public function test_log_error_adds_error_to_session_and_history() {
		$history_id = 789;
		$this->generation_logger->set_history_id($history_id);

		$this->session->expects($this->once())
			->method('add_error');

		$this->history_repository->expects($this->once())
			->method('add_log_entry')
			->with(
				$this->equalTo($history_id),
				$this->equalTo('validation_error'),
				$this->equalTo(array('message' => 'Template is invalid'))
			);

		$this->generation_logger->log_error('validation', 'Template is invalid');
	}

	/**
	 * Test log_error without history_id does not call repository.
	 */
	public function test_log_error_without_history_id() {
		// Do not set history_id

		$this->session->expects($this->once())
			->method('add_error');

		// History repository should not be called
		$this->history_repository->expects($this->never())
			->method('add_log_entry');

		$this->generation_logger->log_error('api', 'API error occurred');
	}

	/**
	 * Test warning method calls logger's warning method.
	 */
	public function test_warning_calls_logger_warning_method() {
		// Create a logger mock that has a warning method
		$logger_with_warning = $this->createMock(AIPS_Logger::class);
		$logger_with_warning->expects($this->once())
			->method('warning')
			->with(
				$this->equalTo('Warning message'),
				$this->equalTo(array('context' => 'test'))
			);

		$generation_logger = new AIPS_Generation_Logger(
			$logger_with_warning,
			$this->history_repository,
			$this->session
		);

		$generation_logger->warning('Warning message', array('context' => 'test'));
	}

	/**
	 * Test warning method falls back to log when logger has no warning method.
	 */
	public function test_warning_falls_back_to_log_method() {
		// Create a logger mock without warning method
		$logger_without_warning = $this->getMockBuilder(stdClass::class)
			->addMethods(array('log'))
			->getMock();

		$logger_without_warning->expects($this->once())
			->method('log')
			->with(
				$this->equalTo('Warning message'),
				$this->equalTo('warning'),
				$this->equalTo(array('context' => 'test'))
			);

		$generation_logger = new AIPS_Generation_Logger(
			$logger_without_warning,
			$this->history_repository,
			$this->session
		);

		$generation_logger->warning('Warning message', array('context' => 'test'));
	}

	/**
	 * Test set_history_id properly updates history context.
	 */
	public function test_set_history_id_updates_context() {
		$history_id = 999;
		$this->generation_logger->set_history_id($history_id);

		// Verify by calling log_ai_call and checking history repository is called with correct ID
		$this->session->expects($this->once())
			->method('log_ai_call');

		$this->history_repository->expects($this->once())
			->method('add_log_entry')
			->with($this->equalTo($history_id));

		$this->generation_logger->log_ai_call('test', 'prompt', 'response');
	}
}
