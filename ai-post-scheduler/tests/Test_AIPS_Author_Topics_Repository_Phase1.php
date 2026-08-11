<?php
/**
 * Phase 1 tests for AIPS_Author_Topics_Repository:
 *   - Exact inserted-record identification via generation_run_id.
 *   - max_posts_per_topic eligibility semantics.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Author_Topics_Repository_Phase1 extends WP_UnitTestCase {

	/** @var AIPS_Author_Topics_Repository */
	private $repository;

	/** @var AIPS_Author_Topic_Logs_Repository */
	private $logs_repository;

	/** @var AIPS_Authors_Repository */
	private $authors_repository;

	/** @var int */
	private $author_id;

	public function setUp(): void {
		parent::setUp();

		$this->repository         = new AIPS_Author_Topics_Repository();
		$this->logs_repository    = new AIPS_Author_Topic_Logs_Repository();
		$this->authors_repository = new AIPS_Authors_Repository();

		$this->author_id = (int) $this->authors_repository->create( array(
			'name'                => 'Phase 1 Author',
			'field_niche'         => 'Testing',
			'is_active'           => 1,
			'max_posts_per_topic' => 2,
		) );
	}

	public function tearDown(): void {
		global $wpdb;

		$topic_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}aips_author_topics WHERE author_id = %d",
			$this->author_id
		) );
		if ( ! empty( $topic_ids ) ) {
			$this->logs_repository->delete_by_topic_ids( array_map( 'intval', $topic_ids ) );
		}

		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_author_topics WHERE author_id = " . (int) $this->author_id );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_authors WHERE id = " . (int) $this->author_id );

		parent::tearDown();
	}

	private function should_skip() {
		global $wpdb;
		return property_exists( $wpdb, 'get_results_return_val' );
	}

	private function make_topic( $title, $status = 'approved' ) {
		return array(
			'author_id'   => $this->author_id,
			'topic_title' => $title,
			'status'      => $status,
			'score'       => 50,
			'metadata'    => '',
		);
	}

	/**
	 * create_bulk() with a run ID returns the exact inserted IDs, and
	 * get_by_run_id() returns only those records — even when a second batch is
	 * inserted for the same author between reads (interleaved inserts).
	 */
	public function test_create_bulk_returns_exact_records_for_run_id() {
		if ( $this->should_skip() ) {
			$this->markTestSkipped( 'Requires a real wpdb instance.' );
		}

		$run_a = 'run-a-' . uniqid();
		$run_b = 'run-b-' . uniqid();

		$ids_a = $this->repository->create_bulk( array(
			$this->make_topic( 'Batch A Topic One' ),
			$this->make_topic( 'Batch A Topic Two' ),
		), $run_a );

		// A concurrent/interleaved batch for the SAME author.
		$ids_b = $this->repository->create_bulk( array(
			$this->make_topic( 'Batch B Topic One' ),
			$this->make_topic( 'Batch B Topic Two' ),
			$this->make_topic( 'Batch B Topic Three' ),
		), $run_b );

		$this->assertCount( 2, $ids_a );
		$this->assertCount( 3, $ids_b );
		$this->assertEmpty( array_intersect( $ids_a, $ids_b ), 'Batches must not share IDs.' );

		$records_a = $this->repository->get_by_run_id( $run_a, $this->author_id );
		$titles_a  = wp_list_pluck( $records_a, 'topic_title' );

		$this->assertCount( 2, $records_a, 'Only Batch A records should be returned for run A.' );
		$this->assertContains( 'Batch A Topic One', $titles_a );
		$this->assertContains( 'Batch A Topic Two', $titles_a );
		$this->assertNotContains( 'Batch B Topic One', $titles_a );

		// Returned IDs match the exact persisted rows.
		$this->assertSame(
			array_map( 'intval', wp_list_pluck( $records_a, 'id' ) ),
			array_values( $ids_a )
		);
	}

	/**
	 * Eligibility respects max_posts_per_topic for values of 1, 2 and 10.
	 */
	public function test_eligibility_honors_max_posts_per_topic() {
		if ( $this->should_skip() ) {
			$this->markTestSkipped( 'Requires a real wpdb instance.' );
		}

		$ids   = $this->repository->create_bulk( array( $this->make_topic( 'Eligibility Topic' ) ), 'run-elig-' . uniqid() );
		$topic_id = (int) $ids[0];

		// max = 1: eligible with 0 posts, ineligible after 1 post.
		$this->assertEligibleCount( 1, 1, 'max=1, 0 posts → eligible' );
		$post_one = self::factory()->post->create();
		$this->logs_repository->log_post_generation( $topic_id, $post_one );
		$this->assertEligibleCount( 0, 1, 'max=1, 1 post → ineligible' );

		// max = 2: same topic becomes eligible again (1 < 2), ineligible at 2.
		$this->assertEligibleCount( 1, 2, 'max=2, 1 post → eligible' );
		$post_two = self::factory()->post->create();
		$this->logs_repository->log_post_generation( $topic_id, $post_two );
		$this->assertEligibleCount( 0, 2, 'max=2, 2 posts → ineligible' );

		// max = 10: eligible again (2 < 10).
		$this->assertEligibleCount( 1, 10, 'max=10, 2 posts → eligible' );
	}

	/**
	 * Failed / non-post logs do not consume the per-topic limit.
	 */
	public function test_failed_logs_do_not_consume_limit() {
		if ( $this->should_skip() ) {
			$this->markTestSkipped( 'Requires a real wpdb instance.' );
		}

		global $wpdb;

		$ids      = $this->repository->create_bulk( array( $this->make_topic( 'Failure Topic' ) ), 'run-fail-' . uniqid() );
		$topic_id = (int) $ids[0];

		// A claim-only / non-post_generated log with no post_id.
		$this->logs_repository->create( array(
			'author_topic_id' => $topic_id,
			'action'          => 'generation_failed',
			'post_id'         => null,
		) );

		// A post_generated log whose post_id references a non-existent post.
		$this->logs_repository->log_post_generation( $topic_id, 99999999 );

		$this->assertEligibleCount( 1, 1, 'Failed/non-existent-post logs must not consume the limit.' );
	}

	/**
	 * Deleting the referenced post frees the slot (post-existence policy).
	 */
	public function test_deleted_post_frees_the_slot() {
		if ( $this->should_skip() ) {
			$this->markTestSkipped( 'Requires a real wpdb instance.' );
		}

		$ids      = $this->repository->create_bulk( array( $this->make_topic( 'Deletion Topic' ) ), 'run-del-' . uniqid() );
		$topic_id = (int) $ids[0];

		$post_id = self::factory()->post->create();
		$this->logs_repository->log_post_generation( $topic_id, $post_id );
		$this->assertEligibleCount( 0, 1, 'With one existing post, topic is ineligible at max=1.' );

		wp_delete_post( $post_id, true );
		$this->assertEligibleCount( 1, 1, 'Deleting the post should free the slot again.' );
	}

	/**
	 * The global queue query uses the same eligibility rule.
	 */
	public function test_queue_query_uses_same_eligibility() {
		if ( $this->should_skip() ) {
			$this->markTestSkipped( 'Requires a real wpdb instance.' );
		}

		// Author max_posts_per_topic is 2 (from setUp).
		$ids      = $this->repository->create_bulk( array( $this->make_topic( 'Queue Topic' ) ), 'run-queue-' . uniqid() );
		$topic_id = (int) $ids[0];

		$this->assertQueueContains( $topic_id, true, '0 posts → in queue' );

		$this->logs_repository->log_post_generation( $topic_id, self::factory()->post->create() );
		$this->assertQueueContains( $topic_id, true, '1 post (< max 2) → still in queue' );

		$this->logs_repository->log_post_generation( $topic_id, self::factory()->post->create() );
		$this->assertQueueContains( $topic_id, false, '2 posts (>= max 2) → removed from queue' );
	}

	/**
	 * Assert the eligible-topic count for this author at a given max.
	 */
	private function assertEligibleCount( $expected, $max, $message ) {
		$topics = $this->repository->get_approved_for_generation( $this->author_id, 10, 0, $max );
		$this->assertCount( (int) $expected, $topics, $message );
	}

	/**
	 * Assert whether a topic appears in the global approved queue.
	 */
	private function assertQueueContains( $topic_id, $expected, $message ) {
		$queue = $this->repository->get_all_approved_for_queue();
		$ids   = array_map( 'intval', wp_list_pluck( $queue, 'id' ) );
		$this->assertSame( (bool) $expected, in_array( (int) $topic_id, $ids, true ), $message );
	}
}
