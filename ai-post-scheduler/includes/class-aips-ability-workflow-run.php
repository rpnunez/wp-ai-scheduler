<?php
/**
 * Ability Workflow Run DTO
 *
 * Immutable value object that wraps a row from the
 * `aips_ability_workflow_runs` DB table.
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Ability_Workflow_Run
 */
class AIPS_Ability_Workflow_Run {

	public readonly int $id;
	public readonly int $workflow_id;
	public readonly int $workflow_version;
	public readonly string $status;
	public readonly array $trigger_context;
	public readonly int $started_at;
	public readonly int $finished_at;
	public readonly ?int $created_by;
	public readonly ?string $correlation_id;

	private function __construct(
		int $id,
		int $workflow_id,
		int $workflow_version,
		string $status,
		array $trigger_context,
		int $started_at,
		int $finished_at,
		?int $created_by,
		?string $correlation_id
	) {
		$this->id               = $id;
		$this->workflow_id      = $workflow_id;
		$this->workflow_version = $workflow_version;
		$this->status           = $status;
		$this->trigger_context  = $trigger_context;
		$this->started_at       = $started_at;
		$this->finished_at      = $finished_at;
		$this->created_by       = $created_by;
		$this->correlation_id   = $correlation_id;
	}

	/**
	 * Build an instance from a DB row object returned by wpdb.
	 *
	 * @param object $row A stdClass row from aips_ability_workflow_runs.
	 * @return self
	 */
	public static function from_row( object $row ): self {
		return new self(
			(int) $row->id,
			(int) $row->workflow_id,
			isset( $row->workflow_version ) ? (int) $row->workflow_version : 1,
			(string) ( $row->status ?? 'queued' ),
			self::decode_json( $row->trigger_context ?? null ),
			isset( $row->started_at ) ? (int) $row->started_at : 0,
			isset( $row->finished_at ) ? (int) $row->finished_at : 0,
			isset( $row->created_by ) && $row->created_by !== null ? (int) $row->created_by : null,
			isset( $row->correlation_id ) && $row->correlation_id !== '' ? (string) $row->correlation_id : null
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
			'workflow_id'      => $this->workflow_id,
			'workflow_version' => $this->workflow_version,
			'status'           => $this->status,
			'trigger_context'  => $this->trigger_context,
			'started_at'       => $this->started_at,
			'finished_at'      => $this->finished_at,
			'created_by'       => $this->created_by,
			'correlation_id'   => $this->correlation_id,
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
