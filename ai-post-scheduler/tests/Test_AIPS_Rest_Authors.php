<?php
/**
 * Tests for REST migration slice 3b: authors CRUD + reporters + suggestions.
 *
 *   /aips/v1/authors[, /{id}, /{id}/topics, /{id}/posts, /{id}/feedback]
 *   /aips/v1/authors/suggest
 *   /aips/v1/author-topics/{id}/posts
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

class Test_AIPS_Rest_Authors extends WP_UnitTestCase {

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

	public function test_authors_resource_is_mapped() {
		$this->assertSame('AIPS_Authors_Rest_Controller', AIPS_Rest_Registry::get_controller_for('authors'));
		$this->assertSame('AIPS_Authors_Rest_Controller', AIPS_Rest_Registry::get_controller_for('author-topics'));
	}

	public function test_routes_registered() {
		$routes = $this->server->get_routes();
		foreach (array(
			'/aips/v1/authors',
			'/aips/v1/authors/suggest',
			'/aips/v1/authors/(?P<id>[\d]+)',
			'/aips/v1/authors/(?P<id>[\d]+)/topics',
			'/aips/v1/authors/(?P<id>[\d]+)/posts',
			'/aips/v1/authors/(?P<id>[\d]+)/feedback',
			'/aips/v1/author-topics/(?P<id>[\d]+)/posts',
		) as $route) {
			$this->assertArrayHasKey($route, $routes, "Missing route: {$route}");
		}
	}

	// -------------------------------------------------------------------------
	// Authors CRUD
	// -------------------------------------------------------------------------

	public function test_authors_requires_auth() {
		wp_set_current_user(0);
		$this->assertSame(401, $this->dispatch('GET', 'authors')->get_status());
	}

	public function test_authors_full_crud_roundtrip() {
		$this->as_admin();

		$created = $this->dispatch('POST', 'authors', array(
			'name'        => 'Test Author',
			'field_niche' => 'tech',
			'is_active'   => true,
		));
		$this->assertSame(201, $created->get_status(), wp_json_encode($created->get_data()));
		$id = $created->get_data()['author_id'];
		$this->assertGreaterThan(0, $id);

		$fetched = $this->dispatch('GET', 'authors/' . $id);
		$this->assertSame(200, $fetched->get_status());
		$this->assertSame('Test Author', $fetched->get_data()['author']->name);

		$updated = $this->dispatch('PUT', 'authors/' . $id, array(
			'name'        => 'Renamed Author',
			'field_niche' => 'science',
		));
		$this->assertSame(200, $updated->get_status());

		$deleted = $this->dispatch('DELETE', 'authors/' . $id);
		$this->assertSame(200, $deleted->get_status());
		$this->assertSame(404, $this->dispatch('GET', 'authors/' . $id)->get_status());
	}

	public function test_authors_missing_required_returns_400() {
		$this->as_admin();
		$this->assertSame(400, $this->dispatch('POST', 'authors', array('name' => 'Only Name'))->get_status());
	}

	public function test_authors_delete_missing_returns_404() {
		$this->as_admin();
		$this->assertSame(404, $this->dispatch('DELETE', 'authors/999999999')->get_status());
	}

	// -------------------------------------------------------------------------
	// Reporters
	// -------------------------------------------------------------------------

	public function test_author_topics_reporter_shape_and_missing_author_is_404() {
		$this->as_admin();

		$this->assertSame(404, $this->dispatch('GET', 'authors/999999999/topics')->get_status());

		$id = $this->dispatch('POST', 'authors', array('name' => 'Reporter', 'field_niche' => 'x'))->get_data()['author_id'];
		$response = $this->dispatch('GET', 'authors/' . $id . '/topics');
		$this->assertSame(200, $response->get_status());
		$data = $response->get_data();
		$this->assertArrayHasKey('topics', $data);
		$this->assertArrayHasKey('status_counts', $data);
		$this->assertArrayHasKey('posts_generated', $data['status_counts']);
	}

	public function test_author_posts_and_feedback_return_empty_arrays_for_new_author() {
		$this->as_admin();

		$id = $this->dispatch('POST', 'authors', array('name' => 'Empty', 'field_niche' => 'x'))->get_data()['author_id'];

		$posts = $this->dispatch('GET', 'authors/' . $id . '/posts');
		$this->assertSame(200, $posts->get_status());
		$this->assertSame(array(), $posts->get_data()['posts']);

		$feedback = $this->dispatch('GET', 'authors/' . $id . '/feedback');
		$this->assertSame(200, $feedback->get_status());
		$this->assertSame(array(), $feedback->get_data()['feedback']);
	}

	public function test_topic_posts_missing_topic_is_404() {
		$this->as_admin();
		$this->assertSame(404, $this->dispatch('GET', 'author-topics/999999999/posts')->get_status());
	}

	// -------------------------------------------------------------------------
	// Suggestions
	// -------------------------------------------------------------------------

	public function test_suggest_missing_site_niche_returns_400() {
		$this->as_admin();
		$this->assertSame(400, $this->dispatch('POST', 'authors/suggest')->get_status());
	}
}
