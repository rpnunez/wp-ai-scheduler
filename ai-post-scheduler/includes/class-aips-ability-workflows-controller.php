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
	 * Workflow statuses an administrator can choose.
	 */
	const STATUSES = array( 'draft', 'active', 'paused', 'archived' );

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

		// The validator must be given a catalog service so save-time
		// validation actually checks that each step's ability_name refers to
		// a real, available ability — without it, AIPS_Ability_Workflow_Document_Validator::validate()
		// silently skips ability-existence checks regardless of the
		// $skip_ability_check argument, since that check is gated on
		// `$this->catalog instanceof AIPS_Ability_Catalog_Service`.
		if ( $validator ) {
			$this->validator = $validator;
		} else {
			$container = AIPS_Container::get_instance();
			$catalog   = $container->has( AIPS_Ability_Catalog_Service::class )
				? $container->make( AIPS_Ability_Catalog_Service::class )
				: new AIPS_Ability_Catalog_Service();

			$this->validator = new AIPS_Ability_Workflow_Document_Validator( null, null, $catalog );
		}

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

		AIPS_Ability_Workflows_Feature::guard_ajax();

		$workflow_id = isset( $_POST['workflow_id'] ) ? absint( $_POST['workflow_id'] ) : 0;
		$is_update   = $workflow_id > 0;
		$name        = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

		if ( '' === trim( $name ) ) {
			AIPS_Ajax_Response::error( __( 'Workflow name is required.', 'ai-post-scheduler' ) );
		}

		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'draft';

		if ( ! in_array( $status, self::STATUSES, true ) ) {
			AIPS_Ajax_Response::invalid_request( __( 'Unknown workflow status.', 'ai-post-scheduler' ) );
		}

		$existing          = $is_update ? $this->repository->get_workflow( $workflow_id ) : null;
		$existing_settings = $existing && is_array( $existing->settings ) ? $existing->settings : array();

		$data = array(
			'name'           => $name,
			'description'    => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'status'         => $status,
			// Manual is the only trigger that exists; the column keeps room for others.
			'trigger_type'   => 'manual',
			'trigger_config' => array(),
			'settings'       => $this->sanitize_settings( $this->decode_json_field( $_POST['settings'] ?? '' ), $existing_settings ),
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
			'action'      => $is_update ? 'saved' : 'created',
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

		AIPS_Ability_Workflows_Feature::guard_ajax();

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

		AIPS_Ability_Workflows_Feature::guard_ajax();

		$args = array(
			'status'       => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '',
			'trigger_type' => isset( $_POST['trigger_type'] ) ? sanitize_key( wp_unslash( $_POST['trigger_type'] ) ) : '',
			'search'       => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
			'page'         => isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1,
			'per_page'     => isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20,
		);

		// Opening the list is a cheap moment to restart execution that stalled (queued
		// runs with no open background process, for example after the process was stopped).
		if ( $this->repository->count_open_runs() > 0 ) {
			$process = AIPS_Container::get_instance()->make( AIPS_Background_Process_Manager::class )->get( AIPS_Ability_Workflows_Process::KEY );

			if ( $process instanceof AIPS_Ability_Workflows_Process ) {
				$process->ensure_scheduled();
			}
		}

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

		AIPS_Ability_Workflows_Feature::guard_ajax();

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

		AIPS_Ability_Workflows_Feature::guard_ajax();

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

		AIPS_Ability_Workflows_Feature::guard_ajax();

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

		AIPS_Ability_Workflows_Feature::guard_ajax();

		$workflow_id = isset( $_POST['workflow_id'] ) ? absint( $_POST['workflow_id'] ) : 0;

		if ( $workflow_id <= 0 || ! $this->repository->get_workflow( $workflow_id ) ) {
			AIPS_Ajax_Response::not_found( __( 'Workflow', 'ai-post-scheduler' ) );
		}

		$steps = $this->decode_steps_field( $_POST['steps'] ?? '' );

		if ( is_wp_error( $steps ) ) {
			AIPS_Ajax_Response::invalid_request( $steps->get_error_message() );
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

		if ( isset( $_POST['approved_abilities'] ) ) {
			$this->save_approved_abilities( $workflow_id, $steps, $this->decode_json_field( $_POST['approved_abilities'] ) );
		}

		do_action( 'aips_ability_workflow_changed', array(
			'action'      => 'steps_saved',
			'workflow_id' => $workflow_id,
			'user_id'     => get_current_user_id(),
		) );

		$saved_steps    = $this->repository->get_steps( $workflow_id );
		$saved_workflow = $this->repository->get_workflow( $workflow_id );

		AIPS_Ajax_Response::success(
			array(
				'approved_abilities' => $saved_workflow && isset( $saved_workflow->settings['approved_abilities'] ) ? $saved_workflow->settings['approved_abilities'] : array(),
				'steps'              => array_map( function ( AIPS_Ability_Workflow_Step $step ) {
					return $step->to_array();
				}, $saved_steps ),
			),
			__( 'Workflow steps saved successfully.', 'ai-post-scheduler' )
		);
	}

	/**
	 * Store the abilities the builder approved, limited to those the saved steps use.
	 *
	 * @param int   $workflow_id Workflow ID.
	 * @param array $steps       Saved step definitions.
	 * @param array $approved    Ability names sent by the builder.
	 */
	private function save_approved_abilities( int $workflow_id, array $steps, array $approved ): void {
		$workflow = $this->repository->get_workflow( $workflow_id );

		if ( ! $workflow ) {
			return;
		}

		$used     = array();
		foreach ( $steps as $step ) {
			if ( is_array( $step ) && ! empty( $step['ability_name'] ) ) {
				$used[] = (string) $step['ability_name'];
			}
		}

		$settings = is_array( $workflow->settings ) ? $workflow->settings : array();
		$before   = $settings['approved_abilities'] ?? array();

		$settings['approved_abilities'] = array_values( array_intersect( $this->sanitize_ability_names( $approved ), $used ) );

		if ( $before === $settings['approved_abilities'] ) {
			return;
		}

		$this->repository->update_workflow( $workflow_id, array( 'settings' => $settings, 'updated_by' => get_current_user_id() ) );
	}

	/**
	 * Keep only the settings the executor understands, with sane bounds.
	 *
	 * The approved-abilities list is edited from the builder, not the settings
	 * form, so a save that does not send it keeps the list already stored.
	 *
	 * @param array $raw      Decoded settings from the request.
	 * @param array $existing Settings already stored for the workflow.
	 * @return array
	 */
	private function sanitize_settings( array $raw, array $existing = array() ): array {
		$approved = array_key_exists( 'approved_abilities', $raw ) ? $raw['approved_abilities'] : ( $existing['approved_abilities'] ?? array() );

		return array(
			'max_steps'                   => max( 1, min( 100, isset( $raw['max_steps'] ) ? (int) $raw['max_steps'] : 20 ) ),
			'max_runtime_seconds'         => max( 1, min( 3600, isset( $raw['max_runtime_seconds'] ) ? (int) $raw['max_runtime_seconds'] : 120 ) ),
			'allow_destructive_abilities' => ! empty( $raw['allow_destructive_abilities'] ),
			'approved_abilities'          => $this->sanitize_ability_names( $approved ),
		);
	}

	/**
	 * Keep valid, unique ability names (for example "ai/title-generation").
	 *
	 * @param mixed $names Raw list.
	 * @return string[]
	 */
	private function sanitize_ability_names( $names ): array {
		if ( ! is_array( $names ) ) {
			return array();
		}

		$clean = array();

		foreach ( $names as $name ) {
			if ( is_string( $name ) && preg_match( '#^[a-z0-9][a-z0-9_-]*(/[a-z0-9][a-z0-9_-]*)*$#', $name ) ) {
				$clean[ $name ] = $name;
			}
		}

		return array_values( $clean );
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

	/**
	 * Decode the steps payload strictly, distinguishing "explicitly empty"
	 * from "malformed JSON" — unlike decode_json_field(), a malformed value
	 * here must NOT silently coerce to an empty array, since an empty steps
	 * array passes validation trivially and would wipe every existing step
	 * via AIPS_Ability_Workflow_Repository::save_steps()'s replace-all
	 * semantics.
	 *
	 * @param mixed $raw Raw POST value.
	 * @return array|WP_Error Decoded steps array, or WP_Error when the raw
	 *                         value was non-empty but failed to decode.
	 */
	private function decode_steps_field( $raw ) {
		if ( is_array( $raw ) ) {
			return $raw;
		}

		if ( ! is_string( $raw ) ) {
			return array();
		}

		$raw = trim( wp_unslash( $raw ) );

		if ( '' === $raw ) {
			return array();
		}

		$decoded = json_decode( $raw, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return new WP_Error( 'ability_workflow_steps_payload_invalid', __( 'Steps payload is not valid JSON.', 'ai-post-scheduler' ) );
		}

		return $decoded;
	}
}
