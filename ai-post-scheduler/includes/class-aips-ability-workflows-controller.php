<?php
/**
 * Ability Workflows Controller
 *
 * AJAX handlers for Ability Workflow header/step CRUD.
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Ability_Workflows_Controller
 */
class AIPS_Ability_Workflows_Controller {

	/**
	 * @var AIPS_Ability_Workflow_Repository
	 */
	private $repository;

	/**
	 * @var AIPS_Ability_Workflow_Document_Validator
	 */
	private $validator;

	/**
	 * Constructor. Registers all wp_ajax_* hooks owned by this controller.
	 *
	 * @param AIPS_Ability_Workflow_Repository|null          $repository Repository.
	 * @param AIPS_Ability_Workflow_Document_Validator|null $validator  Document validator.
	 */
	public function __construct( $repository = null, $validator = null ) {
		$this->repository = $repository ?: AIPS_Ability_Workflow_Repository::instance();
		$this->validator   = $validator ?: new AIPS_Ability_Workflow_Document_Validator();

		add_action( 'wp_ajax_aips_save_ability_workflow', array( $this, 'ajax_save_workflow' ) );
		add_action( 'wp_ajax_aips_get_ability_workflow', array( $this, 'ajax_get_workflow' ) );
		add_action( 'wp_ajax_aips_list_ability_workflows', array( $this, 'ajax_list_workflows' ) );
		add_action( 'wp_ajax_aips_delete_ability_workflow', array( $this, 'ajax_delete_workflow' ) );
		add_action( 'wp_ajax_aips_duplicate_ability_workflow', array( $this, 'ajax_duplicate_workflow' ) );
		add_action( 'wp_ajax_aips_archive_ability_workflow', array( $this, 'ajax_archive_workflow' ) );
		add_action( 'wp_ajax_aips_save_ability_workflow_steps', array( $this, 'ajax_save_workflow_steps' ) );
	}

