<?php
/**
 * Tests proving AIPS_Author_Topics_Controller delegates to injected services/repositories
 * rather than instantiating them inline.
 *
 * Covers controller -> service -> repository delegation boundaries for:
 *   - ajax_get_similar_topics     -> expansion_service->find_similar_topics()
 *   - ajax_suggest_related_topics -> expansion_service->suggest_related_topics()
 *   - ajax_compute_topic_embeddings -> embeddings worker queueing
 *   - ajax_get_bulk_generate_estimate -> history_repository->get_estimated_generation_time()
 *
 * @package AI_Post_Scheduler
 */

class Test_Author_Topics_Controller_Delegation extends WP_UnitTestCase {

	/** @var int */
	private $admin_user_id;

	/** @var int */
	private $subscriber_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin_user_id      = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
	}

	public function tearDown(): void {
		$_POST    = array();
		$_REQUEST = array();
		parent::tearDown();
	}

	/**
	 * Capture JSON output produced by a controller AJAX method.
	 *
	 * @param callable $callable Controller method to invoke.
	 * @return array Decoded response array.
	 */
	private function call_ajax( callable $callable ) {
		// WordPress nonce validation reads from $_REQUEST, not $_POST, so keep them in sync.
		$_REQUEST = array_merge( $_REQUEST, $_POST );
		ob_start();
		try {
			$callable();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected after wp_send_json_*.
		}
		return json_decode( ob_get_clean(), true );
	}

// -----------------------------------------------------------------------
// ajax_get_similar_topics -> expansion_service->find_similar_topics()
// -----------------------------------------------------------------------

/**
 * ajax_get_similar_topics must delegate to expansion_service->find_similar_topics().
 */
public function test_get_similar_topics_delegates_to_expansion_service() {
		wp_set_current_user( $this->admin_user_id );

		$mock_expansion = $this->getMockBuilder( 'AIPS_Topic_Expansion_Service' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'find_similar_topics' ) )
			->getMock();

		$mock_expansion->expects( $this->once() )
			->method( 'find_similar_topics' )
			->with( 10, 5, 3 )
			->willReturn( array() );

		$controller = new AIPS_Author_Topics_Controller( $mock_expansion );

		$_POST = array(
			'nonce'     => wp_create_nonce( 'aips_ajax_nonce' ),
			'topic_id'  => 10,
			'author_id' => 5,
			'limit'     => 3,
		);

		$response = $this->call_ajax( array( $controller, 'ajax_get_similar_topics' ) );

		$this->assertTrue( $response['success'] );
		$this->assertIsArray( $response['data']['similar_topics'] );
}

/**
 * ajax_get_similar_topics returns an error when topic_id or author_id is missing.
 */
public function test_get_similar_topics_requires_topic_and_author_id() {
		wp_set_current_user( $this->admin_user_id );

		$mock_expansion = $this->getMockBuilder( 'AIPS_Topic_Expansion_Service' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'find_similar_topics' ) )
			->getMock();

		$mock_expansion->expects( $this->never() )
			->method( 'find_similar_topics' );

		$controller = new AIPS_Author_Topics_Controller( $mock_expansion );

		$_POST = array(
			'nonce'    => wp_create_nonce( 'aips_ajax_nonce' ),
			'topic_id' => 0,  // missing
		);

		$response = $this->call_ajax( array( $controller, 'ajax_get_similar_topics' ) );

		$this->assertFalse( $response['success'] );
}

// -----------------------------------------------------------------------
// ajax_suggest_related_topics -> expansion_service->suggest_related_topics()
// -----------------------------------------------------------------------

/**
 * ajax_suggest_related_topics must delegate to expansion_service->suggest_related_topics().
 */
public function test_suggest_related_topics_delegates_to_expansion_service() {
		wp_set_current_user( $this->admin_user_id );

		$mock_expansion = $this->getMockBuilder( 'AIPS_Topic_Expansion_Service' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'suggest_related_topics' ) )
			->getMock();

		$mock_expansion->expects( $this->once() )
			->method( 'suggest_related_topics' )
			->with( 7, 10 )
			->willReturn( array( array( 'topic' => 'Test suggestion' ) ) );

		$controller = new AIPS_Author_Topics_Controller( $mock_expansion );

		$_POST = array(
			'nonce'     => wp_create_nonce( 'aips_ajax_nonce' ),
			'author_id' => 7,
			'limit'     => 10,
		);

		$response = $this->call_ajax( array( $controller, 'ajax_suggest_related_topics' ) );

		$this->assertTrue( $response['success'] );
		$this->assertCount( 1, $response['data']['suggestions'] );
}

// -----------------------------------------------------------------------
// ajax_compute_topic_embeddings -> embeddings worker queueing
// -----------------------------------------------------------------------

