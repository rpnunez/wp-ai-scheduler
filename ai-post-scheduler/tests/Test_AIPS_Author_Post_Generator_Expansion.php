<?php
/**
 * Test AIPS_Author_Post_Generator with topic expansion.
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

class Test_AIPS_Author_Post_Generator_Expansion extends WP_UnitTestCase {

	/**
	 * Test that expanded context is added to the prompt when similar topics exist.
	 *
	 * @return void
	 */
	public function test_expanded_context_added_to_prompt() {
		// Create mock expansion service
		$expansion_service = new class {
			public function get_expanded_context($author_id, $topic_id, $limit = 5) {
				return "Related approved topics:\n- How to write better code\n- Best practices for software development";
			}
		};

		// Create mock logger
		$logger = new class {
			public $logged_messages = array();
			
			public function log($message, $level = 'info', $context = array()) {
				$this->logged_messages[] = array(
					'message' => $message,
					'level' => $level
				);
			}
		};

		// Create mock repositories
		$authors_repository = new class {
			public function get_by_id($id) {
				return (object) array(
					'id' => $id,
					'name' => 'Test Author',
					'field_niche' => 'Software Development',
					'generate_featured_image' => 1,
					'featured_image_source' => 'ai_prompt',
					'post_status' => 'draft',
					'post_category' => 1,
					'post_tags' => '',
					'post_author' => 1,
					'article_structure_id' => null,
					'is_active' => 1
				);
			}
		};

		$topics_repository = new class {
			public function get_by_id($id) {
				return (object) array(
					'id' => $id,
					'author_id' => 1,
					'topic_title' => 'Clean Code Principles',
					'status' => 'approved'
				);
			}
		};

		$logs_repository = new class {
			public function log_post_generation($topic_id, $post_id, $metadata) {
				// No-op for test
			}
		};

		$history_repository = new class {
			public function create($data) {
				return 123;
			}
			
			public function update($id, $data) {
				return true;
			}
		};

		$interval_calculator = new class {
			public function calculate_next_run($frequency) {
				return gmdate('Y-m-d H:i:s', strtotime('+1 day'));
			}
		};

		// Create a mock generator that captures the template
		$captured_template = null;
		$generator = new class($captured_template) {
			public $captured_template;
			
			public function __construct(&$captured_template) {
				$this->captured_template =& $captured_template;
			}
			
			public function generate_post($template, $history_id, $variables) {
				$this->captured_template = $template;
				return 456; // Return a mock post ID
			}
		};

		// Use reflection to inject dependencies into AIPS_Author_Post_Generator
		$post_generator = new AIPS_Author_Post_Generator();
		$reflection = new ReflectionClass($post_generator);

		// Inject mocked dependencies
		$this->inject_property($reflection, $post_generator, 'authors_repository', $authors_repository);
		$this->inject_property($reflection, $post_generator, 'topics_repository', $topics_repository);
		$this->inject_property($reflection, $post_generator, 'logs_repository', $logs_repository);
		$this->inject_property($reflection, $post_generator, 'generator', $generator);
		$this->inject_property($reflection, $post_generator, 'logger', $logger);
		$this->inject_property($reflection, $post_generator, 'interval_calculator', $interval_calculator);
		$this->inject_property($reflection, $post_generator, 'history_repository', $history_repository);
		$this->inject_property($reflection, $post_generator, 'expansion_service', $expansion_service);

		// Create author and topic objects
		$author = $authors_repository->get_by_id(1);
		$topic = $topics_repository->get_by_id(1);

		// Call generate_post_from_topic
		$result = $post_generator->generate_post_from_topic($topic, $author);

		// Assert post was created
		$this->assertEquals(456, $result);

		// Assert the captured template contains expanded context
		$this->assertNotNull($generator->captured_template);
		$this->assertStringContainsString('Related approved topics:', $generator->captured_template->prompt_template);
		$this->assertStringContainsString('How to write better code', $generator->captured_template->prompt_template);
		$this->assertStringContainsString('Best practices for software development', $generator->captured_template->prompt_template);

		// Assert base prompt is still present
		$this->assertStringContainsString('Write a comprehensive blog post about: Clean Code Principles', $generator->captured_template->prompt_template);
		$this->assertStringContainsString('Field/Niche: Software Development', $generator->captured_template->prompt_template);

		// Assert logger recorded the context addition
		$found_log = false;
		foreach ($logger->logged_messages as $log) {
			if (strpos($log['message'], 'Added expanded context') !== false) {
				$found_log = true;
				break;
			}
		}
		$this->assertTrue($found_log, 'Logger should record that expanded context was added');
	}

	/**
	 * Test that prompt works without expanded context when none is available.
	 *
	 * @return void
	 */
	public function test_prompt_without_expanded_context() {
		// Create mock expansion service that returns empty context
		$expansion_service = new class {
			public function get_expanded_context($author_id, $topic_id, $limit = 5) {
				return ''; // No similar topics
			}
		};

		// Create mock logger
		$logger = new class {
			public $logged_messages = array();
			
			public function log($message, $level = 'info', $context = array()) {
				$this->logged_messages[] = array(
					'message' => $message,
					'level' => $level
				);
			}
		};

		// Create mock repositories
		$authors_repository = new class {
			public function get_by_id($id) {
				return (object) array(
					'id' => $id,
					'name' => 'Test Author',
					'field_niche' => 'Software Development',
					'generate_featured_image' => 1,
					'featured_image_source' => 'ai_prompt',
					'post_status' => 'draft',
					'post_category' => 1,
					'post_tags' => '',
					'post_author' => 1,
					'article_structure_id' => null,
					'is_active' => 1
				);
			}
		};

		$topics_repository = new class {
			public function get_by_id($id) {
				return (object) array(
					'id' => $id,
					'author_id' => 1,
					'topic_title' => 'New Topic Without Similar Topics',
					'status' => 'approved'
				);
			}
		};

		$logs_repository = new class {
			public function log_post_generation($topic_id, $post_id, $metadata) {
				// No-op for test
			}
		};

		$history_repository = new class {
			public function create($data) {
				return 123;
			}
			
			public function update($id, $data) {
				return true;
			}
		};

		$interval_calculator = new class {
			public function calculate_next_run($frequency) {
				return gmdate('Y-m-d H:i:s', strtotime('+1 day'));
			}
		};

		// Create a mock generator that captures the template
		$captured_template = null;
		$generator = new class($captured_template) {
			public $captured_template;
			
			public function __construct(&$captured_template) {
				$this->captured_template =& $captured_template;
			}
			
			public function generate_post($template, $history_id, $variables) {
				$this->captured_template = $template;
				return 456; // Return a mock post ID
			}
		};

		// Use reflection to inject dependencies into AIPS_Author_Post_Generator
		$post_generator = new AIPS_Author_Post_Generator();
		$reflection = new ReflectionClass($post_generator);

		// Inject mocked dependencies
		$this->inject_property($reflection, $post_generator, 'authors_repository', $authors_repository);
		$this->inject_property($reflection, $post_generator, 'topics_repository', $topics_repository);
		$this->inject_property($reflection, $post_generator, 'logs_repository', $logs_repository);
		$this->inject_property($reflection, $post_generator, 'generator', $generator);
		$this->inject_property($reflection, $post_generator, 'logger', $logger);
		$this->inject_property($reflection, $post_generator, 'interval_calculator', $interval_calculator);
		$this->inject_property($reflection, $post_generator, 'history_repository', $history_repository);
		$this->inject_property($reflection, $post_generator, 'expansion_service', $expansion_service);

		// Create author and topic objects
		$author = $authors_repository->get_by_id(1);
		$topic = $topics_repository->get_by_id(1);

		// Call generate_post_from_topic
		$result = $post_generator->generate_post_from_topic($topic, $author);

		// Assert post was created
		$this->assertEquals(456, $result);

		// Assert the captured template does NOT contain expanded context
		$this->assertNotNull($generator->captured_template);
		$this->assertStringNotContainsString('Related approved topics:', $generator->captured_template->prompt_template);

		// Assert base prompt is still present
		$this->assertStringContainsString('Write a comprehensive blog post about: New Topic Without Similar Topics', $generator->captured_template->prompt_template);
		$this->assertStringContainsString('Field/Niche: Software Development', $generator->captured_template->prompt_template);

		// Assert logger did NOT record context addition
		$found_log = false;
		foreach ($logger->logged_messages as $log) {
			if (strpos($log['message'], 'Added expanded context') !== false) {
				$found_log = true;
				break;
			}
		}
		$this->assertFalse($found_log, 'Logger should not record context addition when context is empty');
	}

	/**
	 * Helper method to inject property values using reflection.
	 *
	 * @param ReflectionClass $reflection The reflection class.
	 * @param object $object The object instance.
	 * @param string $property_name The property name.
	 * @param mixed $value The value to inject.
	 */
	private function inject_property($reflection, $object, $property_name, $value) {
		$property = $reflection->getProperty($property_name);
		$property->setAccessible(true);
		$property->setValue($object, $value);
	}
}
