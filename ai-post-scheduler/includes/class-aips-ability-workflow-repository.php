<?php
/**
 * Ability Workflow Repository
 *
 * Centralizes all `$wpdb` access for the four Ability Workflow tables:
 * `aips_ability_workflows`, `aips_ability_workflow_steps`,
 * `aips_ability_workflow_runs`, and `aips_ability_workflow_step_runs`.
 *
 * Controllers, services, and the executor must go through this class for
 * any persistence — no direct SQL outside this file.
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Ability_Workflow_Repository
 */
class AIPS_Ability_Workflow_Repository {

	/**
	 * Run status constants.
	 */
	const RUN_STATUS_QUEUED    = 'queued';
	const RUN_STATUS_RUNNING   = 'running';
	const RUN_STATUS_COMPLETED = 'completed';
	const RUN_STATUS_FAILED    = 'failed';
	const RUN_STATUS_CANCELLED = 'cancelled';

	/**
	 * Terminal run statuses — once reached, status transitions are ignored.
	 */
	const RUN_TERMINAL_STATUSES = array(
		self::RUN_STATUS_COMPLETED,
		self::RUN_STATUS_FAILED,
		self::RUN_STATUS_CANCELLED,
	);

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * @var string
	 */
	private $workflows_table;

	/**
	 * @var string
	 */
	private $steps_table;

	/**
	 * @var string
	 */
	private $runs_table;

