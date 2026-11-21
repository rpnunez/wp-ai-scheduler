<?php
/**
 * Tests for author post batching behaviour.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Author_Post_Generator_Batching extends WP_UnitTestCase {

	/**
	 * Helper to inject a private property via reflection.
	 *
	 * @param object $object Object instance.
	 * @param string $property_name Property name.
	 * @param mixed  $value Property value.
	 * @return void
	 */
	private function inject_property($object, $property_name, $value) {
		$reflection = new ReflectionClass($object);

		while ($reflection && !$reflection->hasProperty($property_name)) {
			$reflection = $reflection->getParentClass();
		}

		$this->assertInstanceOf('ReflectionClass', $reflection);

		$property = $reflection->getProperty($property_name);
		$property->setAccessible(true);
		$property->setValue($object, $value);
	}

	/**
	 * Manual author runs should use the manual per-run quantity.
	 *
	 * @return void
	 */
	public function test_generate_posts_for_author_uses_manual_quantity() {
		$topics_repository = new class {
			public $last_limit = 0;

			public function get_approved_for_generation($author_id, $limit = 1, $after_id = 0) {
				$this->last_limit = $limit;

				return array(
					(object) array('id' => 11, 'topic_title' => 'Topic 11'),
					(object) array('id' => 12, 'topic_title' => 'Topic 12'),
				);
			}
		};

		$post_generator = new class extends AIPS_Author_Post_Generator {
			public $generated = array();

			public function generate_post_from_topic($topic, $author, $creation_method = 'manual', $trigger_context = array()) {
				$this->generated[] = array(
					'topic_id' => $topic->id,
					'creation_method' => $creation_method,
				);

				return 1000 + (int) $topic->id;
			}
		};

		$this->inject_property($post_generator, 'topics_repository', $topics_repository);

		$author = (object) array(
			'id' => 5,
			'name' => 'Manual Author',
			'manual_post_generation_quantity' => 2,
			'scheduled_post_generation_quantity' => 1,
		);

		$result = $post_generator->generate_posts_for_author($author, null, 'manual', false);

		$this->assertSame(array(1011, 1012), $result);
		$this->assertSame(2, $topics_repository->last_limit);
		$this->assertCount(2, $post_generator->generated);
		$this->assertSame('manual', $post_generator->generated[0]['creation_method']);
	}

	/**
	 * Scheduled author runs should use the scheduled per-run quantity.
	 *
	 * @return void
	 */
	public function test_generate_posts_for_author_uses_scheduled_quantity() {
		$topics_repository = new class {
			public $last_limit = 0;

			public function get_approved_for_generation($author_id, $limit = 1, $after_id = 0) {
				$this->last_limit = $limit;

				return array(
					(object) array('id' => 21, 'topic_title' => 'Topic 21'),
					(object) array('id' => 22, 'topic_title' => 'Topic 22'),
					(object) array('id' => 23, 'topic_title' => 'Topic 23'),
				);
			}
		};

		$post_generator = new class extends AIPS_Author_Post_Generator {
			public $generated = array();

			public function generate_post_from_topic($topic, $author, $creation_method = 'manual', $trigger_context = array()) {
				$this->generated[] = (int) $topic->id;
				return 2000 + (int) $topic->id;
			}
		};

		$this->inject_property($post_generator, 'topics_repository', $topics_repository);

		$author = (object) array(
			'id' => 9,
			'name' => 'Scheduled Author',
			'manual_post_generation_quantity' => 1,
			'scheduled_post_generation_quantity' => 3,
		);

		$result = $post_generator->generate_posts_for_author($author, null, 'scheduled', false);

		$this->assertSame(array(2021, 2022, 2023), $result);
		$this->assertSame(3, $topics_repository->last_limit);
		$this->assertCount(3, $post_generator->generated);
	}

	/**
	 * Build a batching generator whose per-topic results are scripted.
	 *
	 * @param array $results Topic ID => post ID or WP_Error.
	 * @return AIPS_Author_Post_Generator
	 */
	private function make_scripted_generator(array $results) {
		$topics_repository = new class {
			public function get_approved_for_generation($author_id, $limit = 1, $after_id = 0) {
				return array(
					(object) array('id' => 41, 'topic_title' => 'Topic 41'),
					(object) array('id' => 42, 'topic_title' => 'Topic 42'),
					(object) array('id' => 43, 'topic_title' => 'Topic 43'),
				);
			}
		};

		$post_generator = new class extends AIPS_Author_Post_Generator {
			public $results = array();
			public $attempted = array();

			public function generate_post_from_topic($topic, $author, $creation_method = 'manual', $trigger_context = array()) {
				$this->attempted[] = (int) $topic->id;
				return $this->results[(int) $topic->id];
			}
		};

		$post_generator->results = $results;
		$this->inject_property($post_generator, 'topics_repository', $topics_repository);

		return $post_generator;
	}

	/**
	 * A rate limit (wrapped by the generator as missing content) stops the batch;
	 * the remaining topics are not attempted.
	 *
	 * @return void
	 */
	public function test_batch_stops_after_systemic_error() {
		$rate_limited = new WP_Error(
			'aips_generation_missing_required_content',
			'Post generation failed before a usable Post Content could be created. Cause: Rate limit exceeded.',
			array('cause_code' => 'rate_limit_exceeded')
		);

		$post_generator = $this->make_scripted_generator(array(
			41 => 5041,
			42 => $rate_limited,
			43 => 5043,
		));

		$author = (object) array('id' => 14, 'name' => 'Batch Author', 'scheduled_post_generation_quantity' => 3);
		$result = $post_generator->generate_posts_for_author($author, null, 'scheduled', false);

		$this->assertSame(array(5041), $result);
		$this->assertSame(array(41, 42), $post_generator->attempted);
	}

	/**
	 * An ordinary per-topic failure does not stop the batch.
	 *
	 * @return void
	 */
	public function test_batch_continues_after_topic_specific_error() {
		$post_generator = $this->make_scripted_generator(array(
			41 => new WP_Error('aips_generation_missing_required_content', 'Failed.', array('cause_code' => 'empty_content')),
			42 => 5042,
			43 => 5043,
		));

		$author = (object) array('id' => 15, 'name' => 'Batch Author', 'scheduled_post_generation_quantity' => 3);
		$result = $post_generator->generate_posts_for_author($author, null, 'scheduled', false);

		$this->assertSame(array(5042, 5043), $result);
		$this->assertSame(array(41, 42, 43), $post_generator->attempted);
	}

	/**
	 * Cause resolution prefers cause_code from the error data.
	 *
	 * @return void
	 */
	public function test_systemic_error_detection() {
		$this->assertTrue(AIPS_Author_Post_Generator::is_systemic_generation_error(new WP_Error('circuit_breaker_open', 'Open.')));
		$this->assertTrue(AIPS_Author_Post_Generator::is_systemic_generation_error(new WP_Error('wrapped', 'x', array('cause_code' => 'rate_limit_exceeded'))));
		$this->assertFalse(AIPS_Author_Post_Generator::is_systemic_generation_error(new WP_Error('generation_failed', 'x')));
		$this->assertSame('rate_limit_exceeded', AIPS_Author_Post_Generator::get_generation_error_cause(new WP_Error('wrapped', 'x', array('cause_code' => 'rate_limit_exceeded'))));
	}

	/**
	 * The legacy single-post API should continue returning a single post ID.
	 *
	 * @return void
	 */
	public function test_generate_post_for_author_preserves_single_post_contract() {
		$topics_repository = new class {
			public $last_limit = 0;

			public function get_approved_for_generation($author_id, $limit = 1, $after_id = 0) {
				$this->last_limit = $limit;

				return array(
					(object) array('id' => 31, 'topic_title' => 'Topic 31'),
				);
			}
		};

		$authors_repository = new class {
			public function update_post_generation_schedule($author_id, $next_run) {
				// No-op: avoid DB writes in unit tests.
			}
			public function acquire_post_generation_lock(int $author_id, int $lock_ttl = 900): bool {
				return true;
			}
			public function release_post_generation_lock(int $author_id): bool {
				return true;
			}
		};

		$post_generator = new class extends AIPS_Author_Post_Generator {
			public function generate_post_from_topic($topic, $author, $creation_method = 'manual', $trigger_context = array()) {
				return 3000 + (int) $topic->id;
			}
		};

		$this->inject_property($post_generator, 'topics_repository', $topics_repository);
		$this->inject_property($post_generator, 'authors_repository', $authors_repository);

		$author = (object) array(
			'id' => 12,
			'name' => 'Single Author',
			'manual_post_generation_quantity' => 4,
			'scheduled_post_generation_quantity' => 4,
			'post_generation_frequency' => 'daily',
			'post_generation_next_run' => time(),
		);

		$result = $post_generator->generate_post_for_author($author);

		$this->assertSame(3031, $result);
		$this->assertSame(1, $topics_repository->last_limit);
	}
}