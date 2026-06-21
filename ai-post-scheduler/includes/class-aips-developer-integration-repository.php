<?php
/**
 * Developer Integration Repository
 *
 * Option-backed persistence for low-volume developer integrations.
 *
 * @package AI_Post_Scheduler
 * @since 2.9.2
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Developer_Integration_Repository
 *
 * Stores integration definitions in a structured option for the first version.
 */
class AIPS_Developer_Integration_Repository {

	/**
	 * Option name used for integration records.
	 *
	 * @var string
	 */
	private $option_name = 'aips_developer_integrations';

	/**
	 * Get all integrations.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		$records = AIPS_Config::get_instance()->get_option( $this->option_name, array() );
		return is_array( $records ) ? array_values( array_filter( $records, 'is_array' ) ) : array();
	}

	/**
	 * Find an integration by ID.
	 *
	 * @param string $id Integration ID.
	 * @return array<string, mixed>|null
	 */
	public function find( string $id ): ?array {
		$id = sanitize_key( $id );
		foreach ( $this->all() as $record ) {
			if ( isset( $record['id'] ) && $record['id'] === $id ) {
				return $record;
			}
		}

		return null;
	}

	/**
	 * Save an integration.
	 *
	 * @param array<string, mixed> $data Integration data.
	 * @return array<string, mixed> Saved integration.
	 */
	public function save( array $data ): array {
		$records = $this->all();
		$id      = ! empty( $data['id'] ) ? sanitize_key( $data['id'] ) : $this->generate_id();
		$now     = AIPS_DateTime::now()->timestamp();
		$record  = $this->normalize( $data, $id, $now );
		$updated = false;

		foreach ( $records as $index => $existing ) {
			if ( isset( $existing['id'] ) && $existing['id'] === $id ) {
				$record['created_at'] = isset( $existing['created_at'] ) ? absint( $existing['created_at'] ) : $now;
				$records[ $index ]    = $record;
				$updated              = true;
				break;
			}
		}

		if ( ! $updated ) {
			$records[] = $record;
		}

		update_option( $this->option_name, $records, false );

		return $record;
	}

	/**
	 * Delete an integration by ID.
	 *
	 * @param string $id Integration ID.
	 * @return bool True when a record was deleted.
	 */
	public function delete( string $id ): bool {
		$id      = sanitize_key( $id );
		$records = $this->all();
		$kept    = array_values( array_filter( $records, function( $record ) use ( $id ) {
			return ! isset( $record['id'] ) || $record['id'] !== $id;
		} ) );

		if ( count( $kept ) === count( $records ) ) {
			return false;
		}

		update_option( $this->option_name, $kept, false );
		return true;
	}

	/**
	 * Toggle an integration active state.
	 *
	 * @param string $id        Integration ID.
	 * @param bool   $is_active Desired active state.
	 * @return array<string, mixed>|null Updated integration, or null when missing.
	 */
	public function toggle( string $id, bool $is_active ): ?array {
		$record = $this->find( $id );
		if ( ! $record ) {
			return null;
		}

		$record['is_active'] = $is_active;
		return $this->save( $record );
	}

	/**
	 * Normalize integration data for storage.
	 *
	 * @param array<string, mixed> $data Integration data.
	 * @param string               $id   Integration ID.
	 * @param int                  $now  Current timestamp.
	 * @return array<string, mixed>
	 */
	private function normalize( array $data, string $id, int $now ): array {
		$config     = AIPS_Config::get_instance();
		$allowlist  = $config->get_option( 'aips_developer_integration_provider_allowlist', array() );
		$provider   = sanitize_key( $data['provider'] ?? '' );
		$is_allowed = empty( $allowlist ) || in_array( $provider, $allowlist, true );

		return array(
			'id'              => $id,
			'name'            => sanitize_text_field( $data['name'] ?? '' ),
			'provider'        => $is_allowed ? $provider : '',
			'disclosure_text' => sanitize_textarea_field( $data['disclosure_text'] ?? $config->get_option( 'aips_developer_integration_default_disclosure_text' ) ),
			'cta_text'        => sanitize_text_field( $data['cta_text'] ?? $config->get_option( 'aips_developer_integration_default_cta_text' ) ),
			'endpoint_url'    => esc_url_raw( $data['endpoint_url'] ?? '' ),
			'is_active'       => ! empty( $data['is_active'] ),
			'created_at'      => isset( $data['created_at'] ) ? absint( $data['created_at'] ) : $now,
			'updated_at'      => $now,
		);
	}

	/**
	 * Generate a storage-safe ID.
	 *
	 * @return string
	 */
	private function generate_id(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return sanitize_key( wp_generate_uuid4() );
		}

		return sanitize_key( uniqid( 'aips-integration-', true ) );
	}
}
