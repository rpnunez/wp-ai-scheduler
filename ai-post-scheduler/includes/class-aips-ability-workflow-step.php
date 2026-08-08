<?php
/**
 * Ability Workflow Step DTO
 *
 * Immutable value object that wraps a row from the
 * `aips_ability_workflow_steps` DB table.
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Ability_Workflow_Step
 */
class AIPS_Ability_Workflow_Step {

	public readonly int $id;
	public readonly int $workflow_id;
	public readonly string $step_key;
	public readonly ?string $name;
	public readonly string $ability_name;
	public readonly int $position;
	public readonly array $depends_on;
	public readonly array $input_map;
	public readonly array $condition_tree;
	public readonly ?string $output_alias;
	public readonly array $on_success;
	public readonly array $on_failure;
	public readonly array $retry_policy;
	public readonly int $created_at;
	public readonly int $updated_at;

	private function __construct(
		int $id,
		int $workflow_id,
		string $step_key,
		?string $name,
		string $ability_name,
		int $position,
		array $depends_on,
		array $input_map,
		array $condition_tree,
		?string $output_alias,
		array $on_success,
		array $on_failure,
		array $retry_policy,
		int $created_at,
		int $updated_at
	) {
		$this->id             = $id;
		$this->workflow_id    = $workflow_id;
		$this->step_key       = $step_key;
		$this->name           = $name;
		$this->ability_name   = $ability_name;
		$this->position       = $position;
		$this->depends_on     = $depends_on;
		$this->input_map      = $input_map;
		$this->condition_tree = $condition_tree;
		$this->output_alias   = $output_alias;
		$this->on_success     = $on_success;
		$this->on_failure     = $on_failure;
		$this->retry_policy   = $retry_policy;
		$this->created_at     = $created_at;
		$this->updated_at     = $updated_at;
	}

	/**
	 * Build an instance from a DB row object returned by wpdb.
	 *
	 * @param object $row A stdClass row from aips_ability_workflow_steps.
	 * @return self
	 */
	public static function from_row( object $row ): self {
		return new self(
			(int) $row->id,
			(int) $row->workflow_id,
			(string) $row->step_key,
			isset( $row->name ) && $row->name !== '' ? (string) $row->name : null,
			(string) $row->ability_name,
			isset( $row->position ) ? (int) $row->position : 0,
			self::decode_json( $row->depends_on ?? null ),
			self::decode_json( $row->input_map ?? null ),
			self::decode_json( $row->condition_tree ?? null ),
			isset( $row->output_alias ) && $row->output_alias !== '' ? (string) $row->output_alias : null,
			self::decode_json( $row->on_success ?? null ),
			self::decode_json( $row->on_failure ?? null ),
			self::decode_json( $row->retry_policy ?? null ),
			isset( $row->created_at ) ? (int) $row->created_at : 0,
			isset( $row->updated_at ) ? (int) $row->updated_at : 0
		);
	}

	/**
	 * Serialize for AJAX/UI responses.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'id'             => $this->id,
			'workflow_id'    => $this->workflow_id,
			'step_key'       => $this->step_key,
			'name'           => $this->name,
			'ability_name'   => $this->ability_name,
			'position'       => $this->position,
			'depends_on'     => $this->depends_on,
			'input_map'      => $this->input_map,
			'condition_tree' => $this->condition_tree,
			'output_alias'   => $this->output_alias,
			'on_success'     => $this->on_success,
			'on_failure'     => $this->on_failure,
			'retry_policy'   => $this->retry_policy,
			'created_at'     => $this->created_at,
			'updated_at'     => $this->updated_at,
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
