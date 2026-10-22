<?php
/**
 * Ability Workflow Step Run DTO
 *
 * Immutable value object that wraps a row from the
 * `aips_ability_workflow_step_runs` DB table.
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Ability_Workflow_Step_Run
 */
class AIPS_Ability_Workflow_Step_Run {

	public readonly int $id;
	public readonly int $run_id;
	public readonly int $workflow_id;
	public readonly ?int $step_id;
	public readonly string $step_key;
	public readonly string $ability_name;
	public readonly string $status;
	public readonly array $input_snapshot;
	public readonly array $output_snapshot;
	public readonly array $error;
	public readonly int $started_at;
	public readonly int $finished_at;

	private function __construct(
		int $id,
		int $run_id,
		int $workflow_id,
		?int $step_id,
		string $step_key,
		string $ability_name,
		string $status,
		array $input_snapshot,
		array $output_snapshot,
		array $error,
		int $started_at,
		int $finished_at
	) {
		$this->id              = $id;
		$this->run_id          = $run_id;
		$this->workflow_id     = $workflow_id;
		$this->step_id         = $step_id;
		$this->step_key        = $step_key;
		$this->ability_name    = $ability_name;
		$this->status          = $status;
		$this->input_snapshot  = $input_snapshot;
		$this->output_snapshot = $output_snapshot;
		$this->error           = $error;
		$this->started_at      = $started_at;
		$this->finished_at     = $finished_at;
	}

	/**
	 * Build an instance from a DB row object returned by wpdb.
	 *
	 * @param object $row A stdClass row from aips_ability_workflow_step_runs.
	 * @return self
	 */
	public static function from_row( object $row ): self {
		return new self(
			(int) $row->id,
			(int) $row->run_id,
			(int) $row->workflow_id,
			isset( $row->step_id ) && $row->step_id !== null ? (int) $row->step_id : null,
			(string) $row->step_key,
			(string) $row->ability_name,
			(string) ( $row->status ?? 'pending' ),
			self::decode_json( $row->input_snapshot ?? null ),
			self::decode_json( $row->output_snapshot ?? null ),
			self::decode_json( $row->error ?? null ),
			isset( $row->started_at ) ? (int) $row->started_at : 0,
			isset( $row->finished_at ) ? (int) $row->finished_at : 0
		);
	}

	/**
	 * Serialize for AJAX/UI responses.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'id'               => $this->id,
			'run_id'           => $this->run_id,
			'workflow_id'      => $this->workflow_id,
			'step_id'          => $this->step_id,
			'step_key'         => $this->step_key,
			'ability_name'     => $this->ability_name,
			'status'           => $this->status,
			'input_snapshot'   => $this->input_snapshot,
			'output_snapshot'  => $this->output_snapshot,
			'error'            => $this->error,
			'started_at'       => $this->started_at,
			'finished_at'      => $this->finished_at,
		);
	}

	/**
	 * Decode a nullable JSON column, defaulting to an empty array.
	 *
	 * @param mixed $value Raw column value.
	 * @return array
	 */
	private static function decode_json( $value ): array {
		if ( ! is_string( $value ) || $value === '' ) {
			return array();
		}

		$decoded = json_decode( $value, true );

		return is_array( $decoded ) ? $decoded : array();
	}
}
