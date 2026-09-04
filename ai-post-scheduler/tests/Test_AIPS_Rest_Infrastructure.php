<?php
/**
 * Tests for the REST API infrastructure (migration slice 0).
 *
 * Covers:
 *   1. AIPS_Rest_Registry route → controller resolution and request detection.
 *   2. AIPS_Rest_Controller permission gating (401 / 403 / 200) through the
 *      real WP_REST_Server dispatch path.
 *   3. AIPS_Rest_Controller response and error envelope contract.
 *   4. Shared arg-schema helpers (id, pagination, ids) sanitize/validate.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

/**
 * Minimal concrete controller exercising every base-class helper.
 */
class AIPS_Test_Echo_Rest_Controller extends AIPS_Rest_Controller {

	protected $rest_base = 'echo';

	public function register_routes() {
		register_rest_route($this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'list_items'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->pagination_args(10, 50),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'create_item_cb'),
				'permission_callback' => array($this, 'permission_check'),
			),
		));

		register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_item_cb'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->id_arg(),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array($this, 'delete_item_cb'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->id_arg(),
			),
		));

		register_rest_route($this->namespace, '/' . $this->rest_base . '/bulk', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array($this, 'bulk_cb'),
			'permission_callback' => $this->capability_callback('edit_posts'),
			'args'                => $this->ids_arg(),
		));
	}

	public function list_items($request) {
		return $this->respond_collection(
			array(array('id' => 1), array('id' => 2)),
			45,
			$request->get_param('per_page')
		);
	}

	public function create_item_cb($request) {
		return $this->respond_created(array('id' => 99, 'name' => $request->get_param('name')));
	}

	public function get_item_cb($request) {
		$id = $request->get_param('id');
		if (404 === $id) {
			return $this->error_not_found('Widget');
		}
		if (409 === $id) {
			return $this->error_conflict('Already exists', array('field' => 'name'));
		}
		if (500 === $id) {
			return $this->error_server();
		}
		if (400 === $id) {
			return $this->error_invalid_request('Bad widget', array('errors' => array('name' => 'required')));
		}
		return $this->respond(array('id' => $id, 'id_type' => gettype($id)));
	}

	public function delete_item_cb() {
		return $this->respond_no_content();
	}

	public function bulk_cb($request) {
		return $this->respond(array('ids' => $request->get_param('ids')));
	}
}

class Test_AIPS_Rest_Infrastructure extends WP_UnitTestCase {

	/** @var WP_REST_Server */
	private $server;

	public function setUp(): void {
		parent::setUp();

		AIPS_Rest_Registry::register('echo', 'AIPS_Test_Echo_Rest_Controller');

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		add_action('rest_api_init', array($this, 'register_test_routes'));
		do_action('rest_api_init', $this->server);
	}

	public function tearDown(): void {
		remove_action('rest_api_init', array($this, 'register_test_routes'));
		AIPS_Rest_Registry::unregister('echo');

		global $wp_rest_server;
		$wp_rest_server = null;

		unset($_GET['rest_route']);
		unset($_SERVER['REQUEST_URI']);

		parent::tearDown();
	}

