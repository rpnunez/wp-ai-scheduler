<?php
/**
 * Tests for Embeddings System Enable/Disable Toggle.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Embeddings_Toggle extends WP_UnitTestCase {

	/** @var AIPS_Embeddings_Repository */
	private $embeddings_repo;

	/** @var AIPS_Relationships_Repository */
	private $relationships_repo;

	/** @var AIPS_Embeddings_Service */
	private $embeddings_service;

	/** @var AIPS_Content_Indexer_Service */
	private $indexer_service;

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();

		$this->embeddings_repo    = new AIPS_Embeddings_Repository();
		$this->relationships_repo = new AIPS_Relationships_Repository();

		$mock_ai_service = $this->createMock( AIPS_AI_Service_Interface::class );
		$mock_ai_service->method( 'is_available' )->willReturn( true );
		$mock_ai_service->method( 'supports_embeddings' )->willReturn( true );
		$mock_ai_service->method( 'generate_embedding' )->willReturn( array( 0.1, 0.2, 0.3 ) );

		$this->embeddings_service = new AIPS_Embeddings_Service(
			$mock_ai_service,
			new AIPS_Logger()
		);

		$this->indexer_service = new AIPS_Content_Indexer_Service(
			$this->embeddings_repo,
			$this->relationships_repo,
			$this->embeddings_service
		);

		AIPS_Config::get_instance()->set_option( 'aips_embeddings_enabled', true );
	}

	public function tearDown(): void {
		AIPS_Config::get_instance()->set_option( 'aips_embeddings_enabled', true );
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_embeddings' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_relationships' );
		parent::tearDown();
	}

	/**
	 * Test default option value is true.
	 */
	public function test_default_option_is_true() {
		$config = AIPS_Config::get_instance();
		$this->assertTrue( (bool) $config->get_option( 'aips_embeddings_enabled' ) );
		$this->assertTrue( $this->embeddings_service->is_enabled() );
		$this->assertTrue( $this->embeddings_service->is_embeddings_supported() );
	}

	/**
	 * Test is_enabled and is_embeddings_supported return false when disabled.
	 */
	public function test_is_enabled_returns_false_when_disabled() {
		AIPS_Config::get_instance()->set_option( 'aips_embeddings_enabled', false );
		$this->assertFalse( $this->embeddings_service->is_enabled() );
		$this->assertFalse( $this->embeddings_service->is_embeddings_supported() );
	}

	/**
	 * Test generate_embedding returns WP_Error when disabled.
	 */
	public function test_generate_embedding_returns_error_when_disabled() {
		AIPS_Config::get_instance()->set_option( 'aips_embeddings_enabled', false );
		$result = $this->embeddings_service->generate_embedding( 'Sample text to embed' );

		$this->assertWPError( $result );
		$this->assertEquals( 'embeddings_disabled', $result->get_error_code() );
	}

	/**
	 * Test index_post returns error when disabled.
	 */
	public function test_index_post_returns_error_when_disabled() {
		AIPS_Config::get_instance()->set_option( 'aips_embeddings_enabled', false );

		$post_id = wp_insert_post( array(
			'post_title'   => 'Test Post',
			'post_content' => 'Sample post content',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		$result = $this->indexer_service->index_post( $post_id );
		$this->assertWPError( $result );
		$this->assertEquals( 'embeddings_disabled', $result->get_error_code() );

		$record = $this->embeddings_repo->get_by_post_id( $post_id );
		$this->assertNull( $record );
	}

	/**
	 * Test on_post_save skips indexing when disabled.
	 */
	public function test_on_post_save_skips_indexing_when_disabled() {
		AIPS_Config::get_instance()->set_option( 'aips_embeddings_enabled', false );

		$post_id = wp_insert_post( array(
			'post_title'   => 'Test Post on Save',
			'post_content' => 'Content that should not be indexed automatically',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		) );

		$post = get_post( $post_id );
		$this->indexer_service->on_post_save( $post_id, $post );

		$record = $this->embeddings_repo->get_by_post_id( $post_id );
		$this->assertNull( $record );
	}

	/**
	 * Test get_indexing_status reflects embeddings_enabled status.
	 */
	public function test_get_indexing_status_reflects_toggle() {
		AIPS_Config::get_instance()->set_option( 'aips_embeddings_enabled', true );
		$status = $this->indexer_service->get_indexing_status();
		$this->assertTrue( $status['embeddings_enabled'] );

		AIPS_Config::get_instance()->set_option( 'aips_embeddings_enabled', false );
		$status = $this->indexer_service->get_indexing_status();
		$this->assertFalse( $status['embeddings_enabled'] );
	}
}
