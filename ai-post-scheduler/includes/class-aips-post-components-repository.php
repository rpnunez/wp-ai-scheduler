<?php
/**
 * Post Components Repository
 *
 * Phase 1 backend repository for runtime post-component resolution.
 *
 * @package AI_Post_Scheduler
 * @since 2.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Post_Components_Repository {

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * @var string
	 */
	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . 'aips_content_components';
	}

	/**
	 * Return all active components that can participate in injection.
	 *
	 * @return object[]
	 */
	public function get_active_components() {
		$sql = "SELECT * FROM {$this->table_name} WHERE is_active = 1 ORDER BY id ASC";

		return $this->wpdb->get_results( $sql );
	}

	/**
	 * Populate Phase 1 columns for an existing component row.
	 *
	 * @param int   $component_id Component ID.
	 * @param array $data Extended component data.
	 * @return bool
	 */
	public function update_extended_fields( $component_id, array $data ) {
		$component_id = absint( $component_id );
		if ( $component_id < 1 ) {
			return false;
		}

		$update_data = array();
		$formats     = array();

		if ( array_key_exists( 'slug', $data ) ) {
			$update_data['slug'] = sanitize_title( (string) $data['slug'] );
			$formats[]           = '%s';
		}

		if ( array_key_exists( 'status', $data ) ) {
			$status = sanitize_key( (string) $data['status'] );
			if ( ! in_array( $status, array( 'active', 'draft' ), true ) ) {
				$status = 'draft';
			}
			$update_data['status'] = $status;
			$formats[]             = '%s';
		}

		if ( array_key_exists( 'content_mode', $data ) ) {
			$content_mode = sanitize_key( (string) $data['content_mode'] );
			if ( ! in_array( $content_mode, array( 'html', 'shortcode', 'template' ), true ) ) {
				$content_mode = 'html';
			}
			$update_data['content_mode'] = $content_mode;
			$formats[]                   = '%s';
		}

		if ( array_key_exists( 'content_payload', $data ) ) {
			$update_data['content_payload'] = wp_kses_post( (string) $data['content_payload'] );
			$formats[]                      = '%s';
		}

		if ( array_key_exists( 'media_payload', $data ) ) {
			$update_data['media_payload'] = wp_json_encode( $data['media_payload'] );
			$formats[]                    = '%s';
		}

		if ( array_key_exists( 'cta_payload', $data ) ) {
			$update_data['cta_payload'] = wp_json_encode( $data['cta_payload'] );
			$formats[]                  = '%s';
		}

		if ( empty( $update_data ) ) {
			return true;
		}

		$update_data['updated_at'] = AIPS_DateTime::now()->timestamp();
		$formats[]                 = '%d';

		return false !== $this->wpdb->update(
			$this->table_name,
			$update_data,
			array( 'id' => $component_id ),
			$formats,
			array( '%d' )
		);
	}
}
