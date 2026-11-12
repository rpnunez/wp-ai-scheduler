<?php
/**
 * Tests for Author Post Generation Concurrency and Atomic Claiming.
 *
 * Covers:
 * - Atomic topic claim-first CAS locking
 * - Per-author post generation run locking
 * - Two concurrent workers on the same topic producing exactly one post
 * - Failed generation releases topic claim for retry
 * - Stale claim auto-expires after TTL
 * - Regeneration allows claiming an already generated topic
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Author_Post_Concurrency extends WP_UnitTestCase {

	/**
	 * @var AIPS_Authors_Repository
	 */
	private $authors_repository;

	/**
	 * @var AIPS_Author_Topics_Repository
	 */
	private $topics_repository;

	/**
	 * @var AIPS_Author_Topic_Logs_Repository
	 */
	private $logs_repository;

	/**
	 * @var int Test author ID.
	 */
	private $author_id;

	public function setUp(): void {
		parent::setUp();

		AIPS_DB_Manager::install_tables();

		$this->authors_repository = new AIPS_Authors_Repository();
		$this->topics_repository  = new AIPS_Author_Topics_Repository();
		$this->logs_repository    = new AIPS_Author_Topic_Logs_Repository();

		// Create a test author row in the database.
		global $wpdb;
		$authors_table = $wpdb->prefix . 'aips_authors';
		$wpdb->insert(
			$authors_table,
			array(
				'name'                              => 'Concurrency Author',
				'field_niche'                       => 'Technology',
				'post_generation_frequency'         => 'daily',
				'post_generation_next_run'          => time() - 60,
				'manual_post_generation_quantity'   => 1,
				'scheduled_post_generation_quantity'=> 1,
				'is_active'                         => 1,
				'post_generation_lock'              => 0,
			)
		);
		$this->author_id = (int) $wpdb->insert_id;
	}

	public function tearDown(): void {
		global $wpdb;
		if ( $this->author_id ) {
			$wpdb->delete( $wpdb->prefix . 'aips_authors', array( 'id' => $this->author_id ) );
			$wpdb->delete( $wpdb->prefix . 'aips_author_topics', array( 'author_id' => $this->author_id ) );
		}
		parent::tearDown();
	}

	/**
	 * Helper to create an approved topic for the test author.
	 *
	 * @param string $title Topic title.
	 * @return int Topic ID.
	 */
	private function create_test_topic( $title = 'Concurrency Test Topic' ): int {
		global $wpdb;
		$topics_table = $wpdb->prefix . 'aips_author_topics';
		$wpdb->insert(
			$topics_table,
			array(
				'author_id'    => $this->author_id,
				'topic_title'  => $title,
				'status'       => 'approved',
				'score'        => 80,
				'claimed_at'   => 0,
				'generated_at' => time(),
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Helper to inject a property via reflection.
	 *
	 * @param object $object Object instance.
	 * @param string $property_name Property name.
	 * @param mixed  $value Value to set.
	 */
	private function inject_property( $object, $property_name, $value ): void {
		$reflection = new ReflectionClass( $object );
		while ( $reflection && ! $reflection->hasProperty( $property_name ) ) {
			$reflection = $reflection->getParentClass();
		}
		$property = $reflection->getProperty( $property_name );
		$property->setAccessible( true );
		$property->setValue( $object, $value );
	}

	/**
	 * Test atomic topic claim acquire and release.
	 */
	public function test_atomic_topic_claim_and_release() {
		$topic_id = $this->create_test_topic( 'Topic Claim Test' );

		// Worker 1 claims topic: should succeed.
		$acquired = $this->topics_repository->claim_topic_for_generation( $topic_id, 900 );
		$this->assertTrue( $acquired, 'First worker must acquire topic claim' );

		// Worker 2 attempts to claim while locked: must fail.
		$second_attempt = $this->topics_repository->claim_topic_for_generation( $topic_id, 900 );
		$this->assertFalse( $second_attempt, 'Second worker must fail to claim an already claimed topic' );

		// Worker 1 releases topic claim.
		$released = $this->topics_repository->release_topic_claim( $topic_id );
		$this->assertTrue( $released, 'Releasing topic claim must succeed' );

		// Worker 2 attempts again after release: should succeed.
		$third_attempt = $this->topics_repository->claim_topic_for_generation( $topic_id, 900 );
		$this->assertTrue( $third_attempt, 'Claim must succeed after previous claim was released' );
	}

	/**
	 * Test atomic author run lock acquire and release.
	 */
	public function test_atomic_author_run_lock_and_release() {
		// Worker 1 acquires author run lock: should succeed.
		$acquired = $this->authors_repository->acquire_post_generation_lock( $this->author_id, 900 );
		$this->assertTrue( $acquired, 'First worker must acquire author run lock' );

		// Worker 2 attempts to acquire lock for same author: must fail.
		$second_attempt = $this->authors_repository->acquire_post_generation_lock( $this->author_id, 900 );
		$this->assertFalse( $second_attempt, 'Second worker must fail to acquire lock for same author' );

		// Worker 1 releases lock.
		$released = $this->authors_repository->release_post_generation_lock( $this->author_id );
		$this->assertTrue( $released, 'Releasing author run lock must succeed' );

		// Worker 2 attempts again: should succeed.
		$third_attempt = $this->authors_repository->acquire_post_generation_lock( $this->author_id, 900 );
		$this->assertTrue( $third_attempt, 'Author run lock must be acquirable after release' );
	}

	/**
	 * Test that two concurrent workers targeting the same topic produce exactly one post.
	 *
	 * Worker 1 successfully claims and generates the post.
	 * Worker 2 is rejected by the CAS claim check with 'topic_already_claimed' WP_Error.
	 */
	public function test_two_concurrent_runs_on_same_topic_produce_exactly_one_post() {
		$topic_id = $this->create_test_topic( 'Race Condition Topic' );
		$topic    = $this->topics_repository->get_by_id( $topic_id );
		$author   = $this->authors_repository->get_by_id( $this->author_id );

		// Worker 1: claims topic.
		$claim_w1 = $this->topics_repository->claim_topic_for_generation( $topic_id, 900 );
		$this->assertTrue( $claim_w1, 'Worker 1 must claim the topic' );

		// Worker 2 attempts generation through AIPS_Author_Post_Generator on the same topic.
		$post_generator = new AIPS_Author_Post_Generator();
		$result_w2      = $post_generator->generate_post_from_topic( $topic, $author, 'manual' );

		$this->assertWPError( $result_w2, 'Worker 2 must receive a WP_Error when topic is already claimed' );
		$this->assertSame( 'topic_already_claimed', $result_w2->get_error_code() );

		// Worker 1 completes generation by simulating post creation and log entry.
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Generated Post 1',
				'post_content' => 'Post Content',
				'post_status'  => 'draft',
			)
		);
		$this->logs_repository->log_post_generation(
			$topic_id,
			$post_id,
			wp_json_encode( array( 'author_id' => $this->author_id ) )
		);

		// Worker 1 releases claim after successful post creation and logging.
		$this->topics_repository->release_topic_claim( $topic_id );

		// Verify that Worker 2 still cannot generate from this topic even after lock release,
		// because the topic now has a post_generated log entry (NOT EXISTS check).
		$claim_w2_after = $this->topics_repository->claim_topic_for_generation( $topic_id, 900, false );
		$this->assertFalse( $claim_w2_after, 'Worker 2 cannot claim an already generated topic' );

		// Verify exactly 1 log entry exists for this topic.
		global $wpdb;
		$logs_table = $wpdb->prefix . 'aips_author_topic_logs';
		$log_count  = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$logs_table} WHERE author_topic_id = %d AND action = 'post_generated'",
				$topic_id
			)
		);
		$this->assertSame( 1, $log_count, 'Exactly 1 post_generated log entry must exist' );
	}

	/**
	 * Test that a failed post generation releases the claim so the topic can be retried.
	 */
	public function test_failed_generation_releases_claim_for_retry() {
		$topic_id = $this->create_test_topic( 'Fail And Retry Topic' );
		$topic    = $this->topics_repository->get_by_id( $topic_id );
		$author   = $this->authors_repository->get_by_id( $this->author_id );

		// Mock generator that returns WP_Error to simulate generation failure.
		$mock_generator = new class {
			public function generate_post( $context ) {
				return new WP_Error( 'ai_error', 'AI Provider failed' );
			}
		};

		$post_generator = new AIPS_Author_Post_Generator();
		$this->inject_property( $post_generator, 'generator', $mock_generator );

		$result = $post_generator->generate_post_from_topic( $topic, $author, 'manual' );

		$this->assertWPError( $result );
		$this->assertSame( 'ai_error', $result->get_error_code() );

		// The claim should have been released in the failure path.
		global $wpdb;
		$topics_table = $wpdb->prefix . 'aips_author_topics';
		$claimed_at   = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT claimed_at FROM {$topics_table} WHERE id = %d", $topic_id )
		);
		$this->assertSame( 0, $claimed_at, 'claimed_at must be reset to 0 after failure' );

		// The topic must be re-claimable for retry.
		$reclaim = $this->topics_repository->claim_topic_for_generation( $topic_id, 900 );
		$this->assertTrue( $reclaim, 'Topic must be claimable again after generation failure' );
	}

	/**
	 * Test that an exception during generation releases the claim.
	 */
	public function test_exception_during_generation_releases_claim() {
		$topic_id = $this->create_test_topic( 'Exception Topic' );
		$topic    = $this->topics_repository->get_by_id( $topic_id );
		$author   = $this->authors_repository->get_by_id( $this->author_id );

		// Mock generator that throws an Exception.
		$mock_generator = new class {
			public function generate_post( $context ) {
				throw new Exception( 'Fatal AI error' );
			}
		};

		$post_generator = new AIPS_Author_Post_Generator();
		$this->inject_property( $post_generator, 'generator', $mock_generator );

		$result = $post_generator->generate_post_from_topic( $topic, $author, 'manual' );

		$this->assertWPError( $result );
		$this->assertSame( 'generation_failed', $result->get_error_code() );

		// Verify claim was released.
		global $wpdb;
		$topics_table = $wpdb->prefix . 'aips_author_topics';
		$claimed_at   = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT claimed_at FROM {$topics_table} WHERE id = %d", $topic_id )
		);
		$this->assertSame( 0, $claimed_at, 'claimed_at must be reset to 0 after exception' );
	}

	/**
	 * Test that a stale claim (older than timeout TTL) auto-expires and allows re-claiming.
	 */
	public function test_stale_claim_auto_expires_after_ttl() {
		$topic_id = $this->create_test_topic( 'Stale Claim Topic' );

		// Set claimed_at to 20 minutes ago (1200 seconds ago; TTL is 900s).
		global $wpdb;
		$topics_table = $wpdb->prefix . 'aips_author_topics';
		$stale_time   = time() - 1200;
		$wpdb->update(
			$topics_table,
			array( 'claimed_at' => $stale_time ),
			array( 'id' => $topic_id )
		);

		// Claiming with TTL 900 should succeed because current claim is stale (> 900s).
		$acquired = $this->topics_repository->claim_topic_for_generation( $topic_id, 900 );
		$this->assertTrue( $acquired, 'Stale claim must be overridden by new worker' );

		$new_claimed_at = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT claimed_at FROM {$topics_table} WHERE id = %d", $topic_id )
		);
		$this->assertGreaterThan( $stale_time, $new_claimed_at, 'claimed_at should be updated to current timestamp' );
	}

	/**
	 * Test that regeneration allows claiming a topic that was already generated.
	 */
	public function test_regeneration_bypasses_historical_post_log_check() {
		$topic_id = $this->create_test_topic( 'Regeneration Topic' );

		// Simulate that this topic was previously generated and logged.
		$post_id = self::factory()->post->create( array( 'post_title' => 'Previous Post' ) );
		$this->logs_repository->log_post_generation(
			$topic_id,
			$post_id,
			wp_json_encode( array( 'author_id' => $this->author_id ) )
		);

		// Normal claim (allow_regenerate = false) must fail because of the existing log entry.
		$normal_claim = $this->topics_repository->claim_topic_for_generation( $topic_id, 900, false );
		$this->assertFalse( $normal_claim, 'Normal generation cannot claim an already generated topic' );

		// Regeneration claim (allow_regenerate = true) must succeed.
		$regen_claim = $this->topics_repository->claim_topic_for_generation( $topic_id, 900, true );
		$this->assertTrue( $regen_claim, 'Regeneration must succeed in claiming an already generated topic' );
	}

	/**
	 * Test that generate_posts_for_author rejects concurrent run when author lock is held.
	 */
	public function test_generate_posts_for_author_skips_when_author_locked() {
		$author = $this->authors_repository->get_by_id( $this->author_id );

		// Acquire author run lock externally (simulating worker 1 running).
		$this->authors_repository->acquire_post_generation_lock( $this->author_id, 900 );

		// Worker 2 attempts generate_posts_for_author.
		$post_generator = new AIPS_Author_Post_Generator();
		$result         = $post_generator->generate_posts_for_author( $author );

		$this->assertWPError( $result, 'Worker 2 must receive WP_Error when author is locked' );
		$this->assertSame( 'author_run_locked', $result->get_error_code() );

		// Release author lock.
		$this->authors_repository->release_post_generation_lock( $this->author_id );

		// Author lock should now be released.
		global $wpdb;
		$authors_table = $wpdb->prefix . 'aips_authors';
		$lock_val      = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT post_generation_lock FROM {$authors_table} WHERE id = %d", $this->author_id )
		);
		$this->assertSame( 0, $lock_val, 'Author lock must be 0 after release' );
	}
}
