<?php
/**
 * Tests for the enrichment queue and AIPS_Ability_Enrichment_Process, the
 * managed background process that enriches generated posts.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ability_Enrichment_Process extends WP_UnitTestCase {

	/** @var AIPS_Background_Process_Repository */
	private $runs;

	/** @var AIPS_Ability_Enrichment_Queue_Repository */
	private $queue;

	/** @var array[] Ability invocations: slug, payload, user_id. */
	private $calls = array();

	public function setUp(): void {
		parent::setUp();

		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_background_processes' );

		$this->calls = array();
		$this->runs  = new AIPS_Background_Process_Repository();
		$this->queue = new AIPS_Ability_Enrichment_Queue_Repository();

		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_enrichment', 1 );
		AIPS_Config::get_instance()->set_option( 'aips_ability_enrichment_user_id', 0 );
		wp_set_current_user( 0 );
	}

	public function tearDown(): void {
		global $wpdb;

		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_background_processes' );
		wp_clear_scheduled_hook( AIPS_Managed_Background_Process::TICK_HOOK, array( AIPS_Ability_Enrichment_Process::KEY ) );
		remove_all_filters( 'aips_ability_provider' );
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_enrichment', 0 );
		AIPS_Config::get_instance()->set_option( 'aips_ability_enrichment_user_id', 0 );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	// ---------------------------------------------------------------------
	// Fixtures
	// ---------------------------------------------------------------------

	private function register_abilities( ?array $alt = null, ?array $classification = null ) {
		$abilities = array();

		$abilities[ AIPS_Ability_Enrichment_Service::ALT_TEXT_ABILITY ] = function () use ( $alt ) {
			return null === $alt ? array( 'alt_text' => 'A red bicycle' ) : $alt;
		};
		$abilities[ AIPS_Ability_Enrichment_Service::CLASSIFICATION_ABILITY ] = function () use ( $classification ) {
			return null === $classification ? array( 'suggestions' => array() ) : $classification;
		};

		add_filter(
			'aips_ability_provider',
			function () use ( $abilities ) {
				return array(
					'name'   => 'enrichment-process-fixture',
					'list'   => function () use ( $abilities ) {
						$list = array();

						foreach ( array_keys( $abilities ) as $slug ) {
							$list[ $slug ] = array( 'slug' => $slug );
						}

						return $list;
					},
					'invoke' => function ( $slug, $payload ) use ( $abilities ) {
						$this->calls[] = array( 'slug' => $slug, 'payload' => $payload, 'user_id' => get_current_user_id() );

						return call_user_func( $abilities[ $slug ], $payload );
					},
				);
			}
		);
	}

	/**
	 * A generated post with a featured image that has no alt text.
	 */
	private function make_post( $author_id = 0 ) {
		$post_id       = self::factory()->post->create( array( 'post_author' => $author_id ) );
		$attachment_id = self::factory()->attachment->create( array( 'post_parent' => $post_id ) );
		update_post_meta( $post_id, '_thumbnail_id', $attachment_id );

		return array( $post_id, $attachment_id );
	}

	private function alt( $attachment_id ) {
		return get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
	}

	private function process() {
		$abilities = new AIPS_Ability_Service();
		$service   = new AIPS_Ability_Enrichment_Service( $abilities, new AIPS_Ability_Catalog_Service( $abilities ), null, $this->queue );

		return new AIPS_Ability_Enrichment_Process( $this->runs, $this->queue, $service );
	}

	private function open_run() {
		return $this->runs->get_open( AIPS_Ability_Enrichment_Process::KEY );
	}

	// ---------------------------------------------------------------------
	// Queue
	// ---------------------------------------------------------------------

	public function test_the_queue_holds_each_post_once_oldest_first_with_the_triggering_user() {
		$first  = self::factory()->post->create();
		$second = self::factory()->post->create();

		$this->assertTrue( $this->queue->enqueue( $second, 7 ) );
		$this->assertTrue( $this->queue->enqueue( $first, 0 ) );
		$this->queue->enqueue( $second, 9 );

		$this->assertSame( 2, $this->queue->count_pending() );
		$this->assertSame(
			array(
				array( 'post_id' => $first, 'user_id' => 0 ),
				array( 'post_id' => $second, 'user_id' => 9 ),
			),
			$this->queue->get_pending( 10 )
		);
		$this->assertCount( 1, $this->queue->get_pending( 1 ) );
	}

	public function test_dequeue_removes_a_post() {
		$post_id = self::factory()->post->create();
		$this->queue->enqueue( $post_id, 1 );

		$this->queue->dequeue( $post_id );

		$this->assertSame( 0, $this->queue->count_pending() );
		$this->assertSame( array(), $this->queue->get_pending( 10 ) );
	}

	// ---------------------------------------------------------------------
	// Registration, start, kick
	// ---------------------------------------------------------------------

	public function test_the_manager_registers_the_process() {
		$process = ( new AIPS_Background_Process_Manager() )->get( AIPS_Ability_Enrichment_Process::KEY );

		$this->assertInstanceOf( 'AIPS_Ability_Enrichment_Process', $process );
		$this->assertTrue( $process->uses_ai() );
	}

	public function test_start_is_refused_while_enrichment_is_disabled() {
		$this->queue->enqueue( self::factory()->post->create(), 0 );
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_enrichment', 0 );

		$result = $this->process()->start();

		$this->assertWPError( $result );
		$this->assertSame( 'ability_enrichment_disabled', $result->get_error_code() );
	}

	public function test_start_reports_nothing_to_do_with_an_empty_queue() {
		$result = $this->process()->start();

		$this->assertWPError( $result );
		$this->assertSame( 'aips_bg_nothing_to_do', $result->get_error_code() );
	}

	public function test_generation_queues_the_post_and_starts_the_real_process() {
		$this->register_abilities();
		list( $post_id ) = $this->make_post();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$user_id = get_current_user_id();

		( new AIPS_Ability_Enrichment_Service() )->handle_post_generated( $post_id );

		$this->assertSame( array( array( 'post_id' => $post_id, 'user_id' => $user_id ) ), $this->queue->get_pending( 10 ) );
		$run = $this->open_run();
		$this->assertNotNull( $run, 'Queuing must start the background process.' );
		$this->assertSame( 'running', $run->status );
		$this->assertNotFalse( wp_next_scheduled( AIPS_Managed_Background_Process::TICK_HOOK, array( AIPS_Ability_Enrichment_Process::KEY ) ) );
		$this->assertCount( 0, $this->calls, 'Queuing must not call any ability.' );
	}

	// ---------------------------------------------------------------------
	// Slices
	// ---------------------------------------------------------------------

	public function test_a_tick_enriches_queued_posts_and_empties_the_queue() {
		$this->register_abilities();
		list( $post_id, $attachment_id ) = $this->make_post();
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->queue->enqueue( $post_id, $user_id );
		$process = $this->process();
		$process->start();

		$process->tick();

		$this->assertSame( 'A red bicycle', $this->alt( $attachment_id ) );
		$this->assertSame( $user_id, $this->calls[0]['user_id'], 'Runs as the user who triggered the generation.' );
		$this->assertSame( 0, get_current_user_id(), 'The cron user is restored.' );
		$this->assertSame( 0, $this->queue->count_pending() );
		$snapshot = $process->get_snapshot();
		$this->assertSame( 'completed', $snapshot['last_status'] );
		$this->assertSame( 1, $snapshot['processed'] );
		$this->assertSame( 0, $snapshot['failed'] );
	}

	public function test_a_slice_enriches_two_posts_then_carries_on() {
		$this->register_abilities();
		$attachments = array();
		$user_id     = self::factory()->user->create( array( 'role' => 'editor' ) );
		for ( $i = 0; $i < 3; $i++ ) {
			list( $post_id, $attachment_id ) = $this->make_post();
			$attachments[]                   = $attachment_id;
			$this->queue->enqueue( $post_id, $user_id );
		}
		$process = $this->process();
		$process->start();

		$process->tick();

		$this->assertSame( 1, $this->queue->count_pending(), 'One slice enriches two posts.' );
		$this->assertSame( 'running', $this->open_run()->status );

		$process->tick();

		$this->assertSame( 0, $this->queue->count_pending() );
		foreach ( $attachments as $attachment_id ) {
			$this->assertSame( 'A red bicycle', $this->alt( $attachment_id ) );
		}
	}

	public function test_a_post_nobody_may_edit_leaves_the_queue_and_counts_as_failed() {
		$this->register_abilities();
		list( $post_id, $attachment_id ) = $this->make_post();
		$this->queue->enqueue( $post_id, 0 );
		$process = $this->process();
		$process->start();

		$process->tick();

		$this->assertSame( 0, $this->queue->count_pending(), 'A post is tried once; it must not block the queue.' );
		$this->assertCount( 0, $this->calls );
		$this->assertSame( '', $this->alt( $attachment_id ) );
		$this->assertSame( 1, $process->get_snapshot()['failed'] );
	}

	public function test_the_configured_service_user_runs_posts_queued_by_cron() {
		$this->register_abilities();
		list( $post_id, $attachment_id ) = $this->make_post();
		$service_user = self::factory()->user->create( array( 'role' => 'editor' ) );
		AIPS_Config::get_instance()->set_option( 'aips_ability_enrichment_user_id', $service_user );
		$this->queue->enqueue( $post_id, 0 );
		$process = $this->process();
		$process->start();

		$process->tick();

		$this->assertSame( $service_user, $this->calls[0]['user_id'] );
		$this->assertSame( 'A red bicycle', $this->alt( $attachment_id ) );
	}

	public function test_a_failing_step_counts_the_post_as_failed_but_still_dequeues_it() {
		$this->register_abilities( array( 'error' => 'Provider down.' ) );
		list( $post_id ) = $this->make_post();
		$user_id         = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->queue->enqueue( $post_id, $user_id );
		$process = $this->process();
		$process->start();

		$process->tick();

		$this->assertSame( 0, $this->queue->count_pending() );
		$this->assertSame( 1, $process->get_snapshot()['failed'] );
	}

	public function test_ability_calls_are_metered() {
		$this->register_abilities();
		list( $post_id ) = $this->make_post();
		$user_id         = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->queue->enqueue( $post_id, $user_id );
		$process = $this->process();
		$process->start();

		$process->tick();

		$this->assertSame( count( $this->calls ), $process->get_snapshot()['ai_calls_used'] );
		$this->assertGreaterThan( 0, count( $this->calls ) );
	}

	public function test_disabling_enrichment_fails_the_process_and_keeps_the_queue() {
		$this->register_abilities();
		list( $post_id ) = $this->make_post();
		$this->queue->enqueue( $post_id, 1 );
		$process = $this->process();
		$process->start();
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_enrichment', 0 );

		$process->tick();

		$this->assertCount( 0, $this->calls );
		$this->assertSame( 1, $this->queue->count_pending(), 'Posts stay queued so enabling it again picks them up.' );
		$this->assertSame( 'failed', $process->get_snapshot()['last_status'] );
	}

	public function test_a_paused_process_does_not_enrich() {
		$this->register_abilities();
		list( $post_id ) = $this->make_post();
		$this->queue->enqueue( $post_id, self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$process = $this->process();
		$process->start();
		$this->assertTrue( $process->pause() );

		$process->tick();

		$this->assertCount( 0, $this->calls );
		$this->assertSame( 1, $this->queue->count_pending() );
	}

	public function test_the_estimate_counts_queued_posts() {
		$process = $this->process();
		$this->assertSame( 0, $process->get_estimate()['items'] );

		$this->queue->enqueue( self::factory()->post->create(), 0 );
		$this->queue->enqueue( self::factory()->post->create(), 0 );
		$estimate = $process->get_estimate();

		$this->assertSame( 2, $estimate['items'] );
		$this->assertSame( 6, $estimate['ai_calls'] );
	}
}
