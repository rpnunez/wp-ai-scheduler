<?php
/**
 * Tests for the embeddings background worker queue helpers.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Embeddings_Cron extends WP_UnitTestCase {

	/**
	 * Batch-size requests are clamped to the worker-supported range.
	 */
	public function test_sanitize_batch_size_clamps_requested_bounds() {
		$this->assertSame( AIPS_Embeddings_Cron::DEFAULT_BATCH_SIZE, AIPS_Embeddings_Cron::sanitize_batch_size( 0 ) );
		$this->assertSame( AIPS_Embeddings_Cron::MAX_BATCH_SIZE, AIPS_Embeddings_Cron::sanitize_batch_size( 999 ) );
		$this->assertSame( 12, AIPS_Embeddings_Cron::sanitize_batch_size( 12 ) );
	}

	/**
	 * Queueing delegates to the scheduler with the worker hook and bounded args.
	 */
	public function test_queue_author_embeddings_uses_worker_hook_and_sanitized_args() {
		$mock_scheduler = $this->getMockBuilder( 'AIPS_Job_Scheduler' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'schedule_simple' ) )
			->getMock();

		$mock_scheduler->expects( $this->once() )
			->method( 'schedule_simple' )
			->with(
				AIPS_Embeddings_Cron::HOOK,
				$this->isType( 'int' ),
				array(
					array(
						'author_id'         => 7,
						'batch_size'        => AIPS_Embeddings_Cron::MAX_BATCH_SIZE,
						'last_processed_id' => 4,
					),
				),
				array(
					'job_type'      => 'author_embeddings',
					'retry_options' => array(
						'max_attempts' => 3,
					),
				)
			)
			->willReturn( true );

		$cron = new AIPS_Embeddings_Cron( null, null, null, $mock_scheduler );

		$this->assertTrue( $cron->queue_author_embeddings( 7, 999, 4 ) );
	}
}
