<?php
/**
 * Tests for REST migration slice 2: simple CRUD singletons.
 *
 *   /aips/v1/voices, /aips/v1/structures, /aips/v1/prompt-sections, /aips/v1/post-slices
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

class Test_AIPS_Rest_Crud_Singletons extends WP_UnitTestCase {

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
	// Registry
	// -------------------------------------------------------------------------

	public function test_slice_two_resources_are_mapped() {
		$this->assertSame('AIPS_Voices_Rest_Controller', AIPS_Rest_Registry::get_controller_for('voices'));
		$this->assertSame('AIPS_Structures_Rest_Controller', AIPS_Rest_Registry::get_controller_for('structures'));
		$this->assertSame('AIPS_Prompt_Sections_Rest_Controller', AIPS_Rest_Registry::get_controller_for('prompt-sections'));
		$this->assertSame('AIPS_Post_Slices_Rest_Controller', AIPS_Rest_Registry::get_controller_for('post-slices'));
	}

	public function test_routes_registered() {
		$routes = $this->server->get_routes();
		foreach (array(
			'/aips/v1/voices',
			'/aips/v1/voices/(?P<id>[\d]+)',
			'/aips/v1/structures',
			'/aips/v1/structures/(?P<id>[\d]+)',
			'/aips/v1/prompt-sections',
			'/aips/v1/prompt-sections/(?P<id>[\d]+)',
			'/aips/v1/post-slices',
			'/aips/v1/post-slices/(?P<id>[\d]+)',
			'/aips/v1/post-slices/bulk-toggle',
			'/aips/v1/post-slices/bulk-delete',
		) as $route) {
			$this->assertArrayHasKey($route, $routes, "Missing route: {$route}");
		}
	}

	// -------------------------------------------------------------------------
	// Voices
	// -------------------------------------------------------------------------

	public function test_voices_requires_auth() {
		wp_set_current_user(0);
		$this->assertSame(401, $this->dispatch('GET', 'voices')->get_status());
	}

	public function test_voices_full_crud_roundtrip() {
		$this->as_admin();

		$created = $this->dispatch('POST', 'voices', array(
			'name'                 => 'Test Voice',
			'title_prompt'         => 'Title prompt.',
			'content_instructions' => 'Content instructions.',
			'is_active'            => true,
		));
		$this->assertSame(201, $created->get_status());
		$id = $created->get_data()['voice_id'];
		$this->assertGreaterThan(0, $id);

		$fetched = $this->dispatch('GET', 'voices/' . $id);
		$this->assertSame(200, $fetched->get_status());
		$this->assertSame('Test Voice', $fetched->get_data()['voice']->name);

		$updated = $this->dispatch('PUT', 'voices/' . $id, array(
			'name'                 => 'Updated Voice',
			'title_prompt'         => 'Updated title.',
			'content_instructions' => 'Updated content.',
			'is_active'            => false,
		));
		$this->assertSame(200, $updated->get_status());
		$this->assertSame('Updated Voice', $updated->get_data()['voice']->name);

		$deleted = $this->dispatch('DELETE', 'voices/' . $id);
		$this->assertSame(200, $deleted->get_status());

		$this->assertSame(404, $this->dispatch('GET', 'voices/' . $id)->get_status());
	}

	public function test_voices_create_missing_required_returns_400() {
		$this->as_admin();
		$this->assertSame(400, $this->dispatch('POST', 'voices', array('name' => 'Only Name'))->get_status());
	}

	public function test_voices_search_returns_matches() {
		$this->as_admin();
		$this->dispatch('POST', 'voices', array(
			'name' => 'Findable Voice', 'title_prompt' => 't', 'content_instructions' => 'c', 'is_active' => true,
		));
		$data = $this->dispatch('GET', 'voices', array('search' => 'Findable'))->get_data();
		$this->assertNotEmpty($data['voices']);
	}

	// -------------------------------------------------------------------------
	// Structures
	// -------------------------------------------------------------------------

	public function test_structures_requires_auth() {
		wp_set_current_user(0);
		$this->assertSame(401, $this->dispatch('GET', 'structures')->get_status());
	}

	public function test_structures_create_update_patch_delete() {
		$this->as_admin();

		$created = $this->dispatch('POST', 'structures', array(
			'name'            => 'Test Structure',
			'description'     => 'Desc',
			'sections'        => array('intro', 'body', 'outro'),
			'prompt_template' => 'Prompt template body',
			'is_active'       => true,
		));
		$this->assertSame(201, $created->get_status());
		$id = $created->get_data()['structure_id'];
		$this->assertGreaterThan(0, $id);

		$updated = $this->dispatch('PUT', 'structures/' . $id, array(
			'name'            => 'Renamed Structure',
			'sections'        => array('a', 'b'),
			'prompt_template' => 'Updated template',
			'is_active'       => true,
		));
		$this->assertSame(200, $updated->get_status());

		$patched = $this->dispatch('PATCH', 'structures/' . $id, array('is_active' => false));
		$this->assertSame(200, $patched->get_status());

		$this->assertSame(200, $this->dispatch('DELETE', 'structures/' . $id)->get_status());
		$this->assertSame(404, $this->dispatch('GET', 'structures/' . $id)->get_status());
	}

	public function test_structures_missing_fields_returns_400() {
		$this->as_admin();
		$this->assertSame(400, $this->dispatch('POST', 'structures', array('name' => 'X'))->get_status());
	}

	// -------------------------------------------------------------------------
	// Prompt sections
	// -------------------------------------------------------------------------

	public function test_prompt_sections_requires_auth() {
		wp_set_current_user(0);
		$this->assertSame(401, $this->dispatch('GET', 'prompt-sections')->get_status());
	}

	public function test_prompt_sections_full_crud_and_duplicate_key_conflict() {
		$this->as_admin();

		$created = $this->dispatch('POST', 'prompt-sections', array(
			'name'        => 'Test Section',
			'section_key' => 'test_section',
			'content'     => 'Content body',
			'is_active'   => true,
		));
		$this->assertSame(201, $created->get_status());
		$id = $created->get_data()['section_id'];
		$this->assertGreaterThan(0, $id);

		$dup = $this->dispatch('POST', 'prompt-sections', array(
			'name'        => 'Other',
			'section_key' => 'test_section',
			'content'     => 'other',
		));
		$this->assertSame(409, $dup->get_status());
		$this->assertSame('aips_conflict', $dup->get_data()['code']);

		$patched = $this->dispatch('PATCH', 'prompt-sections/' . $id, array('is_active' => false));
		$this->assertSame(200, $patched->get_status());

		$this->assertSame(200, $this->dispatch('DELETE', 'prompt-sections/' . $id)->get_status());
	}

	public function test_prompt_sections_missing_required_returns_400() {
		$this->as_admin();
		$this->assertSame(400, $this->dispatch('POST', 'prompt-sections', array('name' => 'X'))->get_status());
	}

	public function test_prompt_sections_get_missing_is_404() {
		$this->as_admin();
		$this->assertSame(404, $this->dispatch('GET', 'prompt-sections/999999999')->get_status());
	}

	// -------------------------------------------------------------------------
	// Post slices
	// -------------------------------------------------------------------------

	public function test_post_slices_requires_auth() {
		wp_set_current_user(0);
		$this->assertSame(401, $this->dispatch('GET', 'post-slices')->get_status());
	}

	public function test_post_slices_full_crud_duplicate_and_bulk_ops() {
		$this->as_admin();

		$a = $this->dispatch('POST', 'post-slices', array('name' => 'Slice A', 'is_active' => true))->get_data()['slice_id'];
		$b = $this->dispatch('POST', 'post-slices', array('name' => 'Slice B', 'is_active' => true))->get_data()['slice_id'];
		$this->assertGreaterThan(0, $a);
		$this->assertGreaterThan(0, $b);

		$dup = $this->dispatch('POST', 'post-slices', array('name' => 'Slice A'));
		$this->assertSame(409, $dup->get_status());

		$patched = $this->dispatch('PATCH', 'post-slices/' . $a, array('is_active' => false));
		$this->assertSame(200, $patched->get_status());

		$toggled = $this->dispatch('POST', 'post-slices/bulk-toggle', array('ids' => array($a, $b), 'is_active' => false));
		$this->assertSame(200, $toggled->get_status());
		$this->assertArrayHasKey('updated', $toggled->get_data());

		$deleted = $this->dispatch('POST', 'post-slices/bulk-delete', array('ids' => array($a, $b)));
		$this->assertSame(200, $deleted->get_status());
		$this->assertArrayHasKey('deleted', $deleted->get_data());

		$this->assertSame(404, $this->dispatch('GET', 'post-slices/' . $a)->get_status());
	}

	public function test_post_slices_bulk_requires_ids() {
		$this->as_admin();
		$this->assertSame(400, $this->dispatch('POST', 'post-slices/bulk-toggle', array('is_active' => true))->get_status());
		$this->assertSame(400, $this->dispatch('POST', 'post-slices/bulk-delete', array())->get_status());
	}

	public function test_post_slices_get_missing_is_404() {
		$this->as_admin();
		$this->assertSame(404, $this->dispatch('GET', 'post-slices/999999999')->get_status());
	}

	public function test_post_slices_response_shape_has_counts() {
		$this->as_admin();
		$data = $this->dispatch('GET', 'post-slices')->get_data();
		$this->assertArrayHasKey('slices', $data);
		$this->assertArrayHasKey('counts', $data);
	}
}
