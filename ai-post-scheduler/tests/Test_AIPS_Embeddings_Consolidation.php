<?php
/**
 * Tests for the Embeddings Consolidation & Related Posts feature.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Embeddings_Consolidation extends WP_UnitTestCase {

	/** @var AIPS_Post_Embeddings_Repository */
	private $embeddings_repo;

	/** @var AIPS_Embeddings_Service */
	private $embeddings_service;

	/** @var AIPS_Template_Repository */
	private $template_repo;

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();
		$this->embeddings_repo    = new AIPS_Post_Embeddings_Repository();
		$this->template_repo      = AIPS_Template_Repository::instance();

		// Mock AIPS_AI_Service for embedding generation
		$ai_service_mock = $this->createMock( AIPS_AI_Service_Interface::class );
		$ai_service_mock->method( 'is_available' )->willReturn( true );
		// Return a fixed mock embedding vector based on text
		$ai_service_mock->method( 'generate_embedding' )->willReturnCallback( function( $text ) {
			if ( strpos( $text, 'Apple' ) !== false ) {
				return array( 0.9, 0.1, 0.0 );
			} elseif ( strpos( $text, 'iPhone' ) !== false ) {
				return array( 0.8, 0.2, 0.0 );
			} elseif ( strpos( $text, 'Banana' ) !== false ) {
				return array( 0.1, 0.9, 0.0 );
			}
			return array( 0.0, 0.0, 1.0 );
		} );

		$this->embeddings_service = new AIPS_Embeddings_Service( $ai_service_mock );

		// Register mock in Container so AIPS_Post_Manager resolves this service
		AIPS_Container::get_instance()->singleton( AIPS_Embeddings_Service::class, function() {
			return $this->embeddings_service;
		} );
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_post_embeddings' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_templates' );
		parent::tearDown();
	}

	/**
	 * Verify AIPS_Embeddings_Service::index_post successfully creates post embedding rows.
	 */
	public function test_index_post_success() {
		$post_id = $this->factory->post->create( array(
			'post_title'   => 'Apple Review',
			'post_content' => 'This is a post about Apple fruit and products.',
		) );

		$result = $this->embeddings_service->index_post( $post_id );
		$this->assertTrue( $result );

		$row = $this->embeddings_repo->get_by_post_id( $post_id );
		$this->assertNotNull( $row );
		$this->assertEquals( $post_id, (int) $row->post_id );

		$embedding = json_decode( $row->embedding, true );
		$this->assertEquals( array( 0.9, 0.1, 0.0 ), $embedding );
	}

	/**
	 * Verify AIPS_Embeddings_Service::process_post_indexing_batch indexes batch correctly.
	 */
	public function test_process_post_indexing_batch() {
		$post_id1 = $this->factory->post->create( array(
			'post_title'   => 'Apple Post',
			'post_content' => 'Some content.',
		) );
		$post_id2 = $this->factory->post->create( array(
			'post_title'   => 'Banana Post',
			'post_content' => 'Different content.',
		) );

		// Make sure they are initially unindexed
		$this->assertNull( $this->embeddings_repo->get_by_post_id( $post_id1 ) );
		$this->assertNull( $this->embeddings_repo->get_by_post_id( $post_id2 ) );

		$batch_result = $this->embeddings_service->process_post_indexing_batch( 10 );

		$this->assertEquals( 2, $batch_result['success'] );
		$this->assertEquals( 0, $batch_result['failed'] );
		$this->assertTrue( $batch_result['done'] );

		$this->assertNotNull( $this->embeddings_repo->get_by_post_id( $post_id1 ) );
		$this->assertNotNull( $this->embeddings_repo->get_by_post_id( $post_id2 ) );
	}

	/**
	 * Verify AIPS_Embeddings_Service::find_similar_posts works correctly.
	 */
	public function test_find_similar_posts() {
		$post_id_apple = $this->factory->post->create( array(
			'post_title'   => 'Apple Post',
			'post_content' => 'About Apples.',
		) );
		$post_id_iphone = $this->factory->post->create( array(
			'post_title'   => 'iPhone Post',
			'post_content' => 'About iPhones.',
		) );
		$post_id_banana = $this->factory->post->create( array(
			'post_title'   => 'Banana Post',
			'post_content' => 'About Bananas.',
		) );

		// Index all of them
		$this->embeddings_service->index_post( $post_id_apple );
		$this->embeddings_service->index_post( $post_id_iphone );
		$this->embeddings_service->index_post( $post_id_banana );

		// Find posts similar to Apple Post with low threshold
		$similar = $this->embeddings_service->find_similar_posts( $post_id_apple, 3, 0.50 );
		$this->assertNotEmpty( $similar );

		// iPhone Post should be similar (dot product of [0.9,0.1,0] and [0.8,0.2,0] is 0.72 + 0.02 = 0.74)
		$similar_ids = array_column( $similar, 'id' );
		$this->assertContains( $post_id_iphone, $similar_ids );

		// Banana Post should not be similar enough (dot product of [0.9,0.1,0] and [0.1,0.9,0] is 0.09 + 0.09 = 0.18, below 0.50 threshold)
		$this->assertNotContains( $post_id_banana, $similar_ids );
	}

	/**
	 * Verify that AIPS_Post_Manager::create_post correctly snapshots template-specific related posts options to post meta.
	 */
	public function test_post_manager_create_post_snapshots_meta() {
		$template_id = $this->template_repo->create( array(
			'name'                    => 'Test Template with Related Posts',
			'prompt_template'         => 'Generate a post about iPhones.',
			'post_status'             => 'draft',
			'enable_related_posts'    => 1,
			'related_posts_limit'     => 5,
			'related_posts_threshold' => 0.85,
		) );

		$this->assertNotFalse( $template_id );
		$template = $this->template_repo->get_by_id( $template_id );

		$post_manager = new AIPS_Post_Manager();
		$post_id = $post_manager->create_post( array(
			'title'    => 'My Generated iPhone Post',
			'content'  => 'Reviewing iPhone specs.',
			'template' => $template,
		) );

		$this->assertNotFalse( $post_id );
		$this->assertNotInstanceOf( WP_Error::class, $post_id );

		// Verify meta fields are saved as strings representing their template configurations
		$this->assertEquals( '1', get_post_meta( $post_id, '_aips_enable_related_posts', true ) );
		$this->assertEquals( '5', get_post_meta( $post_id, '_aips_related_posts_limit', true ) );
		$this->assertEquals( '0.85', get_post_meta( $post_id, '_aips_related_posts_threshold', true ) );
		$this->assertEquals( (string) $template_id, get_post_meta( $post_id, '_aips_template_id', true ) );
	}
}
