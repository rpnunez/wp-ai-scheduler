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
	 * Column widths of the steps table. Values longer than these would be
	 * truncated or rejected by MySQL, so save_steps() refuses them up front.
	 */
	/**
	 * Largest stored snapshot (run trigger, step input or step output), in bytes.
	 * Larger ones are shortened with a marker.
	 */
	const SNAPSHOT_MAX_BYTES = 262144;

	/**
	 * Characters kept of a long text value when a snapshot is shortened.
	 */
	const SNAPSHOT_TEXT_PREVIEW = 1000;

	const STEP_KEY_MAX_LENGTH     = 100;
	const OUTPUT_ALIAS_MAX_LENGTH = 100;
	const ABILITY_NAME_MAX_LENGTH = 191;
	const STEP_NAME_MAX_LENGTH    = 255;

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Whether the open atomic section is a savepoint inside the caller's
	 * transaction rather than a transaction of its own.
	 *
	 * @var bool
	 */
	private $use_savepoint = false;

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

		// Settings (max steps, destructive gate, ...) are part of the definition
		// a run was dispatched against, so changing them bumps the version just
		// like saving steps does. The executor fails runs whose version moved.
		if ( array_key_exists( 'settings', $data ) ) {
			$this->bump_version( $workflow_id );
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
	 * Delete a workflow together with its steps, runs and step runs.
	 *
	 * Normal deletion keeps runs for audit; this is for disposable workflows
	 * such as the Stress Test fixtures. The History rows of the deleted runs are
	 * not touched: their IDs are returned so the caller can remove them.
	 *
	 * @param int $workflow_id Workflow ID.
	 * @return array{runs:int, history_ids:int[]}
	 */
	public function delete_workflow_with_runs( int $workflow_id ): array {
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare( "SELECT id, history_id FROM {$this->runs_table} WHERE workflow_id = %d", $workflow_id )
		);

		$run_ids     = array();
		$history_ids = array();

		foreach ( $rows ?: array() as $row ) {
			$run_ids[] = (int) $row->id;

			if ( (int) $row->history_id > 0 ) {
				$history_ids[] = (int) $row->history_id;
			}
		}

		if ( ! empty( $run_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $run_ids ), '%d' ) );

			$this->wpdb->query( $this->wpdb->prepare( "DELETE FROM {$this->step_runs_table} WHERE run_id IN ($placeholders)", $run_ids ) );
			$this->wpdb->query( $this->wpdb->prepare( "DELETE FROM {$this->runs_table} WHERE id IN ($placeholders)", $run_ids ) );
		}

		$this->delete_workflow( $workflow_id );

		return array(
			'runs'        => count( $run_ids ),
			'history_ids' => $history_ids,
		);
	}

	/**
	 * IDs of workflows whose name starts with a prefix.
	 *
	 * @param string $prefix Name prefix.
	 * @return int[]
	 */
	public function get_workflow_ids_by_name_prefix( string $prefix ): array {
		$ids = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT id FROM {$this->workflows_table} WHERE name LIKE %s ORDER BY id ASC",
				$this->wpdb->esc_like( $prefix ) . '%'
			)
		);

		return array_map( 'intval', $ids ?: array() );
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

			$saved = $this->save_steps( $new_id, $step_payload );

			if ( is_wp_error( $saved ) ) {
				// Do not leave a half-copied workflow behind.
				$this->delete_workflow( $new_id );

				return $saved;
			}
		}

		return $new_id;
	}

	// -----------------------------------------------------------------------
	// Step operations
	// -----------------------------------------------------------------------

	/**
	 * Replace all steps for a workflow and bump its version.
	 *
	 * Every step is validated before anything is deleted, and the delete plus
	 * the inserts run in one transaction, so a failure part-way through can no
	 * longer leave the workflow with no steps or only some of them.
	 *
	 * @param int   $workflow_id Workflow ID.
	 * @param array $steps       Array of step field arrays (step_key, name, ability_name,
	 *                            position, depends_on, input_map, condition_tree,
	 *                            output_alias, on_success, on_failure, retry_policy).
	 * @return bool|WP_Error
	 */
	public function save_steps( int $workflow_id, array $steps ) {
		$now  = time();
		$rows = array();

		foreach ( array_values( $steps ) as $index => $step ) {
			$valid = $this->validate_step_for_storage( $step );

			if ( is_wp_error( $valid ) ) {
				return $valid;
			}

			$rows[] = array(
				'workflow_id'    => $workflow_id,
				'step_key'       => (string) $step['step_key'],
				'name'           => isset( $step['name'] ) && '' !== (string) $step['name'] ? (string) $step['name'] : null,
				'ability_name'   => (string) $step['ability_name'],
				'position'       => isset( $step['position'] ) ? (int) $step['position'] : $index,
				'depends_on'     => wp_json_encode( isset( $step['depends_on'] ) && is_array( $step['depends_on'] ) ? array_values( $step['depends_on'] ) : array() ),
				'input_map'      => wp_json_encode( isset( $step['input_map'] ) && is_array( $step['input_map'] ) ? $step['input_map'] : array() ),
				'condition_tree' => wp_json_encode( isset( $step['condition_tree'] ) && is_array( $step['condition_tree'] ) ? $step['condition_tree'] : array() ),
				'output_alias'   => isset( $step['output_alias'] ) && '' !== (string) $step['output_alias'] ? (string) $step['output_alias'] : null,
				'on_success'     => wp_json_encode( isset( $step['on_success'] ) && is_array( $step['on_success'] ) ? $step['on_success'] : array( 'strategy' => 'continue' ) ),
				'on_failure'     => wp_json_encode( isset( $step['on_failure'] ) && is_array( $step['on_failure'] ) ? $step['on_failure'] : array( 'strategy' => 'stop' ) ),
				'retry_policy'   => wp_json_encode( isset( $step['retry_policy'] ) && is_array( $step['retry_policy'] ) ? $step['retry_policy'] : array( 'attempts' => 0, 'backoff_seconds' => 0 ) ),
				'created_at'     => $now,
				'updated_at'     => $now,
			);
		}

		$this->begin_transaction();

		if ( false === $this->wpdb->delete( $this->steps_table, array( 'workflow_id' => $workflow_id ), array( '%d' ) ) ) {
			return $this->rollback_with_error( 'ability_workflow_step_save_failed', __( 'Failed to save workflow step.', 'ai-post-scheduler' ) );
		}

		foreach ( $rows as $row ) {
			$result = $this->wpdb->insert(
				$this->steps_table,
				$row,
				array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
			);

			if ( $result === false ) {
				return $this->rollback_with_error( 'ability_workflow_step_save_failed', __( 'Failed to save workflow step.', 'ai-post-scheduler' ) );
			}
		}

		if ( ! $this->bump_version( $workflow_id, $now ) ) {
			return $this->rollback_with_error( 'ability_workflow_step_save_failed', __( 'Failed to save workflow step.', 'ai-post-scheduler' ) );
		}

		$this->commit_transaction();

		return true;
	}

	/**
	 * Check a step payload against the steps table's constraints.
	 *
	 * @param mixed $step Step payload.
	 * @return true|WP_Error
	 */
	private function validate_step_for_storage( $step ) {
		if ( ! is_array( $step ) || empty( $step['step_key'] ) || empty( $step['ability_name'] ) ) {
			return new WP_Error( 'ability_workflow_step_invalid', __( 'Each step requires a step_key and ability_name.', 'ai-post-scheduler' ) );
		}

		$limits = array(
			'step_key'     => self::STEP_KEY_MAX_LENGTH,
			'ability_name' => self::ABILITY_NAME_MAX_LENGTH,
			'output_alias' => self::OUTPUT_ALIAS_MAX_LENGTH,
			'name'         => self::STEP_NAME_MAX_LENGTH,
		);

		foreach ( $limits as $field => $max ) {
			if ( isset( $step[ $field ] ) && is_scalar( $step[ $field ] ) && $this->string_length( (string) $step[ $field ] ) > $max ) {
				return new WP_Error(
					'ability_workflow_step_invalid',
					/* translators: 1: field name, 2: maximum length */
					sprintf( __( 'Step field "%1$s" must be at most %2$d characters.', 'ai-post-scheduler' ), $field, $max )
				);
			}
		}

		return true;
	}

	/**
	 * Character length of a string, multibyte-safe when mbstring is present.
	 *
	 * @param string $value Value to measure.
	 * @return int
	 */
	private function string_length( string $value ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}

	/**
	 * Increment a workflow's version.
	 *
	 * @param int      $workflow_id Workflow ID.
	 * @param int|null $now         Timestamp for updated_at; defaults to now.
	 * @return bool
	 */
	private function bump_version( int $workflow_id, ?int $now = null ): bool {
		$now = $now ?? time();

		return false !== $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->workflows_table} SET version = version + 1, updated_at = %d WHERE id = %d",
				$now,
				$workflow_id
			)
		);
	}

	/**
	 * Roll back the open transaction and build the mutation error.
	 *
	 * @param string $code    Error code.
	 * @param string $message Display message.
	 * @return WP_Error
	 */
	private function rollback_with_error( $code, $message ) {
		$error = $this->build_error( $code, $message );

		$this->wpdb->query( $this->use_savepoint ? 'ROLLBACK TO SAVEPOINT aips_ability_steps' : 'ROLLBACK' );

		return $error;
	}

	/**
	 * Open an atomic section.
	 *
	 * A plain START TRANSACTION would implicitly commit a transaction the
	 * caller already has open (autocommit off, as in the WordPress test
	 * harness), so in that case a savepoint is used instead.
	 *
	 * @return void
	 */
	private function begin_transaction(): void {
		$this->use_savepoint = '0' === (string) $this->wpdb->get_var( 'SELECT @@autocommit' );

		$this->wpdb->query( $this->use_savepoint ? 'SAVEPOINT aips_ability_steps' : 'START TRANSACTION' );
	}

	/**
	 * Close the atomic section opened by begin_transaction().
	 *
	 * @return void
	 */
	private function commit_transaction(): void {
		$this->wpdb->query( $this->use_savepoint ? 'RELEASE SAVEPOINT aips_ability_steps' : 'COMMIT' );
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
				'trigger_context'  => $this->prepare_payload( $trigger_context, 'trigger' ),
				'started_at'       => 0,
				'finished_at'      => 0,
				'created_by'       => get_current_user_id() ?: null,
				'correlation_id'   => '' !== $correlation_id ? $correlation_id : null,
				'created_at'       => time(),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%d' )
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
	 * Remember which History container records a run, so every cron
	 * invocation of the run reuses one container instead of opening a new one.
	 *
	 * @param int $run_id     Run ID.
	 * @param int $history_id History container ID.
	 * @return bool
	 */
	public function set_run_history_id( int $run_id, int $history_id ): bool {
		return false !== $this->wpdb->update(
			$this->runs_table,
			array( 'history_id' => $history_id ),
			array( 'id' => $run_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Number of runs that still need work (queued or running).
	 *
	 * @return int
	 */
	public function count_open_runs(): int {
		return (int) $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->runs_table} WHERE status IN ('queued','running')"
		);
	}

	/**
	 * IDs of the runs that still need work, oldest first.
	 *
	 * @param int $limit Maximum number of IDs.
	 * @return int[]
	 */
	public function get_open_run_ids( int $limit = 50 ): array {
		$ids = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT id FROM {$this->runs_table} WHERE status IN ('queued','running') ORDER BY id ASC LIMIT %d",
				max(1, $limit)
			)
		);

		return array_map( 'intval', $ids ?: array() );
	}

	/**
	 * IDs of open runs that have shown no sign of life since `$cutoff`.
	 *
	 * A run's last activity is the latest of when it was created, started, or
	 * touched one of its step runs.
	 *
	 * @param int $cutoff Unix time; runs whose last activity is older are stale.
	 * @param int $limit  Maximum number of IDs.
	 * @return int[]
	 */
	public function get_stale_run_ids( int $cutoff, int $limit = 100 ): array {
		$ids = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT r.id FROM {$this->runs_table} r
				LEFT JOIN (
					SELECT run_id, MAX(GREATEST(COALESCE(started_at, 0), COALESCE(finished_at, 0))) AS last_activity
					FROM {$this->step_runs_table}
					GROUP BY run_id
				) s ON s.run_id = r.id
				WHERE r.status IN ('queued','running')
				AND GREATEST(COALESCE(r.created_at, 0), COALESCE(r.started_at, 0), COALESCE(s.last_activity, 0)) < %d
				ORDER BY r.id ASC
				LIMIT %d",
				$cutoff,
				max( 1, $limit )
			)
		);

		return array_map( 'intval', $ids ?: array() );
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

		$limit_sql  = '';
		$limit_args = $where_args;
		$per_page   = (int) $args['per_page'];

		// Consistent with list_workflows(): per_page <= 0 means "no limit",
		// not "LIMIT 0" (which would silently return zero rows).
		if ( $per_page > 0 ) {
			$page         = max( 1, (int) $args['page'] );
			$limit_sql    = 'LIMIT %d OFFSET %d';
			$limit_args[] = $per_page;
			$limit_args[] = ( $page - 1 ) * $per_page;
		}

		// $where_args always has at least the workflow_id filter, so this
		// always needs prepare() — unlike list_workflows()'s fully optional
		// WHERE clause.
		$sql  = "SELECT * FROM {$this->runs_table} $where_sql ORDER BY started_at DESC, id DESC $limit_sql";
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
	 * Redact and size-limit a payload before it is stored.
	 *
	 * Runs store what steps received and returned so the run history can show
	 * it. The `aips_ability_workflow_redact_payload` filter can remove or mask
	 * sensitive fields first. A payload that is still larger than
	 * SNAPSHOT_MAX_BYTES has its largest values replaced by short markers (text
	 * is cut to a preview) until it fits, and a `_truncated` key lists them, so
	 * the small fields a later step reads are kept. Redaction and shortening
	 * apply to what is stored only; steps in the same run slice keep the full
	 * values.
	 *
	 * @param array  $data Payload.
	 * @param string $kind 'trigger', 'step_input' or 'step_output'.
	 * @param int    $id   Run ID (trigger, step_input on create) or step-run ID, 0 when unknown.
	 * @return string JSON to store.
	 */
	public function prepare_payload( array $data, string $kind, int $id = 0 ): string {
		/**
		 * Filters a payload before it is stored with a workflow run.
		 *
		 * @param array  $data Payload (trigger context, step input or step output).
		 * @param string $kind 'trigger', 'step_input' or 'step_output'.
		 * @param int    $id   Related run or step-run ID, 0 when unknown.
		 */
		$filtered = apply_filters( 'aips_ability_workflow_redact_payload', $data, $kind, $id );

		if ( ! is_array( $filtered ) ) {
			$filtered = $data;
		}

		$json = wp_json_encode( $filtered );

		if ( false === $json ) {
			return (string) wp_json_encode( array( '_unencodable' => true ) );
		}

		if ( strlen( $json ) <= self::SNAPSHOT_MAX_BYTES ) {
			return $json;
		}

		return (string) wp_json_encode( $this->shrink_payload( $filtered, strlen( $json ) ) );
	}

	/**
	 * Replace the largest values of a payload with markers until it fits.
	 *
	 * @param array $data           Payload that is too large.
	 * @param int   $original_bytes Its encoded size.
	 * @return array
	 */
	private function shrink_payload( array $data, int $original_bytes ): array {
		$limit = self::SNAPSHOT_MAX_BYTES - 1024;
		$sizes = array();

		foreach ( $data as $key => $value ) {
			$sizes[ $key ] = strlen( (string) wp_json_encode( $value ) );
		}

		arsort( $sizes );

		$truncated = array();

		foreach ( $sizes as $key => $size ) {
			if ( strlen( (string) wp_json_encode( $data ) ) <= $limit || $size <= self::SNAPSHOT_TEXT_PREVIEW ) {
				break;
			}

			$data[ $key ] = is_string( $data[ $key ] )
				? mb_substr( $data[ $key ], 0, self::SNAPSHOT_TEXT_PREVIEW ) . ' ...[truncated]'
				: array( '_truncated' => true, '_original_bytes' => $size );
			$truncated[]  = (string) $key;
		}

		$data['_truncated'] = $truncated;

		if ( strlen( (string) wp_json_encode( $data ) ) > self::SNAPSHOT_MAX_BYTES ) {
			return array( '_truncated' => true, '_original_bytes' => $original_bytes );
		}

		return $data;
	}

	/**
	 * Delete finished runs, with their step runs, that ended before a cutoff.
	 *
	 * History rows are not touched: History has its own retention.
	 *
	 * @param int $cutoff Unix time; runs whose last activity is older are deleted.
	 * @param int $limit  Most runs to delete in one call.
	 * @return int Runs deleted.
	 */
	public function delete_terminal_runs_before( int $cutoff, int $limit = 500 ): int {
		$statuses     = self::RUN_TERMINAL_STATUSES;
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		$ids = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT id FROM {$this->runs_table}
				WHERE status IN ($placeholders)
				AND GREATEST(created_at, started_at, finished_at) < %d
				ORDER BY id ASC
				LIMIT %d",
				array_merge( $statuses, array( $cutoff, max( 1, $limit ) ) )
			)
		);

		$ids = array_map( 'intval', $ids ?: array() );

		if ( empty( $ids ) ) {
			return 0;
		}

		$id_placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$this->wpdb->query( $this->wpdb->prepare( "DELETE FROM {$this->step_runs_table} WHERE run_id IN ($id_placeholders)", $ids ) );
		$this->wpdb->query( $this->wpdb->prepare( "DELETE FROM {$this->runs_table} WHERE id IN ($id_placeholders)", $ids ) );

		return count( $ids );
	}

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
				'input_snapshot'  => $this->prepare_payload( $input_snapshot, 'step_input', $run_id ),
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
			$update_data['input_snapshot'] = $this->prepare_payload( is_array( $extra['input_snapshot'] ) ? $extra['input_snapshot'] : array(), 'step_input', $step_run_id );
			$format[]                      = '%s';
		}

		if ( array_key_exists( 'output_snapshot', $extra ) ) {
			$update_data['output_snapshot'] = $this->prepare_payload( is_array( $extra['output_snapshot'] ) ? $extra['output_snapshot'] : array(), 'step_output', $step_run_id );
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
	// Run locks
	// -----------------------------------------------------------------------

	/**
	 * Atomically claim an expiring lock stored in the options table.
	 *
	 * The first claim is an INSERT IGNORE on the unique option_name key. An
	 * expired lock is taken over with a compare-and-swap UPDATE on the value
	 * that was read, so two workers that both see the same expired lock cannot
	 * both win (the loser's UPDATE matches no row).
	 *
	 * @param string $key   Option name.
	 * @param string $token Ownership token.
	 * @param int    $ttl   Seconds until the lock may be taken over.
	 * @return bool True when this caller now holds the lock.
	 */
	public function acquire_lock( string $key, string $token, int $ttl ): bool {
		$now   = time();
		$value = $token . '|' . ( $now + max( 1, $ttl ) );
		$table = $this->wpdb->options;

		$inserted = $this->wpdb->query(
			$this->wpdb->prepare(
				"INSERT IGNORE INTO {$table} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
				$key,
				$value
			)
		);

		if ( 1 === (int) $inserted ) {
			$this->flush_option_cache( $key );
			return true;
		}

		$existing = $this->wpdb->get_var(
			$this->wpdb->prepare( "SELECT option_value FROM {$table} WHERE option_name = %s", $key )
		);

		if ( null === $existing ) {
			return false;
		}

		$parts  = explode( '|', (string) $existing );
		$expiry = isset( $parts[1] ) ? (int) $parts[1] : 0;

		if ( $expiry > $now ) {
			return false;
		}

		$swapped = $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$table} SET option_value = %s WHERE option_name = %s AND option_value = %s",
				$value,
				$key,
				$existing
			)
		);

		$this->flush_option_cache( $key );

		return 1 === (int) $swapped;
	}

	/**
	 * Push a held lock's expiry forward. Does nothing if the lock is no longer ours.
	 *
	 * @param string $key   Option name.
	 * @param string $token Ownership token.
	 * @param int    $ttl   Seconds from now.
	 * @return bool True when the lock was still ours and was extended.
	 */
	public function refresh_lock( string $key, string $token, int $ttl ): bool {
		$table   = $this->wpdb->options;
		$owner   = $this->wpdb->esc_like( $token ) . '|%';
		$updated = $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$table} SET option_value = %s WHERE option_name = %s AND option_value LIKE %s",
				$token . '|' . ( time() + max( 1, $ttl ) ),
				$key,
				$owner
			)
		);

		$this->flush_option_cache( $key );

		if ( 1 === (int) $updated ) {
			return true;
		}

		// MySQL reports 0 changed rows when the new value equals the old one
		// (a refresh within the same second), so confirm ownership directly.
		return null !== $this->wpdb->get_var(
			$this->wpdb->prepare( "SELECT option_id FROM {$table} WHERE option_name = %s AND option_value LIKE %s", $key, $owner )
		);
	}

	/**
	 * Release a lock, but only if this token still owns it.
	 *
	 * @param string $key   Option name.
	 * @param string $token Ownership token.
	 * @return void
	 */
	public function release_lock( string $key, string $token ): void {
		$table = $this->wpdb->options;

		$this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$table} WHERE option_name = %s AND option_value LIKE %s",
				$key,
				$this->wpdb->esc_like( $token ) . '|%'
			)
		);

		$this->flush_option_cache( $key );
	}

	/**
	 * Drop cached copies of an option after it was changed with raw SQL.
	 *
	 * @param string $key Option name.
	 * @return void
	 */
	private function flush_option_cache( string $key ): void {
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
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
