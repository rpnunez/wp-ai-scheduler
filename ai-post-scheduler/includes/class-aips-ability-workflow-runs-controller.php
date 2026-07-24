<?php
/**
 * Ability Workflow Runs Controller
 *
 * AJAX handlers for dispatching and browsing Ability Workflow runs.
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Ability_Workflow_Runs_Controller
 */
class AIPS_Ability_Workflow_Runs_Controller {

	/**
	 * @var AIPS_Ability_Workflow_Repository
	 */
	private $repository;

	/**
	 * @var AIPS_Ability_Workflow_Executor
	 */
	private $executor;

	/**
	 * Constructor. Registers all wp_ajax_* hooks owned by this controller.
	 *
	 * @param AIPS_Ability_Workflow_Repository|null $repository Repository.
	 * @param AIPS_Ability_Workflow_Executor|null    $executor   Executor.
	 */
	public function __construct( $repository = null, $executor = null ) {
		$this->repository = $repository ?: AIPS_Ability_Workflow_Repository::instance();
		$this->executor    = $executor ?: new AIPS_Ability_Workflow_Executor();

		add_action( 'wp_ajax_aips_run_ability_workflow_now', array( $this, 'ajax_run_now' ) );
		add_action( 'wp_ajax_aips_list_ability_workflow_runs', array( $this, 'ajax_list_runs' ) );
		add_action( 'wp_ajax_aips_get_ability_workflow_run', array( $this, 'ajax_get_run' ) );
		add_action( 'wp_ajax_aips_cancel_ability_workflow_run', array( $this, 'ajax_cancel_run' ) );
	}

	/**
	 * Dispatch a workflow run immediately. Never blocks — the run executes
	 * asynchronously via WP-Cron.
	 */
	public function ajax_run_now() {
		if ( ! check_ajax_referer( 'aips_ajax_nonce', 'nonce', false ) ) {
			AIPS_Ajax_Response::error( __( 'Invalid nonce.', 'ai-post-scheduler' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			AIPS_Ajax_Response::permission_denied();
		}

		$workflow_id = isset( $_POST['workflow_id'] ) ? absint( $_POST['workflow_id'] ) : 0;

		if ( $workflow_id <= 0 ) {
			AIPS_Ajax_Response::invalid_request();
		}

		$trigger_context = array(
			'type'    => 'manual',
			'user_id' => get_current_user_id(),
		);

		$run_id = $this->executor->dispatch_run( $workflow_id, $trigger_context );

		if ( is_wp_error( $run_id ) ) {
			AIPS_Ajax_Response::error( $run_id->get_error_message() );
		}

		do_action( 'aips_ability_workflow_changed', array(
			'action'      => 'run_dispatched',
			'workflow_id' => $workflow_id,
			'run_id'      => absint( $run_id ),
			'user_id'     => get_current_user_id(),
		) );

		AIPS_Ajax_Response::success(
			array( 'run_id' => $run_id ),
			__( 'Workflow run started.', 'ai-post-scheduler' )
		);
	}

	/**
	 * List runs for a workflow.
	 */
	public function ajax_list_runs() {
		if ( ! check_ajax_referer( 'aips_ajax_nonce', 'nonce', false ) ) {
			AIPS_Ajax_Response::error( __( 'Invalid nonce.', 'ai-post-scheduler' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			AIPS_Ajax_Response::permission_denied();
		}

		$workflow_id = isset( $_POST['workflow_id'] ) ? absint( $_POST['workflow_id'] ) : 0;

		if ( $workflow_id <= 0 ) {
			AIPS_Ajax_Response::invalid_request();
		}

		$args = array(
			'status'   => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '',
			'page'     => isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1,
			'per_page' => isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20,
		);

		$result = $this->repository->list_runs( $workflow_id, $args );

		AIPS_Ajax_Response::success(
			array(
				'runs'  => array_map( function ( AIPS_Ability_Workflow_Run $run ) {
					return $run->to_array();
				}, $result['items'] ),
				'total' => $result['total'],
			)
		);
	}

	/**
	 * Fetch a run and its per-step results.
	 */
	public function ajax_get_run() {
		if ( ! check_ajax_referer( 'aips_ajax_nonce', 'nonce', false ) ) {
			AIPS_Ajax_Response::error( __( 'Invalid nonce.', 'ai-post-scheduler' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			AIPS_Ajax_Response::permission_denied();
		}

		$run_id = isset( $_POST['run_id'] ) ? absint( $_POST['run_id'] ) : 0;
		$run    = $run_id > 0 ? $this->repository->get_run( $run_id ) : null;

		if ( ! $run ) {
			AIPS_Ajax_Response::not_found( __( 'Run', 'ai-post-scheduler' ) );
		}

		$step_runs = $this->repository->get_step_runs( $run_id );

		AIPS_Ajax_Response::success(
			array(
				'run'       => $run->to_array(),
				'step_runs' => array_map( function ( AIPS_Ability_Workflow_Step_Run $step_run ) {
					return $step_run->to_array();
				}, $step_runs ),
			)
		);
	}

	/**
	 * Cancel a queued/running run. Best-effort — an in-flight cron
	 * invocation completes its current step before observing the
	 * cancellation and stopping.
	 */
	public function ajax_cancel_run() {
		if ( ! check_ajax_referer( 'aips_ajax_nonce', 'nonce', false ) ) {
			AIPS_Ajax_Response::error( __( 'Invalid nonce.', 'ai-post-scheduler' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			AIPS_Ajax_Response::permission_denied();
		}

		$run_id = isset( $_POST['run_id'] ) ? absint( $_POST['run_id'] ) : 0;

		if ( $run_id <= 0 || ! $this->repository->get_run( $run_id ) ) {
			AIPS_Ajax_Response::not_found( __( 'Run', 'ai-post-scheduler' ) );
		}

		$this->repository->update_run_status(
			$run_id,
			AIPS_Ability_Workflow_Repository::RUN_STATUS_CANCELLED,
			array( 'finished_at' => time() )
		);

		do_action( 'aips_ability_workflow_changed', array(
			'action'  => 'run_cancelled',
			'run_id'  => $run_id,
			'user_id' => get_current_user_id(),
		) );

		AIPS_Ajax_Response::success( array(), __( 'Workflow run cancelled.', 'ai-post-scheduler' ) );
	}
}
