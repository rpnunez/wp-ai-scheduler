<?php
/**
 * Tests for AIPS_REST_Editor_Controller
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_REST_Editor_Controller extends WP_UnitTestCase {

	/**
	 * @var WP_REST_Server
	 */
	protected $server;

	/**
	 * Set up before each test method.
	 */
	public function setUp(): void {
		parent::setUp();

		global $wp_rest_server;
		$this->server = $wp_rest_server = new WP_REST_Server();
		do_action('rest_api_init');
	}

	public function tearDown(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		parent::tearDown();
	}

	/**
	 * Test that routes are registered.
	 */
	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey('/aips/v1/editor/link-suggestions', $routes);
		$this->assertArrayHasKey('/aips/v1/editor/find-anchors', $routes);
	}

	/**
	 * Test permission callback blocks unauthorized users.
	 */
	public function test_check_editor_permissions_unauthorized() {
		wp_set_current_user(0);

		$request = new WP_REST_Request('POST', '/aips/v1/editor/link-suggestions');
		$request->set_body_params(array('content' => 'Sample draft content for indexing.'));
		$response = $this->server->dispatch($request);

		$this->assertSame(401, $response->get_status());
	}

	/**
	 * Test permission callback allows users with edit_posts capabilities.
	 */
	public function test_check_editor_permissions_authorized_user() {
		$user_id = $this->factory->user->create(array('role' => 'editor'));
		wp_set_current_user($user_id);

		$request = new WP_REST_Request('POST', '/aips/v1/editor/link-suggestions');
		$request->set_body_params(array('content' => 'Short text'));
		$response = $this->server->dispatch($request);

		$this->assertSame(200, $response->get_status());
	}

	/**
	 * Test link suggestions returns precomputed relationships when available.
	 */
	public function test_get_link_suggestions_precomputed() {
		$user_id = $this->factory->user->create(array('role' => 'editor'));
		wp_set_current_user($user_id);

		$source_post_id = $this->factory->post->create(array(
			'post_title'   => 'Source Article',
			'post_content' => 'Discussing advanced WordPress architecture and vector embeddings.',
			'post_status'  => 'publish',
		));

		$target_post_id = $this->factory->post->create(array(
			'post_title'   => 'Target Guide',
			'post_content' => 'A comprehensive guide to vector embeddings and semantic search.',
			'post_status'  => 'publish',
		));

		$rel_repo = new AIPS_Relationships_Repository();
		$rel_repo->upsert('post', $source_post_id, 'post', $target_post_id, 0.88, 'related_post');

		$controller = new AIPS_REST_Editor_Controller($rel_repo);
		$request    = new WP_REST_Request('POST', '/aips/v1/editor/link-suggestions');
		$request->set_body_params(array(
			'post_id' => $source_post_id,
		));

		$response = $controller->get_link_suggestions($request);
		$data     = $response->get_data();

		$this->assertTrue($data['success']);
		$this->assertCount(1, $data['suggestions']);
		$this->assertSame($target_post_id, $data['suggestions'][0]['id']);
		$this->assertSame('Target Guide', $data['suggestions'][0]['title']);
		$this->assertSame(88, $data['suggestions'][0]['similarity_pct']);
		$this->assertTrue($data['suggestions'][0]['is_precomputed']);
	}

	/**
	 * Test find anchors validates required parameters.
	 */
	public function test_find_anchors_validates_required_args() {
		$user_id = $this->factory->user->create(array('role' => 'editor'));
		wp_set_current_user($user_id);

		$controller = new AIPS_REST_Editor_Controller();

		// Missing content
		$request = new WP_REST_Request('POST', '/aips/v1/editor/find-anchors');
		$request->set_body_params(array(
			'target_post_id' => 123,
		));
		$response = $controller->find_anchors($request);
		$this->assertWPError($response);
		$this->assertSame('empty_content', $response->get_error_code());

		// Missing target ID
		$request = new WP_REST_Request('POST', '/aips/v1/editor/find-anchors');
		$request->set_body_params(array(
			'source_content' => 'Some sample content here.',
		));
		$response = $controller->find_anchors($request);
		$this->assertWPError($response);
		$this->assertSame('invalid_target', $response->get_error_code());
	}

	/**
	 * Test that target_post_type filters out suggestions of other post types.
	 */
	public function test_get_link_suggestions_filters_by_post_type() {
		$user_id = $this->factory->user->create(array('role' => 'editor'));
		wp_set_current_user($user_id);

		$source_post_id = $this->factory->post->create(array(
			'post_title'   => 'Parent Article',
			'post_type'    => 'post',
			'post_status'  => 'publish',
		));

		$target_post_id = $this->factory->post->create(array(
			'post_title'   => 'Target Regular Post',
			'post_type'    => 'post',
			'post_status'  => 'publish',
		));

		$target_page_id = $this->factory->post->create(array(
			'post_title'   => 'Target Page Doc',
			'post_type'    => 'page',
			'post_status'  => 'publish',
		));

		$rel_repo = new AIPS_Relationships_Repository();
		$rel_repo->upsert('post', $source_post_id, 'post', $target_post_id, 0.90, 'related_post');
		$rel_repo->upsert('post', $source_post_id, 'post', $target_page_id, 0.85, 'related_post');

		$controller = new AIPS_REST_Editor_Controller($rel_repo);

		// Request only 'page'
		$request = new WP_REST_Request('POST', '/aips/v1/editor/link-suggestions');
		$request->set_body_params(array(
			'post_id'          => $source_post_id,
			'target_post_type' => 'page',
		));

		$response = $controller->get_link_suggestions($request);
		$data     = $response->get_data();

		$this->assertTrue($data['success']);
		$this->assertCount(1, $data['suggestions']);
		$this->assertSame($target_page_id, $data['suggestions'][0]['id']);
		$this->assertSame('page', $data['suggestions'][0]['post_type']);
	}

	/**
	 * Test that custom query parameter generates similarity suggestions based on keyword.
	 */
	public function test_get_link_suggestions_with_query_override() {
		$user_id = $this->factory->user->create(array('role' => 'editor'));
		wp_set_current_user($user_id);

		$target_post_id = $this->factory->post->create(array(
			'post_title'   => 'Docker Container Architecture',
			'post_type'    => 'post',
			'post_status'  => 'publish',
		));

		$embeddings_repo = $this->createMock(AIPS_Embeddings_Repository::class);
		$embeddings_repo->method('get_all_for_similarity')->willReturn(array(
			(object) array(
				'object_id'        => $target_post_id,
				'object_post_type' => 'post',
				'embedding'        => json_encode(array(0.1, 0.2, 0.3)),
			),
		));

		$embeddings_service = $this->createMock(AIPS_Embeddings_Service::class);
		$embeddings_service->method('is_embeddings_supported')->willReturn(true);
		$embeddings_service->method('generate_embedding')->willReturn(array(0.1, 0.2, 0.3));
		$embeddings_service->method('find_nearest_neighbors')->willReturn(array(
			array(
				'id'         => $target_post_id,
				'similarity' => 0.95,
			),
		));

		$controller = new AIPS_REST_Editor_Controller(null, $embeddings_repo, $embeddings_service);

		$request = new WP_REST_Request('POST', '/aips/v1/editor/link-suggestions');
		$request->set_body_params(array(
			'query' => 'Docker caching',
		));

		$response = $controller->get_link_suggestions($request);
		$data     = $response->get_data();

		$this->assertTrue($data['success']);
		$this->assertCount(1, $data['suggestions']);
		$this->assertSame($target_post_id, $data['suggestions'][0]['id']);
		$this->assertSame(95, $data['suggestions'][0]['similarity_pct']);
	}

	/**
	 * Test get_post_seo_metrics endpoint returns inbound, outbound, depth, and orphan info.
	 */
	public function test_get_post_seo_metrics() {
		$user_id = $this->factory->user->create(array('role' => 'editor'));
		wp_set_current_user($user_id);

		$post_id = $this->factory->post->create(array('post_title' => 'Sample Article', 'post_status' => 'publish'));

		$links_repo = new AIPS_Content_Links_Repository();
		$graph_service = new AIPS_Link_Graph_Service($links_repo);
		$controller = new AIPS_REST_Editor_Controller(null, null, null, null, $graph_service, $links_repo);

		$request = new WP_REST_Request('GET', '/aips/v1/editor/post-seo-metrics');
		$request->set_param('post_id', $post_id);

		$response = $controller->get_post_seo_metrics($request);
		$data     = $response->get_data();

		$this->assertTrue($data['success']);
		$this->assertArrayHasKey('metrics', $data);
		$this->assertSame(0, $data['metrics']['inbound_count']);
		$this->assertSame(0, $data['metrics']['outbound_count']);
		$this->assertTrue($data['metrics']['is_orphan']);
	}

	/**
	 * Test link suggestions response enriches candidates with SEO metrics and opportunity fields.
	 */
	public function test_link_suggestions_includes_seo_metrics() {
		$user_id = $this->factory->user->create(array('role' => 'editor'));
		wp_set_current_user($user_id);

		$source_id = $this->factory->post->create(array('post_title' => 'Source Article', 'post_status' => 'publish'));
		$target_id = $this->factory->post->create(array('post_title' => 'Orphan Target', 'post_status' => 'publish'));

		$rel_repo = new AIPS_Relationships_Repository();
		$rel_repo->upsert('post', $source_id, 'post', $target_id, 0.85, 'related_post');

		$links_repo = new AIPS_Content_Links_Repository();
		$graph_service = new AIPS_Link_Graph_Service($links_repo);
		$controller = new AIPS_REST_Editor_Controller($rel_repo, null, null, null, $graph_service, $links_repo);

		$request = new WP_REST_Request('POST', '/aips/v1/editor/link-suggestions');
		$request->set_body_params(array('post_id' => $source_id));

		$response = $controller->get_link_suggestions($request);
		$data     = $response->get_data();

		$this->assertTrue($data['success']);
		$this->assertCount(1, $data['suggestions']);
		$s = $data['suggestions'][0];
		$this->assertSame(0, $s['inbound_count']);
		$this->assertTrue($s['is_orphan']);
		$this->assertFalse($s['is_already_linked']);
		$this->assertArrayHasKey('cross_link', $s);
		$this->assertArrayHasKey('opportunity_score', $s);
	}

	/**
	 * Test link suggestions sort_by=seo_opportunity prioritizes under-linked/orphan posts.
	 */
	public function test_link_suggestions_sort_by_seo_opportunity() {
		$user_id = $this->factory->user->create(array('role' => 'editor'));
		wp_set_current_user($user_id);

		$source_id = $this->factory->post->create(array('post_title' => 'Source', 'post_status' => 'publish'));
		$post_saturated = $this->factory->post->create(array('post_title' => 'Saturated Post', 'post_status' => 'publish'));
		$post_orphan    = $this->factory->post->create(array('post_title' => 'Orphan Post', 'post_status' => 'publish'));

		// Saturated post has higher similarity (0.80) but 5 inbound links
		// Orphan post has lower similarity (0.75) but 0 inbound links
		$rel_repo = new AIPS_Relationships_Repository();
		$rel_repo->upsert('post', $source_id, 'post', $post_saturated, 0.80, 'related_post');
		$rel_repo->upsert('post', $source_id, 'post', $post_orphan, 0.75, 'related_post');

		$links_repo = new AIPS_Content_Links_Repository();
		// Create 5 inbound links pointing to saturated post
		for ($i = 0; $i < 5; $i++) {
			$other = $this->factory->post->create(array('post_status' => 'publish'));
			$links_repo->sync_post_links($other, array(
				array('target_id' => $post_saturated, 'anchor_text' => 'link', 'link_url' => get_permalink($post_saturated), 'post_type' => 'post')
			));
		}

		$graph_service = new AIPS_Link_Graph_Service($links_repo);
		$controller = new AIPS_REST_Editor_Controller($rel_repo, null, null, null, $graph_service, $links_repo);

		// With default similarity sort, saturated is first (0.80 > 0.75)
		$req_sim = new WP_REST_Request('POST', '/aips/v1/editor/link-suggestions');
		$req_sim->set_body_params(array('post_id' => $source_id, 'sort_by' => 'similarity'));
		$res_sim = $controller->get_link_suggestions($req_sim)->get_data();
		$this->assertSame($post_saturated, $res_sim['suggestions'][0]['id']);

		// With seo_opportunity sort, orphan gets +0.35 boost and ranks first (1.10 > 0.80)
		$req_opp = new WP_REST_Request('POST', '/aips/v1/editor/link-suggestions');
		$req_opp->set_body_params(array('post_id' => $source_id, 'sort_by' => 'seo_opportunity'));
		$res_opp = $controller->get_link_suggestions($req_opp)->get_data();
		$this->assertSame($post_orphan, $res_opp['suggestions'][0]['id']);
	}

	/**
	 * Test get_link_graph_modal_data returns nodes and edges.
	 */
	public function test_get_link_graph_modal_data() {
		$user_id = $this->factory->user->create(array('role' => 'editor'));
		wp_set_current_user($user_id);

		$source_id = $this->factory->post->create(array('post_title' => 'Source Article', 'post_status' => 'publish'));
		$target_id = $this->factory->post->create(array('post_title' => 'Target Article', 'post_status' => 'publish'));

		$links_repo = new AIPS_Content_Links_Repository();
		$links_repo->sync_post_links($source_id, array(
			array('target_id' => $target_id, 'anchor_text' => 'Target', 'link_url' => get_permalink($target_id), 'post_type' => 'post')
		));

		$controller = new AIPS_REST_Editor_Controller(null, null, null, null, null, $links_repo);
		$request = new WP_REST_Request('GET', '/aips/v1/editor/link-graph-modal-data');
		$request->set_param('post_id', $source_id);
		$request->set_param('target_ids', (string) $target_id);

		$response = $controller->get_link_graph_modal_data($request);
		$data     = $response->get_data();

		$this->assertTrue($data['success']);
		$this->assertCount(2, $data['data']['nodes']);
		$this->assertCount(1, $data['data']['links']);
		$this->assertSame($source_id, $data['data']['links'][0]['source']);
		$this->assertSame($target_id, $data['data']['links'][0]['target']);
	}
}
