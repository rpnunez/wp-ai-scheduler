<?php
/**
 * Regression tests for AIPS_Schedule_Generation_Service::do_process_batch_slice().
 *
 * These guard the C-02 defects that were silently dropped once already:
 *  - a failed `once` schedule must NOT be handed a success value to
 *    handle_post_execution_cleanup() (which would delete the schedule), and the
 *    run_state must be marked failed/partial rather than completed;
 *  - when "Prevent AI Generation" is enabled, a queued slice must terminate
 *    early and persist a resumable cursor instead of generating posts.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Schedule_Batch_Slice_Regression extends WP_UnitTestCase {

	private function make_repo() {
		return $this->getMockBuilder( 'AIPS_Schedule_Repository' )
			->onlyMethods( array( 'get_by_id', 'update_run_state', 'update_batch_progress', 'clear_batch_progress' ) )
			->getMock();
	}

	private function make_result_handler() {
		return $this->getMockBuilder( 'AIPS_Schedule_Result_Handler' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_or_create_schedule_history', 'handle_post_execution_cleanup', 'handle_execution_terminated_by_setting' ) )
			->getMock();
	}

	private function make_generator() {
		return $this->getMockBuilder( 'AIPS_Generator' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'generate_post' ) )
			->getMock();
	}

	private function make_template_repo( $template ) {
		$repo = $this->getMockBuilder( 'AIPS_Template_Repository' )
			->onlyMethods( array( 'get_by_id' ) )
			->getMock();
		$repo->method( 'get_by_id' )->willReturn( $template );
		return $repo;
	}

	private function make_type_selector() {
		$sel = $this->getMockBuilder( 'AIPS_Template_Type_Selector' )
			->onlyMethods( array( 'select_structure' ) )
			->getMock();
		$sel->method( 'select_structure' )->willReturn( 0 );
		return $sel;
	}

	private function build_service( $repo, $template_repo, $generator, $result_handler ) {
		return new AIPS_Schedule_Generation_Service(
			$repo,
			$template_repo,
			$generator,
			null,
			$this->make_type_selector(),
			$this->createMock( AIPS_Logger_Interface::class ),
			null,
			$result_handler,
			null
		);
	}

	private function schedule_row( $frequency = 'once' ) {
		return (object) array(
			'id'          => 321,
			'template_id' => 654,
			'topic'       => 'Regression Topic',
			'frequency'   => $frequency,
			'is_active'   => 1,
			'run_state'   => wp_json_encode( array( 'status' => 'batch_queued' ) ),
		);
	}

	private function template_row() {
		return (object) array(
			'id'            => 654,
			'name'          => 'Regression Template',
			'post_quantity' => 3,
			'is_active'     => 1,
		);
	}

	/**
	 * A WP_Error from the generator must flow to handle_post_execution_cleanup()
	 * as a WP_Error (never a hardcoded success), so a failed `once` schedule is
	 * not deleted, and the run_state is marked failed rather than completed.
	 */
	public function test_generator_error_does_not_delete_a_once_schedule() {
		$repo           = $this->make_repo();
		$result_handler = $this->make_result_handler();
		$generator      = $this->make_generator();

		$repo->method( 'get_by_id' )->willReturn( $this->schedule_row( 'once' ) );

		$run_states = array();
		$repo->method( 'update_run_state' )->willReturnCallback(
			function ( $id, $state ) use ( &$run_states ) {
				$run_states[] = $state;
				return true;
			}
		);

		$result_handler->method( 'get_or_create_schedule_history' )->willReturn( null );

		$generator->expects( $this->once() )
			->method( 'generate_post' )
			->willReturn( new WP_Error( 'gen_fail', 'boom' ) );

		$cleanup_results = array();
		$result_handler->expects( $this->once() )
			->method( 'handle_post_execution_cleanup' )
			->willReturnCallback(
				function ( $schedule_obj, $result ) use ( &$cleanup_results ) {
					$cleanup_results[] = $result;
					return null;
				}
			);

		$result_handler->expects( $this->never() )->method( 'handle_execution_terminated_by_setting' );

		$this->build_service( $repo, $this->make_template_repo( $this->template_row() ), $generator, $result_handler )
			->process_batch_slice( 321, 0, 3, 3 );

		$this->assertCount( 1, $cleanup_results );
		$this->assertInstanceOf( 'WP_Error', $cleanup_results[0], 'Failed once-schedule must receive a WP_Error, not a success value.' );

		$last_state = end( $run_states );
		$this->assertSame( 'failed', $last_state['status'], 'run_state must be marked failed when the first post errors.' );
	}

	/**
	 * With "Prevent AI Generation" enabled, the slice must terminate early via
	 * handle_execution_terminated_by_setting() with a resumable cursor, and must
	 * not call the generator at all.
	 */
	public function test_prevent_ai_generation_terminates_with_resumable_cursor() {
		update_option( 'aips_prevent_scheduled_ai_generation', 1 );

		$repo           = $this->make_repo();
		$result_handler = $this->make_result_handler();
		$generator      = $this->make_generator();

		$repo->method( 'get_by_id' )->willReturn( $this->schedule_row( 'daily' ) );
		$repo->method( 'update_run_state' )->willReturn( true );
		$result_handler->method( 'get_or_create_schedule_history' )->willReturn( null );

		$generator->expects( $this->never() )->method( 'generate_post' );

		$captured = array();
		$result_handler->expects( $this->once() )
			->method( 'handle_execution_terminated_by_setting' )
			->willReturnCallback(
				function ( $schedule_obj, $history, $is_manual, $label, $options = array() ) use ( &$captured ) {
					$captured = $options;
					return null;
				}
			);

		$this->build_service( $repo, $this->make_template_repo( $this->template_row() ), $generator, $result_handler )
			->process_batch_slice( 321, 0, 3, 3 );

		$this->assertArrayHasKey( 'run_state', $captured );
		$this->assertTrue( ! empty( $captured['run_state']['resumable'] ), 'Terminated slice must be marked resumable.' );
		$this->assertSame( 0, (int) $captured['run_state']['resume_index'], 'Resume index must equal the slice start index.' );
	}

	/**
	 * Subsequent batch slices must merge prior slices' post_ids in both
	 * update_batch_progress() and aips_schedule_execution_completed ([M-03]).
	 */
	public function test_batch_slice_merges_prior_post_ids_across_slices_and_completion_hook() {
		delete_option( 'aips_prevent_scheduled_ai_generation' );

		$repo           = $this->make_repo();
		$result_handler = $this->make_result_handler();
		$generator      = $this->make_generator();

		$row                 = $this->schedule_row( 'daily' );
		$row->batch_progress = wp_json_encode( array(
			'completed'  => 2,
			'total'      => 3,
			'last_index' => 1,
			'post_ids'   => array( 101, 102 ),
		) );

		$repo->method( 'get_by_id' )->willReturn( $row );
		$repo->method( 'update_run_state' )->willReturn( true );
		$repo->method( 'clear_batch_progress' )->willReturn( true );
		$result_handler->method( 'get_or_create_schedule_history' )->willReturn( null );
		$result_handler->method( 'handle_post_execution_cleanup' )->willReturn( null );

		$generator->expects( $this->once() )
			->method( 'generate_post' )
			->willReturn( 103 );

		$progress_post_ids = array();
		$repo->expects( $this->once() )
			->method( 'update_batch_progress' )
			->willReturnCallback(
				function ( $id, $completed, $total, $last_index, $post_ids ) use ( &$progress_post_ids ) {
					$progress_post_ids = $post_ids;
					return true;
				}
			);

		$completed_hook_ids = array();
		$listener           = function ( $schedule_id, $post_ids ) use ( &$completed_hook_ids ) {
			$completed_hook_ids = $post_ids;
		};
		add_action( 'aips_schedule_execution_completed', $listener, 10, 2 );

		try {
			$this->build_service( $repo, $this->make_template_repo( $this->template_row() ), $generator, $result_handler )
				->process_batch_slice( 321, 2, 1, 3 );
		} finally {
			remove_action( 'aips_schedule_execution_completed', $listener, 10 );
		}

		$this->assertSame( array( 101, 102, 103 ), $progress_post_ids );
		$this->assertSame( array( 101, 102, 103 ), $completed_hook_ids );
	}
}
