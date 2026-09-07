<?php
/**
 * Tests for REST migration slice 1: read-only reporters.
 *
 *   GET /aips/v1/dashboard
 *   GET /aips/v1/telemetry, GET /aips/v1/telemetry/{id}
 *   GET /aips/v1/calendar/events
 *
 * Also guards registry completeness: every AIPS_Rest_Controller subclass in
 * includes/ must be mapped in AIPS_Rest_Registry.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

class Test_AIPS_Rest_Readers extends WP_UnitTestCase {

	/** @var WP_REST_Server */
	private $server;

	public function setUp(): void {
		parent::setUp();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		add_action('rest_api_init', array($this, 'register_all_plugin_routes'));
		do_action('rest_api_init', $this->server);
	}

	public function tearDown(): void {
		remove_action('rest_api_init', array($this, 'register_all_plugin_routes'));

		global $wp_rest_server;
		$wp_rest_server = null;

		delete_option('aips_enable_telemetry');

		parent::tearDown();
	}

	public function register_all_plugin_routes() {
		foreach (AIPS_Rest_Registry::all_controllers() as $class) {
			$controller = new $class();
			$controller->register_routes();
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function get($path, array $params = array()) {
		$request = new WP_REST_Request('GET', '/' . AIPS_Rest_Registry::NAMESPACE_V1 . '/' . ltrim($path, '/'));
		foreach ($params as $key => $value) {
			$request->set_param($key, $value);
		}
		return $this->server->dispatch($request);
	}

	private function as_admin() {
		wp_set_current_user(self::factory()->user->create(array('role' => 'administrator')));
	}

	private function enable_telemetry($enabled) {
		update_option('aips_enable_telemetry', $enabled ? 1 : 0);

		// AIPS_Config caches option reads per request (and persistently for a
		// few critical options); clear both so the new value is observed.
		AIPS_Config::get_instance()->flush_option_cache();
		$cache = AIPS_Cache_Factory::instance();
		if ($cache->is_available() && method_exists($cache, 'delete')) {
			$cache->delete('aips_enable_telemetry');
		}
	}

	// -------------------------------------------------------------------------
	// Registry completeness
	// -------------------------------------------------------------------------

	public function test_every_rest_controller_class_is_registered() {
		$registered = AIPS_Rest_Registry::all_controllers();
		$missing    = array();

		foreach (glob(AIPS_PLUGIN_DIR . 'includes/*.php') as $file) {
			$content = file_get_contents($file);
			if (preg_match_all('/class\s+(AIPS_\w+)\s+extends\s+AIPS_Rest_Controller\b/', $content, $matches)) {
				foreach ($matches[1] as $class) {
					if (!in_array($class, $registered, true)) {
						$missing[] = $class . ' (' . basename($file) . ')';
					}
				}
			}
		}

		$this->assertSame(array(), $missing, 'Unregistered REST controllers: ' . implode(', ', $missing));
	}

	public function test_slice_one_resources_are_mapped() {
		$this->assertSame('AIPS_Dashboard_Rest_Controller', AIPS_Rest_Registry::get_controller_for('dashboard'));
		$this->assertSame('AIPS_Telemetry_Rest_Controller', AIPS_Rest_Registry::get_controller_for('telemetry'));
		$this->assertSame('AIPS_Calendar_Rest_Controller', AIPS_Rest_Registry::get_controller_for('calendar'));
	}

	public function test_routes_registered() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey('/aips/v1/dashboard', $routes);
		$this->assertArrayHasKey('/aips/v1/telemetry', $routes);
		$this->assertArrayHasKey('/aips/v1/telemetry/(?P<id>[\d]+)', $routes);
		$this->assertArrayHasKey('/aips/v1/calendar/events', $routes);
	}

	// -------------------------------------------------------------------------
	// Dashboard
	// -------------------------------------------------------------------------

	public function test_dashboard_requires_auth() {
		wp_set_current_user(0);
		$this->assertSame(401, $this->get('dashboard')->get_status());
	}

	public function test_dashboard_returns_payload_with_expected_keys() {
		$this->as_admin();
		$response = $this->get('dashboard');
		$data     = $response->get_data();

		$this->assertSame(200, $response->get_status());
		$this->assertArrayNotHasKey('success', $data);

		foreach (array(
			'date_from', 'date_to', 'date_from_formatted', 'date_to_formatted',
			'completed_in_period', 'failed_in_period', 'partial_in_period', 'success_rate_in_period',
			'topics_created_in_period', 'topics_pending_in_period',
			'ai_calls_in_period', 'ai_errors_in_period', 'ai_error_rate_in_period',
			'recent_posts', 'recent_topics', 'posts_by_topic', 'executed_schedules', 'chart_data',
		) as $key) {
			$this->assertArrayHasKey($key, $data, "Missing key: {$key}");
		}

		foreach (array('labels', 'completed', 'failed', 'errorRate', 'topics', 'aiCalls', 'aiErrors') as $series) {
			$this->assertArrayHasKey($series, $data['chart_data']);
		}
	}

	public function test_dashboard_honours_explicit_range_and_fills_every_day() {
		$this->as_admin();
		$data = $this->get('dashboard', array('date_from' => '2026-03-01', 'date_to' => '2026-03-10'))->get_data();

		$this->assertSame('2026-03-01', $data['date_from']);
		$this->assertSame('2026-03-10', $data['date_to']);
		$this->assertCount(10, $data['chart_data']['labels']);
		$this->assertSame('Mar 1', $data['chart_data']['labels'][0]);
	}

	public function test_dashboard_falls_back_on_invalid_dates() {
		$this->as_admin();
		$data = $this->get('dashboard', array('date_from' => 'nope', 'date_to' => '2026-13-45'))->get_data();

		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-01$/', $data['date_from']);
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $data['date_to']);
	}

	public function test_dashboard_clamps_range_to_max_days() {
		$this->as_admin();
		$data = $this->get('dashboard', array('date_from' => '2020-01-01', 'date_to' => '2026-01-01'))->get_data();

		$this->assertCount(AIPS_Dashboard_Metrics_Service::MAX_DATE_RANGE_DAYS, $data['chart_data']['labels']);
		$this->assertSame('2026-01-01', $data['date_to']);
	}

	public function test_dashboard_ajax_and_rest_return_identical_shape() {
		$this->as_admin();
		$rest = $this->get('dashboard', array('date_from' => '2026-02-01', 'date_to' => '2026-02-05'))->get_data();

		$service = new AIPS_Dashboard_Metrics_Service();
		$direct  = $service->get_data('2026-02-01', '2026-02-05');

		$this->assertSame(array_keys($direct), array_keys($rest));
	}

	// -------------------------------------------------------------------------
	// Telemetry
	// -------------------------------------------------------------------------

	public function test_telemetry_requires_auth() {
		$this->enable_telemetry(true);
		wp_set_current_user(0);
		$this->assertSame(401, $this->get('telemetry')->get_status());
	}

	public function test_telemetry_disabled_returns_403_feature_disabled() {
		$this->enable_telemetry(false);
		$this->as_admin();

		$response = $this->get('telemetry');
		$this->assertSame(403, $response->get_status());
		$this->assertSame('aips_feature_disabled', $response->get_data()['code']);

		$details = $this->get('telemetry/1');
		$this->assertSame(403, $details->get_status());
	}

	public function test_telemetry_report_shape_and_pagination_headers() {
		$this->enable_telemetry(true);
		$this->as_admin();

		$response = $this->get('telemetry', array('per_page' => 50, 'page' => 1));
		$data     = $response->get_data();

		$this->assertSame(200, $response->get_status());
		foreach (array('rows', 'total', 'per_page', 'total_pages', 'page', 'start_date', 'end_date', 'filters', 'charts') as $key) {
			$this->assertArrayHasKey($key, $data, "Missing key: {$key}");
		}
		$this->assertSame(50, $data['per_page']);
		$this->assertSame(1, $data['page']);

		$headers = $response->get_headers();
		$this->assertArrayHasKey('X-WP-Total', $headers);
		$this->assertArrayHasKey('X-WP-TotalPages', $headers);
		$this->assertSame($data['total'], $headers['X-WP-Total']);
	}

	public function test_telemetry_rejects_disallowed_per_page_and_filters() {
		$this->enable_telemetry(true);
		$this->as_admin();

		$this->assertSame(400, $this->get('telemetry', array('per_page' => 33))->get_status());
		$this->assertSame(400, $this->get('telemetry', array('type' => 'bogus'))->get_status());
		$this->assertSame(400, $this->get('telemetry', array('request_method' => 'TRACE'))->get_status());
		$this->assertSame(200, $this->get('telemetry', array('type' => 'cron', 'request_method' => 'GET'))->get_status());
	}

	public function test_telemetry_swaps_reversed_dates_and_falls_back_on_invalid() {
		$this->enable_telemetry(true);
		$this->as_admin();

		$swapped = $this->get('telemetry', array('start_date' => '2026-02-10', 'end_date' => '2026-02-01'))->get_data();
		$this->assertSame('2026-02-01', $swapped['start_date']);
		$this->assertSame('2026-02-10', $swapped['end_date']);

		$fallback = $this->get('telemetry', array('start_date' => 'garbage'))->get_data();
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $fallback['start_date']);
	}

	public function test_telemetry_issues_only_is_coerced_to_boolean() {
		$this->enable_telemetry(true);
		$this->as_admin();

		$this->assertTrue($this->get('telemetry', array('issues_only' => 'true'))->get_data()['filters']['issues_only']);
		$this->assertFalse($this->get('telemetry')->get_data()['filters']['issues_only']);
	}

	public function test_telemetry_details_unknown_row_is_404() {
		$this->enable_telemetry(true);
		$this->as_admin();

		$response = $this->get('telemetry/999999999');
		$this->assertSame(404, $response->get_status());
		$this->assertSame('aips_not_found', $response->get_data()['code']);
	}

	// -------------------------------------------------------------------------
	// Calendar
	// -------------------------------------------------------------------------

	public function test_calendar_requires_auth() {
		wp_set_current_user(0);
		$this->assertSame(401, $this->get('calendar/events')->get_status());
	}

	public function test_calendar_defaults_to_current_month() {
		$this->as_admin();
		$response = $this->get('calendar/events');
		$data     = $response->get_data();

		$this->assertSame(200, $response->get_status());
		$this->assertIsArray($data['events']);
		$this->assertSame((int) AIPS_DateTime::now()->toDisplay('Y'), $data['year']);
		$this->assertSame((int) AIPS_DateTime::now()->toDisplay('n'), $data['month']);
	}

	public function test_calendar_echoes_requested_month() {
		$this->as_admin();
		$data = $this->get('calendar/events', array('year' => 2026, 'month' => 7))->get_data();

		$this->assertSame(2026, $data['year']);
		$this->assertSame(7, $data['month']);
	}

	public function test_calendar_rejects_invalid_month_via_schema() {
		$this->as_admin();

		$this->assertSame(400, $this->get('calendar/events', array('month' => 13))->get_status());
		$this->assertSame(400, $this->get('calendar/events', array('month' => 0))->get_status());
		$this->assertSame(400, $this->get('calendar/events', array('year' => 1800))->get_status());
	}

	public function test_calendar_controller_can_skip_ajax_hook_registration() {
		$before = has_action('wp_ajax_aips_get_calendar_events');
		new AIPS_Calendar_Controller(false);
		$this->assertSame($before, has_action('wp_ajax_aips_get_calendar_events'));
	}
}
