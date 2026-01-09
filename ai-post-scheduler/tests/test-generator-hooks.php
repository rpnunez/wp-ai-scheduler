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
		};

		$history_repository = new class {
			public function create($data) {
				return 99;
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

		// Create a simple mock class instead of anonymous class with reference passing
		$mock_post_creator = new stdClass();
		$mock_post_creator->action_called =& $action_called;
		$mock_post_creator->received_data = null;
		$mock_post_creator->create_post = function($data) use ($mock_post_creator) {
			if (!$mock_post_creator->action_called) {
				throw new Exception('Expected pre-create action to fire before post creation.');
			}
			$mock_post_creator->received_data = $data;
			return 321;
		};
		$mock_post_creator->set_featured_image = function($post_id, $attachment_id) {
			// No-op for tests.
		};

		$post_creator = new class($mock_post_creator) {
			private $mock;
			public $received_data;

			public function __construct($mock) {
				$this->mock = $mock;
			}

			public function create_post($data) {
				$result = ($this->mock->create_post)($data);
				$this->received_data = $this->mock->received_data;
				return $result;
			}

			public function set_featured_image($post_id, $attachment_id) {
				return ($this->mock->set_featured_image)($post_id, $attachment_id);
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
		$this->assertSame($template, $captured_data['template']);
	}
}