	/**
	 * @var string
	 */
	private $step_runs_table;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb            = $wpdb;
		$this->workflows_table = $wpdb->prefix . 'aips_ability_workflows';
		$this->steps_table     = $wpdb->prefix . 'aips_ability_workflow_steps';
		$this->runs_table      = $wpdb->prefix . 'aips_ability_workflow_runs';
		$this->step_runs_table = $wpdb->prefix . 'aips_ability_workflow_step_runs';
	}

	// -----------------------------------------------------------------------
	// Workflow CRUD
	// -----------------------------------------------------------------------

	/**
	 * Create a new workflow.
	 *
	 * @param array $data Workflow fields (name, description, status, trigger_type,
	 *                     trigger_config, settings, created_by, updated_by).
	 * @return int|WP_Error New workflow ID, or WP_Error on failure.
	 */
	public function create_workflow( array $data ) {
		if ( empty( $data['name'] ) ) {
			return new WP_Error( 'ability_workflow_invalid', __( 'Workflow name is required.', 'ai-post-scheduler' ) );
		}

		$now = time();

		$result = $this->wpdb->insert(
			$this->workflows_table,
			array(
				'uuid'           => wp_generate_uuid4(),
				'name'           => (string) $data['name'],
				'description'    => isset( $data['description'] ) ? (string) $data['description'] : null,
				'status'         => isset( $data['status'] ) ? (string) $data['status'] : 'draft',
				'trigger_type'   => isset( $data['trigger_type'] ) ? (string) $data['trigger_type'] : 'manual',
				'trigger_config' => wp_json_encode( isset( $data['trigger_config'] ) && is_array( $data['trigger_config'] ) ? $data['trigger_config'] : array() ),
				'settings'       => wp_json_encode( isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array() ),
				'version'        => 1,
				'created_by'     => isset( $data['created_by'] ) ? (int) $data['created_by'] : null,
				'updated_by'     => isset( $data['updated_by'] ) ? (int) $data['updated_by'] : null,
				'created_at'     => $now,
				'updated_at'     => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d' )
		);

		if ( $result === false ) {
			return $this->build_error( 'ability_workflow_create_failed', __( 'Failed to create workflow.', 'ai-post-scheduler' ) );
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update workflow header fields.
	 *
	 * @param int   $workflow_id Workflow ID.
	 * @param array $data        Fields to update (any of: name, description, status,
	 *                            trigger_type, trigger_config, settings, updated_by).
	 * @return bool|WP_Error
	 */
	public function update_workflow( int $workflow_id, array $data ) {
		$update_data = array();
		$format      = array();

		$string_fields = array( 'name', 'description', 'status', 'trigger_type' );
		foreach ( $string_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$update_data[ $field ] = $data[ $field ] !== null ? (string) $data[ $field ] : null;
				$format[]              = '%s';
			}
		}

		foreach ( array( 'trigger_config', 'settings' ) as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$update_data[ $field ] = wp_json_encode( is_array( $data[ $field ] ) ? $data[ $field ] : array() );
				$format[]              = '%s';
			}
		}

		if ( array_key_exists( 'updated_by', $data ) ) {
			$update_data['updated_by'] = $data['updated_by'] !== null ? (int) $data['updated_by'] : null;
			$format[]                  = '%d';
		}

		if ( empty( $update_data ) ) {
			return true;
		}

		$update_data['updated_at'] = time();
		$format[]                  = '%d';

		$result = $this->wpdb->update(
			$this->workflows_table,
			$update_data,
			array( 'id' => $workflow_id ),
			$format,
			array( '%d' )
		);

		if ( $result === false ) {
			return $this->build_error( 'ability_workflow_update_failed', __( 'Failed to update workflow.', 'ai-post-scheduler' ) );
		}

		return true;
	}

	/**
	 * Fetch a workflow by ID.
	 *
	 * @param int $workflow_id Workflow ID.
	 * @return AIPS_Ability_Workflow|null
	 */
	public function get_workflow( int $workflow_id ): ?AIPS_Ability_Workflow {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->workflows_table} WHERE id = %d", $workflow_id )
		);

		return $row ? AIPS_Ability_Workflow::from_row( $row ) : null;
	}

	/**
	 * Fetch a workflow by UUID.
	 *
	 * @param string $uuid Workflow UUID.
	 * @return AIPS_Ability_Workflow|null
	 */
	public function get_workflow_by_uuid( string $uuid ): ?AIPS_Ability_Workflow {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->workflows_table} WHERE uuid = %s", $uuid )
		);

		return $row ? AIPS_Ability_Workflow::from_row( $row ) : null;
	}

	/**
	 * List workflows with optional filters and pagination.
	 *
	 * @param array $args {
	 *     @type string $status       Optional status filter.
	 *     @type string $trigger_type Optional trigger_type filter.
	 *     @type string $search       Optional name search.
	 *     @type string $orderby      Column to order by (whitelisted).
	 *     @type string $order        ASC|DESC.
	 *     @type int    $per_page     Results per page (0 = no limit).
	 *     @type int    $page         1-indexed page number.
	 * }
	 * @return array { items: AIPS_Ability_Workflow[], total: int }
	 */
	public function list_workflows( array $args = array() ): array {
		$defaults = array(
			'status'       => '',
			'trigger_type' => '',
			'search'       => '',
			'orderby'      => 'updated_at',
			'order'        => 'DESC',
			'per_page'     => 20,
			'page'         => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$where_clauses = array();
		$where_args    = array();

		if ( '' !== $args['status'] ) {
			$where_clauses[] = 'status = %s';
			$where_args[]    = $args['status'];
		}

		if ( '' !== $args['trigger_type'] ) {
			$where_clauses[] = 'trigger_type = %s';
			$where_args[]    = $args['trigger_type'];
		}

		if ( '' !== $args['search'] ) {
			$where_clauses[] = 'name LIKE %s';
			$where_args[]    = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
		}

		$where_sql = empty( $where_clauses ) ? '' : 'WHERE ' . implode( ' AND ', $where_clauses );

		$allowed_orderby = array( 'name', 'status', 'trigger_type', 'created_at', 'updated_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'updated_at';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$total_sql = "SELECT COUNT(*) FROM {$this->workflows_table} $where_sql";
		$total     = (int) ( empty( $where_args )
			? $this->wpdb->get_var( $total_sql )
			: $this->wpdb->get_var( $this->wpdb->prepare( $total_sql, $where_args ) ) );

		$limit_sql   = '';
		$limit_args  = $where_args;
		$per_page    = (int) $args['per_page'];
		if ( $per_page > 0 ) {
			$page      = max( 1, (int) $args['page'] );
			$limit_sql = 'LIMIT %d OFFSET %d';
			$limit_args[] = $per_page;
			$limit_args[] = ( $page - 1 ) * $per_page;
		}

		$sql = "SELECT * FROM {$this->workflows_table} $where_sql ORDER BY $orderby $order $limit_sql";

		$rows = empty( $limit_args )
			? $this->wpdb->get_results( $sql )
			: $this->wpdb->get_results( $this->wpdb->prepare( $sql, $limit_args ) );

		$items = array_map( array( 'AIPS_Ability_Workflow', 'from_row' ), $rows ?: array() );

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Delete a workflow and its steps. Runs/step-runs are preserved for audit history.
	 *
	 * @param int $workflow_id Workflow ID.
	 * @return bool
	 */
	public function delete_workflow( int $workflow_id ): bool {
		$this->delete_steps( $workflow_id );

		return false !== $this->wpdb->delete( $this->workflows_table, array( 'id' => $workflow_id ), array( '%d' ) );
	}

	/**
	 * Archive a workflow (soft — sets status to 'archived').
	 *
	 * @param int $workflow_id Workflow ID.
	 * @return bool
	 */
	public function archive_workflow( int $workflow_id ): bool {
		$result = $this->wpdb->update(
			$this->workflows_table,
			array(
				'status'     => 'archived',
				'updated_at' => time(),
			),
			array( 'id' => $workflow_id ),
			array( '%s', '%d' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Duplicate a workflow and its steps as a new draft.
	 *
	 * @param int         $workflow_id Source workflow ID.
	 * @param string|null $new_name    Optional new name; defaults to "<name> (Copy)".
	 * @return int|WP_Error New workflow ID, or WP_Error.
	 */
	public function duplicate_workflow( int $workflow_id, ?string $new_name = null ) {
		$source = $this->get_workflow( $workflow_id );

		if ( ! $source ) {
			return new WP_Error( 'ability_workflow_not_found', __( 'Workflow not found.', 'ai-post-scheduler' ) );
		}

		/* translators: %s: original workflow name */
		$name = $new_name ?: sprintf( __( '%s (Copy)', 'ai-post-scheduler' ), $source->name );

		$new_id = $this->create_workflow(
			array(
				'name'           => $name,
				'description'    => $source->description,
				'status'         => 'draft',
				'trigger_type'   => $source->trigger_type,
				'trigger_config' => $source->trigger_config,
				'settings'       => $source->settings,
				'created_by'     => $source->created_by,
				'updated_by'     => $source->updated_by,
			)
		);

		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		$steps = $this->get_steps( $workflow_id );

		if ( ! empty( $steps ) ) {
			$step_payload = array_map(
				function ( AIPS_Ability_Workflow_Step $step ) {
					return $step->to_array();
				},
				$steps
			);

			$this->save_steps( $new_id, $step_payload );
		}

		return $new_id;
	}

	// -----------------------------------------------------------------------
	// Step operations
	// -----------------------------------------------------------------------

	/**
	 * Replace all steps for a workflow and bump its version.
	 *
	 * @param int   $workflow_id Workflow ID.
	 * @param array $steps       Array of step field arrays (step_key, name, ability_name,
	 *                            position, depends_on, input_map, condition_tree,
	 *                            output_alias, on_success, on_failure, retry_policy).
	 * @return bool|WP_Error
	 */
	public function save_steps( int $workflow_id, array $steps ) {
		$this->delete_steps( $workflow_id );

		$now = time();

		foreach ( $steps as $index => $step ) {
			if ( empty( $step['step_key'] ) || empty( $step['ability_name'] ) ) {
				return new WP_Error( 'ability_workflow_step_invalid', __( 'Each step requires a step_key and ability_name.', 'ai-post-scheduler' ) );
			}

			$result = $this->wpdb->insert(
				$this->steps_table,
				array(
					'workflow_id'    => $workflow_id,
					'step_key'       => (string) $step['step_key'],
					'name'           => isset( $step['name'] ) ? (string) $step['name'] : null,
					'ability_name'   => (string) $step['ability_name'],
					'position'       => isset( $step['position'] ) ? (int) $step['position'] : $index,
					'depends_on'     => wp_json_encode( isset( $step['depends_on'] ) && is_array( $step['depends_on'] ) ? $step['depends_on'] : array() ),
					'input_map'      => wp_json_encode( isset( $step['input_map'] ) && is_array( $step['input_map'] ) ? $step['input_map'] : array() ),
					'condition_tree' => wp_json_encode( isset( $step['condition_tree'] ) && is_array( $step['condition_tree'] ) ? $step['condition_tree'] : array() ),
					'output_alias'   => isset( $step['output_alias'] ) ? (string) $step['output_alias'] : null,
					'on_success'     => wp_json_encode( isset( $step['on_success'] ) && is_array( $step['on_success'] ) ? $step['on_success'] : array( 'strategy' => 'continue' ) ),
					'on_failure'     => wp_json_encode( isset( $step['on_failure'] ) && is_array( $step['on_failure'] ) ? $step['on_failure'] : array( 'strategy' => 'stop' ) ),
					'retry_policy'   => wp_json_encode( isset( $step['retry_policy'] ) && is_array( $step['retry_policy'] ) ? $step['retry_policy'] : array( 'attempts' => 0, 'backoff_seconds' => 0 ) ),
					'created_at'     => $now,
					'updated_at'     => $now,
				),
				array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
			);

			if ( $result === false ) {
				return $this->build_error( 'ability_workflow_step_save_failed', __( 'Failed to save workflow step.', 'ai-post-scheduler' ) );
			}
		}

		$this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->workflows_table} SET version = version + 1, updated_at = %d WHERE id = %d",
				$now,
				$workflow_id
			)
		);

		return true;
	}

	/**
	 * Get all steps for a workflow, ordered by position.
	 *
	 * @param int $workflow_id Workflow ID.
	 * @return AIPS_Ability_Workflow_Step[]
	 */
	public function get_steps( int $workflow_id ): array {
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare( "SELECT * FROM {$this->steps_table} WHERE workflow_id = %d ORDER BY position ASC", $workflow_id )
		);

		return array_map( array( 'AIPS_Ability_Workflow_Step', 'from_row' ), $rows ?: array() );
	}

	/**
	 * Delete all steps for a workflow.
	 *
	 * @param int $workflow_id Workflow ID.
	 * @return bool
	 */
	public function delete_steps( int $workflow_id ): bool {
		return false !== $this->wpdb->delete( $this->steps_table, array( 'workflow_id' => $workflow_id ), array( '%d' ) );
	}

	/**
	 * Reorder steps by assigning new positions.
	 *
	 * @param int   $workflow_id           Workflow ID.
	 * @param array $step_id_to_position   Map of step_id => position.
	 * @return bool
	 */
	public function reorder_steps( int $workflow_id, array $step_id_to_position ): bool {
		foreach ( $step_id_to_position as $step_id => $position ) {
			$result = $this->wpdb->update(
				$this->steps_table,
				array(
					'position'   => (int) $position,
					'updated_at' => time(),
				),
				array(
					'id'          => (int) $step_id,
					'workflow_id' => $workflow_id,
				),
				array( '%d', '%d' ),
				array( '%d', '%d' )
			);

			if ( $result === false ) {
				return false;
			}
		}

		return true;
	}

	// -----------------------------------------------------------------------
	// Run operations
	// -----------------------------------------------------------------------

	/**
	 * Create a new workflow run.
	 *
	 * @param int    $workflow_id      Workflow ID.
	 * @param int    $workflow_version Workflow version this run was dispatched against.
	 * @param array  $trigger_context  Trigger context payload.
	 * @param string $correlation_id   Optional correlation ID.
	 * @return int|WP_Error New run ID, or WP_Error.
	 */
	public function create_run( int $workflow_id, int $workflow_version, array $trigger_context = array(), string $correlation_id = '' ) {
		$result = $this->wpdb->insert(
			$this->runs_table,
			array(
				'workflow_id'      => $workflow_id,
				'workflow_version' => $workflow_version,
				'status'           => self::RUN_STATUS_QUEUED,
				'trigger_context'  => wp_json_encode( $trigger_context ),
				'started_at'       => 0,
				'finished_at'      => 0,
				'created_by'       => get_current_user_id() ?: null,
				'correlation_id'   => '' !== $correlation_id ? $correlation_id : null,
			),
			array( '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%s' )
		);

		if ( $result === false ) {
			return $this->build_error( 'ability_workflow_run_create_failed', __( 'Failed to create workflow run.', 'ai-post-scheduler' ) );
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update a run's status. Ignored once the run has reached a terminal status,
	 * so a late-arriving cron slice can never clobber a failed/completed/cancelled run.
	 *
	 * @param int    $run_id Run ID.
	 * @param string $status New status.
	 * @param array  $extra  Optional extra columns (started_at, finished_at).
	 * @return bool
	 */
	public function update_run_status( int $run_id, string $status, array $extra = array() ): bool {
		$update_data = array( 'status' => $status );
		$format      = array( '%s' );

		if ( array_key_exists( 'started_at', $extra ) ) {
			$update_data['started_at'] = (int) $extra['started_at'];
			$format[]                  = '%d';
		}

		if ( array_key_exists( 'finished_at', $extra ) ) {
			$update_data['finished_at'] = (int) $extra['finished_at'];
			$format[]                   = '%d';
		}

		$set_sql = array();
		foreach ( $update_data as $column => $value ) {
			$set_sql[] = "$column = %s";
		}

		$placeholders   = array_values( $update_data );
		$terminal_list  = "'" . implode( "','", array_map( 'esc_sql', self::RUN_TERMINAL_STATUSES ) ) . "'";
		$sql            = "UPDATE {$this->runs_table} SET " . implode( ', ', $set_sql ) . " WHERE id = %d AND status NOT IN ($terminal_list)";
		$placeholders[] = $run_id;

		$result = $this->wpdb->query( $this->wpdb->prepare( $sql, $placeholders ) );

		return false !== $result;
	}

	/**
	 * Fetch a run by ID.
	 *
	 * @param int $run_id Run ID.
	 * @return AIPS_Ability_Workflow_Run|null
	 */
	public function get_run( int $run_id ): ?AIPS_Ability_Workflow_Run {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->runs_table} WHERE id = %d", $run_id )
		);

		return $row ? AIPS_Ability_Workflow_Run::from_row( $row ) : null;
	}

	/**
	 * List runs for a workflow.
	 *
	 * @param int   $workflow_id Workflow ID.
	 * @param array $args        { status, per_page, page }
	 * @return array { items: AIPS_Ability_Workflow_Run[], total: int }
	 */
	public function list_runs( int $workflow_id, array $args = array() ): array {
		$defaults = array(
			'status'   => '',
			'per_page' => 20,
			'page'     => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$where_clauses = array( 'workflow_id = %d' );
		$where_args    = array( $workflow_id );

		if ( '' !== $args['status'] ) {
			$where_clauses[] = 'status = %s';
			$where_args[]    = $args['status'];
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );

		$total = (int) $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(*) FROM {$this->runs_table} $where_sql", $where_args ) );

		$per_page   = (int) $args['per_page'];
		$page       = max( 1, (int) $args['page'] );
		$limit_args = $where_args;
		$limit_args[] = $per_page;
		$limit_args[] = ( $page - 1 ) * $per_page;

		$sql  = "SELECT * FROM {$this->runs_table} $where_sql ORDER BY started_at DESC, id DESC LIMIT %d OFFSET %d";
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $limit_args ) );

		return array(
			'items' => array_map( array( 'AIPS_Ability_Workflow_Run', 'from_row' ), $rows ?: array() ),
			'total' => $total,
		);
	}

	// -----------------------------------------------------------------------
	// Step-run operations
	// -----------------------------------------------------------------------

	/**
	 * Create a step-run row.
	 *
	 * @param int                        $run_id         Parent run ID.
	 * @param int                        $workflow_id    Workflow ID.
	 * @param AIPS_Ability_Workflow_Step $step           The step being executed.
	 * @param array                      $input_snapshot Resolved input snapshot.
	 * @return int|WP_Error New step-run ID, or WP_Error.
	 */
	public function create_step_run( int $run_id, int $workflow_id, AIPS_Ability_Workflow_Step $step, array $input_snapshot = array() ) {
		$result = $this->wpdb->insert(
			$this->step_runs_table,
			array(
				'run_id'          => $run_id,
				'workflow_id'     => $workflow_id,
				'step_id'         => $step->id,
				'step_key'        => $step->step_key,
				'ability_name'    => $step->ability_name,
				'status'          => 'pending',
				'input_snapshot'  => wp_json_encode( $input_snapshot ),
				'output_snapshot' => wp_json_encode( array() ),
				'error'           => wp_json_encode( array() ),
				'started_at'      => 0,
				'finished_at'     => 0,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
		);

		if ( $result === false ) {
			return $this->build_error( 'ability_workflow_step_run_create_failed', __( 'Failed to create step run.', 'ai-post-scheduler' ) );
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update a step-run's status.
	 *
	 * @param int    $step_run_id Step-run ID.
	 * @param string $status      New status.
	 * @param array  $extra       Optional extra columns (output_snapshot, error, started_at, finished_at).
	 * @return bool
	 */
	public function update_step_run_status( int $step_run_id, string $status, array $extra = array() ): bool {
		$update_data = array( 'status' => $status );
		$format      = array( '%s' );

		if ( array_key_exists( 'input_snapshot', $extra ) ) {
			$update_data['input_snapshot'] = wp_json_encode( is_array( $extra['input_snapshot'] ) ? $extra['input_snapshot'] : array() );
			$format[]                      = '%s';
		}

		if ( array_key_exists( 'output_snapshot', $extra ) ) {
			$update_data['output_snapshot'] = wp_json_encode( is_array( $extra['output_snapshot'] ) ? $extra['output_snapshot'] : array() );
			$format[]                       = '%s';
		}

		if ( array_key_exists( 'error', $extra ) ) {
			$update_data['error'] = wp_json_encode( is_array( $extra['error'] ) ? $extra['error'] : array() );
			$format[]             = '%s';
		}

		if ( array_key_exists( 'started_at', $extra ) ) {
			$update_data['started_at'] = (int) $extra['started_at'];
			$format[]                  = '%d';
		}

		if ( array_key_exists( 'finished_at', $extra ) ) {
			$update_data['finished_at'] = (int) $extra['finished_at'];
			$format[]                   = '%d';
		}

		$result = $this->wpdb->update(
			$this->step_runs_table,
			$update_data,
			array( 'id' => $step_run_id ),
			$format,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get all step-runs for a run, in execution order.
	 *
	 * @param int $run_id Run ID.
	 * @return AIPS_Ability_Workflow_Step_Run[]
	 */
	public function get_step_runs( int $run_id ): array {
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare( "SELECT * FROM {$this->step_runs_table} WHERE run_id = %d ORDER BY id ASC", $run_id )
		);

		return array_map( array( 'AIPS_Ability_Workflow_Step_Run', 'from_row' ), $rows ?: array() );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Build a user-safe mutation error, attaching the last DB error when present.
	 *
	 * @param string $code    Error code.
	 * @param string $message Display message.
	 * @return WP_Error
	 */
	private function build_error( $code, $message ) {
		$error = new WP_Error( $code, $message );

		if ( ! empty( $this->wpdb->last_error ) ) {
			$error->add_data( array( 'db_error' => $this->wpdb->last_error ) );
		}

		return $error;
	}
}
