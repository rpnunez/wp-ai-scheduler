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
			public function is_available() {
				return true;
			}

			public function generate_text($prompt, $options = array()) {
				return 'Generated content';
			}
		};

		$template_processor = new class {
			public function process($value, $topic = null) {
				return str_replace('{{topic}}', $topic, $value);
			}
			public function extract_ai_variables($template) {
				return array();
			}
		};

		$prompt_builder = new class {
			public function __construct() {
			}

			public function build_content_prompt($context) {
				return 'Content prompt';
			}

			public function build_content_context($context) {
				return 'Content context';
			}

			public function build_title_prompt($context, $topic = null, $voice = null, $content = '') {
				return 'Title prompt';
			}

			public function build_excerpt_prompt($title, $content, $voice = null, $topic = null) {
				return 'Excerpt prompt';
			}

			public function build_base_content_prompt($template, $topic) {
				return 'Base prompt ' . $topic;
			}

			public function build_excerpt_instructions($voice, $topic) {
				return 'Excerpt instructions';
			}
		};

		$history_repository = new class {
			public function create($data) {
				return new class {
					public function with_session($context) {
						return $this;
					}
					public function get_id() {
						return 99;
					}
					public function complete_success($data) {
						return true;
					}
					public function complete_failure($msg, $data) {
						return true;
					}
					public function record($type, $msg, $input = null, $output = null, $context = []) {
						return true;
					}
				};
			}

			public function update($id, $data) {
				return true;
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
			$history_repository,
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

		// The captured data now contains 'context' instead of 'template'
		$this->assertTrue(isset($captured_data['context']));
		$this->assertInstanceOf('AIPS_Template_Context', $captured_data['context']);
		$this->assertSame($template, $captured_data['context']->get_template());
	}
}