	public function register_test_routes() {
		$controller = new AIPS_Test_Echo_Rest_Controller();
		$controller->register_routes();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function dispatch($method, $path, array $params = array(), array $body = array()) {
		$request = new WP_REST_Request($method, '/' . AIPS_Rest_Registry::NAMESPACE_V1 . '/' . ltrim($path, '/'));
		foreach ($params as $key => $value) {
			$request->set_param($key, $value);
		}
		if (!empty($body)) {
			$request->set_header('Content-Type', 'application/json');
			$request->set_body(wp_json_encode($body));
		}
		return $this->server->dispatch($request);
	}

	private function as_admin() {
		wp_set_current_user(self::factory()->user->create(array('role' => 'administrator')));
	}

	private function as_subscriber() {
		wp_set_current_user(self::factory()->user->create(array('role' => 'subscriber')));
	}

	private function as_editor() {
		wp_set_current_user(self::factory()->user->create(array('role' => 'editor')));
	}

	// -------------------------------------------------------------------------
	// 1. Registry
	// -------------------------------------------------------------------------

	public function test_namespace_constant() {
		$this->assertSame('aips/v1', AIPS_Rest_Registry::NAMESPACE_V1);
	}

	public function test_register_and_lookup() {
		$this->assertTrue(AIPS_Rest_Registry::has_resource('echo'));
		$this->assertSame('AIPS_Test_Echo_Rest_Controller', AIPS_Rest_Registry::get_controller_for('echo'));
		$this->assertNull(AIPS_Rest_Registry::get_controller_for('nope'));
		$this->assertContains('echo', AIPS_Rest_Registry::all_resource_bases());
		$this->assertContains('AIPS_Test_Echo_Rest_Controller', AIPS_Rest_Registry::all_controllers());
	}

	public function test_controllers_for_route_resolves_owning_controller_only() {
		AIPS_Rest_Registry::register('other', 'AIPS_Some_Other_Controller');
		try {
			$this->assertSame(
				array('AIPS_Test_Echo_Rest_Controller'),
				AIPS_Rest_Registry::controllers_for_route('/aips/v1/echo/12')
			);
			$this->assertSame(
				array('AIPS_Test_Echo_Rest_Controller'),
				AIPS_Rest_Registry::controllers_for_route('aips/v1/echo'),
				'Leading slash must be optional'
			);
		} finally {
			AIPS_Rest_Registry::unregister('other');
		}
	}

	public function test_controllers_for_route_outside_namespace_is_empty() {
		$this->assertSame(array(), AIPS_Rest_Registry::controllers_for_route('/wp/v2/posts'));
		$this->assertSame(array(), AIPS_Rest_Registry::controllers_for_route('/aips/v2/echo'));
		$this->assertSame(array(), AIPS_Rest_Registry::controllers_for_route('/aips/v10/echo'), 'Prefix match must respect segment boundary');
	}

	public function test_controllers_for_route_falls_back_to_all_for_index_and_unknown_resource() {
		$all = AIPS_Rest_Registry::all_controllers();
		$this->assertNotEmpty($all);
		$this->assertSame($all, AIPS_Rest_Registry::controllers_for_route('/'));
		$this->assertSame($all, AIPS_Rest_Registry::controllers_for_route('/aips/v1'));
		$this->assertSame($all, AIPS_Rest_Registry::controllers_for_route('/aips/v1/'));
		$this->assertSame($all, AIPS_Rest_Registry::controllers_for_route('/aips/v1/unmapped/1'));
	}

	public function test_detect_current_route_from_rest_route_query_var() {
		$_GET['rest_route'] = '/aips/v1/echo/3';
		$this->assertSame('/aips/v1/echo/3', AIPS_Rest_Registry::detect_current_route());

		$_GET['rest_route'] = 'aips/v1/echo';
		$this->assertSame('/aips/v1/echo', AIPS_Rest_Registry::detect_current_route());
	}

	public function test_detect_current_route_from_request_uri() {
		unset($_GET['rest_route']);
		$prefix = rest_get_url_prefix();

		$_SERVER['REQUEST_URI'] = '/' . $prefix . '/aips/v1/echo/3?_locale=user';
		$this->assertSame('/aips/v1/echo/3', AIPS_Rest_Registry::detect_current_route());

		$_SERVER['REQUEST_URI'] = '/' . $prefix . '/';
		$this->assertSame('/', AIPS_Rest_Registry::detect_current_route());

		$_SERVER['REQUEST_URI'] = '/' . $prefix;
		$this->assertSame('/', AIPS_Rest_Registry::detect_current_route());
	}

	public function test_detect_current_route_returns_null_for_non_rest_requests() {
		unset($_GET['rest_route']);

		$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=aips';
		$this->assertNull(AIPS_Rest_Registry::detect_current_route());

		$_SERVER['REQUEST_URI'] = '/blog/wp-json-archive/';
		$this->assertNull(AIPS_Rest_Registry::detect_current_route(), 'Prefix must match as a whole path segment');

		unset($_SERVER['REQUEST_URI']);
		$this->assertNull(AIPS_Rest_Registry::detect_current_route());
	}

	public function test_plugin_has_private_boot_rest_and_is_rest_request() {
		$rc = new ReflectionClass('AI_Post_Scheduler');
		foreach (array('boot_rest', 'is_rest_request') as $method) {
			$this->assertTrue($rc->hasMethod($method), "AI_Post_Scheduler must define {$method}()");
			$this->assertTrue($rc->getMethod($method)->isPrivate(), "{$method}() must be private");
		}
	}

	// -------------------------------------------------------------------------
	// 2. Permissions via real dispatch
	// -------------------------------------------------------------------------

	public function test_routes_are_registered_under_namespace() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey('/aips/v1/echo', $routes);
		$this->assertArrayHasKey('/aips/v1/echo/(?P<id>[\d]+)', $routes);
		$this->assertArrayHasKey('/aips/v1/echo/bulk', $routes);
	}

	public function test_anonymous_request_gets_401() {
		wp_set_current_user(0);
		$response = $this->dispatch('GET', 'echo');

		$this->assertSame(401, $response->get_status());
		$data = $response->get_data();
		$this->assertSame('aips_unauthorized', $data['code']);
		$this->assertSame(401, $data['data']['status']);
	}

	public function test_insufficient_capability_gets_403() {
		$this->as_subscriber();
		$response = $this->dispatch('GET', 'echo');

		$this->assertSame(403, $response->get_status());
		$this->assertSame('aips_forbidden', $response->get_data()['code']);
	}

