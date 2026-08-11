<?php
/**
 * Unit tests for the Phase 1 generation-result value objects.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Generation_Result_Objects extends WP_UnitTestCase {

	/**
	 * Full success: all requested posts generated, no failures.
	 */
	public function test_post_result_total_success() {
		$result = new AIPS_Author_Post_Generation_Result( 1, 2 );
		$result->add_success( 101 );
		$result->add_success( 102 );
		$result->finalize();

		$this->assertSame( 'success', $result->get_status() );
		$this->assertTrue( $result->is_success() );
		$this->assertFalse( $result->is_partial() );
		$this->assertSame( array( 101, 102 ), $result->get_post_ids() );
		$this->assertSame( array( 101, 102 ), $result->to_legacy_return() );
	}

	/**
	 * Partial success: fewer posts than requested, or mixed success/failure.
	 */
	public function test_post_result_partial_success() {
		$result = new AIPS_Author_Post_Generation_Result( 1, 3 );
		$result->add_success( 201 );
		$result->add_failure( 9, 'Topic Nine', 'generation_failed', 'boom' );
		$result->finalize();

		$this->assertSame( 'partial', $result->get_status() );
		$this->assertTrue( $result->is_success() );
		$this->assertTrue( $result->is_partial() );
		$this->assertSame( array( 201 ), $result->to_legacy_return(), 'Legacy shape returns the successful IDs.' );
		$this->assertCount( 1, $result->get_failures() );
	}

	/**
	 * Partial when fewer successes than requested even without explicit failures.
	 */
	public function test_post_result_partial_when_under_requested() {
		$result = new AIPS_Author_Post_Generation_Result( 1, 3 );
		$result->add_success( 301 );
		$result->finalize();

		$this->assertSame( 'partial', $result->get_status() );
	}

	/**
	 * Total failure: no successes, at least one failure.
	 */
	public function test_post_result_total_failure() {
		$result = new AIPS_Author_Post_Generation_Result( 1, 2 );
		$result->add_failure( 1, 'Topic One', 'generation_failed', 'first' );
		$result->add_failure( 2, 'Topic Two', 'timeout', 'second' );
		$result->finalize();

		$this->assertSame( 'failed', $result->get_status() );
		$this->assertFalse( $result->is_success() );

		$legacy = $result->to_legacy_return();
		$this->assertInstanceOf( 'WP_Error', $legacy );
		$this->assertSame( 'timeout', $legacy->get_error_code(), 'Last failure surfaces in the legacy WP_Error.' );
	}

	/**
	 * No work: no eligible topics.
	 */
	public function test_post_result_no_work() {
		$result = new AIPS_Author_Post_Generation_Result( 1, 1 );
		$result->mark_no_work();

		$this->assertSame( 'no_work', $result->get_status() );
		$this->assertFalse( $result->is_success() );

		$legacy = $result->to_legacy_return();
		$this->assertInstanceOf( 'WP_Error', $legacy );
		$this->assertSame( 'no_topics', $legacy->get_error_code() );
	}

	/**
	 * Already running: claim contended.
	 */
	public function test_post_result_already_running() {
		$result = new AIPS_Author_Post_Generation_Result( 1, 1 );
		$result->mark_already_running();

		$this->assertSame( 'already_running', $result->get_status() );

		$legacy = $result->to_legacy_return();
		$this->assertInstanceOf( 'WP_Error', $legacy );
		$this->assertSame( 'already_running', $legacy->get_error_code() );
	}

	/**
	 * Skipped topics are recorded and surfaced in the array form.
	 */
	public function test_post_result_records_skips() {
		$result = new AIPS_Author_Post_Generation_Result( 1, 2 );
		$result->add_success( 501 );
		$result->add_skipped( 77, 'Busy Topic', 'already_running' );
		$result->finalize();

		$data = $result->to_array();
		$this->assertSame( 1, $data['success_count'] );
		$this->assertSame( 1, $data['skipped_count'] );
		$this->assertSame( 'already_running', $data['skipped'][0]['reason'] );
	}

	/**
	 * Topic result: full success when all requested topics persisted.
	 */
	public function test_topic_result_success() {
		$result = new AIPS_Author_Topic_Generation_Result( 1, 2, 'run-xyz' );
		$result->set_persisted_topics( array(
			array( 'id' => 1, 'topic_title' => 'A' ),
			array( 'id' => 2, 'topic_title' => 'B' ),
		) );
		$result->finalize();

		$this->assertSame( 'success', $result->get_status() );
		$this->assertSame( array( 1, 2 ), $result->get_persisted_topic_ids() );
		$this->assertSame( 'run-xyz', $result->get_generation_run_id() );
	}

	/**
	 * Topic result: partial when fewer persisted than requested.
	 */
	public function test_topic_result_partial() {
		$result = new AIPS_Author_Topic_Generation_Result( 1, 5, 'run-1' );
		$result->set_persisted_topics( array( array( 'id' => 1, 'topic_title' => 'A' ) ) );
		$result->finalize();

		$this->assertSame( 'partial', $result->get_status() );
		$this->assertTrue( $result->is_partial() );
	}

	/**
	 * Topic result: failed marks and surfaces the error via legacy return.
	 */
	public function test_topic_result_failed_legacy_return() {
		$result = new AIPS_Author_Topic_Generation_Result( 1, 3, 'run-2' );
		$result->mark_failed( new WP_Error( 'db_insert_error', 'nope' ) );
		$result->finalize();

		$this->assertSame( 'failed', $result->get_status() );
		$legacy = $result->to_legacy_return();
		$this->assertInstanceOf( 'WP_Error', $legacy );
		$this->assertSame( 'db_insert_error', $legacy->get_error_code() );
	}

	/**
	 * Topic result: no work when nothing persisted and no error.
	 */
	public function test_topic_result_no_work() {
		$result = new AIPS_Author_Topic_Generation_Result( 1, 3, 'run-3' );
		$result->finalize();

		$this->assertSame( 'no_work', $result->get_status() );
	}
}
