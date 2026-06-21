<?php
/**
 * Content Enhancement Repository
 *
 * Option-backed persistence for low-volume content enhancements.
 *
 * @package AI_Post_Scheduler
 * @since 2.9.2
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Content_Enhancement_Repository
 *
 * Stores enhancement definitions in a structured option for the first version.
 */
class AIPS_Content_Enhancement_Repository {

	/**
	 * Option name used for enhancement records.
	 *
	 * @var string
	 */
	private $option_name = 'aips_content_enhancements';

	/**
	 * Get all enhancements.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		$records = AIPS_Config::get_instance()->get_option( $this->option_name, array() );
		if ( ! is_array( $records ) ) {
			return array();
		}

		return array_values( array_map( array( 'AIPS_Content_Enhancement', 'from_array' ), array_filter( $records, 'is_array' ) ) );
	}

	/**
	 * Find an enhancement by ID.
	 *
	 * @param string $id Enhancement ID.
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
	 * Get active content enhancements.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function active(): array {
		return array_values( array_filter( $this->all(), function( $record ) {
			return ! empty( $record['is_active'] );
		} ) );
	}

	/**
	 * Find an enhancement by slug.
	 *
	 * @param string $slug Enhancement slug.
	 * @return array<string, mixed>|null
	 */
	public function find_by_slug( string $slug ): ?array {
		$slug = sanitize_title( $slug );
		foreach ( $this->all() as $record ) {
			if ( isset( $record['slug'] ) && $record['slug'] === $slug ) {
				return $record;
			}
		}

		return null;
	}

	/**
	 * Save an enhancement.
	 *
	 * @param array<string, mixed> $data Enhancement data.
	 * @return array<string, mixed> Saved enhancement.
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
	 * Delete an enhancement by ID.
	 *
	 * @param string $id Enhancement ID.
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
	 * Toggle an enhancement active state.
	 *
	 * @param string $id        Enhancement ID.
	 * @param bool   $is_active Desired active state.
	 * @return array<string, mixed>|null Updated enhancement, or null when missing.
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
	 * Normalize enhancement data for storage.
	 *
	 * @param array<string, mixed> $data Enhancement data.
	 * @param string               $id   Enhancement ID.
	 * @param int                  $now  Current timestamp.
	 * @return array<string, mixed>
	 */
	private function normalize( array $data, string $id, int $now ): array {
		$config     = AIPS_Config::get_instance();
		$allowlist  = $config->get_option( 'aips_content_enhancement_provider_allowlist', array() );
		$provider   = sanitize_key( $data['provider'] ?? 'custom' );
		$is_allowed = empty( $allowlist ) || in_array( $provider, $allowlist, true );
		$type       = sanitize_key( $data['type'] ?? $provider );
		$types      = array( 'embed', 'calculator', 'ticker', 'code_playground', 'cta_card', 'comparison_table', 'shortcode' );
		$name       = sanitize_text_field( $data['name'] ?? '' );
		$slug       = sanitize_title( $data['slug'] ?? $name );

		return array(
			'id'              => $id,
			'slug'            => $slug ? $slug : sanitize_key( $id ),
			'name'            => $name,
			'type'            => in_array( $type, $types, true ) ? $type : 'embed',
			'provider'        => $is_allowed ? $provider : 'custom',
			'disclosure_text' => sanitize_textarea_field( $data['disclosure_text'] ?? $config->get_option( 'aips_content_enhancement_default_disclosure_text' ) ),
			'cta_text'        => sanitize_text_field( $data['cta_text'] ?? $config->get_option( 'aips_content_enhancement_default_cta_text' ) ),
			'use_case'        => sanitize_textarea_field( $data['use_case'] ?? '' ),
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

		return sanitize_key( uniqid( 'aips-enhancement-', true ) );
	}
}
