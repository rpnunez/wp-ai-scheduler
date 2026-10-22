<?php
/**
 * AJAX contract tests for AIPS_Taxonomy_Controller.
 *
 * Verifies authentication, nonces, input validation, response shape,
 * lifecycle state transitions (generate, approve, reject, delete, term creation),
 * and bulk operations.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Taxonomy_Controller extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	private $admin_user_id;

	/**
	 * @var int
	 */
	private $subscriber_user_id;

	/**
	 * @var AIPS_Taxonomy_Controller
	 */
	private $controller;

	/**
	 * @var AIPS_Taxonomy_Repository
	 */
	private $repository;

	public function setUp(): void {
		parent::setUp();

		$this->admin_user_id = $this->factory->user->create(array('role' => 'administrator'));
		$this->subscriber_user_id = $this->factory->user->create(array('role' => 'subscriber'));

		wp_set_current_user($this->admin_user_id);

		$_POST = array();
		$_GET = array();
		$_REQUEST = array();

		$this->repository = new AIPS_Taxonomy_Repository();
		$this->controller = new AIPS_Taxonomy_Controller($this->repository);
	}

	public function tearDown(): void {
		$_POST = array();
		$_GET = array();
		$_REQUEST = array();

		AIPS_Container::get_instance()->clear();
		AI_Post_Scheduler::get_instance()->register_container_bindings();

		parent::tearDown();
	}

	/**
	 * Helper to capture JSON response from an AJAX action.
	 */
	private function capture_ajax(callable $callable): array {
		ob_start();

		try {
			$callable();
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		} catch (WPAjaxDieStopException $e) {
			// Expected.
		}

		$output = ob_get_clean();
		$decoded = json_decode($output, true);

		return is_array($decoded) ? $decoded : array('raw' => $output);
	}

	/**
	 * Test that endpoints fail on invalid nonce.
	 */
	public function test_endpoints_fail_on_invalid_nonce() {
		$_POST = array('nonce' => 'invalid_nonce_value');
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($this->controller, 'ajax_get_taxonomy_items'));
		$this->assertFalse($response['success']);
		$this->assertSame('Invalid nonce.', $response['data']['message']);

		$response = $this->capture_ajax(array($this->controller, 'ajax_generate_taxonomy'));
		$this->assertFalse($response['success']);
		$this->assertSame('Invalid nonce.', $response['data']['message']);

		$response = $this->capture_ajax(array($this->controller, 'ajax_approve_taxonomy'));
		$this->assertFalse($response['success']);
		$this->assertSame('Invalid nonce.', $response['data']['message']);
	}

	/**
	 * Test that endpoints fail on unauthorized user.
	 */
	public function test_endpoints_fail_on_unauthorized_user() {
		wp_set_current_user($this->subscriber_user_id);

		$_POST = array('nonce' => wp_create_nonce('aips_ajax_nonce'));
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($this->controller, 'ajax_get_taxonomy_items'));
		$this->assertFalse($response['success']);
		$this->assertSame('permission_denied', $response['data']['code']);

		$response = $this->capture_ajax(array($this->controller, 'ajax_generate_taxonomy'));
		$this->assertFalse($response['success']);
		$this->assertSame('permission_denied', $response['data']['code']);

		$response = $this->capture_ajax(array($this->controller, 'ajax_delete_taxonomy'));
		$this->assertFalse($response['success']);
		$this->assertSame('permission_denied', $response['data']['code']);
	}

	/**
	 * Test input validation on taxonomy endpoints.
	 */
	public function test_input_validation() {
		$nonce = wp_create_nonce('aips_ajax_nonce');

		// 1. Get items without taxonomy_type
		$_POST = array('nonce' => $nonce);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($this->controller, 'ajax_get_taxonomy_items'));
		$this->assertFalse($response['success']);
		$this->assertSame('Taxonomy type is required.', $response['data']['message']);

		// 2. Generate without taxonomy_type
		$response = $this->capture_ajax(array($this->controller, 'ajax_generate_taxonomy'));
		$this->assertFalse($response['success']);
		$this->assertSame('Taxonomy type is required.', $response['data']['message']);

		// 3. Approve without item_id
		$response = $this->capture_ajax(array($this->controller, 'ajax_approve_taxonomy'));
		$this->assertFalse($response['success']);
		$this->assertSame('Invalid item ID.', $response['data']['message']);

		// 4. Reject without item_id
		$response = $this->capture_ajax(array($this->controller, 'ajax_reject_taxonomy'));
		$this->assertFalse($response['success']);
		$this->assertSame('Invalid item ID.', $response['data']['message']);

		// 5. Delete without item_id
		$response = $this->capture_ajax(array($this->controller, 'ajax_delete_taxonomy'));
		$this->assertFalse($response['success']);
		$this->assertSame('Invalid item ID.', $response['data']['message']);

		// 6. Create term without item_id
		$response = $this->capture_ajax(array($this->controller, 'ajax_create_taxonomy_term'));
		$this->assertFalse($response['success']);
		$this->assertSame('Invalid item ID.', $response['data']['message']);

		// 7. Bulk actions with empty item_ids
		$response = $this->capture_ajax(array($this->controller, 'ajax_bulk_approve_taxonomy'));
		$this->assertFalse($response['success']);
		$this->assertSame('No items selected.', $response['data']['message']);

		$response = $this->capture_ajax(array($this->controller, 'ajax_bulk_reject_taxonomy'));
		$this->assertFalse($response['success']);
		$this->assertSame('No items selected.', $response['data']['message']);

		$response = $this->capture_ajax(array($this->controller, 'ajax_bulk_delete_taxonomy'));
		$this->assertFalse($response['success']);
		$this->assertSame('No items selected.', $response['data']['message']);

		$response = $this->capture_ajax(array($this->controller, 'ajax_bulk_create_taxonomy_terms'));
		$this->assertFalse($response['success']);
		$this->assertSame('No items selected.', $response['data']['message']);
	}

	/**
	 * Test single item lifecycle: approve, reject, delete, and create term.
	 */
	public function test_single_item_lifecycle_state_transitions() {
		$mock_repo = $this->getMockBuilder('AIPS_Taxonomy_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('update_status', 'delete', 'get_by_id', 'update', 'get_status_counts'))
			->getMock();

		$item = (object) array(
			'id'            => 42,
			'name'          => 'AI Technology',
			'slug'          => 'ai-technology',
			'taxonomy_type' => 'category',
			'description'   => 'Category for AI topics',
			'status'        => 'approved',
			'term_id'       => 0,
		);

		$mock_repo->method('update_status')->willReturn(true);
		$mock_repo->method('delete')->willReturn(true);
		$mock_repo->method('update')->willReturn(true);
		$mock_repo->method('get_by_id')->willReturn($item);
		$mock_repo->method('get_status_counts')->willReturn(array(
			'categories' => array('pending' => 0, 'approved' => 1, 'rejected' => 0),
			'tags'       => array('pending' => 0, 'approved' => 0, 'rejected' => 0),
		));

		$ctrl = new AIPS_Taxonomy_Controller($mock_repo);
		$nonce = wp_create_nonce('aips_ajax_nonce');

		// 1. Approve item
		$_POST = array(
			'nonce'   => $nonce,
			'item_id' => 42,
		);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($ctrl, 'ajax_approve_taxonomy'));
		$this->assertTrue($response['success']);
		$this->assertSame('Item approved successfully.', $response['data']['message']);

		// 2. Reject item
		$response = $this->capture_ajax(array($ctrl, 'ajax_reject_taxonomy'));
		$this->assertTrue($response['success']);
		$this->assertSame('Item rejected successfully.', $response['data']['message']);

		// 3. Create term in WordPress
		$response = $this->capture_ajax(array($ctrl, 'ajax_create_taxonomy_term'));
		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('term_id', $response['data']);

		// Clean up created term
		if (!empty($response['data']['term_id'])) {
			wp_delete_term((int) $response['data']['term_id'], 'category');
		}

		// 4. Delete item
		$response = $this->capture_ajax(array($ctrl, 'ajax_delete_taxonomy'));
		$this->assertTrue($response['success']);
		$this->assertSame('Item deleted successfully.', $response['data']['message']);
	}

	/**
	 * Test bulk operations: bulk approve, reject, delete, and term creation.
	 */
	public function test_bulk_operations() {
		$mock_repo = $this->getMockBuilder('AIPS_Taxonomy_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('update_status', 'delete', 'get_by_id', 'update', 'get_status_counts'))
			->getMock();

		$mock_repo->method('update_status')->willReturn(true);
		$mock_repo->method('delete')->willReturn(true);
		$mock_repo->method('update')->willReturn(true);
		$mock_repo->method('get_by_id')->willReturnCallback(function ($id) {
			return (object) array(
				'id'            => $id,
				'name'          => 'Bulk Term ' . $id,
				'slug'          => 'bulk-term-' . $id,
				'taxonomy_type' => 'category',
				'description'   => 'Desc for ' . $id,
				'status'        => 'approved',
				'term_id'       => 0,
			);
		});
		$mock_repo->method('get_status_counts')->willReturn(array(
			'categories' => array('pending' => 0, 'approved' => 3, 'rejected' => 0),
			'tags'       => array('pending' => 0, 'approved' => 0, 'rejected' => 0),
		));

		$ctrl = new AIPS_Taxonomy_Controller($mock_repo);
		$nonce = wp_create_nonce('aips_ajax_nonce');

		// 1. Bulk approve
		$_POST = array(
			'nonce'    => $nonce,
			'item_ids' => array(10, 11, 12),
		);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($ctrl, 'ajax_bulk_approve_taxonomy'));
		$this->assertTrue($response['success']);
		$this->assertSame(3, $response['data']['success_count']);

		// 2. Bulk reject
		$response = $this->capture_ajax(array($ctrl, 'ajax_bulk_reject_taxonomy'));
		$this->assertTrue($response['success']);
		$this->assertSame(3, $response['data']['success_count']);

		// 3. Bulk create terms
		$response = $this->capture_ajax(array($ctrl, 'ajax_bulk_create_taxonomy_terms'));
		$this->assertTrue($response['success']);
		$this->assertSame(3, $response['data']['success_count']);

		// 4. Bulk delete
		$response = $this->capture_ajax(array($ctrl, 'ajax_bulk_delete_taxonomy'));
		$this->assertTrue($response['success']);
		$this->assertSame(3, $response['data']['success_count']);
	}

	/**
	 * Test post search endpoint.
	 */
	public function test_ajax_search_posts() {
		$post_id = $this->factory->post->create(array(
			'post_title'   => 'Taxonomy Searchable Post',
			'post_content' => 'Sample content for search',
			'post_status'  => 'publish',
		));

		$_POST = array(
			'nonce'       => wp_create_nonce('aips_ajax_nonce'),
			'search_term' => 'Taxonomy Searchable Post',
		);
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($this->controller, 'ajax_search_posts'));
		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('posts', $response['data']);
		$this->assertIsArray($response['data']['posts']);
	}
}
