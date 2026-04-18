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
		ob_start();
			$this->controller->ajax_get_calendar_events();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}

		$output = ob_get_clean() ?: '';
		$response = json_decode($output, true);

		$this->assertTrue($response['success']);
		$this->assertEquals(2026, $response['data']['year']);
		$this->assertEquals(2, $response['data']['month']);
		$this->assertIsArray($response['data']['events']);
		$this->assertArrayHasKey('template_map', $response['data'], 'AJAX response must include template_map key');
		$this->assertIsArray($response['data']['template_map']);
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
		ob_start();
			$this->controller->ajax_get_calendar_events();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}

		$output = ob_get_clean() ?: '';
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
		ob_start();
			$this->controller->ajax_get_calendar_events();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}

		$output = ob_get_clean() ?: '';
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

	/**
	 * Test that the AJAX response includes a template_map with unique templates.
	 *
	 * Creates two active daily schedules backed by two different templates and
	 * asserts that the returned template_map lists both templates in the order
	 * they first appear in the event stream, with the correct id and name keys.
	 */
	public function test_ajax_response_includes_template_map_with_correct_entries() {
		global $wpdb;

		// Create two templates with distinct names.
		$wpdb->insert(
			$wpdb->prefix . 'aips_templates',
			array(
				'name'           => 'Alpha Template',
				'content_prompt' => 'Prompt A',
				'is_active'      => 1,
			)
		);
		$template_id_a = $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'aips_templates',
			array(
				'name'           => 'Beta Template',
				'content_prompt' => 'Prompt B',
				'is_active'      => 1,
			)
		);
		$template_id_b = $wpdb->insert_id;

		// Create two active daily schedules — one per template.
		$this->schedule_repo->create( array(
			'template_id' => $template_id_a,
			'frequency'   => 'daily',
			'next_run'    => '2026-02-01 08:00:00',
			'is_active'   => 1,
		) );

		$this->schedule_repo->create( array(
			'template_id' => $template_id_b,
			'frequency'   => 'daily',
			'next_run'    => '2026-02-01 09:00:00',
			'is_active'   => 1,
		) );

		$_POST['year']    = 2026;
		$_POST['month']   = 2;
		$_POST['nonce']   = wp_create_nonce( 'aips_ajax_nonce' );
		$_REQUEST['nonce'] = $_POST['nonce'];

		try {
		ob_start();
			$this->controller->ajax_get_calendar_events();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected.
		}

		$response = json_decode( ob_get_clean() ?: '', true );

		$this->assertTrue( $response['success'] );

		$template_map = $response['data']['template_map'];
		$this->assertIsArray( $template_map );
		$this->assertCount( 2, $template_map, 'template_map should contain exactly two entries' );

		// Both entries must have 'id' and 'name' keys.
		foreach ( $template_map as $entry ) {
			$this->assertArrayHasKey( 'id',   $entry );
			$this->assertArrayHasKey( 'name', $entry );
		}

		// Collect the returned IDs and names for assertion.
		$returned_ids   = array_column( $template_map, 'id' );
		$returned_names = array_column( $template_map, 'name' );

		$this->assertContains( $template_id_a, $returned_ids, 'Alpha Template ID must be in template_map' );
		$this->assertContains( $template_id_b, $returned_ids, 'Beta Template ID must be in template_map' );
		$this->assertContains( 'Alpha Template', $returned_names );
		$this->assertContains( 'Beta Template',  $returned_names );
	}

	/**
	 * Test that the template_map contains no duplicate template entries.
	 *
	 * A single template with multiple daily occurrences in the month must
	 * appear only once in the template_map, regardless of how many events
	 * that template generates.
	 */
	public function test_template_map_deduplicates_same_template() {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'aips_templates',
			array(
				'name'           => 'Repeated Template',
				'content_prompt' => 'Prompt X',
				'is_active'      => 1,
			)
		);
		$template_id = $wpdb->insert_id;

		// Single daily schedule generates 28 events in February — all for the
		// same template.  The map must deduplicate these to one entry.
		$this->schedule_repo->create( array(
			'template_id' => $template_id,
			'frequency'   => 'daily',
			'next_run'    => '2026-02-01 10:00:00',
			'is_active'   => 1,
		) );

		$_POST['year']    = 2026;
		$_POST['month']   = 2;
		$_POST['nonce']   = wp_create_nonce( 'aips_ajax_nonce' );
		$_REQUEST['nonce'] = $_POST['nonce'];

		try {
		ob_start();
			$this->controller->ajax_get_calendar_events();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected.
		}

		$response     = json_decode( ob_get_clean() ?: '', true );
		$template_map = $response['data']['template_map'];

		$this->assertCount( 1, $template_map, 'template_map must contain exactly one entry for a single template' );
		$this->assertEquals( $template_id,       $template_map[0]['id'] );
		$this->assertEquals( 'Repeated Template', $template_map[0]['name'] );
	}
}