	public function test_admin_gets_200() {
		$this->as_admin();
		$response = $this->dispatch('GET', 'echo');
		$this->assertSame(200, $response->get_status());
	}

	public function test_capability_callback_overrides_default_capability() {
		// Editor lacks manage_options but has edit_posts.
		$this->as_editor();

		$this->assertSame(403, $this->dispatch('GET', 'echo')->get_status());
		$this->assertSame(200, $this->dispatch('POST', 'echo/bulk', array('ids' => array(1)))->get_status());
	}

	// -------------------------------------------------------------------------
	// 3. Envelope contract
	// -------------------------------------------------------------------------

	public function test_success_payload_has_no_envelope() {
		$this->as_admin();
		$data = $this->dispatch('GET', 'echo/7')->get_data();

		$this->assertArrayNotHasKey('success', $data);
		$this->assertSame(7, $data['id']);
	}

	public function test_created_and_no_content_statuses() {
		$this->as_admin();

		$created = $this->dispatch('POST', 'echo', array('name' => 'x'));
		$this->assertSame(201, $created->get_status());
		$this->assertSame(99, $created->get_data()['id']);

		$deleted = $this->dispatch('DELETE', 'echo/7');
		$this->assertSame(204, $deleted->get_status());
		$this->assertNull($deleted->get_data());
	}

	public function test_collection_response_sets_pagination_headers() {
		$this->as_admin();
		$response = $this->dispatch('GET', 'echo', array('per_page' => 10));
		$headers  = $response->get_headers();

		$this->assertSame(45, $headers['X-WP-Total']);
		$this->assertSame(5, $headers['X-WP-TotalPages']);
	}

	/**
	 * @dataProvider error_status_provider
	 */
	public function test_error_helpers_map_to_status_and_code($id, $expected_status, $expected_code) {
		$this->as_admin();
		$response = $this->dispatch('GET', 'echo/' . $id);
		$data     = $response->get_data();

		$this->assertSame($expected_status, $response->get_status());
		$this->assertSame($expected_code, $data['code']);
		$this->assertNotEmpty($data['message']);
		$this->assertSame($expected_status, $data['data']['status']);
	}

	public function error_status_provider() {
		return array(
			'not found'       => array(404, 404, 'aips_not_found'),
			'conflict'        => array(409, 409, 'aips_conflict'),
			'server error'    => array(500, 500, 'aips_server_error'),
			'invalid request' => array(400, 400, 'aips_invalid_request'),
		);
	}

	public function test_error_extra_data_is_preserved_alongside_status() {
		$this->as_admin();

		$conflict = $this->dispatch('GET', 'echo/409')->get_data();
		$this->assertSame('name', $conflict['data']['field']);

		$invalid = $this->dispatch('GET', 'echo/400')->get_data();
		$this->assertSame(array('name' => 'required'), $invalid['data']['errors']);
	}

	public function test_not_found_message_includes_resource_label() {
		$this->as_admin();
		$this->assertSame('Widget not found.', $this->dispatch('GET', 'echo/404')->get_data()['message']);
	}

	// -------------------------------------------------------------------------
	// 4. Arg schema helpers
	// -------------------------------------------------------------------------

	public function test_id_arg_is_cast_to_integer() {
		$this->as_admin();
		$data = $this->dispatch('GET', 'echo/12')->get_data();

		$this->assertSame(12, $data['id']);
		$this->assertSame('integer', $data['id_type']);
	}

	public function test_pagination_args_enforce_bounds_and_defaults() {
		$this->as_admin();

		$over = $this->dispatch('GET', 'echo', array('per_page' => 500));
		$this->assertSame(400, $over->get_status(), 'per_page above maximum must be rejected');

		$zero = $this->dispatch('GET', 'echo', array('page' => 0));
		$this->assertSame(400, $zero->get_status(), 'page below minimum must be rejected');

		$default = $this->dispatch('GET', 'echo');
		$this->assertSame(5, $default->get_headers()['X-WP-TotalPages'], 'Default per_page of 10 must apply');
	}

	public function test_ids_arg_sanitizes_and_requires_non_empty_integers() {
		$this->as_admin();

		$ok = $this->dispatch('POST', 'echo/bulk', array('ids' => array('3', 4, '5')));
		$this->assertSame(200, $ok->get_status());
		$this->assertSame(array(3, 4, 5), $ok->get_data()['ids']);

		$missing = $this->dispatch('POST', 'echo/bulk');
		$this->assertSame(400, $missing->get_status(), 'ids is required');

		$empty = $this->dispatch('POST', 'echo/bulk', array('ids' => array()));
		$this->assertSame(400, $empty->get_status(), 'ids must contain at least one item');

		$bad = $this->dispatch('POST', 'echo/bulk', array('ids' => array('abc')));
		$this->assertSame(400, $bad->get_status(), 'non-integer ids must be rejected');
	}
}