/**
 * ajax_compute_topic_embeddings with author_id > 0 delegates to the
 * embeddings worker queue API.
 */
public function test_compute_embeddings_for_author_delegates_to_embeddings_worker() {
		wp_set_current_user( $this->admin_user_id );

		$mock_expansion = $this->getMockBuilder( 'AIPS_Topic_Expansion_Service' )
			->disableOriginalConstructor()
			->getMock();

		$mock_cron = $this->getMockBuilder( 'AIPS_Embeddings_Cron' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'queue_author_embeddings' ) )
			->getMock();

		$mock_cron->expects( $this->once() )
			->method( 'queue_author_embeddings' )
			->with( 3, AIPS_Embeddings_Cron::MAX_BATCH_SIZE, 0, 2 )
			->willReturn( true );

		$controller = new AIPS_Author_Topics_Controller( $mock_expansion, null, null, null, $mock_cron );

		$_POST = array(
			'nonce'     => wp_create_nonce( 'aips_compute_topic_embeddings' ),
			'author_id' => 3,
			'batch_size' => 999,
		);

		$response = $this->call_ajax( array( $controller, 'ajax_compute_topic_embeddings' ) );

		$this->assertTrue( $response['success'] );
		$this->assertSame( array( 3 ), $response['data']['queued_authors'] );
}

/**
 * ajax_compute_topic_embeddings rejects duplicate author queue requests while a
 * per-author queue marker already exists.
 */
public function test_compute_embeddings_for_author_rejects_duplicate_queue_request() {
		wp_set_current_user( $this->admin_user_id );

		$mock_expansion = $this->getMockBuilder( 'AIPS_Topic_Expansion_Service' )
			->disableOriginalConstructor()
			->getMock();

		$mock_cron = $this->getMockBuilder( 'AIPS_Embeddings_Cron' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'queue_author_embeddings' ) )
			->getMock();

		$mock_cron->expects( $this->never() )
			->method( 'queue_author_embeddings' );

		$controller = new AIPS_Author_Topics_Controller( $mock_expansion, null, null, null, $mock_cron );
		set_transient( AIPS_Embeddings_Cron::get_progress_transient_key( 3 ), 'queued', HOUR_IN_SECONDS );

		$_POST = array(
			'nonce'     => wp_create_nonce( 'aips_compute_topic_embeddings' ),
			'author_id' => 3,
		);

		$response = $this->call_ajax( array( $controller, 'ajax_compute_topic_embeddings' ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 3, $response['data']['author_id'] );
}

// -----------------------------------------------------------------------
// ajax_get_bulk_generate_estimate -> history_repository->get_estimated_generation_time()
// -----------------------------------------------------------------------

/**
 * ajax_get_bulk_generate_estimate must delegate to
 * history_repository->get_estimated_generation_time(), not instantiate
 * AIPS_History_Repository inline.
 */
public function test_get_bulk_generate_estimate_delegates_to_history_repository() {
		wp_set_current_user( $this->admin_user_id );

		$mock_history_repo = $this->getMockBuilder( 'AIPS_History_Repository' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_estimated_generation_time' ) )
			->getMock();

		$mock_history_repo->expects( $this->once() )
			->method( 'get_estimated_generation_time' )
			->with( 20 )
			->willReturn( array( 'per_post_seconds' => 45, 'sample_size' => 10 ) );

		$mock_expansion = $this->getMockBuilder( 'AIPS_Topic_Expansion_Service' )
			->disableOriginalConstructor()
			->getMock();

		$controller = new AIPS_Author_Topics_Controller( $mock_expansion, $mock_history_repo );

		$_POST = array(
			'nonce' => wp_create_nonce( 'aips_ajax_nonce' ),
		);

		$response = $this->call_ajax( array( $controller, 'ajax_get_bulk_generate_estimate' ) );

		$this->assertTrue( $response['success'] );
		$this->assertEquals( 45, $response['data']['per_post_seconds'] );
		$this->assertEquals( 10, $response['data']['sample_size'] );
}

/**
 * ajax_get_bulk_generate_estimate must deny non-admin users.
 */
public function test_get_bulk_generate_estimate_permission_denied() {
		wp_set_current_user( $this->subscriber_user_id );

		$mock_expansion = $this->getMockBuilder( 'AIPS_Topic_Expansion_Service' )
			->disableOriginalConstructor()
			->getMock();

		$controller = new AIPS_Author_Topics_Controller( $mock_expansion );

		$_POST = array(
			'nonce' => wp_create_nonce( 'aips_ajax_nonce' ),
		);

		$response = $this->call_ajax( array( $controller, 'ajax_get_bulk_generate_estimate' ) );

		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'Permission denied.', $response['data']['message'] );
}
}
