<?php
/**
 * Test case for Calendar Controller
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Calendar_Controller extends WP_UnitTestCase {

	private $controller;
	private $schedule_repo;

	public function setUp(): void {
		parent::setUp();

		$this->controller = new AIPS_Calendar_Controller();
		$this->schedule_repo = new AIPS_Schedule_Repository();

		// Mock WP User
		$user_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($user_id);
	}

	public function tearDown(): void {
		// Clean up schedules
		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->prefix}aips_schedule");
		$wpdb->query("DELETE FROM {$wpdb->prefix}aips_templates");

		parent::tearDown();
	}

	/**
	 * Test AJAX get calendar events with valid nonce and permissions
	 */
	public function test_ajax_get_calendar_events_success() {
		$_POST['year'] = 2026;
		$_POST['month'] = 2;
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce'] = $_POST['nonce'];

		try {
			$this->controller->ajax_get_calendar_events();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}

		$output = $this->getActualOutput();
		$response = json_decode($output, true);

		$this->assertTrue($response['success']);
		$this->assertEquals(2026, $response['data']['year']);
		$this->assertEquals(2, $response['data']['month']);
		$this->assertIsArray($response['data']['events']);
	}

	/**
	 * Test AJAX get calendar events with invalid nonce
	 */
	public function test_ajax_get_calendar_events_invalid_nonce() {
		$_POST['year'] = 2026;
		$_POST['month'] = 2;
		$_POST['nonce'] = 'invalid_nonce';
		$_REQUEST['nonce'] = $_POST['nonce'];

		$this->expectException(WPAjaxDieStopException::class);
		$this->controller->ajax_get_calendar_events();
	}

	/**
	 * Test AJAX get calendar events without permissions
	 */
	public function test_ajax_get_calendar_events_no_permissions() {
		// Create a subscriber user
		$user_id = $this->factory->user->create(array('role' => 'subscriber'));
		wp_set_current_user($user_id);

		$_POST['year'] = 2026;
		$_POST['month'] = 2;
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce'] = $_POST['nonce'];

		try {
			$this->controller->ajax_get_calendar_events();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}

		$output = $this->getActualOutput();
		$response = json_decode($output, true);

		$this->assertFalse($response['success']);
		$this->assertStringContainsString('Unauthorized', $response['data']['message']);
	}

	/**
	 * Test AJAX get calendar events with invalid month
	 */
	public function test_ajax_get_calendar_events_invalid_month() {
		$_POST['year'] = 2026;
		$_POST['month'] = 13; // Invalid month
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce'] = $_POST['nonce'];

		try {
			$this->controller->ajax_get_calendar_events();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}

		$output = $this->getActualOutput();
		$response = json_decode($output, true);

		$this->assertFalse($response['success']);
		$this->assertStringContainsString('Invalid month', $response['data']['message']);
	}

	/**
	 * Test get_month_events returns events for active schedules
	 */
	public function test_get_month_events_with_active_schedules() {
		// Create a template first
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'aips_templates',
			array(
				'name' => 'Test Template',
				'content_prompt' => 'Test prompt',
				'is_active' => 1,
			)
		);
		$template_id = $wpdb->insert_id;

		// Create a daily schedule
		$this->schedule_repo->create(array(
			'template_id' => $template_id,
			'frequency' => 'daily',
			'next_run' => '2026-02-01 10:00:00',
			'is_active' => 1,
		));

		$events = $this->controller->get_month_events(2026, 2);

		$this->assertIsArray($events);
		$this->assertNotEmpty($events);

		// Daily schedule should have 28 occurrences in February 2026
		$this->assertCount(28, $events);

		// Verify event structure
		$first_event = $events[0];
		$this->assertArrayHasKey('id', $first_event);
		$this->assertArrayHasKey('title', $first_event);
		$this->assertArrayHasKey('start', $first_event);
		$this->assertArrayHasKey('template_id', $first_event);
		$this->assertArrayHasKey('frequency', $first_event);
		$this->assertEquals('daily', $first_event['frequency']);
		$this->assertEquals('2026-02-01 10:00:00', $first_event['start']);
	}

	/**
	 * Test get_month_events handles timestamp-based next_run values.
	 */
	public function test_get_month_events_with_timestamp_next_run() {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'aips_templates',
			array(
				'name' => 'Timestamp Template',
				'content_prompt' => 'Test prompt',
				'is_active' => 1,
			)
		);
		$template_id = $wpdb->insert_id;

		$this->schedule_repo->create(array(
			'template_id' => $template_id,
			'frequency' => 'daily',
			'next_run' => AIPS_DateTime::fromMysql('2026-02-01 10:00:00')->timestamp(),
			'is_active' => 1,
		));

		$events = $this->controller->get_month_events(2026, 2);

		$this->assertCount(28, $events);
		$this->assertEquals('2026-02-01 10:00:00', $events[0]['start']);
	}

	/**
	 * Test month expansion handles legacy MySQL datetime values without DB-backed repositories.
	 */
	public function test_get_month_events_expands_legacy_datetime_schedule_with_stubbed_dependencies() {
		$this->set_controller_dependencies(
			array(
				$this->build_schedule('2026-02-01 10:00:00'),
			)
		);

		$events = $this->controller->get_month_events(2026, 2);

		$this->assertCount(28, $events);
		$this->assertEquals('2026-02-01 10:00:00', $events[0]['start']);
		$this->assertEquals('2026-02-28 10:00:00', $events[27]['start']);
	}

	/**
	 * Test month expansion handles bigint timestamp next_run values without DB-backed repositories.
	 */
	public function test_get_month_events_expands_timestamp_schedule_with_stubbed_dependencies() {
		$this->set_controller_dependencies(
			array(
				$this->build_schedule(AIPS_DateTime::fromMysql('2026-02-01 10:00:00')->timestamp()),
			)
		);

		$events = $this->controller->get_month_events(2026, 2);

		$this->assertCount(28, $events);
		$this->assertEquals('2026-02-01 10:00:00', $events[0]['start']);
		$this->assertEquals('2026-02-28 10:00:00', $events[27]['start']);
	}

	/**
	 * Test get_month_events handles different frequencies correctly
	 */
	public function test_get_month_events_different_frequencies() {
		global $wpdb;
		
		// Create a template
		$wpdb->insert(
			$wpdb->prefix . 'aips_templates',
			array(
				'name' => 'Test Template',
				'content_prompt' => 'Test prompt',
				'is_active' => 1,
			)
		);
		$template_id = $wpdb->insert_id;

		// Test hourly frequency (should have many occurrences)
		$this->schedule_repo->create(array(
			'template_id' => $template_id,
			'frequency' => 'hourly',
			'next_run' => '2026-02-01 00:00:00',
			'is_active' => 1,
		));

		$events = $this->controller->get_month_events(2026, 2);

		// February 2026 has 28 days = 672 hours, so should have 672 hourly events
		// (or up to the max occurrences limit of 1000)
		$this->assertGreaterThan(500, count($events), 'Hourly schedule should generate many events');
	}

	/**
	 * Test get_month_events handles schedules with next_run before the month
	 */
	public function test_get_month_events_with_past_next_run() {
		global $wpdb;
		
		// Create a template
		$wpdb->insert(
			$wpdb->prefix . 'aips_templates',
			array(
				'name' => 'Test Template',
				'content_prompt' => 'Test prompt',
				'is_active' => 1,
			)
		);
		$template_id = $wpdb->insert_id;

		// Create a weekly schedule that started in January
		$this->schedule_repo->create(array(
			'template_id' => $template_id,
			'frequency' => 'weekly',
			'next_run' => '2026-01-01 10:00:00',
			'is_active' => 1,
		));

		$events = $this->controller->get_month_events(2026, 2);

		$this->assertIsArray($events);
		$this->assertNotEmpty($events);

		// Weekly schedule should have 4 occurrences in February 2026
		$this->assertEquals(4, count($events));
	}

	/**
	 * Test that inactive schedules are not included
	 */
	public function test_get_month_events_excludes_inactive_schedules() {
		global $wpdb;
		
		// Create a template
		$wpdb->insert(
			$wpdb->prefix . 'aips_templates',
			array(
				'name' => 'Test Template',
				'content_prompt' => 'Test prompt',
				'is_active' => 1,
			)
		);
		$template_id = $wpdb->insert_id;

		// Create an inactive daily schedule
		$this->schedule_repo->create(array(
			'template_id' => $template_id,
			'frequency' => 'daily',
			'next_run' => '2026-02-01 10:00:00',
			'is_active' => 0,
		));

		$events = $this->controller->get_month_events(2026, 2);

		$this->assertIsArray($events);
		$this->assertEmpty($events, 'Inactive schedules should not generate events');
	}

	/**
	 * Test that author and category are resolved from template metadata
	 */
	public function test_get_month_events_resolves_author_and_category() {
		global $wpdb;
		
		// Create a test user
		$user_id = $this->factory->user->create(array(
			'display_name' => 'Test Author',
			'user_login' => 'testauthor',
		));
		
		// Create a test category
		$category_id = $this->factory->category->create(array(
			'name' => 'Test Category',
			'slug' => 'test-category',
		));
		
		// Create a template with author and category
		$wpdb->insert(
			$wpdb->prefix . 'aips_templates',
			array(
				'name' => 'Template with Metadata',
				'content_prompt' => 'Test prompt',
				'post_author' => $user_id,
				'post_category' => $category_id,
				'is_active' => 1,
			)
		);
		$template_id = $wpdb->insert_id;

		// Create a daily schedule
		$this->schedule_repo->create(array(
			'template_id' => $template_id,
			'frequency' => 'daily',
			'next_run' => '2026-02-01 10:00:00',
			'is_active' => 1,
		));

		$events = $this->controller->get_month_events(2026, 2);

		$this->assertNotEmpty($events);
		$first_event = $events[0];
		
		// Verify author and category are resolved
		$this->assertEquals('Test Author', $first_event['author']);
		$this->assertEquals('Test Category', $first_event['category']);
	}

	private function set_controller_dependencies(array $schedules) {
		$schedule_repo = new class($schedules) {
			private $schedules;

			public function __construct($schedules) {
				$this->schedules = $schedules;
			}

			public function get_all($active_only = false) {
				return $this->schedules;
			}
		};

		$template_repo = new class() {
			public function get_by_id($template_id) {
				return null;
			}
		};

		$this->set_private_property($this->controller, 'schedule_repo', $schedule_repo);
		$this->set_private_property($this->controller, 'template_repo', $template_repo);
		$this->set_private_property($this->controller, 'interval_calculator', new AIPS_Interval_Calculator());
	}

	private function build_schedule($next_run) {
		return (object) array(
			'id' => 1,
			'template_name' => 'Stub Template',
			'template_id' => null,
			'frequency' => 'daily',
			'next_run' => $next_run,
			'topic' => '',
		);
	}

	private function set_private_property($object, $property_name, $value) {
		$reflection = new ReflectionProperty($object, $property_name);
		$reflection->setAccessible(true);
		$reflection->setValue($object, $value);
	}

	private function getActualOutput() {
		$output = ob_get_contents();

		if ($output === false) {
			return '';
		}

		ob_clean();

		return $output;
	}
}
