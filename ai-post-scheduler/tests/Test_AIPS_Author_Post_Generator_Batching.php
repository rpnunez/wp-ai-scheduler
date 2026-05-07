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

			public function generate_post_from_topic($topic, $author, $creation_method = 'manual') {
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

			public function generate_post_from_topic($topic, $author, $creation_method = 'manual') {
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

		$post_generator = new class extends AIPS_Author_Post_Generator {
			public function generate_post_from_topic($topic, $author, $creation_method = 'manual') {
				return 3000 + (int) $topic->id;
			}
		};

		$this->inject_property($post_generator, 'topics_repository', $topics_repository);

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