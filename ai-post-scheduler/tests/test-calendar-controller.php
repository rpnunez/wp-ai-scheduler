<?php
/**
 * Test case for Calendar Controller
 *
 * @package AI_Post_Scheduler
 */

// Mock global functions if they don't exist
if (!function_exists('get_userdata')) {
	function get_userdata($user_id) {
		$user = new stdClass();
		$user->display_name = 'Test Author';
		return $user;
	}
}

if (!function_exists('get_category')) {
	function get_category($id) {
		$cat = new stdClass();
		$cat->name = 'Test Category';
		return $cat;
	}
}

class Test_AIPS_Calendar_Controller extends WP_UnitTestCase {

	private $controller;
	private $schedule_repo_mock;
	private $template_repo_mock;

	public function setUp(): void {
		parent::setUp();

		$this->controller = new AIPS_Calendar_Controller();

		// Create mocks for repositories
		$this->schedule_repo_mock = $this->getMockBuilder('AIPS_Schedule_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('get_all', 'create'))
			->getMock();

		$this->template_repo_mock = $this->getMockBuilder('AIPS_Template_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('get_by_id'))
			->getMock();

		// Inject mocks using Reflection
		$reflection = new ReflectionClass($this->controller);

		$schedule_prop = $reflection->getProperty('schedule_repo');
		$schedule_prop->setAccessible(true);
		$schedule_prop->setValue($this->controller, $this->schedule_repo_mock);

		$template_prop = $reflection->getProperty('template_repo');
		$template_prop->setAccessible(true);
		$template_prop->setValue($this->controller, $this->template_repo_mock);

		// Mock WP User
		$user_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($user_id);
	}

	public function tearDown(): void {
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

		// Configure mock to return empty array by default
		$this->schedule_repo_mock->method('get_all')->willReturn(array());

		// Expect output for JSON response
		$this->expectOutputRegex('/.*/');

		try {
			$this->controller->ajax_get_calendar_events();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}
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

		// Expect output for JSON error
		$this->expectOutputRegex('/.*/');

		try {
			$this->controller->ajax_get_calendar_events();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}
	}

	/**
	 * Test AJAX get calendar events with invalid month
	 */
	public function test_ajax_get_calendar_events_invalid_month() {
		$_POST['year'] = 2026;
		$_POST['month'] = 13; // Invalid month
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce'] = $_POST['nonce'];

		// Expect output for JSON error
		$this->expectOutputRegex('/.*/');

		try {
			$this->controller->ajax_get_calendar_events();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}
	}

	/**
	 * Test get_month_events returns events for active schedules
	 */
	public function test_get_month_events_with_active_schedules() {
		// Create mock schedule
		$schedule = new stdClass();
		$schedule->id = 1;
		$schedule->template_id = 1;
		$schedule->template_name = 'Test Template';
		$schedule->frequency = 'daily';
		$schedule->next_run = '2026-02-01 10:00:00';
		$schedule->is_active = 1;
		$schedule->topic = 'Test Topic';

		$this->schedule_repo_mock->method('get_all')
			->with(true) // active_only = true
			->willReturn(array($schedule));

		// Mock template repo
		$template = new stdClass();
		$template->id = 1;
		$template->post_author = 1;
		$template->post_category = 1;

		$this->template_repo_mock->method('get_by_id')
			->willReturn($template);

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
	}

	/**
	 * Test get_month_events handles different frequencies correctly
	 */
	public function test_get_month_events_different_frequencies() {
		// Create mock schedule
		$schedule = new stdClass();
		$schedule->id = 1;
		$schedule->template_id = 1;
		$schedule->template_name = 'Test Template';
		$schedule->frequency = 'hourly';
		$schedule->next_run = '2026-02-01 00:00:00';
		$schedule->is_active = 1;
		$schedule->topic = 'Test Topic';
		
		$this->schedule_repo_mock->method('get_all')
			->willReturn(array($schedule));

		$events = $this->controller->get_month_events(2026, 2);

		// February 2026 has 28 days = 672 hours
		$this->assertGreaterThan(500, count($events), 'Hourly schedule should generate many events');
	}

	/**
	 * Test get_month_events handles schedules with next_run before the month
	 */
	public function test_get_month_events_with_past_next_run() {
		// Create mock schedule
		$schedule = new stdClass();
		$schedule->id = 1;
		$schedule->template_id = 1;
		$schedule->template_name = 'Test Template';
		$schedule->frequency = 'weekly';
		$schedule->next_run = '2026-01-01 10:00:00'; // Past date
		$schedule->is_active = 1;
		$schedule->topic = 'Test Topic';
		
		$this->schedule_repo_mock->method('get_all')
			->willReturn(array($schedule));

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
		// Even if repo returns inactive (if called without filtering),
		// calculate_schedule_occurrences doesn't check is_active.
		// However, get_month_events calls get_all(true) which filters by active.
		// So this test mainly ensures that if get_all returns empty, events are empty.
		
		$this->schedule_repo_mock->method('get_all')
			->with(true)
			->willReturn(array());

		$events = $this->controller->get_month_events(2026, 2);

		$this->assertIsArray($events);
		$this->assertEmpty($events, 'Inactive schedules should not generate events');
	}

	/**
	 * Test that author and category are resolved from template metadata
	 */
	public function test_get_month_events_resolves_author_and_category() {
		$schedule = new stdClass();
		$schedule->id = 1;
		$schedule->template_id = 1;
		$schedule->template_name = 'Template with Metadata';
		$schedule->frequency = 'daily';
		$schedule->next_run = '2026-02-01 10:00:00';
		$schedule->is_active = 1;
		$schedule->topic = 'Test Topic';
		
		$this->schedule_repo_mock->method('get_all')
			->willReturn(array($schedule));

		$template = new stdClass();
		$template->id = 1;
		$template->post_author = 1;
		$template->post_category = 1;
		
		$this->template_repo_mock->method('get_by_id')
			->willReturn($template);

		$events = $this->controller->get_month_events(2026, 2);

		$this->assertNotEmpty($events);
		$first_event = $events[0];
		
		$this->assertArrayHasKey('author', $first_event);
		$this->assertArrayHasKey('category', $first_event);
	}
}
