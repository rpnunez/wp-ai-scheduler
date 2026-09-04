<?php
/**
 * Tests for REST migration slice 3a: templates + sources CRUD.
 *
 *   /aips/v1/templates, /aips/v1/templates/{id}, /aips/v1/templates/{id}/clone
 *   /aips/v1/sources, /aips/v1/sources/{id}
 *   /aips/v1/source-groups, /aips/v1/source-groups/{id}
 *   /aips/v1/source-data/{id}
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

class Test_AIPS_Rest_Templates_Sources extends WP_UnitTestCase {

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
		parent::tearDown();
	}

	public function register_all_plugin_routes() {
		foreach (AIPS_Rest_Registry::all_controllers() as $class) {
			$controller = new $class();
			$controller->register_routes();
		}
	}

	private function dispatch($method, $path, array $params = array()) {
		$request = new WP_REST_Request($method, '/' . AIPS_Rest_Registry::NAMESPACE_V1 . '/' . ltrim($path, '/'));
		foreach ($params as $key => $value) {
			$request->set_param($key, $value);
		}
		return $this->server->dispatch($request);
	}

	private function as_admin() {
		wp_set_current_user(self::factory()->user->create(array('role' => 'administrator')));
	}

	// -------------------------------------------------------------------------
	// Registry / routes
	// -------------------------------------------------------------------------

	public function test_slice_three_resources_are_mapped() {
		$this->assertSame('AIPS_Templates_Rest_Controller', AIPS_Rest_Registry::get_controller_for('templates'));
		$this->assertSame('AIPS_Sources_Rest_Controller', AIPS_Rest_Registry::get_controller_for('sources'));
		$this->assertSame('AIPS_Source_Groups_Rest_Controller', AIPS_Rest_Registry::get_controller_for('source-groups'));
		$this->assertSame('AIPS_Source_Data_Rest_Controller', AIPS_Rest_Registry::get_controller_for('source-data'));
	}

	public function test_routes_registered() {
		$routes = $this->server->get_routes();
		foreach (array(
			'/aips/v1/templates',
			'/aips/v1/templates/(?P<id>[\d]+)',
			'/aips/v1/templates/(?P<id>[\d]+)/clone',
			'/aips/v1/sources',
			'/aips/v1/sources/(?P<id>[\d]+)',
			'/aips/v1/source-groups',
			'/aips/v1/source-groups/(?P<id>[\d]+)',
			'/aips/v1/source-data/(?P<id>[\d]+)',
		) as $route) {
			$this->assertArrayHasKey($route, $routes, "Missing route: {$route}");
		}
	}

	// -------------------------------------------------------------------------
	// Templates
	// -------------------------------------------------------------------------

	public function test_templates_requires_auth() {
		wp_set_current_user(0);
		$this->assertSame(401, $this->dispatch('GET', 'templates')->get_status());
	}

	public function test_templates_full_crud_and_clone_roundtrip() {
		$this->as_admin();

		$created = $this->dispatch('POST', 'templates', array(
			'name'            => 'Slice3 Template',
			'prompt_template' => 'Write about {topic}.',
			'post_quantity'   => 2,
			'is_active'       => true,
		));
		$this->assertSame(201, $created->get_status(), 'unexpected status: ' . wp_json_encode($created->get_data()));
		$id = $created->get_data()['template_id'];
		$this->assertGreaterThan(0, $id);

		$fetched = $this->dispatch('GET', 'templates/' . $id);
		$this->assertSame(200, $fetched->get_status());
		$this->assertSame('Slice3 Template', $fetched->get_data()['template']->name);

		$updated = $this->dispatch('PUT', 'templates/' . $id, array(
			'name'            => 'Renamed Template',
			'prompt_template' => 'Updated prompt.',
			'is_active'       => false,
		));
		$this->assertSame(200, $updated->get_status());

		$cloned = $this->dispatch('POST', 'templates/' . $id . '/clone');
		$this->assertSame(201, $cloned->get_status());
		$this->assertGreaterThan(0, $cloned->get_data()['template_id']);
		$this->assertNotSame($id, $cloned->get_data()['template_id']);

		$deleted = $this->dispatch('DELETE', 'templates/' . $id);
		$this->assertSame(200, $deleted->get_status());
		$this->assertSame(404, $this->dispatch('GET', 'templates/' . $id)->get_status());
	}

	public function test_templates_missing_required_returns_400() {
		$this->as_admin();
		$this->assertSame(400, $this->dispatch('POST', 'templates', array('name' => 'Only Name'))->get_status());
	}

	public function test_templates_delete_missing_returns_404() {
		$this->as_admin();
		$this->assertSame(404, $this->dispatch('DELETE', 'templates/999999999')->get_status());
	}

	// -------------------------------------------------------------------------
	// Sources
	// -------------------------------------------------------------------------

	public function test_sources_requires_auth() {
		wp_set_current_user(0);
		$this->assertSame(401, $this->dispatch('GET', 'sources')->get_status());
	}

	public function test_sources_full_crud_and_patch() {
		$this->as_admin();

		$created = $this->dispatch('POST', 'sources', array(
			'url'       => 'https://example.com/article-one',
			'label'     => 'Example',
			'is_active' => true,
		));
		$this->assertSame(201, $created->get_status());
		$id = $created->get_data()['source_id'];

		$dup = $this->dispatch('POST', 'sources', array('url' => 'https://example.com/article-one'));
		$this->assertSame(409, $dup->get_status());

		$patched = $this->dispatch('PATCH', 'sources/' . $id, array('is_active' => false));
		$this->assertSame(200, $patched->get_status());

		$updated = $this->dispatch('PUT', 'sources/' . $id, array(
			'url'       => 'https://example.com/article-two',
			'label'     => 'Renamed',
			'is_active' => true,
		));
		$this->assertSame(200, $updated->get_status());

		$this->assertSame(200, $this->dispatch('DELETE', 'sources/' . $id)->get_status());
		$this->assertSame(404, $this->dispatch('PATCH', 'sources/' . $id, array('is_active' => true))->get_status());
	}

	public function test_sources_rejects_invalid_url_and_interval() {
		$this->as_admin();
		$this->assertSame(400, $this->dispatch('POST', 'sources', array('url' => 'not-a-url'))->get_status());
		$this->assertSame(400, $this->dispatch('POST', 'sources', array(
			'url'            => 'https://example.com/valid',
			'fetch_interval' => 'bogus',
		))->get_status());
	}

	// -------------------------------------------------------------------------
	// Source groups
	// -------------------------------------------------------------------------

	public function test_source_groups_full_crud() {
		$this->as_admin();

		register_taxonomy(AIPS_Source_Groups_Rest_Controller::TAXONOMY, 'post');

		$created = $this->dispatch('POST', 'source-groups', array('name' => 'News'));
		$this->assertSame(201, $created->get_status());
		$term_id = $created->get_data()['group']->term_id;

		$updated = $this->dispatch('PUT', 'source-groups/' . $term_id, array('name' => 'Newsroom'));
		$this->assertSame(200, $updated->get_status());

		$this->assertSame(200, $this->dispatch('DELETE', 'source-groups/' . $term_id)->get_status());
	}

	// -------------------------------------------------------------------------
	// Source data
	// -------------------------------------------------------------------------

	public function test_source_data_get_missing_is_404() {
		$this->as_admin();
		$this->assertSame(404, $this->dispatch('GET', 'source-data/999999999')->get_status());
	}

	public function test_source_data_update_invalid_status_is_400() {
		$this->as_admin();
		$repo = new AIPS_Sources_Data_Repository();
		$id   = $repo->create(array(
			'source_id'    => 0,
			'url'          => 'https://example.com/data',
			'page_title'   => 'x',
			'raw_html'     => '',
			'fetch_status' => 'success',
			'fetched_at'   => time(),
		));
		if (!$id) {
			$this->markTestSkipped('AIPS_Sources_Data_Repository::create not available in this test env.');
		}
		$this->assertSame(400, $this->dispatch('PUT', 'source-data/' . $id, array('fetch_status' => 'bogus'))->get_status());
	}
}
