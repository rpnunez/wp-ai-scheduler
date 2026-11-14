<?php
/**
 * Tests for AIPS_Author_Embeddings_Service.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Author_Embeddings_Service extends WP_UnitTestCase {

	/** @var AIPS_Embeddings_Rate_Limiter */
	private $limiter;

	/** @var AIPS_Embeddings_Repository */
	private $embeddings_repo;

	/** @var AIPS_Job_Repository */
	private $job_repo;

	/** @var AIPS_Job_Manager */
	private $job_manager;

	/** @var int */
	public $generated = 0;

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();

		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_author_topics' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_embeddings' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_jobs' );

		$config = AIPS_Config::get_instance();
		$config->set_option( 'aips_embeddings_enabled', true );
		update_option( 'aips_embeddings_rate_limits_enabled', true );
		update_option( 'aips_embeddings_daily_limit', 100 );

		$container             = AIPS_Container::get_instance();
		$this->limiter         = new AIPS_Embeddings_Rate_Limiter( $config );
		$this->embeddings_repo = new AIPS_Embeddings_Repository();
		$this->job_repo        = $container->get_job_repository();
		$this->job_manager     = $container->get_job_manager();
		$this->generated       = 0;
	}

	public function tearDown(): void {
		$this->limiter->reset_history();
		delete_option( 'aips_embeddings_rate_limits_enabled' );
		delete_option( 'aips_embeddings_daily_limit' );
		parent::tearDown();
	}

	private function topic( int $author_id, string $status = 'approved' ): int {
		return (int) ( new AIPS_Author_Topics_Repository() )->create( array(
			'author_id'   => $author_id,
			'topic_title' => 'Topic ' . wp_generate_password( 6, false ),
			'status'      => $status,
		) );
	}

	private function make_service(): AIPS_Author_Embeddings_Service {
		$limiter = $this->limiter;
		$repo    = $this->embeddings_repo;
		$test    = $this;

		$service = $this->getMockBuilder( 'AIPS_Embeddings_Service' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'is_enabled', 'compute_topic_embedding', 'get_rate_limiter', 'get_embeddings_repository' ) )
			->getMock();
		$service->method( 'is_enabled' )->willReturn( true );
		$service->method( 'get_rate_limiter' )->willReturn( $limiter );
		$service->method( 'get_embeddings_repository' )->willReturn( $repo );
		$service->method( 'compute_topic_embedding' )->willReturnCallback( function ( $topic_id ) use ( $limiter, $repo, $test ) {
			$limiter->record_usage( 1 );
			$test->generated++;
			$repo->upsert( 'topic', $topic_id, array( 0.1, 0.2 ), 'm', 2, md5( (string) $topic_id ) );
			return array( 0.1, 0.2 );
		} );

		return new AIPS_Author_Embeddings_Service( $service, AIPS_Config::get_instance(), $repo );
	}

	public function test_service_metadata_and_estimation() {
		$service = $this->make_service();

		$this->assertSame( 'author_topic_embeddings', $service->get_key() );
		$this->assertTrue( $service->uses_ai() );
		$this->assertTrue( $service->can_run() );

		$this->topic( 1 );
		$this->topic( 1 );
		$this->topic( 2, 'rejected' );

		$this->assertSame( 2, $service->count_remaining() );
		$this->assertSame( 2, $service->count_remaining( 1 ) );
		$this->assertSame( 0, $service->count_remaining( 2 ) );

		$estimate = $service->get_estimate();
		$this->assertSame( 2, $estimate['items'] );
		$this->assertSame( 2, $estimate['ai_calls'] );
	}

	public function test_handle_job_slice_embeds_topics_and_updates_cursor() {
		$this->topic( 1 );
		$this->topic( 1 );

		$service = $this->make_service();
		$record  = new AIPS_Job_Record( array(
			'job_id'   => 'job-author-embeds-1',
			'job_type' => AIPS_Job_Registry::TYPE_AUTHOR_TOPIC_EMBEDDINGS,
			'payload'  => array( 'author_id' => 1 ),
		) );
		$context = new AIPS_Job_Context( $record, 10, 30.0 );

		$result = $service->handle_job_slice( $context );

		$this->assertSame( 2, $result->get_processed_count() );
		$this->assertSame( 0, $result->get_failed_count() );
		$this->assertTrue( $result->is_completed() );
		$this->assertSame( 2, $this->generated );
	}
}
