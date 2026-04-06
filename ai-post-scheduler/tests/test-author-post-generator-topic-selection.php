<?php
/**
 * Tests for author post generator topic selection strategy.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Author_Post_Generator_Topic_Selection extends WP_UnitTestCase {

	/**
	 * @param object $object Object instance.
	 * @param string $property_name Private property name.
	 * @param mixed  $value Value to inject.
	 * @return void
	 */
	private function inject_property($object, $property_name, $value) {
		$reflection = new ReflectionClass($object);
		$property = $reflection->getProperty($property_name);
		$property->setAccessible(true);
		$property->setValue($object, $value);
	}

	/**
	 * @return object
	 */
	private function make_test_generator() {
		return new class extends AIPS_Author_Post_Generator {
			public function generate_post_from_topic($topic, $author, $creation_method = 'manual') {
				return 777;
			}
		};
	}

	/**
	 * Uses the new repository method when the author option is enabled.
	 *
	 * @return void
	 */
	public function test_generate_post_for_author_uses_without_generated_posts_query_when_enabled() {
		$topics_repository = new class {
			public $used_strict = false;
			public $used_legacy = false;

			public function get_approved_for_generation($author_id, $limit = 1) {
				$this->used_legacy = true;
				return array(
					(object) array('id' => 201, 'topic_title' => 'Legacy topic')
				);
			}

			public function get_approved_without_generated_posts_for_generation($author_id, $limit = 1) {
				$this->used_strict = true;
				return array(
					(object) array('id' => 101, 'topic_title' => 'Strict topic')
				);
			}
		};

		$authors_repository = new class {
			public function update_post_generation_schedule($author_id, $next_run) {
				return true;
			}
		};

		$interval_calculator = new class {
			public function calculate_next_run($frequency, $current = null) {
				return current_time('mysql');
			}
		};

		$logger = new class {
			public function log($message, $level = 'info', $context = array()) {
				return true;
			}
		};

		$author = (object) array(
			'id' => 12,
			'name' => 'Strict Author',
			'post_generation_frequency' => 'daily',
			'post_generation_next_run' => current_time('mysql'),
			'post_generation_only_without_generated_posts' => 1,
		);

		$generator = $this->make_test_generator();
		$this->inject_property($generator, 'topics_repository', $topics_repository);
		$this->inject_property($generator, 'authors_repository', $authors_repository);
		$this->inject_property($generator, 'interval_calculator', $interval_calculator);
		$this->inject_property($generator, 'logger', $logger);

		$result = $generator->generate_post_for_author($author);

		$this->assertSame(777, $result);
		$this->assertTrue($topics_repository->used_strict);
		$this->assertFalse($topics_repository->used_legacy);
	}

	/**
	 * Uses the legacy approved-topics query when the author option is disabled.
	 *
	 * @return void
	 */
	public function test_generate_post_for_author_uses_legacy_query_when_disabled() {
		$topics_repository = new class {
			public $used_strict = false;
			public $used_legacy = false;

			public function get_approved_for_generation($author_id, $limit = 1) {
				$this->used_legacy = true;
				return array(
					(object) array('id' => 301, 'topic_title' => 'Legacy topic')
				);
			}

			public function get_approved_without_generated_posts_for_generation($author_id, $limit = 1) {
				$this->used_strict = true;
				return array(
					(object) array('id' => 401, 'topic_title' => 'Strict topic')
				);
			}
		};

		$authors_repository = new class {
			public function update_post_generation_schedule($author_id, $next_run) {
				return true;
			}
		};

		$interval_calculator = new class {
			public function calculate_next_run($frequency, $current = null) {
				return current_time('mysql');
			}
		};

		$logger = new class {
			public function log($message, $level = 'info', $context = array()) {
				return true;
			}
		};

		$author = (object) array(
			'id' => 99,
			'name' => 'Legacy Author',
			'post_generation_frequency' => 'weekly',
			'post_generation_next_run' => current_time('mysql'),
			'post_generation_only_without_generated_posts' => 0,
		);

		$generator = $this->make_test_generator();
		$this->inject_property($generator, 'topics_repository', $topics_repository);
		$this->inject_property($generator, 'authors_repository', $authors_repository);
		$this->inject_property($generator, 'interval_calculator', $interval_calculator);
		$this->inject_property($generator, 'logger', $logger);

		$result = $generator->generate_post_for_author($author);

		$this->assertSame(777, $result);
		$this->assertTrue($topics_repository->used_legacy);
		$this->assertFalse($topics_repository->used_strict);
	}
}
