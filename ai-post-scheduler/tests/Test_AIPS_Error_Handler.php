<?php
/**
 * Tests for AIPS_Error_Handler.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Error_Handler extends WP_UnitTestCase {

	/**
	 * @var Test_AIPS_Error_Handler_Logger
	 */
	private $logger;

	public function setUp(): void {
		parent::setUp();

		$this->logger = new Test_AIPS_Error_Handler_Logger();
		$this->set_logger_singleton($this->logger);
	}

	public function tearDown(): void {
		$this->set_logger_singleton(null);
		parent::tearDown();
	}

	public function test_safe_call_returns_callback_result() {
		$result = AIPS_Error_Handler::safe_call(
			function() {
				return 'generated text';
			},
			'Fallback message'
		);

		$this->assertSame('generated text', $result);
		$this->assertCount(0, $this->logger->errors);
	}

	public function test_safe_call_returns_wp_error_when_callback_throws() {
		$result = AIPS_Error_Handler::safe_call(
			function() {
				throw new RuntimeException('Provider exploded');
			},
			'Fallback message'
		);

		$this->assertInstanceOf('WP_Error', $result);
		$this->assertSame('safe_call_failed', $result->get_error_code());
		$this->assertSame('Fallback message', $result->get_error_message());
		$this->assertSame('RuntimeException', $result->get_error_data()['exception_class']);
		$this->assertSame('Provider exploded', $result->get_error_data()['exception_message']);
		$this->assertCount(1, $this->logger->errors);
		$this->assertStringContainsString('Provider exploded', $this->logger->errors[0]['message']);
		$this->assertSame('Fallback message', $this->logger->errors[0]['context']['fallback']);
	}

	public function test_safe_call_uses_default_message_for_empty_fallback() {
		$result = AIPS_Error_Handler::safe_call(
			function() {
				throw new RuntimeException('Boom');
			},
			'  '
		);

		$this->assertInstanceOf('WP_Error', $result);
		$this->assertSame(
			'An unexpected error occurred while processing the request.',
			$result->get_error_message()
		);
	}

	public function test_safe_call_returns_invalid_callback_error_for_non_callable_input() {
		$result = AIPS_Error_Handler::safe_call('not-a-callable', 'Invalid callback fallback');

		$this->assertInstanceOf('WP_Error', $result);
		$this->assertSame('invalid_callback', $result->get_error_code());
		$this->assertSame('Invalid callback fallback', $result->get_error_message());
		$this->assertCount(1, $this->logger->errors);
	}

	public function test_get_safe_call_error_message_returns_original_exception_message() {
		$error = new WP_Error(
			'safe_call_failed',
			'Fallback message',
			array(
				'exception_message' => 'Provider exploded',
			)
		);

		$this->assertSame(
			'Provider exploded',
			AIPS_Error_Handler::get_safe_call_error_message($error)
		);
	}

	public function test_get_safe_call_error_message_falls_back_to_wp_error_message() {
		$error = new WP_Error('generic_error', 'Fallback message');

		$this->assertSame(
			'Fallback message',
			AIPS_Error_Handler::get_safe_call_error_message($error)
		);
	}

	/**
	 * Replace the shared logger singleton for test isolation.
	 *
	 * @param AIPS_Logger|null $logger Logger instance to inject.
	 *
	 * @return void
	 */
	private function set_logger_singleton($logger) {
		$reflection = new ReflectionClass('AIPS_Logger');
		$property   = $reflection->getProperty('instance');
		$property->setAccessible(true);
		$property->setValue(null, $logger);
	}
}

class Test_AIPS_Error_Handler_Logger extends AIPS_Logger {

	/**
	 * @var array<int, array<string, mixed>>
	 */
	public $errors = array();

	public function __construct() {}

	public function error($message, $context = array()) {
		$this->errors[] = array(
			'message' => $message,
			'context' => $context,
		);
	}
}
