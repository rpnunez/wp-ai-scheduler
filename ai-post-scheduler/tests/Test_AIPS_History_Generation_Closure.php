<?php
/**
 * Tests that generate_post_from_topic closes its History wrapper on every path,
 * and that the 3.7.12 / 3.7.13 History migrations repair existing rows.
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

class Test_AIPS_History_Generation_Closure extends WP_UnitTestCase {

	/**
	 * Build an author post generator whose collaborators are fakes, but whose
	 * History service is the real one so the wrapper row lands in the database.
	 *
	 * @param callable $generate Callback standing in for AIPS_Generator::generate_post().
	 * @return AIPS_Author_Post_Generator
	 */
	private function make_post_generator(callable $generate) {
		$evaluator = new class {
			public function get_expanded_context($author_id, $topic_id, $limit = 5) {
				return '';
			}
		};
		AIPS_Container::get_instance()->bind(AIPS_Similarity_Evaluator::class, function () use ($evaluator) {
			return $evaluator;
		});

		$generator = new class($generate) {
			private $generate;

			public function __construct($generate) {
				$this->generate = $generate;
			}

			public function generate_post($context) {
				return call_user_func($this->generate, $context);
			}
		};

		$logger = new class {
			public function log($message, $level = 'info', $context = array()) {}
		};

		$authors = new class {
			public function update_post_generation_last_run($author_id, $timestamp) {}
		};

		$logs = new class {
			public function log_post_generation($topic_id, $post_id, $metadata) {}
		};

		$topics = new class {
			public function claim_topic_for_generation($topic_id, $timeout = 900, $allow_regenerate = false) {
				return true;
			}
			public function release_topic_claim($topic_id) {}
			public function invalidate_topic_cache($topic_id, $author_id) {}
		};

		$post_generator = new AIPS_Author_Post_Generator();
		$reflection     = new ReflectionClass($post_generator);
		foreach (array(
			'generator'          => $generator,
			'logger'             => $logger,
			'authors_repository' => $authors,
			'logs_repository'    => $logs,
			'topics_repository'  => $topics,
			'history_service'    => new AIPS_History_Service(new AIPS_History_Repository()),
		) as $name => $value) {
			$property = $reflection->getProperty($name);
			$property->setAccessible(true);
			$property->setValue($post_generator, $value);
		}

		return $post_generator;
	}

	private function wrapper_row() {
		global $wpdb;

		return $wpdb->get_row("SELECT * FROM {$wpdb->prefix}aips_history WHERE creation_method = 'topic_post_generation' ORDER BY id DESC LIMIT 1");
	}

	private function topic() {
		return (object) array('id' => 5, 'author_id' => 1, 'topic_title' => 'Clean Code', 'status' => 'approved');
	}

	private function author() {
		return (object) array(
			'id' => 1,
			'name' => 'Test Author',
			'field_niche' => 'Software Development',
			'generate_featured_image' => 0,
			'featured_image_source' => 'ai_prompt',
			'post_status' => 'draft',
			'post_category' => 1,
			'post_tags' => '',
			'post_author' => 1,
			'article_structure_id' => null,
			'is_active' => 1,
		);
	}

	// -- Wrapper closes on every path ----------------------------------------------------

	public function test_wrapper_is_completed_when_generation_succeeds() {
		$post_generator = $this->make_post_generator(function () {
			return 456;
		});

		$this->assertSame(456, $post_generator->generate_post_from_topic($this->topic(), $this->author()));

		$row = $this->wrapper_row();
		$this->assertSame('completed', $row->status);
		$this->assertGreaterThan(0, (int) $row->completed_at);
	}

	public function test_wrapper_is_failed_when_generation_returns_a_wp_error() {
		$post_generator = $this->make_post_generator(function () {
			return new WP_Error('ai_failed', 'The AI call failed.');
		});

		$result = $post_generator->generate_post_from_topic($this->topic(), $this->author());

		$this->assertWPError($result);
		$row = $this->wrapper_row();
		$this->assertSame('failed', $row->status);
		$this->assertSame('The AI call failed.', $row->error_message);
	}

	public function test_wrapper_is_failed_when_generation_throws() {
		$post_generator = $this->make_post_generator(function () {
			throw new Exception('Boom');
		});

		$result = $post_generator->generate_post_from_topic($this->topic(), $this->author());

		$this->assertWPError($result);
		$row = $this->wrapper_row();
		$this->assertSame('failed', $row->status);
		$this->assertSame('Boom', $row->error_message);
	}

	// -- Migrations ------------------------------------------------------------------------

	private function insert_row($creation_method, array $metadata, $status = 'processing', $event_status = 'success', $age = 0) {
		global $wpdb;

		$service   = new AIPS_History_Service(new AIPS_History_Repository());
		$container = $service->create($creation_method, array_merge(array('creation_method' => $creation_method), $metadata));
		$container->record('activity', 'x', array('event_type' => 'e', 'event_status' => $event_status));

		$id   = (int) $container->get_id();
		$then = AIPS_DateTime::now()->timestamp() - $age;
		$wpdb->update($wpdb->prefix . 'aips_history', array('status' => $status, 'created_at' => $then), array('id' => $id));
		$wpdb->update($wpdb->prefix . 'aips_history_log', array('timestamp' => $then), array('history_id' => $id));

		return $id;
	}

	private function status_of($id) {
		global $wpdb;

		return $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}aips_history WHERE id = %d", $id));
	}

	public function test_history_migrations_repair_existing_rows() {
		if (!defined('ABSPATH') || !file_exists(ABSPATH . 'wp-admin/includes/upgrade.php')) {
			$this->markTestSkipped('Database migration tests require the full WordPress test library.');
		}

		$incomplete = self::factory()->post->create(array('post_status' => 'draft'));
		update_post_meta($incomplete, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, 'true');

		$orphan_ok          = $this->insert_row('post_review_action', array('post_id' => 1), 'processing', 'success');
		$orphan_failed      = $this->insert_row('post_review_action', array('post_id' => 2), 'processing', 'failed');
		$abandoned          = $this->insert_row('author_embeddings', array('author_id' => 1), 'processing', 'success', 3 * DAY_IN_SECONDS);
		$active             = $this->insert_row('author_embeddings', array('author_id' => 2), 'processing', 'success', HOUR_IN_SECONDS);
		$template_lifecycle = $this->insert_row('template_lifecycle', array('template_id' => 1), 'processing', 'success', 30 * DAY_IN_SECONDS);
		$schedule_lifecycle = $this->insert_row('schedule_lifecycle', array('schedule_id' => 1), 'processing', 'success', 30 * DAY_IN_SECONDS);
		$schedule_exec_fail = $this->insert_row('schedule_execution', array('schedule_id' => 1), 'processing', 'failed');
		$generation         = $this->insert_row('post_generation', array('post_id' => $incomplete), 'completed');
		$clean              = $this->insert_row('post_generation', array('post_id' => self::factory()->post->create()), 'completed');

		AIPS_Config::get_instance()->set_option('aips_db_version', '3.7.11');
		AIPS_DB_Migrations::check_and_run();

		// 3.7.12: orphaned event rows close from their logged outcome.
		$this->assertSame('completed', $this->status_of($orphan_ok));
		$this->assertSame('failed', $this->status_of($orphan_failed));
		// 3.7.12: a row idle for days is abandoned; recent and excluded lifecycle rows are not.
		$this->assertSame('terminated', $this->status_of($abandoned));
		$this->assertSame('processing', $this->status_of($active));
		$this->assertSame('processing', $this->status_of($template_lifecycle));
		// 3.7.19: orphaned schedule_lifecycle and schedule_execution rows close from their logged outcome.
		$this->assertSame('completed', $this->status_of($schedule_lifecycle));
		$this->assertSame('failed', $this->status_of($schedule_exec_fail));
		// 3.7.13: incomplete generations become partial.
		$this->assertSame('partial', $this->status_of($generation));
		$this->assertSame('completed', $this->status_of($clean));
	}
}