	/**
	 * Create or update a workflow's header fields.
	 */
	public function ajax_save_workflow() {
		if ( ! check_ajax_referer( 'aips_ajax_nonce', 'nonce', false ) ) {
			AIPS_Ajax_Response::error( __( 'Invalid nonce.', 'ai-post-scheduler' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			AIPS_Ajax_Response::permission_denied();
		}

		$workflow_id = isset( $_POST['workflow_id'] ) ? absint( $_POST['workflow_id'] ) : 0;
		$name        = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

		if ( '' === trim( $name ) ) {
			AIPS_Ajax_Response::error( __( 'Workflow name is required.', 'ai-post-scheduler' ) );
		}

		$data = array(
			'name'           => $name,
			'description'    => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'status'         => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'draft',
			'trigger_type'   => isset( $_POST['trigger_type'] ) ? sanitize_key( wp_unslash( $_POST['trigger_type'] ) ) : 'manual',
			'trigger_config' => $this->decode_json_field( $_POST['trigger_config'] ?? '' ),
			'settings'       => $this->decode_json_field( $_POST['settings'] ?? '' ),
		);

		if ( $workflow_id > 0 ) {
			$data['updated_by'] = get_current_user_id();
			$result = $this->repository->update_workflow( $workflow_id, $data );

			if ( is_wp_error( $result ) ) {
				AIPS_Ajax_Response::error( $result->get_error_message() );
			}
		} else {
			$data['created_by'] = get_current_user_id();
			$data['updated_by'] = get_current_user_id();
			$workflow_id = $this->repository->create_workflow( $data );

			if ( is_wp_error( $workflow_id ) ) {
				AIPS_Ajax_Response::error( $workflow_id->get_error_message() );
			}
		}

		do_action( 'aips_ability_workflow_changed', array(
			'action'      => $workflow_id ? 'saved' : 'created',
			'workflow_id' => absint( $workflow_id ),
			'user_id'     => get_current_user_id(),
		) );

		$workflow = $this->repository->get_workflow( $workflow_id );

		AIPS_Ajax_Response::success(
			array( 'workflow' => $workflow ? $workflow->to_array() : null ),
			__( 'Workflow saved successfully.', 'ai-post-scheduler' )
		);
	}

	/**
	 * Fetch a single workflow with its steps.
	 */
	public function ajax_get_workflow() {
		if ( ! check_ajax_referer( 'aips_ajax_nonce', 'nonce', false ) ) {
			AIPS_Ajax_Response::error( __( 'Invalid nonce.', 'ai-post-scheduler' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			AIPS_Ajax_Response::permission_denied();
		}

		$workflow_id = isset( $_POST['workflow_id'] ) ? absint( $_POST['workflow_id'] ) : 0;
		$workflow    = $workflow_id > 0 ? $this->repository->get_workflow( $workflow_id ) : null;

		if ( ! $workflow ) {
			AIPS_Ajax_Response::not_found( __( 'Workflow', 'ai-post-scheduler' ) );
		}

		$steps = $this->repository->get_steps( $workflow_id );

		AIPS_Ajax_Response::success(
			array(
				'workflow' => $workflow->to_array(),
				'steps'    => array_map( function ( AIPS_Ability_Workflow_Step $step ) {
					return $step->to_array();
				}, $steps ),
			)
		);
	}

	/**
	 * List workflows with optional filters and pagination.
	 */
	public function ajax_list_workflows() {
		if ( ! check_ajax_referer( 'aips_ajax_nonce', 'nonce', false ) ) {
			AIPS_Ajax_Response::error( __( 'Invalid nonce.', 'ai-post-scheduler' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			AIPS_Ajax_Response::permission_denied();
		}

		$args = array(
			'status'       => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '',
			'trigger_type' => isset( $_POST['trigger_type'] ) ? sanitize_key( wp_unslash( $_POST['trigger_type'] ) ) : '',
			'search'       => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
			'page'         => isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1,
			'per_page'     => isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20,
		);

		$result = $this->repository->list_workflows( $args );

		AIPS_Ajax_Response::success(
			array(
				'workflows' => array_map( function ( AIPS_Ability_Workflow $workflow ) {
					return $workflow->to_array();
				}, $result['items'] ),
				'total'     => $result['total'],
			)
		);
	}

	/**
	 * Delete a workflow (and its steps). Run history is preserved.
	 */
	public function ajax_delete_workflow() {
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

		if ( ! $this->repository->delete_workflow( $workflow_id ) ) {
			AIPS_Ajax_Response::error( __( 'Failed to delete workflow.', 'ai-post-scheduler' ) );
		}

		do_action( 'aips_ability_workflow_changed', array(
			'action'      => 'deleted',
			'workflow_id' => $workflow_id,
			'user_id'     => get_current_user_id(),
		) );

		AIPS_Ajax_Response::success( array(), __( 'Workflow deleted successfully.', 'ai-post-scheduler' ) );
	}

	/**
	 * Duplicate a workflow and its steps as a new draft.
	 */
	public function ajax_duplicate_workflow() {
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

		$new_id = $this->repository->duplicate_workflow( $workflow_id );

		if ( is_wp_error( $new_id ) ) {
			AIPS_Ajax_Response::error( $new_id->get_error_message() );
		}

		do_action( 'aips_ability_workflow_changed', array(
			'action'      => 'duplicated',
			'workflow_id' => absint( $new_id ),
			'user_id'     => get_current_user_id(),
		) );

		$workflow = $this->repository->get_workflow( $new_id );

		AIPS_Ajax_Response::success(
			array( 'workflow' => $workflow ? $workflow->to_array() : null ),
			__( 'Workflow duplicated successfully.', 'ai-post-scheduler' )
		);
	}

	/**
	 * Archive a workflow.
	 */
	public function ajax_archive_workflow() {
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

		if ( ! $this->repository->archive_workflow( $workflow_id ) ) {
			AIPS_Ajax_Response::error( __( 'Failed to archive workflow.', 'ai-post-scheduler' ) );
		}

		do_action( 'aips_ability_workflow_changed', array(
			'action'      => 'archived',
			'workflow_id' => $workflow_id,
			'user_id'     => get_current_user_id(),
		) );

		AIPS_Ajax_Response::success( array(), __( 'Workflow archived successfully.', 'ai-post-scheduler' ) );
	}

	/**
	 * Validate and replace all steps for a workflow.
	 */
	public function ajax_save_workflow_steps() {
		if ( ! check_ajax_referer( 'aips_ajax_nonce', 'nonce', false ) ) {
			AIPS_Ajax_Response::error( __( 'Invalid nonce.', 'ai-post-scheduler' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			AIPS_Ajax_Response::permission_denied();
		}

		$workflow_id = isset( $_POST['workflow_id'] ) ? absint( $_POST['workflow_id'] ) : 0;

		if ( $workflow_id <= 0 || ! $this->repository->get_workflow( $workflow_id ) ) {
			AIPS_Ajax_Response::not_found( __( 'Workflow', 'ai-post-scheduler' ) );
		}

		$steps = $this->decode_json_field( $_POST['steps'] ?? '' );

		if ( ! is_array( $steps ) ) {
			AIPS_Ajax_Response::invalid_request( __( 'Steps payload must be a JSON array.', 'ai-post-scheduler' ) );
		}

		$validation = $this->validator->validate( array( 'steps' => $steps ) );

		if ( is_wp_error( $validation ) ) {
			AIPS_Ajax_Response::error(
				$validation->get_error_message(),
				$validation->get_error_code(),
				200,
				(array) $validation->get_error_data()
			);
		}

		$result = $this->repository->save_steps( $workflow_id, $steps );

		if ( is_wp_error( $result ) ) {
			AIPS_Ajax_Response::error( $result->get_error_message() );
		}

		do_action( 'aips_ability_workflow_changed', array(
			'action'      => 'steps_saved',
			'workflow_id' => $workflow_id,
			'user_id'     => get_current_user_id(),
		) );

		$saved_steps = $this->repository->get_steps( $workflow_id );

		AIPS_Ajax_Response::success(
			array(
				'steps' => array_map( function ( AIPS_Ability_Workflow_Step $step ) {
					return $step->to_array();
				}, $saved_steps ),
			),
			__( 'Workflow steps saved successfully.', 'ai-post-scheduler' )
		);
	}

	/**
	 * Decode a JSON-encoded POST field into an array.
	 *
	 * @param mixed $raw Raw POST value.
	 * @return array
	 */
	private function decode_json_field( $raw ): array {
		if ( is_array( $raw ) ) {
			return $raw;
		}

		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}

		$decoded = json_decode( wp_unslash( $raw ), true );

		return is_array( $decoded ) ? $decoded : array();
	}
}
