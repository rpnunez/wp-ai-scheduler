<?php
/**
 * Test generator hooks.
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

class Test_AIPS_Generator_Hooks extends WP_UnitTestCase {

	/**
	 * Ensure the before post creation hook fires with the expected payload.
	 *
	 * @return void
	 */
	public function test_before_post_create_action_receives_post_data() {
		$logger = new class {
			public function log($message, $level = 'info', $context = array()) {
				// Silence logger for tests.
			}
		};

		$ai_service = new class {
			private $chat_counter = 0;
			
			public function is_available() {
				return true;
			}

			public function generate_text($prompt, $options = array()) {
				return 'Generated content';
			}
			
			public function generate_with_chatbot($chatbot_id, $message, $options = array(), $log_type = 'chatbot') {
				$this->chat_counter++;
				
				// Return different responses for different steps
				if ($this->chat_counter === 1) {
					// Content generation
					return array(
						'reply' => 'Generated content',
						'chatId' => 'test-chat-id-123'
					);
				} elseif ($this->chat_counter === 2) {
					// Title generation
					return array(
						'reply' => 'Generated Title',
						'chatId' => 'test-chat-id-123'
					);
				} elseif ($this->chat_counter === 3) {
					// Excerpt generation
					return array(
						'reply' => 'Generated excerpt',
						'chatId' => 'test-chat-id-123'
					);
				}
				
				return array(
					'reply' => 'Generic response',
					'chatId' => 'test-chat-id-123'
				);
			}
		};

		$template_processor = new class {
			public function process($value, $topic = null) {
				return str_replace('{{topic}}', $topic, $value);
			}
		};

		$prompt_builder = new class {
			public function __construct() {
			}

			public function build_base_content_prompt($template, $topic) {
				return 'Base prompt ' . $topic;
			}

			public function build_excerpt_instructions($voice, $topic) {
				return 'Excerpt instructions';
			}
			
			public function build_content_prompt($context) {
				return 'Content prompt for ' . $context->get_topic();
			}
			
			public function build_content_context($context) {
				return 'Content context';
			}
			
			public function build_title_prompt($context, $x = null, $y = null, $content = '') {
				return 'Title prompt for ' . $context->get_topic();
			}
			
			public function build_excerpt_prompt($title, $content, $voice = null, $topic = null) {
				return 'Excerpt prompt for ' . $title;
			}
		};

		// Mock history service that returns a history container mock
		$history_service = new class {
			public function create($type, $data = array()) {
				$container = new class {
					private $id = 99;
					
					public function get_id() {
						return $this->id;
					}
					
					public function with_session($context) {
						return $this;
					}
					
					public function record($type, $message, $data = null, $result = null, $metadata = array()) {
						// No-op for tests
					}
					
					public function complete_failure($error, $metadata = array()) {
						// No-op for tests
					}
					
					public function complete_success($data = array()) {
						// No-op for tests
					}
				};
				
				return $container;
			}
		};

		$action_called = false;
		$captured_data = null;

		add_action(
			'aips_post_generation_before_post_create',
			function($data) use (&$action_called, &$captured_data) {
				$action_called = true;
				$captured_data = $data;
			},
			10,
			1
		);

		// Create a simple reference container
		$ref_container = new stdClass();
		$ref_container->action_called =& $action_called;

		$post_creator = new class($ref_container) {
			private $ref_container;
			public $received_data;

			public function __construct($ref_container) {
				$this->ref_container = $ref_container;
			}

			public function create_post($data) {
				if (!$this->ref_container->action_called) {
					throw new Exception('Expected pre-create action to fire before post creation.');
				}

				$this->received_data = $data;
				return 321;
			}

			public function set_featured_image($post_id, $attachment_id) {
				// No-op for tests.
			}
		};

		$generator = new AIPS_Generator(
			$logger,
			$ai_service,
			$template_processor,
			null,
			new class {
			},
			$post_creator,
			$history_service,
			$prompt_builder
		);

		$template = (object) array(
			'id' => 5,
			'prompt_template' => 'Prompt for {{topic}}',
			'title_prompt' => 'Title for {{topic}}',
			'post_status' => 'draft',
			'post_category' => '',
			'post_tags' => '',
			'post_author' => 1,
			'post_quantity' => 1,
			'generate_featured_image' => false,
			'image_prompt' => '',
		);

		$result = $generator->generate_post($template, null, 'Testing');

		$this->assertEquals(321, $result);
		$this->assertTrue($action_called);
		$this->assertIsArray($captured_data);
		$this->assertSame('Generated content', $captured_data['content']);
		$this->assertSame($template, $captured_data['template']);
	}
	
	/**
	 * Test chatbot conversational flow maintains chatId across steps.
	 *
	 * Validates that:
	 * 1. Content generation establishes a chatId
	 * 2. Title generation reuses the same chatId
	 * 3. Excerpt generation reuses the same chatId
	 * 4. All three steps receive correct options
	 *
	 * @return void
	 */
	public function test_chatbot_conversational_flow_maintains_chat_id() {
		$logger = new class {
			public function log($message, $level = 'info', $context = array()) {
				// Silence logger for tests.
			}
		};

		// Track chatbot calls to verify conversation continuity
		$chatbot_calls = array();
		
		$ai_service = new class($chatbot_calls) {
			private $calls;
			private $chat_counter = 0;
			
			public function __construct(&$calls) {
				$this->calls =& $calls;
			}
			
			public function is_available() {
				return true;
			}

			public function generate_text($prompt, $options = array()) {
				return 'Generated content';
			}
			
			public function generate_with_chatbot($chatbot_id, $message, $options = array(), $log_type = 'chatbot') {
				$this->chat_counter++;
				
				// Record the call
				$this->calls[] = array(
					'step' => $this->chat_counter,
					'chatbot_id' => $chatbot_id,
					'message' => $message,
					'options' => $options,
					'log_type' => $log_type,
				);
				
				// Return different responses for different steps
				if ($this->chat_counter === 1) {
					// Content generation - establish chatId
					return array(
						'reply' => 'Generated content about Testing topic',
						'chatId' => 'test-chat-id-123'
					);
				} elseif ($this->chat_counter === 2) {
					// Title generation - should include chatId in options
					return array(
						'reply' => 'Amazing Title About Testing',
						'chatId' => 'test-chat-id-123'
					);
				} elseif ($this->chat_counter === 3) {
					// Excerpt generation - should include chatId in options
					return array(
						'reply' => 'This is an excerpt about the testing topic',
						'chatId' => 'test-chat-id-123'
					);
				}
				
				return array(
					'reply' => 'Generic response',
					'chatId' => 'test-chat-id-123'
				);
			}
		};

		$template_processor = new class {
			public function process($value, $topic = null) {
				return str_replace('{{topic}}', $topic, $value);
			}
		};

		$prompt_builder = new class {
			public function __construct() {
			}

			public function build_base_content_prompt($template, $topic) {
				return 'Base prompt ' . $topic;
			}

			public function build_excerpt_instructions($voice, $topic) {
				return 'Excerpt instructions';
			}
			
			public function build_content_prompt($context) {
				return 'Content prompt for ' . $context->get_topic();
			}
			
			public function build_content_context($context) {
				return 'Content context';
			}
			
			public function build_title_prompt($context, $x = null, $y = null, $content = '') {
				return 'Title prompt for ' . $context->get_topic() . ' with content: ' . mb_substr($content, 0, 20);
			}
			
			public function build_excerpt_prompt($title, $content, $voice = null, $topic = null) {
				return 'Excerpt prompt for ' . $title . ' with content: ' . mb_substr($content, 0, 20);
			}
		};

		// Mock history service that returns a history container mock
		$history_service = new class {
			public function create($type, $data = array()) {
				$container = new class {
					private $id = 99;
					
					public function get_id() {
						return $this->id;
					}
					
					public function with_session($context) {
						return $this;
					}
					
					public function record($type, $message, $data = null, $result = null, $metadata = array()) {
						// No-op for tests
					}
					
					public function complete_failure($error, $metadata = array()) {
						// No-op for tests
					}
					
					public function complete_success($data = array()) {
						// No-op for tests
					}
				};
				
				return $container;
			}
		};

		$post_creator = new class {
			public function create_post($data) {
				return 321;
			}

			public function set_featured_image($post_id, $attachment_id) {
				// No-op for tests.
			}
		};

		$generator = new AIPS_Generator(
			$logger,
			$ai_service,
			$template_processor,
			null,
			new class {
			},
			$post_creator,
			$history_service,
			$prompt_builder
		);

		$template = (object) array(
			'id' => 5,
			'prompt_template' => 'Prompt for {{topic}}',
			'title_prompt' => 'Title for {{topic}}',
			'post_status' => 'draft',
			'post_category' => '',
			'post_tags' => '',
			'post_author' => 1,
			'post_quantity' => 1,
			'generate_featured_image' => false,
			'image_prompt' => '',
		);

		$result = $generator->generate_post($template, null, 'Testing');

		// Verify post was created
		$this->assertEquals(321, $result);
		
		// Verify we made exactly 3 chatbot calls
		$this->assertCount(3, $chatbot_calls);
		
		// Verify first call (content) doesn't have chatId
		$this->assertEquals(1, $chatbot_calls[0]['step']);
		$this->assertEquals('content', $chatbot_calls[0]['log_type']);
		$this->assertArrayHasKey('context', $chatbot_calls[0]['options']); // Should have context
		$this->assertArrayNotHasKey('chatId', $chatbot_calls[0]['options']); // No chatId yet
		
		// Verify second call (title) reuses chatId
		$this->assertEquals(2, $chatbot_calls[1]['step']);
		$this->assertEquals('title', $chatbot_calls[1]['log_type']);
		$this->assertArrayHasKey('chatId', $chatbot_calls[1]['options']); // Should have chatId
		$this->assertEquals('test-chat-id-123', $chatbot_calls[1]['options']['chatId']); // Same chatId
		
		// Verify third call (excerpt) reuses chatId
		$this->assertEquals(3, $chatbot_calls[2]['step']);
		$this->assertEquals('excerpt', $chatbot_calls[2]['log_type']);
		$this->assertArrayHasKey('chatId', $chatbot_calls[2]['options']); // Should have chatId
		$this->assertEquals('test-chat-id-123', $chatbot_calls[2]['options']['chatId']); // Same chatId
	}
	
	/**
	 * Test chatbot flow handles missing chatId gracefully.
	 *
	 * Validates that when AI Engine doesn't return a chatId:
	 * 1. Content generation succeeds
	 * 2. Title generation continues without chatId
	 * 3. Excerpt generation continues without chatId
	 * 4. Post is still created successfully
	 *
	 * @return void
	 */
	public function test_chatbot_flow_handles_missing_chat_id() {
		$logger = new class {
			private $warnings = array();
			
			public function log($message, $level = 'info', $context = array()) {
				if ($level === 'warning') {
					$this->warnings[] = $message;
				}
			}
			
			public function get_warnings() {
				return $this->warnings;
			}
		};

		$ai_service = new class {
			private $chat_counter = 0;
			
			public function is_available() {
				return true;
			}

			public function generate_text($prompt, $options = array()) {
				return 'Generated content';
			}
			
			public function generate_with_chatbot($chatbot_id, $message, $options = array(), $log_type = 'chatbot') {
				$this->chat_counter++;
				
				// Return responses WITHOUT chatId to simulate edge case
				if ($this->chat_counter === 1) {
					return array(
						'reply' => 'Generated content about Testing topic'
						// No chatId!
					);
				} elseif ($this->chat_counter === 2) {
					return array(
						'reply' => 'Amazing Title About Testing'
						// No chatId!
					);
				} elseif ($this->chat_counter === 3) {
					return array(
						'reply' => 'This is an excerpt about the testing topic'
						// No chatId!
					);
				}
				
				return array('reply' => 'Generic response');
			}
		};

		$template_processor = new class {
			public function process($value, $topic = null) {
				return str_replace('{{topic}}', $topic, $value);
			}
		};

		$prompt_builder = new class {
			public function __construct() {
			}

			public function build_content_prompt($context) {
				return 'Content prompt for ' . $context->get_topic();
			}
			
			public function build_content_context($context) {
				return 'Content context';
			}
			
			public function build_title_prompt($context, $x = null, $y = null, $content = '') {
				return 'Title prompt with content: ' . mb_substr($content, 0, 20);
			}
			
			public function build_excerpt_prompt($title, $content, $voice = null, $topic = null) {
				return 'Excerpt prompt for ' . $title;
			}
		};

		$history_service = new class {
			public function create($type, $data = array()) {
				return new class {
					public function get_id() { return 99; }
					public function with_session($context) { return $this; }
					public function record($type, $message, $data = null, $result = null, $metadata = array()) {}
					public function complete_failure($error, $metadata = array()) {}
					public function complete_success($data = array()) {}
				};
			}
		};

		$post_creator = new class {
			public function create_post($data) {
				return 321;
			}
			public function set_featured_image($post_id, $attachment_id) {}
		};

		$generator = new AIPS_Generator(
			$logger,
			$ai_service,
			$template_processor,
			null,
			new class {},
			$post_creator,
			$history_service,
			$prompt_builder
		);

		$template = (object) array(
			'id' => 5,
			'prompt_template' => 'Prompt for {{topic}}',
			'title_prompt' => 'Title for {{topic}}',
			'post_status' => 'draft',
			'post_category' => '',
			'post_tags' => '',
			'post_author' => 1,
			'post_quantity' => 1,
			'generate_featured_image' => false,
			'image_prompt' => '',
		);

		$result = $generator->generate_post($template, null, 'Testing');

		// Verify post was still created despite missing chatId
		$this->assertEquals(321, $result);
		
		// Verify a warning was logged about missing chatId
		$warnings = $logger->get_warnings();
		$this->assertNotEmpty($warnings);
		$this->assertStringContainsString('chatId', $warnings[0]);
	}
}
