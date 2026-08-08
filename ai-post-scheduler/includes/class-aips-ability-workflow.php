<?php
/**
 * Ability Workflow DTO
 *
 * Immutable value object that wraps a row from the `aips_ability_workflows`
 * DB table.
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Ability_Workflow
 */
class AIPS_Ability_Workflow {

	public readonly int $id;
	public readonly string $uuid;
	public readonly string $name;
	public readonly ?string $description;
	public readonly string $status;
	public readonly string $trigger_type;
	public readonly array $trigger_config;
	public readonly array $settings;
	public readonly int $version;
	public readonly ?int $created_by;
	public readonly ?int $updated_by;
	public readonly int $created_at;
	public readonly int $updated_at;

	private function __construct(
		int $id,
		string $uuid,
		string $name,
		?string $description,
		string $status,
		string $trigger_type,
		array $trigger_config,
		array $settings,
		int $version,
		?int $created_by,
		?int $updated_by,
		int $created_at,
		int $updated_at
	) {
		$this->id             = $id;
		$this->uuid            = $uuid;
		$this->name            = $name;
		$this->description     = $description;
		$this->status          = $status;
		$this->trigger_type    = $trigger_type;
		$this->trigger_config  = $trigger_config;
		$this->settings        = $settings;
		$this->version         = $version;
		$this->created_by      = $created_by;
		$this->updated_by      = $updated_by;
		$this->created_at      = $created_at;
		$this->updated_at      = $updated_at;
	}

	/**
	 * Build an instance from a DB row object returned by wpdb.
	 *
	 * @param object $row A stdClass row from aips_ability_workflows.
	 * @return self
	 */
	public static function from_row( object $row ): self {
		return new self(
			(int) $row->id,
			(string) $row->uuid,
			(string) $row->name,
			isset( $row->description ) && $row->description !== '' ? (string) $row->description : null,
			(string) ( $row->status ?? 'draft' ),
			(string) ( $row->trigger_type ?? 'manual' ),
			self::decode_json( $row->trigger_config ?? null ),
			self::decode_json( $row->settings ?? null ),
			isset( $row->version ) ? (int) $row->version : 1,
			isset( $row->created_by ) && $row->created_by !== null ? (int) $row->created_by : null,
			isset( $row->updated_by ) && $row->updated_by !== null ? (int) $row->updated_by : null,
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
			'uuid'           => $this->uuid,
			'name'           => $this->name,
			'description'    => $this->description,
			'status'         => $this->status,
			'trigger_type'   => $this->trigger_type,
			'trigger_config' => $this->trigger_config,
			'settings'       => $this->settings,
			'version'        => $this->version,
			'created_by'     => $this->created_by,
			'updated_by'     => $this->updated_by,
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
